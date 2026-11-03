# Device identification

How the e-shop learns which device (customer's browser) is visiting it, so the widget can load the customer's saved
delivery addresses and checkout choices from myorder.

## Why a round-trip is needed
myorder identifies the device by its own session cookie on the myorder domain (`DeviceProvider`, cookie lifetime 1 year).
The e-shop runs on a different domain, and browsers block 3rd party cookies, so a request from the e-shop page cannot
carry the myorder cookie. The browser must visit the myorder domain directly (top-level navigation in a popup window,
where the myorder cookie is first-party, `SameSite=Lax`), and myorder then redirects back to the e-shop with the device-id.

To make this safe, the e-shop first asks myorder (server-to-server, with the client JWT) for a one-time
**device identity request**. Only a redirect carrying that request id is accepted back by the middleware.

## Flow

```
Customer browser            E-shop + middleware               myorder-core
(e-shop page / popup)       (PHP session)                     (myorder session cookie)
      |                            |                                  |
1.    |--- opens e-shop page ----->|                                  |
      |                            |-- 2. POST /api/sys/device-identity-request (JWT)
      |                            |      {redirectUrlTemplate} ----->|  creates DeviceIdentityRequest
      |                            |<---- {deviceIdentityRequest} ----|
      |                            |  stores request id in session    |
      |<-- page with widget -------|                                  |
      |    (device-id empty)       |                                  |
      |                            |                                  |
3.    |-- customer opens widget: popup GET iskladApi.php?service=middleware&uri=identify-device
      |                            |                                  |
      |<-- 302 to myorder ---------|                                  |
4.    |--- GET /device/device-identity-request/{requestId} ---------->|
      |                                                               |  finds/creates Device by myorder cookie
      |                                                               |  fills redirectUrlTemplate
      |                                                               |  DELETES the request
5.    |<-- 302 to iskladApi.php?service=middleware&uri=receive-device-identity&_isklad_deviceId=...&_isklad_deviceIdentityRequestId=...
      |                            |                                  |
      |--- GET (that URL) -------->|                                  |
      |                            |  request id matches session?     |
      |                            |  -> store device-id in session   |
      |                            |  -> remove request id            |
      |<-- page that closes popup -|                                  |
      |                            |                                  |
6.    |-- widget polls GET iskladApi.php?service=middleware&uri=device-id
      |<-- {"id": "<deviceId>"} ---|                                  |
```

### 1. Customer opens the e-shop
The e-shop instantiates `IskladApp`. If the PHP session has neither a device-id nor a device identity request id
(`IskladApp::initialize()`), the middleware creates a new device identity request (step 2). This happens once per
PHP session.

If the session already has a device-id (customer was identified before in this session), steps 2-6 are skipped and the
device-id is passed to the widget directly (`device-id="<?= $app->getDeviceId() ?>"`).

### 2. Middleware requests a device identity request
`ApiClient::fetchDeviceIdentity()` calls myorder (server-to-server, with the client JWT):

```
POST https://core.prod.myorder.isklad.eu/api/sys/device-identity-request
Authorization: Bearer <client JWT>

{
  "redirectUrlTemplate": "https://your-eshop.com/iskladApi.php?service=middleware&uri=receive-device-identity&_isklad_deviceId={{ISKLAD_DEVICE_ID}}&_isklad_deviceIdentityRequestId={{ISKLAD_DEVICE_IDENTITY_REQUEST_ID}}"
}
```

myorder (`DeviceIdentityRequestSysController`) saves a `DeviceIdentityRequest` (UUID v7 id + `redirectUrlTemplate`)
and returns its id in `deviceIdentityRequest`. The middleware stores it in the PHP session under
`_isklad_deviceIdentityRequestId` (configurable by `keyDeviceIdentityRequestId`).

The client JWT is required, so only a registered e-shop can create requests, and the redirect target is fixed by the
e-shop server, not by the browser.

### 3. Widget opens the popup
When the customer opens the widget (`WidgetService::openWidget()`), the widget (`DeviceService::identifyDevice()`)
opens a small popup window:

```
GET https://your-eshop.com/iskladApi.php?service=middleware&uri=identify-device
```

The middleware (`DeviceIdentification::identifyDevice()`) reads the request id from the session and redirects the popup to:

```
302 https://core.prod.myorder.isklad.eu/device/device-identity-request/{deviceIdentityRequestId}
```

### 4. myorder identifies the device and deletes the request
`DeviceWebController::getDeviceIdentity()` on myorder:
1. loads the `DeviceIdentityRequest` by id (`404` if it does not exist, i.e. it was already used),
2. gets the current device by the myorder session cookie (`DeviceProvider::getCurrentDevice()`): the existing `Device`
   is reused (and its `lastUsedAt` updated), or a new `Device` is created and its id is stored in the myorder session,
3. replaces `{{ISKLAD_DEVICE_ID}}` and `{{ISKLAD_DEVICE_IDENTITY_REQUEST_ID}}` in the `redirectUrlTemplate`
   (`DeviceIdentityRequestFactory::buildRedirectUri()`),
4. **deletes the device identity request** (hard delete), so it cannot be used again,
5. redirects the popup back to the e-shop.

### 5. Middleware receives the device-id
```
GET https://your-eshop.com/iskladApi.php?service=middleware&uri=receive-device-identity&_isklad_deviceId=<deviceId>&_isklad_deviceIdentityRequestId=<requestId>
```

`DeviceIdentification::receiveDeviceIdentity()` accepts the device-id only if the request id from the URL equals the
one in the PHP session. Then it stores the device-id in the session under `_isklad_deviceId` (configurable by
`keyDeviceId`) and removes the request id from the session. The popup renders a page that closes itself
(unless `disabledPopupClose` is set).

### 6. Widget gets the device-id
While the popup is open, the widget (`DeviceService::fetchDeviceId()`) polls the middleware every 2 seconds
(max 3 attempts):

```
GET https://your-eshop.com/iskladApi.php?service=middleware&uri=device-id
-> {"id": "<deviceId>"}
```

Once it has the device-id, it loads the device's delivery addresses and favorites from myorder
(see [api.md](api.md#device-customers-browser)). On the next page loads, the device-id is already in the PHP session and
is passed to the widget directly.

## Lifetime
- The device identity request has **no expiration**. It is valid until it is used once (step 4 deletes it).
- On the e-shop side it lives as long as the PHP session. A new session creates a new request.
- The device-id lives in the e-shop PHP session. The myorder device itself is remembered by the myorder session cookie
  (1 year), so a new e-shop session gets the same device-id back after a new round-trip.

## Security
- Only the e-shop server (with the client JWT) can create a device identity request, and it sets the redirect URL itself.
- A request can be used only once, it is deleted on use.
- The middleware accepts the device-id only together with the request id stored in the same PHP session, so an attacker
  cannot inject a different device-id into the customer's session via a crafted link.
- The middleware endpoint must be on the same origin as the e-shop pages, because it relies on the same PHP session.

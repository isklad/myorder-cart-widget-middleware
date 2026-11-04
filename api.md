# APIs used by the widget and the middleware

This is the list of isklad APIs used by [myorder-cart-widget](https://github.com/isklad/myorder-cart-widget) and by this middleware.

The widget never calls isklad services directly. It calls the middleware endpoint (`middlewareUrl`, i.e. `iskladApi.php`) with:
- `service` - which service to call: `myorder` (default), `egon` or `middleware`,
- `uri` - path of the API on that service (the paths listed below),
- `query` - (optional) url encoded query string.

The middleware checks the CSRF token, attaches the client JWT (`Authorization: Bearer ...`) and forwards the request to the service.
See [API endpoint](README.md#api-endpoint).

Default domains (can be overridden in the ini file, see [Domain overrides](README.md#domain-overrides)):

| Service   | Default domain                        |
|-----------|---------------------------------------|
| `myorder` | `https://core.prod.myorder.isklad.eu` |
| `egon`    | `https://api.isklad.com`              |
| `auth`    | `https://auth.isklad.com`             |

## Called by the middleware itself
These requests are made server-side by the middleware (PHP), not by the widget.

| Method | Service   | API                                                    | Description                                                                                                                                                                                                                                                                           |
|--------|-----------|--------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `POST` | `auth`    | `/auth/access-token`                                   | Exchanges `clientId` and `clientSecret` for the client JWT (`accessToken`, `expireAt`). The token is cached in `dataDir` and attached to all proxied requests. See [Manage client auth token](README.md#manage-client-auth-token).                                                       |
| `POST` | `myorder` | `/api/sys/device-identity-request`                     | Creates a one-time device identity request with the `redirectUrlTemplate` (callback URL to the middleware). Returns the `deviceIdentityRequest` id, which is stored in session. See [Device identification request](README.md#device-identification-request).                             |
| `GET`  | `myorder` | `/device/device-identity-request/{deviceIdentityRequest}` | Not an API, but a page opened in the popup window (the middleware `identify-device` redirects to it). Identifies the device by the myorder cookie, deletes the one-time request and redirects back to the middleware (`receive-device-identity`) with the device-id. |
| `GET`  | `myorder` | `/widget/cart/shop/{eshopId}`                          | Not an API, but the widget JS file (`$app->env()->getWidgetJsUrl()`), in the version configured for the shop. Loaded by the e-shop page in a `<script>` tag.                                                                                                                       |

## Handled by the middleware (`service=middleware`)
Not forwarded to any service, see [API endpoint](README.md#api-endpoint).

| Method | `uri`                     | Description                                                                                                                                  |
|--------|---------------------------|----------------------------------------------------------------------------------------------------------------------------------------------|
| `GET`  | `identify-device`         | Opened by the widget in a popup window. Redirects to the device identification page on myorder (see above).                                 |
| `GET`  | `receive-device-identity` | Callback from myorder with the device-id and device-identity-request-id. Stores the device-id to session and closes the popup.              |
| `GET`  | `device-id`               | Returns the device-id from session: `{"id": "..."}`.                                                                                         |

## myorder (`service=myorder`)

### Shop and settings
| Method | API                                                                                                                         | Description                                                                                                                                                                                                                                       |
|--------|-----------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `GET`  | `/api/sys/shop/{eshopId}`                                                                                                   | Returns basic data of the shop (id, slug, shop name, locale, widgetOpenStrategy).                                                                                                                                                                  |
| `GET`  | `/api/sys/country/shop/{eshopId}`                                                                                           | Returns the list of all countries (name, code, intl locale) together with the shop's settings for each country, so the widget knows which countries the shop delivers to.                                                                         |
| `GET`  | `/api/sys/currency/code/{currencyCode}`                                                                                     | Returns the currency by its 3-letter ISO code (i.e. `EUR`): code, rounding precision and exchange rate to EUR.                                                                                                                                    |
| `GET`  | `/api/sys/shop-country-setting/shop/{eshopId}/country/{countryId}/order/weight/{weight}/price/{price}/currency/{currencyId}/lead-time/{leadTime}/vat/{vat}` | Returns the shop's checkout settings for the selected country: available delivery options (with prices calculated for the order weight, price, currency, lead time and VAT percentage), payment methods, checkout extras and rounding precision. `404` if the shop does not deliver to the country. |
| `GET`  | `/api/sys/delivery-point/delivery-option/{deliveryOptionIds}/closest/lat/{lat}/lon/{lon}`                                   | Returns the closest operating delivery points (pickup points, boxes) of the given delivery options (comma separated ids) to the given GPS location.                                                                                              |
| `GET`  | `/api/pub/translation/scope/widget/locale/{locale}`                                                                         | Returns the widget translations for the locale (i.e. `sk`, `en-GB`).                                                                                                                                                                              |
| `GET`  | `/api/pub/translation/scope/widget/locale/{locale}/hash`                                                                    | Returns only the hash of the widget translations, so the widget can check if its cached translations are still up to date.                                                                                                                        |

### Device (customer's browser)
The customer is identified by the device-id (see [Device-id callback handler](README.md#device-id-callback-handler)).

| Method   | API                                                                                                                                        | Description                                                                                                                                                                                                                           |
|----------|--------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `GET`    | `/api/sys/device-address/device/{deviceId}`                                                                                                | Returns the list of the customer's saved delivery addresses (person, country, address, main flag) for the device (or the user the device belongs to).                                                                                |
| `POST`   | `/api/sys/device-address`                                                                                                                  | Creates a new delivery address for the device.                                                                                                                                                                                        |
| `PUT`    | `/api/sys/device-address/{deliveryAddressId}`                                                                                              | Updates the delivery address.                                                                                                                                                                                                         |
| `DELETE` | `/api/sys/device-address/{deliveryAddressId}`                                                                                              | Deletes the delivery address (if it can be deleted).                                                                                                                                                                                  |
| `GET`    | `/api/sys/device-favorites/device/{deviceId}/shop-country-setting/{shopCountrySettingId}/order/weight/{weight}/price/{price}/currency/{currencyId}/lead-time/{leadTime}/vat/{vat}` | Returns the customer's last used checkout choices (delivery option, delivery address, delivery point / Packeta point, payment method, checkout extras), so the widget can prefill them. Empty if the device has no favorites yet. |
| `POST`   | `/api/sys/device-favorites`                                                                                                                | Creates or updates the customer's checkout choices for the device.                                                                                                                                                                    |
| `POST`   | `/api/sys/device-auth-link/{locale}`                                                                                                       | Starts the login by phone: if the phone number belongs to a known user (or an address with an order), it sends an SMS with a verification code (rate limited) and returns the device auth link.                                     |
| `POST`   | `/api/sys/device-auth-link/{deviceAuthLinkId}/verify`                                                                                      | Verifies the SMS code. On success the device is linked to the user (the user's addresses and favorites become available) and the user is returned, otherwise `403`.                                                                |
| `POST`   | `/api/sys/device-order`                                                                                                                    | Creates the order (fast checkout) from the signed cart and the customer's checkout choices, for the device.                                                                                                                          |

### User (logged-in user mode)
The widget can also run in `user` login mode inside a host app (i.e. free-app-pwa), where the host app makes the requests
with the user's JWT (`customRequest`), **not through this middleware**. In this mode the widget uses these APIs instead of the
device ones above (same functionality, but for the logged-in user):

| Method   | API                                                                                                                                         | Device equivalent                                   |
|----------|---------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------|
| `GET`    | `/api/prv/user-address`                                                                                                                     | `GET /api/sys/device-address/device/{deviceId}`     |
| `POST`   | `/api/prv/user-address`                                                                                                                     | `POST /api/sys/device-address`                      |
| `PUT`    | `/api/prv/user-address/{deliveryAddressId}`                                                                                                 | `PUT /api/sys/device-address/{deliveryAddressId}`   |
| `DELETE` | `/api/prv/user-address/{deliveryAddressId}`                                                                                                 | `DELETE /api/sys/device-address/{deliveryAddressId}`|
| `GET`    | `/api/prv/user-favorites/shop-country-setting/{shopCountrySettingId}/order/weight/{weight}/price/{price}/currency/{currencyId}/lead-time/{leadTime}/vat/{vat}` | `GET /api/sys/device-favorites/device/{deviceId}/...` |
| `POST`   | `/api/prv/user-favorites`                                                                                                                   | `POST /api/sys/device-favorites`                    |
| `POST`   | `/api/prv/device-auth-link/{locale}`                                                                                                        | `POST /api/sys/device-auth-link/{locale}`           |
| `POST`   | `/api/prv/user-order`                                                                                                                       | `POST /api/sys/device-order`                        |

## egon (`service=egon`)

| Method | API                               | Description                                                                                                                                                          |
|--------|-----------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `POST` | `/egoncart/address/validate`      | Validates the delivery address, returns `{"valid": bool, "errors": {...}}`.                                                                                          |
| `POST` | `/egoncart/address/lookup`        | Address autocomplete (used when the shop's address lookup service is `egon` instead of Google): searches streets/cities by `searchString` (optionally in `countryId`), returns up to 10 `addresses`. |
| `POST` | `/egoncart/packeta-point/validate` | Validates that the selected Packeta pickup point exists in Egon and is in operation, returns `{"valid": bool, "errors": {...}}`.                                     |

## Third-party APIs
Called by the widget directly from the browser, not through the middleware:
- Google Maps API (`googleApiKey`) - address autocomplete, geocoding and map.
- Packeta widget API (`https://widget.packeta.com/v6/pps/api/widget/v1/validate`) - Packeta pickup point selection and validation.

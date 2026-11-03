# myorder-cart-widget-middleware
This middleware service is required for secure communication with isklad services.

See the example implementation (and the widget documentation) here: https://github.com/isklad/eshop-one

See the list of isklad APIs used by the widget and the middleware here: [api.md](api.md)

See how the device (customer's browser) is identified here: [deviceIdRequest.md](deviceIdRequest.md)

## Requirements
- PHP >= 8.2 (required by `lcobucci/jwt` ^5.6)
- PHP extensions: `curl`, `json`
- PHP sessions (the middleware starts the session itself if it is not active)
- writable data directory for the cached client token (see `dataDir`)

## Installation
The package is not published on Packagist, add the repository to your `composer.json` first:
```json
{
  "repositories": {
    "myorder-cart-widget-middleware": {
      "type": "vcs",
      "url": "https://github.com/isklad/myorder-cart-widget-middleware.git"
    }
  }
}
```
Then install it:
```shell
composer require isklad/myorder-cart-widget-middleware
```

## Instantiation
To instantiate the middleware, you have to provide it with required env parameters.
Either directly pass arguments to the constructor, or provide an ini file with the arguments.

Direct example: 
```php
use Isklad\MyorderCartWidgetMiddleware\IskladApp;
use Isklad\MyorderCartWidgetMiddleware\IskladEnv;
use Isklad\MyorderCartWidgetMiddleware\Jwt\JwtConfig;

$app = new IskladApp(
    new IskladEnv(
        getenv('ISKLAD_CLIENT_ID'),     // clientId
        getenv('ISKLAD_CLIENT_SECRET'), // clientSecret
        123,                            // eshopId
        new JwtConfig(
            getenv('ISKLAD_PRIVATE_KEY'),            // base64 encoded private key
            getenv('ISKLAD_PRIVATE_KEY_PASSPHRASE'), // private key passphrase
            'PT8H'                                   // signed data expiration
        ),
        'https://your-eshop.com/iskladApi.php', // middlewareUrl
        __DIR__ . '/../data'                    // dataDir
    )
);
```
The remaining (optional) constructor arguments are, in order: `widgetDomain`, `myorderDomain`, `egonDomain`, `authDomain`,
`keyDeviceId`, `keyDeviceIdentityRequestId`, `keyCsrfToken`, `displayErrors`, `disabledCsrfTokenVerification`, `disabledPopupClose`.
See [Configuration in INI file](#configuration-in-ini-file).

Ini example: 
```php
$app = new IskladApp(
    IskladEnv::fromIniFile(__DIR__ . '/../env.ini')
);
```
See example ini file here: https://github.com/isklad/eshop-one/blob/main/env.dist.ini  
Please make sure the ini file is outside the public folder of your website, and never commit it to a repository, 
since it contains your clientSecret (password) and private key. Or use the direct option with values from environment variables.

### What happens on instantiation
Creating `IskladApp` is not free, so instantiate it only on the pages with the widget and in the API endpoint:
- the PHP session is started (if not active yet),
- the client auth token is loaded from `dataDir`, or fetched from isklad-auth server if missing or expiring (see [Manage client auth token](#manage-client-auth-token)),
- if the session has no device-id yet, a device identification request is created via API on myorder server (once per session, see [Device identification request](#device-identification-request)).

If the isklad services are unavailable or the credentials are wrong, the constructor throws `ApiClient\ApiError`.
Catch it, so your e-shop page still works (just without the widget):
```php
use Isklad\MyorderCartWidgetMiddleware\ApiClient\ApiError;

try {
    $app = new IskladApp(IskladEnv::fromIniFile(__DIR__ . '/../env.ini'));
} catch (ApiError $e) {
    error_log('iSklad widget unavailable: ' . $e->getMessage());
    $app = null; // do not render the widget
}
```

## API endpoint
You have to create an API endpoint somewhere on your website.

I.E: create a `iskladApi.php` file in the public directory on your website and call `$app->iskladController();` in it.
Don't forget to update the link to your iskladApi in your env.ini file (key: middlewareUrl).
Example: https://github.com/isklad/eshop-one/blob/main/public/iskladApi.php

**The endpoint must be on the same origin (scheme + domain + port) as the pages with the widget**, because it relies on the same PHP session (CSRF token, device-id).

The endpoint is called by the widget with these GET parameters:
- `service` - `myorder` (default), `egon` or `middleware`.
- `uri` - path of the requested API on the given service.
- `query` - (optional) url encoded query string passed to the service.

Requests to `myorder` and `egon` services must have a valid CSRF token in the `X-Isklad-CSRF-Token` header and are proxied with the client JWT.
The `middleware` service handles these `uri` values itself:
- `identify-device` - redirects the popup window to device identification on myorder server.
- `receive-device-identity` - callback from myorder server with the device-id (see [Device-id callback handler](#device-id-callback-handler)).
- `device-id` - returns the device-id from session as JSON: `{"id": "..."}`.

The list of all APIs called through the endpoint is in [api.md](api.md).

## What does it do
### Manage client auth token
If the JWT auth token is empty, expired or expires in less than 10 hours, it fetches a new one via API from isklad-auth server, using the clientId and clientSecret.
This JWT is then used for authorizing requests to isklad services like isklad-myorder.
The JWT token is saved/cached in a PHP file `ClientToken_<eshopId>.php` in the `dataDir` directory. 
Keep the `dataDir` outside the public folder of your website.

### Attach Authorization header to requests
Widget must fetch data like countries, delivery options, users' addresses etc. from isklad-myorder service. 
To be able to do that, the request must be made with valid JWT.
JWT cannot be exposed to public, because an attacker could use it to impersonate client requests.
Therefore, the widget makes request to the middleware, which then makes the request to the myorder service with JWT attached and passes the response. 
This way JWT is always kept secret.

### Manage CSRF token
To ensure only the widget communicates with the middleware, and not an attacker, all requests from widget to the middleware must have valid CSRF token attached.
CSRF token is generated by the middleware (`$app->getCsrfToken()`) and is passed to the widget ([see widget configuration options](https://github.com/isklad/eshop-one?tab=readme-ov-file#configuration-options)).
The CSRF token is saved to a session.

### Provide API for iSklad services (egon, myorder, auth etc..)
See section [API endpoint](#api-endpoint)

### Device-id callback handler
To be able to list and save users' addresses, we must identify the session on the myorder server.
Since the 3rd party cookies blocking policy is now a standard in browsers, we must do a round-trip to myorder service and back.
The myorder service will redirect back to the client with the device-id in a GET parameter.
The device-id is then stored to session (`$app->getDeviceId()`) and passed to the widget. 

### Device identification request
If there is no device-id saved in session, the middleware will create a request for identification of the device via API to myorder server, which will respond with a one-time device-identity-request-id.
The request contains the callback URL where the myorder service will redirect to with the correct device-id and device-identity-request-id.
When clicked upon the widget for the first time, it will open a popup window, which will make the round-trip to myorder server with device-identity-request-id, and receive the device-id in GET parameter (see [Device-id callback handler](#device-id-callback-handler)).
The popup closes itself after the device-id is received.

### Provide additional vars from ini
You may store more than just the vars necessary to instantiate the app in your ini file and then access them as a key=>value array.
Example:

ini: 
```ini
googleApiKey=yourGoogleApiKey
```
php:
```php
$app->env()->getIni()['googleApiKey']
```

### Signing data with private key
Sign data with private key that can be verified by 3rd party services via public key.
`getSigned()` returns `Lcobucci\JWT\Token\Plain`, use `->toString()` to get the token string.
Example:

```php
$app->getSigned(['foo' => 'bar'])->toString()
```

The token is signed with `RS256`, it contains the data in the `data` claim, the eshop id in the `sub` claim (`iss` is `shop-<eshopId>`), 
and expires after `tokenExpireInterval`.

If you do not yet have a private/public key pair, you may generate them for example with this script:
```shell
# Generate and encrypt the private key with a password
openssl genrsa -aes256 -out private.pem 4096

# Extract the public key from the generated private key
openssl rsa -in private.pem -pubout -out public.pem

# Base64 encode the private key for the privateKey ini value
base64 < private.pem | tr -d '\n'
```

Don't forget to save the new base64 encoded public key (`base64 < public.pem | tr -d '\n'`) in Egon-Fastcheckout settings: https://egon.isklad.com/client.  
Never share or commit the private key, only the public key is given to iSklad.

### Cart data format
The cart for the widget (fast checkout) is signed with `getSigned()`, use the models from `src/Model`:

```php
use Isklad\MyorderCartWidgetMiddleware\Model\Cart;

$cart = new Cart();
// ... fill the cart
$signedCart = $app->getSigned((array) $cart)->toString();
```

[`Cart`](src/Model/Cart.php):
- `orderExternalId` (string) - id of the order in your e-shop.
- `products` ([`Product`](src/Model/Product.php)[]) - products in the cart.
- `currency` (string) - 3-letter ISO currency code (i.e. `EUR`), it overrides the widget's `currency-code`.
- `weight` (int) - weight of the order.
- `buyer` ([`CartUser`](src/Model/CartUser.php), optional) - buyer's person and address, used to prefill the checkout.
- `seller` ([`CartUser`](src/Model/CartUser.php), optional) - seller's person and address (i.e. for `c2c` orders).

[`Product`](src/Model/Product.php): `id`, `name`, `imageUrl`, `description`, `price` (float), `quantity` (int), `leadTime` (int hours, optional).

[`CartUser`](src/Model/CartUser.php): `person` ([`Person`](src/Model/Person.php), optional), `address` ([`Address`](src/Model/Address.php), optional).

[`Person`](src/Model/Person.php): `name`, `surname`, `email`, `phone`, `phoneRegion` (i.e. `SK`).

[`Address`](src/Model/Address.php): `name`, `company`, `businessId`, `taxId`, `vatId`, `street`, `streetNumber`, `city`, `postalCode`.

Example: https://github.com/isklad/eshop-one/blob/main/src/Repository/CartRepository.php

## Customization
You may want to customize the following items in your ini file:

`keyDeviceId` - this is the key under which the device-id will be stored in session, and also the name of the GET parameter in the callback from device identification (see [Device-id callback handler](#device-id-callback-handler)).

`keyDeviceIdentityRequestId` - this is the key under which the device-identity-request-id will be stored in session, and also the name of the GET parameter in the callback from device identification.

`keyCsrfToken` - this is the key under which the CSRF token will be stored in session.

## Configuration in INI file
### Required
Widget will not work or will not work fully without these parameters:

`clientId` - your isklad client id.  
`clientSecret` - your isklad client secret.  
`eshopId` - your isklad e-shop id.  
`middlewareUrl` - the URL of the middleware API.  
`dataDir` - writable directory where the JWT token will be stored.  
`googleApiKey` - your google API key to use with Google Maps API (address lookup, map..). Not used by the middleware itself, read it via `$app->env()->getIni()['googleApiKey']`.

### Required for signing data (Fastcheckout cart):
`privateKey` - your base64 encoded private key (PEM) to sign JWT token.  
`privateKeyPassphrase` - your private key passphrase.  
`tokenExpireInterval` - (optional) how long the signed JWT token will be valid for, as [ISO 8601 duration](https://www.php.net/manual/en/dateinterval.construct.php), defaults to `PT8H`.

### Optional
If set, will override defaults. Mostly for development purposes.

#### Session keys
`keyDeviceId` - (optional) if set, will override the key under which the device-id will be stored in session. Default: `_isklad_deviceId`.  
`keyDeviceIdentityRequestId` - (optional) if set, will override the key under which the device-identity-request-id will be stored in session. Default: `_isklad_deviceIdentityRequestId`.  
`keyCsrfToken` - (optional) if set, will override the key under which the CSRF token will be stored in session. Default: `_isklad_csrf_token`.  

#### Domain overrides
`widgetDomain` - (optional) if set, will override the domain where the widget is hosted. Default: `https://core.prod.myorder.isklad.eu`.  
`myorderDomain` - (optional) if set, will override the domain where MyOrder is hosted. Default: `https://core.prod.myorder.isklad.eu`.  
`egonDomain` - (optional) if set, will override the domain where Egon service is hosted. Default: `https://api.isklad.com`.  
`authDomain` - (optional) if set, will override the domain where Auth service is hosted. Default: `https://auth.isklad.com`.

#### Development
`displayErrors` - (optional, do not use in production) if set to true, middleware will display PHP errors.  
`disabledCsrfTokenVerification` - (optional, do not use in production) if set to true, middleware will not verify CSRF token.  
`disabledPopupClose` - (optional, do not use in production) if set to true, middleware will not close popup window used for device identification.  

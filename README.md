# myorder-cart-widget-middleware
This middleware service is required for secure communication with isklad services.

## Installation
```shell
composer install isklad/myorder-cart-widget-middleware
```

## Instantiation
To instantiate the middleware, you have to provide it with required env parameters.
Either directly pass arguments to the constructor, or provide an ini file with the arguments.

Direct example: 
```php
$app = new \Isklad\MyorderCartWidgetMiddleware\IskladApp(
    new \Isklad\MyorderCartWidgetMiddleware\IskladEnv(
        '01921da3-83d2-7edd-9c38-ffd8f9e9db33',
        'yourSecretPass',
        123,
        'https://eshop-one-test.isklad.eu/iskladApi.php'
        __DIR__ . '/data',
    )
);
```

Ini example: 
```php
$app = new IskladApp(
    IskladEnv::fromIniFile(__DIR__ . '/../env.ini')
);
```
See example ini file here: https://github.com/isklad/eshop-one/blob/main/env.dist.ini
Please make sure the ini file is outside the public folder of your website, since it contains your clientSecret (password), or use the direct option.

## API endpoint
You have to create an API endpoint somewhere on your website.

I.E: create a `iskladApi.php` file in the public directory on your website and call `$app->iskladApiController();` in it.
Don't forget to update the link to your iskladApi in your env.ini file (key: middlewareUrl).
Example: https://github.com/isklad/eshop-one/blob/main/public/iskladApi.php

## What does it do
### Manage client auth token
If the JWT auth token is expired, expires in few hours or empty, it fetches a new one via API from isklad-auth server, using the clientId and clientSecret.
This JWT is then used for authorizing requests to isklad services like isklad-myorder.
The JWT token is saved/cached in a PHP file on the filesystem.

### Attach Authorization header to requests
Widget must fetch data like countries, delivery options, users' addresses etc. from isklad-myorder service. 
To be able to do that, the request must be made with valid JWT.
JWT cannot be exposed to public, because an attacker could use it to impersonate client requests.
Therefore, the widget makes request to the middleware, which then makes the request to the myorder service with JWT attached and passes the response. 
This way JWT is always kept secret.

### Manage CSRF token
To ensure only the widget communicates with the middleware, and not an attacker, all requests from widget to the middleware must have valid CSRF token attached.
CSRF token is generated by the middleware and is passed to the widget ([see widget configuration options](https://github.com/isklad/eshop-one?tab=readme-ov-file#configuration-options)).
The CSRF token is saved to a session.

### Provide API for iSklad services (egon, myorder, auth etc..)
See section [API endpoint](#api-endpoint)

### Device-id callback handler
To be able to list and save users' addresses, we must identify the session on the isklad-auth server.
Since the 3rd party cookies blocking policy is now a standard in browsers, we must do a round-trip to isklad-auth service and back.
The isklad-auth will redirect back to the client with the device-id in a GET parameter.
The device-id is then stored to session and passed to the widget. 

### Device identification request
If there is no device-id saved in session, the middleware will create a request for identification of the device via API to isklad-auth server, which will respond with a one-time device-identity-request-id.
The request contains the callback URL where the auth-server will redirect to with the correct device-id and device-identity-request-id.
When clicked upon the widget for the first time, it will open a popup window, which will make the round-trip to isklad-auth server with device-identity-request-id, and receive the device-id in GET parameter (see [Device-id callback handler](#device-id-callback-handler)).

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

## Customization
You may want to customize the following items in your ini file:

`keyDeviceId` - this is the key under which the device-id will be stored in session, and also the name of the GET parameter in the callback from device identification (see [Device-id callback handler](#device-id-callback-handler)).

`keyDeviceIdentityRequestId` - this is the key under which the device-identity-request-id will be stored in session, and also the name of the GET parameter in the callback from device identification.

`keyCsrfToken` - this is the key under which the device-id will be stored in session.

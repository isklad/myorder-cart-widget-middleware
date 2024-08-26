<?php
declare(strict_types=1);

namespace Isklad\MyorderCartWidgetMiddleware;

use RuntimeException;

final class IskladEnv
{
    /**
     * Url from which to fetch widget.
     */
    private string $widgetJsUrl;

    /**
     * ID of client in isklad-auth app.
     */
    private string $clientId;

    /**
     * Password for client.
     */
    private string $clientSecret;

    /**
     * URL from which to fetch client token.
     */
    private string $clientTokenUrl;

    /**
     * ID of eshop.
     */
    private int $eshopId = 0;

    /**
     * URL where to request device identification. Secured by client token.
     */
    private string $iskladApiDeviceIdentityRequestUrl;

    /**
     * This key will be used to store device id in session.
     * It will also be used to fetch the device ID from query param.
     * @see getIdentityRedirectUrlTemplate
     */
    private string $keyDeviceId;

    /**
     * This key will be used to store deviceIdentityRequestId to session.
     * It will also be used to fetch the device ID from query param.
     * @see getIdentityRedirectUrlTemplate
     */
    private string $keyDeviceIdentityRequestId;

    /**
     * This key will be used to store csrf token to session.
     */
    private string $keyCsrfToken;

    /**
     * Myorder backend.
     */
    private string $myorderDomain;

    public function __construct(
        string $clientId,
        string $clientSecret,
        int $eshopId,
        string $widgetJsUrl = 'https://myorder.isklad.eu/widget/cart/shop/',
        string $clientTokenUrl = 'https://auth.isklad.eu/auth/access-token',
        string $iskladApiDeviceIdentityRequestUrl = 'https://auth.isklad.eu/api/client/device-identity-request',
        string $myorderDomain = 'https://myorder.isklad.eu',
        string $keyDeviceId = '_isklad_deviceId',
        string $keyDeviceIdentityRequestId = '_isklad_deviceIdentityRequestId',
        string $keyCsrfToken = '_isklad_csrf_token',
        bool $displayErrors = false
    ) {
        if ($displayErrors) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
        }
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->eshopId = $eshopId;
        $this->widgetJsUrl = $widgetJsUrl . $eshopId;
        $this->clientTokenUrl = $clientTokenUrl;
        $this->keyDeviceId = $keyDeviceId;
        $this->keyDeviceIdentityRequestId = $keyDeviceIdentityRequestId;
        $this->keyCsrfToken = $keyCsrfToken;
        $this->myorderDomain = $myorderDomain;
        $this->iskladApiDeviceIdentityRequestUrl = $iskladApiDeviceIdentityRequestUrl;
    }

    /**
     * URL target for redirect with provided device id.
     *
     * The {{ISKLAD_DEVICE_ID}} will be translated into an actual device ID (UUID v7).
     * Example: https://myeshop.com?iskladDevice={{ISKLAD_DEVICE_ID}} will be redirected to
     *          https://myeshop.com?iskladDevice=01915014-a940-77ec-9b79-72380ecbc0b0
     */
    public function getIdentityRedirectUrlTemplate(): string
    {
        $protocol = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http') . '://';
        $uri = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $param = (empty($_GET) ? '?' : '&')
            . $this->keyDeviceId . '={{ISKLAD_DEVICE_ID}}'
            . '&' . $this->keyDeviceIdentityRequestId . '={{ISKLAD_DEVICE_IDENTITY_REQUEST_ID}}';

        return $protocol . $uri . $param;
    }

    /**
     * Data directory used to store tokens.
     */
    public function getDataDir(): string
    {
        $dir = __DIR__ . '/data';
        if (is_dir($dir)) {
            return $dir;
        }

        if (false === mkdir($dir, 0770, true) && false === is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        return $dir;
    }

    public function getWidgetJsUrl(): string
    {
        return sprintf($this->widgetJsUrl,);
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function getClientTokenUrl(): string
    {
        return $this->clientTokenUrl;
    }

    public function getEshopId(): int
    {
        return $this->eshopId;
    }

    public function getIskladApiDeviceIdentityRequestUrl(): string
    {
        return $this->iskladApiDeviceIdentityRequestUrl;
    }

    public function getKeyDeviceId(): string
    {
        return $this->keyDeviceId;
    }

    public function getKeyDeviceIdentityRequestId(): string
    {
        return $this->keyDeviceIdentityRequestId;
    }

    public function getKeyCsrfToken(): string
    {
        return $this->keyCsrfToken;
    }

    public function getMyorderDomain(): string
    {
        return $this->myorderDomain;
    }
}

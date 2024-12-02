<?php
declare(strict_types=1);

namespace Isklad\MyorderCartWidgetMiddleware;

final class IskladEnv
{
    /**
     * Parsed ini vars, (if this class is instantiated with an ini file).
     * @see self::fromIniFile()
     */
    private array $ini = [];

    /**
     * ID of client in isklad-auth app.
     */
    private string $clientId;

    /**
     * Password for client.
     */
    private string $clientSecret;

    /**
     * ID of eshop.
     */
    private int $eshopId;

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

    /**
     * Egon backend.
     */
    private string $egonDomain;

    /**
     * Auth backend.
     */
    private string $authDomain;

    /**
     * Writable directory where the access token will be stored.
     */
    private string $dataDir;

    /**
     * Url of the middleware endpoint.
     */
    private string $middlewareUrl;

    /**
     * Use this only for local development purposes. Never disable CSRF token verification in production.
     */
    private bool $disabledCsrfTokenVerification;

    public function __construct(
        string $clientId,
        string $clientSecret,
        int $eshopId,
        string $middlewareUrl,
        string $dataDir,
        ?string $myorderDomain = 'https://myorder.isklad.eu',
        ?string $egonDomain = 'https://api.isklad.eu',
        ?string $authDomain = 'https://auth.isklad.eu',
        ?string $keyDeviceId = '_isklad_deviceId',
        ?string $keyDeviceIdentityRequestId = '_isklad_deviceIdentityRequestId',
        ?string $keyCsrfToken = '_isklad_csrf_token',
        ?bool $displayErrors = false,
        ?bool $disabledCsrfTokenVerification = false
    ) {
        // required
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->eshopId = $eshopId;
        $this->dataDir = $dataDir;
        $this->middlewareUrl = $middlewareUrl;
        // optional
        $this->keyDeviceId = $keyDeviceId;
        $this->keyDeviceIdentityRequestId = $keyDeviceIdentityRequestId;
        $this->keyCsrfToken = $keyCsrfToken;
        $this->myorderDomain = $myorderDomain;
        $this->egonDomain = $egonDomain;
        $this->authDomain = $authDomain;
        $this->disabledCsrfTokenVerification = $disabledCsrfTokenVerification;

        if ($displayErrors) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
        }
    }

    public static function fromIniFile(string $filename): self
    {
        $ini = parse_ini_file($filename, false, INI_SCANNER_TYPED);

        $self = new self(
            $ini['clientId'] ?? null,
            $ini['clientSecret'] ?? null,
            $ini['eshopId'] ?? null,
            $ini['middlewareUrl'] ?? null,
            $ini['dataDir'] ?? null,
            $ini['myorderDomain'] ?? null,
            $ini['egonDomain'] ?? null,
            $ini['authDomain'] ?? null,
            $ini['keyDeviceId'] ?? null,
            $ini['keyDeviceIdentityRequestId'] ?? null,
            $ini['keyCsrfToken'] ?? null,
            $ini['displayErrors'] ?? null,
            $ini['disabledCsrfTokenVerification'] ?? null,
        );
        $self->ini = $ini;

        return $self;
    }

    /**
     * @noinspection PhpUnused
     */
    public function getIni(): array
    {
        return $this->ini;
    }

    /**
     * URL target for redirect with provided device id.
     *
     * The {{ISKLAD_DEVICE_ID}} will be translated into an actual device ID (UUID v7).
     */
    public function getIdentityRedirectUrlTemplate(): string
    {
        return $this->getMiddlewareUrl()
            . '?service=middleware'
            . '&uri=' . DeviceIdentification::URI_RECEIVE_DEVICE_IDENTITY
            . '&' . $this->keyDeviceId . '={{ISKLAD_DEVICE_ID}}'
            . '&' . $this->keyDeviceIdentityRequestId . '={{ISKLAD_DEVICE_IDENTITY_REQUEST_ID}}'
        ;
    }

    public function getDataDir(): string
    {
        return rtrim($this->dataDir, '/');
    }

    /**
     * Url from which to fetch widget.
     *
     * @noinspection PhpUnused
     */
    public function getWidgetJsUrl(): string
    {
        return $this->getMyorderDomain() . '/widget/cart/shop/' . $this->getEshopId();
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    /**
     * URL from which to fetch client token.
     */
    public function getClientTokenUrl(): string
    {
        return $this->getAuthDomain() . '/auth/access-token';
    }

    public function getEshopId(): int
    {
        return $this->eshopId;
    }

    /**
     * URL where to request device identification. Secured by client token.
     */
    public function getIskladApiDeviceIdentityRequestUrl(): string
    {
        return $this->getAuthDomain() . '/api/client/device-identity-request';
    }

    public function getIskladDeviceIdentificationUrl(string $deviceIdentityRequestId): string
    {
        return $this->getAuthDomain() . '/web/device/device-identity-request/' . $deviceIdentityRequestId;
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

    public function getEgonDomain(): string
    {
        return $this->egonDomain;
    }

    public function getAuthDomain(): string
    {
        return $this->authDomain;
    }

    public function getMiddlewareUrl(): string
    {
        return $this->middlewareUrl;
    }

    public function isDisabledCsrfTokenVerification(): bool
    {
        return $this->disabledCsrfTokenVerification;
    }
}

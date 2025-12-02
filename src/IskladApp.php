<?php
declare(strict_types=1);

namespace Isklad\MyorderCartWidgetMiddleware;

use Isklad\MyorderCartWidgetMiddleware\ApiClient\ApiClient;
use Isklad\MyorderCartWidgetMiddleware\ApiClient\ApiError;
use Isklad\MyorderCartWidgetMiddleware\Jwt\JwtService;
use Lcobucci\JWT\Token;

final class IskladApp
{
    private IskladEnv $env;
    private ApiClient $apiClient;
    private DeviceIdentification $deviceIdentification;
    private JwtService $jwtService;

    public function __construct(IskladEnv $env)
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            session_start();
        }
        $this->env = $env;
        $this->apiClient = new ApiClient($this->env);
        $this->jwtService = new JwtService($env);
        $this->deviceIdentification = new DeviceIdentification($env);
        $this->initialize();
    }

    public function getSigned(array $data): Token\Plain
    {
        return $this->jwtService->createJwt($data);
    }

    public function env(): IskladEnv
    {
        return $this->env;
    }

    public function iskladController(): void
    {
        if (!$this->env()->isDisabledCsrfTokenVerification()
            && ($_SERVER['HTTP_X_ISKLAD_CSRF_TOKEN'] ?? null) !== $this->getCsrfToken()) {
            http_response_code(403);
            exit;
        }

        switch ($_GET['service'] ?? '') {
            case 'middleware':
                $this->middlewareController((string) $_GET['uri']);

                return;
            case 'egon':
                $domain = $this->env()->getEgonDomain();
                break;
            case 'myorder':
            default:
                $domain = $this->env()->getMyorderDomain();
                break;
        }
        $url = $domain . ($_GET['uri'] ?? '');
        if ($_GET['query'] ?? null) {
            $url .= '?' . $_GET['query'];
        }
        header('Content-Type: application/json');
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $data = $method === 'GET' ? null : file_get_contents('php://input');
            $response = $this->apiClient->customRequest($method, $url, $data);

            echo json_encode($response);
        } catch (ApiError $apiError) {
            http_response_code($apiError->getHttpCode());
            echo json_encode([
                'error' => $apiError->getMessage(),
                'httpCode' => $apiError->getHttpCode(),
            ]);
        }
    }

    private function middlewareController(string $uri): void
    {
        switch ($uri) {
            case DeviceIdentification::URI_IDENTIFY_DEVICE:
                $this->deviceIdentification->identifyDevice();
                return;
            case DeviceIdentification::URI_RECEIVE_DEVICE_IDENTITY:
                $this->deviceIdentification->receiveDeviceIdentity();
                return;
            case 'device-id':
                header('Content-Type: application/json');
                echo json_encode([
                    'id' => $this->getDeviceId(),
                ]);
                return;
        }
    }

    public function getApiClient(): ApiClient
    {
        return $this->apiClient;
    }

    public function getDeviceId(): ?string
    {
        return $_SESSION[$this->env->getKeyDeviceId()] ?? null;
    }

    public function getCsrfToken(): string
    {
        if (empty($_SESSION[$this->env->getKeyCsrfToken()])) {
            $_SESSION[$this->env->getKeyCsrfToken()] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$this->env->getKeyCsrfToken()];
    }

    private function initialize(): void
    {
        if (empty($_SESSION[$this->env->getKeyDeviceId()])
            && empty($_SESSION[$this->env->getKeyDeviceIdentityRequestId()]))
        {
            $_SESSION[$this->env->getKeyDeviceIdentityRequestId()] = $this->getApiClient()->fetchDeviceIdentity();
        }
    }
}

<?php
declare(strict_types=1);

namespace Isklad\MyorderCartWidgetMiddleware;

final class IskladApp
{
    private IskladEnv $env;
    private ApiClient $apiClient;
    private DeviceIdentification $deviceIdentification;

    public function __construct(IskladEnv $env)
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            session_start();
        }
        $this->env = $env;
        $this->apiClient = new ApiClient($this->env);
        $this->deviceIdentification = new DeviceIdentification($env);
        $this->initialize();
    }

    public function env(): IskladEnv
    {
        return $this->env;
    }

    public function iskladController(): void
    {
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
        if (!$this->env()->isDisabledCsrfTokenVerification()
            && ($_SERVER['HTTP_X_ISKLAD_CSRF_TOKEN'] ?? null) !== $this->getCsrfToken()) {
            http_response_code(403);
            exit;
        }
        $url = $domain . $_GET['uri'];
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

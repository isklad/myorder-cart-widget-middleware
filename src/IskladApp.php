<?php
declare(strict_types=1);

namespace Isklad\MyorderCartWidgetMiddleware;

final class IskladApp
{
    private IskladEnv $env;
    private ApiClient $apiClient;
    private bool $showWidgetModal = false;

    public function __construct(IskladEnv $env)
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            session_start();
        }
        $this->env = $env;
        $this->apiClient = new ApiClient($this->env);
        $this->initialize();
    }

    public function env(): IskladEnv
    {
        return $this->env;
    }

    public function myorderApiController(): void
    {
        if (($_SERVER['HTTP_X_ISKLAD_CSRF_TOKEN'] ?? null) !== $this->getCsrfToken()) {
            http_response_code(403);
            exit;
        }
        switch ($_GET['service']) {
            case 'egon':
                $domain = $this->env()->getEgonDomain();
                break;
            case 'myorder':
            default:
                $domain = $this->env()->getMyorderDomain();
                break;
        }
        $url = $domain . $_GET['uri'];
        header('Content-Type: application/json');
        try {
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'POST':
                    $data = file_get_contents('php://input');
                    $response = $this->apiClient->post($url, $data);
                    break;
                case 'GET':
                    $response = $this->apiClient->get($url);
                    break;
                default:
                    $response = [];
            }
            echo json_encode($response);
        } catch (ApiError $apiError) {
            echo json_encode([
                'error' => $apiError->getMessage(),
                'httpCode' => $apiError->getHttpCode(),
            ]);
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

    public function getDeviceIdentityRequestId(): ?string
    {
        return $_SESSION[$this->env->getKeyDeviceIdentityRequestId()] ?? null;
    }

    public function getCsrfToken(): string
    {
        if (empty($_SESSION[$this->env->getKeyCsrfToken()])) {
            $_SESSION[$this->env->getKeyCsrfToken()] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$this->env->getKeyCsrfToken()];
    }

    public function isShowWidgetModal(): bool
    {
        return $this->showWidgetModal;
    }

    private function initialize(): void
    {
        if (isset($_GET[$this->env->getKeyDeviceId()])
            && isset($_GET[$this->env->getKeyDeviceIdentityRequestId()])
            && isset($_SESSION[$this->env->getKeyDeviceIdentityRequestId()])
            && $_GET[$this->env->getKeyDeviceIdentityRequestId()] === $_SESSION[$this->env->getKeyDeviceIdentityRequestId()])
        {
            $_SESSION[$this->env->getKeyDeviceId()] = $_GET[$this->env->getKeyDeviceId()];
            unset($_SESSION[$this->env->getKeyDeviceIdentityRequestId()]);
            $this->showWidgetModal = true;

            return;
        }
        if (empty($_SESSION[$this->env->getKeyDeviceId()])
            && empty($_SESSION[$this->env->getKeyDeviceIdentityRequestId()]))
        {
            $_SESSION[$this->env->getKeyDeviceIdentityRequestId()] = $this->getApiClient()->fetchDeviceIdentity();
        }
    }
}

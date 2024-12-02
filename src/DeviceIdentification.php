<?php
declare(strict_types=1);

namespace Isklad\MyorderCartWidgetMiddleware;

final class DeviceIdentification
{
    public const URI_IDENTIFY_DEVICE = 'identify-device';
    public const URI_RECEIVE_DEVICE_IDENTITY = 'receive-device-identity';

    private IskladEnv $env;

    public function __construct(IskladEnv $env)
    {
        $this->env = $env;
    }

    public function identifyDevice(): void
    {
        $deviceRequestId = $this->getDeviceIdentityRequestId();
        if ($deviceRequestId) {
            header('Location: ' . $this->env->getIskladDeviceIdentificationUrl($deviceRequestId));
            exit();
        }
        $this->closeWindow();
    }

    public function receiveDeviceIdentity(): void
    {
        if (isset($_GET[$this->env->getKeyDeviceId()])
            && isset($_GET[$this->env->getKeyDeviceIdentityRequestId()])
            && isset($_SESSION[$this->env->getKeyDeviceIdentityRequestId()])
            && $_GET[$this->env->getKeyDeviceIdentityRequestId()] === $_SESSION[$this->env->getKeyDeviceIdentityRequestId()])
        {
            $_SESSION[$this->env->getKeyDeviceId()] = $_GET[$this->env->getKeyDeviceId()];
            unset($_SESSION[$this->env->getKeyDeviceIdentityRequestId()]);
        }
        $this->closeWindow();
    }

    private function getDeviceIdentityRequestId(): ?string
    {
        return $_SESSION[$this->env->getKeyDeviceIdentityRequestId()] ?? null;
    }

    private function closeWindow(): void
    {
        echo '<script>window.close();</script>';
    }
}

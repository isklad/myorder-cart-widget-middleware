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
        $this->renderWindow();
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
        $this->renderWindow();
    }

    private function getDeviceIdentityRequestId(): ?string
    {
        return $_SESSION[$this->env->getKeyDeviceIdentityRequestId()] ?? null;
    }

    private function renderWindow(): void
    {
        echo '
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Loading Circle</title>
                <style>
                    body {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                        background-color: #f4f4f4;
                        margin: 0;
                    }
                    .loader {
                        width: 50px;
                        height: 50px;
                        border: 5px solid #ccc;
                        border-top: 5px solid #007bff;
                        border-radius: 50%;
                        animation: spin 1s linear infinite;
                    }
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                </style>
            </head>
            <body>
                <div class="loader"></div>
                <script>window.close();</script>
            </body>
            </html>
        ';
    }
}

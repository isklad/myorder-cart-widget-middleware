<?php
declare(strict_types=1);

namespace Isklad\MyorderCartWidgetMiddleware;

use DateTimeImmutable;

final class ApiClient
{
    private IskladEnv $env;
    private ClientTokenStorage $clientTokenStorage;
    private string $clientToken = '';

    /**
     * @throws ApiError
     */
    public function __construct(IskladEnv $env)
    {
        $this->env = $env;
        $this->clientTokenStorage = new ClientTokenStorage($env);
        $this->clientToken = $this->getClientToken();
    }

    /**
     * @throws ApiError
     */
    public function get(string $url): array
    {
        $ch = $this->getCurlHandle($url);

        return $this->handleResponse($ch);
    }

    /**
     * @param array|string $data - json string or array that will be encoded as json.
     * @throws ApiError
     */
    public function post(string $url, $data): array
    {
        if (is_array($data)) {
            $data = json_encode($data);
        }
        $ch = $this->getCurlHandle($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        return $this->handleResponse($ch);
    }

    public function fetchDeviceIdentity(): string
    {
        $url = $this->env->getIskladApiDeviceIdentityRequestUrl();
        $data = [
            'redirectUrlTemplate' => $this->env->getIdentityRedirectUrlTemplate(),
        ];

        /** @var array{redirectUrlTemplate:string, deviceIdentityRequest:string} $response */
        $response = $this->post($url, $data);

        return $response['deviceIdentityRequest'];
    }

    /**
     * @throws ApiError
     */
    private function getClientToken(): string
    {
        $tokenDto = $this->clientTokenStorage->getSavedTokenDto();
        $expireAt = new DateTimeImmutable($tokenDto['expireAt']);
        $inFewHours = new DateTimeImmutable('now + 10 hours');
        if ($expireAt < $inFewHours) {
            $tokenDto = $this->fetchAccessToken();
            $this->clientTokenStorage->saveToken($tokenDto);
        }

        return $tokenDto['accessToken'];
    }

    /**
     * @return array{accessToken:string, expireAt:string}
     * @throws ApiError
     */
    private function fetchAccessToken(): array
    {
        $url = $this->env->getClientTokenUrl();
        $data = [
            'clientId' => $this->env->getClientId(),
            'clientSecret' => $this->env->getClientSecret(),
        ];

        /** @var array{accessToken:string, expireAt:string} $tokenDto */
        $tokenDto = $this->post($url, $data);

        return $tokenDto;
    }

    /**
     * @return resource
     */
    private function getCurlHandle(string $url)
    {
        $ch = curl_init($url);
        $headers = ['Content-Type: application/json',];
        if ($this->clientToken) {
            $headers[] = 'Authorization: Bearer ' . $this->clientToken;
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        return $ch;
    }

    /**
     * @param resource $ch
     * @throws ApiError
     */
    private function handleResponse($ch): array
    {
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new ApiError(0, 'Request error:' . curl_error($ch));
        }
        $responseStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseData = json_decode($response, true);

        if ($responseStatus >= 400) {
            $error = $responseData['error'] ?? '';
            throw new ApiError($responseStatus, 'Request failed with status ' . $responseStatus . '. ' . $error);
        }

        return $responseData;
    }
}

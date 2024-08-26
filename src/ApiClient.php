<?php
declare(strict_types=1);

namespace Isklad\MyorderCartWidgetMiddleware;

use DateTimeImmutable;
use RuntimeException;

final class ApiClient
{
  private IskladEnv $env;
  private ClientTokenStorage $clientTokenStorage;
  private string $clientToken = '';

  public function __construct(IskladEnv $env)
  {
    $this->env = $env;
    $this->clientTokenStorage = new ClientTokenStorage();
    $this->clientToken = $this->getClientToken();
  }

  public function get(string $url): array
  {
    $ch = $this->getCurlHandle($url);

    return $this->handleResponse($ch);
  }

  /**
   * @param array|string $data - json string or array that will be encoded as json.
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

  private function getClientToken(): string
  {
    $tokenDto = $this->clientTokenStorage->getSavedTokenDto();
    $expiresAt = new DateTimeImmutable($tokenDto['expire_at']);
    $inFewHours = new DateTimeImmutable('now + 10 hours');
    if ($expiresAt < $inFewHours) {
      $tokenDto = $this->fetchAccessToken();
      $this->clientTokenStorage->saveToken($tokenDto);
    }

    return $tokenDto['access_token'];
  }

  /**
   * @return array{access_token:string, expire_at:string}
   */
  private function fetchAccessToken(): array
  {
    $url = $this->env->getClientTokenUrl();
    $data = [
      'id' => $this->env->getClientId(),
      'password' => $this->env->getClientSecret(),
    ];

    /** @var array{access_token:string, expire_at:string} $tokenDto */
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
   */
  private function handleResponse($ch): array
  {
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
      throw new RuntimeException('Error fetching device identity request:' . curl_error($ch));
    }
    curl_close($ch);

    return json_decode($response, true);
  }
}

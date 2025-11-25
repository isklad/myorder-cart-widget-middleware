<?php
declare(strict_types=1);

namespace Isklad\MyorderCartWidgetMiddleware\Jwt;

use DateInterval;
use Exception;
use Lcobucci\JWT\Signer;
use RuntimeException;

final class JwtConfig
{
    private const DEFAULT_TOKEN_EXPIRE_INTERVAL = 'PT8H';
    public const CLAIM_DATA = 'products';

    private string $privateKey;
    private string $privateKeyPassphrase;
    private string $tokenExpireInterval;

    public function __construct(
        string $privateKey,
        string $privateKeyPassphrase,
        ?string $tokenExpireInterval = self::DEFAULT_TOKEN_EXPIRE_INTERVAL
    ) {
        $this->privateKey = $privateKey;
        $this->privateKeyPassphrase = $privateKeyPassphrase;
        $this->tokenExpireInterval = $tokenExpireInterval;
    }

    public function getPrivateKey(): Signer\Key\InMemory
    {
        if ('' === $this->privateKey) {
            throw new RuntimeException('Private key is not set. Check env');
        }

        return Signer\Key\InMemory::base64Encoded($this->privateKey, $this->privateKeyPassphrase);
    }

    public function getSigner(): Signer\Rsa\Sha256
    {
        return new Signer\Rsa\Sha256();
    }

    public function getTokenExpireInterval(): DateInterval
    {
        if (empty($this->tokenExpireInterval)) {
            throw new RuntimeException('Token expire interval is not set. Check env');
        }
        try {
            return new DateInterval($this->tokenExpireInterval);
        } catch (Exception $e) {
            throw new RuntimeException('Token expire interval is invalid DateInterval string. Check env');
        }
    }
}

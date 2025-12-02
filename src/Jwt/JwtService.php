<?php
declare(strict_types=1);

namespace Isklad\MyorderCartWidgetMiddleware\Jwt;

use DateTimeImmutable;
use Isklad\MyorderCartWidgetMiddleware\IskladEnv;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Token\Builder;

final class JwtService
{
    private IskladEnv $env;

    public function __construct(
        IskladEnv $env
    ) {
        $this->env = $env;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createJwt(array $data): Token\Plain
    {
        $tokenBuilder = (new Builder(new JoseEncoder(), ChainedFormatter::default()));
        $now = new DateTimeImmutable();

        return $tokenBuilder
            ->issuedBy('shop-' . $this->env->getEshopId())
            ->relatedTo((string) $this->env->getEshopId())
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->add($this->env->getJwtConfig()->getTokenExpireInterval()))
            ->withClaim(JwtConfig::CLAIM_DATA, $data)
            ->getToken($this->env->getJwtConfig()->getSigner(), $this->env->getJwtConfig()->getPrivateKey())
        ;
    }
}

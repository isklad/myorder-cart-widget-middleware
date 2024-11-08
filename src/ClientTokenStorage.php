<?php
declare(strict_types=1);

namespace Isklad\MyorderCartWidgetMiddleware;

use RuntimeException;

final class ClientTokenStorage
{
    private IskladEnv $env;

    public function __construct(IskladEnv $env)
    {
        $this->env = $env;
    }

    /**
   * @return array{accessToken:string, expireAt:string}
   */
  public function getSavedTokenDto(): array
  {
    $tokenDto = ['accessToken' => '', 'expireAt' => '1 year ago'];
    $filename = $this->getClientTokenFilename();
    if (file_exists($filename)) {
        require $filename;
    }

    return $tokenDto;
  }

  private function getClientTokenFilename(): string
  {
    return $this->env->getDataDir() . '/ClientToken.php';
  }

  /**
   * @param array{accessToken:string, expireAt:string} $tokenDto
   */
  public function saveToken(array $tokenDto)
  {
    $tokenDtoVar = var_export($tokenDto, true);
    $fileContent = '<?php' . PHP_EOL;
    $fileContent .= "\$tokenDto = $tokenDtoVar;" . PHP_EOL;
    $fileContent .= '?>';

    $filename = $this->getClientTokenFilename();
    if (false === file_put_contents($filename, $fileContent)) {
      throw new RuntimeException('Error writing access token to data dir.');
    }
    if (function_exists('opcache_invalidate')) {
      opcache_invalidate($filename, true);
    }
  }
}

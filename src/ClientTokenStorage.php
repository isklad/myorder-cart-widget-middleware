<?php
declare(strict_types=1);

namespace Isklad\MyorderCartWidgetMiddleware;

use RuntimeException;

final class ClientTokenStorage
{
  /**
   * @return array{access_token:string, expire_at:string}
   */
  public function getSavedTokenDto(): array
  {
    $tokenDto = ['access_token' => '', 'expire_at' => '1 year ago'];
    $filename = $this->getClientTokenFilename();
    require $filename;

    return $tokenDto;
  }

  private function getClientTokenFilename(): string
  {
    return __DIR__ . '/data/ClientToken.php';
  }

  /**
   * @param array{access_token:string, expire_at:string} $tokenDto
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
  }
}

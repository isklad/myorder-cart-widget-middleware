<?php
declare(strict_types=1);

namespace Isklad\MyorderCartWidgetMiddleware;

use Exception;

class ApiError extends Exception
{
    private int $httpCode;

    public function __construct(int $httpCode, string $message)
    {
        parent::__construct($message);
        $this->httpCode = $httpCode;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}
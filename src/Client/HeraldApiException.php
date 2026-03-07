<?php

declare(strict_types=1);

namespace Herald\Bundle\Client;

final class HeraldApiException extends \RuntimeException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

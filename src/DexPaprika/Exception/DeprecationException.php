<?php

namespace DexPaprika\Exception;

/**
 * Exception thrown when a deprecated API endpoint or method is used
 */
class DeprecationException extends DexPaprikaApiException
{
    public function __construct(string $message = "", int $code = 410, ?array $errorData = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $errorData, $previous);
    }
} 
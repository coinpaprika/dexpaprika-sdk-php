<?php

declare(strict_types=1);

namespace DexPaprika\Exception;

use Exception;

/**
 * Exception thrown when a resource is not found
 */
class NotFoundException extends DexPaprikaApiException
{
    /**
     * @param string $message Error message
     * @param int $code Error code
     * @param array|null $errorData Additional error data
     * @param Exception|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Resource not found',
        int $code = 404,
        ?array $errorData = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $errorData, $previous);
    }
}

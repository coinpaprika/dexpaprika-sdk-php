<?php

declare(strict_types=1);

namespace DexPaprika\Exception;

use Exception;

/**
 * Base exception for all DexPaprika API exceptions
 */
class DexPaprikaApiException extends Exception
{
    /**
     * Additional error data from the API response
     */
    protected ?array $errorData = null;
    
    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $code Error code
     * @param array|null $errorData Additional error data
     * @param Exception|null $previous Previous exception
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        ?array $errorData = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorData = $errorData;
    }
    
    /**
     * Set additional error data
     *
     * @param array<string, mixed> $data The error data
     * @return self
     */
    public function setErrorData(array $data): self
    {
        $this->errorData = $data;
        return $this;
    }
    
    /**
     * Get additional error data
     *
     * @return array|null Error data from API response
     */
    public function getErrorData(): ?array
    {
        return $this->errorData;
    }
} 
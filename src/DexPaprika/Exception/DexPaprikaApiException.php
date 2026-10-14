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

    /**
     * Get the API's error code, when the response body names one.
     *
     * Reads the top-level "error" field (a short machine code such as
     * "plan_required" on the keyless-paging 403), not the legacy
     * {"error": "..."} message string some endpoints still send: that case
     * resolves to the exception message instead, so this returns null for it
     * too since there is no separate code to report.
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        $error = $this->errorData['error'] ?? null;

        return is_string($error) && $error !== '' ? $error : null;
    }

    /**
     * Get the plan tier the API says is required, when it names one.
     *
     * Present on the "plan_required" errors, for example the 403 a keyless
     * caller gets paging past the first page of a search endpoint.
     *
     * @return string|null
     */
    public function getRequiredTier(): ?string
    {
        $tier = $this->errorData['required_tier'] ?? null;

        return is_string($tier) && $tier !== '' ? $tier : null;
    }

    /**
     * Get the request parameter the API blamed for this error, when it names one.
     *
     * @return string|null
     */
    public function getParam(): ?string
    {
        $param = $this->errorData['param'] ?? null;

        return is_string($param) && $param !== '' ? $param : null;
    }
}
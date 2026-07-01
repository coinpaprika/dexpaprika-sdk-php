<?php

namespace DexPaprika\Exception;

/**
 * Exception thrown when a deprecated API endpoint or method is used.
 *
 * When the API response body carries a "replacement" hint, it is captured
 * automatically and exposed via getReplacement() so callers can migrate to the
 * current endpoint. The hint is also folded into the exception message.
 */
class DeprecationException extends DexPaprikaApiException
{
    /**
     * Suggested replacement endpoint or method, when the API provides one.
     */
    protected ?string $replacement = null;

    public function __construct(string $message = "", int $code = 410, ?array $errorData = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $errorData, $previous);

        // Self-populate the replacement hint from the API error body when present,
        // so a DeprecationException always knows where to point callers next.
        if (isset($errorData['replacement']) && is_string($errorData['replacement']) && $errorData['replacement'] !== '') {
            $this->replacement = $errorData['replacement'];
        }
    }

    /**
     * Get the suggested replacement endpoint or method, if the API provided one.
     *
     * @return string|null
     */
    public function getReplacement(): ?string
    {
        return $this->replacement;
    }

    /**
     * Set the suggested replacement endpoint or method.
     *
     * @param string $replacement
     * @return self
     */
    public function setReplacement(string $replacement): self
    {
        $this->replacement = $replacement;
        return $this;
    }
}

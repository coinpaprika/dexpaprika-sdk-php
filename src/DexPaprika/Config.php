<?php

declare(strict_types=1);

namespace DexPaprika;

use DexPaprika\Cache\CacheInterface;

/**
 * SDK Configuration
 */
class Config
{
    /**
     * Environment variable consulted when no key is set explicitly
     */
    public const API_KEY_ENV_VAR = 'DEXPAPRIKA_API_KEY';

    /**
     * Base API URL
     *
     * Serves keyless callers and registered free keys alike. Only Pro moves to
     * api-pro.dexpaprika.com, which callers set with setBaseUrl(). The host is
     * never inferred from the presence of a key: sending a free key to the Pro
     * host returns 403, so guessing would break the people who just registered.
     */
    private string $baseUrl = 'https://api.dexpaprika.com';

    /**
     * Optional API key. Null means keyless, which is the default and works.
     */
    private ?string $apiKey = null;
    
    /**
     * API Timeout in seconds
     */
    private int $timeout = 30;
    
    /**
     * Maximum number of retry attempts
     */
    private int $maxRetries = 5;
    
    /**
     * Retry delay values in milliseconds
     * 
     * @var array<int, int>
     */
    private array $retryDelays = [100, 500, 1000, 2500, 5000];
    
    /**
     * Cache implementation
     */
    private ?CacheInterface $cache = null;
    
    /**
     * Whether caching is enabled
     */
    private bool $cacheEnabled = false;
    
    /**
     * Default cache TTL in seconds (1 hour)
     */
    private int $cacheTtl = 3600;
    
    /**
     * Set the API key sent with every request
     *
     * Optional. Without one the client is keyless, which works and needs no
     * signup. An explicit key here beats the DEXPAPRIKA_API_KEY environment
     * variable.
     *
     * The key is sent as the entire Authorization value. There is no "Bearer"
     * prefix and no other scheme word: the API checksums the raw header, so a
     * scheme word returns 401. This is the most common reason a working key
     * looks broken.
     *
     * @param string|null $apiKey The API key, or null for keyless
     * @return self
     */
    public function setApiKey(?string $apiKey): self
    {
        $this->apiKey = self::sanitizeApiKey($apiKey);
        return $this;
    }

    /**
     * Get the API key in use, or null when running keyless
     *
     * Falls back to the DEXPAPRIKA_API_KEY environment variable when no key was
     * set explicitly.
     */
    public function getApiKey(): ?string
    {
        if ($this->apiKey !== null) {
            return $this->apiKey;
        }

        $fromEnv = getenv(self::API_KEY_ENV_VAR);

        return self::sanitizeApiKey($fromEnv === false ? null : $fromEnv);
    }

    /**
     * Trim a key and reject anything that could break out of a header
     *
     * A key carrying CR, LF or NUL is dropped rather than mangled: a mangled key
     * authenticates as nobody, and because the data endpoints ignore an
     * unreadable key instead of rejecting it, the caller would never find out.
     */
    private static function sanitizeApiKey(?string $apiKey): ?string
    {
        if ($apiKey === null) {
            return null;
        }

        $trimmed = trim($apiKey);

        if ($trimmed === '' || preg_match('/[\r\n\x00]/', $trimmed) === 1) {
            return null;
        }

        return $trimmed;
    }

    /**
     * Set the base API URL
     *
     * @param string $baseUrl The base URL for API requests
     * @return self
     */
    public function setBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        return $this;
    }
    
    /**
     * Get the base API URL
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
    
    /**
     * Set the request timeout
     *
     * @param int $timeout Timeout in seconds
     * @return self
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }
    
    /**
     * Get the request timeout
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }
    
    /**
     * Set the maximum number of retry attempts
     *
     * @param int $maxRetries Maximum number of retries
     * @return self
     */
    public function setMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }
    
    /**
     * Get the maximum number of retry attempts
     *
     * @return int
     */
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }
    
    /**
     * Set the retry delay values in milliseconds
     *
     * @param array<int, int> $retryDelays Array of retry delays in milliseconds
     * @return self
     */
    public function setRetryDelays(array $retryDelays): self
    {
        $this->retryDelays = $retryDelays;
        return $this;
    }
    
    /**
     * Get the retry delay values in milliseconds
     *
     * @return array<int, int>
     */
    public function getRetryDelays(): array
    {
        return $this->retryDelays;
    }
    
    /**
     * Get retry delay for a specific attempt (zero-based index)
     *
     * @param int $attempt Retry attempt number (0-based)
     * @return int Delay in milliseconds
     */
    public function getRetryDelayForAttempt(int $attempt): int
    {
        if ($attempt < 0) {
            return 0;
        }
        
        if ($attempt >= count($this->retryDelays)) {
            return end($this->retryDelays);
        }
        
        return $this->retryDelays[$attempt];
    }
    
    /**
     * Set the cache implementation
     *
     * @param CacheInterface|null $cache Cache implementation
     * @return self
     */
    public function setCache(?CacheInterface $cache): self
    {
        $this->cache = $cache;
        
        // Automatically enable caching if a cache is provided
        if ($cache !== null) {
            $this->cacheEnabled = true;
        }
        
        return $this;
    }
    
    /**
     * Get the cache implementation
     *
     * @return CacheInterface|null
     */
    public function getCache(): ?CacheInterface
    {
        return $this->cache;
    }
    
    /**
     * Enable or disable caching
     *
     * @param bool $enabled Whether caching is enabled
     * @return self
     */
    public function setCacheEnabled(bool $enabled): self
    {
        $this->cacheEnabled = $enabled;
        return $this;
    }
    
    /**
     * Check if caching is enabled
     *
     * @return bool
     */
    public function isCacheEnabled(): bool
    {
        return $this->cacheEnabled && $this->cache !== null;
    }
    
    /**
     * Set the default cache TTL
     *
     * @param int $ttl Time-to-live in seconds
     * @return self
     */
    public function setCacheTtl(int $ttl): self
    {
        $this->cacheTtl = $ttl;
        return $this;
    }
    
    /**
     * Get the default cache TTL
     *
     * @return int
     */
    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }
} 
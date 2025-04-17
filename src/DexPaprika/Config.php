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
     * Base API URL
     */
    private string $baseUrl = 'https://api.dexpaprika.com';
    
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
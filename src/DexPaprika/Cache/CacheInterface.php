<?php

declare(strict_types=1);

namespace DexPaprika\Cache;

/**
 * Cache interface for DexPaprika SDK
 * 
 * This is a simplified interface inspired by PSR-6 and PSR-16 for caching API responses
 */
interface CacheInterface
{
    /**
     * Get an item from the cache
     *
     * @param string $key Cache key
     * @return mixed|null The cached value or null if not found
     */
    public function get(string $key): mixed;
    
    /**
     * Store an item in the cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param int|null $ttl Time-to-live in seconds (null for default)
     * @return bool Success indicator
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;
    
    /**
     * Check if an item exists in the cache
     *
     * @param string $key Cache key
     * @return bool True if exists, false otherwise
     */
    public function has(string $key): bool;
    
    /**
     * Remove an item from the cache
     *
     * @param string $key Cache key
     * @return bool Success indicator
     */
    public function delete(string $key): bool;
    
    /**
     * Clear the entire cache
     *
     * @return bool Success indicator
     */
    public function clear(): bool;
} 
<?php

declare(strict_types=1);

namespace DexPaprika\Cache;

/**
 * Filesystem-based cache implementation
 */
class FilesystemCache implements CacheInterface
{
    /**
     * @var string Cache directory path
     */
    private string $cacheDir;
    
    /**
     * @var int Default TTL in seconds (7 days)
     */
    private int $defaultTtl;
    
    /**
     * Constructor
     *
     * @param string|null $cacheDir Cache directory (null for system temp directory)
     * @param int $defaultTtl Default time-to-live in seconds
     */
    public function __construct(?string $cacheDir = null, int $defaultTtl = 604800)
    {
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . '/dexpaprika_cache';
        $this->defaultTtl = $defaultTtl;
        
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function get(string $key): mixed
    {
        $filename = $this->getFilename($key);
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $content = file_get_contents($filename);
        $data = unserialize($content);
        
        // Check if item is expired
        if ($data['expires_at'] !== null && $data['expires_at'] < time()) {
            $this->delete($key);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $filename = $this->getFilename($key);
        $ttl = $ttl ?? $this->defaultTtl;
        
        $data = [
            'value' => $value,
            'expires_at' => $ttl > 0 ? time() + $ttl : null,
        ];
        
        return file_put_contents($filename, serialize($data)) !== false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $filename = $this->getFilename($key);
        
        if (file_exists($filename)) {
            return unlink($filename);
        }
        
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $files = glob($this->cacheDir . '/*.cache');
        
        if ($files === false) {
            return false;
        }
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        return true;
    }
    
    /**
     * Get the cache filename for a key
     *
     * @param string $key Cache key
     * @return string Full path to cache file
     */
    private function getFilename(string $key): string
    {
        $safeName = md5($key);
        return $this->cacheDir . '/' . $safeName . '.cache';
    }
} 
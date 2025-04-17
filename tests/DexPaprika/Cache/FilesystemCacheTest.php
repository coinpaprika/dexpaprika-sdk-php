<?php

namespace DexPaprika\Tests\Cache;

use DexPaprika\Cache\FilesystemCache;
use PHPUnit\Framework\TestCase;

class FilesystemCacheTest extends TestCase
{
    private FilesystemCache $cache;
    private string $cacheDir;
    
    protected function setUp(): void
    {
        // Create a temporary directory for testing
        $this->cacheDir = sys_get_temp_dir() . '/dexpaprika-cache-test-' . uniqid();
        mkdir($this->cacheDir, 0777, true);
        
        $this->cache = new FilesystemCache($this->cacheDir);
    }
    
    protected function tearDown(): void
    {
        // Clean up test directory
        $this->removeDirectory($this->cacheDir);
    }
    
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
    
    public function testSetAndGet(): void
    {
        $key = 'test_key';
        $data = ['foo' => 'bar', 'baz' => [1, 2, 3]];
        $ttl = 60; // 1 minute
        
        // Set data in cache
        $this->cache->set($key, $data, $ttl);
        
        // Verify data was stored and can be retrieved
        $this->assertTrue($this->cache->has($key));
        $this->assertEquals($data, $this->cache->get($key));
        
        // Verify the cache file was created
        $cacheFile = $this->cacheDir . '/' . md5($key) . '.cache';
        $this->assertFileExists($cacheFile);
    }
    
    public function testCacheExpiration(): void
    {
        $key = 'expiring_key';
        $data = 'test data';
        $ttl = 1; // 1 second
        
        // Set data with a short TTL
        $this->cache->set($key, $data, $ttl);
        
        // Verify it exists initially
        $this->assertTrue($this->cache->has($key));
        
        // Wait for expiration
        sleep(2);
        
        // Verify it's expired
        $this->assertFalse($this->cache->has($key));
        $this->assertNull($this->cache->get($key));
    }
    
    public function testDelete(): void
    {
        $key = 'delete_test';
        $data = 'data to delete';
        
        // Set data
        $this->cache->set($key, $data);
        
        // Verify it exists
        $this->assertTrue($this->cache->has($key));
        
        // Delete and verify it's gone
        $this->cache->delete($key);
        $this->assertFalse($this->cache->has($key));
    }
    
    public function testClear(): void
    {
        // Set multiple cache items
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');
        
        // Verify they exist
        $this->assertTrue($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));
        $this->assertTrue($this->cache->has('key3'));
        
        // Clear cache
        $this->cache->clear();
        
        // Verify all items are gone
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
        $this->assertFalse($this->cache->has('key3'));
    }
} 
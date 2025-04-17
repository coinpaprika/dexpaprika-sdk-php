<?php

namespace DexPaprika\Tests\Cache;

use DexPaprika\Cache\FilesystemCache;
use DexPaprika\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ApiCacheTest extends TestCase
{
    private string $cacheDir;
    
    protected function setUp(): void
    {
        // Create a temporary directory for cache testing
        $this->cacheDir = sys_get_temp_dir() . '/dexpaprika-cache-test-' . uniqid();
        mkdir($this->cacheDir, 0777, true);
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
    
    /**
     * Creates a mock HTTP client with predefined responses
     */
    private function createMockClient(array $responses): GuzzleClient
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        return new GuzzleClient(['handler' => $handlerStack]);
    }
    
    public function testApiResponseCaching(): void
    {
        // API response that should be cached
        $networkResponse = [
            'networks' => [
                [
                    'id' => 'ethereum',
                    'name' => 'Ethereum',
                    'symbol' => 'ETH'
                ]
            ]
        ];
        
        // Create a mock client that returns the response only once
        // If the response is requested again, we'll get an exception (since there's no more mocked responses)
        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($networkResponse)),
            // No second response - if caching works, we shouldn't need one
        ]);
        
        // Create DexPaprika client with caching enabled
        $client = new Client(null, $mockClient);
        
        // Setup cache
        $cache = new FilesystemCache($this->cacheDir);
        $client->setupCache($cache, 3600); // Cache for 1 hour
        
        // First API call should hit the mock and store in cache
        $result1 = $client->networks->getNetworks();
        
        // Second API call should use the cached result and not hit the mock
        $result2 = $client->networks->getNetworks();
        
        // Verify both results are identical
        $this->assertEquals($result1, $result2);
        $this->assertEquals($networkResponse, $result1);
        
        // Verify at least one cache file was created
        $files = scandir($this->cacheDir);
        $this->assertGreaterThan(2, count($files)); // More than "." and ".."
    }
    
    public function testCacheDisable(): void
    {
        // Setup two identical responses for the mock
        $networkResponse = [
            'networks' => [
                [
                    'id' => 'ethereum',
                    'name' => 'Ethereum',
                    'symbol' => 'ETH'
                ]
            ]
        ];
        
        // Create a mock handler that will be emptied when requests are made
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode($networkResponse)),
            new Response(200, [], json_encode($networkResponse))
        ]);
        
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new GuzzleClient(['handler' => $handlerStack]);
        
        // Create client with cache initially enabled
        $client = new Client(null, $mockClient);
        $cache = new FilesystemCache($this->cacheDir);
        $client->setupCache($cache, 3600);
        
        // First call
        $client->networks->getNetworks();
        
        // Now disable cache
        $client->getConfig()->setCacheEnabled(false);
        
        // Second call should hit the API again since cache is disabled
        $client->networks->getNetworks();
        
        // Verify we've used both mocked responses (handler should be empty)
        $this->assertEquals(0, $mockHandler->count());
    }
} 
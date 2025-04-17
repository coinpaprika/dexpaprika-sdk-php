<?php

namespace DexPaprika\Tests\Api;

use DexPaprika\Api\NetworksApi;
use DexPaprika\Config;
use DexPaprika\Exception\RateLimitException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class RetryTest extends TestCase
{
    /**
     * Test that the API client retries on rate limit errors
     */
    public function testRetryOnRateLimit(): void
    {
        // Create a history container to record requests
        $container = [];
        $history = Middleware::history($container);
        
        // First response is rate limit error, second is success
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => '1'], json_encode(['error' => 'Rate limit exceeded'])),
            new Response(200, [], json_encode(['networks' => [['id' => 'ethereum', 'name' => 'Ethereum']]]))
        ]);
        
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);
        
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);
        
        // Configure minimal retries to speed up test
        $config = new Config();
        $config->setMaxRetries(1);
        $config->setRetryDelays([100, 500, 1000, 2500, 5000]); // Specified retry delay sequence
        
        $api = new NetworksApi($httpClient, false, $config);
        
        // This should encounter rate limit, then retry and succeed
        $result = $api->getNetworks();
        
        // Verify success
        $this->assertArrayHasKey('networks', $result);
        $this->assertEquals('ethereum', $result['networks'][0]['id']);
        
        // Verify two requests were made (initial + 1 retry)
        $this->assertCount(2, $container);
    }
    
    /**
     * Test that API gives up after max retries
     */
    public function testMaxRetriesExceeded(): void
    {
        // Create responses: all rate limit errors
        $responses = array_fill(0, 3, new Response(
            429, 
            ['Retry-After' => '1'], 
            json_encode(['error' => 'Rate limit exceeded'])
        ));
        
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);
        
        // Configure for 2 retries with safe positive delays
        $config = new Config();
        $config->setMaxRetries(2);
        $config->setRetryDelays([100, 500, 1000, 2500, 5000]); // Specified retry delay sequence
        
        $api = new NetworksApi($httpClient, false, $config);
        
        // Expect an exception after all retries are exhausted
        try {
            $api->getNetworks();
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertInstanceOf(RateLimitException::class, $e);
            // Verify all responses were used (initial + 2 retries)
            $this->assertEquals(0, $mock->count());
        }
    }
    
    /**
     * Test that only certain status codes trigger a retry
     */
    public function testRetryOnlyForSpecificStatusCodes(): void
    {
        // Create responses: client error (400) then success (shouldn't retry on 400)
        $mock = new MockHandler([
            new Response(400, [], json_encode(['error' => 'Bad request'])),
            new Response(200, [], json_encode(['networks' => [['id' => 'ethereum']]]))
        ]);
        
        $container = [];
        $history = Middleware::history($container);
        
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);
        
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);
        
        $config = new Config();
        $config->setMaxRetries(2);
        $config->setRetryDelays([100, 500, 1000, 2500, 5000]); // Specified retry delay sequence
        
        $api = new NetworksApi($httpClient, false, $config);
        
        // This should fail without retrying
        try {
            $api->getNetworks();
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            // Verify only one request was made (no retries)
            $this->assertCount(1, $container);
            $this->assertEquals(1, $mock->count()); // One response left unused
        }
    }
    
    /**
     * Test exponential backoff timing with rate limit
     */
    public function testExponentialBackoff(): void
    {
        // Prepare response queue: several 429s followed by success
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => '1'], json_encode(['error' => 'Rate limit exceeded'])),
            new Response(429, ['Retry-After' => '1'], json_encode(['error' => 'Rate limit exceeded'])),
            new Response(429, ['Retry-After' => '1'], json_encode(['error' => 'Rate limit exceeded'])),
            new Response(200, [], json_encode(['networks' => [['id' => 'ethereum']]]))
        ]);
        
        // Record timestamps of each request
        $timestamps = [];
        $container = [];
        $history = Middleware::history($container);
        
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);
        
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);
        
        // Use the specified retry delay sequence
        $config = new Config();
        $config->setMaxRetries(3);
        $config->setRetryDelays([100, 500, 1000, 2500, 5000]); // Milliseconds
        
        $api = new NetworksApi($httpClient, false, $config);
        
        // Record start time
        $startTime = microtime(true);
        
        // Should eventually succeed after 3 retries
        $result = $api->getNetworks();
        
        // Record end time
        $endTime = microtime(true);
        
        // Verify success
        $this->assertArrayHasKey('networks', $result);
        
        // Verify we made all 4 requests (initial + 3 retries)
        $this->assertCount(4, $container);
        
        // Calculate total elapsed time
        $elapsedMs = ($endTime - $startTime) * 1000; // Convert to ms
        
        // Verify total time was at least a significant portion of the sum of delays
        // Expected minimum: a good portion of 100 + 500 + 1000 = 1600ms
        $this->assertGreaterThan(800, $elapsedMs, 
            "Total execution time should include meaningful backoff delays");
    }
} 
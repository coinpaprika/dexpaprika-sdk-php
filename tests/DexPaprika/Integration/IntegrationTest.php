<?php

namespace DexPaprika\Tests\Integration;

use DexPaprika\Client;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests that make real API calls.
 * 
 * These tests are skipped by default to avoid making actual API requests during normal test runs.
 * To run these tests, set the environment variable DEXPAPRIKA_RUN_INTEGRATION_TESTS=1
 * 
 * @group integration
 */
class IntegrationTest extends TestCase
{
    private ?Client $client = null;
    
    protected function setUp(): void
    {
        if (!$this->shouldRunIntegrationTests()) {
            $this->markTestSkipped('Integration tests are skipped. Set DEXPAPRIKA_RUN_INTEGRATION_TESTS=1 to run them.');
        }
        
        $this->client = new Client();
    }
    
    private function shouldRunIntegrationTests(): bool
    {
        return (bool) (getenv('DEXPAPRIKA_RUN_INTEGRATION_TESTS') ?: false);
    }
    
    public function testGetNetworks(): void
    {
        $networks = $this->client->networks->getNetworks();
        
        $this->assertIsArray($networks);
        $this->assertNotEmpty($networks['networks']);
        
        // Test with object transformation
        $networksObj = $this->client->networks->getNetworks(['asObject' => true]);
        $this->assertIsObject($networksObj);
        $this->assertIsArray($networksObj->networks);
    }
    
    public function testSearch(): void
    {
        $results = $this->client->search->search('ethereum');
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('tokens', $results);
        $this->assertArrayHasKey('pools', $results);
        $this->assertArrayHasKey('dexes', $results);
    }
    
    public function testGetStats(): void
    {
        $stats = $this->client->search->search('stats');
        
        $this->assertIsArray($stats);
    }
    
    public function testGetTokenDetails(): void
    {
        // Note: This test assumes that Ethereum network and WETH token exist
        $tokenDetails = $this->client->tokens->getTokenDetails(
            'ethereum', 
            '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2'
        );
        
        $this->assertIsArray($tokenDetails);
        $this->assertArrayHasKey('token', $tokenDetails);
    }
    
    public function testGetTokenPools(): void
    {
        // Note: This test assumes that Ethereum network and WETH token exist
        $tokenPools = $this->client->tokens->getTokenPools(
            'ethereum', 
            '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2',
            ['limit' => 3]
        );
        
        $this->assertIsArray($tokenPools);
        $this->assertArrayHasKey('pools', $tokenPools);
        $this->assertLessThanOrEqual(3, count($tokenPools['pools']));
    }
    
    public function testGetTopPools(): void
    {
        $topPools = $this->client->pools->getTopPools(['limit' => 3]);
        
        $this->assertIsArray($topPools);
        $this->assertArrayHasKey('pools', $topPools);
        $this->assertLessThanOrEqual(3, count($topPools['pools']));
    }
    
    public function testPagination(): void
    {
        $paginator = $this->client->createPaginator(
            $this->client->pools, 
            'getTopPools',
            ['limit' => 2]
        );
        
        $page1 = $paginator->getNextPage();
        $this->assertIsArray($page1);
        $this->assertArrayHasKey('pools', $page1);
        $this->assertCount(2, $page1['pools']);
        
        $page2 = $paginator->getNextPage();
        $this->assertIsArray($page2);
        $this->assertArrayHasKey('pools', $page2);
        $this->assertCount(2, $page2['pools']);
        
        // The IDs of pools in page 1 and page 2 should be different
        $page1Ids = array_map(fn($pool) => $pool['id'], $page1['pools']);
        $page2Ids = array_map(fn($pool) => $pool['id'], $page2['pools']);
        
        $this->assertEmpty(array_intersect($page1Ids, $page2Ids));
    }
    
    public function testObjectTransformation(): void
    {
        $this->client->setTransformResponses(true);
        
        $networks = $this->client->networks->getNetworks();
        $this->assertIsObject($networks);
        $this->assertIsArray($networks->networks);
        
        $stats = $this->client->stats->getStats();
        $this->assertIsObject($stats);
    }
} 
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
    
    public function testAdvancedSearchPoolsGlobal(): void
    {
        // Global advanced search across all networks with sorting + a filter.
        // Canonical sortBy/sortDir must be translated to order_by/sort on the wire.
        $result = $this->client->pools->advancedSearchPools([
            'limit' => 3,
            'sortBy' => 'volume_usd_24h',
            'sortDir' => 'desc',
            'priceUsdMin' => 0.5,
            'dexName' => 'uniswap_v3',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('has_next_page', $result);
        $this->assertArrayHasKey('next_cursor', $result);
        $this->assertArrayHasKey('query', $result);

        $this->assertNotEmpty($result['results']);
        $this->assertLessThanOrEqual(3, count($result['results']));

        // The query echo proves the wire translation: canonical sortBy/sortDir
        // are sent as order_by/sort, never the raw canonical names.
        $this->assertSame('volume_usd_24h', $result['query']['order_by']);
        $this->assertSame('desc', $result['query']['sort']);
        $this->assertArrayNotHasKey('sort_by', $result['query']);
        $this->assertArrayNotHasKey('sort_dir', $result['query']);

        // The dex_name filter actually narrows the results.
        foreach ($result['results'] as $pool) {
            $this->assertSame('uniswap_v3', $pool['dex_id']);
        }
    }

    public function testAdvancedSearchPoolsPerNetwork(): void
    {
        // Per-network variant hits /frontend/v1/networks/{network}/pools.
        $result = $this->client->pools->advancedSearchPools([
            'network' => 'ethereum',
            'limit' => 3,
            'sortBy' => 'volume_usd_24h',
            'sortDir' => 'desc',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertNotEmpty($result['results']);
        $this->assertSame('ethereum', $result['query']['network']);

        // Every returned pool belongs to the requested chain.
        foreach ($result['results'] as $pool) {
            $this->assertSame('ethereum', $pool['chain']);
        }
    }

    public function testAdvancedSearchPoolsCursorPagination(): void
    {
        // Cursor pagination: feed next_cursor back in and the second page must
        // contain different pools than the first.
        $page1 = $this->client->pools->advancedSearchPools([
            'network' => 'ethereum',
            'limit' => 3,
        ]);

        $this->assertNotEmpty($page1['results']);
        $this->assertNotEmpty($page1['next_cursor']);

        $page2 = $this->client->pools->advancedSearchPools([
            'network' => 'ethereum',
            'limit' => 3,
            'cursor' => $page1['next_cursor'],
        ]);

        $this->assertNotEmpty($page2['results']);

        $page1Ids = array_map(fn($pool) => $pool['id'], $page1['results']);
        $page2Ids = array_map(fn($pool) => $pool['id'], $page2['results']);
        $this->assertEmpty(array_intersect($page1Ids, $page2Ids));
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
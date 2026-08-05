<?php

namespace DexPaprika\Tests;

use DexPaprika\Api\DexesApi;
use DexPaprika\Exception\NotFoundException;
use DexPaprika\Exception\DeprecationException;
use DexPaprika\Exception\DexPaprikaApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class DexesApiTest extends TestCase
{
    private function createMockClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        return new Client(['handler' => $handlerStack]);
    }

    public function testGetNetworkDexes(): void
    {
        $expectedResponse = [
            'dexes' => [
                [
                    'id' => 'uniswap-v2',
                    'name' => 'Uniswap v2',
                    'description' => 'Decentralized exchange protocol',
                    'pool_count' => 1200,
                    'volume_usd_24h' => 1500000000,
                ],
                [
                    'id' => 'sushiswap',
                    'name' => 'Sushiswap',
                    'description' => 'Community-driven exchange',
                    'pool_count' => 800,
                    'volume_usd_24h' => 800000000,
                ],
            ],
            'page_info' => [
                'page' => 0,
                'total_pages' => 1,
                'items_on_page' => 2,
                'total_items' => 2,
            ],
        ];

        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($expectedResponse)),
        ]);

        $api = new DexesApi($mockClient);
        $result = $api->getNetworkDexes('ethereum');

        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetNetworkDexesWithObjectTransformation(): void
    {
        $expectedResponse = [
            'dexes' => [
                [
                    'id' => 'uniswap-v2',
                    'name' => 'Uniswap v2',
                    'description' => 'Decentralized exchange protocol',
                ],
            ],
            'page_info' => [
                'page' => 0,
                'total_pages' => 1,
                'items_on_page' => 1,
                'total_items' => 1,
            ],
        ];

        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($expectedResponse)),
        ]);

        $api = new DexesApi($mockClient, true); // Enable response transformation
        $result = $api->getNetworkDexes('ethereum');

        $this->assertIsObject($result);
        $this->assertIsArray($result->dexes);
        $this->assertEquals('uniswap-v2', $result->dexes[0]->id);
        $this->assertEquals('Uniswap v2', $result->dexes[0]->name);
    }

    public function testListByNetwork(): void
    {
        $expectedResponse = [
            'dexes' => [
                [
                    'id' => 'uniswap-v2',
                    'name' => 'Uniswap v2',
                ],
            ],
            'page_info' => [
                'page' => 0,
                'total_pages' => 1,
                'items_on_page' => 1,
                'total_items' => 1,
            ],
        ];

        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($expectedResponse)),
        ]);

        $api = new DexesApi($mockClient);
        $result = $api->listByNetwork('ethereum');

        $this->assertEquals($expectedResponse, $result);
    }

    /**
     * One row of a real GET /networks/ethereum/pools/search?dex_name=curve
     * response, captured live on 2026-08-05. Field names come off the wire:
     * there is no bare volume_usd, no transactions, and no page_info.
     *
     * @return array<string, mixed>
     */
    private function liveDexPoolRow(string $id = '0x4f493b7de8aac7d55f71853688b1f7c8f0243c85'): array
    {
        return [
            'id' => $id,
            'dex_id' => 'curve',
            'dex_name' => 'Curve',
            'chain' => 'ethereum',
            'volume_usd_24h' => 15883391.558251368,
            'created_at' => '2025-01-25T17:20:47Z',
            'created_at_block_number' => 21702976,
            'transactions_24h' => 289,
            'price_usd' => 0.9995787501356217,
            'price_change_percentage_5m' => null,
            'price_change_percentage_1h' => 0.02422482089565938,
            'price_change_percentage_6h' => 0.009802157529374174,
            'price_change_percentage_24h' => 0.007018797950998323,
            'fee' => null,
            'volume_usd_7d' => 31781851.73428885,
            'volume_usd_30d' => 136889876.39037386,
            'liquidity_usd' => 7407910.088430515,
            'tokens' => [
                ['id' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48', 'chain' => 'ethereum', 'has_image' => true],
                ['id' => '0xdac17f958d2ee523a2206206994597c13d831ec7', 'chain' => 'ethereum', 'has_image' => true],
            ],
        ];
    }

    /**
     * The full cursor-paginated envelope returned by /pools/search.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function liveSearchEnvelope(array $rows, bool $hasNext = true, ?string $nextCursor = 'eyJjaGFpbiI6ImV0aGVyZXVtIn0'): array
    {
        return [
            'results' => $rows,
            'has_next_page' => $hasNext,
            'next_cursor' => $nextCursor,
            'query' => ['network' => 'ethereum', 'limit' => 10, 'dex_name' => 'curve', 'order_by' => 'volume_usd_24h'],
        ];
    }

    public function testGetDexPoolsTargetsPoolsSearchWithDexName(): void
    {
        // DexPaprika removed /networks/{network}/dexes/{dex}/pools (410 Gone).
        // The DEX now travels in the dex_name query parameter instead.
        $expectedResponse = $this->liveSearchEnvelope([$this->liveDexPoolRow()]);

        $mockApi = $this->getMockBuilder(DexesApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['get'])
            ->getMock();

        $mockApi->expects($this->once())
            ->method('get')
            ->with(
                $this->equalTo('/networks/ethereum/pools/search'),
                $this->equalTo([
                    'dex_name' => 'curve',
                    'limit' => 10,
                ])
            )
            ->willReturn($expectedResponse);

        // `page` is still accepted but must not reach the wire.
        $result = $mockApi->getDexPools('ethereum', 'curve', [
            'page' => 0,
            'limit' => 10,
        ]);

        $this->assertEquals($expectedResponse, $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayNotHasKey('pools', $result);
        $this->assertArrayNotHasKey('page_info', $result);
        $this->assertTrue($result['has_next_page']);
        $this->assertSame('curve', $result['results'][0]['dex_id']);
        $this->assertSame(15883391.558251368, $result['results'][0]['volume_usd_24h']);
        $this->assertArrayNotHasKey('volume_usd', $result['results'][0]);
    }

    public function testGetDexPoolsMapsLegacyOrderByAndForwardsCursor(): void
    {
        $mockApi = $this->getMockBuilder(DexesApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['get'])
            ->getMock();

        $mockApi->expects($this->once())
            ->method('get')
            ->with(
                $this->equalTo('/networks/ethereum/pools/search'),
                $this->equalTo([
                    'dex_name' => 'uniswap_v3',
                    'limit' => 5,
                    'order_by' => 'volume_usd_24h',
                    'sort' => 'desc',
                    'cursor' => 'abc123',
                ])
            )
            ->willReturn($this->liveSearchEnvelope([$this->liveDexPoolRow()]));

        $mockApi->getDexPools('ethereum', 'uniswap_v3', [
            'page' => 3,
            'limit' => 5,
            'orderBy' => 'volume_usd',
            'sort' => 'desc',
            'cursor' => 'abc123',
        ]);
    }

    public function testGetDexPoolsAsObject(): void
    {
        $expectedResponse = $this->liveSearchEnvelope([
            $this->liveDexPoolRow(),
            $this->liveDexPoolRow('0xbebc44782c7db0a1a60cb6fe97d0b483032ff1c7'),
        ]);

        $mockApi = $this->getMockBuilder(DexesApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['get'])
            ->getMock();

        $mockApi->expects($this->once())
            ->method('get')
            ->willReturn($expectedResponse);

        $result = $mockApi->getDexPools('ethereum', 'curve', [
            'limit' => 10,
            'asObject' => true,
        ]);

        $this->assertIsObject($result);
        $this->assertIsArray($result->results);
        $this->assertCount(2, $result->results);
        $this->assertSame('curve', $result->results[0]->dex_id);
        $this->assertTrue($result->has_next_page);
        $this->assertFalse(property_exists($result, 'page_info'));
    }

    public function testListDexPools(): void
    {
        $expectedResponse = $this->liveSearchEnvelope([$this->liveDexPoolRow()]);

        // Use partial mock to only mock the getDexPools method
        $mockApi = $this->getMockBuilder(DexesApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['getDexPools'])
            ->getMock();

        // Set up expectations for the mocked getDexPools method
        $mockApi->expects($this->once())
            ->method('getDexPools')
            ->with(
                $this->equalTo('ethereum'),
                $this->equalTo('curve'),
                $this->equalTo(['limit' => 10])
            )
            ->willReturn($expectedResponse);

        // Call listDexPools and verify it correctly uses getDexPools
        $result = $mockApi->listDexPools('ethereum', 'curve', ['limit' => 10]);
        $this->assertEquals($expectedResponse, $result);
    }

    public function testFetchAllDexPools(): void
    {
        // Cursor-paginated pages, shaped like the live search envelope.
        $responses = [
            $this->liveSearchEnvelope(
                [$this->liveDexPoolRow('pool1'), $this->liveDexPoolRow('pool2')],
                true,
                'cursor-page-2'
            ),
            $this->liveSearchEnvelope(
                [$this->liveDexPoolRow('pool3'), $this->liveDexPoolRow('pool4')],
                false,
                null
            ),
        ];

        // Mock the DexesApi but only the getDexPools method
        $mockApi = $this->getMockBuilder(DexesApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['getDexPools'])
            ->getMock();

        $seenCursors = [];

        // Pages are threaded by cursor, never by a page number.
        $mockApi->expects($this->exactly(2))
            ->method('getDexPools')
            ->willReturnCallback(function ($networkId, $dexId, $options) use ($responses, &$seenCursors) {
                $this->assertArrayNotHasKey('page', $options);
                $seenCursors[] = $options['cursor'] ?? null;
                return $responses[count($seenCursors) - 1];
            });

        // Create a collector for results
        $allPools = [];
        $callback = function ($poolsData, $page) use (&$allPools) {
            foreach ($poolsData['results'] ?? [] as $pool) {
                $allPools[] = $pool;
            }
            return true; // Continue pagination
        };

        // Execute fetchAllDexPools
        $totalPages = $mockApi->fetchAllDexPools('ethereum', 'curve', $callback, [
            'limit' => 2
        ]);

        // Verify results
        $this->assertEquals(2, $totalPages, 'Should have fetched 2 pages');
        $this->assertCount(4, $allPools, 'Should have collected 4 pools');
        $this->assertEquals('pool1', $allPools[0]['id']);
        $this->assertEquals('pool4', $allPools[3]['id']);
        // First call has no cursor, second reuses next_cursor from page one.
        $this->assertSame([null, 'cursor-page-2'], $seenCursors);
    }

    public function testFetchAllDexPoolsWithStopCondition(): void
    {
        $responses = [
            $this->liveSearchEnvelope(
                [$this->liveDexPoolRow('pool1'), $this->liveDexPoolRow('pool2')],
                true,
                'cursor-page-2'
            ),
            $this->liveSearchEnvelope(
                [$this->liveDexPoolRow('pool3'), $this->liveDexPoolRow('pool4')],
                true,
                'cursor-page-3'
            ),
            // Page 3 should not be requested because the callback stops first.
            $this->liveSearchEnvelope(
                [$this->liveDexPoolRow('pool5'), $this->liveDexPoolRow('pool6')],
                true,
                'cursor-page-4'
            ),
        ];

        // Mock the DexesApi but only the getDexPools method
        $mockApi = $this->getMockBuilder(DexesApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['getDexPools'])
            ->getMock();

        $calls = 0;

        // Set up expectations for each call to getDexPools
        $mockApi->expects($this->exactly(2)) // Only two calls should happen
            ->method('getDexPools')
            ->willReturnCallback(function ($networkId, $dexId, $options) use ($responses, &$calls) {
                return $responses[$calls++];
            });

        // Create a collector for results
        $allPools = [];
        $callback = function ($poolsData, $page) use (&$allPools) {
            foreach ($poolsData['results'] ?? [] as $pool) {
                $allPools[] = $pool;
            }
            // Stop after page 1 (the second page)
            return $page < 1;
        };

        // Execute fetchAllDexPools
        $totalPages = $mockApi->fetchAllDexPools('ethereum', 'curve', $callback, [
            'limit' => 2
        ]);

        // Verify results
        $this->assertEquals(2, $totalPages, 'Should have fetched 2 pages');
        $this->assertCount(4, $allPools, 'Should have collected 4 pools');
        $this->assertEquals('pool1', $allPools[0]['id']);
        $this->assertEquals('pool4', $allPools[3]['id']);
    }

    public function testRemovedDexPoolsPathSurfacesReplacement(): void
    {
        // The exact body the API returns for the removed path, captured live.
        $mockClient = $this->createMockClient([
            new Response(410, [], json_encode([
                'code' => 410,
                'message' => 'endpoint removed',
                'replacement' => '/networks/:network/pools/search',
            ])),
        ]);

        $api = new DexesApi($mockClient);

        try {
            // A caller still pinned to the old path.
            $reflection = new \ReflectionMethod(DexesApi::class, 'get');
            $reflection->setAccessible(true);
            $reflection->invoke($api, '/networks/ethereum/dexes/uniswap_v3/pools', []);
            $this->fail('Expected a DeprecationException for the removed endpoint');
        } catch (DeprecationException $e) {
            $this->assertSame(410, $e->getCode());
            $this->assertSame('/networks/:network/pools/search', $e->getReplacement());
            $this->assertStringContainsString('Use /networks/:network/pools/search instead.', $e->getMessage());
        }
    }

    public function testGetNetworkDexesThrowsExceptionOnApiError(): void
    {
        $mockClient = $this->createMockClient([
            new Response(500, [], json_encode(['error' => 'Internal server error'])),
        ]);

        $api = new DexesApi($mockClient);

        $this->expectException(DexPaprikaApiException::class);
        $api->getNetworkDexes('ethereum');
    }

    public function testTransformResponse(): void
    {
        $mockClient = $this->createMockClient([]);
        $api = new DexesApi($mockClient);
        
        $rawResponse = [
            'dexes' => [
                [
                    'id' => 'uniswap-v2',
                    'name' => 'Uniswap v2',
                ]
            ]
        ];

        // Use reflection to test protected method
        $reflectionMethod = new \ReflectionMethod(DexesApi::class, 'transformResponse');
        $reflectionMethod->setAccessible(true);
        
        // Test array response (default)
        $arrayResult = $reflectionMethod->invoke($api, $rawResponse, false);
        $this->assertIsArray($arrayResult);
        $this->assertEquals($rawResponse, $arrayResult);
        
        // Test object response
        $objectResult = $reflectionMethod->invoke($api, $rawResponse, true);
        $this->assertIsObject($objectResult);
        $this->assertIsArray($objectResult->dexes);
        $this->assertEquals('uniswap-v2', $objectResult->dexes[0]->id);
    }
}

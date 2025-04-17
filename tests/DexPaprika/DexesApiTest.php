<?php

namespace DexPaprika\Tests;

use DexPaprika\Api\DexesApi;
use DexPaprika\Exception\NotFoundException;
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

    public function testGetDexPools(): void
    {
        // Mock the API response for getDexPools
        $expectedResponse = [
            'pools' => [
                ['id' => 'eth_wbtc', 'name' => 'ETH/WBTC'],
                ['id' => 'eth_usdc', 'name' => 'ETH/USDC']
            ],
            'page_info' => [
                'page' => 0,
                'total_pages' => 5,
                'items_on_page' => 2,
                'total_items' => 10
            ]
        ];

        // Instead of using the actual implementation, we'll mock the get method
        $mockApi = $this->getMockBuilder(DexesApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['get'])
            ->getMock();

        // Set up expectations for the mocked get method - allow any number of calls
        $mockApi->expects($this->any())
            ->method('get')
            ->with(
                $this->equalTo('/networks/ethereum/dexes/uniswap_v3/pools'),
                $this->equalTo([
                    'network' => 'ethereum',
                    'dex' => 'uniswap_v3',
                    'page' => 0,
                    'limit' => 10
                ])
            )
            ->willReturn($expectedResponse);

        // Call the method with test parameters
        $result = $mockApi->getDexPools('ethereum', 'uniswap_v3', [
            'page' => 0,
            'limit' => 10
        ]);

        // Assert the response matches our expectations
        $this->assertEquals($expectedResponse, $result);

        // Test with asObject = true
        $result = $mockApi->getDexPools('ethereum', 'uniswap_v3', [
            'page' => 0,
            'limit' => 10,
            'asObject' => true
        ]);

        // When testing with transformation, we need to make sure the 
        // transformResponse method is correctly working
        $this->assertIsObject($result);
        $this->assertIsArray($result->pools);
        $this->assertEquals(2, count($result->pools));
    }

    public function testListDexPools(): void
    {
        // Mock the API response for listDexPools
        $expectedResponse = [
            'pools' => [
                ['id' => 'eth_wbtc', 'name' => 'ETH/WBTC'],
                ['id' => 'eth_usdc', 'name' => 'ETH/USDC']
            ],
            'page_info' => [
                'page' => 0,
                'total_pages' => 5,
                'items_on_page' => 2,
                'total_items' => 10
            ]
        ];

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
                $this->equalTo('uniswap_v3'),
                $this->equalTo(['limit' => 10])
            )
            ->willReturn($expectedResponse);

        // Call listDexPools and verify it correctly uses getDexPools
        $result = $mockApi->listDexPools('ethereum', 'uniswap_v3', ['limit' => 10]);
        $this->assertEquals($expectedResponse, $result);
    }

    public function testFetchAllDexPools(): void
    {
        // Test responses for multiple pages
        $responses = [
            // Page 0
            [
                'pools' => [
                    ['id' => 'pool1', 'name' => 'Pool 1'],
                    ['id' => 'pool2', 'name' => 'Pool 2']
                ],
                'page_info' => [
                    'page' => 0,
                    'total_pages' => 2,
                    'items_on_page' => 2,
                    'total_items' => 4
                ]
            ],
            // Page 1
            [
                'pools' => [
                    ['id' => 'pool3', 'name' => 'Pool 3'],
                    ['id' => 'pool4', 'name' => 'Pool 4']
                ],
                'page_info' => [
                    'page' => 1,
                    'total_pages' => 2,
                    'items_on_page' => 2,
                    'total_items' => 4
                ]
            ]
        ];

        // Mock the DexesApi but only the getDexPools method
        $mockApi = $this->getMockBuilder(DexesApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['getDexPools'])
            ->getMock();

        // Set up expectations for each call to getDexPools
        $mockApi->expects($this->exactly(2))
            ->method('getDexPools')
            ->willReturnCallback(function($networkId, $dexId, $options) use ($responses) {
                $page = $options['page'] ?? 0;
                return $responses[$page];
            });

        // Create a collector for results
        $allPools = [];
        $callback = function($poolsData, $page) use (&$allPools) {
            if (isset($poolsData['pools'])) {
                foreach ($poolsData['pools'] as $pool) {
                    $allPools[] = $pool;
                }
            }
            return true; // Continue pagination
        };

        // Execute fetchAllDexPools
        $totalPages = $mockApi->fetchAllDexPools('ethereum', 'uniswap_v3', $callback, [
            'limit' => 2
        ]);

        // Verify results
        $this->assertEquals(2, $totalPages, 'Should have fetched 2 pages');
        $this->assertCount(4, $allPools, 'Should have collected 4 pools');
        $this->assertEquals('pool1', $allPools[0]['id']);
        $this->assertEquals('pool4', $allPools[3]['id']);
    }

    public function testFetchAllDexPoolsWithStopCondition(): void
    {
        // Test responses for multiple pages
        $responses = [
            // Page 0
            [
                'pools' => [
                    ['id' => 'pool1', 'name' => 'Pool 1'],
                    ['id' => 'pool2', 'name' => 'Pool 2']
                ],
                'page_info' => [
                    'page' => 0,
                    'total_pages' => 3,
                    'items_on_page' => 2,
                    'total_items' => 6
                ]
            ],
            // Page 1
            [
                'pools' => [
                    ['id' => 'pool3', 'name' => 'Pool 3'],
                    ['id' => 'pool4', 'name' => 'Pool 4']
                ],
                'page_info' => [
                    'page' => 1,
                    'total_pages' => 3,
                    'items_on_page' => 2,
                    'total_items' => 6
                ]
            ],
            // Page 2 (should not be called due to stop condition)
            [
                'pools' => [
                    ['id' => 'pool5', 'name' => 'Pool 5'],
                    ['id' => 'pool6', 'name' => 'Pool 6']
                ],
                'page_info' => [
                    'page' => 2,
                    'total_pages' => 3,
                    'items_on_page' => 2,
                    'total_items' => 6
                ]
            ]
        ];

        // Mock the DexesApi but only the getDexPools method
        $mockApi = $this->getMockBuilder(DexesApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['getDexPools'])
            ->getMock();

        // Set up expectations for each call to getDexPools
        $mockApi->expects($this->exactly(2)) // Only two calls should happen
            ->method('getDexPools')
            ->willReturnCallback(function($networkId, $dexId, $options) use ($responses) {
                $page = $options['page'] ?? 0;
                return $responses[$page];
            });

        // Create a collector for results
        $allPools = [];
        $callback = function($poolsData, $page) use (&$allPools) {
            if (isset($poolsData['pools'])) {
                foreach ($poolsData['pools'] as $pool) {
                    $allPools[] = $pool;
                }
            }
            // Stop after page 1 (the second page)
            return $page < 1;
        };

        // Execute fetchAllDexPools
        $totalPages = $mockApi->fetchAllDexPools('ethereum', 'uniswap_v3', $callback, [
            'limit' => 2
        ]);

        // Verify results
        $this->assertEquals(2, $totalPages, 'Should have fetched 2 pages');
        $this->assertCount(4, $allPools, 'Should have collected 4 pools');
        $this->assertEquals('pool1', $allPools[0]['id']);
        $this->assertEquals('pool4', $allPools[3]['id']);
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

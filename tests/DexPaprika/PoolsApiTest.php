<?php

declare(strict_types=1);

namespace DexPaprika\Tests;

use DexPaprika\Api\PoolsApi;
use DexPaprika\Exception\DexPaprikaApiException;
use DexPaprika\Exception\NotFoundException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class PoolsApiTest extends TestCase
{
    private function createMockClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        return new Client(['handler' => $handlerStack]);
    }

    public function testGetTopPools(): void
    {
        // Mock the API response for getTopPools
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

        // Create a mock for the PoolsApi that only mocks the get method
        $mockApi = $this->getMockBuilder(PoolsApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['get'])
            ->getMock();

        // Set up expectations for the mocked get method
        $mockApi->expects($this->any())
            ->method('get')
            ->with(
                $this->equalTo('/pools'),
                $this->equalTo([
                    'page' => 0,
                    'limit' => 10
                ])
            )
            ->willReturn($expectedResponse);

        // Call the method with test parameters
        $result = $mockApi->getTopPools([
            'page' => 0,
            'limit' => 10
        ]);

        // Assert the response matches our expectations
        $this->assertEquals($expectedResponse, $result);

        // Test with asObject = true
        $result = $mockApi->getTopPools([
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

    public function testListTopPools(): void
    {
        // Mock the API response for listTopPools
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

        // Use partial mock to only mock the getTopPools method
        $mockApi = $this->getMockBuilder(PoolsApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['getTopPools'])
            ->getMock();

        // Set up expectations for the mocked getTopPools method
        $mockApi->expects($this->once())
            ->method('getTopPools')
            ->with($this->equalTo(['limit' => 10]))
            ->willReturn($expectedResponse);

        // Call listTopPools and verify it correctly uses getTopPools
        $result = $mockApi->listTopPools(['limit' => 10]);
        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetNetworkPools(): void
    {
        // Mock the API response for getNetworkPools
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

        // Create a mock for the PoolsApi that only mocks the get method
        $mockApi = $this->getMockBuilder(PoolsApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['get'])
            ->getMock();

        // Set up expectations for the mocked get method
        $mockApi->expects($this->any())
            ->method('get')
            ->with(
                $this->equalTo('/networks/ethereum/pools'),
                $this->equalTo([
                    'network' => 'ethereum',
                    'page' => 0,
                    'limit' => 10
                ])
            )
            ->willReturn($expectedResponse);

        // Call the method with test parameters
        $result = $mockApi->getNetworkPools('ethereum', [
            'page' => 0,
            'limit' => 10
        ]);

        // Assert the response matches our expectations
        $this->assertEquals($expectedResponse, $result);

        // Test with asObject = true
        $result = $mockApi->getNetworkPools('ethereum', [
            'page' => 0,
            'limit' => 10,
            'asObject' => true
        ]);

        // Verify transformation to object
        $this->assertIsObject($result);
        $this->assertIsArray($result->pools);
        $this->assertEquals(2, count($result->pools));
    }

    public function testListNetworkPools(): void
    {
        // Mock the API response for listNetworkPools
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

        // Use partial mock to only mock the getNetworkPools method
        $mockApi = $this->getMockBuilder(PoolsApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['getNetworkPools'])
            ->getMock();

        // Set up expectations for the mocked getNetworkPools method
        $mockApi->expects($this->once())
            ->method('getNetworkPools')
            ->with(
                $this->equalTo('ethereum'),
                $this->equalTo(['limit' => 10])
            )
            ->willReturn($expectedResponse);

        // Call listNetworkPools and verify it correctly uses getNetworkPools
        $result = $mockApi->listNetworkPools('ethereum', ['limit' => 10]);
        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetPoolDetails(): void
    {
        // Mock the API response for getPoolDetails
        $expectedResponse = [
            'pool' => [
                'id' => 'eth_wbtc',
                'name' => 'ETH/WBTC',
                'address' => '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640',
                'network' => 'ethereum',
                'dex' => 'uniswap_v3',
                'volume_usd_24h' => 5000000,
                'liquidity_usd' => 50000000,
                'token1' => [
                    'address' => '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2',
                    'symbol' => 'WETH'
                ],
                'token2' => [
                    'address' => '0x2260fac5e5542a773aa44fbcfedf7c193bc2c599',
                    'symbol' => 'WBTC'
                ]
            ]
        ];

        // Create a mock for the PoolsApi that only mocks the get method
        $mockApi = $this->getMockBuilder(PoolsApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['get'])
            ->getMock();

        // Set up expectations for the mocked get method
        $mockApi->expects($this->any())
            ->method('get')
            ->with(
                $this->equalTo('/networks/ethereum/pools/0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640'),
                $this->equalTo([
                    'network' => 'ethereum',
                    'poolAddress' => '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640',
                    'inversed' => false
                ])
            )
            ->willReturn($expectedResponse);

        // Call the method with test parameters
        $result = $mockApi->getPoolDetails('ethereum', '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640', [
            'inversed' => false
        ]);

        // Assert the response matches our expectations
        $this->assertEquals($expectedResponse, $result);

        // Test with asObject = true
        $result = $mockApi->getPoolDetails('ethereum', '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640', [
            'inversed' => false,
            'asObject' => true
        ]);

        // Verify transformation to object
        $this->assertIsObject($result);
        $this->assertIsObject($result->pool);
        $this->assertEquals('eth_wbtc', $result->pool->id);
    }

    public function testGetPoolOHLCV(): void
    {
        // Mock the API response for getPoolOHLCV
        $expectedResponse = [
            'ohlcv' => [
                [
                    'timestamp' => 1609459200,
                    'open' => 1000.0,
                    'high' => 1050.0,
                    'low' => 950.0,
                    'close' => 1025.0,
                    'volume' => 1000000.0
                ],
                [
                    'timestamp' => 1609545600,
                    'open' => 1025.0,
                    'high' => 1075.0,
                    'low' => 975.0,
                    'close' => 1050.0,
                    'volume' => 1100000.0
                ]
            ]
        ];

        // Create a mock for the PoolsApi that only mocks the get method
        $mockApi = $this->getMockBuilder(PoolsApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['get'])
            ->getMock();

        // Set up expectations for the mocked get method
        $mockApi->expects($this->any())
            ->method('get')
            ->with(
                $this->equalTo('/networks/ethereum/pools/0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640/ohlcv'),
                $this->equalTo([
                    'network' => 'ethereum',
                    'poolAddress' => '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640',
                    'start' => '2021-01-01',
                    'end' => '2021-01-02',
                    'interval' => '1d'
                ])
            )
            ->willReturn($expectedResponse);

        // Call the method with test parameters - match the signature: getPoolOHLCV(string $networkId, string $poolAddress, string $start, array $options = [])
        $result = $mockApi->getPoolOHLCV(
            'ethereum', 
            '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640', 
            '2021-01-01', 
            [
                'end' => '2021-01-02',
                'interval' => '1d'
            ]
        );

        // Assert the response matches our expectations
        $this->assertEquals($expectedResponse, $result);

        // Test with asObject = true
        $result = $mockApi->getPoolOHLCV(
            'ethereum', 
            '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640', 
            '2021-01-01', 
            [
                'end' => '2021-01-02',
                'interval' => '1d',
                'asObject' => true
            ]
        );

        // Verify transformation to object
        $this->assertIsObject($result);
        $this->assertIsArray($result->ohlcv);
        $this->assertEquals(2, count($result->ohlcv));
    }

    public function testGetPoolTransactions(): void
    {
        // Mock the API response for getPoolTransactions
        $expectedResponse = [
            'transactions' => [
                [
                    'hash' => '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
                    'timestamp' => 1609459200,
                    'block_number' => 1000000,
                    'from' => '0xabcdef1234567890abcdef1234567890abcdef12',
                    'to' => '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640',
                    'value' => 1.5,
                    'type' => 'swap'
                ],
                [
                    'hash' => '0xabcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
                    'timestamp' => 1609459300,
                    'block_number' => 1000010,
                    'from' => '0x1234567890abcdef1234567890abcdef12345678',
                    'to' => '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640',
                    'value' => 2.0,
                    'type' => 'swap'
                ]
            ],
            'page_info' => [
                'page' => 0,
                'total_pages' => 5,
                'items_on_page' => 2,
                'total_items' => 10
            ]
        ];

        // Create a mock for the PoolsApi that only mocks the get method
        $mockApi = $this->getMockBuilder(PoolsApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['get'])
            ->getMock();

        // Set up expectations for the mocked get method
        $mockApi->expects($this->any())
            ->method('get')
            ->with(
                $this->equalTo('/networks/ethereum/pools/0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640/transactions'),
                $this->equalTo([
                    'network' => 'ethereum',
                    'poolAddress' => '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640',
                    'page' => 0,
                    'limit' => 10
                ])
            )
            ->willReturn($expectedResponse);

        // Call the method with test parameters
        $result = $mockApi->getPoolTransactions('ethereum', '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640', [
            'page' => 0,
            'limit' => 10
        ]);

        // Assert the response matches our expectations
        $this->assertEquals($expectedResponse, $result);

        // Test with asObject = true
        $result = $mockApi->getPoolTransactions('ethereum', '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640', [
            'page' => 0,
            'limit' => 10,
            'asObject' => true
        ]);

        // Verify transformation to object
        $this->assertIsObject($result);
        $this->assertIsArray($result->transactions);
        $this->assertEquals(2, count($result->transactions));
    }

    public function testFindPool(): void
    {
        // Mock the API response for findPool
        $expectedResponse = [
            'pool' => [
                'id' => 'eth_wbtc',
                'name' => 'ETH/WBTC',
                'address' => '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640',
                'network' => 'ethereum',
                'dex' => 'uniswap_v3'
            ]
        ];

        // Use partial mock to only mock the getPoolDetails method since findPool uses it
        $mockApi = $this->getMockBuilder(PoolsApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['getPoolDetails'])
            ->getMock();

        // Set up expectations for the mocked getPoolDetails method
        $mockApi->expects($this->once())
            ->method('getPoolDetails')
            ->with(
                $this->equalTo('ethereum'),
                $this->equalTo('ETH/WBTC'),
                $this->equalTo(['asObject' => false])
            )
            ->willReturn($expectedResponse);

        // Call the method with test parameters
        $result = $mockApi->findPool('ethereum', 'ETH/WBTC');

        // Assert the response matches our expectations
        $this->assertEquals($expectedResponse, $result);

        // Mock getPoolDetails again for asObject = true
        $mockApi = $this->getMockBuilder(PoolsApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['getPoolDetails'])
            ->getMock();
            
        // For the asObject test, convert to a proper nested object structure
        $objectResponse = (object) [
            'pool' => (object) [
                'id' => 'eth_wbtc',
                'name' => 'ETH/WBTC',
                'address' => '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640',
                'network' => 'ethereum',
                'dex' => 'uniswap_v3'
            ]
        ];
            
        $mockApi->expects($this->once())
            ->method('getPoolDetails')
            ->with(
                $this->equalTo('ethereum'),
                $this->equalTo('ETH/WBTC'),
                $this->equalTo(['asObject' => true])
            )
            ->willReturn($objectResponse);
            
        // Test with asObject = true
        $result = $mockApi->findPool('ethereum', 'ETH/WBTC', true);

        // Verify transformation to object
        $this->assertIsObject($result);
        $this->assertIsObject($result->pool);
        $this->assertEquals('eth_wbtc', $result->pool->id);
    }

    public function testFindPoolThrowsExceptionWhenNotFound(): void
    {
        // Now that we've implemented NotFoundException, we can test this scenario
        
        // Create a mock for the PoolsApi that only mocks the getPoolDetails method
        $mockApi = $this->getMockBuilder(PoolsApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['getPoolDetails'])
            ->getMock();

        // Set up mock to return an empty response (no pool found)
        $mockApi->expects($this->once())
            ->method('getPoolDetails')
            ->with(
                $this->equalTo('ethereum'),
                $this->equalTo('NON_EXISTENT_POOL'),
                $this->equalTo(['asObject' => false])
            )
            ->willReturn(['page_info' => []]);

        // Expect a NotFoundException to be thrown
        $this->expectException(NotFoundException::class);

        // Call the method with a query that doesn't match any pool
        $mockApi->findPool('ethereum', 'NON_EXISTENT_POOL');
    }

    public function testFetchAllNetworkPools(): void
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

        // Mock the PoolsApi but only the getNetworkPools method
        $mockApi = $this->getMockBuilder(PoolsApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['getNetworkPools'])
            ->getMock();

        // Set up expectations for each call to getNetworkPools
        $mockApi->expects($this->exactly(2))
            ->method('getNetworkPools')
            ->willReturnCallback(function($networkId, $options) use ($responses) {
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

        // Execute fetchAllNetworkPools
        $totalPages = $mockApi->fetchAllNetworkPools('ethereum', $callback, [
            'limit' => 2
        ]);

        // Verify results
        $this->assertEquals(2, $totalPages, 'Should have fetched 2 pages');
        $this->assertCount(4, $allPools, 'Should have collected 4 pools');
        $this->assertEquals('pool1', $allPools[0]['id']);
        $this->assertEquals('pool4', $allPools[3]['id']);
    }

    public function testFetchAllPoolTransactions(): void
    {
        // Test responses for multiple pages
        $responses = [
            // Page 0
            [
                'transactions' => [
                    ['id' => 'tx1', 'hash' => '0x1234...'],
                    ['id' => 'tx2', 'hash' => '0x5678...']
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
                'transactions' => [
                    ['id' => 'tx3', 'hash' => '0x9abc...'],
                    ['id' => 'tx4', 'hash' => '0xdef0...']
                ],
                'page_info' => [
                    'page' => 1,
                    'total_pages' => 2,
                    'items_on_page' => 2,
                    'total_items' => 4
                ]
            ]
        ];

        // Mock the PoolsApi but only the getPoolTransactions method
        $mockApi = $this->getMockBuilder(PoolsApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['getPoolTransactions'])
            ->getMock();

        // Set up expectations for each call to getPoolTransactions
        $mockApi->expects($this->exactly(2))
            ->method('getPoolTransactions')
            ->willReturnCallback(function($networkId, $poolAddress, $options) use ($responses) {
                $page = $options['page'] ?? 0;
                return $responses[$page];
            });

        // Create a collector for results
        $allTransactions = [];
        $callback = function($transactionsData, $page) use (&$allTransactions) {
            if (isset($transactionsData['transactions'])) {
                foreach ($transactionsData['transactions'] as $tx) {
                    $allTransactions[] = $tx;
                }
            }
            return true; // Continue pagination
        };

        // Execute fetchAllPoolTransactions
        $totalPages = $mockApi->fetchAllPoolTransactions('ethereum', '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640', $callback, [
            'limit' => 2
        ]);

        // Verify results
        $this->assertEquals(2, $totalPages, 'Should have fetched 2 pages');
        $this->assertCount(4, $allTransactions, 'Should have collected 4 transactions');
        $this->assertEquals('tx1', $allTransactions[0]['id']);
        $this->assertEquals('tx4', $allTransactions[3]['id']);
    }

    public function testTransformResponse(): void
    {
        // Now that we've added the transformPools method to ResponseTransformer,
        // we can properly test the transformation functionality
        $api = new PoolsApi($this->createMockClient([]));
        
        // Use reflection to access the protected transformResponse method
        $reflectionMethod = new \ReflectionMethod(PoolsApi::class, 'transformResponse');
        $reflectionMethod->setAccessible(true);
        
        // Test with pools data
        $poolsData = [
            'pools' => [
                ['id' => 'pool1', 'name' => 'Pool 1'],
                ['id' => 'pool2', 'name' => 'Pool 2']
            ]
        ];
        
        // Transform to array (default)
        $result = $reflectionMethod->invoke($api, $poolsData, false);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('pools', $result);
        
        // Transform to object
        $result = $reflectionMethod->invoke($api, $poolsData, true);
        $this->assertIsObject($result);
        $this->assertIsArray($result->pools);
        $this->assertEquals(2, count($result->pools));
        $this->assertEquals('pool1', $result->pools[0]->id);
    }
}

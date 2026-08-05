<?php

namespace DexPaprika\Tests;

use DexPaprika\Api\TokensApi;
use DexPaprika\Exception\NotFoundException;
use DexPaprika\Exception\DexPaprikaApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class TokensApiTest extends TestCase
{
    private function createMockClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        return new Client(['handler' => $handlerStack]);
    }

    public function testGetTokenDetails(): void
    {
        // Shape copied from a live GET /networks/ethereum/tokens/0xc02aaa...,
        // which returns the token at the top level with no wrapper key.
        $expectedResponse = [
            'id' => '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2',
            'name' => 'Wrapped Ether',
            'symbol' => 'WETH',
            'chain' => 'ethereum',
            'decimals' => 18,
            'price_stats' => [
                'high_24h' => 1885.8392874243973,
                'low_24h' => 1843.5637556909835,
            ],
        ];

        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($expectedResponse)),
        ]);

        $api = new TokensApi($mockClient);
        $result = $api->getTokenDetails('ethereum', '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2');

        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetTokenDetailsWithObjectTransformation(): void
    {
        $expectedResponse = [
            'id' => '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2',
            'name' => 'Wrapped Ether',
            'symbol' => 'WETH',
            'chain' => 'ethereum',
        ];

        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($expectedResponse)),
        ]);

        $api = new TokensApi($mockClient);
        $result = $api->getTokenDetails('ethereum', '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2', ['asObject' => true]);

        $this->assertIsObject($result);
        $this->assertEquals('0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2', $result->id);
        $this->assertEquals('Wrapped Ether', $result->name);
    }

    public function testFindToken(): void
    {
        $expectedResponse = [
            'id' => '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2',
            'name' => 'Wrapped Ether',
            'symbol' => 'WETH',
            'chain' => 'ethereum',
        ];

        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($expectedResponse)),
        ]);

        $api = new TokensApi($mockClient);
        $result = $api->findToken('ethereum', '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2');

        $this->assertEquals($expectedResponse, $result);
    }

    public function testFindTokenThrowsExceptionWhenNotFound(): void
    {
        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode(['message' => 'Not Found'])),
        ]);

        $api = new TokensApi($mockClient);

        $this->expectException(NotFoundException::class);
        $api->findToken('ethereum', '0x1234567890123456789012345678901234567890');
    }

    public function testGetTokenPools(): void
    {
        $expectedResponse = [
            'results' => [
                [
                    'id' => '0x0d4a11d5eeaac28ec3f61d100daf4d40471f1852',
                    'chain' => 'ethereum',
                    'volume_usd_24h' => 150000000,
                ],
                [
                    'id' => '0xa478c2975ab1ea89e8196811f51a7b7ade33eb11',
                    'chain' => 'ethereum',
                    'volume_usd_24h' => 75000000,
                ],
            ],
            'has_next_page' => false,
            'next_cursor' => null,
        ];

        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($expectedResponse)),
        ]);

        $api = new TokensApi($mockClient);
        $result = $api->getTokenPools('ethereum', '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2', [
            'limit' => 10,
            'page' => 0,
        ]);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetTokenPoolsUsesPoolSearchEndpoint(): void
    {
        // getTokenPools must hit /pools/search with token_address, drop page and
        // the removed address/reorder params, and map legacy sort fields.
        $mockApi = $this->getMockBuilder(TokensApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['get'])
            ->getMock();

        $mockApi->expects($this->once())
            ->method('get')
            ->with(
                $this->equalTo('/networks/ethereum/pools/search'),
                $this->equalTo([
                    'token_address' => '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2',
                    'limit' => 5,
                    'order_by' => 'volume_usd_24h',
                    'sort' => 'desc',
                ])
            )
            ->willReturn(['results' => [], 'has_next_page' => false, 'next_cursor' => null]);

        $mockApi->getTokenPools('ethereum', '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2', [
            'page' => 2, // ignored: cursor-paginated
            'limit' => 5,
            'orderBy' => 'volume_usd', // legacy -> volume_usd_24h
            'sort' => 'desc',
            'address' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48', // deprecated, not sent
            'reorder' => true, // deprecated, not sent
        ]);
    }

    public function testListTokenPools(): void
    {
        $expectedResponse = [
            'results' => [
                [
                    'id' => '0x0d4a11d5eeaac28ec3f61d100daf4d40471f1852',
                    'chain' => 'ethereum',
                ],
            ],
            'has_next_page' => false,
            'next_cursor' => null,
        ];

        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($expectedResponse)),
        ]);

        $api = new TokensApi($mockClient);
        $result = $api->listTokenPools('ethereum', '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2');

        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetTokenPairs(): void
    {
        $expectedResponse = [
            'results' => [
                [
                    'id' => '0x0d4a11d5eeaac28ec3f61d100daf4d40471f1852',
                    'chain' => 'ethereum',
                ],
            ],
            'has_next_page' => false,
            'next_cursor' => null,
        ];

        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($expectedResponse)),
        ]);

        $api = new TokensApi($mockClient);
        $result = $api->getTokenPairs('ethereum', '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2');

        $this->assertEquals($expectedResponse, $result);
    }

    public function testListTokenPairs(): void
    {
        $expectedResponse = [
            'results' => [
                [
                    'id' => '0x0d4a11d5eeaac28ec3f61d100daf4d40471f1852',
                    'chain' => 'ethereum',
                ],
            ],
            'has_next_page' => false,
            'next_cursor' => null,
        ];

        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($expectedResponse)),
        ]);

        $api = new TokensApi($mockClient);
        $result = $api->listTokenPairs('ethereum', '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2');

        $this->assertEquals($expectedResponse, $result);
    }

    public function testFetchAllTokenPools(): void
    {
        $response1 = [
            'results' => [
                [
                    'id' => '0x0d4a11d5eeaac28ec3f61d100daf4d40471f1852',
                    'chain' => 'ethereum',
                ],
            ],
            'has_next_page' => true,
            'next_cursor' => 'cursor-page-2',
        ];

        $response2 = [
            'results' => [
                [
                    'id' => '0xa478c2975ab1ea89e8196811f51a7b7ade33eb11',
                    'chain' => 'ethereum',
                ],
            ],
            'has_next_page' => false,
            'next_cursor' => null,
        ];

        // Create a partial mock of TokensApi that will only mock the getTokenPools method
        $api = $this->getMockBuilder(TokensApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['getTokenPools'])
            ->getMock();

        // Setup the mock to return our predefined responses and verify the
        // second call carries the cursor from the first response
        $api->expects($this->exactly(2))
            ->method('getTokenPools')
            ->willReturnCallback(function($networkId, $tokenAddress, $options) use ($response1, $response2) {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    $this->assertArrayNotHasKey('cursor', $options);
                    return $response1;
                }
                $this->assertSame('cursor-page-2', $options['cursor'] ?? null);
                return $response2;
            });

        $poolsCollected = [];
        $totalPages = $api->fetchAllTokenPools(
            'ethereum',
            '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2',
            function ($poolsPage, $page) use (&$poolsCollected) {
                $poolsCollected = array_merge($poolsCollected, $poolsPage['results']);
                // Return false after the first page to stop pagination
                return $page < 1; // Only continue for the first page (index 0)
            },
            ['limit' => 1, 'maxPages' => 2] // Also limit max pages to 2
        );

        $this->assertEquals(2, $totalPages);
        $this->assertCount(2, $poolsCollected);
        $this->assertEquals('0x0d4a11d5eeaac28ec3f61d100daf4d40471f1852', $poolsCollected[0]['id']);
        $this->assertEquals('0xa478c2975ab1ea89e8196811f51a7b7ade33eb11', $poolsCollected[1]['id']);
    }

    public function testFetchAllTokenPoolsWithStopCondition(): void
    {
        $response1 = [
            'results' => [
                [
                    'id' => '0x0d4a11d5eeaac28ec3f61d100daf4d40471f1852',
                    'chain' => 'ethereum',
                ],
            ],
            'has_next_page' => true,
            'next_cursor' => 'cursor-page-2',
        ];

        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($response1)),
        ]);

        $api = new TokensApi($mockClient);

        $poolsCollected = [];
        $totalPages = $api->fetchAllTokenPools(
            'ethereum',
            '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2',
            function ($poolsPage, $page) use (&$poolsCollected) {
                $poolsCollected = array_merge($poolsCollected, $poolsPage['results']);
                return false; // Stop after first page
            },
            ['limit' => 1]
        );

        $this->assertEquals(1, $totalPages);
        $this->assertCount(1, $poolsCollected);
    }

    public function testTokenDetailsThrowsExceptionOnApiError(): void
    {
        $mockClient = $this->createMockClient([
            new Response(500, [], json_encode(['error' => 'Internal server error'])),
        ]);

        $api = new TokensApi($mockClient);

        $this->expectException(DexPaprikaApiException::class);
        $api->getTokenDetails('ethereum', '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2');
    }

    public function testTransformResponse(): void
    {
        $mockClient = $this->createMockClient([]);
        $api = new TokensApi($mockClient);
        
        $rawResponse = [
            'id' => 'eth',
            'name' => 'Ethereum',
            'symbol' => 'ETH'
        ];

        // Use reflection to test protected method
        $reflectionMethod = new \ReflectionMethod(TokensApi::class, 'transformResponse');
        $reflectionMethod->setAccessible(true);
        
        // Test array response (default)
        $arrayResult = $reflectionMethod->invoke($api, $rawResponse, false);
        $this->assertIsArray($arrayResult);
        $this->assertEquals($rawResponse, $arrayResult);
        
        // Test object response
        $objectResult = $reflectionMethod->invoke($api, $rawResponse, true);
        $this->assertIsObject($objectResult);
        $this->assertEquals('eth', $objectResult->id);
        $this->assertEquals('Ethereum', $objectResult->name);
    }

    public function testGetTopTokensUsesSearchEndpoint(): void
    {
        // getTopTokens must hit /tokens/search, drop page, and map legacy sort fields.
        $mockApi = $this->getMockBuilder(TokensApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['get'])
            ->getMock();

        $mockApi->expects($this->once())
            ->method('get')
            ->with(
                $this->equalTo('/networks/ethereum/tokens/search'),
                $this->equalTo([
                    'limit' => 5,
                    'order_by' => 'volume_usd_24h',
                    'sort' => 'asc',
                ])
            )
            ->willReturn(['results' => [], 'has_next_page' => false, 'next_cursor' => null]);

        $mockApi->getTopTokens('ethereum', [
            'page' => 1, // ignored
            'limit' => 5,
            'orderBy' => 'volume_24h', // legacy -> volume_usd_24h
            'sort' => 'asc',
        ]);
    }

    public function testFilterTokensUsesSearchEndpointAndMapsParams(): void
    {
        // filterTokens must hit /tokens/search, send order_by + sort (not sort_by/sort_dir),
        // drop page, and rename legacy filter params to canonical names.
        $mockApi = $this->getMockBuilder(TokensApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['get'])
            ->getMock();

        $mockApi->expects($this->once())
            ->method('get')
            ->with(
                $this->equalTo('/networks/ethereum/tokens/search'),
                $this->equalTo([
                    'limit' => 3,
                    'order_by' => 'fdv_usd',
                    'sort' => 'desc',
                    'volume_usd_24h_min' => 100000,
                    'fdv_min' => 1000000,
                ])
            )
            ->willReturn(['results' => [], 'has_next_page' => false, 'next_cursor' => null]);

        $mockApi->filterTokens('ethereum', [
            'page' => 1, // ignored
            'limit' => 3,
            'sortBy' => 'fdv', // legacy -> fdv_usd
            'sortDir' => 'desc',
            'volume24hMin' => 100000, // legacy -> volume_usd_24h_min
            'fdvMin' => 1000000,
        ]);
    }
}
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
        $expectedResponse = [
            'token' => [
                'id' => 'eth-ethereum',
                'name' => 'Ethereum',
                'symbol' => 'ETH',
                'address' => '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2',
                'market_data' => [
                    'price_usd' => 3500.45,
                    'volume_24h_usd' => 1200000000,
                ],
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
            'token' => [
                'id' => 'eth-ethereum',
                'name' => 'Ethereum',
                'symbol' => 'ETH',
                'address' => '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2',
            ],
        ];

        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($expectedResponse)),
        ]);

        $api = new TokensApi($mockClient);
        $result = $api->getTokenDetails('ethereum', '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2', ['asObject' => true]);

        $this->assertIsObject($result);
        $this->assertIsObject($result->token);
        $this->assertEquals('eth-ethereum', $result->token->id);
        $this->assertEquals('Ethereum', $result->token->name);
    }

    public function testFindToken(): void
    {
        $expectedResponse = [
            'token' => [
                'id' => 'eth-ethereum',
                'name' => 'Ethereum',
                'symbol' => 'ETH',
                'address' => '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2',
            ],
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
            new Response(200, [], json_encode(['not_token' => []])),
        ]);

        $api = new TokensApi($mockClient);

        $this->expectException(NotFoundException::class);
        $api->findToken('ethereum', '0x1234567890123456789012345678901234567890');
    }

    public function testGetTokenPools(): void
    {
        $expectedResponse = [
            'pools' => [
                [
                    'id' => 'uniswap-v2-eth-usdt',
                    'address' => '0x0d4a11d5eeaac28ec3f61d100daf4d40471f1852',
                    'name' => 'ETH/USDT',
                    'volume_usd_24h' => 150000000,
                ],
                [
                    'id' => 'uniswap-v2-eth-dai',
                    'address' => '0xa478c2975ab1ea89e8196811f51a7b7ade33eb11',
                    'name' => 'ETH/DAI',
                    'volume_usd_24h' => 75000000,
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

        $api = new TokensApi($mockClient);
        $result = $api->getTokenPools('ethereum', '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2', [
            'limit' => 10,
            'page' => 0,
        ]);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testListTokenPools(): void
    {
        $expectedResponse = [
            'pools' => [
                [
                    'id' => 'uniswap-v2-eth-usdt',
                    'address' => '0x0d4a11d5eeaac28ec3f61d100daf4d40471f1852',
                    'name' => 'ETH/USDT',
                ],
            ],
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
            'pools' => [
                [
                    'id' => 'uniswap-v2-eth-usdt',
                    'address' => '0x0d4a11d5eeaac28ec3f61d100daf4d40471f1852',
                    'name' => 'ETH/USDT',
                ],
            ],
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
            'pools' => [
                [
                    'id' => 'uniswap-v2-eth-usdt',
                    'address' => '0x0d4a11d5eeaac28ec3f61d100daf4d40471f1852',
                    'name' => 'ETH/USDT',
                ],
            ],
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
            'pools' => [
                [
                    'id' => 'uniswap-v2-eth-usdt',
                    'address' => '0x0d4a11d5eeaac28ec3f61d100daf4d40471f1852',
                    'name' => 'ETH/USDT',
                ],
            ],
            'page_info' => [
                'page' => 0,
                'total_pages' => 2,
                'items_on_page' => 1,
                'total_items' => 2,
            ],
        ];

        $response2 = [
            'pools' => [
                [
                    'id' => 'uniswap-v2-eth-dai',
                    'address' => '0xa478c2975ab1ea89e8196811f51a7b7ade33eb11',
                    'name' => 'ETH/DAI',
                ],
            ],
            'page_info' => [
                'page' => 1,
                'total_pages' => 2,
                'items_on_page' => 1,
                'total_items' => 2,
            ],
        ];

        // Create a partial mock of TokensApi that will only mock the getTokenPools method
        $api = $this->getMockBuilder(TokensApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['getTokenPools'])
            ->getMock();
            
        // Setup the mock to return our predefined responses
        $api->expects($this->exactly(2))
            ->method('getTokenPools')
            ->willReturnCallback(function($networkId, $tokenAddress, $options) use ($response1, $response2) {
                static $callCount = 0;
                $callCount++;
                return ($callCount === 1) ? $response1 : $response2;
            });
        
        $poolsCollected = [];
        $totalPages = $api->fetchAllTokenPools(
            'ethereum', 
            '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2', 
            function ($poolsPage, $page) use (&$poolsCollected) {
                $poolsCollected = array_merge($poolsCollected, $poolsPage['pools']);
                // Return false after the first page to stop pagination
                return $page < 1; // Only continue for the first page (index 0)
            },
            ['limit' => 1, 'maxPages' => 2] // Also limit max pages to 2
        );

        $this->assertEquals(2, $totalPages);
        $this->assertCount(2, $poolsCollected);
        $this->assertEquals('uniswap-v2-eth-usdt', $poolsCollected[0]['id']);
        $this->assertEquals('uniswap-v2-eth-dai', $poolsCollected[1]['id']);
    }

    public function testFetchAllTokenPoolsWithStopCondition(): void
    {
        $response1 = [
            'pools' => [
                [
                    'id' => 'uniswap-v2-eth-usdt',
                    'address' => '0x0d4a11d5eeaac28ec3f61d100daf4d40471f1852',
                    'name' => 'ETH/USDT',
                ],
            ],
            'page_info' => [
                'page' => 0,
                'total_pages' => 2,
                'items_on_page' => 1,
                'total_items' => 2,
            ],
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
                $poolsCollected = array_merge($poolsCollected, $poolsPage['pools']);
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
}
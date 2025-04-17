<?php

namespace DexPaprika\Tests;

use DexPaprika\Api\SearchApi;
use DexPaprika\Exception\DexPaprikaApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class SearchApiTest extends TestCase
{
    private function createMockClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        return new Client(['handler' => $handlerStack]);
    }

    public function testSearch(): void
    {
        $expectedResponse = [
            'tokens' => [
                [
                    'id' => 'eth-ethereum',
                    'name' => 'Ethereum',
                    'symbol' => 'ETH',
                    'network' => 'ethereum',
                    'address' => '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2',
                ],
            ],
            'pools' => [
                [
                    'id' => 'uniswap-v2-eth-usdt',
                    'name' => 'ETH/USDT',
                    'network' => 'ethereum',
                    'address' => '0x0d4a11d5eeaac28ec3f61d100daf4d40471f1852',
                ],
            ],
            'dexes' => [
                [
                    'id' => 'uniswap-v2',
                    'name' => 'Uniswap V2',
                    'network' => 'ethereum',
                ],
            ],
        ];

        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($expectedResponse)),
        ]);

        $api = new SearchApi($mockClient);
        $result = $api->search('ethereum');

        $this->assertEquals($expectedResponse, $result);
    }

    public function testSearchWithObjectTransformation(): void
    {
        $expectedResponse = [
            'tokens' => [
                [
                    'id' => 'eth-ethereum',
                    'name' => 'Ethereum',
                    'symbol' => 'ETH',
                ],
            ],
            'pools' => [
                [
                    'id' => 'uniswap-v2-eth-usdt',
                    'name' => 'ETH/USDT',
                ],
            ],
        ];

        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($expectedResponse)),
        ]);

        $api = new SearchApi($mockClient, true); // Enable transformation
        $result = $api->search('ethereum', ['asObject' => true]);

        $this->assertIsObject($result);
        $this->assertIsArray($result->tokens);
        $this->assertIsObject($result->tokens[0]);
        $this->assertEquals('eth-ethereum', $result->tokens[0]->id);
        $this->assertEquals('Ethereum', $result->tokens[0]->name);
    }

    public function testSearchThrowsExceptionOnApiError(): void
    {
        $mockClient = $this->createMockClient([
            new Response(500, [], json_encode(['error' => 'Internal server error'])),
        ]);

        $api = new SearchApi($mockClient);

        $this->expectException(DexPaprikaApiException::class);
        $api->search('ethereum');
    }

    public function testEmptySearchResultHandling(): void
    {
        $expectedResponse = [
            'tokens' => [],
            'pools' => [],
            'dexes' => [],
        ];

        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($expectedResponse)),
        ]);

        $api = new SearchApi($mockClient);
        $result = $api->search('nonexistenttoken123');

        $this->assertEquals($expectedResponse, $result);
        $this->assertEmpty($result['tokens']);
        $this->assertEmpty($result['pools']);
        $this->assertEmpty($result['dexes']);
    }
} 
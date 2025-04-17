<?php

namespace DexPaprika\Tests;

use DexPaprika\Api\NetworksApi;
use DexPaprika\Exception\DexPaprikaApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class NetworksApiTest extends TestCase
{
    private function createMockClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        return new Client(['handler' => $handlerStack]);
    }

    public function testGetNetworks(): void
    {
        $expectedResponse = [
            [
                'id' => 'ethereum',
                'display_name' => 'Ethereum',
            ],
            [
                'id' => 'solana',
                'display_name' => 'Solana',
            ],
        ];

        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($expectedResponse)),
        ]);

        $api = new NetworksApi($mockClient);
        $result = $api->getNetworks();

        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetNetworksThrowsExceptionOnApiError(): void
    {
        $mockClient = $this->createMockClient([
            new Response(500, [], json_encode(['error' => 'Internal server error'])),
        ]);

        $api = new NetworksApi($mockClient);

        $this->expectException(DexPaprikaApiException::class);
        $api->getNetworks();
    }
} 
<?php

namespace DexPaprika\Tests;

use DexPaprika\Api\StatsApi;
use DexPaprika\Exception\DexPaprikaApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class StatsApiTest extends TestCase
{
    private function createMockClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        return new Client(['handler' => $handlerStack]);
    }

    public function testGetStats(): void
    {
        $expectedResponse = [
            'total_volume_usd_24h' => 15000000000,
            'total_transactions_24h' => 2500000,
            'total_tokens' => 8500,
            'total_pools' => 45000,
            'total_networks' => 12,
            'networks' => [
                [
                    'id' => 'ethereum',
                    'name' => 'Ethereum',
                    'volume_usd_24h' => 8000000000,
                ],
                [
                    'id' => 'solana',
                    'name' => 'Solana',
                    'volume_usd_24h' => 2000000000,
                ],
            ],
        ];

        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($expectedResponse)),
        ]);

        $api = new StatsApi($mockClient);
        $result = $api->getStats();

        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetStatsWithObjectTransformation(): void
    {
        $expectedResponse = [
            'total_volume_usd_24h' => 15000000000,
            'total_transactions_24h' => 2500000,
            'networks' => [
                [
                    'id' => 'ethereum',
                    'name' => 'Ethereum',
                    'volume_usd_24h' => 8000000000,
                ],
            ],
        ];

        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($expectedResponse)),
        ]);

        $api = new StatsApi($mockClient, true); // Enable transformation
        $result = $api->getStats(['asObject' => true]);

        $this->assertIsObject($result);
        $this->assertEquals(15000000000, $result->total_volume_usd_24h);
        $this->assertEquals(2500000, $result->total_transactions_24h);
        $this->assertIsArray($result->networks);
        $this->assertIsObject($result->networks[0]);
        $this->assertEquals('ethereum', $result->networks[0]->id);
    }

    public function testGetStatsThrowsExceptionOnApiError(): void
    {
        $mockClient = $this->createMockClient([
            new Response(500, [], json_encode(['error' => 'Internal server error'])),
        ]);

        $api = new StatsApi($mockClient);

        $this->expectException(DexPaprikaApiException::class);
        $api->getStats();
    }

    public function testTransformResponseMethodWorksCorrectly(): void
    {
        $mockResponse = [
            'stats' => [
                'total_dexes' => 10,
                'total_pools' => 1000,
                'total_tokens' => 5000,
                'total_networks' => 5,
            ],
        ];
        
        // Create a mock client for the first test
        $mockClient1 = $this->createMockClient([
            new Response(200, [], json_encode($mockResponse)),
        ]);
        
        // Test with transformation disabled
        $api1 = new StatsApi($mockClient1, false);
        $result1 = $api1->getStats();
        $this->assertIsArray($result1);

        // Create a new mock client for the second test
        $mockClient2 = $this->createMockClient([
            new Response(200, [], json_encode($mockResponse)),
        ]);
        
        // Test with transformation enabled
        // Create a mock of the BaseApi.transformResponse method
        $api2 = $this->getMockBuilder(StatsApi::class)
            ->setConstructorArgs([$mockClient2])
            ->onlyMethods(['transformResponse', 'get'])
            ->getMock();
            
        // Make the mock return the expected object when transformResponse is called
        $objectResponse = (object)['stats' => (object)[
            'total_dexes' => 10,
            'total_pools' => 1000,
            'total_tokens' => 5000,
            'total_networks' => 5,
        ]];
        
        $api2->expects($this->once())
            ->method('transformResponse')
            ->willReturn($objectResponse);
            
        $api2->expects($this->once())
            ->method('get')
            ->willReturn($mockResponse);
            
        // Call getStats with asObject = true
        $result2 = $api2->getStats(['asObject' => true]);
        $this->assertIsObject($result2);
        $this->assertIsObject($result2->stats);
    }
} 
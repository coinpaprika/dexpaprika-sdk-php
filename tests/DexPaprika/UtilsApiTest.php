<?php

namespace DexPaprika\Tests;

use DexPaprika\Api\UtilsApi;
use DexPaprika\Exception\DexPaprikaApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class UtilsApiTest extends TestCase
{
    private function createMockClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        return new Client(['handler' => $handlerStack]);
    }

    public function testGetStats(): void
    {
        // Simple test for get() method success scenario
        // We mock the get method using getMockBuilder to avoid implementation issues
        $mockApi = $this->getMockBuilder(UtilsApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['get'])
            ->getMock();
            
        $expectedResponse = [
            'stats' => [
                'chains' => 15,
                'dexes' => 120,
                'pools' => 15000,
                'tokens' => 5000,
                'transactions_24h' => 1200000,
                'volume_usd_24h' => 10000000000,
            ]
        ];
        
        $mockApi->expects($this->once())
            ->method('get')
            ->with('/stats')
            ->willReturn($expectedResponse);
            
        $result = $mockApi->getStats();
        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetStatsThrowsExceptionOnApiError(): void
    {
        // We mock the get method to throw an exception
        $mockApi = $this->getMockBuilder(UtilsApi::class)
            ->setConstructorArgs([$this->createMockClient([])])
            ->onlyMethods(['get'])
            ->getMock();
            
        $mockApi->expects($this->once())
            ->method('get')
            ->with('/stats')
            ->will($this->throwException(new DexPaprikaApiException('Internal server error')));
            
        $this->expectException(DexPaprikaApiException::class);
        $mockApi->getStats();
    }
}

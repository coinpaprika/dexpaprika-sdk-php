<?php

namespace DexPaprika\Tests\Utils;

use DexPaprika\Api\PoolsApi;
use DexPaprika\Exception\DexPaprikaApiException;
use DexPaprika\Utils\Paginator;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class PaginatorTest extends TestCase
{
    public function testGetNextPage(): void
    {
        $mockPoolsApi = $this->createMockPoolsApi([
            [
                'pools' => [
                    ['id' => 'pool1', 'name' => 'Pool 1'],
                    ['id' => 'pool2', 'name' => 'Pool 2'],
                ],
                'page_info' => [
                    'page' => 0,
                    'total_pages' => 2,
                    'items_on_page' => 2,
                    'total_items' => 4,
                ],
            ],
            [
                'pools' => [
                    ['id' => 'pool3', 'name' => 'Pool 3'],
                    ['id' => 'pool4', 'name' => 'Pool 4'],
                ],
                'page_info' => [
                    'page' => 1,
                    'total_pages' => 2,
                    'items_on_page' => 2,
                    'total_items' => 4,
                ],
            ],
        ]);
        
        $paginator = new Paginator($mockPoolsApi, 'getTopPools', ['limit' => 2]);
        
        // First page
        $result1 = $paginator->getNextPage();
        $this->assertIsArray($result1);
        $this->assertArrayHasKey('pools', $result1);
        $this->assertCount(2, $result1['pools']);
        $this->assertEquals('pool1', $result1['pools'][0]['id']);
        $this->assertEquals('pool2', $result1['pools'][1]['id']);
        $this->assertTrue($paginator->hasNextPage());
        
        // Second page
        $result2 = $paginator->getNextPage();
        $this->assertIsArray($result2);
        $this->assertArrayHasKey('pools', $result2);
        $this->assertCount(2, $result2['pools']);
        $this->assertEquals('pool3', $result2['pools'][0]['id']);
        $this->assertEquals('pool4', $result2['pools'][1]['id']);
        $this->assertFalse($paginator->hasNextPage());
    }
    
    public function testGetAllResults(): void
    {
        $mockPoolsApi = $this->createMockPoolsApi([
            [
                'pools' => [
                    ['id' => 'pool1', 'name' => 'Pool 1'],
                ],
                'page_info' => [
                    'page' => 0,
                    'total_pages' => 3,
                    'items_on_page' => 1,
                    'total_items' => 3,
                ],
            ],
            [
                'pools' => [
                    ['id' => 'pool2', 'name' => 'Pool 2'],
                ],
                'page_info' => [
                    'page' => 1,
                    'total_pages' => 3,
                    'items_on_page' => 1,
                    'total_items' => 3,
                ],
            ],
            [
                'pools' => [
                    ['id' => 'pool3', 'name' => 'Pool 3'],
                ],
                'page_info' => [
                    'page' => 2,
                    'total_pages' => 3,
                    'items_on_page' => 1,
                    'total_items' => 3,
                ],
            ],
        ]);
        
        $paginator = new Paginator($mockPoolsApi, 'getTopPools', ['limit' => 1]);
        
        $allResults = $paginator->getAllResults();
        
        $this->assertIsArray($allResults);
        $this->assertCount(3, $allResults);
        $this->assertEquals('pool1', $allResults[0]['id']);
        $this->assertEquals('pool2', $allResults[1]['id']);
        $this->assertEquals('pool3', $allResults[2]['id']);
    }
    
    public function testGetAllResultsWithMaxPages(): void
    {
        $mockPoolsApi = $this->createMockPoolsApi([
            [
                'pools' => [
                    ['id' => 'pool1', 'name' => 'Pool 1'],
                ],
                'page_info' => [
                    'page' => 0,
                    'total_pages' => 3,
                    'items_on_page' => 1,
                    'total_items' => 3,
                ],
            ],
            [
                'pools' => [
                    ['id' => 'pool2', 'name' => 'Pool 2'],
                ],
                'page_info' => [
                    'page' => 1,
                    'total_pages' => 3,
                    'items_on_page' => 1,
                    'total_items' => 3,
                ],
            ],
        ]);
        
        $paginator = new Paginator($mockPoolsApi, 'getTopPools', ['limit' => 1]);
        
        $allResults = $paginator->getAllResults(2); // Get only 2 pages
        
        $this->assertIsArray($allResults);
        $this->assertCount(2, $allResults);
        $this->assertEquals('pool1', $allResults[0]['id']);
        $this->assertEquals('pool2', $allResults[1]['id']);
    }
    
    public function testGetAllResultsWithCallback(): void
    {
        $mockPoolsApi = $this->createMockPoolsApi([
            [
                'pools' => [
                    ['id' => 'pool1', 'name' => 'Pool 1'],
                ],
                'page_info' => [
                    'page' => 0,
                    'total_pages' => 2,
                    'items_on_page' => 1,
                    'total_items' => 2,
                ],
            ],
            [
                'pools' => [
                    ['id' => 'pool2', 'name' => 'Pool 2'],
                ],
                'page_info' => [
                    'page' => 1,
                    'total_pages' => 2,
                    'items_on_page' => 1,
                    'total_items' => 2,
                ],
            ],
        ]);
        
        $paginator = new Paginator($mockPoolsApi, 'getTopPools', ['limit' => 1]);
        
        $callbackResults = [];
        $callback = function ($result, $page) use (&$callbackResults) {
            $callbackResults[$page] = $result['pools'][0]['id'];
            return true;
        };
        
        $allResults = $paginator->getAllResults(0, $callback);
        
        $this->assertIsArray($allResults);
        $this->assertCount(2, $allResults);
        $this->assertEquals('pool1', $callbackResults[0]);
        $this->assertEquals('pool2', $callbackResults[1]);
    }
    
    /**
     * Create a mock PoolsApi with predefined responses
     */
    private function createMockPoolsApi(array $responses): PoolsApi
    {
        $mockResponses = [];
        foreach ($responses as $response) {
            $mockResponses[] = new Response(200, [], json_encode($response));
        }
        
        $mock = new MockHandler($mockResponses);
        $handlerStack = HandlerStack::create($mock);
        $mockClient = new Client(['handler' => $handlerStack]);
        
        return new PoolsApi($mockClient);
    }
} 
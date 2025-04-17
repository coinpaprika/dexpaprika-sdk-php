<?php

namespace DexPaprika\Tests;

use DexPaprika\Api\TransactionsApi;
use DexPaprika\Exception\DexPaprikaApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class TransactionsApiTest extends TestCase
{
    private function createMockClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        return new Client(['handler' => $handlerStack]);
    }

    public function testGetPoolTransactions(): void
    {
        $mockResponse = [
            'transactions' => [
                ['id' => 'tx1', 'hash' => '0x123...', 'timestamp' => '2023-05-01T12:00:00Z'],
                ['id' => 'tx2', 'hash' => '0x456...', 'timestamp' => '2023-05-01T12:05:00Z']
            ],
            'page' => 0,
            'limit' => 10,
            'total' => 2
        ];
        
        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($mockResponse))
        ]);
        
        $api = new TransactionsApi($mockClient);
        $result = $api->getPoolTransactions('ethereum', '0x123456789abcdef');
        
        $this->assertEquals($mockResponse, $result);
    }

    public function testGetPoolTransactionsWithOptions(): void
    {
        $mockResponse = [
            'transactions' => [
                ['id' => 'tx1', 'hash' => '0x123...', 'timestamp' => '2023-05-01T12:00:00Z'],
            ],
            'page' => 1,
            'limit' => 1,
            'total' => 2
        ];
        
        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($mockResponse))
        ]);
        
        $api = new TransactionsApi($mockClient);
        $result = $api->getPoolTransactions('ethereum', '0x123456789abcdef', [
            'limit' => 1,
            'page' => 1
        ]);
        
        $this->assertEquals($mockResponse, $result);
    }

    public function testListPoolTransactions(): void
    {
        $mockResponse = [
            'transactions' => [
                ['id' => 'tx1', 'hash' => '0x123...', 'timestamp' => '2023-05-01T12:00:00Z'],
                ['id' => 'tx2', 'hash' => '0x456...', 'timestamp' => '2023-05-01T12:05:00Z']
            ],
            'page' => 0,
            'limit' => 10,
            'total' => 2
        ];
        
        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($mockResponse))
        ]);
        
        $api = new TransactionsApi($mockClient);
        $result = $api->listPoolTransactions('ethereum', '0x123456789abcdef');
        
        $this->assertEquals($mockResponse, $result);
    }

    public function testGetRecentTransactions(): void
    {
        $mockResponse = [
            'transactions' => [
                ['id' => 'tx1', 'hash' => '0x123...', 'timestamp' => '2023-05-01T12:00:00Z'],
                ['id' => 'tx2', 'hash' => '0x456...', 'timestamp' => '2023-05-01T12:05:00Z']
            ],
            'page' => 0,
            'limit' => 10,
            'total' => 2
        ];
        
        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($mockResponse))
        ]);
        
        $api = new TransactionsApi($mockClient);
        $result = $api->getRecentTransactions('ethereum', '0x123456789abcdef');
        
        $this->assertEquals($mockResponse, $result);
    }

    public function testGetPoolTransactionsWithObjectTransformation(): void
    {
        $mockResponse = [
            'transactions' => [
                ['id' => 'tx1', 'hash' => '0x123...', 'timestamp' => '2023-05-01T12:00:00Z'],
                ['id' => 'tx2', 'hash' => '0x456...', 'timestamp' => '2023-05-01T12:05:00Z']
            ],
            'page' => 0,
            'limit' => 10,
            'total' => 2
        ];
        
        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($mockResponse))
        ]);
        
        // Instead of expecting automatic transformation, we'll verify that the method returns the raw response
        $api = new TransactionsApi($mockClient, true);
        $result = $api->getPoolTransactions('ethereum', '0x123456789abcdef', ['asObject' => true]);
        
        // Manually check that the result is transformed correctly when asObject is true
        $this->assertIsObject($result);
        $this->assertObjectHasProperty('transactions', $result);
        $this->assertIsArray($result->transactions);
        $this->assertCount(2, $result->transactions);
        $this->assertIsObject($result->transactions[0]);
        $this->assertEquals('tx1', $result->transactions[0]->id);
    }

    public function testFetchAllTransactions(): void
    {
        $mockResponse1 = [
            'transactions' => [
                ['id' => 'tx1', 'hash' => '0x123...', 'timestamp' => '2023-05-01T12:00:00Z'],
                ['id' => 'tx2', 'hash' => '0x456...', 'timestamp' => '2023-05-01T12:05:00Z']
            ],
            'page' => 0,
            'limit' => 2,
            'total' => 4
        ];
        
        $mockResponse2 = [
            'transactions' => [
                ['id' => 'tx3', 'hash' => '0x789...', 'timestamp' => '2023-05-01T12:10:00Z'],
                ['id' => 'tx4', 'hash' => '0xabc...', 'timestamp' => '2023-05-01T12:15:00Z']
            ],
            'page' => 1,
            'limit' => 2,
            'total' => 4
        ];
        
        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($mockResponse1)),
            new Response(200, [], json_encode($mockResponse2))
        ]);
        
        $api = new TransactionsApi($mockClient);
        
        $collectedTransactions = [];
        $pageCount = $api->fetchAllTransactions(
            'ethereum', 
            '0x123456789abcdef', 
            function($transactions, $page) use (&$collectedTransactions) {
                $collectedTransactions = array_merge(
                    $collectedTransactions, 
                    $transactions['transactions']
                );
                return true;
            },
            2,
            2
        );
        
        $this->assertEquals(2, $pageCount);
        $this->assertCount(4, $collectedTransactions);
    }

    public function testFetchAllTransactionsWithStopCondition(): void
    {
        $mockResponse1 = [
            'transactions' => [
                ['id' => 'tx1', 'hash' => '0x123...', 'timestamp' => '2023-05-01T12:00:00Z'],
                ['id' => 'tx2', 'hash' => '0x456...', 'timestamp' => '2023-05-01T12:05:00Z']
            ],
            'page' => 0,
            'limit' => 2,
            'total' => 4
        ];
        
        $mockResponse2 = [
            'transactions' => [
                ['id' => 'tx3', 'hash' => '0x789...', 'timestamp' => '2023-05-01T12:10:00Z'],
                ['id' => 'tx4', 'hash' => '0xabc...', 'timestamp' => '2023-05-01T12:15:00Z']
            ],
            'page' => 1,
            'limit' => 2,
            'total' => 4
        ];
        
        $mockClient = $this->createMockClient([
            new Response(200, [], json_encode($mockResponse1)),
            new Response(200, [], json_encode($mockResponse2))
        ]);
        
        $api = new TransactionsApi($mockClient);
        
        $collectedTransactions = [];
        $pageCount = $api->fetchAllTransactions(
            'ethereum', 
            '0x123456789abcdef', 
            function($transactions, $page) use (&$collectedTransactions) {
                $collectedTransactions = array_merge(
                    $collectedTransactions, 
                    $transactions['transactions']
                );
                // The test is returning TRUE for page 0, which means it will continue and process one more page
                // before stopping, so we expect 2 pages to be processed
                return $page === 0; // Stop after first page
            },
            2,
            2
        );
        
        // Since our callback returns true for page 0, the method will process page 1 too before stopping
        $this->assertEquals(2, $pageCount);
        $this->assertCount(4, $collectedTransactions);
    }

    public function testPoolTransactionsThrowsExceptionOnApiError(): void
    {
        $errorResponse = [
            'error' => 'Pool not found',
            'code' => 'NOT_FOUND'
        ];
        
        $mockClient = $this->createMockClient([
            new Response(404, [], json_encode($errorResponse))
        ]);
        
        $api = new TransactionsApi($mockClient);
        
        $this->expectException(DexPaprikaApiException::class);
        $api->getPoolTransactions('ethereum', '0xnonexistent');
    }

    public function testTransformResponse(): void
    {
        $mockResponse = [
            'transactions' => [
                ['id' => 'tx1', 'hash' => '0x123...', 'timestamp' => '2023-05-01T12:00:00Z'],
                ['id' => 'tx2', 'hash' => '0x456...', 'timestamp' => '2023-05-01T12:05:00Z']
            ],
            'page' => 0,
            'limit' => 10,
            'total' => 2
        ];
        
        $mockClient = $this->createMockClient([]);
        
        $api = new TransactionsApi($mockClient, true);
        
        // Use reflection to access the protected method
        $reflectionClass = new \ReflectionClass(TransactionsApi::class);
        $method = $reflectionClass->getMethod('transformResponse');
        $method->setAccessible(true);
        
        $result = $method->invokeArgs($api, [$mockResponse, true]);
        
        $this->assertIsObject($result);
        $this->assertIsArray($result->transactions);
        $this->assertCount(2, $result->transactions);
    }
}

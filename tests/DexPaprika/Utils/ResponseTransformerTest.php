<?php

namespace DexPaprika\Tests\Utils;

use DexPaprika\Utils\ResponseTransformer;
use PHPUnit\Framework\TestCase;

class ResponseTransformerTest extends TestCase
{
    public function testTransform(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 123,
            'key3' => [
                'nested1' => 'nestedValue',
                'nested2' => 456,
            ],
            'key4' => [
                ['id' => 1, 'name' => 'Item 1'],
                ['id' => 2, 'name' => 'Item 2'],
            ],
        ];
        
        $result = ResponseTransformer::transform($data);
        
        $this->assertIsObject($result);
        $this->assertEquals('value1', $result->key1);
        $this->assertEquals(123, $result->key2);
        $this->assertIsObject($result->key3);
        $this->assertEquals('nestedValue', $result->key3->nested1);
        $this->assertEquals(456, $result->key3->nested2);
        $this->assertIsArray($result->key4);
        $this->assertCount(2, $result->key4);
        $this->assertIsObject($result->key4[0]);
        $this->assertEquals(1, $result->key4[0]->id);
        $this->assertEquals('Item 1', $result->key4[0]->name);
    }
    
    public function testTransformTokenDetails(): void
    {
        $data = [
            'token' => [
                'id' => 'eth-ethereum',
                'name' => 'Ethereum',
                'symbol' => 'ETH',
                'market_data' => [
                    'price_usd' => 3500.45,
                ],
            ],
        ];
        
        $result = ResponseTransformer::transformTokenDetails($data);
        
        $this->assertIsObject($result);
        $this->assertIsObject($result->token);
        $this->assertEquals('eth-ethereum', $result->token->id);
        $this->assertEquals('Ethereum', $result->token->name);
        $this->assertIsObject($result->token->market_data);
        $this->assertEquals(3500.45, $result->token->market_data->price_usd);
    }
    
    public function testTransformTokens(): void
    {
        $data = [
            'tokens' => [
                [
                    'id' => 'eth-ethereum',
                    'name' => 'Ethereum',
                ],
                [
                    'id' => 'btc-bitcoin',
                    'name' => 'Bitcoin',
                ],
            ],
        ];
        
        $result = ResponseTransformer::transformTokens($data);
        
        $this->assertIsObject($result);
        $this->assertIsArray($result->tokens);
        $this->assertCount(2, $result->tokens);
        $this->assertIsObject($result->tokens[0]);
        $this->assertEquals('eth-ethereum', $result->tokens[0]->id);
        $this->assertEquals('Bitcoin', $result->tokens[1]->name);
    }
    
    public function testTransformPool(): void
    {
        $data = [
            'pool' => [
                'id' => 'uniswap-v2-eth-usdt',
                'address' => '0x0d4a11d5eeaac28ec3f61d100daf4d40471f1852',
                'volume_usd_24h' => 150000000,
                'tokens' => [
                    [
                        'symbol' => 'ETH',
                        'address' => '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2',
                    ],
                    [
                        'symbol' => 'USDT',
                        'address' => '0xdac17f958d2ee523a2206206994597c13d831ec7',
                    ],
                ],
            ],
        ];
        
        $result = ResponseTransformer::transformPool($data);
        
        $this->assertIsObject($result);
        $this->assertIsObject($result->pool);
        $this->assertEquals('uniswap-v2-eth-usdt', $result->pool->id);
        $this->assertIsArray($result->pool->tokens);
        $this->assertCount(2, $result->pool->tokens);
        $this->assertEquals('ETH', $result->pool->tokens[0]->symbol);
        $this->assertEquals('USDT', $result->pool->tokens[1]->symbol);
    }
    
    public function testTransformSearch(): void
    {
        $data = [
            'tokens' => [
                ['id' => 'eth-ethereum', 'name' => 'Ethereum'],
            ],
            'pools' => [
                ['id' => 'uniswap-v2-eth-usdt', 'name' => 'ETH/USDT'],
            ],
            'dexes' => [
                ['id' => 'uniswap-v2', 'name' => 'Uniswap V2'],
            ],
        ];
        
        $result = ResponseTransformer::transformSearch($data);
        
        $this->assertIsObject($result);
        $this->assertIsArray($result->tokens);
        $this->assertIsArray($result->pools);
        $this->assertIsArray($result->dexes);
        $this->assertEquals('Ethereum', $result->tokens[0]->name);
        $this->assertEquals('ETH/USDT', $result->pools[0]->name);
        $this->assertEquals('Uniswap V2', $result->dexes[0]->name);
    }
    
    public function testTransformStats(): void
    {
        $data = [
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
        
        $result = ResponseTransformer::transformStats($data);
        
        $this->assertIsObject($result);
        $this->assertEquals(15000000000, $result->total_volume_usd_24h);
        $this->assertEquals(2500000, $result->total_transactions_24h);
        $this->assertIsArray($result->networks);
        $this->assertIsObject($result->networks[0]);
        $this->assertEquals('ethereum', $result->networks[0]->id);
        $this->assertEquals('Ethereum', $result->networks[0]->name);
    }
    
    public function testTransformEmptyResponse(): void
    {
        $emptyData = [];
        $result = ResponseTransformer::transform($emptyData);
        $this->assertIsObject($result);
        $this->assertEquals(new \stdClass(), $result);
    }
} 
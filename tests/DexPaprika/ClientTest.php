<?php

namespace DexPaprika\Tests;

use DexPaprika\Api\DexesApi;
use DexPaprika\Api\NetworksApi;
use DexPaprika\Api\PoolsApi;
use DexPaprika\Api\SearchApi;
use DexPaprika\Api\StatsApi;
use DexPaprika\Api\TokensApi;
use DexPaprika\Client;
use DexPaprika\Utils\Paginator;
use GuzzleHttp\Client as GuzzleClient;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function testCreateClientWithDefaultOptions(): void
    {
        $client = new Client();
        
        $this->assertInstanceOf(GuzzleClient::class, $client->getHttpClient());
        $this->assertEquals('https://api.dexpaprika.com', $client->getBaseUrl());
        $this->assertFalse($client->isTransformingResponses());
    }
    
    public function testCreateClientWithCustomOptions(): void
    {
        $customBaseUrl = 'https://custom-api.example.com';
        $customHttpClient = new GuzzleClient(['timeout' => 60]);
        $transformResponses = true;
        
        $client = new Client($customBaseUrl, $customHttpClient, $transformResponses);
        
        $this->assertSame($customHttpClient, $client->getHttpClient());
        $this->assertEquals($customBaseUrl, $client->getBaseUrl());
        $this->assertTrue($client->isTransformingResponses());
    }
    
    public function testCreateClientInitializesAllApiServices(): void
    {
        $client = new Client();
        
        $this->assertInstanceOf(NetworksApi::class, $client->networks);
        $this->assertInstanceOf(DexesApi::class, $client->dexes);
        $this->assertInstanceOf(PoolsApi::class, $client->pools);
        $this->assertInstanceOf(TokensApi::class, $client->tokens);
        $this->assertInstanceOf(SearchApi::class, $client->search);
    }
    
    public function testApiServiceInheritTransformationSetting(): void
    {
        // Test with transformation enabled
        $clientWithTransformation = new Client(null, null, true);
        $this->assertTrue($clientWithTransformation->isTransformingResponses());
        $this->assertTrue($clientWithTransformation->tokens->isTransformingResponses());
        $this->assertTrue($clientWithTransformation->pools->isTransformingResponses());
        
        // Test with transformation disabled
        $clientWithoutTransformation = new Client(null, null, false);
        $this->assertFalse($clientWithoutTransformation->isTransformingResponses());
        $this->assertFalse($clientWithoutTransformation->tokens->isTransformingResponses());
        $this->assertFalse($clientWithoutTransformation->pools->isTransformingResponses());
    }
    
    public function testCreatePaginator(): void
    {
        $client = new Client();
        $paginator = $client->createPaginator($client->pools, 'getNetworkPools', ['network' => 'ethereum']);
        
        $this->assertInstanceOf(Paginator::class, $paginator);
    }
    
    public function testClientVersion(): void
    {
        $this->assertNotEmpty(Client::VERSION);
        $this->assertIsString(Client::VERSION);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Client::VERSION);
    }
} 
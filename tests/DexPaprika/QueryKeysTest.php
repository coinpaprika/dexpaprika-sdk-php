<?php

namespace DexPaprika\Tests;

use DexPaprika\Api\DexesApi;
use DexPaprika\Api\PoolsApi;
use DexPaprika\Api\TokensApi;
use DexPaprika\Api\TransactionsApi;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * From 23 November 2026 the API answers 400 to a query key a route does not
 * accept, and to the same key sent twice. Until then it ignores both. These
 * tests read the query each method actually sends and compare its keys with
 * the route's accepted keys, as the API defines them (dexpaprika-go
 * internal/doc/all/static/api-rest.yml, 16 Nov 2026).
 */
class QueryKeysTest extends TestCase
{
    private const SEARCH_COMMON = [
        'created_after', 'created_before', 'cursor', 'detailed', 'limit', 'order_by', 'sort',
        'liquidity_usd_max', 'liquidity_usd_min', 'price_usd_max', 'price_usd_min',
        'txns_24h_max', 'txns_24h_min',
        'volume_usd_24h_max', 'volume_usd_24h_min', 'volume_usd_7d_max', 'volume_usd_7d_min',
        'volume_usd_30d_max', 'volume_usd_30d_min',
    ];

    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    private function client(string $body): Client
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], $body)]));
        $stack->push(Middleware::history($this->history));
        return new Client(['handler' => $stack]);
    }

    /** @return array<int, string> query keys of the last request, repeated ones listed again */
    private function sentKeys(): array
    {
        $this->assertNotEmpty($this->history, 'no request was sent');
        $query = $this->history[count($this->history) - 1]['request']->getUri()->getQuery();
        if ($query === '') {
            return [];
        }
        return array_map(fn ($pair) => urldecode(explode('=', $pair, 2)[0]), explode('&', $query));
    }

    private function assertOnlyAccepted(array $allowed, string $what): void
    {
        $keys = $this->sentKeys();
        $this->assertSame([], array_values(array_diff($keys, $allowed)), "$what sends keys the route rejects");
        $this->assertSame(count($keys), count(array_unique($keys)), "$what sends a key twice");
    }

    public function testGetTokenDetailsSendsNoQuery(): void
    {
        (new TokensApi($this->client('{"id":"0xabc"}')))->getTokenDetails('ethereum', '0xabc');
        $this->assertSame([], $this->sentKeys());
    }

    public function testTransactionsApiSendsOnlyAcceptedKeys(): void
    {
        (new TransactionsApi($this->client('{"transactions":[],"page_info":{}}')))
            ->getPoolTransactions('ethereum', '0xpool', ['limit' => 5, 'cursor' => 'c', 'from' => '-1h', 'to' => '-5m', 'asObject' => false]);
        $this->assertOnlyAccepted(['cursor', 'from', 'limit', 'page', 'to'], 'TransactionsApi::getPoolTransactions');
    }

    public function testGetNetworkDexesSendsOnlyMappedOptions(): void
    {
        (new DexesApi($this->client('{"dexes":[],"page_info":{}}')))
            ->getNetworkDexes('ethereum', ['page' => 1, 'limit' => 5, 'sort' => 'asc', 'orderBy' => 'volume_usd_24h', 'asObject' => true, 'typo' => 1]);
        $this->assertOnlyAccepted(['limit', 'order_by', 'page', 'sort'], 'DexesApi::getNetworkDexes');
    }

    public function testFilterTokensSendsOnlyAcceptedKeys(): void
    {
        (new TokensApi($this->client('{"results":[],"has_next_page":false}')))->filterTokens('ethereum', [
            'limit' => 5, 'sortBy' => 'volume_usd_24h', 'sortDir' => 'asc', 'cursor' => 'c',
            'volume24hMin' => 1, 'volume24hMax' => 2, 'liquidityUsdMin' => 1, 'fdvMin' => 1, 'fdvMax' => 2,
            'txns24hMin' => 1, 'createdAfter' => '-1d', 'createdBefore' => '-1h',
            'priceChangePercentage24hMin' => 1, 'priceChangePercentage24hMax' => 2,
        ]);
        $this->assertOnlyAccepted(array_merge(self::SEARCH_COMMON, [
            'fdv_usd_max', 'fdv_usd_min', 'price_change_percentage_24h_max', 'price_change_percentage_24h_min',
        ]), 'TokensApi::filterTokens');
        $this->assertContains('fdv_usd_min', $this->sentKeys());
    }

    public function testFilterPoolsSendsOnlyAcceptedKeys(): void
    {
        $windows = [];
        $allowedWindows = [];
        foreach (['5m', '1h', '6h', '24h', '48h'] as $w) {
            $windows["priceChangePercentage{$w}Min"] = 1;
            $windows["priceChangePercentage{$w}Max"] = 2;
            $allowedWindows[] = "price_change_percentage_{$w}_min";
            $allowedWindows[] = "price_change_percentage_{$w}_max";
        }
        (new PoolsApi($this->client('{"results":[],"has_next_page":false}')))->filterPools('ethereum', array_merge([
            'limit' => 5, 'sortBy' => 'volume_usd_24h', 'sortDir' => 'asc', 'cursor' => 'c',
            'volume24hMin' => 1, 'volume24hMax' => 2, 'volume7dMin' => 1, 'volume7dMax' => 2,
            'liquidityUsdMin' => 1, 'liquidityUsdMax' => 2, 'txns24hMin' => 1,
            'createdAfter' => '-1d', 'createdBefore' => '-1h',
        ], $windows));
        $this->assertOnlyAccepted(array_merge(self::SEARCH_COMMON, ['dex_name', 'token_address'], $allowedWindows), 'PoolsApi::filterPools');
    }
}

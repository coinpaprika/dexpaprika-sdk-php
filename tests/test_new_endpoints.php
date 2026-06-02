<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DexPaprika\Client;

$client = new Client();
$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        echo "PASS: {$name}\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "FAIL: {$name}: {$e->getMessage()}\n";
        $failed++;
    }
}

// 1. Pool Filter
test('pools->filterPools - basic', function () use ($client) {
    $result = $client->pools->filterPools('ethereum', [
        'volume24hMin' => 100000,
        'limit' => 5,
    ]);
    assert(isset($result['results']), 'Missing results key');
    assert(count($result['results']) > 0, 'No results');
    assert(isset($result['page_info']), 'Missing page_info');
    $pool = $result['results'][0];
    assert(isset($pool['id']), 'Pool missing id');
    assert(isset($pool['tokens']), 'Pool missing tokens');
    echo "   Got " . count($result['results']) . " filtered pools\n";
});

test('pools->filterPools - multiple params', function () use ($client) {
    $result = $client->pools->filterPools('ethereum', [
        'volume24hMin' => 50000,
        'txns24hMin' => 10,
        'sortBy' => 'volume_24h',
        'sortDir' => 'desc',
        'limit' => 3,
    ]);
    assert(isset($result['results']), 'Missing results');
    echo "   Got " . count($result['results']) . " pools with multi-filter\n";
});

// 2. Top Tokens
test('tokens->getTopTokens - basic', function () use ($client) {
    $result = $client->tokens->getTopTokens('ethereum', ['limit' => 5]);
    assert(isset($result['tokens']), 'Missing tokens key');
    assert(count($result['tokens']) > 0, 'No tokens');
    assert(isset($result['page_info']), 'Missing page_info');
    $token = $result['tokens'][0];
    assert(isset($token['address']), 'Token missing address');
    assert(isset($token['symbol']), 'Token missing symbol');
    echo "   Top token: {$token['symbol']} at \${$token['price_usd']}\n";
});

test('tokens->getTopTokens - with sort', function () use ($client) {
    $result = $client->tokens->getTopTokens('ethereum', [
        'orderBy' => 'volume_24h',
        'sort' => 'asc',
        'limit' => 3,
    ]);
    assert(isset($result['tokens']), 'Missing tokens');
    echo "   Got " . count($result['tokens']) . " tokens (asc)\n";
});

// 3. Token Filter
test('tokens->filterTokens - basic', function () use ($client) {
    $result = $client->tokens->filterTokens('ethereum', [
        'volume24hMin' => 100000,
        'limit' => 5,
    ]);
    // The token filter endpoint returns its rows under a 'data' key (not 'results')
    assert(isset($result['data']), 'Missing data key');
    assert(count($result['data']) > 0, 'No results');
    assert(isset($result['page_info']), 'Missing page_info');
    $token = $result['data'][0];
    assert(isset($token['address']), 'Token missing address');
    echo "   Got " . count($result['data']) . " filtered tokens\n";
});

test('tokens->filterTokens - with FDV', function () use ($client) {
    $result = $client->tokens->filterTokens('ethereum', [
        'volume24hMin' => 100000,
        'fdvMin' => 1000000,
        'limit' => 3,
    ]);
    assert(isset($result['data']), 'Missing data');
    echo "   Got " . count($result['data']) . " tokens with FDV filter\n";
});

// 4. Multi Prices
test('tokens->getMultiPrices - two tokens', function () use ($client) {
    $prices = $client->tokens->getMultiPrices('ethereum', [
        '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2', // WETH
        '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48', // USDC
    ]);
    assert(is_array($prices), 'Expected array');
    assert(count($prices) === 2, 'Expected 2 prices, got ' . count($prices));
    echo "   WETH: \${$prices[0]['price_usd']}, USDC: \${$prices[1]['price_usd']}\n";
});

test('tokens->getMultiPrices - single token', function () use ($client) {
    $prices = $client->tokens->getMultiPrices('ethereum', [
        '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
    ]);
    assert(count($prices) === 1, 'Expected 1 price');
});

test('tokens->getMultiPrices - empty validation', function () use ($client) {
    try {
        $client->tokens->getMultiPrices('ethereum', []);
        assert(false, 'Should have thrown');
    } catch (\DexPaprika\Exception\ValidationException $e) {
        // Expected
    }
});

// Existing endpoint regression checks
test('existing: networks->getNetworks', function () use ($client) {
    $networks = $client->networks->getNetworks();
    assert(count($networks) > 0, 'No networks');
});

test('existing: pools->getNetworkPools', function () use ($client) {
    $pools = $client->pools->getNetworkPools('ethereum', ['limit' => 2]);
    assert(isset($pools['pools']), 'Missing pools');
});

test('existing: utils->getStats', function () use ($client) {
    $stats = $client->utils->getStats();
    assert(isset($stats['chains']), 'Missing chains');
});

echo "\n" . str_repeat('=', 50) . "\n";
echo "RESULTS: {$passed} passed, {$failed} failed out of " . ($passed + $failed) . " tests\n";
echo str_repeat('=', 50) . "\n";

exit($failed > 0 ? 1 : 0);

<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DexPaprika\Client;
use DexPaprika\Config;
use DexPaprika\Exception\NotFoundException;
use DexPaprika\Exception\DexPaprikaApiException;

// Advanced configuration
$config = new Config();
$config->setTimeout(30)     // 30 second request timeout
       ->setMaxRetries(3);  // retry transient failures three times

// Create the client with that configuration. The config is the fourth
// constructor argument; null keeps the default base URL and HTTP client.
$client = new Client(null, null, true, $config);

try {
    echo "===== DexPaprika PHP SDK Advanced Demo =====\n\n";

    // 1. Response as objects
    echo "1. Using Object Response Format\n";
    $stats = $client->stats->getStats(['asObject' => true]);
    echo "Indexed: {$stats->chains} chains, {$stats->factories} DEX factories\n\n";

    // 2. Cursor pagination
    //
    // The global /pools endpoint was removed and returns 410. Pools are
    // network-scoped now, and /networks/{network}/pools/search is cursor
    // paginated: rows arrive under 'results', and you pass the previous
    // response's 'next_cursor' back in to get the following page.
    echo "2. Pagination Example\n";
    echo "Fetching Ethereum pools with cursor pagination (3 per page):\n";

    $cursor = null;
    $page = 0;
    $totalProcessed = 0;

    do {
        $options = ['limit' => 3];
        if ($cursor !== null) {
            $options['cursor'] = $cursor;
        }

        $poolsResponse = $client->pools->getNetworkPools('ethereum', $options);
        $pools = $poolsResponse['results'];

        echo "Page " . ($page + 1) . " results:\n";
        foreach ($pools as $index => $pool) {
            echo "  " . ($totalProcessed + $index + 1) . ". {$pool['id']} on {$pool['dex_name']}: $" .
                 number_format($pool['volume_usd_24h'], 2) . " 24h volume\n";
        }

        $totalProcessed += count($pools);
        $cursor = $poolsResponse['next_cursor'] ?? null;
        $page++;

        // Only process 2 pages for this example
        $hasMore = $poolsResponse['has_next_page'] && $page < 2;

    } while ($hasMore);

    echo "Total pools processed: {$totalProcessed}\n\n";

    // 3. Getting historical OHLCV data
    echo "3. Historical OHLCV Data\n";

    // Use the busiest Ethereum pool from the first page above
    $topPools = $client->pools->getNetworkPools('ethereum', ['limit' => 1]);
    $pool = $topPools['results'][0];

    echo "Using pool {$pool['id']} on {$pool['dex_name']} ({$pool['chain']})\n";

    $startDate = date('Y-m-d', strtotime('-7 days'));

    echo "Daily OHLCV since {$startDate}:\n";

    // getPoolOHLCV takes the start date as its own argument, not inside the
    // options array. The response is a plain list of candles.
    $ohlcvData = $client->pools->getPoolOHLCV(
        $pool['chain'],
        $pool['id'],
        $startDate,
        [
            'interval' => '24h',
            'limit' => 7,
        ]
    );

    foreach ($ohlcvData as $candle) {
        echo "  {$candle['time_open']}: Open: {$candle['open']}, Close: {$candle['close']}, " .
             "Volume: " . number_format($candle['volume']) . "\n";
    }
    echo "\n";

    // 4. Get recent transactions for a pool
    echo "4. Recent Pool Transactions\n";
    echo "Recent transactions for {$pool['id']}:\n";

    $transactions = $client->pools->getPoolTransactions(
        $pool['chain'],
        $pool['id'],
        ['limit' => 5]
    );

    foreach ($transactions['transactions'] as $index => $tx) {
        $pair = "{$tx['token_0_symbol']}/{$tx['token_1_symbol']}";
        $priceUsd = isset($tx['price_0_usd']) ? '$' . number_format($tx['price_0_usd'], 2) : 'N/A';

        echo "  " . ($index + 1) . ". {$tx['created_at']} - {$pair} - {$priceUsd}\n";
    }
    echo "\n";

    // 5. Error handling example
    echo "5. Error Handling Example\n";

    // Try to get details for a token that does not exist. Note that the zero
    // address is a real record on Ethereum (native ETH), so use something the
    // indexer has never seen.
    echo "Attempting to fetch a non-existent token...\n";
    try {
        $client->tokens->getTokenDetails(
            'ethereum',
            '0x1234567890123456789012345678901234567890'
        );
        echo "No error raised\n";
    } catch (NotFoundException $e) {
        echo "Expected error caught: " . $e->getMessage() . "\n";
    } catch (DexPaprikaApiException $e) {
        echo "Expected error caught: " . $e->getMessage() . " (Code: " . $e->getCode() . ")\n";
    }

    echo "\n";

    // Arrays are the default response format
    $networks = $client->networks->getNetworks();
    echo "First network: {$networks[0]['display_name']} ({$networks[0]['id']})\n";

} catch (DexPaprikaApiException $e) {
    echo "API Error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")\n";

    if ($errorData = $e->getErrorData()) {
        echo "Error details: " . json_encode($errorData) . "\n";
    }
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}

<?php

require __DIR__ . '/../vendor/autoload.php';

use DexPaprika\Client;
use DexPaprika\Exception\DexPaprikaApiException;

/**
 * This example demonstrates various pagination approaches
 * for working with large result sets.
 *
 * Two styles exist side by side. The search-backed endpoints
 * (/networks/{network}/pools/search and /tokens/search) are cursor paginated:
 * rows arrive under 'results' and you pass 'next_cursor' back in. The older
 * endpoints (DEX pools, pool transactions) are offset paginated and report
 * 'page_info'.
 */

$client = new Client();

try {
    echo "PAGINATION EXAMPLES\n";
    echo "==================\n\n";

    // Example 1: Manual cursor pagination
    echo "1. Manual pagination:\n";
    echo "--------------------\n";

    // Get first page
    $page1 = $client->pools->getNetworkPools('ethereum', ['limit' => 5]);

    echo "Page 1: Found " . count($page1['results']) . " pools\n";
    foreach ($page1['results'] as $index => $pool) {
        echo "  " . ($index + 1) . ". " . $pool['dex_name'] . " - Volume: $" .
             number_format($pool['volume_usd_24h'], 2) . "\n";
    }

    // Get second page with the cursor from the first
    $page2 = $client->pools->getNetworkPools('ethereum', [
        'limit' => 5,
        'cursor' => $page1['next_cursor'],
    ]);

    echo "\nPage 2: Found " . count($page2['results']) . " pools\n";
    foreach ($page2['results'] as $index => $pool) {
        echo "  " . ($index + 6) . ". " . $pool['dex_name'] . " - Volume: $" .
             number_format($pool['volume_usd_24h'], 2) . "\n";
    }

    echo "\n";

    // Example 2: Using the Paginator utility
    //
    // createPaginator takes the API object, the method name, and the method
    // parameters by name. It threads the cursor through for you.
    echo "2. Using the Paginator utility:\n";
    echo "-----------------------------\n";

    $paginator = $client->createPaginator(
        $client->tokens,
        'getTokenPools',
        [
            'networkId' => 'ethereum',
            'tokenAddress' => '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2', // WETH
            'limit' => 3,
        ]
    );

    // Get first page
    $page = $paginator->getNextPage();
    echo "Page 1: Found " . count($page['results']) . " pools\n";
    foreach ($page['results'] as $index => $pool) {
        echo "  " . ($index + 1) . ". " . $pool['dex_name'] . " - Volume: $" .
             number_format($pool['volume_usd_24h'], 2) . "\n";
    }

    // Check if there are more pages
    if ($paginator->hasNextPage()) {
        $page = $paginator->getNextPage();
        echo "\nPage 2: Found " . count($page['results']) . " pools\n";
        foreach ($page['results'] as $index => $pool) {
            echo "  " . ($index + 4) . ". " . $pool['dex_name'] . " - Volume: $" .
                 number_format($pool['volume_usd_24h'], 2) . "\n";
        }
    }

    echo "\n";

    // Example 3: Get all results at once
    //
    // DEX pools are offset paginated and identified by a DEX id, not a factory
    // address. The ids use underscores: uniswap_v3, not uniswap-v3.
    echo "3. Get all results at once (limited to 10):\n";
    echo "----------------------------------------\n";

    $paginator = $client->createPaginator(
        $client->dexes,
        'getDexPools',
        [
            'networkId' => 'ethereum',
            'dexId' => 'uniswap_v3',
            'limit' => 5,
        ]
    );

    // Get all results (with a maximum of 10 items/2 pages)
    $allPools = $paginator->getAllResults(2);

    echo "Found " . count($allPools) . " pools in total\n";
    foreach (array_slice($allPools, 0, 5) as $index => $pool) {
        echo "  " . ($index + 1) . ". " . $pool['dex_name'] . " - Volume: $" .
             number_format($pool['volume_usd'], 2) . "\n";
    }

    echo "  ... (and " . (count($allPools) - 5) . " more)\n\n";

    // Example 4: Using callbacks for processing
    echo "4. Using callbacks for processing each page:\n";
    echo "----------------------------------------\n";

    $paginator = $client->createPaginator(
        $client->pools,
        'getNetworkPools',
        [
            'networkId' => 'ethereum',
            'limit' => 5,
        ]
    );

    $totalVolume = 0;
    $totalTransactions = 0;

    $paginator->getAllResults(2, function ($page, $pageNum) use (&$totalVolume, &$totalTransactions) {
        echo "Processing page " . ($pageNum + 1) . "...\n";

        foreach ($page['results'] as $pool) {
            $totalVolume += $pool['volume_usd_24h'];
            $totalTransactions += $pool['transactions_24h'];
        }

        return true; // Continue processing
    });

    echo "Total volume across all pools: $" . number_format($totalVolume, 2) . "\n";
    echo "Total transactions across all pools: " . number_format($totalTransactions) . "\n";

} catch (DexPaprikaApiException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

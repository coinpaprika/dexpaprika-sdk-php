<?php

require __DIR__ . '/../vendor/autoload.php';

use DexPaprika\Client;
use DexPaprika\Exception\DexPaprikaApiException;

/**
 * This example demonstrates various pagination approaches
 * for working with large result sets
 */

$client = new Client();

try {
    echo "PAGINATION EXAMPLES\n";
    echo "==================\n\n";
    
    // Example 1: Manual pagination
    echo "1. Manual pagination:\n";
    echo "--------------------\n";
    
    // Get first page
    $page1 = $client->pools->getNetworkPools('ethereum', [
        'limit' => 5,
        'page' => 0,
    ]);
    
    echo "Page 1: Found " . count($page1['pools']) . " pools\n";
    foreach ($page1['pools'] as $index => $pool) {
        echo "  " . ($index + 1) . ". " . $pool['dex_name'] . " - Volume: $" . 
             number_format($pool['volume_usd'], 2) . "\n";
    }
    
    // Get second page
    $page2 = $client->pools->getNetworkPools('ethereum', [
        'limit' => 5,
        'page' => 1,
    ]);
    
    echo "\nPage 2: Found " . count($page2['pools']) . " pools\n";
    foreach ($page2['pools'] as $index => $pool) {
        echo "  " . ($index + 6) . ". " . $pool['dex_name'] . " - Volume: $" . 
             number_format($pool['volume_usd'], 2) . "\n";
    }
    
    echo "\n";
    
    // Example 2: Using the Paginator utility
    echo "2. Using the Paginator utility:\n";
    echo "-----------------------------\n";
    
    $paginator = $client->createPaginator($client->tokens->getTokenPools(
        'ethereum',
        '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2', // WETH
        ['limit' => 3]
    ));
    
    // Get first page
    $page = $paginator->getNextPage();
    echo "Page 1: Found " . count($page['pools']) . " pools\n";
    foreach ($page['pools'] as $index => $pool) {
        echo "  " . ($index + 1) . ". " . $pool['dex_name'] . " - Volume: $" . 
             number_format($pool['volume_usd'], 2) . "\n";
    }
    
    // Check if there are more pages
    if ($paginator->hasNextPage()) {
        $page = $paginator->getNextPage();
        echo "\nPage 2: Found " . count($page['pools']) . " pools\n";
        foreach ($page['pools'] as $index => $pool) {
            echo "  " . ($index + 4) . ". " . $pool['dex_name'] . " - Volume: $" . 
                 number_format($pool['volume_usd'], 2) . "\n";
        }
    }
    
    echo "\n";
    
    // Example 3: Get all results at once
    echo "3. Get all results at once (limited to 10):\n";
    echo "----------------------------------------\n";
    
    $paginator = $client->createPaginator(
        $client->pools,
        'getDexPools',
        [
            'network' => 'ethereum',
            'dex' => '0x1f98431c8ad98523631ae4a59f267346ea31f984', // Uniswap V3
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
        'getTopPools',
        ['limit' => 5]
    );
    
    $totalVolume = 0;
    $totalTransactions = 0;
    
    $paginator->getAllResults(2, function($page, $pageNum) use (&$totalVolume, &$totalTransactions) {
        echo "Processing page " . ($pageNum + 1) . "...\n";
        
        foreach ($page['pools'] as $pool) {
            $totalVolume += $pool['volume_usd'];
            $totalTransactions += $pool['transactions'];
        }
        
        return true; // Continue processing
    });
    
    echo "Total volume across all pools: $" . number_format($totalVolume, 2) . "\n";
    echo "Total transactions across all pools: " . number_format($totalTransactions) . "\n";
    
} catch (DexPaprikaApiException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 
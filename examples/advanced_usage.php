<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DexPaprika\Client;
use DexPaprika\Config;
use DexPaprika\Exception\NotFoundException;
use DexPaprika\Exception\DexPaprikaApiException;

// Advanced configuration
$config = new Config();
$config->setResponseFormat('object') // Get objects instead of arrays
       ->setTimeout(30)              // 30 second timeout
       ->setUserAgent('MyApp/1.0');  // Custom user agent

// Create client with configuration
$client = new Client($config);

try {
    echo "===== DexPaprika PHP SDK Advanced Demo =====\n\n";

    // 1. Response as objects
    echo "1. Using Object Response Format\n";
    $networks = $client->networks->getNetworks();
    echo "First network: {$networks[0]->display_name} ({$networks[0]->id})\n\n";

    // 2. Pagination example
    echo "2. Pagination Example\n";
    $page = 0;
    $limit = 3;
    $totalProcessed = 0;
    
    echo "Fetching top pools with pagination (3 per page):\n";
    
    do {
        $poolsResponse = $client->pools->getTopPools([
            'page' => $page,
            'limit' => $limit,
            'orderBy' => 'volume_usd',
            'sort' => 'desc'
        ]);
        
        $pools = $poolsResponse->pools;
        $hasMore = count($pools) > 0;
        
        echo "Page {$page} results:\n";
        foreach ($pools as $index => $pool) {
            $tokenSymbols = [];
            foreach ($pool->tokens as $token) {
                $tokenSymbols[] = $token->symbol;
            }
            $tokenPair = implode('/', $tokenSymbols);
            
            echo "  " . ($totalProcessed + $index + 1) . ". {$tokenPair} on {$pool->dex_name}: $" . 
                 number_format($pool->volume_usd, 2) . " volume\n";
        }
        
        $totalProcessed += count($pools);
        $page++;
        
        // Only process 2 pages for this example
        if ($page >= 2) {
            $hasMore = false;
        }
        
    } while ($hasMore);
    
    echo "Total pools processed: {$totalProcessed}\n\n";
    
    // 3. Getting historical OHLCV data
    echo "3. Historical OHLCV Data\n";
    
    // Find a popular ETH/USDC pool
    $ethUsdcPools = $client->search->search('ETH/USDC')->pools;
    
    if (count($ethUsdcPools) > 0) {
        $pool = $ethUsdcPools[0];
        echo "Found pool: {$pool->name} on {$pool->dex_name} ({$pool->chain})\n";
        
        // Get one week of daily OHLCV data
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-7 days'));
        
        echo "OHLCV data from {$startDate} to {$endDate}:\n";
        
        $ohlcvData = $client->pools->getPoolOHLCV(
            $pool->chain,
            $pool->address,
            [
                'start' => $startDate,
                'end' => $endDate,
                'interval' => '24h'
            ]
        );
        
        foreach ($ohlcvData as $candle) {
            $date = date('Y-m-d', $candle->timestamp);
            echo "  {$date}: Open: ${$candle->open}, Close: ${$candle->close}, " .
                 "Volume: $" . number_format($candle->volume_usd, 2) . "\n";
        }
    } else {
        echo "No ETH/USDC pools found for OHLCV example\n";
    }
    echo "\n";
    
    // 4. Get recent transactions for a pool
    echo "4. Recent Pool Transactions\n";
    
    // Use a popular pool from the search results
    if (isset($pool)) {
        echo "Recent transactions for {$pool->name}:\n";
        
        $transactions = $client->pools->getPoolTransactions(
            $pool->chain,
            $pool->address,
            ['limit' => 5]
        );
        
        foreach ($transactions->transactions as $index => $tx) {
            $time = date('Y-m-d H:i:s', $tx->timestamp);
            $action = $tx->action;
            $amountUsd = isset($tx->amount_usd) ? '$' . number_format($tx->amount_usd, 2) : 'N/A';
            
            echo "  " . ($index + 1) . ". {$time} - {$action} - {$amountUsd}\n";
        }
    } else {
        echo "No pool available for transaction example\n";
    }
    echo "\n";
    
    // 5. Error handling example
    echo "5. Error Handling Example\n";
    
    // Try to get details for a non-existent token
    echo "Attempting to fetch a non-existent token...\n";
    try {
        $invalidToken = $client->tokens->getTokenDetails(
            'ethereum', 
            '0x0000000000000000000000000000000000000000'
        );
    } catch (NotFoundException $e) {
        echo "Expected error caught: " . $e->getMessage() . "\n";
    }
    
    // Reset to array format to show difference
    $client->getConfig()->setResponseFormat('array');
    echo "\nSwitched back to array response format\n";
    
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
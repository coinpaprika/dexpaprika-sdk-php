<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DexPaprika\Client;
use DexPaprika\Exception\NotFoundException;
use DexPaprika\Exception\DexPaprikaApiException;

// Create the client
$client = new Client();

try {
    echo "===== DexPaprika PHP SDK Demo =====\n\n";

    // Get networks
    echo "Supported Networks:\n";
    $networks = $client->networks->getNetworks();
    foreach (array_slice($networks, 0, 5) as $network) {
        echo "- {$network['display_name']} ({$network['id']})\n";
    }
    echo count($networks) > 5 ? "- ... and " . (count($networks) - 5) . " more\n\n" : "\n\n";

    // Get statistics
    echo "DexPaprika Statistics:\n";
    $stats = $client->stats->getStats();
    echo "- {$stats['chains']} chains\n";
    echo "- {$stats['dexes']} DEXes\n";
    echo "- {$stats['pools']} pools\n";
    echo "- {$stats['tokens']} tokens\n\n";

    // Get top pools
    echo "Top 5 Pools by Volume:\n";
    $topPools = $client->pools->getTopPools(['limit' => 5]);
    foreach ($topPools['pools'] as $index => $pool) {
        $tokenPair = count($pool['tokens']) >= 2 
            ? "{$pool['tokens'][0]['symbol']}/{$pool['tokens'][1]['symbol']}" 
            : "Unknown Pair";
        
        $volume = isset($pool['volume_usd']) 
            ? "$" . number_format($pool['volume_usd'], 2) 
            : "N/A";
        
        echo ($index + 1) . ". {$tokenPair} on {$pool['dex_name']} ({$pool['chain']}): {$volume} 24h volume\n";
    }
    echo "\n";

    // Get DEXes on Ethereum
    echo "DEXes on Ethereum:\n";
    $ethDexes = $client->networks->getNetworkDexes('ethereum', ['limit' => 5]);
    foreach ($ethDexes['dexes'] as $index => $dex) {
        echo ($index + 1) . ". {$dex['name']} (ID: {$dex['id']})\n";
    }
    echo "\n";

    // Get pools on Ethereum Uniswap V3
    echo "Top 5 Uniswap V3 Pools on Ethereum:\n";
    $uniswapPools = $client->pools->getDexPools('ethereum', 'uniswap-v3', ['limit' => 5]);
    foreach ($uniswapPools['pools'] as $index => $pool) {
        $tokenPair = count($pool['tokens']) >= 2 
            ? "{$pool['tokens'][0]['symbol']}/{$pool['tokens'][1]['symbol']}" 
            : "Unknown Pair";
        
        $volume = isset($pool['volume_usd']) 
            ? "$" . number_format($pool['volume_usd'], 2) 
            : "N/A";
        
        echo ($index + 1) . ". {$tokenPair}: {$volume} 24h volume\n";
    }
    echo "\n";

    // Search for a token
    echo "Searching for 'ethereum':\n";
    $searchResults = $client->search->search('ethereum');
    echo "Found {$searchResults['summary']['total_tokens']} tokens and {$searchResults['summary']['total_pools']} pools\n\n";

    // Get token details for WETH
    echo "WETH Token Details:\n";
    $wethAddress = '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2';
    $weth = $client->tokens->getTokenDetails('ethereum', $wethAddress);
    echo "- Name: {$weth['name']} ({$weth['symbol']})\n";
    echo "- Price: $" . number_format($weth['price_usd'], 2) . "\n";
    echo "- 24h Volume: $" . number_format($weth['volume_usd_24h'], 2) . "\n";
    
    // Get pools for WETH-USDC pair
    echo "\nTop WETH-USDC Pools:\n";
    $usdcAddress = '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48';
    $wethUsdcPools = $client->tokens->getTokenPools('ethereum', $wethAddress, [
        'limit' => 3,
        'address' => $usdcAddress
    ]);
    
    foreach ($wethUsdcPools['pools'] as $index => $pool) {
        echo ($index + 1) . ". {$pool['dex_name']}: $" . number_format($pool['volume_usd'], 2) . " 24h volume\n";
    }

} catch (NotFoundException $e) {
    echo "Not found: " . $e->getMessage() . "\n";
} catch (DexPaprikaApiException $e) {
    echo "API Error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 
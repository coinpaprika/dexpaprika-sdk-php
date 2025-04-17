<?php

require __DIR__ . '/../vendor/autoload.php';

use DexPaprika\Client;
use DexPaprika\Exception\DexPaprikaApiException;

/**
 * This example demonstrates how to use the object transformation feature 
 * to convert API responses from arrays to objects
 */

// Create a client with transformation enabled
$client = new Client(null, null, true);

try {
    echo "Using object transformation to access API data with object syntax:\n\n";
    
    // Get token details with object transformation
    $wethAddress = '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2';
    $token = $client->tokens->getTokenDetails('ethereum', $wethAddress);
    
    // Access data using object syntax
    echo "Token details for {$token->token->name} ({$token->token->symbol}):\n";
    echo "- Chain: {$token->token->chain}\n";
    echo "- Decimals: {$token->token->decimals}\n";
    
    if (isset($token->token->summary->price_usd)) {
        echo "- Price: $" . number_format($token->token->summary->price_usd, 2) . "\n";
    }
    
    echo "\n";
    
    // Get top pools with object transformation
    $pools = $client->pools->getTopPools([
        'limit' => 3,
        'orderBy' => 'volume_usd',
        'sort' => 'desc'
    ]);
    
    echo "Top pools by volume (using object property access):\n";
    foreach ($pools->pools as $pool) {
        // Access token symbols using object syntax
        $token0Symbol = $pool->tokens[0]->symbol ?? 'Unknown';
        $token1Symbol = $pool->tokens[1]->symbol ?? 'Unknown';
        
        echo "- {$token0Symbol}/{$token1Symbol} on {$pool->dex_name} ({$pool->chain}): $" . 
             number_format($pool->volume_usd, 2) . " volume\n";
    }
    
    echo "\n";
    
    // Mix of array and object access
    $client->setTransformResponses(false); // Disable transformation at client level
    
    // Enable transformation just for this request
    $searchResults = $client->search->search('ethereum', ['asObject' => true]);
    
    echo "Search results for 'ethereum' (as object):\n";
    echo "- Found " . count($searchResults->tokens) . " tokens\n";
    echo "- Found " . count($searchResults->pools) . " pools\n";
    echo "- Found " . count($searchResults->dexes) . " DEXes\n";
    
    // Re-enable transformation for all requests
    $client->setTransformResponses(true);
    
} catch (DexPaprikaApiException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 
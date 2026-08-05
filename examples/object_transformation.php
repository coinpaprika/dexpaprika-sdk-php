<?php

require __DIR__ . '/../vendor/autoload.php';

use DexPaprika\Client;
use DexPaprika\Exception\DexPaprikaApiException;

/**
 * This example demonstrates the object transformation feature. Responses come
 * back as arrays by default. Pass ['asObject' => true] on a call and the SDK
 * hands you stdClass objects instead, so you can use property syntax.
 */

$client = new Client();

try {
    echo "Using object transformation to access API data with object syntax:\n\n";

    // Get token details as an object
    $wethAddress = '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2';
    $token = $client->tokens->getTokenDetails('ethereum', $wethAddress, ['asObject' => true]);

    // Access data using object syntax
    echo "Token details for {$token->name} ({$token->symbol}):\n";
    echo "- Chain: {$token->chain}\n";
    echo "- Decimals: {$token->decimals}\n";

    if (isset($token->summary->price_usd)) {
        echo "- Price: $" . number_format($token->summary->price_usd, 2) . "\n";
    }

    echo "\n";

    // Get pools on a DEX as objects. Rows from /networks/{network}/dexes/{dex}/pools
    // carry the full token records, so symbols are available here.
    $pools = $client->dexes->getDexPools('ethereum', 'uniswap_v3', [
        'limit' => 3,
        'asObject' => true,
    ]);

    echo "Top Uniswap V3 pools by volume (using object property access):\n";
    foreach ($pools->pools as $pool) {
        // Access token symbols using object syntax
        $token0Symbol = $pool->tokens[0]->symbol ?? 'Unknown';
        $token1Symbol = $pool->tokens[1]->symbol ?? 'Unknown';

        echo "- {$token0Symbol}/{$token1Symbol} on {$pool->dex_name} ({$pool->chain}): $" .
             number_format($pool->volume_usd, 2) . " volume\n";
    }

    echo "\n";

    // Transformation is per request, so you can mix the two styles freely.
    $searchResults = $client->search->search('ethereum', ['asObject' => true]);

    // Cast before counting: the transformer turns an empty JSON list into an
    // empty object, and a search can legitimately return no DEXes.
    echo "Search results for 'ethereum' (as object):\n";
    echo "- Found " . count((array) $searchResults->tokens) . " tokens\n";
    echo "- Found " . count((array) $searchResults->pools) . " pools\n";
    echo "- Found " . count((array) $searchResults->dexes) . " DEXes\n";

} catch (DexPaprikaApiException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

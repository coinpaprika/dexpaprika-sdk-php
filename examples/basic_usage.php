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
    echo "- {$stats['factories']} DEX factories\n";
    echo "- {$stats['pools']} pools\n";
    echo "- {$stats['tokens']} tokens\n\n";

    // Get top pools on a network. The global /pools endpoint was removed and
    // returns 410, so pools are network-scoped now. The response is cursor
    // paginated: rows arrive under 'results', not 'pools'.
    echo "Top 5 Ethereum Pools by 24h Volume:\n";
    $topPools = $client->pools->getNetworkPools('ethereum', ['limit' => 5]);
    foreach ($topPools['results'] as $index => $pool) {
        $volume = isset($pool['volume_usd_24h'])
            ? "$" . number_format($pool['volume_usd_24h'], 2)
            : "N/A";

        echo ($index + 1) . ". {$pool['id']} on {$pool['dex_name']} ({$pool['chain']}): {$volume} 24h volume\n";
    }
    echo "\n";

    // Get DEXes on Ethereum
    echo "DEXes on Ethereum:\n";
    $ethDexes = $client->dexes->getNetworkDexes('ethereum', ['limit' => 5]);
    foreach ($ethDexes['dexes'] as $index => $dex) {
        echo ($index + 1) . ". {$dex['dex_name']} (ID: {$dex['dex_id']})\n";
    }
    echo "\n";

    // Get pools on Ethereum Uniswap V3. getDexPools lives on the dexes API,
    // and DEX ids use underscores: uniswap_v3, not uniswap-v3. It runs on
    // /networks/{network}/pools/search with a dex_name filter, so rows come
    // back under 'results' and the 24h volume is 'volume_usd_24h'.
    echo "Top 5 Uniswap V3 Pools on Ethereum:\n";
    $uniswapPools = $client->dexes->getDexPools('ethereum', 'uniswap_v3', ['limit' => 5]);
    foreach ($uniswapPools['results'] as $index => $pool) {
        // Search rows reference tokens by id only, with no symbol.
        $tokenPair = count($pool['tokens']) >= 2
            ? substr($pool['tokens'][0]['id'], 0, 8) . '/' . substr($pool['tokens'][1]['id'], 0, 8)
            : "Unknown Pair";

        $volume = isset($pool['volume_usd_24h'])
            ? "$" . number_format($pool['volume_usd_24h'], 2)
            : "N/A";

        echo ($index + 1) . ". {$tokenPair}: {$volume} 24h volume\n";
    }
    echo "\n";

    // Search for a token
    echo "Searching for 'ethereum':\n";
    $searchResults = $client->search->search('ethereum');
    echo "Found " . count($searchResults['tokens']) . " tokens and " . count($searchResults['pools']) . " pools\n\n";

    // Get token details for WETH. Price and volume live under 'summary'.
    echo "WETH Token Details:\n";
    $wethAddress = '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2';
    $weth = $client->tokens->getTokenDetails('ethereum', $wethAddress);
    echo "- Name: {$weth['name']} ({$weth['symbol']})\n";
    echo "- Price: $" . number_format($weth['summary']['price_usd'], 2) . "\n";
    echo "- 24h Volume: $" . number_format($weth['summary']['24h']['volume_usd'], 2) . "\n";
    
    // Get top pools containing WETH (network-scoped /pools/search filter,
    // cursor-paginated: rows under 'results'). The old pair filter ('address')
    // was removed with the /tokens/{address}/pools endpoint, so match pairs
    // client-side instead.
    echo "\nTop WETH-USDC Pools:\n";
    $usdcAddress = '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48';
    $wethPools = $client->tokens->getTokenPools('ethereum', $wethAddress, [
        'limit' => 10,
    ]);

    $wethUsdcPools = array_values(array_filter($wethPools['results'], function ($pool) use ($usdcAddress) {
        return in_array($usdcAddress, array_column($pool['tokens'] ?? [], 'id'), true);
    }));

    foreach (array_slice($wethUsdcPools, 0, 3) as $index => $pool) {
        echo ($index + 1) . ". {$pool['dex_name']}: $" . number_format($pool['volume_usd_24h'], 2) . " 24h volume\n";
    }

} catch (NotFoundException $e) {
    echo "Not found: " . $e->getMessage() . "\n";
} catch (DexPaprikaApiException $e) {
    echo "API Error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 
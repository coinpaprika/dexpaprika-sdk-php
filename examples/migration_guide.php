<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DexPaprika\Client;
use DexPaprika\Exception\DeprecationException;

/**
 * DexPaprika SDK v1.3.0 Migration Guide
 * 
 * This example demonstrates how to migrate from the deprecated global pools endpoint
 * to the new network-specific endpoints.
 */

// Initialize the client
$client = new Client();

echo "=== DexPaprika SDK v1.3.0 Migration Guide ===\n\n";

// === DEPRECATED APPROACH (Will throw DeprecationException) ===
echo "1. OLD APPROACH (❌ DEPRECATED - Will throw exception):\n";
echo "   \$pools = \$client->pools->getTopPools();\n\n";

try {
    $pools = $client->pools->getTopPools(['limit' => 10]);
    echo "   Result: This should not execute\n";
} catch (DeprecationException $e) {
    echo "   ✅ Caught DeprecationException as expected\n";
    echo "   📋 Message: {$e->getMessage()}\n\n";
    
    $errorData = $e->getErrorData();
    if (isset($errorData['migration_examples'])) {
        echo "   📖 Migration Examples:\n";
        foreach ($errorData['migration_examples'] as $example) {
            echo "      • {$example}\n";
        }
        echo "\n";
    }
    
    if (isset($errorData['supported_networks'])) {
        echo "   🌐 Supported Networks: " . implode(', ', $errorData['supported_networks']) . "\n\n";
    }
}

// === NEW NETWORK-SPECIFIC APPROACH ===
echo "2. NEW APPROACH (✅ RECOMMENDED):\n\n";

// Get pools from a specific network
echo "   2a. Single Network:\n";
echo "       \$pools = \$client->pools->getNetworkPools('ethereum', ['limit' => 10]);\n";
try {
    $ethereumPools = $client->pools->getNetworkPools('ethereum', ['limit' => 5]);
    echo "       ✅ Successfully retrieved " . count($ethereumPools['pools'] ?? []) . " Ethereum pools\n\n";
} catch (Exception $e) {
    echo "       ❌ Error: {$e->getMessage()}\n\n";
}

// Get pools from multiple networks
echo "   2b. Multiple Networks:\n";
echo "       \$networks = ['ethereum', 'solana', 'polygon'];\n";
echo "       foreach (\$networks as \$network) { ... }\n";

$supportedNetworks = ['ethereum', 'solana', 'polygon'];
$allNetworkPools = [];

foreach ($supportedNetworks as $network) {
    try {
        $networkPools = $client->pools->getNetworkPools($network, ['limit' => 3]);
        $poolCount = count($networkPools['pools'] ?? []);
        $allNetworkPools[$network] = $networkPools;
        echo "       ✅ {$network}: Retrieved {$poolCount} pools\n";
    } catch (Exception $e) {
        echo "       ❌ {$network}: Error - {$e->getMessage()}\n";
    }
}

echo "\n";

// === NEW TOKEN POOLS WITH REORDER PARAMETER ===
echo "3. NEW TOKEN POOLS FEATURES:\n\n";

echo "   3a. Using the new 'reorder' parameter:\n";
echo "       \$pools = \$client->tokens->getTokenPools('ethereum', 'token_address', ['reorder' => true]);\n";

try {
    // Example with a common token address (USDC on Ethereum)
    $tokenPools = $client->tokens->getTokenPools(
        'ethereum', 
        '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48', // USDC
        [
            'reorder' => true,
            'limit' => 3
        ]
    );
    $poolCount = count($tokenPools['pools'] ?? []);
    echo "       ✅ Retrieved {$poolCount} USDC pools with reordering\n";
} catch (Exception $e) {
    echo "       ❌ Error: {$e->getMessage()}\n";
}

echo "\n";

// === PARAMETER VALIDATION ===
echo "4. ENHANCED PARAMETER VALIDATION:\n\n";

echo "   4a. Testing limit validation (max 100):\n";
try {
    $client->pools->getNetworkPools('ethereum', ['limit' => 150]); // Invalid: > 100
} catch (\DexPaprika\Exception\ValidationException $e) {
    echo "       ✅ Caught ValidationException: {$e->getMessage()}\n";
}

echo "\n   4b. Testing network validation:\n";
try {
    $client->pools->getNetworkPools('', ['limit' => 10]); // Invalid: empty network
} catch (\DexPaprika\Exception\ValidationException $e) {
    echo "       ✅ Caught ValidationException: {$e->getMessage()}\n";
}

echo "\n";

// === SUMMARY ===
echo "=== MIGRATION SUMMARY ===\n";
echo "✅ Replace all getTopPools() calls with getNetworkPools(network, options)\n";
echo "✅ Use 'reorder' parameter in token pools for better metrics alignment\n";
echo "✅ Ensure limit parameters are <= 100\n";
echo "✅ Handle DeprecationException for graceful error messages\n";
echo "✅ Validate network IDs before making API calls\n\n";

echo "📚 For more information:\n";
echo "   • API Documentation: https://docs.dexpaprika.com/\n";
echo "   • Changelog: https://docs.dexpaprika.com/changelog/changelog\n";
echo "   • SDK Repository: https://github.com/coinpaprika/dexpaprika-sdk-php\n"; 
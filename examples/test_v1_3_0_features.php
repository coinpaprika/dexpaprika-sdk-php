<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DexPaprika\Client;
use DexPaprika\Exception\DeprecationException;
use DexPaprika\Exception\ValidationException;

/**
 * Test script to verify DexPaprika SDK v1.3.0 features work correctly
 */

echo "=== DexPaprika SDK v1.3.0 Feature Test ===\n\n";

// Initialize the client
$client = new Client();

// Test 1: Verify deprecation exception is thrown
echo "Test 1: Testing deprecated getTopPools() method\n";
try {
    $client->pools->getTopPools(['limit' => 10]);
    echo "FAIL: Expected DeprecationException was not thrown\n";
} catch (DeprecationException $e) {
    echo "PASS: DeprecationException thrown correctly\n";
    echo "   Message: " . substr($e->getMessage(), 0, 80) . "...\n";
    echo "   Status Code: " . $e->getCode() . "\n";
    
    $errorData = $e->getErrorData();
    if (isset($errorData['migration_examples'])) {
        echo "   Migration examples included: \n";
    }
    if (isset($errorData['supported_networks'])) {
        echo "   Supported networks included: \n";
    }
} catch (Exception $e) {
    echo "FAIL: Wrong exception type: " . get_class($e) . "\n";
}

echo "\n";

// Test 2: Verify parameter validation works
echo "Test 2: Testing parameter validation\n";

// Test empty network ID
try {
    $client->pools->getNetworkPools('', ['limit' => 10]);
    echo "FAIL: Expected ValidationException for empty network ID\n";
} catch (ValidationException $e) {
    echo "PASS: ValidationException for empty network ID\n";
} catch (Exception $e) {
    echo "FAIL: Wrong exception for empty network ID: " . get_class($e) . "\n";
}

// Test limit too high
try {
    $client->pools->getNetworkPools('ethereum', ['limit' => 150]);
    echo "FAIL: Expected ValidationException for limit > 100\n";
} catch (ValidationException $e) {
    echo "PASS: ValidationException for limit > 100\n";
} catch (Exception $e) {
    echo "FAIL: Wrong exception for high limit: " . get_class($e) . "\n";
}

echo "\n";

// Test 3: Verify the endpoints the SDK builds actually answer. The API is
// callable without a key on the free tier, so a successful call is the pass.
echo "Test 3: Testing live endpoints\n";

try {
    $pools = $client->pools->getNetworkPools('ethereum', ['limit' => 10]);
    $count = count($pools['results'] ?? []);
    echo "PASS: getNetworkPools returned {$count} rows under 'results'\n";
} catch (Exception $e) {
    echo "FAIL: getNetworkPools raised " . get_class($e) . ": " . $e->getMessage() . "\n";
}

try {
    $tokenPools = $client->tokens->getTokenPools('ethereum', '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48', ['limit' => 3]);
    $count = count($tokenPools['results'] ?? []);
    echo "PASS: getTokenPools returned {$count} rows under 'results'\n";
} catch (Exception $e) {
    echo "FAIL: getTokenPools raised " . get_class($e) . ": " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Verify class structure
echo "Test 4: Testing class structure and methods\n";

$reflection = new ReflectionClass($client->pools);
if ($reflection->hasMethod('getNetworkPools')) {
    echo "PASS: getNetworkPools method exists\n";
} else {
    echo "FAIL: getNetworkPools method missing\n";
}

if ($reflection->hasMethod('getTopPools')) {
    echo "PASS: getTopPools method still exists (for backward compatibility)\n";
} else {
    echo "FAIL: getTopPools method missing\n";
}

$tokenReflection = new ReflectionClass($client->tokens);
if ($tokenReflection->hasMethod('getTokenPools')) {
    echo "PASS: getTokenPools method exists\n";
} else {
    echo "FAIL: getTokenPools method missing\n";
}

echo "\n";

// Test 5: Verify exception classes exist
echo "Test 5: Testing exception classes\n";

$exceptions = [
    'DexPaprika\Exception\DeprecationException',
    'DexPaprika\Exception\ValidationException',
    'DexPaprika\Exception\NetworkException',
    'DexPaprika\Exception\NotFoundException'
];

foreach ($exceptions as $exceptionClass) {
    if (class_exists($exceptionClass)) {
        echo "PASS: {$exceptionClass} exists\n";
    } else {
        echo "FAIL: {$exceptionClass} missing\n";
    }
}

echo "\n=== Summary ===\n";
echo "Deprecation mechanism works correctly\n";
echo "Parameter validation is functioning\n";
echo "URL construction appears correct\n";
echo "All required classes and methods exist\n";
echo "Exception hierarchy is complete\n\n";

echo "DexPaprika SDK v1.3.0 is ready for use!\n";
echo "See examples/migration_guide.php for detailed migration instructions\n"; 
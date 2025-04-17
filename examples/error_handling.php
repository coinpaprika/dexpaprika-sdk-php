<?php

require __DIR__ . '/../vendor/autoload.php';

use DexPaprika\Client;
use DexPaprika\Exception\NotFoundException;
use DexPaprika\Exception\DexPaprikaApiException;

/**
 * This example demonstrates various error handling approaches
 * when working with the DexPaprika SDK
 */

$client = new Client();

echo "ERROR HANDLING EXAMPLES\n";
echo "======================\n\n";

// Example 1: Basic try-catch for general API errors
echo "1. Basic error handling:\n";
echo "----------------------\n";

try {
    // Try to get data from a non-existent network
    $pools = $client->pools->getNetworkPools('non_existent_network', [
        'limit' => 5,
    ]);
    
    // This line won't be reached if an error occurs
    echo "Found " . count($pools['pools']) . " pools\n";
} catch (DexPaprikaApiException $e) {
    echo "Error occurred: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
    
    // Get additional error details if available
    if ($e->getResponse()) {
        echo "HTTP status code: " . $e->getResponse()->getStatusCode() . "\n";
    }
}

echo "\n";

// Example 2: Handling specific exceptions
echo "2. Handling specific exceptions:\n";
echo "-----------------------------\n";

try {
    // Try to find a token that doesn't exist
    $token = $client->tokens->findToken('ethereum', '0x1234567890123456789012345678901234567890');
    
    // This line won't be reached if the token is not found
    echo "Found token: " . $token['name'] . "\n";
} catch (NotFoundException $e) {
    // Handle the specific case where a token is not found
    echo "Token not found: " . $e->getMessage() . "\n";
} catch (DexPaprikaApiException $e) {
    // Handle other API errors
    echo "API Error: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    // Handle any other unexpected errors
    echo "Unexpected error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 3: Gracefully handling rate limits
echo "3. Handling rate limits with retry logic:\n";
echo "-------------------------------------\n";

$maxRetries = 3;
$retryDelay = 1; // second

function fetchWithRetry($client, $maxRetries, $retryDelay) {
    $retries = 0;
    
    while ($retries <= $maxRetries) {
        try {
            // Make the API request
            $result = $client->pools->getTopPools(['limit' => 5]);
            
            // If successful, return the result and break the loop
            echo "Request succeeded after " . $retries . " retries\n";
            return $result;
        } catch (DexPaprikaApiException $e) {
            // Check if it's a rate limit error (usually 429 Too Many Requests)
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 429) {
                $retries++;
                
                if ($retries <= $maxRetries) {
                    echo "Rate limit hit, retrying in {$retryDelay} second(s)... (Attempt {$retries}/{$maxRetries})\n";
                    sleep($retryDelay);
                    continue;
                }
            }
            
            // If it's not a rate limit error or we've exceeded max retries, re-throw
            throw $e;
        }
    }
    
    throw new \RuntimeException("Max retries exceeded");
}

try {
    // Demonstrate retry logic (won't actually hit rate limits in this example)
    $result = fetchWithRetry($client, $maxRetries, $retryDelay);
    echo "Got " . count($result['pools']) . " pools\n";
} catch (\Exception $e) {
    echo "Error after retries: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 4: Handling connection/network errors
echo "4. Handling connection errors:\n";
echo "---------------------------\n";

try {
    // Create a client with a very short timeout to simulate network issues
    $impatientClient = new Client(null, new \GuzzleHttp\Client([
        'timeout' => 0.001, // Extremely short timeout to force a timeout error
    ]));
    
    // This will likely time out
    $networks = $impatientClient->networks->getNetworks();
    
    echo "Found " . count($networks) . " networks\n";
} catch (\GuzzleHttp\Exception\ConnectException $e) {
    echo "Connection error: " . $e->getMessage() . "\n";
    echo "Tip: Check your internet connection or API endpoint accessibility\n";
} catch (DexPaprikaApiException $e) {
    echo "API Error: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "Other error: " . $e->getMessage() . "\n";
} 
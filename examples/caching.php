<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DexPaprika\Client;
use DexPaprika\Cache\FilesystemCache;
use DexPaprika\Exception\DexPaprikaApiException;

try {
    // Create a new client with caching enabled using the default filesystem cache
    $client = new Client();
    $client->setupCache();
    
    // First API call will be fetched from the API and cached
    echo "First call to get networks (from API):\n";
    $startTime = microtime(true);
    $networks = $client->networks->getNetworks();
    echo "Request took: " . (microtime(true) - $startTime) . " seconds\n";
    echo "Found " . count($networks) . " networks\n\n";
    
    // Second API call will use the cached response
    echo "Second call to get networks (from cache):\n";
    $startTime = microtime(true);
    $networks = $client->networks->getNetworks();
    echo "Request took: " . (microtime(true) - $startTime) . " seconds\n";
    echo "Found " . count($networks) . " networks\n\n";
    
    // Custom cache TTL example
    echo "Using custom cache TTL (5 minutes):\n";
    $client = new Client();
    $client->setupCache(null, 300); // 5 minutes TTL
    
    // Custom cache directory example
    echo "Using custom cache directory:\n";
    $customCache = new FilesystemCache(__DIR__ . '/cache');
    $client = new Client();
    $client->setupCache($customCache);
    
    // Disabling cache example
    echo "Disabling cache after it's been set up:\n";
    $client->getConfig()->setCacheEnabled(false);
    $startTime = microtime(true);
    $networks = $client->networks->getNetworks(); // This will hit the API again
    echo "Request took: " . (microtime(true) - $startTime) . " seconds\n";
    
    // Re-enabling cache
    echo "Re-enabling cache:\n";
    $client->getConfig()->setCacheEnabled(true);
    $startTime = microtime(true);
    $networks = $client->networks->getNetworks(); // This will use the cache
    echo "Request took: " . (microtime(true) - $startTime) . " seconds\n";
    
} catch (DexPaprikaApiException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    
    if ($e->getErrorData()) {
        echo "Error data: " . json_encode($e->getErrorData(), JSON_PRETTY_PRINT) . "\n";
    }
} 
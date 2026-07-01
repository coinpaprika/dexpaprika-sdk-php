# DexPaprika PHP SDK

A PHP SDK for interacting with the DexPaprika API, providing access to cryptocurrency DEX data, token information, liquidity pools, and market statistics.

## ⚠️ Breaking Changes in v1.3.0

**IMPORTANT**: The global `/pools` endpoint has been deprecated in DexPaprika API v1.3.0. All pool operations now require a network parameter.

### Migration Required
```php
// ❌ OLD - No longer works
$pools = $client->pools->getTopPools(['limit' => 10]);

// ✅ NEW - Network-specific approach
$ethereumPools = $client->pools->getNetworkPools('ethereum', ['limit' => 10]);
$solanaPools = $client->pools->getNetworkPools('solana', ['limit' => 10]);
```

**See the [Migration Guide](examples/migration_guide.php) and [CHANGELOG](CHANGELOG.md) for complete details.**

## Unified search endpoints (v1.2.0)

`getNetworkPools()`, `filterPools()`, `getTopTokens()` and `filterTokens()` now call the
unified `pools/search` and `tokens/search` endpoints (the previous list/filter/top
endpoints return `410 Gone`). The public method signatures are unchanged: legacy sort
values (for example `volume_usd`, `fdv`) and legacy filter parameter names (for example
`volume24hMin`) are mapped to the canonical search names automatically.

These endpoints are cursor-paginated, so the response shape is:

```php
$page = $client->pools->getNetworkPools('ethereum', ['limit' => 20]);
$page['results'];        // array of items
$page['has_next_page'];  // bool
$page['next_cursor'];    // string|null

// Fetch the next page with the cursor
$next = $client->pools->getNetworkPools('ethereum', [
    'limit' => 20,
    'cursor' => $page['next_cursor'],
]);
```

The `page` option is still accepted for backward compatibility but is ignored.

## Features

- Simple and intuitive PHP interface to all DexPaprika API endpoints
- Access data from 33+ blockchain networks
- Query information about DEXes, liquidity pools, and tokens
- Get detailed price information, trading volume, and transactions
- **Filter pools and tokens** by volume, liquidity, FDV, transactions, and creation date
- **Get top tokens** on any network ranked by volume or other metrics
- **Batch price lookups** for up to 10 tokens in a single request
- Search across the entire DexPaprika ecosystem
- Automatic retry with exponential backoff
- Response caching with PSR-6 compatible interface
- Parameter validation with clear error messages
- Comprehensive documentation

## Requirements

- PHP 7.4 or higher
- [Composer](https://getcomposer.org/)
- `ext-json`

## Installation

Install via Composer:

```bash
composer require your-vendor/dexpaprika-sdk-php
```

## Basic Usage

```php
<?php
require_once 'vendor/autoload.php';

use DexPaprika\Client;
use DexPaprika\Exception\DexPaprikaApiException;

try {
    // Create client
    $client = new Client();
    
    // Get list of supported networks
    $networks = $client->networks->getNetworks();
    
    // Get global statistics
    $stats = $client->utils->getStats();
    
    // Get top pools by network (NEW in v1.3.0)
    $ethereumPools = $client->pools->getNetworkPools('ethereum', ['limit' => 10]);
    $solanaPools = $client->pools->getNetworkPools('solana', ['limit' => 10]);
    
    // Search for tokens
    $searchResults = $client->search->search('bitcoin');
    
    // Get token details
    $token = $client->tokens->getTokenDetails('ethereum', '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2');
    
} catch (DexPaprikaApiException $e) {
    echo "API Error: " . $e->getMessage();
}
```

## Advanced Configuration

```php
<?php
use DexPaprika\Client;
use DexPaprika\Config;

// Create configuration
$config = new Config();
$config->setResponseFormat('object') // Get responses as objects instead of arrays
       ->setTimeout(30)              // Set request timeout in seconds
       ->setUserAgent('MyApp/1.0')   // Set custom user agent
       ->setMaxRetries(3)            // Set maximum retry attempts
       ->setRetryDelays([100, 500, 1000]); // Set retry delays in milliseconds

// Create client with configuration
$client = new Client($config);
```

## Caching Responses

The SDK provides built-in caching for API responses:

```php
<?php
use DexPaprika\Client;
use DexPaprika\Cache\FilesystemCache;

// Enable caching with default settings (filesystem cache, 1 hour TTL)
$client = new Client();
$client->setupCache();

// Custom cache TTL (5 minutes)
$client->setupCache(null, 300);

// Custom cache directory
$customCache = new FilesystemCache('/path/to/cache');
$client->setupCache($customCache);

// Disable caching
$client->getConfig()->setCacheEnabled(false);

// Enable caching
$client->getConfig()->setCacheEnabled(true);

// Create a client with caching enabled from the beginning
$config = new Config();
$cache = new FilesystemCache();
$config->setCache($cache)->setCacheEnabled(true);
$client = new Client(null, null, false, $config);
```

## Automatic Retry

The SDK automatically retries failed requests with exponential backoff:

```php
<?php
use DexPaprika\Client;
use DexPaprika\Config;

// Configure retry behavior
$config = new Config();
$config->setMaxRetries(5); // Maximum number of retry attempts
$config->setRetryDelays([100, 500, 1000, 2500, 5000]); // Delay in milliseconds for each attempt

$client = new Client(null, null, false, $config);
```

Only certain types of failures will be retried:
- Network connectivity issues
- Server errors (5xx status codes)
- Rate limiting (429 status codes)

## API Components

This SDK provides the following API modules:

### Networks

```php
// Get all supported networks
$networks = $client->networks->getNetworks();
```

### Statistics

```php
// Get global DEX statistics
$stats = $client->utils->getStats();
```

### DEXes

```php
// Get all DEXes on a specific network
$dexes = $client->dexes->getNetworkDexes('ethereum', ['limit' => 10]);
```

### Pools

```php
// Get pools on a specific network (UPDATED in v1.3.0)
$ethPools = $client->pools->getNetworkPools('ethereum', ['limit' => 20]);
$solanaPools = $client->pools->getNetworkPools('solana', ['limit' => 20]);

// Get pools on a specific DEX
$uniswapPools = $client->dexes->getDexPools('ethereum', 'uniswap_v3', ['limit' => 15]);

// Get detailed information about a pool
$poolDetails = $client->pools->getPoolDetails('ethereum', '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640');

// Get historical OHLCV data for a pool
$ohlcvData = $client->pools->getPoolOHLCV('ethereum', '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640', 
    '2023-01-01', [
        'end' => '2023-01-07',
        'interval' => '24h'
    ]
);

// Get transactions for a pool
$transactions = $client->pools->getPoolTransactions('ethereum', '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640', ['limit' => 20]);
```

### Tokens

```php
// Get detailed information about a token
$tokenDetails = $client->tokens->getTokenDetails('ethereum', '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2');

// Find token by name or address
$token = $client->tokens->findToken('ethereum', 'WETH');

// Get pools containing a specific token (UPDATED in v1.3.0)
$tokenPools = $client->tokens->getTokenPools('ethereum', '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2', [
    'limit' => 20,
    'reorder' => true  // NEW: Reorder pools so the token becomes the primary token for metrics
]);
```

### Pool Filtering

```php
// Find high-volume pools on Ethereum
$filtered = $client->pools->filterPools('ethereum', [
    'volume24hMin' => 100000,
    'txns24hMin' => 50,
    'sortBy' => 'volume_24h',
    'sortDir' => 'desc',
    'limit' => 10,
]);
echo "Found " . count($filtered['results']) . " pools matching criteria\n";
```

### Top Tokens & Token Filtering

```php
// Get top tokens by volume
$topTokens = $client->tokens->getTopTokens('ethereum', [
    'orderBy' => 'volume_24h',
    'limit' => 10,
]);

// Filter tokens by criteria
$filtered = $client->tokens->filterTokens('ethereum', [
    'volume24hMin' => 100000,
    'fdvMin' => 1000000,
    'limit' => 10,
]);
```

### Batch Token Prices

```php
// Get prices for multiple tokens in one request (max 10)
$prices = $client->tokens->getMultiPrices('ethereum', [
    '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2', // WETH
    '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48', // USDC
]);
foreach ($prices as $p) {
    echo "{$p['id']}: \${$p['price_usd']}\n";
}
```

### Search

```php
// Search for tokens, pools, and DEXes
$searchResults = $client->search->search('bitcoin');
```

## Response Formats

By default, the SDK returns responses as PHP arrays. You can change this to objects:

```php
// When initializing the client
$config = new Config();
$config->setResponseFormat('object');
$client = new Client($config);

// After initialization
$client->getConfig()->setResponseFormat('object');

// Get results as objects
$networks = $client->networks->getNetworks();
echo $networks[0]->display_name;
```

## Exception Handling

The SDK throws different types of exceptions:

```php
use DexPaprika\Exception\DexPaprikaApiException;
use DexPaprika\Exception\NotFoundException;
use DexPaprika\Exception\DeprecationException;
use DexPaprika\Exception\ValidationException;
use DexPaprika\Exception\RateLimitException;
use DexPaprika\Exception\ServerException;
use DexPaprika\Exception\NetworkException;

try {
    // This will throw DeprecationException in v1.3.0+
    $pools = $client->pools->getTopPools();
} catch (DeprecationException $e) {
    // Handle deprecated endpoint usage
    echo "Deprecated endpoint: " . $e->getMessage();
    
    // Get migration guidance from error data
    $errorData = $e->getErrorData();
    if (isset($errorData['migration_examples'])) {
        echo "Migration examples:\n";
        foreach ($errorData['migration_examples'] as $example) {
            echo "  {$example}\n";
        }
    }
} catch (ValidationException $e) {
    // Handle parameter validation errors
    echo "Invalid parameters: " . $e->getMessage();
} catch (NotFoundException $e) {
    // Handle not found error
    echo "Resource not found: " . $e->getMessage();
} catch (RateLimitException $e) {
    // Handle rate limiting
    echo "Rate limit exceeded. Try again later.";
} catch (ServerException $e) {
    // Handle server errors
    echo "Server error: " . $e->getMessage();
} catch (NetworkException $e) {
    // Handle network connectivity issues
    echo "Network error: " . $e->getMessage();
} catch (DexPaprikaApiException $e) {
    // Handle API errors
    echo "API Error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")";
    
    // Get raw error response
    $errorData = $e->getErrorData();
} catch (Exception $e) {
    // Handle general errors
    echo "Error: " . $e->getMessage();
}
```

## Examples

See the `examples` directory for more comprehensive usage examples:

- `basic_usage.php` - Simple examples of the core functionality
- `advanced_usage.php` - Advanced usage including pagination, error handling, and more

## Testing

Run tests with PHPUnit:

```bash
# Run unit tests only
./vendor/bin/phpunit --testsuite Unit

# Run all tests including integration (requires API access)
./vendor/bin/phpunit
```

## License

This project is licensed under the MIT License. See the [LICENSE](./LICENSE) file for details.

## Contributing

Contributions, issues, and feature requests are welcome! Feel free to check the [issues page](https://github.com/coinpaprika/dexpaprika-sdk-php/issues) or submit a pull request.

## Versioning

This project adheres to [Semantic Versioning](https://semver.org/). For the list of available versions, see the [tags on this repository](https://github.com/coinpaprika/dexpaprika-sdk-php/tags).

## Support

For support, please open an issue on the GitHub repository or contact the DexPaprika team.

## Resources

- [Official Documentation](https://docs.dexpaprika.com) - Comprehensive API reference
- [DexPaprika Website](https://dexpaprika.com) - Main product website
- [CoinPaprika](https://coinpaprika.com) - Related cryptocurrency data platform

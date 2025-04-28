# DexPaprika PHP SDK

A PHP SDK for interacting with the DexPaprika API, providing access to cryptocurrency DEX data, token information, liquidity pools, and market statistics.

## Features

- Simple and intuitive PHP interface to all DexPaprika API endpoints
- Access data from multiple blockchain networks
- Query information about DEXes, liquidity pools, and tokens
- Get detailed price information, trading volume, and transactions
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
    $stats = $client->stats->getStats();
    
    // Get top pools by volume
    $topPools = $client->pools->getTopPools(['limit' => 10]);
    
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
$stats = $client->stats->getStats();
```

### DEXes

```php
// Get all DEXes on a specific network
$dexes = $client->dexes->getNetworkDexes('ethereum', ['limit' => 10]);
```

### Pools

```php
// Get top pools across all networks
$topPools = $client->pools->getTopPools([
    'limit' => 10,
    'orderBy' => 'volume_usd',
    'sort' => 'desc'
]);

// Get pools on a specific network
$ethPools = $client->pools->getNetworkPools('ethereum', ['limit' => 20]);

// Get pools on a specific DEX
$uniswapPools = $client->pools->getDexPools('ethereum', 'uniswap_v3', ['limit' => 15]);

// Get detailed information about a pool
$poolDetails = $client->pools->getPoolDetails('ethereum', '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640');

// Get historical OHLCV data for a pool
$ohlcvData = $client->pools->getPoolOHLCV('ethereum', '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640', [
    'start' => '2023-01-01',
    'end' => '2023-01-07',
    'interval' => '24h'
]);

// Get transactions for a pool
$transactions = $client->pools->getPoolTransactions('ethereum', '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640', ['limit' => 20]);
```

### Tokens

```php
// Get detailed information about a token
$tokenDetails = $client->tokens->getTokenDetails('ethereum', '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2');

// Find token by name or address
$token = $client->tokens->findToken('ethereum', 'WETH');

// Get pools containing a specific token
$tokenPools = $client->tokens->getTokenPools('ethereum', '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2', ['limit' => 20]);
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
use DexPaprika\Exception\RateLimitException;
use DexPaprika\Exception\ServerException;
use DexPaprika\Exception\NetworkException;

try {
    $token = $client->tokens->getTokenDetails('ethereum', '0x0000000000000000000000000000000000000000');
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

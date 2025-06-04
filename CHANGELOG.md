# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2025-01-27

### ⚠️ BREAKING CHANGES
- **DEPRECATED ENDPOINT**: Global `/pools` endpoint now returns `410 Gone`
- **REQUIRED MIGRATION**: All pool operations must now use network-specific endpoints
- **PARAMETER LIMITS**: Maximum limit reduced from 500 to 100 for most endpoints

### 🚨 Migration Required
- **Before**: `$api->pools->getTopPools($options)`
- **After**: `$api->pools->getNetworkPools('ethereum', $options)`

### Added
- New `DeprecationException` class for handling 410 Gone responses
- Enhanced parameter validation with clear error messages
- Network ID validation for all pool operations
- Pool address validation for pool-specific operations
- `reorder` parameter support in token pools endpoints
- Comprehensive migration guidance in exception messages

### Changed
- `PoolsApi::getTopPools()` now throws `DeprecationException` with migration guidance
- `PoolsApi::listTopPools()` now throws `DeprecationException` with migration guidance
- Maximum `limit` parameter reduced from 500 to 100 across all endpoints
- Parameter names in requests changed from `orderBy` to `order_by` to match API spec
- `TokensApi::getTokenPools()` endpoint URL updated to `/networks/{network}/tokens/{token}/pools`
- Enhanced error messages for parameter validation

### Fixed
- URL paths now correctly match the DexPaprika API v1.3.0 specification
- Parameter validation now prevents invalid limit values
- Consistent parameter naming across all API methods

### Migration Guide

#### Updating Pool Operations
```php
// ❌ OLD - Will throw DeprecationException
$pools = $client->pools->getTopPools(['limit' => 20]);

// ✅ NEW - Network-specific approach
$ethereumPools = $client->pools->getNetworkPools('ethereum', ['limit' => 20]);
$solanaPools = $client->pools->getNetworkPools('solana', ['limit' => 20]);

// ✅ NEW - To get pools from multiple networks
$allPools = [];
$networks = ['ethereum', 'solana', 'fantom', 'polygon'];
foreach ($networks as $network) {
    $networkPools = $client->pools->getNetworkPools($network, ['limit' => 20]);
    $allPools[$network] = $networkPools;
}
```

#### Using the New Reorder Parameter
```php
// Get token pools with reordering
$pools = $client->tokens->getTokenPools(
    'ethereum', 
    '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
    [
        'reorder' => true,  // Token becomes primary for all metrics
        'limit' => 50
    ]
);
```

#### Handling DeprecationException
```php
try {
    $pools = $client->pools->getTopPools();
} catch (\DexPaprika\Exception\DeprecationException $e) {
    // Exception includes migration guidance
    echo $e->getMessage(); // Shows migration examples
    $errorData = $e->getErrorData();
    print_r($errorData['migration_examples']); // Array of before/after examples
    print_r($errorData['supported_networks']); // List of supported networks
}
```

### Supported Networks
- ethereum
- solana  
- fantom
- polygon
- bsc (Binance Smart Chain)
- avalanche

For the complete list of supported networks, call:
```php
$networks = $client->networks->getNetworks();
```

## [1.0.0] - 2023-06-20

### Added
- Added missing exception classes:
  - ValidationException
  - AuthenticationException
  - RateLimitException
  - ServerException
  - ClientException
- Retry with backoff functionality in BaseApi
- Configurable retry options in Config class
- PSR-6 compatible caching system
  - CacheInterface for implementing custom caches
  - FilesystemCache implementation 
  - Cache support in Config and BaseApi
  - Convenient setupCache method in Client class

### Enhanced
- DexPaprikaApiException now includes error data from API responses
- Fixed NotFoundException to use the correct namespace and include error data
- Improved exception handling with detailed error information 
# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.8.0] - 2026-08-14

### Added
- **Optional API key.** `Config::setApiKey()`, falling back to the `DEXPAPRIKA_API_KEY` environment variable when no key is set. Keyless remains the default and is unchanged: without a key the client sends exactly what it sent before. The key is transmitted as the **entire** `Authorization` value, with no `Bearer` prefix and no other scheme word, because the API checksums the raw header and a scheme word returns 401.
- `Config::getApiKey()` resolves the precedence, and `Config::API_KEY_ENV_VAR` names the variable.
- The host is never inferred from the presence of a key. Free keys are served from the default base URL and only Pro moves to `api-pro.dexpaprika.com`, set with `setBaseUrl()`. Sending a free key to the Pro host returns 403, so guessing would break exactly the people who just registered.

### Notes
- A key carrying CR, LF or NUL is dropped rather than sanitised. A mangled key authenticates as nobody, and because the data endpoints ignore an unreadable key instead of rejecting it, the caller would never find out.
- 12 new tests, 26 assertions, asserting on the headers that actually reach a Guzzle mock handler rather than on stored values: the bare-key format against five scheme words, keyless behaviour, precedence, whitespace trimming, header-injection rejection, the host rules, and an end-to-end pass through the real `Client` constructor.
- A key the API cannot read is ignored rather than rejected on the data endpoints: the call returns `200` with real data while quietly serving the keyless tier. `/usage` and its `plan` field are the way to confirm a key is landing.

## [1.7.0] - 2026-08-14

### Changed
- **API endpoint removed (410 Gone)**: `GET /networks/{network}/dexes/{dex}/pools` was removed by DexPaprika. `DexesApi::getDexPools()` (and its aliases `listDexPools()` and `fetchAllDexPools()`) now call the unified `/networks/{network}/pools/search` endpoint with a `dex_name` filter. Method signatures are unchanged. The DEX id you pass is sent as `dex_name`. Despite the parameter name, that filter matches the DEX id (`curve`, `uniswap_v3`) case-insensitively. A display name such as `Uniswap V3` returns an empty result set instead of an error, so pass the `dex_id` field from `GET /networks/{network}/dexes`.
- **Cursor pagination**: `getDexPools()` now returns `results`, `has_next_page` and `next_cursor` instead of `pools` plus `page_info`. The `page` option is still accepted but ignored and is no longer sent on the wire; pass `cursor` to page through results. `fetchAllDexPools()` follows `next_cursor` internally.
- **Field renames on pool rows**: the 24h volume is `volume_usd_24h`, not `volume_usd`, and the transaction count is `transactions_24h`, not `transactions`. Tokens inside a row carry `id`, `chain` and `has_image` only, so `tokens[0]['symbol']` is no longer available. Use `TokensApi::getTokenDetails()` when you need a symbol.
- Legacy `orderBy` values (e.g. `volume_usd`) are mapped to the canonical search names automatically, so canonical fields such as `liquidity_usd` are accepted too.
- Updated SDK VERSION constant to 1.6.0.

### Fixed
- The `DexesApiTest` mocks for `getDexPools` asserted the old `pools` plus `page_info` shape, which is why the broken method kept passing CI. They are now built from a response captured live from `/networks/ethereum/pools/search?dex_name=curve`.
- `examples/basic_usage.php`, `examples/object_transformation.php` and `examples/pagination.php` read `pools` and `volume_usd` off the DEX pools response. They now read `results` and `volume_usd_24h`.


## [1.6.0] - 2026-08-07

### Added
- **Short price-change windows on pool search**: `price_change_percentage_6h`, `price_change_percentage_1h` and `price_change_percentage_5m` are now recognised as canonical `sortBy`/`orderBy` values for `PoolsApi::getNetworkPools()` and `PoolsApi::filterPools()`. Previously they fell through to the unknown-field fallback and were sent as `volume_usd_24h`, so a caller asking for the 1h sort got `200` and a full result set ordered by volume.
- **Price-change bounds on `PoolsApi::filterPools()`**: eight new options, `priceChangePercentage{24h,6h,1h,5m}{Min,Max}`, mapping to `price_change_percentage_{24h,6h,1h,5m}_{min,max}`. `filterPools()` reads named keys, so bounds it does not name cannot be passed at all. The 24h pair is included; the endpoint already accepted it. Bounds are percentages and negative values are the common case: down at least 20 percent in the last hour is `'priceChangePercentage1hMax' => -20`.
- **24h price-change bounds on `TokensApi::filterTokens()`**: `priceChangePercentage24hMin` and `priceChangePercentage24hMax`, mapping to `price_change_percentage_24h_{min,max}`. `tokens/search` has honoured this pair all along and the SDK had no way to send it.

### Notes
- Only the 6h, 1h and 5m windows are pools-only. `tokens/search` returns `400` when you sort by one of those three, ignores their filter bounds, and its rows carry no short-window price-change field, so `TOKEN_SORT_CANONICAL` is deliberately unchanged and `SearchParamsTest::testShortPriceChangeWindowsArePoolOnly` pins that asymmetry. The 24h window is different: it sorts and filters on both endpoints, which is why it lands on `filterTokens()` too.
- Both search endpoints answer `200` and ignore query parameters they do not recognise, so a bound the SDK gets wrong returns a plausible unfiltered list rather than an error. Verified live against `api.dexpaprika.com` by comparing every bound against an unfiltered baseline, with a deliberately misspelled parameter as the control.
- Updated SDK VERSION constant to 1.6.0.

## [1.5.0] - 2026-07-15

### Changed
- **API endpoint removed (410 Gone)**: `GET /networks/{network}/tokens/{address}/pools` was removed by DexPaprika. `TokensApi::getTokenPools()` (and its aliases `listTokenPools()`, `getTokenPairs()`, `listTokenPairs()`) now call the unified `/networks/{network}/pools/search` endpoint with its new `token_address` parameter. Method signatures are unchanged.
- **Cursor pagination**: `getTokenPools()` now returns `results`, `has_next_page` and `next_cursor` instead of `pools` plus `page_info`. The `page` option is still accepted but ignored; pass `cursor` to page through results. `fetchAllTokenPools()` follows `next_cursor` internally.
- **Network-scoped only**: the cross-network `/pools/search` endpoint accepts `token_address` but silently ignores it, so a network is always required.
- **Removed options**: the `address` (pair filter) and `reorder` (pair-perspective flip) options have no `/pools/search` equivalent and are now ignored. Repeating `token_address` does not act as a pair filter; the API uses only one of the values (not guaranteed by order). Filter the returned pools client-side to match a pair.
- Legacy `orderBy` values (e.g. `volume_usd`) are mapped to the canonical search names automatically. An unknown token address returns an empty result set, not an error.
- Updated SDK VERSION constant to 1.5.0.

## [1.4.0] - 2026-07-01

Version jumps from the previous `v1.3.0` release tag straight to `1.4.0` (the migration below was first tagged `v1.2.0`, which sorted under the existing `v1.3.0`; `1.4.0` supersedes both so Composer serves the migrated code).

### Changed
- **Deprecation errors now surface the replacement**: on a removed-endpoint response the SDK builds `DeprecationException` from the API body's `message` and appends `Use <replacement> instead.` (previously the message was the literal `"Unknown error"`), and exposes `DeprecationException::getReplacement()`. Generic: it keys on any error body carrying a `replacement` field.
- **Unified search endpoints**: `PoolsApi::getNetworkPools()` and `PoolsApi::filterPools()` now call `/networks/{network}/pools/search`; `TokensApi::getTopTokens()` and `TokensApi::filterTokens()` now call `/networks/{network}/tokens/search`. The previous list/filter/top endpoints return `410 Gone`.
- **Cursor pagination**: these four methods now return `results`, `has_next_page` and `next_cursor` instead of `pools`/`tokens` plus `page_info`. The `page` option is still accepted but ignored; pass `cursor` to page through results.
- **Filter methods** now send `order_by` + `sort` (previously `sort_by` + `sort_dir`).
- Public method signatures are unchanged. Legacy sort values (e.g. `volume_usd`, `transactions`, `fdv`, `price_usd`) and legacy filter parameter names (e.g. `volume24hMin` -> `volume_usd_24h_min`) are mapped to the canonical search names automatically so requests do not return `400`.
- `Paginator` now understands cursor-paginated responses (`has_next_page`/`next_cursor`) in addition to offset-based `page_info`.
- Updated SDK VERSION constant to 1.2.0.

### Added
- `DexPaprika\Utils\SearchParams` helper with shared, pure sort-field and filter-parameter mappers used by pools and tokens.

## [1.1.0] - 2026-03-31

### Added
- **Pool filtering**: `PoolsApi::filterPools()` method for advanced pool filtering by volume, liquidity, transactions, and creation date
- **Top tokens**: `TokensApi::getTopTokens()` method for discovering top tokens on a network ranked by volume, price, liquidity, or other metrics
- **Token filtering**: `TokensApi::filterTokens()` method for filtering tokens by volume, liquidity, FDV, transactions, and creation date
- **Batch prices**: `TokensApi::getMultiPrices()` method for getting prices of up to 10 tokens in a single request
- Tests for all new endpoints

### Changed
- Updated SDK VERSION constant to 1.1.0

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
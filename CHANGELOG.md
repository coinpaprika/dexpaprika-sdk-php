# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.11.0] - 2026-09-29

### Added
- **`$client->tokens->getTokenOHLCV()`**, backed by the new `GET /networks/{network}/tokens/{token_address}/ohlcv`. Candles are a volume-weighted USD price built across every pool the token trades in on the network, with volume summed the same way; same record shape as `getPoolOHLCV()`. There is no `inversed` option, because a USD price has no pair side to flip. Same `start`/`end`/`interval`/`limit` handling as pool OHLCV, including the 1 to 1000 `limit` range. Requires a Dev, Pro or Enterprise plan, called against `api-pro.dexpaprika.com` with the API key set as the whole `Authorization` value; a keyless or free-key call gets `403` and the thrown `ClientException` carries the API's message. Dev plans see up to the last 30 days of history.

## [1.10.1] - 2026-09-28

### Fixed
- **`$client->networks->getNetworkDexes()` and `listNetworkDexes()` crashed.** They called a client property that `NetworksApi` never had (`Undefined property: NetworksApi::$client`, then a fatal on `sendRequest()`). They now call `GET /networks/{network}/dexes` like `$client->dexes->getNetworkDexes()` does, and `asObject` is no longer sent as a query parameter.
- **`$client->networks->findNetwork()` always returned `null`.** It looked for a `networks` key, but `GET /networks` returns a plain list. It now searches the list, still accepts a wrapped one, and in object mode returns the matching network as an object instead of failing on an undefined property.
- **`$client->networks->findDex()` could never match.** It went through the broken `getNetworkDexes()` and compared an `id` field that DEX rows do not have. It now matches `dex_id` (for example `'uniswap_v3'`, the value `getDexPools()` takes), falling back to `id`.
- **`fetchAllNetworkDexes()` fetched the same list over and over.** The endpoint ignores `page` and `limit` and returns every DEX with `total_pages` 0, so the loop repeated the full list until `maxPages`, and without end when `maxPages` was 0. It now stops when the response gives no page count or the last page is reached.

## [1.10.0] - 2026-09-28

### Fixed
- **`$client->pools->getPoolTransactions()` dropped `from` and `to`.** The options were documented on `TransactionsApi`, which `Client` does not expose, and the method you reach through the client never forwarded them, so a time-filtered call quietly returned the latest transactions instead. Both are now sent.

### API changes this release documents
- **`from` and `to` on pool transactions, and `created_after` and `created_before` on pool and token search, accept a relative offset from now** (`-1h`, `-24h`, `-7d`; units s, m, h, d), RFC 3339 or `YYYY-MM-DD`, next to Unix seconds. `'from' => '-1h'` returns the last hour of transactions. Unix seconds keep working unchanged. `from` is inclusive and `to` is exclusive; the search bounds include both ends.

### Changed
- Docblocks for `from`, `to`, `createdAfter` and `createdBefore` list the accepted formats and take `string|int`.

## [1.9.0] - 2026-09-25

OHLCV availability now depends on your plan. This release documents the change, updates the examples so they work without a key, and lets `limit` go as high as the API allows.

### API changes this release documents
- **OHLCV history depth and candle interval are per plan since 2026-09-25.** Without a key: the last 24 hours at `1h`, `6h`, `12h` and `24h`. Free key: 7 days at `10m` and longer (`1m` and `5m` are paid). Dev: 30 days at every interval. Pro and Enterprise: unlimited. A `start` or `end` outside the window, or a finer interval than the plan allows, is answered with `403`; `getPoolOHLCV` throws a `ClientException` whose message names the plan that lifts the limit. See [OHLCV limits by plan](https://docs.dexpaprika.com/knowledge-base/rate-limits#ohlcv-limits-by-plan).
- **`start` and `end` accept a relative offset from now:** `-24h`, `-7d`, `-90m`, `-30s`. `'-24h'` selects the last 24 hours, which every plan may query. `$start` is a string, so this works in 1.8.1 too.
- A missing or malformed `start` or `end` is answered with `400`.

### Fixed
- **`getPoolOHLCV` rejected `limit` above 366.** The API accepts up to 1000 candles per request; the SDK now does too.
- The README example (`2023-01-01` to `2023-01-07`), `examples/advanced_usage.php` and `examples/ohlcv_data.php` asked for 7 to 14 days of daily candles, which returns 403 without a key. They use `'-24h'` with hourly candles; the SMA and volatility examples work on hourly data.

### Changed
- `getPoolOHLCV` docblock describes the relative offset and the per-plan window.

## [1.8.1] - 2026-09-18

### Fixed
- `composer.json` now requires `psr/log`. `BaseApi` instantiates `Psr\Log\NullLogger` when no logger is passed, and nothing in the dependency tree provided it, so a fresh `composer require coinpaprika/dexpaprika-sdk` followed by `new Client()` failed with `Class "Psr\Log\NullLogger" not found`. Tests never caught it because phpunit brings `psr/log` in as a dev dependency.

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
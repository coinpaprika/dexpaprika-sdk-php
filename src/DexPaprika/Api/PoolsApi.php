<?php

namespace DexPaprika\Api;

use DexPaprika\Exception\NotFoundException;
use DexPaprika\Exception\DeprecationException;
use DexPaprika\Exception\ValidationException;
use DexPaprika\Utils\ResponseTransformer;

class PoolsApi extends BaseApi
{
    /**
     * Get a paginated list of top liquidity pools from all networks
     * 
     * @deprecated Since API v1.3.0, this endpoint returns 410 Gone. Use getNetworkPools() instead.
     * @param array<string, mixed> $options Additional options:
     *  - int $page: Page number for pagination (default: 0)
     *  - int $limit: Number of items per page (default: 10)
     *  - string $orderBy: Field to order by (default: 'volume_usd')
     *  - string $sort: Sort order (default: 'desc')
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object List of top pools from all networks
     * @throws DeprecationException This endpoint has been permanently removed
     */
    public function getTopPools(array $options = [])
    {
        throw new DeprecationException(
            'The global /pools endpoint has been deprecated and returns 410 Gone. ' .
            'Please use network-specific endpoints instead. ' .
            'Examples: getNetworkPools(\'ethereum\'), getNetworkPools(\'solana\'), getNetworkPools(\'fantom\'). ' .
            'For more information, visit: https://docs.dexpaprika.com/changelog/changelog',
            410,
            [
                'error' => 'Endpoint Removed',
                'message' => 'This endpoint has been permanently removed. Please refer to our API documentation for alternatives.',
                'migration_examples' => [
                    'Before: $api->getTopPools($options)',
                    'After: $api->getNetworkPools(\'ethereum\', $options)',
                    'After: $api->getNetworkPools(\'solana\', $options)',
                ],
                'supported_networks' => ['ethereum', 'solana', 'fantom', 'polygon', 'bsc', 'avalanche']
            ]
        );
    }

    /**
     * List top pools (alias for getTopPools)
     *
     * @deprecated Since API v1.3.0, this endpoint returns 410 Gone. Use getNetworkPools() instead.
     * @param array<string, mixed> $options Additional options:
     *  - int $page: Page number for pagination (default: 0)
     *  - int $limit: Number of items per page (default: 10)
     *  - string $orderBy: Field to order by (default: 'volume_usd')
     *  - string $sort: Sort order (default: 'desc')
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object List of top pools from all networks
     * @throws DeprecationException This endpoint has been permanently removed
     */
    public function listTopPools(array $options = [])
    {
        return $this->getTopPools($options);
    }

    /**
     * Get a list of top liquidity pools on a specific network
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param array<string, mixed> $options Additional options:
     *  - int $page: Page number for pagination (default: 0)
     *  - int $limit: Number of items per page (default: 10, max: 100)
     *  - string $orderBy: Field to order by (default: 'volume_usd')
     *  - string $sort: Sort order (default: 'desc')
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object List of pools on the specified network
     * @throws ValidationException If network ID is invalid or parameters are out of range
     */
    public function getNetworkPools(string $networkId, array $options = [])
    {
        // Validate required network parameter
        $this->validateNetworkId($networkId);
        
        // Validate limit parameter
        if (isset($options['limit']) && ($options['limit'] < 1 || $options['limit'] > 100)) {
            throw new ValidationException('Limit must be between 1 and 100');
        }

        $params = [];

        if (isset($options['page'])) {
            $params['page'] = $options['page'];
        }

        if (isset($options['limit'])) {
            $params['limit'] = $options['limit'];
        }

        if (isset($options['orderBy'])) {
            $params['order_by'] = $options['orderBy'];
        }

        if (isset($options['sort'])) {
            $params['sort'] = $options['sort'];
        }

        $response = $this->get("/networks/{$networkId}/pools", $params);
        
        return $this->transformResponse($response, $options['asObject'] ?? false);
    }

    /**
     * List pools on a specific network (alias for getNetworkPools)
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param array<string, mixed> $options Additional options:
     *  - int $page: Page number for pagination (default: 0)
     *  - int $limit: Number of items per page (default: 10, max: 100)
     *  - string $orderBy: Field to order by (default: 'volume_usd')
     *  - string $sort: Sort order (default: 'desc')
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object List of pools on the specified network
     */
    public function listNetworkPools(string $networkId, array $options = [])
    {
        return $this->getNetworkPools($networkId, $options);
    }

    /**
     * Get detailed information about a specific pool on a network
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $poolAddress Pool address or identifier
     * @param array<string, mixed> $options Additional options:
     *  - bool $inversed: Whether to invert the price ratio (default: false)
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object Detailed pool information
     * @throws ValidationException If network ID or pool address is invalid
     */
    public function getPoolDetails(string $networkId, string $poolAddress, array $options = [])
    {
        // Validate required parameters
        $this->validateNetworkId($networkId);
        $this->validatePoolAddress($poolAddress);

        $params = [];

        if (isset($options['inversed'])) {
            $params['inversed'] = $options['inversed'];
        }

        $response = $this->get("/networks/{$networkId}/pools/{$poolAddress}", $params);
        
        return $this->transformResponse($response, $options['asObject'] ?? false);
    }

    /**
     * Get OHLCV data for a specific pool
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $poolAddress Pool address or identifier
     * @param string $start Start time for historical data (ISO-8601, yyyy-mm-dd, or Unix timestamp)
     * @param array<string, mixed> $options Additional options:
     *  - string $end: End time for historical data
     *  - string $interval: Interval granularity for OHLCV data (default: '24h')
     *  - int $limit: Number of data points to retrieve (default: 1, max: 366)
     *  - bool $inversed: Whether to invert the price ratio (default: false)
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object OHLCV data for the pool
     * @throws ValidationException If parameters are invalid
     */
    public function getPoolOHLCV(string $networkId, string $poolAddress, string $start, array $options = [])
    {
        // Validate required parameters
        $this->validateNetworkId($networkId);
        $this->validatePoolAddress($poolAddress);
        
        if (empty($start)) {
            throw new ValidationException('Start parameter is required for OHLCV data');
        }
        
        // Validate limit parameter
        if (isset($options['limit']) && ($options['limit'] < 1 || $options['limit'] > 366)) {
            throw new ValidationException('Limit must be between 1 and 366 for OHLCV data');
        }

        $params = [
            'start' => $start,
        ];

        if (isset($options['end'])) {
            $params['end'] = $options['end'];
        }

        if (isset($options['interval'])) {
            $params['interval'] = $options['interval'];
        }

        if (isset($options['limit'])) {
            $params['limit'] = $options['limit'];
        }

        if (isset($options['inversed'])) {
            $params['inversed'] = $options['inversed'];
        }

        $response = $this->get("/networks/{$networkId}/pools/{$poolAddress}/ohlcv", $params);
        
        return $this->transformResponse($response, $options['asObject'] ?? false);
    }

    /**
     * Get transactions of a pool on a network
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $poolAddress Pool address or identifier
     * @param array<string, mixed> $options Additional options:
     *  - int $page: Page number for pagination (default: 0)
     *  - int $limit: Number of items per page (default: 10, max: 100)
     *  - string $cursor: Transaction ID used for cursor-based pagination
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object Pool transactions
     * @throws ValidationException If parameters are invalid
     */
    public function getPoolTransactions(string $networkId, string $poolAddress, array $options = [])
    {
        // Validate required parameters
        $this->validateNetworkId($networkId);
        $this->validatePoolAddress($poolAddress);
        
        // Validate limit parameter
        if (isset($options['limit']) && ($options['limit'] < 1 || $options['limit'] > 100)) {
            throw new ValidationException('Limit must be between 1 and 100');
        }

        $params = [];

        if (isset($options['page'])) {
            $params['page'] = $options['page'];
        }

        if (isset($options['limit'])) {
            $params['limit'] = $options['limit'];
        }

        if (isset($options['cursor'])) {
            $params['cursor'] = $options['cursor'];
        }

        $response = $this->get("/networks/{$networkId}/pools/{$poolAddress}/transactions", $params);
        
        return $this->transformResponse($response, $options['asObject'] ?? false);
    }

    /**
     * Filter pools on a network by volume, liquidity, transactions, and creation date
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param array<string, mixed> $options Filter options:
     *  - int $page: Page number for pagination (1-indexed, default: 1)
     *  - int $limit: Number of items per page (default: 10, max: 100)
     *  - string $sortBy: Field to sort by (e.g., 'volume_24h', 'liquidity_usd', 'txns_24h')
     *  - string $sortDir: Sort direction ('asc' or 'desc', default: 'desc')
     *  - float $volume24hMin: Minimum 24h volume in USD
     *  - float $volume24hMax: Maximum 24h volume in USD
     *  - float $volume7dMin: Minimum 7d volume in USD
     *  - float $volume7dMax: Maximum 7d volume in USD
     *  - float $liquidityUsdMin: Minimum liquidity in USD
     *  - float $liquidityUsdMax: Maximum liquidity in USD
     *  - int $txns24hMin: Minimum number of transactions in 24h
     *  - string|int $createdAfter: Only pools created after this time (Unix timestamp)
     *  - string|int $createdBefore: Only pools created before this time (Unix timestamp)
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object Filtered pools with pagination info
     * @throws ValidationException If parameters are invalid
     */
    public function filterPools(string $networkId, array $options = [])
    {
        $this->validateNetworkId($networkId);

        if (isset($options['limit']) && ($options['limit'] < 1 || $options['limit'] > 100)) {
            throw new ValidationException('Limit must be between 1 and 100');
        }

        $params = [];

        if (isset($options['page'])) {
            $params['page'] = $options['page'];
        }
        if (isset($options['limit'])) {
            $params['limit'] = $options['limit'];
        }
        if (isset($options['sortBy'])) {
            $params['sort_by'] = $options['sortBy'];
        }
        if (isset($options['sortDir'])) {
            $params['sort_dir'] = $options['sortDir'];
        }
        if (isset($options['volume24hMin'])) {
            $params['volume_24h_min'] = $options['volume24hMin'];
        }
        if (isset($options['volume24hMax'])) {
            $params['volume_24h_max'] = $options['volume24hMax'];
        }
        if (isset($options['volume7dMin'])) {
            $params['volume_7d_min'] = $options['volume7dMin'];
        }
        if (isset($options['volume7dMax'])) {
            $params['volume_7d_max'] = $options['volume7dMax'];
        }
        if (isset($options['liquidityUsdMin'])) {
            $params['liquidity_usd_min'] = $options['liquidityUsdMin'];
        }
        if (isset($options['liquidityUsdMax'])) {
            $params['liquidity_usd_max'] = $options['liquidityUsdMax'];
        }
        if (isset($options['txns24hMin'])) {
            $params['txns_24h_min'] = $options['txns24hMin'];
        }
        if (isset($options['createdAfter'])) {
            $params['created_after'] = $options['createdAfter'];
        }
        if (isset($options['createdBefore'])) {
            $params['created_before'] = $options['createdBefore'];
        }

        $response = $this->get("/networks/{$networkId}/pools/filter", $params);

        return $this->transformResponse($response, $options['asObject'] ?? false);
    }

    /**
     * Advanced pool search across all networks (or a single network).
     *
     * Hits the frontend search surface: GET /frontend/v1/pools (global) and
     * GET /frontend/v1/networks/{network}/pools (per-network). Supports cursor
     * pagination, sorting, and a rich set of numeric/string filters.
     *
     * Pagination here is cursor-based, NOT page-based. Read $response['next_cursor']
     * from the previous response and pass it back as the 'cursor' option to walk
     * forward. $response['has_next_page'] tells you whether more results exist.
     *
     * Note on sort parameters: this method exposes the canonical 'sortBy' / 'sortDir'
     * options and translates them to the wire names the backend expects
     * (sortBy -> order_by, sortDir -> sort). Do not pass order_by/sort directly.
     *
     * @param array<string, mixed> $options Search options:
     *  - string $network: Restrict to a single network (e.g., 'ethereum', 'solana').
     *                     When omitted, searches across all networks.
     *  - int $limit: Number of pools per page
     *  - string $cursor: Cursor for the next page (from a previous response's 'next_cursor')
     *  - string $sortBy: Field to sort by. One of: volume_usd_24h, volume_usd_7d,
     *                    volume_usd_30d, liquidity_usd, txns_24h, price_usd,
     *                    price_change_percentage_24h, created_at (default: volume_usd_24h).
     *                    Translated to 'order_by' on the wire.
     *  - string $sortDir: Sort direction, 'asc' or 'desc' (default: 'desc').
     *                    Translated to 'sort' on the wire.
     *  - float $volume24hMin / $volume24hMax: 24h volume bounds in USD
     *  - float $volume7dMin / $volume7dMax: 7d volume bounds in USD
     *  - float $liquidityUsdMin / $liquidityUsdMax: Liquidity bounds in USD
     *  - int $txns24hMin: Minimum number of transactions in 24h
     *  - float $priceUsdMin / $priceUsdMax: Price bounds in USD
     *  - float $priceChangePercentage24hMin / $priceChangePercentage24hMax: 24h price-change bounds (%)
     *  - string $dexName: Restrict to a single DEX (e.g., 'uniswap_v3')
     *  - string|int $createdAfter / $createdBefore: Pool creation time bounds
     *  - bool $detailed: When true, each token carries fdv plus per-timeframe metric blocks
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object Search results: results, has_next_page, next_cursor, query
     * @throws ValidationException If parameters are invalid
     */
    public function advancedSearchPools(array $options = [])
    {
        if (isset($options['network'])) {
            $this->validateNetworkId($options['network']);
        }

        if (isset($options['limit']) && ($options['limit'] < 1 || $options['limit'] > 100)) {
            throw new ValidationException('Limit must be between 1 and 100');
        }

        $params = [];

        if (isset($options['limit'])) {
            $params['limit'] = $options['limit'];
        }
        if (isset($options['cursor'])) {
            $params['cursor'] = $options['cursor'];
        }
        // Canonical sort options translate to the backend wire names:
        // sortBy -> order_by (field), sortDir -> sort (direction).
        if (isset($options['sortBy'])) {
            $params['order_by'] = $options['sortBy'];
        }
        if (isset($options['sortDir'])) {
            $params['sort'] = $options['sortDir'];
        }
        if (isset($options['volume24hMin'])) {
            $params['volume_24h_min'] = $options['volume24hMin'];
        }
        if (isset($options['volume24hMax'])) {
            $params['volume_24h_max'] = $options['volume24hMax'];
        }
        if (isset($options['volume7dMin'])) {
            $params['volume_7d_min'] = $options['volume7dMin'];
        }
        if (isset($options['volume7dMax'])) {
            $params['volume_7d_max'] = $options['volume7dMax'];
        }
        if (isset($options['liquidityUsdMin'])) {
            $params['liquidity_usd_min'] = $options['liquidityUsdMin'];
        }
        if (isset($options['liquidityUsdMax'])) {
            $params['liquidity_usd_max'] = $options['liquidityUsdMax'];
        }
        if (isset($options['txns24hMin'])) {
            $params['txns_24h_min'] = $options['txns24hMin'];
        }
        if (isset($options['priceUsdMin'])) {
            $params['price_usd_min'] = $options['priceUsdMin'];
        }
        if (isset($options['priceUsdMax'])) {
            $params['price_usd_max'] = $options['priceUsdMax'];
        }
        if (isset($options['priceChangePercentage24hMin'])) {
            $params['price_change_percentage_24h_min'] = $options['priceChangePercentage24hMin'];
        }
        if (isset($options['priceChangePercentage24hMax'])) {
            $params['price_change_percentage_24h_max'] = $options['priceChangePercentage24hMax'];
        }
        if (isset($options['dexName'])) {
            $params['dex_name'] = $options['dexName'];
        }
        if (isset($options['createdAfter'])) {
            $params['created_after'] = $options['createdAfter'];
        }
        if (isset($options['createdBefore'])) {
            $params['created_before'] = $options['createdBefore'];
        }
        if (isset($options['detailed'])) {
            // Send a real query string the backend reads as truthy.
            $params['detailed'] = $options['detailed'] ? 'true' : 'false';
        }

        if (isset($options['network'])) {
            $endpoint = "/frontend/v1/networks/{$options['network']}/pools";
        } else {
            $endpoint = '/frontend/v1/pools';
        }

        $response = $this->get($endpoint, $params);

        return $this->transformResponse($response, $options['asObject'] ?? false);
    }

    /**
     * Advanced pool search (alias for advancedSearchPools).
     *
     * @param array<string, mixed> $options See advancedSearchPools() for the full option list.
     * @return array<string, mixed>|object Search results: results, has_next_page, next_cursor, query
     */
    public function searchPools(array $options = [])
    {
        return $this->advancedSearchPools($options);
    }

    /**
     * Find a pool by its address on a specific network
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $poolAddress Pool address or identifier
     * @param bool $asObject Whether to return the response as an object
     * @return array<string, mixed>|object Pool details
     * @throws NotFoundException If the pool is not found
     * @throws ValidationException If parameters are invalid
     */
    public function findPool(string $networkId, string $poolAddress, bool $asObject = false)
    {
        $result = $this->getPoolDetails($networkId, $poolAddress, ['asObject' => $asObject]);
        
        if ($asObject) {
            if (!isset($result->id)) {
                throw new NotFoundException("Pool with address $poolAddress not found on network $networkId");
            }
        } else {
            if (!isset($result['id'])) {
                throw new NotFoundException("Pool with address $poolAddress not found on network $networkId");
            }
        }
        
        return $result;
    }

    /**
     * Validate network ID parameter
     *
     * @param string $networkId Network ID to validate
     * @throws ValidationException If network ID is invalid
     */
    protected function validateNetworkId(string $networkId): void
    {
        if (empty($networkId) || trim($networkId) === '') {
            throw new ValidationException('Network ID is required and cannot be empty');
        }
        
        // Basic validation for network ID format
        if (!preg_match('/^[a-z0-9_-]+$/i', $networkId)) {
            throw new ValidationException('Network ID must contain only alphanumeric characters, underscores, and hyphens');
        }
    }

    /**
     * Validate pool address parameter
     *
     * @param string $poolAddress Pool address to validate
     * @throws ValidationException If pool address is invalid
     */
    protected function validatePoolAddress(string $poolAddress): void
    {
        if (empty($poolAddress) || trim($poolAddress) === '') {
            throw new ValidationException('Pool address is required and cannot be empty');
        }
    }

    /**
     * Fetch all pools from a network page by page using a callback function
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param callable $callback Function to call for each page of pools: function(array|object $pools, int $page): bool
     *                          Return false from the callback to stop pagination
     * @param array<string, mixed> $options Additional options:
     *  - int $limit: Number of items per page (default: 10, max: 100)
     *  - string $orderBy: Field to order by (default: 'volume_usd')
     *  - string $sort: Sort order (default: 'desc')
     *  - int $maxPages: Maximum number of pages to fetch (default: 10, use 0 for unlimited)
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return int Total number of pages fetched
     */
    public function fetchAllNetworkPools(string $networkId, callable $callback, array $options = []): int
    {
        $page = 0;
        $totalPages = 0;
        $limit = $options['limit'] ?? 10;
        $maxPages = $options['maxPages'] ?? 10;
        
        // Set asObject and remove from options to pass to API
        $asObject = $options['asObject'] ?? false;
        $apiOptions = $options;
        unset($apiOptions['maxPages'], $apiOptions['asObject']);
        $apiOptions['page'] = $page;
        
        do {
            $response = $this->getNetworkPools($networkId, array_merge($apiOptions, ['asObject' => $asObject]));
            
            $continueProcessing = $callback($response, $page);
            $totalPages++;
            $page++;
            
            // Update the page number for the next request
            $apiOptions['page'] = $page;
            
            // Check if we should continue processing
            if ($continueProcessing === false) {
                break;
            }
            
            // Check if we've reached the maximum number of pages
            if ($maxPages > 0 && $page >= $maxPages) {
                break;
            }
            
            // Check if we've reached the end of available pools
            if ($asObject) {
                if (!isset($response->pools) || count($response->pools) < $limit) {
                    break;
                }
                
                // Check if we've reached the last page based on page_info
                if (isset($response->page_info) && 
                    isset($response->page_info->page) && 
                    isset($response->page_info->total_pages) && 
                    $response->page_info->page + 1 >= $response->page_info->total_pages) {
                    break;
                }
            } else {
                if (!isset($response['pools']) || count($response['pools']) < $limit) {
                    break;
                }
                
                // Check if we've reached the last page based on page_info
                if (isset($response['page_info']) && 
                    isset($response['page_info']['page']) && 
                    isset($response['page_info']['total_pages']) && 
                    $response['page_info']['page'] + 1 >= $response['page_info']['total_pages']) {
                    break;
                }
            }
            
        } while (true);
        
        return $totalPages;
    }

    /**
     * Fetch all transactions from a pool using a callback function
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $poolAddress Pool address or identifier
     * @param callable $callback Function to call for each page of transactions: function(array|object $transactions, int $page): bool
     *                          Return false from the callback to stop pagination
     * @param array<string, mixed> $options Additional options:
     *  - int $limit: Number of items per page (default: 10, max: 100)
     *  - int $maxPages: Maximum number of pages to fetch (default: 10, use 0 for unlimited)
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return int Total number of pages fetched
     */
    public function fetchAllPoolTransactions(string $networkId, string $poolAddress, callable $callback, array $options = []): int
    {
        $page = 0;
        $totalPages = 0;
        $limit = $options['limit'] ?? 10;
        $maxPages = $options['maxPages'] ?? 10;
        
        // Set asObject and remove from options to pass to API
        $asObject = $options['asObject'] ?? false;
        $apiOptions = $options;
        unset($apiOptions['maxPages'], $apiOptions['asObject']);
        $apiOptions['page'] = $page;
        
        do {
            $response = $this->getPoolTransactions($networkId, $poolAddress, array_merge($apiOptions, ['asObject' => $asObject]));
            
            $continueProcessing = $callback($response, $page);
            $totalPages++;
            $page++;
            
            // Update the page number for the next request
            $apiOptions['page'] = $page;
            
            // Check if we should continue processing
            if ($continueProcessing === false) {
                break;
            }
            
            // Check if we've reached the maximum number of pages
            if ($maxPages > 0 && $page >= $maxPages) {
                break;
            }
            
            // Check if we've reached the end of available transactions
            if ($asObject) {
                if (!isset($response->transactions) || count($response->transactions) < $limit) {
                    break;
                }
                
                // Check if we've reached the last page based on page_info
                if (isset($response->page_info) && 
                    isset($response->page_info->page) && 
                    isset($response->page_info->total_pages) && 
                    $response->page_info->page + 1 >= $response->page_info->total_pages) {
                    break;
                }
            } else {
                if (!isset($response['transactions']) || count($response['transactions']) < $limit) {
                    break;
                }
                
                // Check if we've reached the last page based on page_info
                if (isset($response['page_info']) && 
                    isset($response['page_info']['page']) && 
                    isset($response['page_info']['total_pages']) && 
                    $response['page_info']['page'] + 1 >= $response['page_info']['total_pages']) {
                    break;
                }
            }
            
        } while (true);
        
        return $totalPages;
    }

    /**
     * Transform the pools response
     *
     * @param array<string, mixed> $response The API response array
     * @param bool $asObject Whether to transform the response to an object
     * @return array<string, mixed>|object The transformed response
     */
    protected function transformResponse(array $response, bool $asObject = false)
    {
        if ($asObject) {
            return ResponseTransformer::transformPools($response);
        }
        
        return $response;
    }
} 
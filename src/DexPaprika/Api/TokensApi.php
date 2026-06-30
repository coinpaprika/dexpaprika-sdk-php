<?php

namespace DexPaprika\Api;

use DexPaprika\Exception\NotFoundException;
use DexPaprika\Exception\ValidationException;
use DexPaprika\Utils\ResponseTransformer;
use DexPaprika\Utils\SearchParams;

class TokensApi extends BaseApi
{
    /**
     * Get detailed information about a specific token on a network
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $tokenAddress Token address or identifier
     * @param array<string, mixed> $options Additional options:
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object Detailed token information
     */
    public function getTokenDetails(string $networkId, string $tokenAddress, array $options = [])
    {
        $params = [
            'network' => $networkId,
            'tokenAddress' => $tokenAddress,
        ];

        $response = $this->get("/tokens/$networkId/$tokenAddress", $params);
        
        return $this->transformResponse($response, $options['asObject'] ?? false);
    }

    /**
     * Find a token by its address on a specific network
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $tokenAddress Token address or identifier
     * @param bool $asObject Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object Token details
     * @throws NotFoundException If the token is not found
     */
    public function findToken(string $networkId, string $tokenAddress, bool $asObject = false)
    {
        $result = $this->getTokenDetails($networkId, $tokenAddress, ['asObject' => $asObject]);
        
        if ($asObject) {
            if (!isset($result->token)) {
                throw new NotFoundException("Token with address $tokenAddress not found on network $networkId");
            }
        } else {
            if (!isset($result['token'])) {
                throw new NotFoundException("Token with address $tokenAddress not found on network $networkId");
            }
        }
        
        return $result;
    }

    /**
     * Get a list of top liquidity pools for a specific token on a network
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $tokenAddress Token address or identifier
     * @param array<string, mixed> $options Additional options:
     *  - string $address: Filter pools that contain this additional token address
     *  - bool $reorder: If true, reorders the pool so that the token becomes the primary token for all metrics (default: false)
     *  - int $page: Page number for pagination (default: 0)
     *  - int $limit: Number of items per page (default: 10, max: 100)
     *  - string $orderBy: Field to order by (default: 'volume_usd')
     *  - string $sort: Sort order (default: 'desc')
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object List of pools containing the token
     */
    public function getTokenPools(string $networkId, string $tokenAddress, array $options = [])
    {
        $params = [];

        if (isset($options['address'])) {
            $params['address'] = $options['address'];
        }

        if (isset($options['reorder'])) {
            $params['reorder'] = $options['reorder'];
        }

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

        $response = $this->get("/networks/{$networkId}/tokens/{$tokenAddress}/pools", $params);
        
        return $this->transformResponse($response, $options['asObject'] ?? false);
    }

    /**
     * List pools for a specific token (alias for getTokenPools)
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $tokenAddress Token address or identifier
     * @param array<string, mixed> $options Additional options:
     *  - string $address: Filter pools that contain this additional token address
     *  - bool $reorder: If true, reorders the pool so that the token becomes the primary token for all metrics (default: false)
     *  - int $page: Page number for pagination (default: 0)
     *  - int $limit: Number of items per page (default: 10, max: 100)
     *  - string $orderBy: Field to order by (default: 'volume_usd')
     *  - string $sort: Sort order (default: 'desc')
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object List of pools containing the token
     */
    public function listTokenPools(string $networkId, string $tokenAddress, array $options = [])
    {
        return $this->getTokenPools($networkId, $tokenAddress, $options);
    }

    /**
     * Get token pairs involving a specific token
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $tokenAddress Token address or identifier
     * @param array<string, mixed> $options Additional options:
     *  - int $page: Page number for pagination (default: 0)
     *  - int $limit: Number of items per page (default: 10)
     *  - string $orderBy: Field to order by ('volume_usd', 'price_usd', 'transactions', 'last_price_change_usd_24h', 'created_at')
     *  - string $sort: Sort order ('asc' or 'desc')
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object List of token pairs
     */
    public function getTokenPairs(string $networkId, string $tokenAddress, array $options = [])
    {
        // Reuse the token pools method
        return $this->getTokenPools($networkId, $tokenAddress, $options);
    }

    /**
     * List token pairs involving a specific token (alias for getTokenPairs)
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $tokenAddress Token address or identifier
     * @param array<string, mixed> $options Additional options
     * @return array<string, mixed>|object List of token pairs
     */
    public function listTokenPairs(string $networkId, string $tokenAddress, array $options = [])
    {
        return $this->getTokenPairs($networkId, $tokenAddress, $options);
    }

    /**
     * Get top tokens on a network ranked by volume, liquidity, or other metrics
     *
     * Backed by the unified /networks/{network}/tokens/search endpoint, which is
     * cursor-paginated. The response is shaped as:
     * {@code results[], has_next_page, next_cursor, query}. Each token exposes
     * address, chain, created_at, price_usd, volume_usd_24h/7d/30d, liquidity_usd,
     * fdv_usd, txns_24h and price_change_percentage_24h (a flat shape, no name/symbol).
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param array<string, mixed> $options Options:
     *  - int $page: Accepted for backward compatibility but ignored (the endpoint is cursor-based)
     *  - string $cursor: Opaque cursor from a previous response's next_cursor
     *  - int $limit: Number of items per page (default: 10, max: 100)
     *  - string $orderBy: Field to order by (legacy values mapped, default: 'volume_usd_24h')
     *  - string $sort: Sort direction ('asc' or 'desc', default: 'desc')
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object Top tokens (results[] + has_next_page + next_cursor)
     * @throws ValidationException If parameters are invalid
     */
    public function getTopTokens(string $networkId, array $options = [])
    {
        if (empty($networkId) || trim($networkId) === '') {
            throw new ValidationException('Network ID is required and cannot be empty');
        }

        if (isset($options['limit']) && ($options['limit'] < 1 || $options['limit'] > 100)) {
            throw new ValidationException('Limit must be between 1 and 100');
        }

        $params = [];

        if (isset($options['limit'])) {
            $params['limit'] = $options['limit'];
        }
        if (isset($options['orderBy'])) {
            $params['order_by'] = SearchParams::mapTokenSortField($options['orderBy']);
        }
        if (isset($options['sort'])) {
            $params['sort'] = $options['sort'];
        }
        if (isset($options['cursor'])) {
            $params['cursor'] = $options['cursor'];
        }

        $response = $this->get("/networks/{$networkId}/tokens/search", $params);

        return $this->transformResponse($response, $options['asObject'] ?? false);
    }

    /**
     * Filter tokens on a network by volume, liquidity, FDV, transactions, and creation date
     *
     * Backed by the unified /networks/{network}/tokens/search endpoint, which is
     * cursor-paginated and returns {@code results[], has_next_page, next_cursor, query}.
     * Legacy sort-field values and legacy filter parameter names are mapped to the
     * canonical search names so requests do not 400.
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param array<string, mixed> $options Filter options:
     *  - int $page: Accepted for backward compatibility but ignored (the endpoint is cursor-based)
     *  - string $cursor: Opaque cursor from a previous response's next_cursor
     *  - int $limit: Number of items per page (default: 10, max: 100)
     *  - string $sortBy: Field to sort by (legacy values mapped, e.g. 'fdv' -> 'fdv_usd')
     *  - string $sortDir: Sort direction ('asc' or 'desc', default: 'desc')
     *  - float $volume24hMin: Minimum 24h volume in USD
     *  - float $volume24hMax: Maximum 24h volume in USD
     *  - float $liquidityUsdMin: Minimum liquidity in USD
     *  - float $fdvMin: Minimum fully diluted valuation in USD
     *  - float $fdvMax: Maximum fully diluted valuation in USD
     *  - int $txns24hMin: Minimum number of transactions in 24h
     *  - string|int $createdAfter: Only tokens created after this time (Unix timestamp)
     *  - string|int $createdBefore: Only tokens created before this time (Unix timestamp)
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object Filtered tokens (results[] + has_next_page + next_cursor)
     * @throws ValidationException If parameters are invalid
     */
    public function filterTokens(string $networkId, array $options = [])
    {
        if (empty($networkId) || trim($networkId) === '') {
            throw new ValidationException('Network ID is required and cannot be empty');
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
        if (isset($options['sortBy'])) {
            $params['order_by'] = SearchParams::mapTokenSortField($options['sortBy']);
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
        if (isset($options['liquidityUsdMin'])) {
            $params['liquidity_usd_min'] = $options['liquidityUsdMin'];
        }
        if (isset($options['fdvMin'])) {
            $params['fdv_min'] = $options['fdvMin'];
        }
        if (isset($options['fdvMax'])) {
            $params['fdv_max'] = $options['fdvMax'];
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

        // Normalise legacy filter parameter names to their canonical search names.
        $params = SearchParams::mapTokenFilterParams($params);

        $response = $this->get("/networks/{$networkId}/tokens/search", $params);

        return $this->transformResponse($response, $options['asObject'] ?? false);
    }

    /**
     * Get batch prices for multiple tokens on a network
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param array<int, string> $tokens Array of token addresses (max 10)
     * @param array<string, mixed> $options Options:
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<int, array<string, mixed>>|array<int, object> Array of token prices
     * @throws ValidationException If parameters are invalid
     */
    public function getMultiPrices(string $networkId, array $tokens, array $options = [])
    {
        if (empty($networkId) || trim($networkId) === '') {
            throw new ValidationException('Network ID is required and cannot be empty');
        }

        if (empty($tokens)) {
            throw new ValidationException('Tokens array is required and must not be empty');
        }

        if (count($tokens) > 10) {
            throw new ValidationException('Tokens array must contain at most 10 addresses');
        }

        $params = [
            'tokens' => implode(',', $tokens),
        ];

        $response = $this->get("/networks/{$networkId}/multi/prices", $params);

        if ($options['asObject'] ?? false) {
            return array_map(function ($item) {
                return (object) $item;
            }, $response);
        }

        return $response;
    }

    /**
     * Fetch all pools containing a specific token using a callback function
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $tokenAddress Token address or identifier
     * @param callable $callback Function to call for each page of pools: function(array|object $pools, int $page): bool
     *                          Return false from the callback to stop pagination
     * @param array<string, mixed> $options Additional options:
     *  - string $address: Filter pools that contain this additional token address
     *  - bool $reorder: If true, reorders the pool so that the token becomes the primary token for all metrics (default: false)
     *  - int $limit: Number of items per page (default: 10, max: 100)
     *  - string $orderBy: Field to order by (default: 'volume_usd')
     *  - string $sort: Sort order (default: 'desc')
     *  - int $maxPages: Maximum number of pages to fetch (default: 10, use 0 for unlimited)
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return int Total number of pages fetched
     */
    public function fetchAllTokenPools(string $networkId, string $tokenAddress, callable $callback, array $options = []): int
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
            $response = $this->getTokenPools($networkId, $tokenAddress, array_merge($apiOptions, ['asObject' => $asObject]));
            
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
            } else {
                if (!isset($response['pools']) || count($response['pools']) < $limit) {
                    break;
                }
            }
            
        } while (true);
        
        return $totalPages;
    }

    /**
     * Transform the tokens response
     *
     * @param array<string, mixed> $response The API response array
     * @param bool $asObject Whether to transform the response to an object
     * @return array<string, mixed>|object The transformed response
     */
    protected function transformResponse(array $response, bool $asObject = false)
    {
        if ($asObject) {
            return ResponseTransformer::transformTokens($response);
        }
        
        return $response;
    }
} 
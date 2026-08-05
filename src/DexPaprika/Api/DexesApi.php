<?php

namespace DexPaprika\Api;

use DexPaprika\Exception\NotFoundException;
use DexPaprika\Utils\ResponseTransformer;
use DexPaprika\Utils\SearchParams;

class DexesApi extends BaseApi
{
    /**
     * Get a list of available DEXes on a specific network
     *
     * @param string $networkId The network ID (e.g., 'ethereum', 'solana')
     * @param array<string, mixed> $options Additional options
     *                            - int $page: Page number (default: 0)
     *                            - int $limit: Number of items per page (default: 10, max: 100)
     *                            - string $sort: Sort order ('asc' or 'desc')
     *                            - string $orderBy: Field to order by
     * @return array<string, mixed>|object The response containing DEXes and pagination info
     * @throws DexPaprikaApiException If the API request fails
     */
    public function getNetworkDexes(string $networkId, array $options = [])
    {
        $this->validateRequired(['networkId' => $networkId], ['networkId']);
        
        $queryParams = $this->buildQueryParams($options, [
            'page' => 'page',
            'limit' => 'limit',
            'sort' => 'sort',
            'orderBy' => 'order_by',
        ]);
        
        $data = $this->get("/networks/{$networkId}/dexes", $queryParams);
        
        if ($this->transformResponses) {
            return ResponseTransformer::transformDexes($data);
        }
        
        return $data;
    }
    
    /**
     * List DEXes on a specific network (alias with more consistent naming)
     *
     * @param string $networkId The network ID (e.g., 'ethereum', 'solana')
     * @param array<string, mixed> $options Additional options
     * @return array<string, mixed>|object The response containing DEXes and pagination info
     * @throws DexPaprikaApiException If the API request fails
     */
    public function listByNetwork(string $networkId, array $options = [])
    {
        return $this->getNetworkDexes($networkId, $options);
    }
    
    /**
     * Get top pools on a specific DEX within a network
     *
     * Backed by the unified /networks/{network}/pools/search endpoint with a
     * dex_name filter. The dedicated /networks/{network}/dexes/{dex}/pools
     * endpoint was removed by DexPaprika and returns 410 Gone.
     *
     * The response is cursor-paginated and shaped as
     * {@code results[], has_next_page, next_cursor, query}. Each pool exposes
     * id (pool address), chain, dex_id, dex_name, fee, created_at,
     * created_at_block_number, volume_usd_24h/7d/30d, liquidity_usd,
     * transactions_24h, price_usd, price_change_percentage_5m/1h/6h/24h and
     * tokens[]. There is no bare volume_usd and no page_info.
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $dexId DEX identifier. Sent as the dex_name query parameter,
     *                      which resolves both the id ('curve') and the display
     *                      name ('Curve'). Prefer the id.
     * @param array<string, mixed> $options Additional options:
     *  - int $page: Accepted for backward compatibility but ignored (the endpoint is cursor-based)
     *  - string $cursor: Opaque cursor from a previous response's next_cursor
     *  - int $limit: Number of items per page (default: 10, max: 100)
     *  - string $orderBy: Field to order by (legacy values are mapped, default: 'volume_usd_24h')
     *  - string $sort: Sort direction ('asc' or 'desc', default: 'desc')
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object Pools on the DEX (results[] + has_next_page + next_cursor)
     * @throws ValidationException If parameters are invalid
     */
    public function getDexPools(string $networkId, string $dexId, array $options = [])
    {
        // Validate required parameters
        if (empty($networkId) || trim($networkId) === '') {
            throw new \DexPaprika\Exception\ValidationException('Network ID is required and cannot be empty');
        }

        if (empty($dexId) || trim($dexId) === '') {
            throw new \DexPaprika\Exception\ValidationException('DEX ID is required and cannot be empty');
        }

        // Validate limit parameter
        if (isset($options['limit']) && ($options['limit'] < 1 || $options['limit'] > 100)) {
            throw new \DexPaprika\Exception\ValidationException('Limit must be between 1 and 100');
        }

        // The DEX moves out of the path and into the dex_name query parameter.
        $params = [
            'dex_name' => $dexId,
        ];

        if (isset($options['limit'])) {
            $params['limit'] = $options['limit'];
        }

        if (isset($options['orderBy'])) {
            $params['order_by'] = SearchParams::mapPoolSortField($options['orderBy']);
        }

        if (isset($options['sort'])) {
            $params['sort'] = $options['sort'];
        }

        if (isset($options['cursor'])) {
            $params['cursor'] = $options['cursor'];
        }

        // Use get method instead of direct client access
        $endpoint = "/networks/{$networkId}/pools/search";
        $response = $this->get($endpoint, $params);

        return $this->transformResponse($response, $options['asObject'] ?? false);
    }

    /**
     * List pools on a specific DEX (alias for getDexPools)
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $dexId DEX identifier, sent as the dex_name query parameter
     * @param array<string, mixed> $options Additional options:
     *  - int $page: Accepted for backward compatibility but ignored (the endpoint is cursor-based)
     *  - string $cursor: Opaque cursor from a previous response's next_cursor
     *  - int $limit: Number of items per page (default: 10, max: 100)
     *  - string $orderBy: Field to order by (legacy values are mapped, default: 'volume_usd_24h')
     *  - string $sort: Sort direction ('asc' or 'desc', default: 'desc')
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object Pools on the DEX (results[] + has_next_page + next_cursor)
     */
    public function listDexPools(string $networkId, string $dexId, array $options = [])
    {
        return $this->getDexPools($networkId, $dexId, $options);
    }

    /**
     * Fetch all pools from a DEX page by page using a callback function
     *
     * Walks the cursor-paginated /networks/{network}/pools/search endpoint,
     * threading next_cursor between requests and stopping when has_next_page is
     * false. The page counter handed to the callback is a running index, not a
     * page number sent to the API.
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $dexId DEX identifier, sent as the dex_name query parameter
     * @param callable $callback Function to call for each page of pools: function(array|object $pools, int $page): bool
     *                          Return false from the callback to stop pagination
     * @param array<string, mixed> $options Additional options:
     *  - string $cursor: Opaque cursor to start from (optional)
     *  - int $limit: Number of items per page (default: 10, max: 100)
     *  - string $orderBy: Field to order by (legacy values are mapped, default: 'volume_usd_24h')
     *  - string $sort: Sort direction ('asc' or 'desc', default: 'desc')
     *  - int $maxPages: Maximum number of pages to fetch (default: 10, use 0 for unlimited)
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return int Total number of pages fetched
     */
    public function fetchAllDexPools(string $networkId, string $dexId, callable $callback, array $options = []): int
    {
        $page = 0;
        $totalPages = 0;
        $maxPages = $options['maxPages'] ?? 10;

        // Set asObject and remove iterator-only keys before passing to the API
        $asObject = $options['asObject'] ?? false;
        $apiOptions = $options;
        unset($apiOptions['maxPages'], $apiOptions['asObject'], $apiOptions['cursor']);

        $cursor = $options['cursor'] ?? null;

        do {
            $callOptions = array_merge($apiOptions, ['asObject' => $asObject]);
            if ($cursor !== null && $cursor !== '') {
                $callOptions['cursor'] = $cursor;
            }

            $response = $this->getDexPools($networkId, $dexId, $callOptions);

            $continueProcessing = $callback($response, $page);
            $totalPages++;
            $page++;

            // Check if we should continue processing
            if ($continueProcessing === false) {
                break;
            }

            // Check if we've reached the maximum number of pages
            if ($maxPages > 0 && $page >= $maxPages) {
                break;
            }

            // Determine the next cursor from the search response shape
            if ($asObject) {
                $hasNext = $response->has_next_page ?? false;
                $cursor = $response->next_cursor ?? null;
            } else {
                $hasNext = $response['has_next_page'] ?? false;
                $cursor = $response['next_cursor'] ?? null;
            }

            if (!$hasNext || $cursor === null || $cursor === '') {
                break;
            }
        } while (true);

        return $totalPages;
    }

    /**
     * Transform the dexes response
     *
     * @param array<string, mixed> $response The API response array
     * @param bool $asObject Whether to transform the response to an object
     * @return array<string, mixed>|object The transformed response
     */
    protected function transformResponse(array $response, bool $asObject = false)
    {
        if ($asObject) {
            return ResponseTransformer::transformDexes($response);
        }
        
        return $response;
    }
} 
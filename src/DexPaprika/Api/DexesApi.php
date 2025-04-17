<?php

namespace DexPaprika\Api;

use DexPaprika\Exception\NotFoundException;
use DexPaprika\Utils\ResponseTransformer;

class DexesApi extends BaseApi
{
    /**
     * Get a list of available DEXes on a specific network
     *
     * @param string $networkId The network ID (e.g., 'ethereum', 'solana')
     * @param array<string, mixed> $options Additional options
     *                            - int $page: Page number (default: 0)
     *                            - int $limit: Number of items per page (default: 10)
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
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $dexId DEX identifier 
     * @param array<string, mixed> $options Additional options:
     *  - int $page: Page number for pagination (default: 0)
     *  - int $limit: Number of items per page (default: 10)
     *  - string $orderBy: Field to order by (default: 'volume_usd')
     *  - string $sort: Sort order (default: 'desc')
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object List of pools on the DEX
     */
    public function getDexPools(string $networkId, string $dexId, array $options = [])
    {
        $params = [
            'network' => $networkId,
            'dex' => $dexId,
        ];

        if (isset($options['page'])) {
            $params['page'] = $options['page'];
        }

        if (isset($options['limit'])) {
            $params['limit'] = $options['limit'];
        }

        if (isset($options['orderBy'])) {
            $params['orderBy'] = $options['orderBy'];
        }

        if (isset($options['sort'])) {
            $params['sort'] = $options['sort'];
        }

        // Use get method instead of direct client access
        $endpoint = "/networks/{$networkId}/dexes/{$dexId}/pools";
        $response = $this->get($endpoint, $params);
        
        return $this->transformResponse($response, $options['asObject'] ?? false);
    }

    /**
     * List pools on a specific DEX (alias for getDexPools)
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $dexId DEX identifier
     * @param array<string, mixed> $options Additional options:
     *  - int $page: Page number for pagination (default: 0)
     *  - int $limit: Number of items per page (default: 10)
     *  - string $orderBy: Field to order by (default: 'volume_usd')
     *  - string $sort: Sort order (default: 'desc')
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object List of pools on the DEX
     */
    public function listDexPools(string $networkId, string $dexId, array $options = [])
    {
        return $this->getDexPools($networkId, $dexId, $options);
    }

    /**
     * Fetch all pools from a DEX page by page using a callback function
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $dexId DEX identifier
     * @param callable $callback Function to call for each page of pools: function(array|object $pools, int $page): bool
     *                          Return false from the callback to stop pagination
     * @param array<string, mixed> $options Additional options:
     *  - int $limit: Number of items per page (default: 10)
     *  - string $orderBy: Field to order by (default: 'volume_usd')
     *  - string $sort: Sort order (default: 'desc')
     *  - int $maxPages: Maximum number of pages to fetch (default: 10, use 0 for unlimited)
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return int Total number of pages fetched
     */
    public function fetchAllDexPools(string $networkId, string $dexId, callable $callback, array $options = []): int
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
            $response = $this->getDexPools($networkId, $dexId, array_merge($apiOptions, ['asObject' => $asObject]));
            
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
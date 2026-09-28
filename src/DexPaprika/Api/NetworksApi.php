<?php

namespace DexPaprika\Api;

use DexPaprika\Utils\ResponseTransformer;

class NetworksApi extends BaseApi
{
    /**
     * Get a list of all supported blockchain networks
     *
     * @param array<string, mixed> $options Additional options:
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object List of supported networks
     */
    public function getNetworks(array $options = [])
    {
        $response = $this->get('/networks', []);
        
        return $this->transformResponse($response, $options['asObject'] ?? false);
    }

    /**
     * Find a network by its ID
     * 
     * @param string $networkId Network ID to find
     * @param bool $asObject Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object|null The network information or null if not found
     */
    public function findNetwork(string $networkId, bool $asObject = false)
    {
        // GET /networks is a plain list. Search the array form and convert only
        // the match, so object mode does not depend on how a list transforms.
        $networks = $this->getNetworks();
        $list = $networks['networks'] ?? $networks;

        foreach ($list as $network) {
            if (is_array($network) && ($network['id'] ?? null) === $networkId) {
                return $asObject ? ResponseTransformer::transform($network) : $network;
            }
        }

        return null;
    }

    /**
     * Get a list of available decentralized exchanges on a specific network
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param array<string, mixed> $options Additional options:
     *  - int $page: Page number for pagination (default: 0)
     *  - int $limit: Number of items per page (default: 10)
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object List of DEXes on the network
     */
    public function getNetworkDexes(string $networkId, array $options = [])
    {
        $this->validateRequired(['networkId' => $networkId], ['networkId']);

        $params = [];

        if (isset($options['page'])) {
            $params['page'] = $options['page'];
        }

        if (isset($options['limit'])) {
            $params['limit'] = $options['limit'];
        }

        $response = $this->get("/networks/{$networkId}/dexes", $params);

        return $this->transformResponse($response, $options['asObject'] ?? false);
    }

    /**
     * List available DEXes on a specific network (alias for getNetworkDexes)
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param array<string, mixed> $options Additional options:
     *  - int $page: Page number for pagination (default: 0)
     *  - int $limit: Number of items per page (default: 10)
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object List of DEXes on the network
     */
    public function listNetworkDexes(string $networkId, array $options = [])
    {
        return $this->getNetworkDexes($networkId, $options);
    }

    /**
     * Find a DEX by its ID on a specific network
     * 
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $dexId DEX ID to find
     * @param bool $asObject Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object|null The DEX information or null if not found
     */
    public function findDex(string $networkId, string $dexId, bool $asObject = false)
    {
        $dexes = $this->getNetworkDexes($networkId);

        foreach ($dexes['dexes'] ?? [] as $dex) {
            // Rows carry dex_id (e.g. 'uniswap_v3') and dex_name ('Uniswap V3');
            // 'id' is kept for older response shapes.
            $id = $dex['dex_id'] ?? $dex['id'] ?? null;
            if ($id === $dexId) {
                return $asObject ? ResponseTransformer::transform($dex) : $dex;
            }
        }

        return null;
    }

    /**
     * Fetch all DEXes from a network page by page using a callback function
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param callable $callback Function to call for each page of DEXes: function(array|object $dexes, int $page): bool
     *                          Return false from the callback to stop pagination
     * @param array<string, mixed> $options Additional options:
     *  - int $limit: Number of items per page (default: 10)
     *  - int $maxPages: Maximum number of pages to fetch (default: 10, use 0 for unlimited)
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return int Total number of pages fetched
     */
    public function fetchAllNetworkDexes(string $networkId, callable $callback, array $options = []): int
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
            $response = $this->getNetworkDexes($networkId, array_merge($apiOptions, ['asObject' => $asObject]));
            
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
            
            // Check if we've reached the end of available DEXes
            $rows = $asObject ? ($response->dexes ?? null) : ($response['dexes'] ?? null);
            if ($rows === null || count($rows) < $limit) {
                break;
            }

            // The endpoint currently ignores page and limit and returns the whole
            // list with total_pages 0. Without a page count there is no next page;
            // asking again would fetch the same list until maxPages (forever at 0).
            $pageInfo = $asObject ? ($response->page_info ?? null) : ($response['page_info'] ?? null);
            $pageCount = (int) (is_object($pageInfo) ? ($pageInfo->total_pages ?? 0) : ($pageInfo['total_pages'] ?? 0));
            if ($pageCount <= 0 || $page >= $pageCount) {
                break;
            }
            
        } while (true);
        
        return $totalPages;
    }

    /**
     * Transform the networks response
     *
     * @param array<string, mixed> $response The API response array
     * @param bool $asObject Whether to transform the response to an object
     * @return array<string, mixed>|object The transformed response
     */
    protected function transformResponse(array $response, bool $asObject = false)
    {
        if ($asObject) {
            return ResponseTransformer::transformNetworks($response);
        }
        
        return $response;
    }
} 
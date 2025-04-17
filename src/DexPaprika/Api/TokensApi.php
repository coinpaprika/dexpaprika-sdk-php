<?php

namespace DexPaprika\Api;

use DexPaprika\Exception\NotFoundException;
use DexPaprika\Utils\ResponseTransformer;

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
     *  - int $page: Page number for pagination (default: 0)
     *  - int $limit: Number of items per page (default: 10)
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

        $response = $this->get("/tokens/$networkId/$tokenAddress/pools", $params);
        
        return $this->transformResponse($response, $options['asObject'] ?? false);
    }

    /**
     * List pools for a specific token (alias for getTokenPools)
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $tokenAddress Token address or identifier
     * @param array<string, mixed> $options Additional options:
     *  - string $address: Filter pools that contain this additional token address
     *  - int $page: Page number for pagination (default: 0)
     *  - int $limit: Number of items per page (default: 10)
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
     * Fetch all pools containing a specific token using a callback function
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $tokenAddress Token address or identifier
     * @param callable $callback Function to call for each page of pools: function(array|object $pools, int $page): bool
     *                          Return false from the callback to stop pagination
     * @param array<string, mixed> $options Additional options:
     *  - string $address: Filter pools that contain this additional token address
     *  - int $limit: Number of items per page (default: 10)
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
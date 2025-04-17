<?php

namespace DexPaprika\Api;

use DexPaprika\Utils\ResponseTransformer;

class TransactionsApi extends BaseApi
{
    /**
     * Get transactions of a pool on a network
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $poolAddress Pool address or identifier
     * @param array<string, mixed> $options Additional options:
     *  - int $limit: Number of items per page (default: 10)
     *  - int $page: Page number for pagination (default: 0)
     *  - string $cursor: Transaction ID used for cursor-based pagination
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object The pool transactions response
     */
    public function getPoolTransactions(string $networkId, string $poolAddress, array $options = [])
    {
        $params = [
            'network' => $networkId,
            'poolAddress' => $poolAddress,
        ];

        if (isset($options['limit'])) {
            $params['limit'] = $options['limit'];
        }

        if (isset($options['page'])) {
            $params['page'] = $options['page'];
        }

        if (isset($options['cursor'])) {
            $params['cursor'] = $options['cursor'];
        }

        $response = $this->get("/networks/{$networkId}/pools/{$poolAddress}/transactions", $params);
        
        return $this->transformResponse($response, $options['asObject'] ?? false);
    }

    /**
     * List transactions of a pool on a network (alias for getPoolTransactions)
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $poolAddress Pool address or identifier
     * @param array<string, mixed> $options Additional options:
     *  - int $limit: Number of items per page (default: 10)
     *  - int $page: Page number for pagination (default: 0)
     *  - string $cursor: Transaction ID used for cursor-based pagination
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object The pool transactions response
     */
    public function listPoolTransactions(string $networkId, string $poolAddress, array $options = [])
    {
        return $this->getPoolTransactions($networkId, $poolAddress, $options);
    }

    /**
     * Get the most recent transactions of a pool
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $poolAddress Pool address or identifier
     * @param int $limit Number of recent transactions to fetch (default: 10)
     * @param bool $asObject Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object The pool transactions response
     */
    public function getRecentTransactions(string $networkId, string $poolAddress, int $limit = 10, bool $asObject = false)
    {
        return $this->getPoolTransactions($networkId, $poolAddress, [
            'limit' => $limit,
            'page' => 0,
            'asObject' => $asObject
        ]);
    }

    /**
     * Fetch all transactions page by page using a callback function
     *
     * @param string $networkId Network ID (e.g., ethereum, solana)
     * @param string $poolAddress Pool address or identifier
     * @param callable $callback Function to call for each page of transactions: function(array|object $transactions, int $page): bool
     *                          Return false from the callback to stop pagination
     * @param int $limit Number of items per page (default: 10)
     * @param int $maxPages Maximum number of pages to fetch (default: 10, use 0 for unlimited)
     * @param bool $asObject Whether to return the response as an object (default: false)
     * @return int Total number of pages fetched
     */
    public function fetchAllTransactions(
        string $networkId, 
        string $poolAddress, 
        callable $callback, 
        int $limit = 10, 
        int $maxPages = 10,
        bool $asObject = false
    ): int {
        $page = 0;
        $totalPages = 0;
        
        do {
            $response = $this->getPoolTransactions($networkId, $poolAddress, [
                'limit' => $limit,
                'page' => $page,
                'asObject' => $asObject
            ]);
            
            $totalPages++;
            
            // Call the callback and check if we should continue
            $continueProcessing = $callback($response, $page);
            if ($continueProcessing === false) {
                return $totalPages;
            }
            
            $page++;
            
            // Check if we've reached the maximum number of pages
            if ($maxPages > 0 && $page >= $maxPages) {
                break;
            }
            
            // Check if we've reached the end of available pages
            if ($asObject) {
                if (!isset($response->transactions) || count($response->transactions) < $limit) {
                    break;
                }
            } else {
                if (!isset($response['transactions']) || count($response['transactions']) < $limit) {
                    break;
                }
            }
            
        } while (true);
        
        return $totalPages;
    }

    /**
     * Transform the transactions response
     *
     * @param array<string, mixed> $response The API response array
     * @param bool $asObject Whether to transform the response to an object
     * @return array<string, mixed>|object The transformed response
     */
    protected function transformResponse(array $response, bool $asObject = false)
    {
        if ($asObject) {
            return ResponseTransformer::transformTransactions($response);
        }
        
        return $response;
    }
} 
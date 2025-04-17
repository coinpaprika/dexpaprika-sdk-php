<?php

namespace DexPaprika\Api;

use DexPaprika\Exception\DexPaprikaApiException;
use DexPaprika\Utils\ResponseTransformer;

class SearchApi extends BaseApi
{
    /**
     * Search for tokens, pools, and DEXes by name or identifier
     *
     * @param string $query Search term (e.g., "uniswap", "bitcoin", or a token address)
     * @param array<string, mixed> $options Additional options:
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object Search results containing tokens, pools, and dexes
     */
    public function search(string $query, array $options = [])
    {
        $params = [
            'query' => $query,
        ];

        $response = $this->get('/search', $params);
        
        return $this->transformResponse($response, $options['asObject'] ?? false);
    }

    /**
     * Transform the search response
     *
     * @param array<string, mixed> $response The API response array
     * @param bool $asObject Whether to transform the response to an object
     * @return array<string, mixed>|object The transformed response
     */
    protected function transformResponse(array $response, bool $asObject = false)
    {
        if ($asObject) {
            return ResponseTransformer::transformSearch($response);
        }
        
        return $response;
    }
} 
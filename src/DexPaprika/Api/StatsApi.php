<?php

namespace DexPaprika\Api;

use DexPaprika\Utils\ResponseTransformer;

class StatsApi extends BaseApi
{
    /**
     * Get high-level statistics about the DexPaprika ecosystem
     *
     * @param array<string, mixed> $options Additional options:
     *  - bool $asObject: Whether to return the response as an object (default: false)
     * @return array<string, mixed>|object Ecosystem statistics including volume, transaction count, and more
     */
    public function getStats(array $options = [])
    {
        $response = $this->get('/stats');
        
        return $this->transformResponse($response, $options['asObject'] ?? false);
    }

    /**
     * Transform the stats response
     *
     * @param array<string, mixed> $response The API response array
     * @param bool $asObject Whether to transform the response to an object
     * @return array<string, mixed>|object The transformed response
     */
    protected function transformResponse(array $response, bool $asObject = false)
    {
        if ($asObject) {
            return ResponseTransformer::transformStats($response);
        }
        
        return $response;
    }
} 
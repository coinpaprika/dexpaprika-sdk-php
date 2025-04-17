<?php

namespace DexPaprika\Api;

use DexPaprika\Exception\DexPaprikaApiException;

class UtilsApi extends BaseApi
{
    /**
     * Get high-level statistics about the DexPaprika ecosystem
     *
     * @return array<string, mixed> Statistics about chains, factories, pools, and tokens
     * @throws DexPaprikaApiException If the API request fails
     */
    public function getStats(): array
    {
        return $this->get('/stats');
    }
} 
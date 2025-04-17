<?php

namespace DexPaprika\Utils;

use DexPaprika\Exception\DexPaprikaApiException;

class Paginator
{
    /**
     * @var object The API instance
     */
    private object $api;
    
    /**
     * @var string The method name to call
     */
    private string $method;
    
    /**
     * @var array<string, mixed> The method parameters
     */
    private array $params;
    
    /**
     * @var int Current page number
     */
    private int $currentPage = 0;
    
    /**
     * @var bool Whether there is a next page
     */
    private bool $hasNext = true;
    
    /**
     * @var array<string, mixed>|null The current page result
     */
    private ?array $currentResult = null;
    
    /**
     * Create a new paginator
     *
     * @param object $api The API instance
     * @param string $method The method name to call
     * @param array<string, mixed> $params The method parameters
     */
    public function __construct(object $api, string $method, array $params = [])
    {
        $this->api = $api;
        $this->method = $method;
        $this->params = $params;
    }
    
    /**
     * Check if there is a next page
     *
     * @return bool
     */
    public function hasNextPage(): bool
    {
        return $this->hasNext;
    }
    
    /**
     * Get the next page of results
     *
     * @return array<string, mixed> The page results
     * @throws DexPaprikaApiException If the API request fails
     */
    public function getNextPage(): array
    {
        // Add page parameter to method params
        $params = array_merge($this->params, ['page' => $this->currentPage]);
        
        // Build arguments array from parameters (handling positional parameters)
        $args = [];
        $reflectionMethod = new \ReflectionMethod($this->api, $this->method);
        $requiredCount = 0;
        
        foreach ($reflectionMethod->getParameters() as $param) {
            if (!$param->isOptional()) {
                $requiredCount++;
            }
            
            $name = $param->getName();
            if (isset($params[$name])) {
                $args[] = $params[$name];
                unset($params[$name]);
            } elseif (!$param->isOptional()) {
                throw new DexPaprikaApiException("Missing required parameter: {$name}");
            }
        }
        
        // Add remaining parameters as options array if the last parameter expects an array
        if (!empty($params)) {
            $lastParam = $reflectionMethod->getParameters()[count($reflectionMethod->getParameters()) - 1] ?? null;
            if ($lastParam && $lastParam->getType() && $lastParam->getType()->getName() === 'array') {
                $args[] = $params;
            }
        }
        
        // Call the method with the arguments
        $result = $this->api->{$this->method}(...$args);
        $this->currentResult = $result;
        
        // Check if there are more pages
        $this->currentPage++;
        $pageInfo = $result['page_info'] ?? null;
        if ($pageInfo) {
            $this->hasNext = $this->currentPage < ($pageInfo['total_pages'] ?? 0);
        } else {
            $this->hasNext = false;
        }
        
        return $result;
    }
    
    /**
     * Get the current page result
     *
     * @return array<string, mixed>|null The current page result
     */
    public function getCurrentResult(): ?array
    {
        return $this->currentResult;
    }
    
    /**
     * Get all results by iterating through all pages
     *
     * @param int $maxPages Maximum number of pages to fetch (0 for all pages)
     * @param callable|null $callback Optional callback function to process each page
     * @return array<int, array<string, mixed>> All results
     * @throws DexPaprikaApiException If the API request fails
     */
    public function getAllResults(int $maxPages = 0, ?callable $callback = null): array
    {
        $allResults = [];
        $pageCount = 0;
        
        while ($this->hasNextPage() && ($maxPages === 0 || $pageCount < $maxPages)) {
            $result = $this->getNextPage();
            
            if ($callback) {
                $callback($result, $pageCount);
            }
            
            // Extract items from the result based on common patterns
            $items = null;
            foreach (['pools', 'tokens', 'dexes', 'transactions'] as $key) {
                if (isset($result[$key]) && is_array($result[$key])) {
                    $items = $result[$key];
                    break;
                }
            }
            
            if ($items) {
                $allResults = array_merge($allResults, $items);
            } else {
                // If no recognizable pattern, just add the whole result
                $allResults[] = $result;
            }
            
            $pageCount++;
        }
        
        return $allResults;
    }
} 
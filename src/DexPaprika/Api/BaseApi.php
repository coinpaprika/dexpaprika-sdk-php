<?php

namespace DexPaprika\Api;

use DexPaprika\Exception\DexPaprikaApiException;
use DexPaprika\Utils\ResponseTransformer;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use DexPaprika\Config;
use DexPaprika\Exception\NetworkException;
use DexPaprika\Exception\ValidationException;
use DexPaprika\Exception\AuthenticationException;
use DexPaprika\Exception\RateLimitException;
use DexPaprika\Exception\NotFoundException;
use DexPaprika\Exception\ServerException;
use DexPaprika\Exception\ClientException;

abstract class BaseApi
{
    /**
     * @var ClientInterface HTTP client
     */
    protected ClientInterface $httpClient;
    
    /**
     * @var bool Whether to transform API responses
     */
    protected bool $transformResponses;

    /**
     * @var Config Configuration
     */
    protected Config $config;
    
    /**
     * @var LoggerInterface Logger instance
     */
    protected LoggerInterface $logger;

    /**
     * BaseApi constructor.
     *
     * @param ClientInterface $httpClient
     * @param bool $transformResponses Whether to transform API responses
     * @param Config|null $config Configuration (optional)
     * @param LoggerInterface|null $logger Logger instance (optional)
     */
    public function __construct(
        ClientInterface $httpClient,
        bool $transformResponses = false,
        ?Config $config = null,
        ?LoggerInterface $logger = null
    ) {
        $this->httpClient = $httpClient;
        $this->transformResponses = $transformResponses;
        $this->config = $config ?? new Config();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Make a GET request to the API
     *
     * @param string $endpoint The API endpoint
     * @param array<string, mixed> $queryParams Query parameters
     * @return array<string, mixed>|object The response data
     * @throws DexPaprikaApiException If the API request fails
     */
    protected function get(string $endpoint, array $queryParams = [])
    {
        return $this->request('GET', $endpoint, $queryParams);
    }

    /**
     * Process an API response
     *
     * @param ResponseInterface $response The HTTP response
     * @return array<string, mixed>|object The response data
     * @throws DexPaprikaApiException If the response cannot be processed
     */
    protected function processResponse(ResponseInterface $response)
    {
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new DexPaprikaApiException(
                'Failed to decode JSON response: ' . json_last_error_msg()
            );
        }
        
        // Apply transformation if enabled
        if ($this->transformResponses) {
            return $this->transformResponse($data);
        }
        
        return $data;
    }
    
    /**
     * Transform an API response based on its content
     *
     * @param array<string, mixed> $data The response data
     * @param bool $asObject Whether to return the data as an object
     * @return array|object The transformed data
     */
    protected function transformResponse(array $data, bool $asObject = false)
    {
        if (!$asObject) {
            return $data;
        }
        
        // When asObject is true, transform the data to an object
        // Try to determine the response type based on the data structure
        if (isset($data['dexes'])) {
            return \DexPaprika\Utils\ResponseTransformer::transformDexes($data);
        } elseif (isset($data['pools'])) {
            return \DexPaprika\Utils\ResponseTransformer::transformPools($data);
        } elseif (isset($data['transactions'])) {
            return \DexPaprika\Utils\ResponseTransformer::transformTransactions($data);
        } elseif (isset($data['tokens'])) {
            return \DexPaprika\Utils\ResponseTransformer::transformTokens($data);
        } elseif (isset($data['token'])) {
            return \DexPaprika\Utils\ResponseTransformer::transformToken($data);
        } elseif (isset($data['ohlcv'])) {
            return \DexPaprika\Utils\ResponseTransformer::transformOHLCV($data);
        } elseif (isset($data['stats'])) {
            return \DexPaprika\Utils\ResponseTransformer::transformStats($data);
        }
        
        // Default transformation
        return \DexPaprika\Utils\ResponseTransformer::transform($data);
    }

    /**
     * Build query parameters from an options array
     *
     * @param array<string, mixed> $options The options array
     * @param array<string, string> $mapping Mapping from option keys to query parameter keys
     * @return array<string, mixed> The query parameters
     */
    protected function buildQueryParams(array $options, array $mapping = []): array
    {
        $queryParams = [];
        
        foreach ($options as $key => $value) {
            if ($value === null) {
                continue;
            }
            
            $paramKey = $mapping[$key] ?? $key;
            $queryParams[$paramKey] = $value;
        }
        
        return $queryParams;
    }

    /**
     * Validate the required parameters
     *
     * @param array<string, mixed> $params The parameters to validate
     * @param array<string> $required The required parameter keys
     * @throws DexPaprikaApiException If any required parameter is missing
     */
    protected function validateRequired(array $params, array $required): void
    {
        foreach ($required as $key) {
            if (!isset($params[$key]) || $params[$key] === '') {
                throw new DexPaprikaApiException("Required parameter '$key' is missing or empty");
            }
        }
    }
    
    /**
     * Validate that a value is one of the allowed values
     *
     * @param mixed $value The value to validate
     * @param array<mixed> $allowed The allowed values
     * @param string $paramName The parameter name for error messages
     * @throws DexPaprikaApiException If the value is not allowed
     */
    protected function validateAllowed($value, array $allowed, string $paramName): void
    {
        if (!in_array($value, $allowed)) {
            throw new DexPaprikaApiException(
                "Invalid $paramName. Must be one of: " . implode(', ', $allowed)
            );
        }
    }
    
    /**
     * Check if response transformation is enabled
     * 
     * @return bool
     */
    public function isTransformingResponses(): bool
    {
        return $this->transformResponses;
    }
    
    /**
     * Enable or disable response transformation
     * 
     * @param bool $enable
     * @return $this
     */
    public function setTransformResponses(bool $enable): self
    {
        $this->transformResponses = $enable;
        return $this;
    }

    /**
     * Generate a cache key for a request
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array<string, mixed> $params Request parameters
     * @return string Cache key
     */
    protected function generateCacheKey(string $method, string $endpoint, array $params): string
    {
        // Sort parameters to ensure consistent keys regardless of order
        if (!empty($params)) {
            ksort($params);
        }
        
        return md5($method . '|' . $endpoint . '|' . json_encode($params));
    }
    
    /**
     * Send a request to the API with retry capabilities and caching
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $endpoint API endpoint
     * @param array<string, mixed> $params Request parameters
     * @return array<string, mixed> API response
     * @throws DexPaprikaApiException If the request fails after all retries
     */
    protected function request(string $method, string $endpoint, array $params = []): array
    {
        // Check if we can use cache for this request
        $canUseCache = $method === 'GET' && $this->config->isCacheEnabled();
        
        if ($canUseCache) {
            $cacheKey = $this->generateCacheKey($method, $endpoint, $params);
            $cache = $this->config->getCache();
            
            // Try to get from cache first
            if ($cache !== null && $cache->has($cacheKey)) {
                $this->logger->debug('Cache hit', ['endpoint' => $endpoint]);
                return $cache->get($cacheKey);
            }
        }
        
        $maxRetries = $this->config->getMaxRetries();
        $retryCount = 0;
        $lastException = null;
        
        while ($retryCount <= $maxRetries) {
            try {
                $options = [];
                
                if ($method === 'GET') {
                    $options['query'] = $params;
                } else {
                    $options['json'] = $params;
                }
                
                $response = $this->httpClient->request($method, $endpoint, $options);
                $data = json_decode($response->getBody()->getContents(), true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new DexPaprikaApiException('Invalid JSON response from API');
                }
                
                // Check for rate limit headers and log them
                if ($response->hasHeader('X-RateLimit-Limit') && $response->hasHeader('X-RateLimit-Remaining')) {
                    $this->logger->debug('Rate limit info', [
                        'limit' => $response->getHeaderLine('X-RateLimit-Limit'),
                        'remaining' => $response->getHeaderLine('X-RateLimit-Remaining'),
                        'reset' => $response->getHeaderLine('X-RateLimit-Reset')
                    ]);
                }
                
                // Store successful response in cache if caching is enabled
                if ($canUseCache && !empty($data)) {
                    $cache = $this->config->getCache();
                    if ($cache !== null) {
                        $ttl = $this->config->getCacheTtl();
                        $cache->set($cacheKey, $data, $ttl);
                        $this->logger->debug('Cached response', ['endpoint' => $endpoint, 'ttl' => $ttl]);
                    }
                }
                
                return $data;
            } catch (ConnectException $e) {
                $lastException = $e;
                $shouldRetry = true;
                $this->logger->warning('Connection failed', ['exception' => $e->getMessage(), 'attempt' => $retryCount + 1]);
            } catch (RequestException $e) {
                $response = $e->getResponse();
                $statusCode = $response ? $response->getStatusCode() : 0;
                
                // Only retry on server errors (5xx) and rate limiting (429)
                $shouldRetry = ($statusCode >= 500 || $statusCode === 429);
                
                if ($response) {
                    try {
                        $errorData = json_decode($response->getBody()->getContents(), true);
                    } catch (\Exception $jsonException) {
                        $errorData = null;
                    }
                    
                    $lastException = $this->createExceptionFromResponse($statusCode, $errorData);
                    
                    $logLevel = $shouldRetry ? 'warning' : 'error';
                    $this->logger->log($logLevel, 'API error', [
                        'status' => $statusCode,
                        'endpoint' => $endpoint,
                        'error' => $errorData['error'] ?? 'Unknown error',
                        'attempt' => $retryCount + 1
                    ]);
                } else {
                    $lastException = new NetworkException('API request failed: ' . $e->getMessage(), 0, null, $e);
                    $this->logger->warning('Network error', ['exception' => $e->getMessage(), 'attempt' => $retryCount + 1]);
                }
            } catch (\Exception $e) {
                $this->logger->error('Unexpected error', ['exception' => $e->getMessage()]);
                // Don't retry on other exceptions
                throw new DexPaprikaApiException('Unexpected error: ' . $e->getMessage(), 0, null, $e);
            }
            
            if (!$shouldRetry || $retryCount >= $maxRetries) {
                break;
            }
            
            // Get retry delay for current attempt with a small amount of jitter
            $jitter = mt_rand(-50, 50) / 1000; // ±50ms jitter
            $delayMs = $this->config->getRetryDelayForAttempt($retryCount) + ($jitter * 1000);
            
            $this->logger->info(sprintf(
                'Retrying API request to %s (attempt %d of %d) after %.0f ms',
                $endpoint,
                $retryCount + 1,
                $maxRetries,
                $delayMs
            ));
            
            // Sleep for calculated delay in microseconds
            usleep((int)($delayMs * 1000));
            
            $retryCount++;
        }
        
        // If we've exhausted all retries, throw the last exception
        if ($lastException instanceof DexPaprikaApiException) {
            throw $lastException;
        } else if ($lastException !== null) {
            throw new DexPaprikaApiException(
                'API request failed after ' . $maxRetries . ' retries',
                0,
                null,
                $lastException
            );
        } else {
            throw new DexPaprikaApiException('API request failed with unknown error');
        }
    }
    
    /**
     * Create an exception from an API response
     *
     * @param int $statusCode HTTP status code
     * @param array<string, mixed>|null $errorData Error data from response
     * @return DexPaprikaApiException The appropriate exception instance
     */
    protected function createExceptionFromResponse(int $statusCode, ?array $errorData = null): DexPaprikaApiException
    {
        $message = $errorData['error'] ?? 'Unknown error';
        
        return match (true) {
            $statusCode === 404 => new NotFoundException($message, $statusCode, $errorData),
            $statusCode === 401 => new AuthenticationException($message, $statusCode, $errorData),
            $statusCode === 429 => new RateLimitException($message, $statusCode, $errorData),
            $statusCode >= 500 => new ServerException($message, $statusCode, $errorData),
            $statusCode >= 400 => new ClientException($message, $statusCode, $errorData),
            default => new DexPaprikaApiException($message, $statusCode, $errorData),
        };
    }
}
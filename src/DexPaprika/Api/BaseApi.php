<?php

namespace DexPaprika\Api;

use DexPaprika\Exception\DexPaprikaApiException;
use DexPaprika\Utils\ResponseTransformer;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
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
use DexPaprika\Exception\DeprecationException;

abstract class BaseApi
{
    /**
     * Indicator values the technical-indicator endpoints accept.
     */
    protected const ALLOWED_INDICATORS = ['rsi', 'ema', 'macd', 'bbands', 'vwap'];

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
        // Carries a Retry-After the server sent on the attempt that just
        // failed, read fresh each iteration. Without it the client backs off
        // on its own schedule, which is far too short for a per-minute rate
        // limit: a 429 clearing in 12s loses every race against a ladder
        // that tops out around 5s.
        $serverDelayMs = null;

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
                $serverDelayMs = null;
                $this->logger->warning('Connection failed', ['exception' => $e->getMessage(), 'attempt' => $retryCount + 1]);
            } catch (RequestException $e) {
                // Guzzle 8 removed RequestException::getResponse(). Only
                // BadResponseException carries a response, so a timeout or a
                // transport failure has to fall through to the network branch
                // below instead of fataling here.
                $response = $e instanceof BadResponseException ? $e->getResponse() : null;
                $statusCode = $response ? $response->getStatusCode() : 0;

                // Only retry on server errors (5xx) and rate limiting (429).
                // Every other 4xx is deterministic: retrying only burns the
                // quota for the same answer.
                $shouldRetry = ($statusCode >= 500 || $statusCode === 429);
                $serverDelayMs = null;

                if ($response) {
                    try {
                        $rawBody = $response->getBody()->getContents();
                        $errorData = json_decode($rawBody, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $errorData = null;
                        }
                    } catch (\Exception $jsonException) {
                        $rawBody = '';
                        $errorData = null;
                    }

                    if ($shouldRetry) {
                        $serverDelayMs = self::retryAfterMs($response, $rawBody);
                    }

                    $lastException = $this->createExceptionFromResponse($statusCode, $errorData, $rawBody);

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

            // The server's own figure wins when it asked for longer than our
            // backoff. Retrying earlier than asked just spends another
            // request on the same 429.
            if ($serverDelayMs !== null && $serverDelayMs > $delayMs) {
                $delayMs = $serverDelayMs;
            }

            $this->logger->info(sprintf(
                'Retrying API request to %s (attempt %d of %d) after %.0f ms',
                $endpoint,
                $retryCount + 1,
                $maxRetries,
                $delayMs
            ));

            // Sleep for the calculated delay. Injectable through
            // Config::setSleeper() so tests do not really wait.
            ($this->config->getSleeper())((int) round($delayMs));

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
     * The message for a 403 whose body is not JSON.
     *
     * A key sent to api-pro.dexpaprika.com without the api_ prefix never
     * reaches the API: Cloudflare answers with its own HTML block page
     * ("Sorry, you have been blocked"), not the API's JSON error shape. That
     * body used to decode to null and fall through to "Unknown error". Never
     * raised when the body is valid JSON (for example the plan_required 403 on
     * keyless search paging), which keeps its own message untouched.
     */
    protected const KEY_REFUSED_BY_EDGE_MESSAGE = 'The API key was refused before it reached the API. '
        . 'Check that the whole key was pasted into the Authorization header with nothing in front of it. '
        . 'Keys are at https://console.dexpaprika.com.';

    /**
     * Create an exception from an API response
     *
     * @param int $statusCode HTTP status code
     * @param array<string, mixed>|null $errorData Error data from response
     * @param string|null $rawBody The raw response body, before any JSON decoding
     * @return DexPaprikaApiException The appropriate exception instance
     */
    protected function createExceptionFromResponse(int $statusCode, ?array $errorData = null, ?string $rawBody = null): DexPaprikaApiException
    {
        if ($statusCode === 403 && $errorData === null && $rawBody !== null && trim($rawBody) !== '') {
            return new ClientException(self::KEY_REFUSED_BY_EDGE_MESSAGE, $statusCode, null);
        }

        $message = self::errorText($errorData);

        // Generic deprecation self-documentation: any error whose body carries a
        // "replacement" hint (regardless of status code) gets that hint surfaced in
        // the message, so future deprecations document their own migration path.
        $replacement = null;
        if (isset($errorData['replacement']) && is_string($errorData['replacement']) && $errorData['replacement'] !== '') {
            $replacement = $errorData['replacement'];
            $message .= ' Use ' . $replacement . ' instead.';
        }

        $exception = match (true) {
            $statusCode === 404 => new NotFoundException($message, $statusCode, $errorData),
            $statusCode === 401 => new AuthenticationException($message, $statusCode, $errorData),
            $statusCode === 410 => new DeprecationException($message, $statusCode, $errorData),
            $statusCode === 429 => new RateLimitException($message, $statusCode, $errorData),
            $statusCode >= 500 => new ServerException($message, $statusCode, $errorData),
            $statusCode >= 400 => new ClientException($message, $statusCode, $errorData),
            default => new DexPaprikaApiException($message, $statusCode, $errorData),
        };

        // Enrich the typed deprecation error with a direct replacement accessor.
        if ($replacement !== null && $exception instanceof DeprecationException) {
            $exception->setReplacement($replacement);
        }

        return $exception;
    }

    /**
     * How long the server asked us to wait before retrying, in milliseconds,
     * or null when it did not say in a form we read.
     *
     * Two shapes, because the API uses both. A request carrying an API key
     * gets a standard `Retry-After` header (observed: "retry-after: 32"). A
     * keyless request gets a 429 with no such header and the delay only in
     * the JSON body (observed: {"error":"rate_limited","tier":"keyless",
     * "retry_after":12,...}). Checked header first since it is cheap and
     * always present when the API sends it; the body field is the documented
     * mirror of it.
     *
     * Retry-After also permits an HTTP-date form ("Wed, 21 Oct 2026
     * 07:28:00 GMT"). That form is deliberately not parsed: when the header
     * is present but not a plain integer, this returns null rather than the
     * body's value, and the caller falls back to its own backoff. A header
     * that did answer, just not in a form read here, is not the same as no
     * header at all.
     *
     * The accepted value is clamped to 60 seconds either way, so a hostile or
     * buggy figure cannot park a caller for an hour.
     *
     * @param ResponseInterface $response The response that carried the retryable error
     * @param string|null $rawBody The raw response body, before any JSON decoding
     * @return int|null Milliseconds to wait, or null
     */
    protected static function retryAfterMs(ResponseInterface $response, ?string $rawBody): ?int
    {
        $header = trim($response->getHeaderLine('Retry-After'));

        if ($header !== '') {
            if (preg_match('/^\d+$/', $header) === 1) {
                return self::clampServerWaitMs((int) $header * 1000);
            }

            // Present, but not a plain integer (an HTTP-date, most likely).
            // Do not parse it and do not fall back to the body either.
            return null;
        }

        if ($rawBody === null || $rawBody === '') {
            return null;
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded) || !isset($decoded['retry_after']) || !is_numeric($decoded['retry_after'])) {
            return null;
        }

        $seconds = (float) $decoded['retry_after'];
        if ($seconds <= 0) {
            return null;
        }

        return self::clampServerWaitMs((int) round($seconds * 1000));
    }

    /**
     * Clamp a server-requested wait to 60 seconds.
     *
     * @param int $milliseconds
     * @return int
     */
    private static function clampServerWaitMs(int $milliseconds): int
    {
        return max(0, min($milliseconds, 60000));
    }

    /**
     * The human-readable message in an error body.
     *
     * The API sends {"message": "..."} or a legacy {"error": "..."}. The edge in
     * front of it answers 5xx with {"success": false, "error": {"code": "...",
     * "message": "..."}}, where "error" is an object; passing that to an exception
     * constructor threw a TypeError instead of the API error.
     *
     * @param array<string, mixed>|null $errorData
     */
    protected static function errorText(?array $errorData): string
    {
        if (isset($errorData['message']) && is_string($errorData['message']) && $errorData['message'] !== '') {
            return $errorData['message'];
        }
        $error = $errorData['error'] ?? null;
        if (is_string($error) && $error !== '') {
            return $error;
        }
        if (is_array($error) && isset($error['message']) && is_string($error['message'])) {
            return $error['message'];
        }
        return 'Unknown error';
    }

    /**
     * Build query parameters for a technical-indicator request (pool or token).
     *
     * `indicator` is required and must be one of self::ALLOWED_INDICATORS;
     * everything else is forwarded only when the caller set it. `period`
     * belongs to rsi, ema, bbands and vwap; `fast_period`, `slow_period` and
     * `signal_period` belong to macd; `std_dev` belongs to bbands. The API
     * itself refuses a parameter sent for the wrong indicator (400
     * invalid_param), so this never fills in a default for one the caller
     * did not ask for.
     *
     * @param string $indicator One of 'rsi', 'ema', 'macd', 'bbands', 'vwap'
     * @param array<string, mixed> $options Additional options:
     *  - string $interval: Candle interval (server picks a default when unset)
     *  - int $limit: Values per response, 1 to 1000
     *  - string $start: First candle: a relative offset such as '-6h', Unix seconds, RFC 3339 or YYYY-MM-DD
     *  - string $end: Exclusive end, same formats as $start
     *  - string $cursor: Opaque cursor from a previous response's next_cursor (do not combine with $start)
     *  - int $period: rsi, ema, bbands, vwap only
     *  - int $fast_period: macd only
     *  - int $slow_period: macd only, must be greater than $fast_period
     *  - int $signal_period: macd only
     *  - float $std_dev: bbands only
     * @return array<string, mixed> The query parameters
     * @throws ValidationException If $indicator is not one of the allowed values, or $limit is out of range
     */
    protected function buildIndicatorParams(string $indicator, array $options): array
    {
        if (!in_array($indicator, self::ALLOWED_INDICATORS, true)) {
            throw new ValidationException(
                'Invalid indicator. Must be one of: ' . implode(', ', self::ALLOWED_INDICATORS)
            );
        }

        if (isset($options['limit']) && ($options['limit'] < 1 || $options['limit'] > 1000)) {
            throw new ValidationException('Limit must be between 1 and 1000 for indicator data');
        }

        $params = ['indicator' => $indicator];

        foreach ([
            'interval', 'limit', 'start', 'end', 'cursor',
            'period', 'fast_period', 'slow_period', 'signal_period', 'std_dev',
        ] as $key) {
            if (isset($options[$key])) {
                $params[$key] = $options[$key];
            }
        }

        return $params;
    }

    /**
     * Fill a missing "volume" with 0 on OHLCV rows.
     *
     * The API leaves "volume" out of a candle whose USD volume rounds down to 0
     * (coinpaprika/dexpaprika-go#2430).
     *
     * @param mixed $rows
     * @return mixed
     */
    protected static function withVolume($rows)
    {
        if (!is_array($rows) || array_values($rows) !== $rows) {
            return $rows;
        }
        foreach ($rows as $i => $row) {
            if (is_array($row) && isset($row['time_open']) && !array_key_exists('volume', $row)) {
                $rows[$i]['volume'] = 0;
            }
        }
        return $rows;
    }
}
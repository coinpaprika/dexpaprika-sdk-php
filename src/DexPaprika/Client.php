<?php

namespace DexPaprika;

use DexPaprika\Api\DexesApi;
use DexPaprika\Api\NetworksApi;
use DexPaprika\Api\PoolsApi;
use DexPaprika\Api\SearchApi;
use DexPaprika\Api\TokensApi;
use DexPaprika\Api\UtilsApi;
use DexPaprika\Cache\CacheInterface;
use DexPaprika\Cache\FilesystemCache;
use DexPaprika\Utils\Paginator;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;

class Client
{
    /**
     * @var string API base URL
     */
    private string $baseUrl;

    /**
     * @var ClientInterface HTTP client
     */
    private ClientInterface $httpClient;
    
    /**
     * @var bool Whether to transform API responses
     */
    private bool $transformResponses;
    
    /**
     * @var Config SDK configuration
     */
    private Config $config;

    /**
     * @var NetworksApi
     */
    public NetworksApi $networks;

    /**
     * @var DexesApi
     */
    public DexesApi $dexes;

    /**
     * @var PoolsApi
     */
    public PoolsApi $pools;

    /**
     * @var TokensApi
     */
    public TokensApi $tokens;

    /**
     * @var SearchApi
     */
    public SearchApi $search;

    /**
     * @var UtilsApi
     */
    public UtilsApi $utils;
    
    /**
     * SDK version
     */
    public const VERSION = '1.0.0';

    /**
     * Create a new DexPaprika client
     *
     * @param string|null $baseUrl The API base URL
     * @param ClientInterface|null $httpClient The HTTP client to use
     * @param bool $transformResponses Whether to transform API responses into objects
     * @param Config|null $config Custom configuration
     */
    public function __construct(
        ?string $baseUrl = null,
        ?ClientInterface $httpClient = null,
        bool $transformResponses = false,
        ?Config $config = null
    ) {
        // Initialize configuration
        $this->config = $config ?? new Config();
        $this->baseUrl = $baseUrl ?? $this->config->getBaseUrl();
        $this->transformResponses = $transformResponses;
        
        // Initialize HTTP client if not provided
        $this->httpClient = $httpClient ?? new GuzzleClient([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->config->getTimeout(),
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'dexpaprika-sdk-php/' . self::VERSION,
            ],
        ]);

        // Initialize API services
        $this->networks = new NetworksApi($this->httpClient, $this->transformResponses, $this->config);
        $this->dexes = new DexesApi($this->httpClient, $this->transformResponses, $this->config);
        $this->pools = new PoolsApi($this->httpClient, $this->transformResponses, $this->config);
        $this->tokens = new TokensApi($this->httpClient, $this->transformResponses, $this->config);
        $this->search = new SearchApi($this->httpClient, $this->transformResponses, $this->config);
        $this->utils = new UtilsApi($this->httpClient, $this->transformResponses, $this->config);
    }

    /**
     * Get the HTTP client
     *
     * @return ClientInterface
     */
    public function getHttpClient(): ClientInterface
    {
        return $this->httpClient;
    }

    /**
     * Get the base URL
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
    
    /**
     * Get the SDK configuration
     * 
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }
    
    /**
     * Set up caching for API responses
     * 
     * @param CacheInterface|null $cache Cache implementation (null to use default filesystem cache)
     * @param int|null $ttl Default cache TTL in seconds (null to use default)
     * @param bool $enabled Whether to enable caching immediately
     * @return self
     */
    public function setupCache(?CacheInterface $cache = null, ?int $ttl = null, bool $enabled = true): self
    {
        // Use provided cache or create a new filesystem cache
        $cacheImpl = $cache ?? new FilesystemCache();
        
        // Set the cache in config
        $this->config->setCache($cacheImpl);
        
        // Set TTL if provided
        if ($ttl !== null) {
            $this->config->setCacheTtl($ttl);
        }
        
        // Enable/disable caching
        $this->config->setCacheEnabled($enabled);
        
        return $this;
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
     * Create a paginator for a specific API method
     *
     * @param object $api The API instance (e.g., $client->pools)
     * @param string $method The method name (e.g., 'getNetworkPools')
     * @param array<string, mixed> $params The method parameters
     * @return Paginator The paginator instance
     */
    public function createPaginator(object $api, string $method, array $params = []): Paginator
    {
        return new Paginator($api, $method, $params);
    }
} 
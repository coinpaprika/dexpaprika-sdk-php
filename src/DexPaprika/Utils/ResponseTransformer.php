<?php

namespace DexPaprika\Utils;

class ResponseTransformer
{
    /**
     * Transform an array response to an object recursively
     *
     * @param array<string, mixed> $data The array data to transform
     * @return object The transformed object
     */
    public static function transform(array $data): object
    {
        $result = new \stdClass();
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Check if it's a sequential array (list) or associative array (object)
                if (array_keys($value) === range(0, count($value) - 1)) {
                    // Sequential array - keep as array but transform any nested associative arrays
                    foreach ($value as $i => $item) {
                        if (is_array($item) && !empty($item) && !isset($item[0])) {
                            $value[$i] = self::transform($item);
                        }
                    }
                    $result->$key = $value;
                } else {
                    // Associative array - transform to object
                    $result->$key = self::transform($value);
                }
            } else {
                $result->$key = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Transform a token details response
     *
     * @param array<string, mixed> $data The token details response
     * @return object The transformed token details
     */
    public static function transformTokenDetails(array $data): object
    {
        return self::transform($data);
    }
    
    /**
     * Transform a tokens response (for token listing or token details)
     *
     * @param array<string, mixed> $data The tokens response
     * @return object The transformed tokens response
     */
    public static function transformTokens(array $data): object
    {
        return self::transform($data);
    }
    
    /**
     * Transform a single token response
     *
     * @param array<string, mixed> $data The token response
     * @return object The transformed token response
     */
    public static function transformToken(array $data): object
    {
        return self::transform($data);
    }
    
    /**
     * Transform a pool response
     *
     * @param array<string, mixed> $data The pool response
     * @return object The transformed pool
     */
    public static function transformPool(array $data): object
    {
        return self::transform($data);
    }
    
    /**
     * Transform a token pools response
     *
     * @param array<string, mixed> $data The token pools response
     * @return object The transformed token pools response
     */
    public static function transformTokenPools(array $data): object
    {
        return self::transform($data);
    }
    
    /**
     * Transform a pool transactions response
     *
     * @param array<string, mixed> $data The transactions response
     * @return object The transformed transactions response
     */
    public static function transformTransactions(array $data): object
    {
        return self::transform($data);
    }
    
    /**
     * Transform an OHLCV response
     *
     * @param array<string, mixed> $data The OHLCV response
     * @return object The transformed OHLCV response
     */
    public static function transformOHLCV(array $data): object
    {
        return self::transform($data);
    }
    
    /**
     * Transform a search response
     * 
     * @param array<string, mixed> $data The search response
     * @return object The transformed search response
     */
    public static function transformSearch(array $data): object
    {
        return self::transform($data);
    }
    
    /**
     * Transform a networks response
     *
     * @param array<string, mixed> $data The networks response
     * @return object The transformed networks response
     */
    public static function transformNetworks(array $data): object
    {
        return self::transform($data);
    }
    
    /**
     * Transform a DEX response
     *
     * @param array<string, mixed> $data The DEX response
     * @return object The transformed DEX response
     */
    public static function transformDex(array $data): object
    {
        return self::transform($data);
    }
    
    /**
     * Transform a statistics response
     *
     * @param array<string, mixed> $data The statistics response
     * @return object The transformed statistics response
     */
    public static function transformStats(array $data): object
    {
        return self::transform($data);
    }
    
    /**
     * Transform a DEXes list response
     *
     * @param array<string, mixed> $data The DEXes list response
     * @return object The transformed DEXes list response
     */
    public static function transformDexes(array $data): object
    {
        return self::transform($data);
    }
    
    /**
     * Transform a pools list response
     *
     * @param array<string, mixed> $data The pools list response
     * @return object The transformed pools list response
     */
    public static function transformPools(array $data): object
    {
        return self::transform($data);
    }
    
    /**
     * Transform a pool details response
     *
     * @param array<string, mixed> $data The pool details response
     * @return object The transformed pool details response
     */
    public static function transformPoolDetails(array $data): object
    {
        return self::transform($data);
    }
} 
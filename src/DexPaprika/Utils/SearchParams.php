<?php

namespace DexPaprika\Utils;

/**
 * Pure helpers that normalise the legacy parameter names and values the SDK
 * accepts onto the canonical query parameters required by the unified search
 * endpoints (/networks/{network}/pools/search and /networks/{network}/tokens/search).
 *
 * The search endpoints return HTTP 400 on legacy sort-field values and legacy
 * filter parameter names, so anything the SDK keeps accepting for backward
 * compatibility has to be mapped before it reaches the wire. Pools and tokens
 * share these helpers; only the lookup tables differ.
 */
class SearchParams
{
    /**
     * Default order_by used when a sort field is missing or unrecognised.
     */
    public const DEFAULT_SORT_FIELD = 'volume_usd_24h';

    /**
     * Legacy to canonical sort fields for pools/search.
     *
     * @var array<string, string>
     */
    private const POOL_SORT_ALIASES = [
        'volume_usd' => 'volume_usd_24h',
        'transactions' => 'txns_24h',
        'last_price_change_usd_24h' => 'price_change_percentage_24h',
        'volume_24h' => 'volume_usd_24h',
        'volume_7d' => 'volume_usd_7d',
        'volume_30d' => 'volume_usd_30d',
        'liquidity' => 'liquidity_usd',
    ];

    /**
     * Canonical sort fields accepted as-is by pools/search.
     *
     * The 6h, 1h and 5m price-change windows are pools-only. tokens/search
     * returns 400 on them, so they must not be copied into TOKEN_SORT_CANONICAL.
     * price_change_percentage_24h belongs in both tables and is not part of that
     * asymmetry.
     *
     * @var array<int, string>
     */
    private const POOL_SORT_CANONICAL = [
        'volume_usd_24h',
        'volume_usd_7d',
        'volume_usd_30d',
        'liquidity_usd',
        'txns_24h',
        'created_at',
        'price_usd',
        'price_change_percentage_24h',
        'price_change_percentage_6h',
        'price_change_percentage_1h',
        'price_change_percentage_5m',
    ];

    /**
     * Legacy to canonical sort fields for tokens/search.
     *
     * @var array<string, string>
     */
    private const TOKEN_SORT_ALIASES = [
        'volume_24h' => 'volume_usd_24h',
        'txns' => 'txns_24h',
        'price_change' => 'price_change_percentage_24h',
        'fdv' => 'fdv_usd',
        'volume_7d' => 'volume_usd_7d',
        'volume_30d' => 'volume_usd_30d',
        // tokens/search returns 400 when ordering by price, so fall back to volume.
        'price_usd' => 'volume_usd_24h',
    ];

    /**
     * Canonical sort fields accepted as-is by tokens/search.
     *
     * @var array<int, string>
     */
    private const TOKEN_SORT_CANONICAL = [
        'volume_usd_24h',
        'volume_usd_7d',
        'volume_usd_30d',
        'liquidity_usd',
        'txns_24h',
        'fdv_usd',
        'created_at',
        'price_change_percentage_24h',
    ];

    /**
     * Legacy to canonical filter query parameter names for pools/search.
     *
     * @var array<string, string>
     */
    private const POOL_FILTER_RENAME = [
        'volume_24h_min' => 'volume_usd_24h_min',
        'volume_24h_max' => 'volume_usd_24h_max',
        'volume_7d_min' => 'volume_usd_7d_min',
        'volume_7d_max' => 'volume_usd_7d_max',
    ];

    /**
     * Legacy to canonical filter query parameter names for tokens/search.
     *
     * @var array<string, string>
     */
    private const TOKEN_FILTER_RENAME = [
        'volume_24h_min' => 'volume_usd_24h_min',
        'volume_24h_max' => 'volume_usd_24h_max',
    ];

    /**
     * Map a pool sort field (legacy or canonical) to the canonical order_by value.
     *
     * @param string|null $value
     */
    public static function mapPoolSortField(?string $value): string
    {
        return self::mapSortField($value, self::POOL_SORT_ALIASES, self::POOL_SORT_CANONICAL);
    }

    /**
     * Map a token sort field (legacy or canonical) to the canonical order_by value.
     *
     * @param string|null $value
     */
    public static function mapTokenSortField(?string $value): string
    {
        return self::mapSortField($value, self::TOKEN_SORT_ALIASES, self::TOKEN_SORT_CANONICAL);
    }

    /**
     * Rename legacy pool filter query parameters to their canonical search names.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function mapPoolFilterParams(array $params): array
    {
        return self::renameKeys($params, self::POOL_FILTER_RENAME);
    }

    /**
     * Rename legacy token filter query parameters to their canonical search names.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function mapTokenFilterParams(array $params): array
    {
        return self::renameKeys($params, self::TOKEN_FILTER_RENAME);
    }

    /**
     * Resolve a sort field against a lookup table, falling back to the default.
     *
     * @param string|null $value
     * @param array<string, string> $aliases
     * @param array<int, string> $canonical
     */
    private static function mapSortField(?string $value, array $aliases, array $canonical): string
    {
        if ($value === null || $value === '') {
            return self::DEFAULT_SORT_FIELD;
        }

        if (in_array($value, $canonical, true)) {
            return $value;
        }

        return $aliases[$value] ?? self::DEFAULT_SORT_FIELD;
    }

    /**
     * Rename array keys according to a map, passing unknown keys through unchanged.
     *
     * @param array<string, mixed> $params
     * @param array<string, string> $rename
     * @return array<string, mixed>
     */
    private static function renameKeys(array $params, array $rename): array
    {
        $out = [];

        foreach ($params as $key => $value) {
            $out[$rename[$key] ?? $key] = $value;
        }

        return $out;
    }
}

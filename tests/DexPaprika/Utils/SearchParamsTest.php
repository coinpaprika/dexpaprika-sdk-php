<?php

declare(strict_types=1);

namespace DexPaprika\Tests\Utils;

use DexPaprika\Utils\SearchParams;
use PHPUnit\Framework\TestCase;

class SearchParamsTest extends TestCase
{
    public function testMapPoolSortFieldMapsLegacyValues(): void
    {
        $this->assertEquals('volume_usd_24h', SearchParams::mapPoolSortField('volume_usd'));
        $this->assertEquals('txns_24h', SearchParams::mapPoolSortField('transactions'));
        $this->assertEquals('price_change_percentage_24h', SearchParams::mapPoolSortField('last_price_change_usd_24h'));
        $this->assertEquals('volume_usd_24h', SearchParams::mapPoolSortField('volume_24h'));
        $this->assertEquals('volume_usd_7d', SearchParams::mapPoolSortField('volume_7d'));
        $this->assertEquals('volume_usd_30d', SearchParams::mapPoolSortField('volume_30d'));
        $this->assertEquals('liquidity_usd', SearchParams::mapPoolSortField('liquidity'));
    }

    public function testMapPoolSortFieldPassesCanonicalThrough(): void
    {
        foreach (['volume_usd_24h', 'volume_usd_7d', 'volume_usd_30d', 'liquidity_usd', 'txns_24h', 'created_at', 'price_usd', 'price_change_percentage_24h'] as $field) {
            $this->assertEquals($field, SearchParams::mapPoolSortField($field));
        }
    }

    public function testMapPoolSortFieldFallsBackToDefault(): void
    {
        $this->assertEquals('volume_usd_24h', SearchParams::mapPoolSortField('not_a_field'));
        $this->assertEquals('volume_usd_24h', SearchParams::mapPoolSortField(null));
        $this->assertEquals('volume_usd_24h', SearchParams::mapPoolSortField(''));
    }

    public function testMapTokenSortFieldMapsLegacyValues(): void
    {
        $this->assertEquals('volume_usd_24h', SearchParams::mapTokenSortField('volume_24h'));
        $this->assertEquals('txns_24h', SearchParams::mapTokenSortField('txns'));
        $this->assertEquals('price_change_percentage_24h', SearchParams::mapTokenSortField('price_change'));
        $this->assertEquals('fdv_usd', SearchParams::mapTokenSortField('fdv'));
        $this->assertEquals('volume_usd_7d', SearchParams::mapTokenSortField('volume_7d'));
        $this->assertEquals('volume_usd_30d', SearchParams::mapTokenSortField('volume_30d'));
        // tokens/search 400s on price ordering, so price_usd folds back to volume.
        $this->assertEquals('volume_usd_24h', SearchParams::mapTokenSortField('price_usd'));
    }

    public function testMapTokenSortFieldPassesCanonicalThrough(): void
    {
        foreach (['volume_usd_24h', 'volume_usd_7d', 'volume_usd_30d', 'liquidity_usd', 'txns_24h', 'fdv_usd', 'created_at', 'price_change_percentage_24h'] as $field) {
            $this->assertEquals($field, SearchParams::mapTokenSortField($field));
        }
    }

    public function testMapTokenSortFieldFallsBackToDefault(): void
    {
        $this->assertEquals('volume_usd_24h', SearchParams::mapTokenSortField('bogus'));
        $this->assertEquals('volume_usd_24h', SearchParams::mapTokenSortField(null));
    }

    public function testMapPoolFilterParamsRenamesLegacyKeys(): void
    {
        $mapped = SearchParams::mapPoolFilterParams([
            'volume_24h_min' => 1,
            'volume_24h_max' => 2,
            'volume_7d_min' => 3,
            'volume_7d_max' => 4,
            'liquidity_usd_min' => 5,
            'liquidity_usd_max' => 6,
            'txns_24h_min' => 7,
            'created_after' => 8,
            'created_before' => 9,
            'order_by' => 'volume_usd_24h',
            'sort' => 'desc',
            'limit' => 10,
        ]);

        $this->assertEquals([
            'volume_usd_24h_min' => 1,
            'volume_usd_24h_max' => 2,
            'volume_usd_7d_min' => 3,
            'volume_usd_7d_max' => 4,
            'liquidity_usd_min' => 5,
            'liquidity_usd_max' => 6,
            'txns_24h_min' => 7,
            'created_after' => 8,
            'created_before' => 9,
            'order_by' => 'volume_usd_24h',
            'sort' => 'desc',
            'limit' => 10,
        ], $mapped);
    }

    public function testMapTokenFilterParamsRenamesLegacyKeys(): void
    {
        $mapped = SearchParams::mapTokenFilterParams([
            'volume_24h_min' => 1,
            'volume_24h_max' => 2,
            'liquidity_usd_min' => 3,
            'fdv_min' => 4,
            'fdv_max' => 5,
            'txns_24h_min' => 6,
            'created_after' => 7,
            'created_before' => 8,
        ]);

        $this->assertEquals([
            'volume_usd_24h_min' => 1,
            'volume_usd_24h_max' => 2,
            'liquidity_usd_min' => 3,
            'fdv_min' => 4,
            'fdv_max' => 5,
            'txns_24h_min' => 6,
            'created_after' => 7,
            'created_before' => 8,
        ], $mapped);
    }
}

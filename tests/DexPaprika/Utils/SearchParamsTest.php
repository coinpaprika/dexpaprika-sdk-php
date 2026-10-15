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

    public function testMapPoolSortFieldPassesShortPriceChangeWindowsThrough(): void
    {
        // pools/search accepts these three, so they must reach the wire unchanged.
        // If any drops out of the canonical list it silently becomes volume_usd_24h
        // and the caller gets 200 with a result set sorted by the wrong column.
        foreach (['price_change_percentage_6h', 'price_change_percentage_1h', 'price_change_percentage_5m'] as $field) {
            $this->assertEquals($field, SearchParams::mapPoolSortField($field));
        }
    }

    public function testMapPoolSortFieldPasses48hWindowThrough(): void
    {
        // Regression test: price_change_percentage_48h must reach the wire
        // unchanged. Before this field was added to POOL_SORT_CANONICAL,
        // mapPoolSortField() fell through to the unknown-field branch and
        // returned DEFAULT_SORT_FIELD (volume_usd_24h), so a caller asking
        // for the 48h sort got 200 with results ordered by volume instead.
        $this->assertEquals('price_change_percentage_48h', SearchParams::mapPoolSortField('price_change_percentage_48h'));
    }

    public function testShortPriceChangeWindowsArePoolOnly(): void
    {
        // tokens/search returns 400 on these windows and token rows carry no
        // price_change_percentage_5m field at all. Adding them to the token table
        // would turn a working request into a 400, so pin the asymmetry here.
        foreach (['price_change_percentage_6h', 'price_change_percentage_1h', 'price_change_percentage_5m'] as $field) {
            $this->assertEquals('volume_usd_24h', SearchParams::mapTokenSortField($field));
        }
    }

    public function test48hWindowIsPoolOnly(): void
    {
        // tokens/search returns 400 on price_change_percentage_48h and token rows
        // carry no 48h price-change field at all, so TOKEN_SORT_CANONICAL must
        // stay unchanged.
        $this->assertEquals('volume_usd_24h', SearchParams::mapTokenSortField('price_change_percentage_48h'));
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

    public function testMapPoolFilterParamsLeavesPriceChangeBoundsAlone(): void
    {
        // These ten are already canonical. The rename table must not touch them,
        // and negative bounds have to survive intact because a max on a price change
        // is normally negative.
        $bounds = [
            'price_change_percentage_48h_min' => 10,
            'price_change_percentage_48h_max' => -5,
            'price_change_percentage_24h_min' => -50,
            'price_change_percentage_24h_max' => -20,
            'price_change_percentage_6h_min' => 5,
            'price_change_percentage_6h_max' => 100,
            'price_change_percentage_1h_min' => -0.5,
            'price_change_percentage_1h_max' => 12.5,
            'price_change_percentage_5m_min' => 1,
            'price_change_percentage_5m_max' => 3,
        ];

        $this->assertEquals($bounds, SearchParams::mapPoolFilterParams($bounds));
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

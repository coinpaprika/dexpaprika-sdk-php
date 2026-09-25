<?php

require __DIR__ . '/../vendor/autoload.php';

use DexPaprika\Client;
use DexPaprika\Exception\DexPaprikaApiException;

/**
 * This example demonstrates how to fetch and process OHLCV data
 * (Open-High-Low-Close-Volume) for price analysis.
 *
 * The OHLCV endpoint returns a plain list of candles. Each candle carries
 * time_open, time_close, open, high, low, close and volume. There is no
 * wrapping 'data' key and no volume_usd field.
 */

$client = new Client();

echo "OHLCV DATA EXAMPLES\n";
echo "==================\n\n";

// Example 1: Hourly OHLCV for the last 24 hours on the busiest Ethereum pool.
// Without a key OHLCV reaches back 24 hours at 1h and longer.
echo "1. Hourly OHLCV data (last 24 hours):\n";
echo "------------------------------\n";

try {
    // Pick the busiest pool on Ethereum. Pools are network-scoped and the rows
    // come back under 'results'.
    $topPools = $client->pools->getNetworkPools('ethereum', ['limit' => 1]);

    if (empty($topPools['results'])) {
        echo "No pools returned\n";
        exit;
    }

    $pool = $topPools['results'][0];
    $poolAddress = $pool['id'];
    $network = $pool['chain'];

    echo "Found pool: {$poolAddress} on {$pool['dex_name']}\n";

    // The start is its own argument, not an entry in the options array.
    // '-24h' is relative to now.
    echo "Fetching hourly OHLCV for the last 24 hours\n";

    $ohlcvData = $client->pools->getPoolOHLCV($network, $poolAddress, '-24h', [
        'interval' => '1h',
        'limit' => 24,
    ]);

    // Display the data in a table format
    echo "\nTime (UTC)       | Open      | High      | Low       | Close     | Volume\n";
    echo "-----------------|-----------|-----------|-----------|-----------|-------------\n";

    foreach ($ohlcvData as $dataPoint) {
        $date = str_replace('T', ' ', substr($dataPoint['time_open'], 0, 16));
        printf(
            "%s | %9.4f | %9.4f | %9.4f | %9.4f | %12.2f\n",
            $date,
            $dataPoint['open'],
            $dataPoint['high'],
            $dataPoint['low'],
            $dataPoint['close'],
            $dataPoint['volume']
        );
    }

    // Example 2: Calculate simple moving average
    echo "\n\n2. Calculate 3-hour Simple Moving Average (SMA):\n";
    echo "---------------------------------------------\n";

    // Reuse the hourly candles from example 1
    $prices = array_map(function ($item) {
        return [
            'date' => str_replace('T', ' ', substr($item['time_open'], 0, 16)),
            'close' => $item['close'],
        ];
    }, $ohlcvData);

    // Calculate 3-hour SMA
    $smaValues = [];
    for ($i = 2; $i < count($prices); $i++) {
        $sum = $prices[$i]['close'] + $prices[$i - 1]['close'] + $prices[$i - 2]['close'];
        $sma = $sum / 3;
        $smaValues[] = [
            'date' => $prices[$i]['date'],
            'price' => $prices[$i]['close'],
            'sma' => $sma,
        ];
    }

    // Display the SMA results
    echo "\nTime (UTC)       | Close     | 3-hour SMA\n";
    echo "-----------------|-----------|------------\n";

    foreach ($smaValues as $data) {
        printf(
            "%s | %9.4f | %9.4f\n",
            $data['date'],
            $data['price'],
            $data['sma']
        );
    }

    // Example 3: Price volatility calculation
    echo "\n\n3. Calculate Hourly Price Volatility:\n";
    echo "---------------------------------\n";

    $volatilityData = [];
    for ($i = 1; $i < count($prices); $i++) {
        $pricePrev = $prices[$i - 1]['close'];
        $priceNow = $prices[$i]['close'];
        $percentChange = (($priceNow - $pricePrev) / $pricePrev) * 100;

        $volatilityData[] = [
            'date' => $prices[$i]['date'],
            'percent_change' => $percentChange,
        ];
    }

    // Display the volatility results
    echo "\nTime (UTC)       | Hourly % Change\n";
    echo "-----------------|----------------\n";

    foreach ($volatilityData as $data) {
        printf(
            "%s | %+6.2f%%\n",
            $data['date'],
            $data['percent_change']
        );
    }

    // Calculate average volatility
    $totalChange = array_sum(array_map(function ($item) {
        return abs($item['percent_change']);
    }, $volatilityData));

    $avgVolatility = $totalChange / count($volatilityData);
    echo "\nAverage hourly volatility over the period: " . number_format($avgVolatility, 2) . "%\n";

} catch (DexPaprikaApiException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

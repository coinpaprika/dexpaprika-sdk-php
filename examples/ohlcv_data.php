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

// Example 1: Get daily OHLCV data for the busiest Ethereum pool
echo "1. Daily OHLCV data:\n";
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

    // Get OHLCV data for the last 7 days. The start date is its own argument,
    // not an entry in the options array.
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-7 days'));

    echo "Fetching OHLCV data from $startDate to $endDate\n";

    $ohlcvData = $client->pools->getPoolOHLCV($network, $poolAddress, $startDate, [
        'end' => $endDate,
        'interval' => '24h',
        'limit' => 14,
    ]);

    // Display the data in a table format
    echo "\nDate         | Open      | High      | Low       | Close     | Volume\n";
    echo "-------------|-----------|-----------|-----------|-----------|-------------\n";

    foreach ($ohlcvData as $dataPoint) {
        $date = substr($dataPoint['time_open'], 0, 10);
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
    echo "\n\n2. Calculate 3-day Simple Moving Average (SMA):\n";
    echo "---------------------------------------------\n";

    // Get OHLCV data for more days to calculate SMA
    $startDate = date('Y-m-d', strtotime('-14 days'));

    $ohlcvData = $client->pools->getPoolOHLCV($network, $poolAddress, $startDate, [
        'end' => $endDate,
        'interval' => '24h',
        'limit' => 21,
    ]);

    $prices = array_map(function ($item) {
        return [
            'date' => substr($item['time_open'], 0, 10),
            'close' => $item['close'],
        ];
    }, $ohlcvData);

    // Calculate 3-day SMA
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
    echo "\nDate         | Close     | 3-day SMA\n";
    echo "-------------|-----------|------------\n";

    foreach ($smaValues as $data) {
        printf(
            "%s | %9.4f | %9.4f\n",
            $data['date'],
            $data['price'],
            $data['sma']
        );
    }

    // Example 3: Price volatility calculation
    echo "\n\n3. Calculate Daily Price Volatility:\n";
    echo "---------------------------------\n";

    $volatilityData = [];
    for ($i = 1; $i < count($prices); $i++) {
        $priceYesterday = $prices[$i - 1]['close'];
        $priceToday = $prices[$i]['close'];
        $percentChange = (($priceToday - $priceYesterday) / $priceYesterday) * 100;

        $volatilityData[] = [
            'date' => $prices[$i]['date'],
            'percent_change' => $percentChange,
        ];
    }

    // Display the volatility results
    echo "\nDate         | Daily % Change\n";
    echo "-------------|---------------\n";

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
    echo "\nAverage daily volatility over the period: " . number_format($avgVolatility, 2) . "%\n";

} catch (DexPaprikaApiException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

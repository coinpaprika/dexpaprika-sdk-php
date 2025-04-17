<?php

require __DIR__ . '/../vendor/autoload.php';

use DexPaprika\Client;
use DexPaprika\Exception\DexPaprikaApiException;

/**
 * This example demonstrates how to fetch and process OHLCV data
 * (Open-High-Low-Close-Volume) for price analysis
 */

$client = new Client();

echo "OHLCV DATA EXAMPLES\n";
echo "==================\n\n";

// Example 1: Get daily OHLCV data for ETH/USDC pool
echo "1. Daily OHLCV data for ETH/USDC:\n";
echo "------------------------------\n";

try {
    // Fetch pools that contain ETH and USDC
    $ethPools = $client->search->search('ethereum usdc');
    
    // Find a suitable ETH/USDC pool (this is simplified for the example)
    $poolAddress = null;
    $network = null;
    
    foreach ($ethPools['pools'] as $pool) {
        if (stripos($pool['name'], 'ETH') !== false && stripos($pool['name'], 'USDC') !== false) {
            $poolAddress = $pool['address'];
            $network = $pool['chain'];
            echo "Found pool: " . $pool['name'] . " (" . $pool['address'] . ")\n";
            break;
        }
    }
    
    if (!$poolAddress) {
        echo "No ETH/USDC pool found\n";
        exit;
    }
    
    // Get OHLCV data for the last 7 days
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-7 days'));
    
    echo "Fetching OHLCV data from $startDate to $endDate\n";
    
    $ohlcvData = $client->pools->getPoolOHLCV($network, $poolAddress, [
        'start' => $startDate,
        'end' => $endDate,
        'interval' => '24h',
    ]);
    
    // Display the data in a table format
    echo "\nDate         | Open      | High      | Low       | Close     | Volume\n";
    echo "-------------|-----------|-----------|-----------|-----------|-------------\n";
    
    foreach ($ohlcvData['data'] as $dataPoint) {
        $date = date('Y-m-d', $dataPoint['time']);
        printf(
            "%s | %9.4f | %9.4f | %9.4f | %9.4f | %12.2f\n",
            $date,
            $dataPoint['open'],
            $dataPoint['high'],
            $dataPoint['low'],
            $dataPoint['close'],
            $dataPoint['volume_usd']
        );
    }
    
    // Example 2: Calculate simple moving average
    echo "\n\n2. Calculate 3-day Simple Moving Average (SMA):\n";
    echo "---------------------------------------------\n";
    
    // Get OHLCV data for more days to calculate SMA
    $startDate = date('Y-m-d', strtotime('-14 days'));
    
    $ohlcvData = $client->pools->getPoolOHLCV($network, $poolAddress, [
        'start' => $startDate,
        'end' => $endDate,
        'interval' => '24h',
    ]);
    
    $prices = array_map(function($item) {
        return [
            'time' => $item['time'],
            'close' => $item['close']
        ];
    }, $ohlcvData['data']);
    
    // Calculate 3-day SMA
    $smaValues = [];
    for ($i = 2; $i < count($prices); $i++) {
        $sum = $prices[$i]['close'] + $prices[$i-1]['close'] + $prices[$i-2]['close'];
        $sma = $sum / 3;
        $smaValues[] = [
            'date' => date('Y-m-d', $prices[$i]['time']),
            'price' => $prices[$i]['close'],
            'sma' => $sma
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
        $priceYesterday = $prices[$i-1]['close'];
        $priceToday = $prices[$i]['close'];
        $percentChange = (($priceToday - $priceYesterday) / $priceYesterday) * 100;
        
        $volatilityData[] = [
            'date' => date('Y-m-d', $prices[$i]['time']),
            'percent_change' => $percentChange
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
    $totalChange = array_sum(array_map(function($item) {
        return abs($item['percent_change']);
    }, $volatilityData));
    
    $avgVolatility = $totalChange / count($volatilityData);
    echo "\nAverage daily volatility over the period: " . number_format($avgVolatility, 2) . "%\n";
    
} catch (DexPaprikaApiException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 
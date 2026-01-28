<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BinanceService
{
    protected $baseUrl;
    protected $apiKey;
    protected $secretKey;

    public function __construct()
    {
        $this->baseUrl = 'https://testnet.binancefuture.com';
        $this->apiKey = "bb1217aaa25495ae6fc28223344b58f559d2edb5332b67047c5b34bdf9477d90m";
        $this->secretKey = "89820485ded9e5073f5a178dd7450e0540ab41324451c81f2cd33ab1a37005d6m";
    }

    /**
     * Generate HMAC SHA256 signature
    */
    protected function generateSignature(string $queryString, string $apiSecret): string
    {
        return hash_hmac('sha256', $queryString, $apiSecret);
    }

    /**
     * Get wallet balance
    */
    public function binanceGetWalletBalance($apiKey, $apiSecret): array
    {
        $endpoint = '/fapi/v2/balance';
        $url = $this->baseUrl . $endpoint;

        // Generate timestamp
        $timestamp = (int) (microtime(true) * 1000);
        $params = [
            'recvWindow' => 5000,
            'timestamp' => $timestamp,
        ];
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $signature = $this->generateSignature($queryString, $apiSecret);
        $params['signature'] = $signature;

        try {
            $response = Http::withHeaders([
                'X-MBX-APIKEY' => $apiKey,
            ])->get($url, $params);

            if ($response->successful()) {
                $balances = $response->json();
                $usdtBalance = collect($balances)->firstWhere('asset', 'USDT');

                if ($usdtBalance) {
                    return [
                        'status' => true,
                        'available_balance' => $usdtBalance['availableBalance']
                    ];
                }

                return [
                    'status' => false,
                    'error' => [
                        "msg" => "USDT balance not found",
                    ],
                ];
            }

            return [
                'status' => false,
                'error' => $response->json(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Place a limit order
    */
    public function binancePlaceLimitOrder(string $symbol, string $side, float $quantity, float $price, $apiKey, $apiSecret): array
    {
        $endpoint = '/fapi/v1/order';
        $url = $this->baseUrl . $endpoint;

        // Generate timestamp
        $timestamp = (int) (microtime(true) * 1000);

        // Request parameters
        $params = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => 'LIMIT',
            'timeInForce' => 'GTC',
            'quantity' => number_format($quantity, 8, '.', ''),
            'price' => number_format($price, 2, '.', ''),      
            'recvWindow' => 5000,
            'timestamp' => $timestamp,
        ];

        // Create query string for signature
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $signature = $this->generateSignature($queryString, $apiSecret);
        $params['signature'] = $signature;

        try {
            $response = Http::withHeaders([
                'X-MBX-APIKEY' => $apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post($url, $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json(),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    public function binanceSetLeverage(string $symbol, int $leverage, $apiKey, $apiSecret): array
    {
        $endpoint = '/fapi/v1/leverage';
        $url = $this->baseUrl . $endpoint;

        // Generate timestamp
        $timestamp = (int) (microtime(true) * 1000);

        // Request parameters
        $params = [
            'symbol' => $symbol,
            'leverage' => $leverage,
            'recvWindow' => 5000,
            'timestamp' => $timestamp,
        ];

        // Create query string for signature
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        // Generate signature
        $signature = $this->generateSignature($queryString, $apiSecret);
        $params['signature'] = $signature;

        try {
            $response = Http::withHeaders([
                'X-MBX-APIKEY' => $apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post($url, $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json(),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    public function binancePlaceOrderWithSlTpAndLeverage(
        string $symbol,
        string $side,
        float $quantity,
        ?float $price,
        int $leverage,
        ?float $stopLossPrice,
        ?float $takeProfitPrice,
        string $apiKey,
        string $apiSecret,
    ): array {
        $results = [];

        // Step 1: Set leverage
        $leverageResult = $this->binanceSetLeverage($symbol, $leverage, $apiKey, $apiSecret);
        // if (!$leverageResult['success']) {
        //     return $leverageResult;
        // }

        // Step 2: Place limit or market order
        if ($price !== null) {
            // Place limit order
            $orderResult = $this->binancePlaceLimitOrder($symbol, $side, $quantity, $price, $apiKey, $apiSecret);
            if (!$orderResult['success']) {
                return $orderResult;
            }
            $results['order'] = $orderResult['data'];
            $results['orderType'] = 'LIMIT';
        } else {
            // Place market order
            $orderResult = $this->placeMarketOrder($symbol, $side, $quantity, $apiKey, $apiSecret);

            if (!$orderResult['success']) {
                return $orderResult;
            }
            $results['order'] = $orderResult['data'];
            $results['orderType'] = 'MARKET';
        }

        // Step 3: Get position details
        $results['actualQuantity'] = $quantity; // Fallback to requested quantity
        if ($price === null) {
            $positionResult = $this->binanceGetPositionInfo($symbol, $apiKey, $apiSecret);

            if($positionResult['success'] && !empty($positionResult['data'])){
                $results['position'] = [
                    'margin' => $positionResult['data']['notional']/$positionResult['data']['leverage'],
                    'positionAmt' => $positionResult['data']['positionAmt'],
                    'entryPrice' => $positionResult['data']['entryPrice'],
                    'leverage' => $positionResult['data']['leverage'],
                ];
                $results['actualQuantity'] = abs(floatval($positionResult['data']['positionAmt']));
            }
        }

        // Step 4: Place SL order if provided
        if ($stopLossPrice !== null) {
            $slResult = $this->binancePlaceSlTpOrder($symbol, $side === 'BUY' ? 'SELL' : 'BUY', $quantity, $stopLossPrice, 'STOP_MARKET', $apiKey, $apiSecret);

            if (!$slResult['success']) {
                return $slResult;
            }
            $results['stopLoss'] = $slResult['data'];
        }

        // Step 5: Place TP order if provided
        if ($takeProfitPrice !== null) {
            $tpResult = $this->binancePlaceSlTpOrder($symbol, $side === 'BUY' ? 'SELL' : 'BUY', $quantity, $takeProfitPrice, 'TAKE_PROFIT_MARKET', $apiKey, $apiSecret);

            if (!$tpResult['success']) {
                return $tpResult;
            }
            $results['takeProfit'] = $tpResult['data'];
        }

        return [
            'success' => true,
            'data' => $results,
        ];
    }
    protected function binancePlaceSlTpOrder(string $symbol, string $side, float $quantity, float $stopPrice, string $type, $apiKey, $apiSecret): array {
        $endpoint = '/fapi/v1/order';
        $url = $this->baseUrl . $endpoint;

        // Generate timestamp
        $timestamp = (int) (microtime(true) * 1000);

        // Request parameters
        $params = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => $type, // STOP_MARKET or TAKE_PROFIT_MARKET
            'quantity' => number_format($quantity, 8, '.', ''),
            'stopPrice' => number_format($stopPrice, 2, '.', ''),
            'reduceOnly' => 'true',
            'recvWindow' => 5000,
            'timestamp' => $timestamp,
        ];

        // Create query string for signature
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        // Generate signature
        $signature = $this->generateSignature($queryString, $apiSecret);
        $params['signature'] = $signature;

        try {
            $response = Http::withHeaders([
                'X-MBX-APIKEY' => $apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post($url, $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json(),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    protected function placeMarketOrder(string $symbol, string $side, float $quantity, $apiKey, $apiSecret): array
    {
        $endpoint = '/fapi/v1/order';
        $url = $this->baseUrl . $endpoint;

        $timestamp = (int) (microtime(true) * 1000);
        $params = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => 'MARKET',
            'quantity' => number_format($quantity, 8, '.', ''),
            'recvWindow' => 5000,
            'timestamp' => $timestamp,
        ];

        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $signature = $this->generateSignature($queryString, $apiSecret);
        $params['signature'] = $signature;

        try {
            $response = Http::withHeaders([
                'X-MBX-APIKEY' => $apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post($url, $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json(),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel an open order
    */
    public function cancelOrder(string $symbol, int $orderId, $apiKey, $apiSecret): array
    {
        $endpoint = '/fapi/v1/order';
        $url = $this->baseUrl . $endpoint;

        // Generate timestamp
        $timestamp = (int) (microtime(true) * 1000);

        // Request parameters
        $params = [
            'symbol' => $symbol,
            'orderId' => $orderId,
            'recvWindow' => 5000,
            'timestamp' => $timestamp,
        ];

        // Create query string for signature
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        // Generate signature
        $signature = $this->generateSignature($queryString, $apiSecret);

        // Append signature to query string
        $queryString .= '&signature=' . $signature;

        // Append query string to URL
        $fullUrl = $url . '?' . $queryString;

        try {
            $response = Http::withHeaders([
                'X-MBX-APIKEY' => $apiKey,
            ])->delete($fullUrl);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json(),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get position information
    */
    public function binanceGetPositionInfo(string $symbol, $apiKey, $apiSecret): array
    {
        $endpoint = '/fapi/v2/positionRisk';
        $url = $this->baseUrl . $endpoint;

        // Generate timestamp
        $timestamp = (int) (microtime(true) * 1000);

        // Request parameters
        $params = [
            'symbol' => $symbol,
            'recvWindow' => 5000,
            'timestamp' => $timestamp,
        ];

        // Create query string for signature
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        // Generate signature
        $signature = $this->generateSignature($queryString, $apiSecret);

        // Append signature to params
        $params['signature'] = $signature;

        try {
            $response = Http::withHeaders([
                'X-MBX-APIKEY' => $apiKey,
            ])->get($url, $params);

            if ($response->successful()) {
                $positions = $response->json();
                $position = collect($positions)->firstWhere('symbol', $symbol);
                return [
                    'success' => true,
                    'data' => $position ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => $response->json(),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    /**
     * Get all position information
    */
    public function binanceGetAllPositions($apiKey, $apiSecret): array
    {
        $endpoint = '/fapi/v2/positionRisk';
        $url = $this->baseUrl . $endpoint;

        // Generate timestamp
        $timestamp = (int) (microtime(true) * 1000);

        // Request parameters
        $params = [
            'recvWindow' => 5000,
            'timestamp' => $timestamp,
        ];

        // Create query string for signature
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        // Generate signature
        $signature = $this->generateSignature($queryString, $apiSecret);

        // Append signature to params
        $params['signature'] = $signature;

        try {
            $response = Http::withHeaders([
                'X-MBX-APIKEY' => $apiKey,
            ])->get($url, $params);

            if ($response->successful()) {
                $positions = $response->json();
                // Filter active positions (positionAmt != 0)
                $activePositions = collect($positions)->filter(function ($position) {
                    return $position['positionAmt'] != '0';
                })->values()->all();
                return $activePositions;
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Close an open position
    */
    public function binanceClosePosition(string $symbol, $apiKey, $apiSecret): array
    {
        // Step 1: Get position details
        $positionResult = $this->binanceGetPositionInfo($symbol, $apiKey, $apiSecret);
        if (!$positionResult['success'] || empty($positionResult['data'])) {
            return [
                'success' => false,
                'error' => 'No open position found for ' . $symbol,
            ];
        }

        $position = $positionResult['data'];
        $positionAmt = floatval($position['positionAmt']); // Positive for long, negative for short
        if ($positionAmt == 0) {
            return [
                'success' => false,
                'error' => 'No open position to close for ' . $symbol,
            ];
        }

        // Step 2: Prepare order to close position
        $side = $positionAmt > 0 ? 'SELL' : 'BUY'; // Opposite side to close
        $quantity = abs($positionAmt); // Absolute quantity

        $endpoint = '/fapi/v1/order';
        $url = $this->baseUrl . $endpoint;

        // Generate timestamp
        $timestamp = (int) (microtime(true) * 1000);

        // Request parameters
        $params = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => 'MARKET', // Use market order for immediate execution
            'quantity' => number_format($quantity, 8, '.', ''),
            'reduceOnly' => 'true', // Ensure it only closes the position
            'recvWindow' => 5000,
            'timestamp' => $timestamp,
        ];

        // Create query string for signature
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        // Generate signature
        $signature = $this->generateSignature($queryString, $apiSecret);

        // Append signature to params
        $params['signature'] = $signature;

        try {
            $response = Http::withHeaders([
                'X-MBX-APIKEY' => $apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post($url, $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json(),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
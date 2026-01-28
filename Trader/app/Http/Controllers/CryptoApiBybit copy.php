<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TelegramUser;
use Carbon\Carbon;

class CryptoApiBybit extends Controller
{
    private $baseUrl;
    public function __construct()
    {
        $this->baseUrl = 'https://api-testnet.bybit.com';
        // $this->baseUrl = 'https://api.bybit.com';
    }
    
    // status 
    public function status($user_id)
    {
        $user = TelegramUser::firstOrCreate(['chat_id' => $user_id]);
        
        // check license
        $licenseApis = $this->licenseApiValidation($user_id);
        if(!$licenseApis["status"]){
            return [
                "status" => false,
                "msg" => $licenseApis["msg"],
                "hint" => $licenseApis["hint"]
            ];
        }
        
        $wallet = $this->walletBalance($user_id);
        
        return $wallet;
    }
    // clear
    public function clear(Request $req)
    {
        $user_id = $req->user_id;
        $user = TelegramUser::firstOrCreate(['chat_id' => $user_id]);
        $user->bybit_api_key = null;
        $user->bybit_api_secret = null;
        $user->save();
        
        return response()->json([
            "status" => true
        ]);
    }
    // set
    public function set(Request $req)
    {
        // const data  
        $user_id = $req->user_id;
        $type = $req->type;
        $value = $req->value;
        
        // props 
        $user = TelegramUser::firstOrCreate(['chat_id' => $user_id]);
        if($type === "key"){
            $user->bybit_api_key = $value;
            $user->bybit_api_secret = null;
        }else{
            $user->bybit_api_secret = $value;
        }
        $user->save();
        
        return response()->json([
            "status" => true
        ]);
    }
    
    
    // balance
    public function walletBalance($user_id)
    {
        $apiKeys = $this->apiKeys($user_id);
        if (!$apiKeys["status"]) {
            return [
                'hint' => "API",
                'status' => false,
                'msg' => $apiKeys["msg"]
            ];
        }
    
        $apiKey = $apiKeys["api_key"];
        $apiSecret = $apiKeys["api_secret"];
        $baseUrl = $this->baseUrl;
    
        $timestamp = round(microtime(true) * 1000);
        $recvWindow = 5000;
    
        // Prepare headers
        $headers = [
            'X-BAPI-API-KEY'     => $apiKey,
            'X-BAPI-TIMESTAMP'   => $timestamp,
            'X-BAPI-RECV-WINDOW' => $recvWindow,
        ];
    
        // Wallet Balance
        $walletParams = [
            'accountType' => 'UNIFIED',
            'coin' => 'USDT',
        ];
        $walletQuery = http_build_query($walletParams);
        $walletSignaturePayload = $timestamp . $apiKey . $recvWindow . $walletQuery;
        $walletSignature = hash_hmac('sha256', $walletSignaturePayload, $apiSecret);
        $walletHeaders = array_merge($headers, ['X-BAPI-SIGN' => $walletSignature]);
    
        $walletResponse = Http::withHeaders($walletHeaders)->get($baseUrl . '/v5/account/wallet-balance', $walletParams);
        if ($walletResponse->failed()) {
            return [
                'status' => false,
                'hint' => "API_CONNECTION",
                'msg' => null
            ];
        }
        
        $walletData = $walletResponse->json();
        
        // check connection 
        if($walletData["retMsg"] != "OK"){
            return [
                'status' => false,
                'hint' => "API_CONNECTION",
                'msg' => null
            ];
        }
        
        $availableBalance = data_get($walletData, 'result.list.0.totalAvailableBalance');
        $marginBalance = data_get($walletData, 'result.list.0.totalMarginBalance');
    
        // Account Info
        $accountSignaturePayload = $timestamp . $apiKey . $recvWindow;
        $accountSignature = hash_hmac('sha256', $accountSignaturePayload, $apiSecret);
        $accountHeaders = array_merge($headers, ['X-BAPI-SIGN' => $accountSignature]);
    
        $accountResponse = Http::withHeaders($accountHeaders)->get($baseUrl . '/v5/account/info');
        if ($accountResponse->failed()) {
            return [
                'status' => false,
                'hint' => "API",
                'msg' => null
            ];
        }
        $accountData = $accountResponse->json();
        $marginMode = data_get($accountData, 'result.marginMode');
        $spotHedgingStatus = data_get($accountData, 'result.spotHedgingStatus');

        return [
            'status' => true,
            'msg' => null,
            'margin_balance' => empty($marginBalance) ? 0 : $marginBalance,
            'available_balance' => empty($availableBalance) ? 0 : $availableBalance,
            'derivatives_enabled' => $marginMode !== 'ISOLATED_MARGIN',
            'spot_trading_enabled' => $spotHedgingStatus === 'ON',
        ];
    }

    
    // open
    public function openTrade(Request $req)
    {
        // Parameters
        $symbol     = (string) $req->symbol;
        $qty        = (string) $req->qty;
        $entryPrice = (string) $req->entryPrice;
        $stopLoss   = (string) $req->stopLoss;
        $leverage   = (string) $req->leverage;
        $takeProfit = (string) $req->takeProfit;
        $side       = (string) $req->type;
        $user_id    = $req->user_id;
        $triggerDir = $side === "Sell" ? 1 : 2;

        $apiKeys = $this->apiKeys($user_id);
        if (!$apiKeys["status"]) {
            return response()->json([
                'hint' => "API",
                'msg' => $apiKeys["msg"]
            ]);
        }
        
        // check license
        $licenseApis = $this->licenseApiValidation($user_id);
        if(!$licenseApis["status"]){
            return response()->json([
                'hint' => $licenseApis["hint"],
                "msg" => $licenseApis["msg"]
            ]);
        }
    
        $apiKey = $apiKeys["api_key"];
        $apiSecret = $apiKeys["api_secret"];
        $baseUrl = $this->baseUrl;
        $recvWindow = 5000;
    
        // 1. Set Leverage
        $leverage = $this->setLeverage($symbol, $leverage, $apiKey, $apiSecret);
        if(!$leverage["status"]){
            return response()->json([
                'hint' => "API",
                'msg' => "Can't set the leverage!"
            ]);
        }
    
        // 2. Limit Place Order
        $timestamp2 = round(microtime(true) * 1000); 
        // $orderParams = [
        //     'category'     => 'linear',
        //     'symbol'       => $symbol,
        //     'side'         => $side,
        //     'orderType'    => 'Limit',
        //     'qty'          => $qty,
        //     'price'        => $entryPrice,
        //     'stopLoss'     => $stopLoss,
        //     'timeInForce'  => 'GTC',
        // ];

        $orderParams = [
            'category'         => 'linear',
            'symbol'           => $symbol,       // e.g. "BTCUSDT"
            'side'             => $side,         // "Buy" or "Sell"
            'orderType'        => 'Limit',       // conditional limit order
            'qty'              => $qty,          // order size
            'price'            => $entryPrice,   // execution price once triggered
            'timeInForce'      => 'GTC',
            'stopLoss'     => $stopLoss,

            // 🔑 Conditional order params
            'triggerPrice'     => $entryPrice,    // level at which order activates
            'triggerDirection' => $triggerDir,                // 1 = trigger when price rises
            'triggerBy'        => 'MarkPrice',      // use Mark Price as condition
            'reduceOnly'       => false,            // optional
            'closeOnTrigger'   => false,            // optional
        ];
        if(!empty($takeProfit)){
            $orderParams["takeProfit"] = $takeProfit;
        }
        $orderPayload = json_encode($orderParams, JSON_UNESCAPED_SLASHES);
        $orderSig = hash_hmac('sha256', $timestamp2 . $apiKey . $recvWindow . $orderPayload, $apiSecret);
    
        $orderResp = Http::withHeaders([
            'X-BAPI-API-KEY'     => $apiKey,
            'X-BAPI-TIMESTAMP'   => $timestamp2,
            'X-BAPI-RECV-WINDOW' => $recvWindow,
            'X-BAPI-SIGN'        => $orderSig,
            'Content-Type'       => 'application/json',
        ])->post($baseUrl . '/v5/order/create', $orderParams);

        if (!$orderResp->successful()) {
            return [
                'hint' => 'others',
                "status" => false,
                "msg" => "Something went wrong. Please try again later."
            ];
        }
    
        return response()->json($orderResp->json());
    }

    // market entry  
    public function marketEntry(Request $req)
    {
        // Get API keys
        $user_id = $req->user_id;
        $apiKeys = $this->apiKeys($user_id);
        if (!$apiKeys["status"]) {
            return response()->json([
                'hint' => "API",
                'msg' => $apiKeys["msg"]
            ]);
        }

        // Check license
        $licenseApis = $this->licenseApiValidation($user_id);
        if (!$licenseApis["status"]) {
            return response()->json([
                'hint' => $licenseApis["hint"],
                "msg" => $licenseApis["msg"]
            ]);
        }

        $apiKey = $apiKeys["api_key"];
        $apiSecret = $apiKeys["api_secret"];
        $baseUrl = $this->baseUrl;
        $recvWindow = 5000;

        // Parameters from the request
        $symbol = (string) $req->symbol;
        $qty = (string) $req->qty;
        $side = (string) $req->type;  // 'Buy' or 'Sell'
        $stopLoss = (string) $req->stopLoss;
        $takeProfit = (string) $req->takeProfit;

        // 1. Set Leverage
        $leverage = $this->setLeverage($symbol, $req->leverage, $apiKey, $apiSecret);
        if(!$leverage["status"]){
            return response()->json([
                'hint' => "API",
                'msg' => "Can't set the leverage!"
            ]);
        }

        // 2. Place Market Order (no price needed for market orders)
        $timestamp2 = round(microtime(true) * 1000); // Fresh timestamp
        $orderParams = [
            'category' => 'linear',
            'symbol' => $symbol,
            'side' => $side,          // 'Buy' or 'Sell'
            'orderType' => 'Market',  // This makes it a market order
            'qty' => $qty,
            'stopLoss' => $stopLoss,  // Optional
            'timeInForce' => 'GTC',   // Good 'Til Canceled
        ];

        // Optional: Set Take Profit if provided
        if (!empty($takeProfit)) {
            $orderParams["takeProfit"] = $takeProfit;
        }

        $orderPayload = json_encode($orderParams, JSON_UNESCAPED_SLASHES);
        $orderSig = hash_hmac('sha256', $timestamp2 . $apiKey . $recvWindow . $orderPayload, $apiSecret);

        // Send the request to place the order
        $orderResp = Http::withHeaders([
            'X-BAPI-API-KEY' => $apiKey,
            'X-BAPI-TIMESTAMP' => $timestamp2,
            'X-BAPI-RECV-WINDOW' => $recvWindow,
            'X-BAPI-SIGN' => $orderSig,
            'Content-Type' => 'application/json',
        ])->post($baseUrl . '/v5/order/create', $orderParams);

        if (!$orderResp->successful()) {
            return [
                'hint' => 'others',
                "status" => false,
                "msg" => "Something went wrong. Please try again later."
            ];
        }

        // Return the response from the order creation 
        $user_id = (string) $req->user_id;
        $symbol = (string) $req->symbol;
        $side = (string) $req->type;
        $newReq = new Request([
            'user_id' => $user_id,
            'symbol' => $symbol,
            'side' => $side
        ]);

        $msg = $orderResp["msg"];
        $orderId = $orderResp["result"]["orderId"] ?? null;

        return response()->json([
            "status" => true,
            "msg" => $msg,
            "order_id" => $orderId,
            "trade_status" => $this->positionStatus($newReq),
        ]);
    }
    
    // tradeStatus
    public function positionStatus(Request $req)
    {
        try {
            $user_id = $req->user_id;
            $symbol = $req->symbol;
            $side = $req->side; // Reverse side for position check
        
            // Get API credentials
            $apiKeys = $this->apiKeys($user_id);
            if (!$apiKeys["status"]) {
                return [
                    'status' => false,
                    'msg' => $apiKeys["msg"]
                ];
            }
        
            $apiKey = $apiKeys["api_key"];
            $apiSecret = $apiKeys["api_secret"];
            $baseUrl = $this->baseUrl;
            $recvWindow = 5000;
        
            // Prepare API params
            $params = [
                'category' => 'linear',
                'symbol' => $symbol
            ];
        
            ksort($params);
            $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            $timestamp = round(microtime(true) * 1000);
        
            // Generate signature
            $signaturePayload = $timestamp . $apiKey . $recvWindow . $queryString;
            $signature = hash_hmac('sha256', $signaturePayload, $apiSecret);
        
            // Make HTTP request
            $response = Http::withHeaders([
                'X-BAPI-API-KEY'     => $apiKey,
                'X-BAPI-TIMESTAMP'   => $timestamp,
                'X-BAPI-RECV-WINDOW' => $recvWindow,
                'X-BAPI-SIGN'        => $signature,
            ])->get($baseUrl . '/v5/position/list?' . $queryString);
        
            // Check if the API call was successful
            if (!$response->successful()) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Failed to fetch position info.',
                ]);
            }
        
            // Decode position list
            $positions = collect($response->json('result.list'));

            // Find matching position
            $position = $positions->first(function ($pos) use ($symbol, $side) {
                return $pos['symbol'] === $symbol && !empty($pos['size']);
            });
        
            if (empty($position)) {
                return [
                    'status' => false,
                    'msg' => 'No matching open position found for this symbol and side.'
                ];
            }
        
            // Extract cost and value 
            $orderValue = isset($position['positionValue']) ? (float) $position['positionValue'] : 0;
            $orderCost  = isset($position['positionIM']) ? (float) $position['positionIM'] : 0;
            $qty        = isset($position['size']) ? (float) $position['size'] : 0;
            $liqPrice   = isset($position['liqPrice']) ? (float) $position['liqPrice'] : 0;
            $unrealisedPnl   = isset($position['unrealisedPnl']) ? (float) $position['unrealisedPnl'] : 0;
            $markPrice   = isset($position['markPrice']) ? (float) $position['markPrice'] : 0;
            $avgPrice   = isset($position['avgPrice']) ? (float) $position['avgPrice'] : 0;
            $leverage   = isset($position['leverage']) ? (float) $position['leverage'] : 0;
        
            return [
                'status' => true,
                'symbol' => $symbol,
                'side' => $side,
                'qty' => $qty,
                'orderValue' => $orderValue,
                'orderCost' => $orderCost,
                'liqPrice' => $liqPrice,
                'unrealisedPnl' => $unrealisedPnl,
                'markPrice' => $markPrice,
                'avgPrice' => $avgPrice,
                'leverage' => $leverage,
            ];
        } catch (\Throwable $th) {
            Log::info("Position Status: $th");
        }
    }
    public function orderStatus(Request $req)
    {
        $user_id = $req->user_id;
        $apiKeys = $this->apiKeys($user_id);
        if (!$apiKeys["status"]) {
            return response()->json([
                'retMsg' => $apiKeys["msg"]
            ]);
        }

        // check license
        $licenseApis = $this->licenseApiValidation($user_id);
        if(!$licenseApis["status"]){
            return response()->json([
                "retMsg" => $licenseApis["msg"]
            ]);
        }

        $apiKey     = $apiKeys["api_key"];
        $apiSecret  = $apiKeys["api_secret"];
        $baseUrl    = $this->baseUrl;
        $recvWindow = 5000;

        $orderId = (string) $req->orderId;
        $symbol  = (string) $req->symbol; // still required

        try {
            $timestamp = round(microtime(true) * 1000);
            $query = [
                'category' => 'linear', // or 'inverse', 'option', 'spot'
                'symbol'   => $symbol,
                'orderId'  => $orderId
            ];

            // Build query string for signing
            $queryStr = http_build_query($query);
            $payload = $timestamp . $apiKey . $recvWindow . $queryStr;
            $signature = hash_hmac('sha256', $payload, $apiSecret);

            $response = Http::withHeaders([
                'X-BAPI-API-KEY'     => $apiKey,
                'X-BAPI-TIMESTAMP'   => $timestamp,
                'X-BAPI-RECV-WINDOW' => $recvWindow,
                'X-BAPI-SIGN'        => $signature,
            ])->get($baseUrl . '/v5/order/realtime', $query);

            $json = $response->json();

            if(isset($json["result"]["list"][0]["symbol"]) && $json["result"]["list"][0]["leavesValue"]){
                return response()->json([
                    "status" => true,
                    "symbol" => $json["result"]["list"][0]["symbol"],
                    "amount" => $json["result"]["list"][0]["leavesValue"],
                ]);
            }else{
                return response()->json([
                    "status" => false
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                "status" => false
            ]);
        }
    }

    // listPosition
    public function listPosition($user_id)
    {
        // Get API credentials
        $apiKeys = $this->apiKeys($user_id);
        if (!$apiKeys["status"]) {
            return [
                'status' => false,
                'msg' => $apiKeys["msg"]
            ];
        }
    
        $apiKey = $apiKeys["api_key"];
        $apiSecret = $apiKeys["api_secret"];
        $baseUrl = $this->baseUrl;
        $recvWindow = 5000;
        $timestamp = round(microtime(true) * 1000);
    
        // Common headers
        $headers = [
            'X-BAPI-API-KEY'     => $apiKey,
            'X-BAPI-TIMESTAMP'   => $timestamp,
            'X-BAPI-RECV-WINDOW' => $recvWindow,
        ];
    
        // =================== POSITION INFO ===================
        $params = [
            'category'    => 'linear',
            'settleCoin'  => 'USDT'
        ];
    
        ksort($params);
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $signaturePayload = $timestamp . $apiKey . $recvWindow . $queryString;
        $signature = hash_hmac('sha256', $signaturePayload, $apiSecret);
    
        $positionResponse = Http::withHeaders(array_merge($headers, [
            'X-BAPI-SIGN' => $signature,
        ]))->get($baseUrl . '/v5/position/list?' . $queryString);
    
        if (!$positionResponse->successful()) {
            return [
                'status' => false,
                'msg' => 'Failed to fetch position info.',
                'log' => $positionResponse
            ];
        }
    
        $positions = collect($positionResponse->json('result.list'));
    
        // =================== WALLET BALANCE ===================
        $walletParams = [
            'accountType' => 'UNIFIED',
            'coin' => 'USDT',
        ];
    
        $walletQuery = http_build_query($walletParams);
        $walletSignaturePayload = $timestamp . $apiKey . $recvWindow . $walletQuery;
        $walletSignature = hash_hmac('sha256', $walletSignaturePayload, $apiSecret);
    
        $walletResponse = Http::withHeaders(array_merge($headers, [
            'X-BAPI-SIGN' => $walletSignature
        ]))->get($baseUrl . '/v5/account/wallet-balance', $walletParams);
    
        if ($walletResponse->failed() || $walletResponse->json('retMsg') !== "OK") {
            return [
                'status' => false,
                'hint' => "API_CONNECTION",
                'msg' => null
            ];
        }
    
        $availableBalance = data_get($walletResponse->json(), 'result.list.0.totalAvailableBalance');
    
        // =================== ACCOUNT INFO ===================
        $accountSignaturePayload = $timestamp . $apiKey . $recvWindow;
        $accountSignature = hash_hmac('sha256', $accountSignaturePayload, $apiSecret);
    
        $accountResponse = Http::withHeaders(array_merge($headers, [
            'X-BAPI-SIGN' => $accountSignature
        ]))->get($baseUrl . '/v5/account/info');
    
        if ($accountResponse->failed()) {
            return [
                'status' => false,
                'hint' => "API",
                'msg' => null
            ];
        }
    
        $accountData = $accountResponse->json();
        $marginMode = data_get($accountData, 'result.marginMode');
        $spotHedgingStatus = data_get($accountData, 'result.spotHedgingStatus');
    
        // =================== FINAL RESPONSE ===================
        return [
            "status" => true,
            "positions" => $positions,
            "wallet" => [
                'available_balance' => $availableBalance,
                'derivatives_enabled' => $marginMode !== 'ISOLATED_MARGIN',
                'spot_trading_enabled' => $spotHedgingStatus === 'ON',
            ]
        ];
    }

    
    // open partial trade  https://thechainguard.com/api/bybit/test?user_id=6062724880&symbol=MNTUSDT&qty=222&side=Buy
    public function openPartialTrade(Request $req)
    {
        // get API 
        $user_id = $req->user_id;
        $apiKeys = $this->apiKeys($user_id);
        if (!$apiKeys["status"]) {
            return response()->json([
                'retMsg' => $apiKeys["msg"]
            ]);
        }
        
        // check license
        $licenseApis = $this->licenseApiValidation($user_id);
        if(!$licenseApis["status"]){
            return response()->json([
                "retMsg" => $licenseApis["msg"]
            ]);
        }
    
        $apiKey = $apiKeys["api_key"];
        $apiSecret = $apiKeys["api_secret"];
        $baseUrl = $this->baseUrl;
        $recvWindow = 5000;
    
        // Parameters
        $symbol     = (string) $req->symbol;
        $side       = (string) $req->side;
        $tpLevels       = $req->takeProfits;

        // get status 
        $positionStatus = $this->positionStatus($req);
        $positionQty = $positionStatus["qty"];
        
        // 3. Place Take Profit Orders (Sell at different prices)
        $tpResponses = [];
        foreach ($tpLevels as $tp) {
            $partialQty = (string) $tp['qty'];
            $tpPrice = (string) $tp['price'];

            $tpTimestamp3 = round(microtime(true) * 1000);
            $tpParams = [
                'category'     => 'linear',
                'symbol'       => $symbol,
                'side'         => $side,
                'orderType'    => 'Limit',
                'qty'          => $partialQty,
                'price'        => $tpPrice,
                'timeInForce'  => 'GTC',
                'reduceOnly'   => true,
            ];
            $tpPayload3 = json_encode($tpParams, JSON_UNESCAPED_SLASHES);
            $tpSig = hash_hmac('sha256', $tpTimestamp3 . $apiKey . $recvWindow . $tpPayload3, $apiSecret);

            $tpResp = Http::withHeaders([
                'X-BAPI-API-KEY'     => $apiKey,
                'X-BAPI-TIMESTAMP'   => $tpTimestamp3,
                'X-BAPI-RECV-WINDOW' => $recvWindow,
                'X-BAPI-SIGN'        => $tpSig,
                'Content-Type'       => 'application/json',
            ])->withBody($tpPayload3, 'application/json')
            ->post($baseUrl . '/v5/order/create');

            $tpResponses[] = $tpResp->json();
        }
        
        return response()->json($tpResponses);
    }

    // close order
    public function closeTrade(Request $req)
    {
        // API  
        $user_id = $req->user_id;
        $apiKeys = $this->apiKeys($user_id);
        if (!$apiKeys["status"]) {
            return response()->json([
                'status' => false,
                'msg' => $apiKeys["msg"]
            ]);
        }
        
        // check license
        $licenseApis = $this->licenseApiValidation($user_id);
        if(!$licenseApis["status"]){
            return response()->json([
                'status' => false,
                "msg" => $licenseApis["msg"]
            ]);
        }
    
        $apiKey     = $apiKeys["api_key"];
        $apiSecret  = $apiKeys["api_secret"];
        $baseUrl    = $this->baseUrl;
        $recvWindow = 5000;
    
        $orderId = (string) $req->orderId;
        $symbol  = (string) $req->symbol;  // <-- REQUIRED
    
        try {
            $timestamp = round(microtime(true) * 1000);
            $body = [
                'category' => 'linear',
                'orderId' => $orderId,
                'symbol'  => $symbol
            ];
            $payload = $timestamp . $apiKey . $recvWindow . json_encode($body, JSON_UNESCAPED_SLASHES);
            $signature = hash_hmac('sha256', $payload, $apiSecret);
    
            $response = Http::withHeaders([
                'X-BAPI-API-KEY'     => $apiKey,
                'X-BAPI-TIMESTAMP'   => $timestamp,
                'X-BAPI-RECV-WINDOW' => $recvWindow,
                'X-BAPI-SIGN'        => $signature,
                'Content-Type'       => 'application/json',
            ])->post($baseUrl . '/v5/order/cancel', $body);
    
            $json = $response->json();
            
            return response()->json([
                "status" => true,
                "msg" => $json
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                'msg' => $e->getMessage()
            ], 500);
        }
    }
    
    // close position 
    public function closePosition(Request $req)
    {
        $user_id = $req->user_id;
        $apiKeys = $this->apiKeys($user_id);
        if (!$apiKeys["status"]) {
            return response()->json([
                'status' => false,
                'msg' => $apiKeys["msg"]
            ]);
        }
        
        // check license
        $licenseApis = $this->licenseApiValidation($user_id);
        if(!$licenseApis["status"]){
            return response()->json([
                'status' => false,
                "msg" => $licenseApis["msg"]
            ]);
        }
    
        $apiKey     = $apiKeys["api_key"];
        $apiSecret  = $apiKeys["api_secret"];
        $baseUrl    = $this->baseUrl;
        $recvWindow = 5000;
    
        $symbol     = (string) $req->symbol;     // e.g., 'BTCUSDT'
        $qty        = (string) $req->qty;        // exact open position qty
        $side       = (string) $req->side;       // 'Buy' to close Short, 'Sell' to close Long

        try {
            $timestamp = round(microtime(true) * 1000);
            $params = [
                'category'     => 'linear',
                'symbol'       => $symbol,
                'side'         => $side,             // Opposite of position direction
                'orderType'    => 'Market',
                'qty'          => $qty,
                'reduceOnly'   => true               // IMPORTANT: ensures only closing trade
            ];
    
            $payload = json_encode($params, JSON_UNESCAPED_SLASHES);
            $signature = hash_hmac('sha256', $timestamp . $apiKey . $recvWindow . $payload, $apiSecret);
    
            $response = Http::withHeaders([
                'X-BAPI-API-KEY'     => $apiKey,
                'X-BAPI-TIMESTAMP'   => $timestamp,
                'X-BAPI-RECV-WINDOW' => $recvWindow,
                'X-BAPI-SIGN'        => $signature,
                'Content-Type'       => 'application/json',
            ])->post($baseUrl . '/v5/order/create', $params);
    
            $json = $response->json();
    
            return response()->json([
                "status" => true,
                "msg" => $json
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'msg' => $e->getMessage()
            ], 500);
        }
    }
    
    // update TP/SL 
    public function updateTradeTPStop(Request $req)
    {
        $user_id = $req->user_id;
        $apiKeys = $this->apiKeys($user_id);
        if (!$apiKeys["status"]) {
            return response()->json([
                'retMsg' => $apiKeys["msg"]
            ]);
        }
    
        // License check if you have it
        $licenseApis = $this->licenseApiValidation($user_id);
        if (!$licenseApis["status"]) {
            return response()->json([
                "retMsg" => $licenseApis["msg"]
            ]);
        }
    
        $apiKey = $apiKeys["api_key"];
        $apiSecret = $apiKeys["api_secret"];
        $baseUrl = $this->baseUrl;
        $recvWindow = 5000;
    
        // Parameters
        $symbol = (string) $req->symbol;
        $takeProfit = (string) $req->takeProfit ?? null;
        $stopLoss = (string) $req->stopLoss ?? null;
        $orderId = (string) $req->order_id ?? null;
    
        if (!$orderId) {
            return response()->json([
                'retMsg' => 'Order ID is required to update TP/SL.'
            ]);
        }
    
        $timestamp = round(microtime(true) * 1000);
    
        $params = [
            'category' => 'linear',
            'symbol' => $symbol,
            'orderId' => $orderId,
            'takeProfit' => $takeProfit
        ];
    
        // Only add if provided
        if ($takeProfit) {
            $params['takeProfit'] = $takeProfit;
        }
    
        if ($stopLoss) {
            $params['stopLoss'] = $stopLoss;
        }
    
        // If you want to cancel TP/SL, you might pass "0" or remove the param, check Bybit API docs.

        $payload = json_encode($params, JSON_UNESCAPED_SLASHES);
        $sign = hash_hmac('sha256', $timestamp . $apiKey . $recvWindow . $payload, $apiSecret);
    
        $response = Http::withHeaders([
            'X-BAPI-API-KEY' => $apiKey,
            'X-BAPI-TIMESTAMP' => $timestamp,
            'X-BAPI-RECV-WINDOW' => $recvWindow,
            'X-BAPI-SIGN' => $sign,
            'Content-Type' => 'application/json',
        ])->post($baseUrl . '/v5/position/trading-stop', $params);
    
        if (!$response->successful()) {
            return response()->json([
                'retMsg' => 'Failed to update take profit / stop loss.'
            ]);
        }
    
        return response()->json($response->json());
    }

    // set leverage 
    private function setLeverage($symbol, $leverage, $apiKey, $apiSecret){
        $baseUrl = $this->baseUrl;

        $recvWindow = 5000;
        $timestamp1 = round(microtime(true) * 1000);
        $leverageParams = [
            'category'     => 'linear',
            'symbol'       => $symbol,
            'buyLeverage'  => $leverage,
            'sellLeverage' => $leverage,
        ];
        $leveragePayload = json_encode($leverageParams, JSON_UNESCAPED_SLASHES);
        $leverageSig = hash_hmac('sha256', $timestamp1 . $apiKey . $recvWindow . $leveragePayload, $apiSecret);

        $maxRetries = 3;
        $attempt = 0;
        $leverageResp = null;

        do {
            $attempt++;
            $leverageResp = Http::withHeaders([
                'X-BAPI-API-KEY'     => $apiKey,
                'X-BAPI-TIMESTAMP'   => $timestamp1,
                'X-BAPI-RECV-WINDOW' => $recvWindow,
                'X-BAPI-SIGN'        => $leverageSig,
                'Content-Type'       => 'application/json',
            ])->post($baseUrl . '/v5/position/set-leverage', $leverageParams);

            if ($leverageResp->successful()) {
                break;
            }

            usleep(200000); // wait 200ms before retry
        } while ($attempt < $maxRetries);

        if (!$leverageResp || !$leverageResp->successful()) {
            return [
                "status" => false,
                "msg" => "Something went wrong. Please try again later."
            ];
        }

        return [
            "status" => true,
        ];
    }

    
    // Others 
    private function apiKeys($user_id)
    {
        $user_id = (string)$user_id;
        $user = TelegramUser::firstOrCreate(['chat_id' => $user_id]);
        
        return [
            "status" => true,
            "api_key" => $user->bybit_api_key,
            "api_secret" => $user->bybit_api_secret,
        ];
    }
    private function licenseApiValidation($user_id)
    {
        $user_id = (string)$user_id;
        $user = TelegramUser::firstOrCreate(['chat_id' => $user_id]);
        
        // check license 
        if(empty($user->expired_in)){
            return [
                "status" => false,
                "msg" => 'Subscribe to SignalTrader to get this feature.',
                'hint' => 'LICENSE'
            ];
        }
        
        // check license validity
        $expired_in = $user->expired_in;
        $expired = Carbon::parse($expired_in);
        $now = Carbon::now();
        $diffInSeconds = $now->diffInSeconds($expired, false);
        if($diffInSeconds < 1){
            return [
                "status" => false,
                "msg" => 'Your package has expired. Please renew.',
                'hint' => 'EXPIRED'
            ];
        }
        
        // check api keys 
        if(empty($user->bybit_api_key) || empty($user->bybit_api_secret)){
            return [
                "status" => false,
                "msg" => 'Connect your Bybit API keys to enjoy this feature.',
                'hint' => 'API'
            ];
        }
        
        return [
            "status" => true,
            'msg' => '',
            'hint' => ''
        ];
    }
}

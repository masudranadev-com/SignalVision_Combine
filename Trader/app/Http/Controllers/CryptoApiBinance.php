<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TelegramUser;
use Carbon\Carbon;
use App\Services\BinanceService;

class CryptoApiBinance extends Controller
{
    protected $binanceService;
    public function __construct(BinanceService $binanceService)
    {
        $this->binanceService = $binanceService;
    }

    // test 
    public function test()
    {
        $wallet = $this->allPositions();
        
        return $wallet;
    }

    // Get Balance
    public function getBalance($user_id)
    {
        // check license
        $licenseApis = $this->licenseApiValidation($user_id);
        if(!$licenseApis["status"]){
            return [
                "status" => false,
                "msg" => $licenseApis["msg"],
                "hint" => $licenseApis["hint"],
            ];
        }

        // check api empty or not  
        $apiKeys = $this->apiKeys($user_id);
        if (!$apiKeys["status"]) {
            return [
                'status' => false,
                'msg' => $apiKeys["msg"],
                'hint' => "API",
            ];
        }
        
        // get balance 
        $apiKey = $apiKeys["api_key"];
        $apiSecret = $apiKeys["api_secret"];
        $result = $this->binanceService->binanceGetWalletBalance($apiKey, $apiSecret);

        if ($result['status']) {
            return[
                'status' => true,
                'msg' => 'Balance retrieved successfully!',
                'available_balance' => $result['available_balance'],
            ];
        }else{
            return[
                'status' => false,
                'msg' => $result['error']['msg'] ?? 'Failed to retrieve balance',
                'hint' => "API_CONNECTION",
            ];
        }
    }

    // placeOrder
    public function placeOrder(Request $request)
    {
        $user_id = $request->user_id;
        $symbol = $request->symbol;
        $qty = $request->qty;
        $entryPrice = $request->entryPrice;
        $stopLoss = $request->stopLoss;
        $takeProfit = $request->takeProfit;
        $leverage = $request->leverage;
        $side = $request->type;

        // check license
        $licenseApis = $this->licenseApiValidation($user_id);
        if(!$licenseApis["status"]){
            return [
                "status" => false,
                "msg" => $licenseApis["msg"],
                "hint" => $licenseApis["hint"],
            ];
        }

        // check api empty or not  
        $apiKeys = $this->apiKeys($user_id);
        if (!$apiKeys["status"]) {
            return [
                'status' => false,
                'msg' => $apiKeys["msg"],
                'hint' => "API",
            ];
        }

        // get balance 
        $apiKey = $apiKeys["api_key"];
        $apiSecret = $apiKeys["api_secret"];

        $result = $this->binanceService->binancePlaceOrderWithSlTpAndLeverage(
            symbol: $symbol,
            side: $side,
            quantity: $qty,
            price: $entryPrice,
            leverage: $leverage,
            stopLossPrice: $stopLoss,
            takeProfitPrice: $takeProfit,
            apiKey: $apiKey,
            apiSecret: $apiSecret,
        );

        if ($result['success']) {
            return response()->json([
                'status' => true,
                'data' => $result['data'],
            ], 200);
        }

        return response()->json([
            'status' => true,
            'msg' => $result['error'],
            'hint' => "API",
        ]);
    }
    public function openPartialTrade(Request $request)
    {
        $user_id    = $request->user_id;
        $symbol     = $req->symbol;
        $side       = $req->side;
        $leverage       = $req->leverage;
        $takeProfits= $req->takeProfits;

        // check api empty or not  
        $apiKeys = $this->apiKeys($user_id);
        if (!$apiKeys["status"]) {
            return [
                'status' => false,
                'msg' => $apiKeys["msg"],
                'hint' => "API",
            ];
        }

        // get balance 
        $apiKey = $apiKeys["api_key"];
        $apiSecret = $apiKeys["api_secret"];

        $tpResponses = [];
        foreach ($takeProfits as $tp) {
            $partialQty = $tp['qty'];
            $tpPrice = $tp['price'];

            $tpResponses[] = $this->binanceService->binancePlaceOrderWithSlTpAndLeverage(
                symbol: $symbol,
                side: $side,
                quantity: $qty,
                price: $tpPrice,
                leverage: $leverage,
                stopLossPrice: null,
                takeProfitPrice: null,
                apiKey: $apiKey,
                apiSecret: $apiSecret,
            );
        }

        return response()->json([
            'status' => true,
            'msg' => $tpResponses,
        ]);
    }
    
    // Cancel Order
    public function cancelOrder(Request $request)
    {
        $user_id = $request->user_id;
        $symbol = $request->symbol;
        $orderId = $request->orderId;

        // check license
        $licenseApis = $this->licenseApiValidation($user_id);
        if(!$licenseApis["status"]){
            return [
                "status" => false,
                "msg" => $licenseApis["msg"],
                "hint" => $licenseApis["hint"],
            ];
        }

        // check api empty or not  
        $apiKeys = $this->apiKeys($user_id);
        if (!$apiKeys["status"]) {
            return [
                'status' => false,
                'msg' => $apiKeys["msg"],
                'hint' => "API",
            ];
        }

        // get balance 
        $apiKey = $apiKeys["api_key"];
        $apiSecret = $apiKeys["api_secret"];

        $result = $this->binanceService->cancelOrder($symbol, $orderId, $apiKey, $apiSecret);

        if ($result['success']) {
            return response()->json([
                'status' => true,
                'data' => $result['data'],
            ], 200);
        }

        return response()->json([
            'status' => true,
            'hint' => "API",
            'msg' => $result['error'],
        ]);
    }

    // close position 
    public function closePosition(Request $request)
    {
        $user_id = $request->user_id;
        $symbol = $request->symbol;

        // check license
        $licenseApis = $this->licenseApiValidation($user_id);
        if(!$licenseApis["status"]){
            return [
                "status" => false,
                "msg" => $licenseApis["msg"],
                "hint" => $licenseApis["hint"],
            ];
        }

        // check api empty or not  
        $apiKeys = $this->apiKeys($user_id);
        if (!$apiKeys["status"]) {
            return [
                'status' => false,
                'msg' => $apiKeys["msg"],
                'hint' => "API",
            ];
        }

        // get balance 
        $apiKey = $apiKeys["api_key"];
        $apiSecret = $apiKeys["api_secret"];

        $result = $this->binanceService->binanceClosePosition($symbol, $apiKey, $apiSecret);

        if ($result['success']) {
            return response()->json([
                'status' => true,
                'msg' => 'Position closed successfully!',
                'data' => $result['data'],
            ], 200);
        }

        return response()->json([
            'status' => false,
            'msg' => $result['error'],
            'hint' => "API",
        ], $result['status'] ?? 500);
    }
    
    // positions 
    public function allPositions($user_id)
    {
        // check license
        $licenseApis = $this->licenseApiValidation($user_id);
        if(!$licenseApis["status"]){
            return [
                "status" => false,
                "msg" => $licenseApis["msg"],
                "hint" => $licenseApis["hint"],
            ];
        }

        // check api empty or not  
        $apiKeys = $this->apiKeys($user_id);
        if (!$apiKeys["status"]) {
            return [
                'status' => false,
                'msg' => $apiKeys["msg"],
                'hint' => "API",
            ];
        }
        
        // get balance 
        $apiKey = $apiKeys["api_key"];
        $apiSecret = $apiKeys["api_secret"];

        $positions = $this->binanceService->binanceGetAllPositions($apiKey, $apiSecret);
        $walletBalance = $this->getBalance($user_id);
        $availableBalance = $walletBalance["available_balance"] ?? 0;

        return [
            "positions" => $positions,
            "balance" => formatNumberFlexible($availableBalance, 2)
        ];
    }
    public function positionStatus(Request $request)
    {
        $user_id = $request->user_id;
        $symbol = $request->symbol;

        // check license
        $licenseApis = $this->licenseApiValidation($user_id);
        if(!$licenseApis["status"]){
            return [
                "status" => false,
                "msg" => $licenseApis["msg"],
                "hint" => $licenseApis["hint"],
            ];
        }

        // check api empty or not  
        $apiKeys = $this->apiKeys($user_id);
        if (!$apiKeys["status"]) {
            return [
                'status' => false,
                'msg' => $apiKeys["msg"],
                'hint' => "API",
            ];
        }
        
        // get balance 
        $apiKey = $apiKeys["api_key"];
        $apiSecret = $apiKeys["api_secret"];

        $positions = $this->binanceService->binanceGetPositionInfo($symbol, $apiKey, $apiSecret);

        return [
            "positionAmt" => $positions["data"]["positionAmt"] ?? '0',
            'orderCost' => ($positions['data']['notional']/$positions['data']['leverage']) !== null? $positions['data']['notional']/$positions['data']['leverage'] : 0,
            "entryPrice" => $positions["data"]["entryPrice"] ?? '0',
            "markPrice" => $positions["data"]["markPrice"] ?? '0',
            "unrealisedPnl" => $positions["data"]["unRealizedProfit"] ?? '0',
            "liqPrice" => $positions["data"]["liquidationPrice"] ?? '0',
        ];
    }

    // Others 
    private function apiKeys($user_id)
    {
        $user_id = (string)$user_id;
        $user = TelegramUser::firstOrCreate(['chat_id' => $user_id]);

        // check api null  ? 
        if(empty($user->binanace_api_key) || empty($user->binanace_api_secret)){
            return [
                "status" => false,
                "msg" => 'Connect your Binance API keys to enjoy this feature.',
            ];
        }
        
        return [
            "status" => true,
            "api_key" => $user->binanace_api_key,
            "api_secret" => $user->binanace_api_secret,
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

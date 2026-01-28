<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BinanceAPIController extends Controller
{
    //createOrder
    public function createOrder($schedule, $orderType)
    {
        // const data 
        $user_id = $schedule->chat_id;
        $instruments = $schedule->instruments;
        $entry_target = $schedule->entry_target;
        $stop_loss = $schedule->stop_loss;
        $leverage = $schedule->leverage;

        // profit  
        $takeProfit = null;
        if($schedule->profit_strategy === "close_specific_tp"){
            $prop = "take_profit{$schedule->specific_tp}";
            $takeProfit = $schedule->$prop;
        }

        // mod 
        $type = $schedule->tp_mode === "SHORT" ? "SELL" : "BUY";
        
        // qty 
        $investment = $schedule->position_size_usdt;
        $qty = formatNumberFlexible((($investment*$leverage) / $entry_target), $schedule->qty_step);

        $response = Http::withHeaders([
            'CRYPTO-API-SECRET' => config('services.api.ctypto_secret')
        ])->post(config('services.api.ctypto_end_point').'/api/binance/open-trade', [
            "user_id" => $user_id,
            "symbol" => $instruments,
            "qty" => $qty,
            "entryPrice" => $orderType === "market" ? null : $entry_target,
            "stopLoss" => $stop_loss,
            "takeProfit" => $takeProfit,
            "leverage" => $leverage,
            "type" => $type
        ]);

        Log::info($response);
        
        if (!$response->successful()) {
            return [
                "status" => false,
                "msg" => "Something went wrong. Please try again later.",
            ];
        }

        if($orderType === "market"){
            // order val  
            $entryPrice = $response["data"]["position"]["entryPrice"] ?? null;

            $orderValue = formatNumberFlexible($qty * $entry_target, 2);
            $actualUSDT = formatNumberFlexible($orderValue / $leverage, 2);

            $positionAmount = $response["data"]["position"]["margin"] ?? $actualUSDT;

            $schedule->entry_target = $entryPrice;
            $schedule->position_size_usdt = formatNumberFlexible($positionAmount, 2);
            $schedule->save();
        }else{
            // order val 
            $orderValue = formatNumberFlexible($qty * $entry_target, 2);
            $actualUSDT = formatNumberFlexible($orderValue / $leverage, 2);
            $schedule->position_size_usdt = $actualUSDT;
            $schedule->save();
        }

        // const data 
        $retMsg = $response["msg"]["msg"] ?? null;
        $orderId = $response["data"]["order"]["orderId"] ?? null;
        $hint = $response["hint"] ?? null;
        if(empty($orderId)){
            return [
                "status" => false,
                "msg" => $retMsg,
            ];
        }else{
            $schedule->trade_id = $orderId; 
            $schedule->save();
            return [
                "status" => true,
                "order_id" => $orderId
            ];
        }
    }

    public function closedTrade($schedule)
    {
        // const data 
        $user_id = $schedule->chat_id;
        $instruments = $schedule->instruments;
        
        if($schedule->status === "running"){
            // mod 
            $type = $schedule->tp_mode === "SHORT" ? "Buy" : "Sell";

            // qty 
            $intQty = $schedule->position_size_usdt / $schedule->entry_target;
            $qty = formatNumberFlexible($intQty, $schedule->qty_step);

            $response = $this->closedPosition($user_id, $instruments, $qty, $type);
        }else{
            $orderId = $schedule->trade_id;
            $response = $this->closedOrder($user_id, $instruments, $orderId);
        }


        return $response;
    }
    public function closedOrder($user_id, $instruments, $orderId)
    {
        $response = Http::withHeaders([
            'CRYPTO-API-SECRET' => config('services.api.ctypto_secret')
        ])->post(config('services.api.ctypto_end_point').'/api/binance/close-trade', [
            "user_id" => $user_id,
            "symbol" => $instruments,
            "orderId" => $orderId,
        ]);

        if (!$response->successful()) {
            return [
                "status" => false,
                "msg" => "Something went wrong. Please try again later. Code: 123"
            ];
        }

        // const data 
        $retMsg = $response["msg"]["msg"] ?? "Something went wrong!";
        $orderId = $response["data"]["orderId"] ?? null;
        if(empty($orderId)){
            return [
                "status" => false,
                "msg" => $retMsg,
            ];
        }else{
            return [
                "status" => true,
                "order_id" => $orderId
            ];
        }
    }
    public function closedPosition($user_id, $instruments, $qty, $type)
    {
        $response = Http::withHeaders([
            'CRYPTO-API-SECRET' => config('services.api.ctypto_secret')
        ])->post(config('services.api.ctypto_end_point').'/api/binance/close-position', [
            "user_id" => $user_id,
            "symbol" => $instruments,
            "qty" => $qty,
            "side" => $type,
        ]);

        Log::info($response);

         // order val
        if (!$response->successful()) {
            return [
                "status" => false,
                "msg" => "Something went wrong. Please try again later Code: 124."
            ];
        }

        // const data 
        $retMsg = $response["msg"] ?? "Something went wrong!";
        $orderId = $response["result"]["orderId"] ?? null;
        if(empty($orderId)){
            return [
                "status" => false,
                "msg" => $retMsg,
            ];
        }else{
            return [
                "status" => true,
                "order_id" => $orderId
            ];
        }
    }

    // partialTrade
    public function partialTrade($schedule)
    {
        // const data 
        $user_id = $schedule->chat_id;
        $instruments = $schedule->instruments;

        // mod 
        $type = $schedule->tp_mode === "SHORT" ? "Buy" : "Sell";
        // qty 
        $intQty = $schedule->position_size_usdt / $schedule->entry_target;
        $qty = formatNumberFlexible($intQty, $schedule->qty_step);

        // take profits  
        $tpLevels = collect([
            $schedule->take_profit1,
            $schedule->take_profit2,
            $schedule->take_profit3,
            $schedule->take_profit4,
            $schedule->take_profit5,
            $schedule->take_profit6,
            $schedule->take_profit7,
            $schedule->take_profit8,
            $schedule->take_profit9,
            $schedule->take_profit10,
        ]);

        $takeProfits = [];
        for ($i = 0; $i <= 9; $i++) {
            $tp = $tpLevels[$i];
            $tpIndex = $i+1;
            $prop = "partial_profits_tp".$tpIndex;
            $partial = $schedule[$prop];

            if(!empty($schedule[$prop])){
                $takeProfits[] = [
                    "price" => $tp,
                    "qty" => formatNumberFlexible((($qty/100) * $partial), $schedule->qty_step)
                ];
            }
        }

        $response = Http::withHeaders([
            'CRYPTO-API-SECRET' => config('services.api.ctypto_secret')
        ])->post(config('services.api.ctypto_end_point').'/api/bybit/open-partial-trade', [
            "user_id" => $user_id,
            "symbol" => $instruments,
            "leverage" => $schedule->leverage,
            "side" => $type,
            "takeProfits" => $takeProfits,
        ]);

        if (!$response->successful()) {
            return [
                "status" => false,
                "msg" => "Something went wrong. Please try again later Code: 124."
            ];
        }

        $jsonRes = $response->json();
        $orderIds = [];
        foreach ($jsonRes as $value) {
            // const data 
            $retMsg = $value["retMsg"];
            $orderId = $value["result"]["orderId"] ?? null;
            if(empty($orderId)){
                Telegram::sendMessage([
                    'chat_id' => $trade->chat_id,
                    'text' => <<<EOT
                    <b>🛠️ Adjust this setting in your Bybit account.</b>
                    {$retMsg}
                    EOT,
                    'parse_mode' => 'HTML',
                ]);

            }else{
                $orderIds[] = $orderId;
            }
        }
        // save in trdae 
        if (!empty($orderIds) && is_array($orderIds)) {
            $schedule->partial_order_ids = $orderIds;
            $schedule->save();
        }
    }

    // position status 
    public function positionStatus($schedule)
    {
        // const data 
        $user_id = $schedule->chat_id;
        $instruments = $schedule->instruments;

        $response = Http::withHeaders([
            'CRYPTO-API-SECRET' => config('services.api.ctypto_secret')
        ])->post(config('services.api.ctypto_end_point').'/api/binance/position-status', [
            "user_id" => $user_id,
            "symbol" => $instruments
        ]);

        if (!$response->successful()) {
            return [
                "status" => false,
                "msg" => "Something went wrong. Please try again later.",
            ];
        }

        if($schedule->status === "waiting" && $response["positionAmt"] != '0'){
            startWaitingTrade($schedule, $response["markPrice"]);

            $schedule->status = "running";
            $schedule->position_size_usdt = formatNumberFlexible($response["orderCost"], 2);
            $schedule->save();
        }
        return $response;
    }
}

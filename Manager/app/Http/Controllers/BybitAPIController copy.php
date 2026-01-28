<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class BybitAPIController extends Controller
{

    //createOrder
    public function createOrder($schedule)
    {
        try {
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
            $type = $schedule->tp_mode === "SHORT" ? "Sell" : "Buy";
            
            // qty 
            $investment = $schedule->position_size_usdt;
            $qty = formatNumberFlexible((($investment*$leverage) / $entry_target), $schedule->qty_step);

            $response = Http::withHeaders([
                'CRYPTO-API-SECRET' => config('services.api.ctypto_secret')
            ])->post(config('services.api.ctypto_end_point').'/api/bybit/open-trade', [
                "user_id" => $user_id,
                "symbol" => $instruments,
                "qty" => $qty,
                "entryPrice" => $entry_target,
                "stopLoss" => $stop_loss,
                "takeProfit" => $takeProfit,
                "leverage" => $leverage,
                "type" => $type
            ]);

            // order val 
            $orderValue = formatNumberFlexible($qty * $entry_target, 2);
            $actualUSDT = formatNumberFlexible($orderValue / $leverage, 2);
            $schedule->position_size_usdt = $actualUSDT;
            $schedule->save();

            if (!$response->successful()) {
                return [
                    "status" => false,
                    "msg" => "Something went wrong. Please try again later.",
                ];
            }

            // const data 
            $retMsg = $response["retMsg"];
            $hint = $response["hint"] ?? null;
            $orderId = $response["result"]["orderId"] ?? null;
            if(empty($orderId)){
                return [
                    "status" => false,
                    "msg" => $retMsg,
                    "hint" => $hint
                ];
            }else{
                $schedule->order_id = $orderId; 
                $schedule->save();

                return [
                    "status" => true,
                    "order_id" => $orderId
                ];
            }
        } catch (\Throwable $th) {
            return [
                "status" => false,
                "msg" => "Connection faild! Code: 1",
                "hint" => "API"
            ];
        }
    }

    // market entry 
    public function marketEntry($schedule)
    {
        try {
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
            $type = $schedule->tp_mode === "SHORT" ? "Sell" : "Buy";
            
            // qty 
            $investment = $schedule->position_size_usdt;
            $qty = formatNumberFlexible((($investment*$leverage) / $entry_target), $schedule->qty_step);

            $response = Http::withHeaders([
                'CRYPTO-API-SECRET' => config('services.api.ctypto_secret')
            ])->post(config('services.api.ctypto_end_point').'/api/bybit/market-entry', [
                "user_id" => $user_id,
                "symbol" => $instruments,
                "qty" => $qty,
                "entryPrice" => $entry_target,
                "stopLoss" => $stop_loss,
                "takeProfit" => $takeProfit,
                "leverage" => $leverage,
                "type" => $type
            ]);

            // order val 
            $orderValue = formatNumberFlexible($qty * $entry_target, 2);
            $actualUSDT = formatNumberFlexible($orderValue / $leverage, 2);
            $schedule->position_size_usdt = $actualUSDT;
            $schedule->save();

            if (!$response->successful()) {
                return [
                    "status" => false,
                    "msg" => "Something went wrong. Please try again later",
                ];
            }

            // const data 
            $retMsg = $response["retMsg"];
            $hint = $response["hint"] ?? null;
            $orderId = $response["order_id"] ?? null;
            if(empty($orderId)){
                return [
                    "status" => false,
                    "msg" => $retMsg,
                    "hint" => $hint
                ];
            }else{
                $position = $response["trade_status"];

                $schedule->order_id = $orderId; 
                $schedule->entry_target = $position["avgPrice"];
                $schedule->status = "running";
                $schedule->position_size_usdt = formatNumberFlexible($position["orderCost"], 2);
                $schedule->save();

                startWaitingTrade($schedule, $position["markPrice"]);

                return [
                    "status" => true,
                    "order_id" => $orderId
                ];
            }
        } catch (\Throwable $th) {
            Log::info("CODE2: $th");
            return [
                "status" => false,
                "msg" => "Connection faild!",
                "hint" => "API"
            ];
        }
    }

    // closed Trade 
    public function closedTrade($schedule)
    {
        try {
            // const data 
            $user_id = $schedule->chat_id;
            $instruments = $schedule->instruments;
            
            if($schedule->status === "running"){
                // mod 
                $type = $schedule->tp_mode === "SHORT" ? "Buy" : "Sell";

                // qty 
                $qty = $schedule->qty;

                $response = $this->closedPosition($user_id, $instruments, $qty, $type);
            }else{
                $orderId = $schedule->order_id;
                $response = $this->closedOrder($user_id, $instruments, $orderId);
            }

            // status 
            if(!$response["status"]){
                return [
                    "status" => false,
                    "msg" => "Something went wrong!",
                    "hint" => "API"
                ];
            }

            return $response;
        } catch (\Throwable $th) {
            Log::info($th);
            return [
                "status" => false,
                "msg" => "Connection faild!",
                "hint" => "API"
            ];
        }
    }
    public function closedOrder($user_id, $instruments, $orderId)
    {
        $response = Http::withHeaders([
            'CRYPTO-API-SECRET' => config('services.api.ctypto_secret')
        ])->post(config('services.api.ctypto_end_point').'/api/bybit/close-trade', [
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
        $orderId = $response["msg"]["result"]["orderId"] ?? null;
        if(empty($orderId)){
            return [
                "status" => false,
                "msg" => $response["msg"],
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
        ])->post(config('services.api.ctypto_end_point').'/api/bybit/close-position', [
            "user_id" => $user_id,
            "symbol" => $instruments,
            "qty" => $qty,
            "side" => $type,
        ]);

        // order val
        if (!$response->successful()) {
            return [
                "status" => false,
                "msg" => "Something went wrong. Please try again later Code: 125."
            ];
        }

        // const data 
        $status = $response["status"];
        if(!$status){
            $msg = $response["msg"];
            return [
                "status" => false,
                "msg" => $msg,
            ];
        }

        $orderId = $response["msg"]["result"]["orderId"] ?? null;
        if(empty($orderId)){
            $msg = $response["msg"];

            return [
                "status" => false,
                "msg" => $msg,
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
        try {
            // const data 
            $user_id = $schedule->chat_id;
            $instruments = $schedule->instruments;

            // mod 
            $type = $schedule->tp_mode === "SHORT" ? "Buy" : "Sell";

            // get status 
            $positionStatus = $this->positionStatus($schedule);
            if(!$positionStatus["status"]){
                return [
                    "status" => false,
                    "msg" => "Something went wrong. Please try again later Code: 125."
                ];
            }

            $qty = $positionStatus["qty"];

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
                "qty" => $qty,
                "side" => $type,
                "takeProfits" => $takeProfits,
            ]);

            if (!$response->successful()) {
                return [
                    "status" => false,
                    "msg" => "Something went wrong. Please try again later Code: 126."
                ];
            }

            $jsonRes = $response->json();
            $orderIds = [];
            foreach ($jsonRes as $value) {
                // const data 
                $retMsg = $value["retMsg"];
                $orderId = $value["result"]["orderId"] ?? null;
                if(!empty($orderId)){
                    $orderIds[] = $orderId;
                }
            }
            // save in trdae 
            if (!empty($orderIds) && is_array($orderIds)) {
                $schedule->partial_order_ids = $orderIds;
                $schedule->save();
            }
        } catch (\Throwable $th) {
            Log::info("CODE2: $th");
            return [
                "status" => false,
                "msg" => "Connection faild!",
                "hint" => "API"
            ];
        }
    }

    // update Take profit  
    public function takeProfit($schedule)
    {
        // const data 
        $user_id = $schedule->chat_id;
        $instruments = $schedule->instruments;
        
        if($schedule->status === "running"){
            $this->updateTradeTPStop($schedule);
        }else{
            $orderId = $schedule->order_id; 
            $this->closedOrder($user_id, $instruments, $orderId);
            $this->createOrder($schedule);
        }
    }
    public function updateTradeTPStop($schedule)
    {
        $key = "take_profit{$schedule['specific_tp']}";

        // const data 
        $response = Http::withHeaders([
            'CRYPTO-API-SECRET' => config('services.api.ctypto_secret')
        ])->post(config('services.api.ctypto_end_point').'/api/bybit/update-trade-tp-sl', [
            "user_id" => $schedule->chat_id,
            "symbol" => $schedule->instruments,
            "takeProfit" => $schedule[$key],
            "stopLoss" => $schedule->stop_loss,
            "order_id" => $schedule->order_id,
        ]);

        // order val
        if (!$response->successful()) {
            return [
                "status" => false,
                "msg" => "Something went wrong. Please try again later Code: 125."
            ];
        }
    }

    // position status 
    public function positionStatus($schedule)
    {
        try {
            // const data 
            $user_id = $schedule->chat_id;
            $instruments = $schedule->instruments;
            $type = $schedule->tp_mode === "SHORT" ? "Buy" : "Sell";

            $response = Http::withHeaders([
                'CRYPTO-API-SECRET' => config('services.api.ctypto_secret')
            ])->post(config('services.api.ctypto_end_point').'/api/bybit/position-status', [
                "user_id" => $user_id,
                "symbol" => $instruments,
                "side" => $type,
            ]);

            if (!$response->successful()) {
                return [
                    "status" => false,
                ];
            }

            if(!$response["status"]){
                // check the position is closed manually ?
                if($schedule->status === "running"){
                    $schedule->status = "closed";
                    $schedule->save();

                    removeTradeFromCache($schedule->id);

                    return [
                        "status" => false
                    ];
                }else{
                    $orderResponse = Http::withHeaders([
                        'CRYPTO-API-SECRET' => config('services.api.ctypto_secret')
                    ])->post(config('services.api.ctypto_end_point').'/api/bybit/order-status', [
                        "user_id" => $user_id,
                        "orderId" => $schedule->order_id,
                        "symbol" => $type,
                    ]);

                    if (!$orderResponse->successful()) {
                        return [
                            "status" => false,
                        ];
                    }

                    if(!$orderResponse["status"]){
                        $schedule->status = "closed";
                        $schedule->save();

                        removeTradeFromCache($schedule->id);

                        return [
                            "status" => false
                        ];
                    }
                }
            }else{
                if($schedule->status === "waiting"){
                    startWaitingTrade($schedule, $response["markPrice"]); 

                    $schedule->status = "running";
                    $schedule->save();
                }
                $position_size_usdt = formatNumberFlexible($response["orderCost"], 2);
                $schedule->position_size_usdt = $position_size_usdt;
                $schedule->leverage = $response["leverage"];
                $schedule->qty = $response["qty"];
                $schedule->save();

                return $response;
            }
        } catch (\Throwable $th) {
            Log::info("Bybit Status: $th");
        }
    }
}

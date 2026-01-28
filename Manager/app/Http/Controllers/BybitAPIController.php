<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BybitAPIController extends Controller
{
    protected string $apiUrl;
    protected array $headers;

    public function __construct()
    {
        $this->apiUrl = rtrim(config('services.api.ctypto_end_point'), '/');
        $this->headers = [
            'CRYPTO-API-SECRET' => config('services.api.ctypto_secret'),
            'Accept' => 'application/json',
        ];
    }

    /**
     * Safely call Bybit API with unified error handling
     */
    protected function sendRequest(string $endpoint, array $payload): array
    {
        try {
            $url = "{$this->apiUrl}/api/bybit/{$endpoint}";
            $response = Http::withHeaders($this->headers)->post($url, $payload);

            if (!$response->successful()) {
                Log::warning("[Bybit] API error at {$endpoint}", [
                    'payload' => $payload,
                    'response' => $response->body(),
                ]);
                return $this->errorResponse("Bybit API request failed", 400);
            }

            return $response->json();
        } catch (Throwable $e) {
            Log::error("[Bybit] Exception at {$endpoint}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse("Connection to Bybit API failed", 500);
        }
    }

    /**
     * Standard success response
     */
    protected function successResponse(array $data = [], string $msg = 'Success'): array
    {
        return ['status' => true, 'msg' => $msg, 'data' => $data];
    }

    /**
     * Standard error response
     */
    protected function errorResponse(string $msg, int $code = 500, array $extra = []): array
    {
        return array_merge(['status' => false, 'msg' => $msg, 'code' => $code], $extra);
    }

    // ============================================================
    // ✅ CREATE CONDITIONAL ORDER
    // ============================================================
    public function createOrder($schedule): array
    {
        try {
            $userId = $schedule->chat_id;
            $symbol = $schedule->instruments;
            $entry = $schedule->entry_target;
            $stopLoss = $schedule->stop_loss;
            $leverage = $schedule->leverage;
            $side = $schedule->tp_mode === 'SHORT' ? 'Sell' : 'Buy';

            $takeProfit = null;
            if ($schedule->profit_strategy === 'close_specific_tp') {
                $prop = "take_profit{$schedule->specific_tp}";
                $takeProfit = $schedule->$prop;
            }

            $investment = $schedule->position_size_usdt;
            $qty = formatNumberFlexible((($investment * $leverage) / $entry), $schedule->qty_step);

            $payload = [
                'user_id' => $userId,
                'symbol' => $symbol,
                'qty' => $qty,
                'entryPrice' => $entry,
                'stopLoss' => $stopLoss,
                'takeProfit' => $takeProfit,
                'leverage' => $leverage,
                'side' => $side,
            ];

            // $payload = [
            //     'user_id' => "6062724880",
            //     'symbol' => "ETHUSDT",
            //     'qty' => "3",
            //     'entryPrice' => "4400",
            //     'stopLoss' => "3500",
            //     'takeProfit' => "4500",
            //     'leverage' => "7",
            //     'side' => "Buy",
            // ];

            $response = $this->sendRequest('orders/smart', $payload);

            // check market entry  
            if($response['decision']['routed'] === "market"){
                if(!$response['place_position']['status']){
                    return $this->errorResponse($response['place_position']['msg']);
                }
                startWaitingTrade($schedule, $response['decision']["markPrice"]); 

                return $this->successResponse([], 'Market entry');
            }

            // limit order
            if (!empty($response['data']['orderId'])) {
                $schedule->order_id = $response['data']['orderId'];
                $schedule->save();

                return $this->successResponse([], 'Conditional order created');
            }

            return $this->errorResponse($response['msg']);
        } catch (Throwable $e) {
            Log::error('[Bybit] createOrder exception', ['error' => $e->getMessage()]);
            return $this->errorResponse('Connection failed while creating conditional order');
        }
    }
    public function createPartialOrder($schedule)
    {
        // try {
            // const data 
            $user_id = $schedule->chat_id;
            $instruments = $schedule->instruments;

            // mod 
            $type = $schedule->tp_mode === "SHORT" ? "Buy" : "Sell";
            $qty = $schedule->qty;

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

            $payload = [
                "user_id" => $user_id,
                "symbol" => $instruments,
                "qty" => $qty,
                "side" => $type,
                "takeProfits" => $takeProfits,
            ];

            $response = $this->sendRequest('orders/partial', $payload);

            if (!$response['status']) {
                return $this->errorResponse($response['msg']);
            }

            $orders = $response['orders'];
            $orderIds = [];
            foreach ($orders as $order) {
                $orderId = $order["data"]["orderId"] ?? null;
                if(!empty($orderId)){
                    $orderIds[] = $orderId;
                }
            }

            // save in trdae 
            if (!empty($orderIds) && is_array($orderIds)) {
                $schedule->partial_order_ids = $orderIds;
                $schedule->save();
            }
        // } catch (\Throwable $th) {
        //     Log::info("CODE2: $th");
        //     return [
        //         "status" => false,
        //         "msg" => "Connection faild!",
        //         "hint" => "API"
        //     ];
        // }
    }

    // ============================================================
    // ✅ MARKET ENTRY 
    // ============================================================
    public function marketEntry($schedule): array
    {
        try {
            $userId = $schedule->chat_id;
            $symbol = $schedule->instruments;
            $entry = $schedule->entry_target;
            $stopLoss = $schedule->stop_loss;
            $leverage = $schedule->leverage;
            $side = $schedule->tp_mode === 'SHORT' ? 'Sell' : 'Buy';

            $takeProfit = null;
            if ($schedule->profit_strategy === 'close_specific_tp') {
                $prop = "take_profit{$schedule->specific_tp}";
                $takeProfit = $schedule->$prop;
            }

            $investment = $schedule->position_size_usdt;
            $qty = formatNumberFlexible((($investment * $leverage) / $entry), $schedule->qty_step);

            $payload = [
                'user_id' => $userId,
                'symbol' => $symbol,
                'qty' => $qty,
                'stopLoss' => $stopLoss,
                'takeProfit' => $takeProfit,
                'leverage' => $leverage,
                'side' => $side,
            ];

            $response = $this->sendRequest('orders/market', $payload);

            if (!($response['place_position']['status'] ?? false)) {
                return $this->errorResponse($response['place_position']['msg'] ?? 'Failed to market entry!');
            }else{

                $schedule->entry_target = $response['place_position']['position'][0]['markPrice'] ?? $schedule->entry_target;
                $schedule->leverage = $response['place_position']['position'][0]['leverage'] ?? $schedule->leverage;
                $schedule->save();

                startWaitingTrade($schedule, $response['decision']["markPrice"]); 

                return $this->successResponse([], 'Market entry success!');
            }

        } catch (Throwable $e) {
            Log::error('[Bybit] marketEntry exception', ['error' => $e->getMessage()]);
            return $this->errorResponse('Connection failed while creating conditional order');
        }
    }

    // ============================================================
    // ✅ CLOSE
    // ============================================================
    public function closeOrder($schedule): array
    {
        try {
            // check running or not 
            if($schedule->status === "running"){
                $userId = $schedule->chat_id;
                $symbol = $schedule->instruments;
                $entry = $schedule->entry_target;
                $leverage = $schedule->leverage;
                $side = $schedule->tp_mode === 'SHORT' ? 'Sell' : 'Buy';

                $qty = $schedule->qty;

                $payload = [
                    'user_id' => $userId,
                    'symbol' => $symbol,
                    'qty' => $qty,
                    'side' => $side,
                ];

                $response = $this->sendRequest('position/partial-close', $payload);

                if (!($response['status'] ?? false)) {
                    return $this->errorResponse($response['msg'] ?? 'Failed to close conditional order');
                }

                return $this->successResponse([], 'Partial closed');
            }else{
                $payload = [
                    'user_id' => $schedule->chat_id,
                    'symbol' => $schedule->instruments,
                    'order_id' => $schedule->order_id,
                ];

                $response = $this->sendRequest('order/close', $payload);

                if (!($response['status'] ?? false)) {
                    return $this->errorResponse($response['msg'] ?? 'Failed to close conditional order');
                }

                return $this->successResponse([], 'Conditional order closed');
            }
            
        } catch (Throwable $e) {
            Log::error('[Bybit] closeOrder exception', ['error' => $e->getMessage()]);
            return $this->errorResponse('Connection failed while closing conditional order');
        }
    }

    // ============================================================
    // ✅ UPDATE TP/SL ON CONDITIONAL ORDER
    // ============================================================
    public function updateTPSL($schedule, $tp, $sl)
    {
        try {
            $side = $schedule->tp_mode === 'SHORT' ? 'Sell' : 'Buy';
            $investment = $schedule->position_size_usdt;
            $qty = formatNumberFlexible((($investment * $schedule->leverage) / $schedule->entry_target), $schedule->qty_step);

            $key = "take_profit{$schedule['specific_tp']}";
            $newKey = "take_profit{$tp}";
            $payload = [
                'user_id' => $schedule->chat_id,
                'order_id' => $schedule->order_id,
                'symbol' => $schedule->instruments,
                'takeProfit' => !empty($tp) ? $schedule[$newKey] : $schedule[$key],
                'stopLoss' => !empty($sl) ? $sl : $schedule->stop_loss,
                'qty' => $qty,
                'leverage' => $schedule->leverage,
                'entry' => $schedule->entry_target,
                'side' => $side
            ];

            $response = $this->sendRequest('update/tp-sl', $payload);

            if (!$response['status']) {
                return $this->errorResponse($response['msg'] ?? 'Failed to update TP/SL');
            }

            if($response['decision_routed'] === "limit_order"){
                // check order is closed ? 
                if(!$response['close']["status"]){
                    return $this->errorResponse($response['close']["msg"] ?? "An error occurred while attempting to close your order. Kindly close it manually.");
                }

                // check new order creating 
                if(!$response['order']["status"]){
                    return $this->errorResponse($response['order']["msg"] ?? "An error occurred while attempting to creating your order. Kindly create it manually.");
                }

                $schedule->order_id = $response['order']["data"]["orderId"];
                $schedule->save();
            }

            return $this->successResponse([], 'TP/SL updated successfully');

        } catch (Throwable $e) {
            Log::error('[Bybit] Update TP/SL exception', ['error' => $e->getMessage()]);
            return $this->errorResponse('Connection failed while updating TP/SL');
        }
    }

    // ============================================================
    // ✅ POSITION STATUS (BASED ON CONDITIONAL SYSTEM)
    // ============================================================
    public function getOrderOrPosition($user_id): array
    {
        try {
            $status = $this->sendRequest('positions-order/lists/' . $user_id, []);

            return $this->successResponse([
                'orders' => $status["orders"],
                'positions' => $status["positions"],
            ], 'Order/Position verified');
        } catch (Throwable $e) {
            Log::error('[Bybit] getOrderOrPosition exception', [
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('Failed to verify order/position state');
        }
    }

}

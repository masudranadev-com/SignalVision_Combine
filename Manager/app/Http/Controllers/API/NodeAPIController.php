<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ScheduleCrypto;
use Illuminate\Support\Facades\Cache;
use App\Jobs\ScheduleNotificationJob;
use Illuminate\Support\Facades\Log;

class NodeAPIController extends Controller
{
    //notification
    public function notification(Request $request)
    {
        $data = $request->all();
        
        return; 
        // \Log::info($data);
        dispatch((new ScheduleNotificationJob($data))->onQueue('SignalManager_notification'));
    }

    // initialTrades
    public function initialTrades()
    {
        $allSchedules = Cache::remember("schedule_cryptos_running_waiting", now()->addHours(1), function () {
            return ScheduleCrypto::whereIn('status', ['running', 'waiting'])->orderBy('id', 'desc')->get();
        });

        $trades = [];
        foreach ($allSchedules as $trade) {
            // remove if something not found 
            if(empty($trade->instruments)){
                removeTradeFromCache($trade->id);
                return;
            }

            $trades[] = [
                "market" => $trade->market,
                "trade_id" => $trade->id,
                "mod" => $trade->tp_mode,
                "pair" => $trade->instruments,
                "entry" => $trade->entry_target,
                "sl" => $trade->stop_loss,
                "sl_percentage" => empty($trade->stop_loss_percentage) ? 0 : $trade->stop_loss_percentage,
                "sl_price" => empty($trade->stop_loss_price) ? 0 : $trade->stop_loss_price,
                "tp1" => $trade->take_profit1,
                "tp2" => $trade->take_profit2,
                "tp3" => $trade->take_profit3,
                "tp4" => $trade->take_profit4,
                "tp5" => $trade->take_profit5,
                "tp6" => $trade->take_profit6,
                "tp7" => $trade->take_profit7,
                "tp8" => $trade->take_profit8,
                "tp9" => $trade->take_profit9,
                "tp10" => $trade->take_profit10,
                "height_price" => $trade->height_price,
                "status" => $trade->status, 
                "last_notification" => $trade->last_alert, 
            ];
        }

        // Log::info($trades);

        return response()->json($trades);  
    }
}

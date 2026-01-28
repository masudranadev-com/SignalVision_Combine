<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Jobs\ScheduleNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use \App\Http\Controllers\BybitAPIController;
use App\Models\ScheduleCrypto;
use Log;

class ScheduleNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
    */
    public $data;
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $request = $this->data;

        $trade = ScheduleCrypto::find($request["id"]);
        if(!isset($trade)){
            \Log::info("Error");
            return;
        }

        // const 
        $type = $request["type"];
        $current_price = $request["current_price"];

        // TRailing STop 
        if($type === "trailing_stop"){
            $trade->height_price = $current_price;
            $trade->stop_loss = isset($request["data"]['sl']) ? $request["data"]['sl'] : $trade->stop_loss;
        }else if($type === "ENTRY"){
            $trade->status = "running";
            startWaitingTrade($trade, $current_price);
        }else if($type === "SL"){
            $trade->status = "closed";
            removeTradeFromCache($trade->id);
            aiAdvisorTakeLose($trade, $current_price);
        }else if(preg_match('/^TP[0-9]+$/', $type)){
            $tp = str_replace("TP", "", $type);
            if(!empty($tp)){
                $tpLevelStatus = "tp_" . $tp;

                if ($tpLevelStatus !== $trade->last_alert) {
                    $chatId = $trade->chat_id;
                    $mode = $trade->tp_mode;
                    $entry = (float)$trade->entry_target;
                    $sl = (float)$trade->stop_loss;
                    $heightPrice = (float)$trade->height_price;
                    $stopLossPercentage = (float)$trade->stop_loss_percentage;
                    $stopLossPrice = (float)$trade->stop_loss_price;
                    $heightTP = (float)$trade->height_tp;

                    $reverse = false;

                    if ($heightTP < $tp) {
                        $trade->height_tp = $tp;
                        $reverse = true;
                    }

                    $trade->last_alert = $tpLevelStatus;

                    $isFinalTrade = false;
                    if ($trade->profit_strategy === "partial_profits") {
                        $nextNotify = $tp + 1;
                        $property = 'partial_profits_tp' . $nextNotify;

                        if (empty($trade->$property)) {
                            $trade->status = "closed";
                            $isFinalTrade = true;
                            removeTradeFromCache($trade->id);
                        }
                    } elseif ($trade->profit_strategy === "close_specific_tp" && $trade->specific_tp == $tp) {
                        $trade->status = "closed";
                        $isFinalTrade = true;
                        removeTradeFromCache($trade->id);
                    }

                    $updated = true;
                    aiAdvisorTakeProfit($trade, $tp, $reverse, $current_price, $isFinalTrade);
                }
            }
        }else if($type === "ERROR"){
            errorNotifications($request["data"]["msg"]);
        }

        $trade->save();
    }
}

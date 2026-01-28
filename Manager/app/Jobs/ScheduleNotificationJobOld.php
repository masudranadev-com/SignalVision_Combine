<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Jobs\ScheduleNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use \App\Http\Controllers\BybitAPIController;
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
        $data = $this->data;
        $trade = $data["trade"];
        $currentPrice = $data["currentPrice"];

        $chatId = $trade->chat_id;
        $mode = $trade->tp_mode;
        $entry = (float)$trade->entry_target;
        $sl = (float)$trade->stop_loss;
        $heightPrice = (float)$trade->height_price;
        $stopLossPercentage = (float)$trade->stop_loss_percentage;
        $stopLossPrice = (float)$trade->stop_loss_price;
        $heightTP = (float)$trade->height_tp;

        $updated = false;

        // Fix the height price and recalculate stop loss if needed
        if ($heightPrice < $currentPrice && (!empty($stopLossPercentage) || !empty($stopLossPrice))) {
            $trade->height_price = $currentPrice;

            if (!empty($stopLossPercentage)) {
                $stopLossChange = ($currentPrice / 100) * $stopLossPercentage;
            } else {
                $stopLossChange = $stopLossPrice;
            }

            $trade->stop_loss = $mode === 'LONG' ? $currentPrice - $stopLossChange : $currentPrice + $stopLossChange;
            $updated = true;
        }

        // ALERT 1 - Start Trade 100 - 98 
        if ($trade->status === "waiting") {
            if (($mode === 'LONG' && $currentPrice < $entry) || ($mode === 'SHORT' && $currentPrice > $entry)) {
                $trade->status = "running";
                $trade->save();
                // updateTradeInCache($trade);
                startWaitingTrade($trade, $currentPrice);
            }
            return;
        }

        // ALERT 2 - Check SL hit
        $slHit = $mode === 'LONG' ? $currentPrice < $sl : $currentPrice > $sl;
        if ($slHit) {
            // call real server
            if($trade->type === "real"){
                $bybitAPI = new BybitAPIController();
                $bybitAPI->closedTrade($trade);
            }

            $trade->last_alert = "sl";
            $trade->status = "closed";
            $trade->save();
            removeTradeFromCache($trade->id);
            
            aiAdvisorTakeLose($trade, $currentPrice);
            return;
        }

        // ALERT 3 - Take Profit
        $tpLevels = collect([
            $trade->take_profit1,
            $trade->take_profit2,
            $trade->take_profit3,
            $trade->take_profit4,
            $trade->take_profit5,
            $trade->take_profit6,
            $trade->take_profit7,
            $trade->take_profit8,
            $trade->take_profit9,
            $trade->take_profit10,
        ]);

        for ($i = 9; $i >= 0; $i--) {
            $tp = (float)$tpLevels[$i];

            if(!empty($tp)){
                $tpLevel = $i + 1;
                $condition = $mode === 'LONG' ? $currentPrice > $tp : $currentPrice < $tp;

                if ($condition) {
                    $tpLevelStatus = "tp_" . $tpLevel;

                    if ($tpLevelStatus !== $trade->last_alert) {
                        $reverse = false;

                        if ($heightTP < $tpLevel) {
                            $trade->height_tp = $tpLevel;
                            $reverse = true;
                        }

                        $trade->last_alert = $tpLevelStatus;

                        $isFinalTrade = false;
                        if ($trade->profit_strategy === "partial_profits") {
                            $nextNotify = $tpLevel + 1;
                            $property = 'partial_profits_tp' . $nextNotify;

                            if (empty($trade->$property)) {
                                // call real server
                                if($trade->type === "real"){
                                    $bybitAPI = new BybitAPIController();
                                    $bybitAPI->closedTrade($trade);
                                }

                                $trade->status = "closed";
                                $isFinalTrade = true;
                                removeTradeFromCache($trade->id);
                            }
                        } elseif ($trade->profit_strategy === "close_specific_tp" && $trade->specific_tp == $tpLevel) {
                            // call real server
                            if($trade->type === "real"){
                                $bybitAPI = new BybitAPIController();
                                $bybitAPI->closedTrade($trade);
                            }

                            $trade->status = "closed";
                            $isFinalTrade = true;
                            removeTradeFromCache($trade->id);
                        }

                        $updated = true;
                        aiAdvisorTakeProfit($trade, $tpLevel, $reverse, $currentPrice, $isFinalTrade);
                    }
                    break;
                }
            }
        }

        if ($updated) {
            $trade->save();
        }
    }
}

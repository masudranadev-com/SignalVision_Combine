<?php

namespace App\Jobs;

use App\Models\ScheduleCrypto;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Jobs\ScheduleNotificationJob;

class ProcessScheduleChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $schedules;
    protected $combainData;
    public function __construct($schedules, $combainData)
    {
        $this->schedules = $schedules;
        $this->combainData = $combainData;
    }

    public function handle()
    {
        $schedules = $this->schedules;
        $combainData = $this->combainData;

        // Notify users
        foreach ($schedules as $trade) {
            $instrument = $trade->instruments;
            $market = $trade->market;
            $currentPrice = isset($combainData[$instrument]) ? $combainData[$instrument] : null;

            if (is_null($currentPrice)) continue;

            // Run Job 
            dispatch((new ScheduleNotificationJob(["trade" => $trade, "currentPrice" => $currentPrice]))->onQueue('SignalManage_notification'));
        }
    }
}

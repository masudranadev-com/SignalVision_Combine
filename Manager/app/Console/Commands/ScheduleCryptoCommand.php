<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use App\Models\ScheduleCrypto;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Jobs\ProcessScheduleChunkJob;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Throwable;

class ScheduleCryptoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:schedule-crypto'; // php artisan command:schedule-crypto
    protected $description = 'Command description';
    public function handle()
    {
        return;
        $interval = 6;
        while (true) {
            $start = microtime(true);

            try {
                $this->runScheduleBatch();
            } catch (\Throwable $e) {
                \Log::error("❌ Error in schedule loop: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                $this->runScheduleBatch();
            }

            $elapsed = microtime(true) - $start;
            $sleep = max(0, $interval - $elapsed);

            if ($sleep > 0) {
                sleep((int) ceil($sleep));
            }
        }
    }



    private function runScheduleBatch()
    {
        $schedules = Cache::remember("schedule_cryptos_running_waiting", now()->addHours(1), function () {
            return ScheduleCrypto::whereIn('status', ['running', 'waiting'])
                ->orderBy('id', 'desc')
                ->get();
        });

        if ($schedules->isEmpty()) {
            return;
        }

        $groupedInstruments = $schedules->pluck('instruments')->unique()->values()->toArray();

        $combainData = combineCryptoPrices($groupedInstruments);

        Setting::where('key', 'crypto_data')->update([
            "value" => $combainData
        ]);

        $batches = $schedules->chunk(100);
        foreach ($batches as $batch) {
            dispatch((new ProcessScheduleChunkJob($batch, $combainData))->onQueue('wdwd'));
        }
    }

}

<?php

namespace App\Observers;

use App\Models\ScheduleCrypto;
use Illuminate\Support\Facades\Log;
use App\Events\TradeConfigEvent;

class ScheduleCryptoObserver
{
    /**
     * Handle the ScheduleCrypto "created" event.
     */
    public function created(ScheduleCrypto $scheduleCrypto): void
    {
        
    }

    /**
     * Handle the ScheduleCrypto "updated" event.
     */
    public function updated(ScheduleCrypto $scheduleCrypto): void
    {
        updateTradeInCache($scheduleCrypto);
        if ($scheduleCrypto->status != "pending" && (
            $scheduleCrypto->isDirty('entry_target') || 
            $scheduleCrypto->isDirty('stop_loss_percentage') || 
            $scheduleCrypto->isDirty('stop_loss_price') || 
            $scheduleCrypto->isDirty('leverage')
        )) {
            event(new TradeConfigEvent("update", $scheduleCrypto));
        }
    }

    /**
     * Handle the ScheduleCrypto "deleted" event.
     */
    public function deleted(ScheduleCrypto $scheduleCrypto): void
    {
        
    }

    /**
     * Handle the ScheduleCrypto "restored" event.
     */
    public function restored(ScheduleCrypto $scheduleCrypto): void
    {
        //
    }

    /**
     * Handle the ScheduleCrypto "force deleted" event.
     */
    public function forceDeleted(ScheduleCrypto $scheduleCrypto): void
    {
        //
    }
}

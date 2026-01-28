<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TradeConfigEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
    */
    public $trade;
    public $action;
    public function __construct($action, $trade)
    {
        \Log::info($trade);
        $this->action = $action;
        $this->trade = $trade;
    }

    public function broadcastOn()
    {
        return new Channel('tradeConfigChannel');
    }

    public function broadcastAs()
    {
        return 'tradeConfigEvent';
    }
}

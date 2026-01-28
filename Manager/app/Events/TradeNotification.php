<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TradeNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */

    public $trade_id;
    public $type;
    public $current_price;
    public function __construct()
    {
        $this->trade_id = $data['TRADE_ID'];
        $this->type = $data['TYPE'];
        $this->current_price = $data['CURRENT_PRICE'];

        // Log the data sent to the event
        Log::info('TradeNotification Event Data:', [
            'TRADE_ID' => $this->TRADE_ID,
            'TYPE' => $this->TYPE,
            'CURRENT_PRICE' => $this->CURRENT_PRICE
        ]);
    }

    public function broadcastOn()
    {
        return new Channel('trade-notifications');
    }

    public function broadcastAs()
    {
        return 'TradeNotification';
    }
}

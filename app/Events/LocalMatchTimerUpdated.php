<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class LocalMatchTimerUpdated implements ShouldBroadcast
{
    public $matchId;
    public $roundId;
    public $remaining;

    public function __construct($matchId, $roundId, $remaining)
    {
        $this->matchId = $matchId;
        $this->roundId = $roundId;
        $this->remaining = $remaining;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->matchId);
    }

    public function broadcastAs()
    {
        return 'timer.tick';
    }
}



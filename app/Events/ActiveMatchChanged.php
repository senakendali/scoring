<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ActiveMatchChanged implements ShouldBroadcastNow
{
    protected $matchId;

    public function __construct($matchId)
    {
    $this->matchId = $matchId;
    \Log::info("📢 Constructor called ActiveMatchChanged", ['match_id' => $this->matchId]);
    }


    public function broadcastOn()
    {
        return new Channel('global.match');
    }

    public function broadcastAs()
    {
        return 'match.changed';
    }

    public function broadcastWith()
    {
        \Log::info("📢 Broadcasting ActiveMatchChanged", ['match_id' => $this->matchId]);
        return ['new_match_id' => $this->matchId];
    }
    

}

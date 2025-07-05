<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Str;

class ActiveMatchChanged implements ShouldBroadcastNow
{
    protected $matchId;
     protected $arenaName;
    public function __construct($matchId, $arenaName)
    {
     $this->matchId = $matchId;
     $this->arenaName = $arenaName;
    \Log::info("📢 Constructor called ActiveMatchChanged", ['match_id' => $this->matchId]);
    }


    public function broadcastOn()
    {
        //return new Channel('global.match.' . $this->matchId);
        return new Channel('arena.match.' . \Str::slug($this->arenaName));
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

<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class SeniActiveMatchChanged implements ShouldBroadcastNow
{
    use SerializesModels;

    public int $matchId;
     protected $arenaName;

    public function __construct(int $matchId, $arenaName)
    {
        $this->matchId = $matchId;
        $this->arenaName = $arenaName;

        \Log::info("📢 [SeniActiveMatchChanged] Constructed", [
            'match_id' => $this->matchId
        ]);
    }

    public function broadcastOn()
    {   
        //return new Channel('global.seni.match.' . $this->matchId);
         //return new Channel('arena.match.' . \Str::slug($this->arenaName));
         return new Channel('arena.seni.match.' . Str::slug($this->arenaName));
    }


    public function broadcastAs()
    {
        return 'seni.match.changed';
    }

    public function broadcastWith()
    {
        \Log::info("📡 [SeniActiveMatchChanged] Broadcasting...", [
            'match_id' => $this->matchId,
            'arena' => $this->arenaName
        ]);

        return [
            'new_match_id' => $this->matchId
        ];
    }
}

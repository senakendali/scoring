<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class SeniActiveMatchChanged implements ShouldBroadcastNow
{
    use SerializesModels;

    public int $matchId;

    public function __construct(int $matchId)
    {
        $this->matchId = $matchId;

        \Log::info("📢 [SeniActiveMatchChanged] Constructed", [
            'match_id' => $this->matchId
        ]);
    }

    public function broadcastOn()
    {   
        return new Channel('global.seni.match.' . $this->matchId);
    }


    public function broadcastAs()
    {
        return 'seni.match.changed';
    }

    public function broadcastWith()
    {
        \Log::info("📡 [SeniActiveMatchChanged] Broadcasting...", [
            'match_id' => $this->matchId
        ]);

        return [
            'new_match_id' => $this->matchId
        ];
    }
}

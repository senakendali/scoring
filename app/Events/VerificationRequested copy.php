<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class VerificationRequested implements ShouldBroadcast
{
    use SerializesModels;

    public $matchId;
    public $roundId;
    public $type;
    public $corner;

    public function __construct($matchId, $roundId, $type, $corner)
    {
        $this->matchId = $matchId;
        $this->roundId = $roundId;
        $this->type = $type;
        $this->corner = $corner;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->matchId);
    }

    public function broadcastAs()
    {
        return 'verification.requested';
    }

    public function broadcastWith()
    {
        return [
            'match_id' => $this->matchId,
            'round_id' => $this->roundId,
            'type' => $this->type,
            'corner' => $this->corner,
        ];
    }
}

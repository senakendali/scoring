<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class VerificationResulted implements ShouldBroadcast
{
    use SerializesModels;

    public $matchId;
    public $roundId;
    public $results;
    public $type;   // jatuhan / hukuman
    public $corner; // blue / red

    public function __construct($matchId, $roundId, $results, $type, $corner)
    {
        $this->matchId = $matchId;
        $this->roundId = $roundId;
        $this->results = $results;
        $this->type = $type;
        $this->corner = $corner;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->matchId);
    }

    public function broadcastAs()
    {
        return 'verification.resulted';
    }

    public function broadcastWith()
    {
        return [
            'match_id' => $this->matchId,
            'round_id' => $this->roundId,
            'results' => $this->results,
            'type' => $this->type,
            'corner' => $this->corner,
        ];
    }
}

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

    public function __construct($matchId, $roundId, $results)
    {
        $this->matchId = $matchId;
        $this->roundId = $roundId;
        $this->results = $results; // array of { judge: "J1", vote: "blue" }
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
        ];
    }
}

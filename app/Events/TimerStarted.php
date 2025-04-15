<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TimerStarted implements ShouldBroadcast
{
    public $matchId;
    public $startTime;
    public $duration;
    public $roundId;
    public $roundNumber;

    public function __construct($matchId, array $data)
    {
        $this->matchId = $matchId;
        $this->startTime = $data['start_time'];
        $this->duration = $data['duration'];
        $this->roundId = $data['round_id'] ?? null;
        $this->roundNumber = $data['round_number'] ?? null;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->matchId);
    }

    public function broadcastAs()
    {
        return 'timer.started';
    }

    public function broadcastWith()
    {
        return [
            'start_time' => $this->startTime,
            'duration' => $this->duration,
            'round_id' => $this->roundId,
            'round_number' => $this->roundNumber,
        ];
    }
}

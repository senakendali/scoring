<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class JudgeActionSubmitted implements ShouldBroadcast
{
    public $matchId;
    public $corner;
    public $judgeNumber;
    public $type; // punch atau kick

    public function __construct($matchId, $corner, $judgeNumber, $type)
    {
        $this->matchId = $matchId;
        $this->corner = $corner;
        $this->judgeNumber = $judgeNumber;
        $this->type = $type;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->matchId);
    }

    public function broadcastAs()
    {
        return 'judge.action.submitted';
    }

    public function broadcastWith()
    {
        return [
            'corner' => $this->corner,
            'judge_number' => $this->judgeNumber,
            'type' => $this->type,
        ];
    }
}

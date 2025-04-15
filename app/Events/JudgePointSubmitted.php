<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class JudgePointSubmitted implements ShouldBroadcast
{
    public $judge_number;
    public $corner;
    public $type;
    public $match_id;

    public function __construct($matchId, $judgeNumber, $corner, $type)
    {
        $this->match_id = $matchId;
        $this->judge_number = $judgeNumber;
        $this->corner = $corner;
        $this->type = $type;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->match_id);
    }

    public function broadcastAs()
    {
        return 'judge.point.submitted';
    }
}

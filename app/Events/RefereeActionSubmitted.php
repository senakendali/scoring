<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class RefereeActionSubmitted implements ShouldBroadcast
{
    use SerializesModels;

    public $matchId;
    public $corner;
    public $action;
    public $pointChange;

    public function __construct($matchId, $corner, $action, $pointChange)
    {
        $this->matchId = $matchId;
        $this->corner = $corner;
        $this->action = $action;
        $this->pointChange = $pointChange;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->matchId);
    }

    public function broadcastAs()
    {
        return 'referee.action';
    }

    public function broadcastWith()
    {
        return [
            'corner' => $this->corner,
            'action' => $this->action,
            'point_change' => $this->pointChange,
        ];
    }
}


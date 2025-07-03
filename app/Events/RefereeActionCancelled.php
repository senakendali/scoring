<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class RefereeActionCancelled implements ShouldBroadcast
{
    use SerializesModels;

    public $matchId;
    public $corner;
    public $action;

    public function __construct($matchId, $corner, $action)
    {
        $this->matchId = $matchId;
        $this->corner = $corner;
        $this->action = $action;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->matchId);
    }

    public function broadcastAs()
    {
        return 'referee.action.cancelled';
    }

    public function broadcastWith()
    {
        return [
            'corner' => $this->corner,
            'action' => $this->action,
        ];
    }
}

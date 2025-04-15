<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use App\Models\LocalMatchRound;
use Carbon\Carbon;

class TimerUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public $round;

    public function __construct(LocalMatchRound $round)
    {
        $this->round = $round;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->round->local_match_id);
    }

    public function broadcastAs()
    {
        return 'timer.updated';
    }

    public function broadcastWith()
    {
        $start = $this->round->start_time instanceof Carbon
            ? $this->round->start_time
            : Carbon::parse($this->round->start_time);

        $end = $this->round->end_time
            ? ($this->round->end_time instanceof Carbon
                ? $this->round->end_time
                : Carbon::parse($this->round->end_time))
            : now();

        $elapsed = ($this->round->status === 'in_progress' || $this->round->status === 'paused')
            ? $start->diffInRealSeconds($end)
            : 0;

        return [
            'round_id'    => $this->round->id,
            'status'      => $this->round->status,
            'start_time'  => $start->toIso8601String(),
            'now'         => now()->toIso8601String(),
            'remaining'   => max(0, 180 - $elapsed),
        ];
    }
}

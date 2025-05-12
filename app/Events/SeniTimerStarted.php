<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Support\Str;

class SeniTimerStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $match_id;
    public $arena_name;
    public $tournament_name;
    public $duration;

    public function __construct($match_id, $arena_name, $tournament_name, $duration = 180)
    {
        \Log::info('📢 Event SeniTimerStarted DIKIRIM', [
            'match_id' => $match_id,
            'arena_name' => $arena_name,
            'tournament_name' => $tournament_name,
            'duration' => $duration
        ]);

        $this->match_id = $match_id;
        $this->arena_name = $arena_name;
        $this->tournament_name = $tournament_name;
        $this->duration = $duration;
    }

    public function broadcastOn()
    {
        $arenaSlug = Str::slug($this->arena_name);
        $tournamentSlug = Str::slug($this->tournament_name);
        return new Channel("seni-timer.{$tournamentSlug}.{$arenaSlug}");
    }

    public function broadcastAs()
    {
        return 'SeniTimerStarted';
    }

    public function broadcastWith()
    {
        return [
            'match_id' => $this->match_id,
            'arena_name' => $this->arena_name,
            'tournament_name' => $this->tournament_name,
            'duration' => $this->duration,
        ];
    }
}

<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class SeniMatchStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $match_id;
    public $arena_name;
    public $tournament_name;

    public function __construct($match_id, $arena_name, $tournament_name)
    {
        $this->match_id = $match_id;
        $this->arena_name = $arena_name;
        $this->tournament_name = $tournament_name;
    }

    public function broadcastOn()
    {
        $arenaSlug = \Str::slug($this->arena_name);
        $tournamentSlug = \Str::slug($this->tournament_name);
        return new Channel("seni-match-start.{$tournamentSlug}.{$arenaSlug}");
    }

    public function broadcastAs()
    {
        return 'SeniMatchStarted';
    }
}


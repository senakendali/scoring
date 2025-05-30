<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;


class MatchStarted implements ShouldBroadcast
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
        $slugTournament = Str::slug($this->tournament_name); // "kejurnas-jakarta"
        $slugArena = Str::slug($this->arena_name);           // "arena-a"

        return new Channel("match-start.{$slugTournament}.{$slugArena}");
    }

    public function broadcastAs()
    {
        return 'MatchStarted';
    }
}


<?php

namespace App\Events;

use App\Models\LocalSeniMatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Support\Str;

class SeniTimerStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $match;

    public function __construct(LocalSeniMatch $match)
    {
        $this->match = $match;

        // ✅ Logging untuk debug
        \Log::info('📢 Event SeniTimerStarted DIKIRIM', [
            'match_id' => $match->id,
            'arena_name' => $match->arena_name,
            'tournament_name' => $match->tournament_name,
            'start_time' => optional($match->start_time)->toIso8601String(),
            'duration' => $match->duration,
        ]);
    }

    public function broadcastOn()
    {
        $arenaSlug = Str::slug($this->match->arena_name);
        $tournamentSlug = Str::slug($this->match->tournament_name);

        return new Channel("seni-timer.{$tournamentSlug}.{$arenaSlug}");
    }

    public function broadcastAs()
    {
        return 'SeniTimerStarted';
    }

    public function broadcastWith()
    {
        return [
            'match_id' => $this->match->id,
            'arena_name' => $this->match->arena_name,
            'tournament_name' => $this->match->tournament_name,
            'duration' => $this->match->duration ?? 180,
            'status' => $this->match->status,
            'start_time' => optional($this->match->start_time)->toIso8601String(),
        ];
    }
}

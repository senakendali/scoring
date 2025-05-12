<?php

namespace App\Events;

use App\Models\LocalSeniMatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SeniTimerUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $match_id;
    public $arena_name;
    public $tournament_name;
    public $status;
    public $elapsed;

    public function __construct(LocalSeniMatch $match)
    {
        $this->match_id = $match->id;
        $this->arena_name = $match->arena_name;
        $this->tournament_name = $match->tournament_name;
        $this->status = $match->status;

        // Hitung elapsed time dari start ke pause/now
        $start = $match->start_time ? Carbon::parse($match->start_time) : null;
        $pauseOrNow = $match->pause_time ? Carbon::parse($match->pause_time) : now();

        $this->elapsed = $start ? $start->diffInSeconds($pauseOrNow) : 0;

        \Log::info('📡 Event SeniTimerUpdated DIKIRIM', [
            'match_id' => $this->match_id,
            'status' => $this->status,
            'elapsed' => $this->elapsed,
        ]);
    }

    public function broadcastOn()
    {
        $arenaSlug = Str::slug($this->arena_name);
        $tournamentSlug = Str::slug($this->tournament_name);
        return new Channel("seni-timer.{$tournamentSlug}.{$arenaSlug}");
    }

    public function broadcastAs()
    {
        return 'SeniTimerUpdated';
    }

    public function broadcastWith()
    {
        return [
            'match_id' => $this->match_id,
            'arena_name' => $this->arena_name,
            'tournament_name' => $this->tournament_name,
            'status' => $this->status,
            'elapsed' => $this->elapsed,
        ];
    }
}

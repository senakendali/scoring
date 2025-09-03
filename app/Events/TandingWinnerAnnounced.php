<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class TandingWinnerAnnounced implements ShouldBroadcastNow
{
    use SerializesModels;

    public string $tournament;
    public string $arena;
    public array  $payload;

    public function __construct(string $tournament, string $arena, array $payload)
    {
        $this->tournament = $tournament;
        $this->arena      = $arena;
        $this->payload    = $payload;
    }

    public function broadcastOn(): Channel
    {
        // ✅ Sesuai FE: arena.match.{slug}
        return new Channel('arena.match.' . Str::slug($this->arena));
    }

    public function broadcastAs(): string
    {
        // FE bind pakai ini
        return 'tanding.winner.announced';
    }

    public function broadcastWith(): array
    {
        return $this->payload + [
            'tournament'     => $this->tournament,
            'arena'          => $this->arena,
            'broadcasted_at' => now()->toIso8601String(),
        ];
    }
}

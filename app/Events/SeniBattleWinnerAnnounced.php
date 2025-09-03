<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // kirim realtime tanpa antri
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
class SeniBattleWinnerAnnounced implements ShouldBroadcastNow
{
    use SerializesModels;

    /**
     * Nama turnamen & arena untuk penentuan channel.
     */
    public string $tournament;
    public string $arena;

    /**
     * Optional: battle group (jika ada).
     */
    public ?string $battleGroup;

    /**
     * Payload yang akan dikirim ke UI.
     * Contoh:
     * [
     *   'winner_name'  => 'Umaya Waskita',
     *   'contingent'   => 'Satria Muda Indonesia Banda Aceh',
     *   'corner'       => 'blue',        // 'blue'|'red'
     *   'reason'       => 'undur_diri',  // 'undur_diri'|'diskualifikasi'
     *   'reason_label' => 'Menang Undur Diri',
     *   'match_id'     => 286,           // optional tapi useful
     *   'round_label'  => 'Final',       // optional
     * ]
     */
    public array $payload;

    /**
     * @param string      $tournament   Nama turnamen
     * @param string      $arena        Nama arena (digunakan untuk nama channel)
     * @param string|null $battleGroup  Battle group ID/string (optional)
     * @param array       $payload      Data pemenang untuk ditampilkan
     */
    public function __construct(string $tournament, string $arena, ?string $battleGroup, array $payload)
    {
        $this->tournament  = $tournament;
        $this->arena       = $arena;
        $this->battleGroup = $battleGroup;
        $this->payload     = $payload;
    }

    /**
     * Channel broadcast — public channel "arena.{arena}".
     */
    public function broadcastOn(): Channel
    {
        // contoh: arena.Arena A  -> disarankan sanitize spasi di sisi subscriber
        // atau ganti ke slug di sini kalau mau: Str::slug($this->arena)
       // return new Channel("arena.{$this->arena}");
        return new Channel('arena.seni.match.' . Str::slug($this->arena));
    }

    /**
     * Nama event di sisi client (Echo): .seni.battle.winner.announced
     */
    public function broadcastAs(): string
    {
        return 'seni.battle.winner.announced';
    }

    /**
     * Data yang dikirim ke client.
     */
    public function broadcastWith(): array
    {
        return $this->payload + [
            'tournament'   => $this->tournament,
            'arena'        => $this->arena,
            'battle_group' => $this->battleGroup,
            'broadcasted_at' => now()->toIso8601String(),
        ];
    }
}

<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class SeniBattleGroupCompleted implements ShouldBroadcastNow
{
    use SerializesModels;

    protected string $tournamentName;
    protected string $arenaName;
    /** @var string battle group disimpan sebagai string supaya aman (bisa alfanumerik) */
    protected string $battleGroup;
    /** @var array top-2 winners */
    protected array $winners;
    protected string $resultUrl;
    /** @var string|null 'blue' | 'red' | null */
    protected ?string $winnerCorner;

    /**
     * @param string      $tournamentName
     * @param string      $arenaName
     * @param string|int  $battleGroup         // fleksibel: bisa string/angka
     * @param array       $winners             // ringkasan top-2 (match_id, corner, contingent, participants, final_score, penalty, duration, ...)
     * @param string      $resultUrl           // URL halaman result
     * @param string|null $winnerCorner        // 'blue' | 'red' | null
     */
    public function __construct(
        string $tournamentName,
        string $arenaName,
        $battleGroup,
        array $winners,
        string $resultUrl,
        ?string $winnerCorner = null
    ) {
        $this->tournamentName = $tournamentName;
        $this->arenaName      = $arenaName;
        $this->battleGroup    = (string) $battleGroup;
        $this->winners        = $winners;
        $this->resultUrl      = $resultUrl;
        $this->winnerCorner   = $winnerCorner ? strtolower($winnerCorner) : null;

        \Log::info('📢 [SeniBattleGroupCompleted] Constructed', [
            'tournament'       => $this->tournamentName,
            'arena'            => $this->arenaName,
            'battle_group'     => $this->battleGroup,
            'winner_corner'    => $this->winnerCorner,
            'winners_count'    => count($this->winners),
            'result_url'       => $this->resultUrl,
        ]);
    }

    public function broadcastOn()
    {
        // Channel: arena.seni.match.{slug}
        return new Channel('arena.seni.match.' . Str::slug($this->arenaName));
    }

    public function broadcastAs()
    {
        // frontend bind: 'seni.group.completed'
        return 'seni.group.completed';
    }

    public function broadcastWith()
    {
        // battle_group_id diset hanya kalau numeric (biar FE lama yang pakai _id tetap kompatibel)
        $battleGroupId = is_numeric($this->battleGroup) ? (int) $this->battleGroup : null;

        \Log::info('📡 [SeniBattleGroupCompleted] Broadcasting...', [
            'arena'             => $this->arenaName,
            'battle_group'      => $this->battleGroup,
            'battle_group_id'   => $battleGroupId,
            'winner_corner'     => $this->winnerCorner,
        ]);

        return [
            'tournament'        => $this->tournamentName,
            'arena'             => $this->arenaName,
            'battle_group'      => $this->battleGroup,    // ← string, aman buat query param
            'battle_group_id'   => $battleGroupId,        // ← int|null, legacy support
            'winner_corner'     => $this->winnerCorner,   // ← 'blue' | 'red' | null
            'winners'           => $this->winners,        // ← top-2 detail
            'result_url'        => $this->resultUrl,      // ← halaman result
        ];
    }
}

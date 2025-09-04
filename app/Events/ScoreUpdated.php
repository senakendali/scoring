<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ScoreUpdated implements ShouldBroadcast
{
    public int $matchId;
    public int $roundId;
    public int $blueScore;
    public int $redScore;
    public int $blueAdjustment;
    public int $redAdjustment;

    public function __construct(
        int $matchId,
        int $roundId,
        int $blueScore,
        int $redScore,
        int $blueAdjustment = 0,
        int $redAdjustment = 0
    ) {
        $this->matchId = $matchId;
        $this->roundId = $roundId;
        $this->blueScore = $blueScore;
        $this->redScore = $redScore;
        $this->blueAdjustment = $blueAdjustment;
        $this->redAdjustment = $redAdjustment;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->matchId);
    }

    public function broadcastAs()
    {
        return 'score.updated';
    }

    // Tambahan (di dalam broadcastWith)
    public function broadcastWith()
    {
        $q = \App\Models\LocalRefereeAction::query()
            ->where('local_match_id', $this->matchId);

        // Jatuhan
        $blueFallCount = (clone $q)->where('corner','blue')->whereRaw("LOWER(action) = 'jatuhan'")->count();
        $redFallCount  = (clone $q)->where('corner','red') ->whereRaw("LOWER(action) = 'jatuhan'")->count();

        // Helper: count penalti = (poin negatif) OR (action termasuk pola penalti), dan bukan 'jatuhan'
        $countPenalty = function (string $corner) use ($q) {
            return (clone $q)
                ->where('corner', $corner)
                ->where(function ($qq) {
                    $qq->where('point_change', '<', 0)
                    ->orWhere(function ($q2) {
                        // tambahkan pola lain kalau perlu
                        $q2->whereRaw("LOWER(action) LIKE 'binaan%'")
                            ->orWhereRaw("LOWER(action) LIKE 'teguran%'")
                            ->orWhereRaw("LOWER(action) LIKE 'peringatan%'");
                    });
                })
                ->whereRaw("LOWER(action) <> 'jatuhan'")
                ->count();
        };

        $bluePenaltyCount = $countPenalty('blue');
        $redPenaltyCount  = $countPenalty('red');

        // Winner realtime: skor → jatuhan → penalti (lebih sedikit menang)
        $winner = null;
        if ($this->blueScore > $this->redScore) {
            $winner = 'blue';
        } elseif ($this->redScore > $this->blueScore) {
            $winner = 'red';
        } else {
            if ($blueFallCount > $redFallCount) {
                $winner = 'blue';
            } elseif ($redFallCount > $blueFallCount) {
                $winner = 'red';
            } else {
                if ($bluePenaltyCount < $redPenaltyCount) {
                    $winner = 'blue';
                } elseif ($redPenaltyCount < $bluePenaltyCount) {
                    $winner = 'red';
                } else {
                    $winner = null; // tetap imbang
                }
            }
        }

        return [
            'match_id'          => $this->matchId,
            'round_id'          => $this->roundId,
            'blueScore'         => $this->blueScore,
            'redScore'          => $this->redScore,
            'blueAdjustment'    => $this->blueAdjustment,
            'redAdjustment'     => $this->redAdjustment,
            'blueFallCount'     => $blueFallCount,
            'redFallCount'      => $redFallCount,
            'bluePenaltyCount'  => $bluePenaltyCount,
            'redPenaltyCount'   => $redPenaltyCount,
            'winner_corner'     => $winner,
        ];
    }



    

    public function broadcastWith__()
    {
        return [
            'match_id' => $this->matchId,
            'round_id' => $this->roundId,
            'blueScore' => $this->blueScore,
            'redScore' => $this->redScore,
            'blueAdjustment' => $this->blueAdjustment,
            'redAdjustment' => $this->redAdjustment,
        ];
    }


}


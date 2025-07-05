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
        // Ambil total penalti dari referee actions
        $bluePenalty = \App\Models\LocalRefereeAction::where('local_match_id', $this->matchId)
            ->where('round_id', $this->roundId)
            ->where('corner', 'blue')
            ->where('action', '!=', 'jatuhan')
            ->sum('point_change');

        $redPenalty = \App\Models\LocalRefereeAction::where('local_match_id', $this->matchId)
            ->where('round_id', $this->roundId)
            ->where('corner', 'red')
            ->where('action', '!=', 'jatuhan')
            ->sum('point_change');

        // Tentukan pemenang realtime
        $winner = null;
        if ($this->blueScore > $this->redScore) {
            $winner = 'blue';
        } elseif ($this->redScore > $this->blueScore) {
            $winner = 'red';
        } else {
            if ($bluePenalty < $redPenalty) {
                $winner = 'blue';
            } elseif ($redPenalty < $bluePenalty) {
                $winner = 'red';
            }
        }

        return [
            'match_id' => $this->matchId,
            'round_id' => $this->roundId,
            'blueScore' => $this->blueScore,
            'redScore' => $this->redScore,
            'blueAdjustment' => $this->blueAdjustment,
            'redAdjustment' => $this->redAdjustment,
            'winner_corner' => $winner,
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


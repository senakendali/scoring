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
        $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $this->matchId)
            ->where('round_id', $this->roundId)
            ->where('corner', 'blue')
            ->sum('point_change');

        $redAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $this->matchId)
            ->where('round_id', $this->roundId)
            ->where('corner', 'red')
            ->sum('point_change');

        /*return [
            'blueScore' => $this->blueScore,
            'redScore' => $this->redScore,
            'blueAdjustment' => $blueAdjustment,
            'redAdjustment' => $redAdjustment,
        ];*/

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


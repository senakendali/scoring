<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use App\Models\MatchPersonnelAssignment;
use App\Models\LocalSeniScore;
use App\Models\LocalSeniPenalties;

class SeniScoreUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $matchId;
    public $arena;
    public $tournament;

    public function __construct($matchId, $arena, $tournament)
    {
        $this->matchId = $matchId;
        $this->arena = $arena;
        $this->tournament = $tournament;
    }

    public function broadcastOn()
    {
        $slugArena = \Str::slug($this->arena);
        $slugTournament = \Str::slug($this->tournament);
        return new Channel("seni-score.{$slugTournament}.{$slugArena}");
    }

    public function broadcastAs()
    {
        return 'SeniScoreUpdated';
    }

    public function broadcastWith()
    {
        $juris = MatchPersonnelAssignment::where('tipe_pertandingan', 'seni')
            ->where('role', 'juri')
            ->where('arena_name', $this->arena)
            ->where('tournament_name', $this->tournament)
            ->get();

        $results = [];

        foreach ($juris as $juri) {
            $deduction = LocalSeniScore::where('local_match_id', $this->matchId)
                ->where('judge_number', $juri->juri_number)
                ->sum('deduction');

            $score = 9.90 - $deduction;

            $results[] = [
                'juri_number' => $juri->juri_number,
                'score' => round($score, 2),
                'deduction' => round($deduction, 2)
            ];
        }

        $penalty = LocalSeniPenalties::where('local_match_id', $this->matchId)->sum('penalty_value');

        $penalties = LocalSeniPenalties::where('local_match_id', $this->matchId)
            ->select('reason', 'penalty_value')
            ->get();

        return [
            'match_id' => $this->matchId,
            'judges' => $results,
            'penalty' => round($penalty, 2),
            'penalties' => $penalties
        ];
    }
}

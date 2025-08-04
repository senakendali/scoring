<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use App\Models\MatchPersonnelAssignment;
use App\Models\LocalSeniScore;
use App\Models\LocalSeniPenalties;
use App\Models\LocalSeniFinalScore;
use App\Models\LocalSeniComponentScore;
use App\Models\LocalSeniMatch;

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

        $match = LocalSeniMatch::find($this->matchId);
        $category = strtolower($match->category);
        $baseScore = in_array($category, ['tunggal', 'regu']) ? 9.90 : 9.10;

        foreach ($juris as $juri) {
            $deduction = LocalSeniScore::where('local_match_id', $this->matchId)
                ->where('judge_number', $juri->juri_number)
                ->sum('deduction');

            $final = LocalSeniFinalScore::where('local_match_id', $this->matchId)
                ->where('judge_number', $juri->juri_number)
                ->first();

            $component = LocalSeniComponentScore::where('local_match_id', $this->matchId)
                ->where('judge_number', $juri->juri_number)
                ->first();

            $additional = $final?->kemantapan ?? 0;

            $componentTotal = 0;
            if ($component) {
                $componentTotal += $component->attack_defense_technique ?? 0;
                $componentTotal += $component->firmness_harmony ?? 0;
                $componentTotal += $component->soulfulness ?? 0;
            }

            $score = $baseScore + $additional + $componentTotal - $deduction;

            $results[] = [
                'juri_number' => $juri->juri_number,
                'truth_score' => round($baseScore + $componentTotal - $deduction, 2),
                'score' => round($score, 2),
                'deduction' => round($deduction, 2),
                'additional_score' => round($additional, 2),
                 'component_score' => round($componentTotal, 2),
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
            'penalties' => $penalties,
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LocalSeniScore;
use App\Models\LocalSeniPenalties;
use App\Models\LocalSeniMatch;
use App\Models\LocalSeniFinalScore;
use App\Models\LocalSeniComponentScore;
use App\Models\MatchPersonnelAssignment;

class LocalSeniScoreController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'local_match_id' => 'required|exists:local_seni_matches,id',
            'judge_number'   => 'required|integer|min:1|max:10',
            'deduction'      => 'required|numeric|min:0.01',
        ]);

        $data['deducted_at'] = now();

        $score = LocalSeniScore::create($data);

        $match = LocalSeniMatch::find($request->local_match_id);

        event(new \App\Events\SeniScoreUpdated(
            $match->id,
            $match->arena_name,
            $match->tournament_name
        ));


        return response()->json([
            'message' => 'Deduction saved successfully',
            'data' => $score
        ]);
    }

    public function storePenalties(Request $request)
    {
         $data = $request->validate([
            'match_id' => 'required|integer',
            'penalties' => 'required|array',
            'penalties.*.type' => 'required|string',
            'penalties.*.value' => 'required|numeric|min:0.01'
        ]);

        // Opsional: Hapus penalti sebelumnya untuk match ini
        LocalSeniPenalties::where('local_match_id', $data['match_id'])->delete();

        // Simpan penalti baru
        foreach ($data['penalties'] as $penalty) {
            LocalSeniPenalties::create([
                'local_match_id' => $data['match_id'],
                'reason' => $penalty['type'],
                'penalty_value' => $penalty['value'],
            ]);
        }

         $match = LocalSeniMatch::find($request->match_id);

        event(new \App\Events\SeniScoreUpdated(
            $match->id,
            $match->arena_name,
            $match->tournament_name
        ));

        return response()->json(['message' => 'Penalties saved successfully']);
    }

    public function storeAdditionalScore(Request $request)
    {
        $data = $request->validate([
            'match_id' => 'required|exists:local_seni_matches,id',
            'judge_number' => 'required|integer|min:1|max:10',
            'additional_score' => 'required|numeric|between:0,0.10',
        ]);

        // Update / create final score khusus tambahan nilai
        LocalSeniFinalScore::updateOrCreate(
            [
                'local_match_id' => $data['match_id'],
                'judge_number' => $data['judge_number'],
            ],
            [
                'submitted_at' => now(), // optional, kalau tambahan dinilai saat submit
                'kemantapan' => $data['additional_score'], // bisa disimpan ke kolom "kemantapan"
            ]
        );

        $match = LocalSeniMatch::find($data['match_id']);

        $this->recalculateFinalScore($match);

        event(new \App\Events\SeniScoreUpdated(
            $match->id,
            $match->arena_name,
            $match->tournament_name
        ));

        return response()->json(['message' => 'Score tambahan berhasil disimpan']);
    }

    private function recalculateFinalScore($match)
    {
        $juris = MatchPersonnelAssignment::where('tipe_pertandingan', 'seni')
            ->where('role', 'juri')
            ->where('arena_name', $match->arena_name)
            ->where('tournament_name', $match->tournament_name)
            ->get();

        $category = strtolower($match->category);
        $baseScore = in_array($category, ['tunggal', 'regu']) ? 9.90 : 9.10;

        $scores = [];

        foreach ($juris as $juri) {
            $deduction = LocalSeniScore::where('local_match_id', $match->id)
                ->where('judge_number', $juri->juri_number)
                ->sum('deduction');

            $final = LocalSeniFinalScore::where('local_match_id', $match->id)
                ->where('judge_number', $juri->juri_number)
                ->first();

            $component = LocalSeniComponentScore::where('local_match_id', $match->id)
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
            $scores[] = $score;
        }

        if (count($scores) > 0) {
            $penalty = LocalSeniPenalties::where('local_match_id', $match->id)->sum('penalty_value');
            $match->final_score = round(collect($scores)->avg() - $penalty, 6);
            $match->save();
        }
    }


    public function storeComponentScore(Request $request)
    {
        
         $request->validate([
            'match_id' => 'required|exists:local_seni_matches,id',
            'judge_number' => 'required|integer|min:1|max:10',
            'component' => 'required|in:attack_defense_technique,firmness_harmony,soulfulness',
            'value' => 'required|numeric|min:0|max:0.30',
        ]);

        LocalSeniComponentScore::updateOrCreate(
            [
                'local_match_id' => $request->match_id,
                'judge_number' => $request->judge_number,
            ],
            [
                $request->component => $request->value,
                'submitted_at' => now(),
            ]
        );

        $match = LocalSeniMatch::find($request->match_id);

         event(new \App\Events\SeniScoreUpdated(
            $match->id,
            $match->arena_name,
            $match->tournament_name
        ));


        return response()->json(['message' => 'Component score saved.']);
    }
}

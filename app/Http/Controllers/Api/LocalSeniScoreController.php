<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LocalSeniScore;
use App\Models\LocalSeniPenalties;
use App\Models\LocalSeniMatch;

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
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LocalSeniScore;

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

        return response()->json([
            'message' => 'Deduction saved successfully',
            'data' => $score
        ]);
    }
}

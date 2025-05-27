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
     private $live_server;

    public function __construct()
    {
        $this->live_server = config('app_settings.data_source');
    }
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

    // ✅ Simpan kemantapan
    \App\Models\LocalSeniFinalScore::updateOrCreate(
        [
            'local_match_id' => $data['match_id'],
            'judge_number' => $data['judge_number'],
        ],
        [
            'submitted_at' => now(),
            'kemantapan' => $data['additional_score'],
        ]
    );

    $match = \App\Models\LocalSeniMatch::find($data['match_id']);

    // ✅ Hitung ulang final_score lokal
    $category = strtolower($match->category);
    $baseScore = in_array($category, ['tunggal', 'regu']) ? 9.90 : 9.10;

    $juris = \App\Models\MatchPersonnelAssignment::where('tipe_pertandingan', 'seni')
        ->where('role', 'juri')
        ->where('arena_name', $match->arena_name)
        ->where('tournament_name', $match->tournament_name)
        ->get();

    $finalScores = [];

    foreach ($juris as $juri) {
        $deduction = \App\Models\LocalSeniScore::where('local_match_id', $match->id)
            ->where('judge_number', $juri->juri_number)
            ->sum('deduction');

        $final = \App\Models\LocalSeniFinalScore::where('local_match_id', $match->id)
            ->where('judge_number', $juri->juri_number)
            ->first();

        $component = \App\Models\LocalSeniComponentScore::where('local_match_id', $match->id)
            ->where('judge_number', $juri->juri_number)
            ->first();

        $additional = $final?->kemantapan ?? 0;

        $componentTotal = 0;
        if ($component) {
            $componentTotal += $component->attack_defense_technique ?? 0;
            $componentTotal += $component->firmness_harmony ?? 0;
            $componentTotal += $component->soulfulness ?? 0;
        }

        $total = $baseScore + $additional + $componentTotal - $deduction;
        $finalScores[] = $total;
    }

    $totalPenalty = \App\Models\LocalSeniPenalties::where('local_match_id', $match->id)->sum('penalty_value');

    $finalScore = count($finalScores) > 0
        ? round(collect($finalScores)->avg() - $totalPenalty, 6)
        : round(0 - $totalPenalty, 6);

    // ✅ Simpan final_score ke DB lokal
    $match->final_score = $finalScore;
    $match->save();

    \Log::info('🛰 [KEMANTAPAN] Kirim ke pusat', [
        'remote_id' => $match->remote_match_id,
        'final_score' => $finalScore,
        'status' => $match->status,
    ]);

    // ✅ Kirim ke server pusat
    if ($match->remote_match_id) {
        try {
            $client = new \GuzzleHttp\Client();

            $client->post($this->live_server . '/api/update-seni-match-status', [
                'json' => [
                    'remote_match_id' => $match->remote_match_id,
                    'status' => $match->status,
                    'final_score' => $finalScore,
                ],
                'timeout' => 5,
            ]);

            \Log::info('✅ Final score disinkron ke server pusat (kemantapan)', [
                'remote_match_id' => $match->remote_match_id,
                'final_score' => $finalScore
            ]);
        } catch (\Throwable $e) {
            \Log::warning('⚠️ Gagal sync ke pusat (kemantapan)', [
                'match_id' => $match->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    // ✅ Broadcast update skor ke frontend
    event(new \App\Events\SeniScoreUpdated(
        $match->id,
        $match->arena_name,
        $match->tournament_name
    ));

    return response()->json(['message' => 'Score tambahan berhasil disimpan']);
}



    public function storeAdditionalScore__(Request $request)
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

    public function refreshFinalScore($id)
    {
        $match = \App\Models\LocalSeniMatch::findOrFail($id);

        $category = strtolower($match->category);
        $baseScore = in_array($category, ['tunggal', 'regu']) ? 9.90 : 9.10;

        $juris = \App\Models\MatchPersonnelAssignment::where('tipe_pertandingan', 'seni')
            ->where('role', 'juri')
            ->where('arena_name', $match->arena_name)
            ->where('tournament_name', $match->tournament_name)
            ->get();

        $finalScores = [];

        foreach ($juris as $juri) {
            $deduction = \App\Models\LocalSeniScore::where('local_match_id', $match->id)
                ->where('judge_number', $juri->juri_number)
                ->sum('deduction');

            $final = \App\Models\LocalSeniFinalScore::where('local_match_id', $match->id)
                ->where('judge_number', $juri->juri_number)
                ->first();

            $component = \App\Models\LocalSeniComponentScore::where('local_match_id', $match->id)
                ->where('judge_number', $juri->juri_number)
                ->first();

            $additional = $final?->kemantapan ?? 0;

            $componentTotal = 0;
            if ($component) {
                $componentTotal += $component->attack_defense_technique ?? 0;
                $componentTotal += $component->firmness_harmony ?? 0;
                $componentTotal += $component->soulfulness ?? 0;
            }

            $total = $baseScore + $additional + $componentTotal - $deduction;
            $finalScores[] = $total;
        }

        $totalPenalty = \App\Models\LocalSeniPenalties::where('local_match_id', $match->id)->sum('penalty_value');

        if (count($finalScores) > 0) {
            $rawAverage = collect($finalScores)->avg();
            $match->final_score = round($rawAverage - $totalPenalty, 6);
        } else {
            $match->final_score = round(0 - $totalPenalty, 6);
        }

        $match->save();

        \Log::info('✅ Final score recalculated', [
            'match_id' => $match->id,
            'final_score' => $match->final_score,
            'penalty' => $totalPenalty,
        ]);

        // ✅ Kirim ulang ke server pusat
        if ($match->remote_match_id && $match->final_score !== null) {
            try {
                $client = new \GuzzleHttp\Client();

                $client->post($this->live_server . '/api/update-seni-match-status', [
                    'json' => [
                        'remote_match_id' => $match->remote_match_id,
                        'status' => $match->status,
                        'final_score' => $match->final_score,
                    ],
                    'timeout' => 5,
                ]);

                \Log::info('✅ Final score dikirim ulang ke server pusat (refresh)', [
                    'remote_match_id' => $match->remote_match_id,
                    'final_score' => $match->final_score
                ]);
            } catch (\Throwable $e) {
                \Log::warning('⚠️ Gagal kirim ulang final score ke server pusat (refresh)', [
                    'match_id' => $match->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'message' => 'Final score berhasil diperbarui.',
            'final_score' => $match->final_score
        ]);
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

    // ✅ Simpan nilai komponen
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

    // ✅ Broadcast update nilai untuk display
    event(new \App\Events\SeniScoreUpdated(
        $match->id,
        $match->arena_name,
        $match->tournament_name
    ));

    // ✅ Hitung ulang final_score berdasarkan data terkini
    $category = strtolower($match->category);
    $baseScore = in_array($category, ['tunggal', 'regu']) ? 9.90 : 9.10;

    $juris = \App\Models\MatchPersonnelAssignment::where('tipe_pertandingan', 'seni')
        ->where('role', 'juri')
        ->where('arena_name', $match->arena_name)
        ->where('tournament_name', $match->tournament_name)
        ->get();

    $finalScores = [];

    foreach ($juris as $juri) {
        $deduction = \App\Models\LocalSeniScore::where('local_match_id', $match->id)
            ->where('judge_number', $juri->juri_number)
            ->sum('deduction');

        $final = \App\Models\LocalSeniFinalScore::where('local_match_id', $match->id)
            ->where('judge_number', $juri->juri_number)
            ->first();

        $component = \App\Models\LocalSeniComponentScore::where('local_match_id', $match->id)
            ->where('judge_number', $juri->juri_number)
            ->first();

        $additional = $final?->kemantapan ?? 0;

        $componentTotal = 0;
        if ($component) {
            $componentTotal += $component->attack_defense_technique ?? 0;
            $componentTotal += $component->firmness_harmony ?? 0;
            $componentTotal += $component->soulfulness ?? 0;
        }

        $total = $baseScore + $additional + $componentTotal - $deduction;
        $finalScores[] = $total;
    }

    $totalPenalty = \App\Models\LocalSeniPenalties::where('local_match_id', $match->id)->sum('penalty_value');

    $finalScore = count($finalScores) > 0
        ? round(collect($finalScores)->avg() - $totalPenalty, 6)
        : round(0 - $totalPenalty, 6);

    // ✅ Kirim ke server pusat
    \Log::info('🛰 Kirim ke pusat', [
        'id' => $match->id,
        'remote_id' => $match->remote_match_id,
        'local_final_score' => $finalScore,
        'status' => $match->status,
    ]);

    if ($match->remote_match_id && $finalScore !== null) {
        try {
            $client = new \GuzzleHttp\Client();

            $client->post($this->live_server . '/api/update-seni-match-status', [
                'json' => [
                    'remote_match_id' => $match->remote_match_id,
                    'status' => $match->status,
                    'final_score' => $finalScore,
                ],
                'timeout' => 5,
            ]);

            \Log::info('✅ Final score disinkron ke server pusat', [
                'remote_match_id' => $match->remote_match_id,
                'final_score' => $finalScore
            ]);
        } catch (\Throwable $e) {
            \Log::warning('⚠️ Gagal sinkron final_score ke server pusat', [
                'match_id' => $match->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    return response()->json(['message' => 'Component score saved.']);
}



    public function storeComponentScore_(Request $request)
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

        // Ambil skor yang sudah disimpan
        $finalScore = $match->final_score;

        \Log::info('🛰 Kirim ke pusat', [
            'id' => $match->id,
            'remote_id' => $match->remote_match_id,
            'local_final_score' => $match->final_score,
            'status' => $match->status,
        ]);

        if ($finalScore !== null) {
            try {
                $client = new \GuzzleHttp\Client();

                $client->post($this->live_server . '/api/update-seni-match-status', [
                    'json' => [
                        'remote_match_id' => $match->remote_match_id,
                        'status' => $match->status, // biasanya ongoing
                        'final_score' => $finalScore,
                    ],
                    'timeout' => 5,
                ]);

                \Log::info('✅ Final score disinkron ke server pusat', [
                    'remote_match_id' => $match->remote_match_id,
                    'final_score' => $finalScore
                ]);
            } catch (\Throwable $e) {
                \Log::warning('⚠️ Gagal sinkron final_score ke server pusat', [
                    'match_id' => $match->id,
                    'error' => $e->getMessage()
                ]);
            }
        }



        return response()->json(['message' => 'Component score saved.']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller; // Added this import
use App\Models\LocalMatch;
use App\Models\LocalJudgeScore;
use App\Models\LocalRefereeAction;
use App\Models\LocalValidScore;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Events\ScoreUpdated;
use App\Events\JudgePointSubmitted;
use App\Events\RefereeActionSubmitted;

class LocalMatchController extends Controller
{
    // Menampilkan semua pertandingan
    public function index()
    {
        $matches = LocalMatch::where('status', 'not_started')
            ->orderBy('arena_name')
            ->orderBy('id')
            ->get();

        $grouped = $matches->groupBy('arena_name');

        return response()->json($grouped);
    }

    // Menampilkan pertandingan berdasarkan ID
    public function show($id)
    {
        $match = LocalMatch::with([
            'rounds' => function ($query) {
                $query->orderBy('round_number');
            }
        ])->findOrFail($id);

        // Hitung skor total dari judge_scores & referee_actions
        $red_score = $this->calculateScore($id, 'red');
        $blue_score = $this->calculateScore($id, 'blue');

        return response()->json([
            'id' => $match->id,
            'tournament_name' => $match->tournament_name,
            'arena_name' => $match->arena_name,
            'match_code' => $match->match_code,
            'class_name' => $match->class_name,
            'status' => $match->status,
            'blue' => [
                'name' => $match->blue_name,
                'contingent' => $match->blue_contingent,
                'score' => $blue_score,
            ],
            'red' => [
                'name' => $match->red_name,
                'contingent' => $match->red_contingent,
                'score' => $red_score,
            ],
            'rounds' => $match->rounds,
            'total_rounds' => $match->total_rounds,
        ]);
    }

    private function calculateScore__($matchId, $corner)
    {
        $judgePoints = \App\Models\LocalJudgeScore::where('local_match_id', $matchId)
            ->where('corner', $corner)
            ->sum('point');

        $refereePoints = \App\Models\LocalRefereeAction::where('local_match_id', $matchId)
            ->where('corner', $corner)
            ->sum('point_change');

        return $judgePoints + $refereePoints;
    }

    private function calculateScore($matchId, $corner)
    {
        // Ambil dari nilai yang sah (valid score)
        $validPoints = \App\Models\LocalValidScore::where('local_match_id', $matchId)
            ->where('corner', $corner)
            ->sum('point');

        // Ambil dari tindakan wasit (referee)
        $refereePoints = \App\Models\LocalRefereeAction::where('local_match_id', $matchId)
            ->where('corner', $corner)
            ->sum('point_change');

        return $validPoints + $refereePoints;
    }


    public function endMatch($id)
    {
        $match = LocalMatch::findOrFail($id);
        $match->status = 'finished';
        $match->save();

        // Tandai ronde yang masih aktif jadi selesai
        $match->rounds()->where('status', 'in_progress')->update([
            'status' => 'finished',
            'end_time' => now()
        ]);

        return response()->json(['message' => 'Pertandingan diakhiri.']);
    }

    public function liveScore($matchId)
    {
        $match = LocalMatch::findOrFail($matchId);

        $scores = DB::table('local_judge_scores')
            ->where('local_match_id', $matchId)
            ->select('corner', DB::raw('SUM(point) as total'))
            ->groupBy('corner')
            ->pluck('total', 'corner');

        return response()->json([
            'blue_score' => $scores['blue'] ?? 0,
            'red_score' => $scores['red'] ?? 0,
        ]);
    }

    public function submitPoint(Request $request)
    {
        $data = $request->validate([
            'match_id' => 'required|exists:local_matches,id',
            'round_id' => 'required|exists:local_match_rounds,id',
            'judge_number' => 'required|integer',
            'judge_name' => 'required|string',
            'corner' => 'required|in:red,blue',
            'type' => 'required|in:punch,kick',
        ]);

        $now = now();

        // 1. Simpan ke local_judge_scores
        \App\Models\LocalJudgeScore::create([
            'local_match_id' => $data['match_id'],
            'round_id' => $data['round_id'],
            'judge_number' => $data['judge_number'],
            'judge_name' => $data['judge_name'],
            'corner' => $data['corner'],
            'type' => $data['type'],
            'point' => $data['type'] === 'punch' ? 1 : 2,
            'scored_at' => $now,
        ]);

        // Broadcast ke semua client
        broadcast(new \App\Events\JudgePointSubmitted(
            $data['match_id'],
            $data['judge_number'],
            $data['corner'],
            $data['type']
        ))->toOthers();
        

        // 2. Ambil skor dalam 1.5 detik terakhir
        $recent = \App\Models\LocalJudgeScore::where('round_id', $data['round_id'])
            ->where('corner', $data['corner'])
            ->where('type', $data['type'])
            ->where('scored_at', '>=', $now->copy()->subMilliseconds(1500)) // gunakan milidetik biar akurat
            ->get();

        logger('🧪 Recent scores:', $recent->toArray());

        // 3. Cek minimal 2 juri berbeda
        $uniqueJudges = $recent->pluck('judge_number')->unique();

        if ($uniqueJudges->count() >= 2) {
            // 4. Cek apakah sudah ada validasi untuk waktu tersebut
            $alreadyExists = \App\Models\LocalValidScore::where('round_id', $data['round_id'])
                ->where('corner', $data['corner'])
                ->where('type', $data['type'])
                ->where('validated_at', '>=', $now->copy()->subMilliseconds(1500))
                ->exists();

            if (!$alreadyExists) {
                // Simpan total sebelumnya sebelum insert
                $prevBlue = \App\Models\LocalValidScore::where('local_match_id', $data['match_id'])
                ->where('round_id', $data['round_id'])
                ->where('corner', 'blue')
                ->sum('point');

                $prevRed = \App\Models\LocalValidScore::where('local_match_id', $data['match_id'])
                ->where('round_id', $data['round_id'])
                ->where('corner', 'red')
                ->sum('point');
                // 5. Simpan ke local_valid_scores
                \App\Models\LocalValidScore::create([
                    'local_match_id' => $data['match_id'],
                    'round_id' => $data['round_id'],
                    'corner' => $data['corner'],
                    'type' => $data['type'],
                    'point' => $data['type'] === 'punch' ? 1 : 2,
                    'validated_at' => $now,
                ]);

                // Hitung ulang total setelah insert
                $newBlue = \App\Models\LocalValidScore::where('local_match_id', $data['match_id'])
                ->where('round_id', $data['round_id'])
                ->where('corner', 'blue')
                ->sum('point');

                $newRed = \App\Models\LocalValidScore::where('local_match_id', $data['match_id'])
                ->where('round_id', $data['round_id'])
                ->where('corner', 'red')
                ->sum('point');

               

                // baru kirim event
               // Kirim event
                /*broadcast(new ScoreUpdated(
                    $data['match_id'],
                    $data['round_id'],
                    $newBlue,
                    $newRed,
                    $newBlue - $prevBlue,
                    $newRed - $prevRed
                ))->toOthers();*/
                // Ambil penyesuaian dari tindakan wasit
                $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['match_id'])
                ->where('round_id', $data['round_id'])
                ->where('corner', 'blue')
                ->sum('point_change');

                $redAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['match_id'])
                ->where('round_id', $data['round_id'])
                ->where('corner', 'red')
                ->sum('point_change');

                // Kirim broadcast lengkap
                broadcast(new ScoreUpdated(
                $data['match_id'],
                $data['round_id'],
                $newBlue + $blueAdjustment,
                $newRed + $redAdjustment,
                $newBlue - $prevBlue,
                $newRed - $prevRed
                ))->toOthers();



                logger('📢 Broadcast ScoreUpdated', [
                    'match_id' => $data['match_id'],
                    'blue_score' => $newBlue + $blueAdjustment,
                    'red_score' => $newRed + $redAdjustment,
                ]);
                
            }
        }

        return response()->json(['message' => 'Point submitted']);
    }

    public function refereeAction(Request $request)
    {
        $data = $request->validate([
            'local_match_id' => 'required|exists:local_matches,id',
            'round_id' => 'required|exists:local_match_rounds,id',
            'corner' => 'required|in:red,blue',
            'action' => 'required|in:jatuhan,binaan_1,binaan_2,teguran_1,teguran_2,peringatan_1,peringatan_2',
        ]);

        // 🎯 Hitung perubahan poin berdasarkan action
        $actionPoints = [
            'jatuhan' => 3,
            'binaan_1' => 0,
            'binaan_2' => 0,
            'teguran_1' => -1,
            'teguran_2' => -2,
            'peringatan_1' => -5,
            'peringatan_2' => -10,
        ];

        $data['point_change'] = $actionPoints[$data['action']];

        // 💾 Simpan ke DB
        LocalRefereeAction::create($data);

        broadcast(new \App\Events\RefereeActionSubmitted(
            $data['local_match_id'],
            $data['corner'],
            $data['action'],
            $data['point_change']
        ))->toOthers();

        // 🔢 Hitung total score
        $blueScore = LocalValidScore::where('local_match_id', $data['local_match_id'])
            ->where('round_id', $data['round_id'])
            ->where('corner', 'blue')
            ->sum('point');

        $redScore = LocalValidScore::where('local_match_id', $data['local_match_id'])
            ->where('round_id', $data['round_id'])
            ->where('corner', 'red')
            ->sum('point');

        // Tambahan poin dari tindakan wasit
        $blueAdjustment = LocalRefereeAction::where('local_match_id', $data['local_match_id'])
            ->where('round_id', $data['round_id'])
            ->where('corner', 'blue')
            ->sum('point_change');

        $redAdjustment = LocalRefereeAction::where('local_match_id', $data['local_match_id'])
            ->where('round_id', $data['round_id'])
            ->where('corner', 'red')
            ->sum('point_change');

        // 🔊 Broadcast score ke semua layar
        broadcast(new ScoreUpdated(
            $data['local_match_id'],
            $data['round_id'],
            $blueScore + $blueAdjustment,
            $redScore + $redAdjustment,
            $blueAdjustment,
            $redAdjustment
        ))->toOthers();

        
        

        return response()->json(['message' => 'Tindakan wasit berhasil disimpan']);
    }

    public function getRecap($matchId)
    {
        $match = LocalMatch::with('rounds')->findOrFail($matchId);
        $recap = [];

        foreach ($match->rounds as $round) {
            $roundData = [
                'round_number' => $round->round_number,
                'judges' => [],
                'valid_scores' => ['blue' => [], 'red' => []],
                'jatuhan' => ['blue' => 0, 'red' => 0],
                'hukuman' => ['blue' => 0, 'red' => 0],
                'final' => ['blue' => 0, 'red' => 0],
            ];

            // 💥 Nilai juri
            for ($i = 1; $i <= 3; $i++) {
                foreach (['blue', 'red'] as $corner) {
                    $points = LocalJudgeScore::where([
                            'local_match_id' => $matchId,
                            'round_id' => $round->id,
                            'judge_number' => $i,
                            'corner' => $corner
                        ])
                        ->orderBy('scored_at')
                        ->pluck('point')
                        ->toArray();

                    $roundData['judges'][] = [
                        'judge' => "Juri $i",
                        'corner' => $corner,
                        'points' => $points,
                        'total' => array_sum($points),
                    ];
                }
            }

            // ✅ Nilai Sah
            foreach (['blue', 'red'] as $corner) {
                $valid = LocalValidScore::where([
                        'local_match_id' => $matchId,
                        'round_id' => $round->id,
                        'corner' => $corner,
                    ])->pluck('point')->toArray();

                $roundData['valid_scores'][$corner] = [
                    'points' => $valid,
                    'total' => array_sum($valid),
                ];
            }

            // ✅ Jatuhan
            foreach (['blue', 'red'] as $corner) {
                $jatuhan = LocalRefereeAction::where([
                        'local_match_id' => $matchId,
                        'round_id' => $round->id,
                        'corner' => $corner,
                        'action' => 'jatuhan'
                    ])->sum('point_change');
                $roundData['jatuhan'][$corner] = $jatuhan;
            }

            // ✅ Hukuman
            foreach (['blue', 'red'] as $corner) {
                $hukuman = LocalRefereeAction::where([
                        'local_match_id' => $matchId,
                        'round_id' => $round->id,
                        'corner' => $corner
                    ])->whereIn('action', [
                        'teguran_1', 'teguran_2',
                        'peringatan_1', 'peringatan_2',
                    ])->sum('point_change');
                $roundData['hukuman'][$corner] = $hukuman;
            }

            // ✅ Nilai akhir = sah + jatuhan + hukuman
            foreach (['blue', 'red'] as $corner) {
                $roundData['final'][$corner] =
                    $roundData['valid_scores'][$corner]['total'] +
                    $roundData['jatuhan'][$corner] +
                    $roundData['hukuman'][$corner];
            }

            $recap[] = $roundData;
        }

        return response()->json($recap);
    }





}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller; // Added this import
use App\Models\LocalMatch;
use App\Models\LocalJudgeScore;
use App\Models\LocalRefereeAction;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Events\ScoreUpdated;
use App\Events\JudgePointSubmitted;

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

    private function calculateScore($matchId, $corner)
    {
        $judgePoints = \App\Models\LocalJudgeScore::where('local_match_id', $matchId)
            ->where('corner', $corner)
            ->sum('point');

        $refereePoints = \App\Models\LocalRefereeAction::where('local_match_id', $matchId)
            ->where('corner', $corner)
            ->sum('point_change');

        return $judgePoints + $refereePoints;
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
                broadcast(new ScoreUpdated(
                    $data['match_id'],
                    $data['round_id'],
                    $newBlue,
                    $newRed,
                    $newBlue - $prevBlue,
                    $newRed - $prevRed
                ))->toOthers();


                logger('📢 Broadcast ScoreUpdated', [
                    'match_id' => $data['match_id'],
                    'blue_score' => $blueScore,
                    'red_score' => $redScore,
                ]);
            }
        }

        return response()->json(['message' => 'Point submitted']);
    }




}
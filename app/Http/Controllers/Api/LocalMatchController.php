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
use Illuminate\Support\Facades\Cache;
use App\Events\VerificationRequested;
use App\Events\VerificationResulted;
use Barryvdh\DomPDF\Facade\Pdf;

class LocalMatchController extends Controller
{
    private $live_server;

    public function __construct()
    {
        $this->live_server = config('app_settings.data_source');
    }
    // Menampilkan semua pertandingan
    public function index(Request $request)
    {
        $arena = session('arena_name');
        $tournament = session('tournament_name');

        $query =  $query = LocalMatch::query();

        if ($arena) {
            $query->where('arena_name', $arena);
        }

        if ($tournament) {
            $query->where('tournament_name', $tournament);
        }

        // Urutkan berdasarkan arena, pool, kelas, dan round_level
        $matches = $query->orderBy('arena_name')
            ->orderBy('pool_name')
            ->orderBy('class_name')
            ->orderBy('round_level')
            ->orderBy('match_number')
            ->get();

        // Group by arena → pool (1 pool = 1 kelas)
        $grouped = $matches->groupBy(['arena_name', 'pool_name']);

        return response()->json($grouped);
    }

   public function fetchMatchForAdmin(Request $request)
    {
        $arena = session('arena_name');
        $tournament = session('tournament_name'); // ✅ ambil nama turnamen dari session
       

        $query = LocalMatch::query();

        if ($arena) {
            $query->where('arena_name', $arena);
        }

        if ($tournament) {
            $query->where('tournament_name', $tournament);
        }

        // Urutkan berdasarkan arena, pool, kelas, dan round_level
        $matches = $query->orderBy('arena_name')
            ->orderBy('pool_name')
            ->orderBy('class_name')
            ->orderBy('round_level')
            ->orderBy('match_number')
            ->get();

        // Group by arena → pool (1 pool = 1 kelas)
        $grouped = $matches->groupBy(['arena_name', 'pool_name']);

        return response()->json($grouped);
    }

    public function exportLocalMatches()
    {
        $arena = session('arena_name');
        $tournament = session('tournament_name');
        

        $query = \App\Models\LocalMatch::query();

        if ($arena) {
            $query->where('arena_name', $arena);
        }

        if ($tournament) {
            $query->where('tournament_name', $tournament);
        }

        $matches = $query->orderBy('arena_name')
            ->orderBy('pool_name')
            ->orderBy('class_name')
            ->orderBy('round_level')
            ->orderBy('match_number')
            ->get();

        $grouped = $matches->groupBy(['arena_name', 'pool_name'])->map(function ($pools) {
            return $pools->map(function ($matches) {
                return $matches->sortBy('match_number');
            });
        });


        $pdf = \PDF::loadView('exports.local-matches', [
            'grouped' => $grouped,
            'arena' => $arena,
            'tournament' => $tournament,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("daftar-pertandingan-{$arena}.pdf");
    }

     


    public function fetchLiveMatches(Request $request)
    {
        $arena = session('arena_name');

        $query = LocalMatch::query();

        if ($arena) {
            $query->where('arena_name', $arena);
        }

        // ✅ Filter hanya match yang sedang berlangsung
        $query->where('status', 'in_progress');

        // Urutkan berdasarkan arena, pool, kelas, dan round_level
        $matches = $query->orderBy('arena_name')
            ->orderBy('pool_name')
            ->orderBy('class_name')
            ->orderBy('round_level')
            ->orderBy('match_number')
            ->get();

        // Group by arena → pool
        $grouped = $matches->groupBy(['arena_name', 'pool_name']);

        return response()->json($grouped);
    }


    public function getBracket(Request $request)
    {
        $tournament = $request->query('tournament');
        $arena = $request->query('arena');
        $pool = $request->query('pool');

        $matches = LocalMatch::where('tournament_name', $tournament)
            ->where('arena_name', $arena)
            ->where('pool_name', $pool)
            ->orderBy('round_level')
            ->orderBy('match_number')
            ->get();

        return response()->json($matches);
    }



    // Menampilkan pertandingan berdasarkan ID
    public function show_asli($id)
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
            'match_number' => $match->match_number,
            'class_name' => $match->class_name,
            'status' => $match->status,
            'is_display_timer' => $match->is_display_timer,
            'round_level' => $match->round_level,
            'round_label' => $match->round_label,
            'round_duration' => $match->round_duration,
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

   public function show($id)
    {
        $match = LocalMatch::with([
            'rounds' => function ($query) {
                $query->orderBy('round_number');
            }
        ])->findOrFail($id);

        // Skor
        $red_score = $this->calculateScore($id, 'red');
        $blue_score = $this->calculateScore($id, 'blue');

        // Total penalti semua ronde (selain jatuhan)
        $bluePenalty = LocalRefereeAction::where('local_match_id', $id)
            ->where('corner', 'blue')
            ->where('action', '!=', 'jatuhan')
            ->sum('point_change');

        $redPenalty = LocalRefereeAction::where('local_match_id', $id)
            ->where('corner', 'red')
            ->where('action', '!=', 'jatuhan')
            ->sum('point_change');

        // Jatuhan
        $blueFallCount = LocalRefereeAction::where('local_match_id', $id)
            ->where('corner', 'blue')
            ->where('action', 'jatuhan')
            ->count();

        $redFallCount = LocalRefereeAction::where('local_match_id', $id)
            ->where('corner', 'red')
            ->where('action', 'jatuhan')
            ->count();

        // Warnings (global count)
        $blueWarnings = LocalRefereeAction::where('local_match_id', $id)
            ->where('corner', 'blue')
            ->whereIn('action', ['peringatan_1', 'peringatan_2'])
            ->count();

        $redWarnings = LocalRefereeAction::where('local_match_id', $id)
            ->where('corner', 'red')
            ->whereIn('action', ['peringatan_1', 'peringatan_2'])
            ->count();

        // Penalties detail untuk display
        $penaltyTypes = [
            'binaan_1', 'binaan_2',
            'teguran_1', 'teguran_2',
            'peringatan_1', 'peringatan_2'
        ];

        $penalties = LocalRefereeAction::where('local_match_id', $id)
            ->whereIn('action', $penaltyTypes)
            ->select('corner', 'action')
            ->get()
            ->groupBy('corner')
            ->map(function ($group) {
                return $group->pluck('action')->unique()->values();
            });

        // Winner logic
        $winner = null;
        if ($blue_score > $red_score) {
            $winner = 'blue';
        } elseif ($red_score > $blue_score) {
            $winner = 'red';
        } else {
            if ($bluePenalty < $redPenalty) {
                $winner = 'blue';
            } elseif ($redPenalty < $bluePenalty) {
                $winner = 'red';
            }
        }

        return response()->json([
            'id' => $match->id,
            'tournament_name' => $match->tournament_name,
            'arena_name' => $match->arena_name,
            'match_code' => $match->match_code,
            'match_number' => $match->match_number,
            'class_name' => $match->class_name,
            'status' => $match->status,
            'is_display_timer' => $match->is_display_timer,
            'round_level' => $match->round_level,
            'round_label' => $match->round_label,
            'round_duration' => $match->round_duration,
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

            // Tambahan untuk display
            'blueFallCount' => $blueFallCount,
            'redFallCount' => $redFallCount,
            'blueWarnings' => $blueWarnings,
            'redWarnings' => $redWarnings,
            'winner_corner' => $winner,
            'penalties' => $penalties,
        ]);
    }

    public function endMatch(Request $request, $id)
    {
        $match = LocalMatch::findOrFail($id);

        $blueScore = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point');
        $redScore = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'red')->sum('point');

        $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point_change');
        $redAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'red')->sum('point_change');

        $totalBlue = $blueScore + $blueAdjustment;
        $totalRed = $redScore + $redAdjustment;

        $match->status = 'finished';
        $match->participant_1_score = $totalBlue;
        $match->participant_2_score = $totalRed;

        if ($request->filled('winner') && $request->filled('reason')) {
            $request->validate([
                'winner' => 'in:red,blue,draw',
                'reason' => 'string|max:255',
            ]);

            if ($request->winner === 'draw') {
                $match->winner_corner = null;
                $match->winner_id = null;
                $match->winner_name = null;
                $match->winner_contingent = null;
            } else {
                $corner = $request->winner;
                $match->winner_corner = $corner;
                $match->winner_id = $match->{$corner . '_id'};
                $match->winner_name = $match->{$corner . '_name'};
                $match->winner_contingent = $match->{$corner . '_contingent'};
            }

            $match->win_reason = $request->reason;
        }

        $match->save();

        $match->rounds()->where('status', 'in_progress')->update([
            'status' => 'finished',
            'end_time' => now(),
        ]);

        /*$baseUrl = $this->live_server;
        $client = new \GuzzleHttp\Client();

        try {
            $client->post($baseUrl . '/api/update-tanding-match-status', [
                'json' => [
                    'remote_match_id' => $match->remote_match_id,
                    'status' => 'finished',
                    'participant_1_score' => $totalBlue, // biru
                    'participant_2_score' => $totalRed,  // merah
                    'winner_id' => $match->winner_id,
                    'winner_corner' => $match->winner_corner,
                    'win_reason' => $match->win_reason,
                ],
                'timeout' => 5,
            ]);

            \Log::info('✅ Status pertandingan dikirim ke server pusat', [
                'remote_match_id' => $match->remote_match_id,
                'winner_id' => $match->winner_id,
                'winner_corner' => $match->winner_corner,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('⚠️ Gagal kirim status finished ke server pusat', [
                'remote_match_id' => $match->remote_match_id,
                'error' => $e->getMessage(),
            ]);
        }*/

        if ($match->winner_corner && $match->winner_corner !== 'draw') {
            $nextMatches = LocalMatch::where(function ($query) use ($match) {
                $query->where('parent_match_red_id', $match->id)
                    ->orWhere('parent_match_blue_id', $match->id);
            })->get();

            foreach ($nextMatches as $nextMatch) {
                $slot = null;

                if ($nextMatch->parent_match_red_id == $match->id) {
                    $nextMatch->red_id = $match->winner_id;
                    $nextMatch->red_name = $match->winner_name;
                    $nextMatch->red_contingent = $match->winner_contingent;
                    $slot = 2; // merah = participant_2
                }

                if ($nextMatch->parent_match_blue_id == $match->id) {
                    $nextMatch->blue_id = $match->winner_id;
                    $nextMatch->blue_name = $match->winner_name;
                    $nextMatch->blue_contingent = $match->winner_contingent;
                    $slot = 1; // biru = participant_1
                }

                $nextMatch->save();

                if ($nextMatch->remote_match_id && $slot) {
                    try {
                        $client->post($baseUrl . '/api/update-next-match-slot', [
                            'json' => [
                                'remote_match_id' => $nextMatch->remote_match_id,
                                'slot' => $slot,             // 1 = biru, 2 = merah
                                'winner_id' => $match->winner_id,
                            ],
                            'timeout' => 5,
                        ]);
                    } catch (\Throwable $e) {
                        \Log::warning('⚠️ Gagal update next match di server pusat', [
                            'remote_match_id' => $nextMatch->remote_match_id,
                            'slot' => $slot,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        return response()->json(['message' => 'Pertandingan diakhiri dan pemenang disimpan.']);
    }

    public function setWinnerManual(Request $request, $id)
    {
        $request->validate([
            'corner' => 'required|in:blue,red',
        ]);

        $match = LocalMatch::findOrFail($id);

        // Set manual winner
        $corner = $request->corner;
        $match->winner_corner = $corner;
        $match->winner_id = $match->{$corner . '_id'};
        $match->winner_name = $match->{$corner . '_name'};
        $match->winner_contingent = $match->{$corner . '_contingent'};
        $match->win_reason = 'manual';
        $match->status = 'finished';

        $match->save();

        // Hentikan semua round yang masih aktif
        $match->rounds()->where('status', 'in_progress')->update([
            'status' => 'finished',
            'end_time' => now(),
        ]);

        // Set peserta ke pertandingan selanjutnya
        if ($match->winner_id) {
            $nextMatches = LocalMatch::where(function ($q) use ($match) {
                $q->where('parent_match_red_id', $match->id)
                ->orWhere('parent_match_blue_id', $match->id);
            })->get();

            foreach ($nextMatches as $nextMatch) {
                $slot = null;

                if ($nextMatch->parent_match_red_id == $match->id) {
                    $nextMatch->red_id = $match->winner_id;
                    $nextMatch->red_name = $match->winner_name;
                    $nextMatch->red_contingent = $match->winner_contingent;
                    $slot = 2;
                }

                if ($nextMatch->parent_match_blue_id == $match->id) {
                    $nextMatch->blue_id = $match->winner_id;
                    $nextMatch->blue_name = $match->winner_name;
                    $nextMatch->blue_contingent = $match->winner_contingent;
                    $slot = 1;
                }

                $nextMatch->save();

                // Optional: kalau pakai sinkronisasi ke server pusat
                /*
                if ($nextMatch->remote_match_id && $slot) {
                    try {
                        $client->post($baseUrl . '/api/update-next-match-slot', [
                            'json' => [
                                'remote_match_id' => $nextMatch->remote_match_id,
                                'slot' => $slot,
                                'winner_id' => $match->winner_id,
                            ],
                            'timeout' => 5,
                        ]);
                    } catch (\Throwable $e) {
                        \Log::warning('⚠️ Gagal update next match di server pusat', [
                            'remote_match_id' => $nextMatch->remote_match_id,
                            'slot' => $slot,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                */
            }
        }

        return response()->json(['message' => 'Pemenang diset manual dan diteruskan ke babak selanjutnya.']);
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


    public function endMatch_($id)
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

    

    public function submitPoint_(Request $request)
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

        // ✅ 1. Simpan ke local_judge_scores
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

        // ✅ 2. Broadcast JudgePointSubmitted (untuk highlight juri)
        broadcast(new \App\Events\JudgePointSubmitted(
            $data['match_id'],
            $data['judge_number'],
            $data['corner'],
            $data['type']
        ))->toOthers();

        broadcast(new \App\Events\JudgeActionSubmitted(
            $data['match_id'],
            $data['corner'],
            $data['judge_number'],
            $data['type']
        ))->toOthers();

        // ✅ 3. Cek skor dalam 1.5 detik terakhir
        $recent = \App\Models\LocalJudgeScore::where('round_id', $data['round_id'])
            ->where('corner', $data['corner'])
            ->where('type', $data['type'])
            ->where('scored_at', '>=', $now->copy()->subMilliseconds(1500))
            ->get();

        logger('🧪 Recent scores:', $recent->toArray());

        $uniqueJudges = $recent->pluck('judge_number')->unique();
        $isValid = false;

        if ($uniqueJudges->count() >= 2) {
            // ✅ 4. Cek apakah validasi ini sudah pernah terjadi
            $alreadyExists = \App\Models\LocalValidScore::where('round_id', $data['round_id'])
                ->where('corner', $data['corner'])
                ->where('type', $data['type'])
                ->where('validated_at', '>=', $now->copy()->subMilliseconds(1500))
                ->exists();

            if (!$alreadyExists) {
                // ✅ 5. Insert ke local_valid_scores
                \App\Models\LocalValidScore::create([
                    'local_match_id' => $data['match_id'],
                    'round_id' => $data['round_id'],
                    'corner' => $data['corner'],
                    'type' => $data['type'],
                    'point' => $data['type'] === 'punch' ? 1 : 2,
                    'validated_at' => $now,
                ]);

                $isValid = true;

                // ✅ 6. Hitung total score AKUMULASI semua ronde
                $newBlue = \App\Models\LocalValidScore::where('local_match_id', $data['match_id'])
                    ->where('corner', 'blue')
                    ->sum('point');

                $newRed = \App\Models\LocalValidScore::where('local_match_id', $data['match_id'])
                    ->where('corner', 'red')
                    ->sum('point');

                // ✅ 7. Hitung total adjustment AKUMULASI semua ronde
                $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['match_id'])
                    ->where('corner', 'blue')
                    ->sum('point_change');

                $redAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['match_id'])
                    ->where('corner', 'red')
                    ->sum('point_change');

                // ✅ 8. Broadcast ScoreUpdated
                broadcast(new \App\Events\ScoreUpdated(
                    $data['match_id'],
                    $data['round_id'],
                    $newBlue + $blueAdjustment, // Blue score AKUMULASI
                    $newRed + $redAdjustment,   // Red score AKUMULASI
                    0, // blueAdjustment sementara kosongkan
                    0  // redAdjustment sementara kosongkan
                ))->toOthers();

                logger('📢 Broadcast ScoreUpdated', [
                    'match_id' => $data['match_id'],
                    'blue_score' => $newBlue + $blueAdjustment,
                    'red_score' => $newRed + $redAdjustment,
                ]);
            }
        }

        // ✅ 9. Return response
        return response()->json([
            'message' => 'Point submitted',
            'type' => $data['type'],
            'corner' => $data['corner'],
            'round_id' => $data['round_id'],
            'value' => $data['type'] === 'punch' ? 1 : 2,
            'valid' => $isValid
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

        // ✅ 1. Simpan ke local_judge_scores
        $judgeScore = \App\Models\LocalJudgeScore::create([
            'local_match_id' => $data['match_id'],
            'round_id' => $data['round_id'],
            'judge_number' => $data['judge_number'],
            'judge_name' => $data['judge_name'],
            'corner' => $data['corner'],
            'type' => $data['type'],
            'point' => $data['type'] === 'punch' ? 1 : 2,
            'scored_at' => $now,
            'is_validated' => false,
        ]);

        // ✅ 2. Broadcast JudgePointSubmitted (highlight juri)
        broadcast(new \App\Events\JudgePointSubmitted(
            $data['match_id'],
            $data['judge_number'],
            $data['corner'],
            $data['type']
        ))->toOthers();

        broadcast(new \App\Events\JudgeActionSubmitted(
            $data['match_id'],
            $data['corner'],
            $data['judge_number'],
            $data['type']
        ))->toOthers();

        // ✅ 3. Cek skor dalam 2 detik terakhir
        /*$recent = \App\Models\LocalJudgeScore::where('local_match_id', $data['match_id'])
            ->where('round_id', $data['round_id'])
            ->where('corner', $data['corner'])
            ->where('type', $data['type'])
            ->where('scored_at', '>=', $judgeScore->scored_at->subSeconds(2)) // 🔥 2 detik
            ->get();*/

        $recent = \App\Models\LocalJudgeScore::where('local_match_id', $data['match_id'])
        ->where('round_id', $data['round_id'])
        ->where('corner', $data['corner'])
        ->where('type', $data['type'])
        ->where('scored_at', '>=', $judgeScore->scored_at->subSeconds(2)) // 🔥 2 detik
        ->where('is_validated', false) // ✅ Tambahkan ini
        ->get();


        logger('🧪 Recent scores (2 detik):', $recent->toArray());

        $uniqueJudges = $recent->pluck('judge_number')->unique();
        $isValid = false;

        if ($uniqueJudges->count() >= 2) {
            // ✅ 4. Cek apakah validasi ini sudah pernah dicatat
            $alreadyExists = \App\Models\LocalValidScore::where('local_match_id', $data['match_id'])
                ->where('round_id', $data['round_id'])
                ->where('corner', $data['corner'])
                ->where('type', $data['type'])
                ->where('validated_at', '>=', $now->copy()->subSeconds(2)) // 🔥 2 detik
                ->exists();

            if (!$alreadyExists) {
                // ✅ 5. Insert ke local_valid_scores
                \App\Models\LocalValidScore::create([
                    'local_match_id' => $data['match_id'],
                    'round_id' => $data['round_id'],
                    'corner' => $data['corner'],
                    'type' => $data['type'],
                    'point' => $data['type'] === 'punch' ? 1 : 2,
                    'validated_at' => $now,
                ]);

                $isValid = true;

                // ✅ 6. Update semua judge scores (2 detik window) -> is_validated = true
                \App\Models\LocalJudgeScore::where('local_match_id', $data['match_id'])
                    ->where('round_id', $data['round_id'])
                    ->where('corner', $data['corner'])
                    ->where('type', $data['type'])
                    ->where('scored_at', '>=', $judgeScore->scored_at->subSeconds(2))
                    ->update(['is_validated' => true]);

                // ✅ 7. Hitung total score AKUMULASI
                $newBlue = \App\Models\LocalValidScore::where('local_match_id', $data['match_id'])
                    ->where('corner', 'blue')
                    ->sum('point');

                $newRed = \App\Models\LocalValidScore::where('local_match_id', $data['match_id'])
                    ->where('corner', 'red')
                    ->sum('point');

                // ✅ 8. Adjustment
                $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['match_id'])
                    ->where('corner', 'blue')
                    ->sum('point_change');

                $redAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['match_id'])
                    ->where('corner', 'red')
                    ->sum('point_change');

                // ✅ 9. Broadcast ScoreUpdated
                broadcast(new \App\Events\ScoreUpdated(
                    $data['match_id'],
                    $data['round_id'],
                    $newBlue + $blueAdjustment,
                    $newRed + $redAdjustment,
                    0, 0
                ))->toOthers();

                logger('📢 Broadcast ScoreUpdated', [
                    'match_id' => $data['match_id'],
                    'blue_score' => $newBlue + $blueAdjustment,
                    'red_score' => $newRed + $redAdjustment,
                ]);
            }
        }

        // ✅ 10. Return
        return response()->json([
            'message' => 'Point submitted',
            'type' => $data['type'],
            'corner' => $data['corner'],
            'round_id' => $data['round_id'],
            'value' => $data['type'] === 'punch' ? 1 : 2,
            'valid' => $isValid
        ]);
    }

    public function judgeRecap($matchId)
    {
        $judgeNumber = session('juri_number');

        // Ambil semua scores dari juri ini dengan relasi localMatchRound
        $scores = \App\Models\LocalJudgeScore::with('localMatchRound') // pakai relasi
            ->where('local_match_id', $matchId)
            ->where('judge_number', $judgeNumber)
            ->orderBy('round_id')
            ->orderBy('scored_at')
            ->get();
        
        
        $recap = [];

        foreach ($scores as $score) {
            $roundNumber = $score->localMatchRound->round_number ?? 0;
            $corner = $score->corner;

            if (!isset($recap[$roundNumber])) {
                $recap[$roundNumber] = ['blue' => [], 'red' => []];
            }

            $recap[$roundNumber][$corner][] = [
                'valid' => (bool) $score->is_validated,
                'type' => $score->type,
            ];
        }

        // Susun response dengan urutan 1-3
        $responseRounds = [];

        for ($i = 1; $i <= 3; $i++) {
            $responseRounds[] = [
                'round_number' => $i,
                'blue' => $recap[$i]['blue'] ?? [],
                'red' => $recap[$i]['red'] ?? [],
            ];
        }

        return response()->json([
            'judge_number' => $judgeNumber,
            'rounds' => $responseRounds,
        ]);
    }




    
    public function judgeRecap3($matchId)
{
    $judgeNumber = session('juri_number');

    $scores = \App\Models\LocalJudgeScore::where('local_match_id', $matchId)
        ->where('judge_number', $judgeNumber)
        ->orderBy('round_id')
        ->orderBy('scored_at')
        ->get();

    $validScores = \App\Models\LocalValidScore::where('local_match_id', $matchId)
        ->orderBy('round_id')
        ->orderBy('validated_at')
        ->get();

    $recap = [
        1 => ['blue' => [], 'red' => []],
        2 => ['blue' => [], 'red' => []],
        3 => ['blue' => [], 'red' => []],
    ];

    $validCount = [
        'blue' => [],
        'red' => []
    ];

    // Hitung jumlah valid per type
    foreach ($validScores as $valid) {
        $validCount[$valid->corner][$valid->type][] = $valid;
    }

    foreach ($scores as $score) {
        $isValid = false;

        // Cek apakah ada valid untuk corner dan type ini
        if (!empty($validCount[$score->corner][$score->type])) {
            // Ambil satu valid lalu pop
            array_shift($validCount[$score->corner][$score->type]);
            $isValid = true;
        }

        $recap[$score->round_id][$score->corner][] = [
            'valid' => $isValid,
            'type' => $score->type
        ];
    }

    return response()->json([
        'rounds' => [
            [
                'round_number' => 1,
                'blue' => $recap[1]['blue'],
                'red' => $recap[1]['red'],
            ],
            [
                'round_number' => 2,
                'blue' => $recap[2]['blue'],
                'red' => $recap[2]['red'],
            ],
            [
                'round_number' => 3,
                'blue' => $recap[3]['blue'],
                'red' => $recap[3]['red'],
            ]
        ]
    ]);
}


    public function judgeRecap_($matchId)
    {
        $judgeNumber = session('juri_number'); // Ambil juri dari session

        // Ambil semua scores dari juri ini
        $scores = \App\Models\LocalJudgeScore::where('local_match_id', $matchId)
            ->where('judge_number', $judgeNumber)
            ->orderBy('round_id')
            ->orderBy('scored_at')
            ->get();

        // Ambil semua valid scores
        $validScores = \App\Models\LocalValidScore::where('local_match_id', $matchId)
            ->get();

        $recap = [
            1 => ['blue' => [], 'red' => []],
            2 => ['blue' => [], 'red' => []],
            3 => ['blue' => [], 'red' => []],
        ];

        foreach ($scores as $score) {
            $isValid = $validScores->contains(function ($valid) use ($score) {
                return 
                    $valid->round_id == $score->round_id &&
                    $valid->corner == $score->corner &&
                    $valid->type == $score->type &&
                    abs(strtotime($valid->validated_at) - strtotime($score->scored_at)) <= 2;
            });

            

            $recap[$score->round_id][$score->corner][] = [
                'valid' => $isValid,
                'type' => $score->type // 🔥 Tambahkan TYPE disini
            ];
        }

        return response()->json([
            'rounds' => [
                [
                    'round_number' => 1,
                    'blue' => $recap[1]['blue'],
                    'red' => $recap[1]['red'],
                ],
                [
                    'round_number' => 2,
                    'blue' => $recap[2]['blue'],
                    'red' => $recap[2]['red'],
                ],
                [
                    'round_number' => 3,
                    'blue' => $recap[3]['blue'],
                    'red' => $recap[3]['red'],
                ]
            ]
        ]);
    }



    

    public function refereeAction(Request $request)
    {
        $data = $request->validate([
            'local_match_id' => 'required|exists:local_matches,id',
            'round_id' => 'required|exists:local_match_rounds,id',
            'corner' => 'required|in:red,blue',
            'action' => 'required|in:jatuhan,binaan_1,binaan_2,teguran_1,teguran_2,peringatan_1,peringatan_2,verifikasi_jatuhan,verifikasi_hukuman',
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
            'verifikasi_jatuhan' => 0,
            'verifikasi_hukuman' => 0,
        ][$data['action']] ?? 0;

        $data['point_change'] = $actionPoints;

        // 💾 Simpan ke DB
        \App\Models\LocalRefereeAction::create($data);

        // 🔊 Broadcast referee action
        broadcast(new \App\Events\RefereeActionSubmitted(
            $data['local_match_id'],
            $data['corner'],
            $data['action'],
            $data['point_change']
        ))->toOthers();

        // 🔢 Hitung total skor AKUMULASI seluruh pertandingan
        $blueScore = \App\Models\LocalValidScore::where('local_match_id', $data['local_match_id'])
            ->where('corner', 'blue')
            ->sum('point');

        $redScore = \App\Models\LocalValidScore::where('local_match_id', $data['local_match_id'])
            ->where('corner', 'red')
            ->sum('point');

        $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['local_match_id'])
            ->where('corner', 'blue')
            ->sum('point_change');

        $redAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['local_match_id'])
            ->where('corner', 'red')
            ->sum('point_change');

        // 🔊 Broadcast skor baru AKUMULASI
        broadcast(new \App\Events\ScoreUpdated(
            $data['local_match_id'],
            $data['round_id'], // Kirim round_id aktif saja
            $blueScore + $blueAdjustment,
            $redScore + $redAdjustment,
            $blueAdjustment,
            $redAdjustment
        ))->toOthers();

        return response()->json(['message' => 'Tindakan wasit berhasil disimpan']);
    }

    public function removeJatuhan(Request $request)
    {
        $data = $request->validate([
            'local_match_id' => 'required|exists:local_matches,id',
            'round_id' => 'required|exists:local_match_rounds,id',
            'corner' => 'required|in:red,blue',
        ]);

        // 🔥 Hapus semua jatuhan untuk sudut & ronde ini
        \App\Models\LocalRefereeAction::where('local_match_id', $data['local_match_id'])
            ->where('round_id', $data['round_id'])
            ->where('corner', $data['corner'])
            ->where('action', 'jatuhan')
            ->delete();

        // 🔢 Hitung ulang skor
        $blueScore = \App\Models\LocalValidScore::where('local_match_id', $data['local_match_id'])
            ->where('corner', 'blue')
            ->sum('point');

        $redScore = \App\Models\LocalValidScore::where('local_match_id', $data['local_match_id'])
            ->where('corner', 'red')
            ->sum('point');

        $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['local_match_id'])
            ->where('corner', 'blue')
            ->sum('point_change');

        $redAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['local_match_id'])
            ->where('corner', 'red')
            ->sum('point_change');

        // 🔊 Broadcast skor terbaru
        broadcast(new \App\Events\ScoreUpdated(
            $data['local_match_id'],
            $data['round_id'],
            $blueScore + $blueAdjustment,
            $redScore + $redAdjustment,
            $blueAdjustment,
            $redAdjustment
        ))->toOthers();

        return response()->json(['message' => 'Jatuhan berhasil dihapus dan skor diperbarui']);
    }


    public function cancelRefereeAction(Request $request)
    {
        $data = $request->validate([
            'match_id' => 'required|exists:local_matches,id',
            'round_id' => 'required|exists:local_match_rounds,id',
            'corner' => 'required|in:red,blue',
            'action' => 'required|string',
        ]);

        // 🔍 Cari record terakhir dengan action & corner yang sama
        $lastAction = \App\Models\LocalRefereeAction::where('local_match_id', $data['match_id'])
            ->where('round_id', $data['round_id'])
            ->where('corner', $data['corner'])
            ->where('action', $data['action'])
            ->latest()
            ->first();

        if (!$lastAction) {
            return response()->json(['message' => 'Tidak ada aksi untuk dibatalkan'], 404);
        }

        // 🔥 Hapus dan hitung ulang skor
        $lastAction->delete();

        broadcast(new \App\Events\RefereeActionCancelled(
            $data['match_id'],
            $data['corner'],
            $data['action']
        ))->toOthers();


        $blueScore = \App\Models\LocalValidScore::where('local_match_id', $data['match_id'])
            ->where('corner', 'blue')
            ->sum('point');

        $redScore = \App\Models\LocalValidScore::where('local_match_id', $data['match_id'])
            ->where('corner', 'red')
            ->sum('point');

        $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['match_id'])
            ->where('corner', 'blue')
            ->sum('point_change');

        $redAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['match_id'])
            ->where('corner', 'red')
            ->sum('point_change');

        broadcast(new \App\Events\ScoreUpdated(
            $data['match_id'],
            $data['round_id'],
            $blueScore + $blueAdjustment,
            $redScore + $redAdjustment,
            $blueAdjustment,
            $redAdjustment
        ))->toOthers();

        return response()->json(['message' => 'Aksi wasit dibatalkan']);
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
                        ->get(); // ⬅️ Ambil objek, bukan pluck

                    $pointData = $points->map(function ($p) {
                        return [
                            'point' => $p->point,
                            'type' => $p->type,
                            'valid' => (bool) $p->is_validated,
                        ];
                    });

                    $roundData['judges'][] = [
                        'judge' => "Juri $i",
                        'corner' => $corner,
                        'points' => $pointData,
                        'total' => $points->sum('point'),
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

    // Route: GET /api/local-matches/tournaments
   public function getTournaments()
    {
        $tanding = \DB::table('local_matches')
            ->select('tournament_name');

        $seni = \DB::table('local_seni_matches')
            ->select('tournament_name');

        $tournaments = $tanding
            ->union($seni)
            ->distinct()
            ->pluck('tournament_name');

        return response()->json($tournaments);
    }


    // Route: GET /api/local-matches/arenas?tournament=Kejuaraan Nasional 2024
   public function getArenas(Request $request)
    {
        $tournament = $request->query('tournament');
        $type = $request->query('type'); // 'tanding' atau 'seni'

        if (!$tournament || !$type) {
            return response()->json([], 400);
        }

        if ($type === 'tanding') {
            $arenas = \DB::table('local_matches')
                ->where('tournament_name', $tournament)
                ->select('arena_name')
                ->distinct()
                ->pluck('arena_name');
        } elseif ($type === 'seni') {
            $arenas = \DB::table('local_seni_matches')
                ->where('tournament_name', $tournament)
                ->select('arena_name')
                ->distinct()
                ->pluck('arena_name');
        } else {
            return response()->json([], 400);
        }

        return response()->json($arenas);
    }


    public function requestVerification_(Request $request)
    {
        $data = $request->validate([
            'match_id' => 'required|integer',
            'round_id' => 'required|integer',
            'type' => 'required|in:jatuhan,hukuman',
            'corner' => 'required|in:blue,red',
        ]);

        // Kosongkan cache vote sebelumnya
        $cacheKey = "verification_votes_{$data['match_id']}_{$data['round_id']}";
        Cache::forget($cacheKey);
        Cache::put($cacheKey, [], now()->addMinutes(5));

        broadcast(new \App\Events\VerificationRequested(
            $data['match_id'],
            $data['round_id'],
            $data['type'],
            $data['corner']
        ))->toOthers();

        return response()->json(['message' => 'Verification request broadcasted']);
    }

    public function requestVerification(Request $request)
    {
        $data = $request->validate([
            'match_id' => 'required|integer',
            'round_id' => 'required|integer',
            'type' => 'required|in:jatuhan,hukuman',
            'corner' => 'required|in:blue,red',
        ]);

        $cacheKey = "verification_votes_{$data['match_id']}_{$data['round_id']}";

        // Simpan struktur lengkap ke cache
        Cache::put($cacheKey, [
            'type' => $data['type'],
            'corner' => $data['corner'],
            'votes' => [],
        ], now()->addMinutes(5));

        broadcast(new \App\Events\VerificationRequested(
            $data['match_id'],
            $data['round_id'],
            $data['type'],
            $data['corner']
        ))->toOthers();

        return response()->json(['message' => 'Verification request broadcasted']);
    }


    public function submitVerificationVote_(Request $request)
    {
        $data = $request->validate([
            'match_id' => 'required|integer',
            'round_id' => 'required|integer',
            'vote' => 'required|in:blue,red,invalid',
            'judge_name' => 'required|string',
        ]);

        $cacheKey = "verification_votes_{$data['match_id']}_{$data['round_id']}";

        $votes = Cache::get($cacheKey, []);

        $votes[] = [
            'judge' => $data['judge_name'],
            'vote' => $data['vote'],
        ];

        Cache::put($cacheKey, $votes, now()->addMinutes(5));

        // Kalau semua 3 juri sudah vote, broadcast hasil
        if (count($votes) >= 3) {
            broadcast(new VerificationResulted(
                $data['match_id'],
                $data['round_id'],
                $votes
            ))->toOthers();

            // Hapus cache
            Cache::forget($cacheKey);
        }

        return response()->json(['message' => 'Vote recorded']);
    }

    public function submitVerificationVote(Request $request)
    {
        $data = $request->validate([
            'match_id' => 'required|integer',
            'round_id' => 'required|integer',
            'vote' => 'required|in:blue,red,invalid',
            'judge_name' => 'required|string',
        ]);

        $cacheKey = "verification_votes_{$data['match_id']}_{$data['round_id']}";
        $cached = Cache::get($cacheKey);

        // ✅ Pastikan struktur cache valid
        if (!$cached || !isset($cached['votes'])) {
            return response()->json(['message' => 'No verification in progress'], 400);
        }

        $votes = collect($cached['votes']);

        // Hindari duplicate vote dari juri yang sama
        $votes = $votes->reject(fn ($v) => $v['judge'] === $data['judge_name'])->values();

        // Tambahkan vote baru
        $votes->push([
            'judge' => $data['judge_name'],
            'vote' => $data['vote'],
        ]);

        // Update cache dengan vote terbaru
        Cache::put($cacheKey, [
            'type' => $cached['type'],
            'corner' => $cached['corner'], // tetap simpan corner permintaan awal
            'votes' => $votes->toArray(),
        ], now()->addMinutes(5));

        // ✅ Jika sudah 3 vote, proses hasilnya
        if ($votes->count() >= 3) {
            // 🔎 Hitung mayoritas
            $blueVotes = $votes->where('vote', 'blue')->count();
            $redVotes = $votes->where('vote', 'red')->count();

            // 🧠 Tentukan sudut mayoritas
            $majorityCorner = $blueVotes > $redVotes ? 'blue' : 'red';

            // 🔊 Broadcast hasil verifikasi
            broadcast(new VerificationResulted(
                $data['match_id'],
                $data['round_id'],
                $votes->toArray(),
                $cached['type'],
                $majorityCorner // ✅ Kirim sudut hasil vote mayoritas
            ))->toOthers();

            // 🧹 Bersihkan cache
            Cache::forget($cacheKey);
        }

        return response()->json(['message' => 'Vote recorded']);
    }








}
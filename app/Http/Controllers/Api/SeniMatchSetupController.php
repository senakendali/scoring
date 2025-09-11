<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Events\SeniMatchStarted;
use App\Events\SeniTimerStarted;
use App\Events\SeniTimerUpdated;
use App\Events\SeniTimerFinished;
use App\Events\SeniActiveMatchChanged;
use App\Events\SeniBattleGroupCompleted;
use App\Models\LocalSeniMatch;
use App\Models\LocalSeniScore;
use App\Models\LocalSeniPenalties;
use App\Models\MatchPersonnelAssignment;
use App\Models\LocalSeniFinalScore;
use App\Models\LocalSeniComponentScore;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Str;


class SeniMatchSetupController extends Controller
{
    private $live_server;

    public function __construct()
    {
        $this->live_server = config('app_settings.data_source');
    }
    public function start(Request $request)
    {
        $request->validate([
            'match_id' => 'required|integer',
            'arena_name' => 'required|string',
            'tournament_name' => 'required|string',
        ]);

        try {
            $match = LocalSeniMatch::findOrFail($request->match_id);

            // ✅ Update status jadi ongoing
            $match->status = 'ongoing';
            $match->save();

            // ✅ Broadcast ke frontend
            event(new SeniMatchStarted(
                $match->id,
                $request->arena_name,
                $request->tournament_name
            ));

            return response()->json(['message' => 'Match started']);
        } catch (\Throwable $e) {
            Log::error('Gagal memulai match: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Gagal memulai match',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function startPerformance($id, Request $request)
    {
        $request->validate([
            'duration' => 'required|integer|min:60|max:600'
        ]);

        $match = LocalSeniMatch::findOrFail($id);
        $duration = (int) $request->duration;

        // ✅ Set data mulai
        $match->start_time = now();
        $match->pause_time = null;
        $match->end_time = null;
        $match->status = 'ongoing';
        $match->duration = $duration;
        $match->save();

        // ✅ Ambil ulang agar data konsisten & format ISO ready
        $fresh = LocalSeniMatch::find($match->id);

        /*if ($match) {
            // ✅ Kirim status ke server pusat
            try {
                $client = new \GuzzleHttp\Client();

                $response = $client->post($this->live_server . '/api/update-seni-match-status', [
                    'json' => [
                        'remote_match_id' => $match->remote_match_id,
                        'status' => 'ongoing',
                    ],
                    'timeout' => 5,
                ]);

                \Log::info('✅ Status pertandingan seni dikirim ke server pusat', [
                    'remote_match_id' => $match->remote_match_id,
                    'status' => 'ongoing',
                    'http_code' => $response->getStatusCode()
                ]);
            } catch (\Throwable $e) {
                \Log::warning('⚠️ Gagal kirim status ke server pusat', [
                    'remote_match_id' => $match->remote_match_id,
                    'error' => $e->getMessage()
                ]);
            }
        }*/

        // ✅ Logging buat debug
        \Log::info("📦 [Seni] Match Dimulai", [
            'id' => $fresh->id,
            'match_type' => $fresh->match_type,
            'start_time' => optional($fresh->start_time)->toIso8601String(),
            'duration' => $fresh->duration,
        ]);

       broadcast(new \App\Events\SeniTimerStarted($fresh));


        return response()->json([
            'message' => 'Penampilan dimulai.',
            'start_time' => optional($fresh->start_time)->toIso8601String(),
            'duration' => $fresh->duration,
        ]);
    }


    public function startPerformance_($id, Request $request)
    {
        $request->validate([
            'duration' => 'required|integer|min:60|max:600'
        ]);

        $match = LocalSeniMatch::findOrFail($id);

        $duration = (int) $request->duration;

        $match->start_time = now(); // ✅ INI HARUS ADA
        $match->pause_time = null;
        $match->end_time = null;
        $match->status = 'ongoing';
        $match->duration = $duration;
        $match->save();
        
        // ✅ PAKSA AMBIL ULANG agar $match->start_time ke-load dengan benar
        $fresh = \App\Models\LocalSeniMatch::find($match->id);

        // ✅ LOG DEBUG
        \Log::info("📦 FRESH MATCH BEFORE BROADCAST", [
            'id' => $fresh->id,
            'start_time' => $fresh->start_time,
            'class' => get_class($fresh->start_time),
        ]);

        broadcast(new \App\Events\SeniTimerStarted($fresh));


        return response()->json([
            'message' => 'Penampilan dimulai.',
            'start_time' => $match->start_time,
            'duration' => $duration
        ]);
    }


    public function pause($id)
    {
        $match = LocalSeniMatch::findOrFail($id);

        if ($match->status !== 'ongoing') {
            return response()->json(['message' => 'Pertandingan tidak sedang berjalan.'], 400);
        }

        $match->pause_time = now();
        $match->status = 'paused';
        $match->save();

        broadcast(new SeniTimerUpdated($match));

        return response()->json(['message' => 'Pertandingan dipause.']);
    }

   

    public function resume($id)
    {
        $match = LocalSeniMatch::findOrFail($id);

        if ($match->status !== 'paused') {
            return response()->json(['message' => 'Pertandingan tidak dalam keadaan pause.'], 400);
        }

        // ✅ Convert start_time dan pause_time ke Carbon
        $start = $match->start_time ? Carbon::parse($match->start_time) : null;
        $pause = $match->pause_time ? Carbon::parse($match->pause_time) : null;

        $elapsed = $start && $pause ? $start->diffInSeconds($pause) : 0;

        // ⏱️ Hitung ulang start_time berdasarkan waktu sekarang
        $match->start_time = now()->subSeconds($elapsed);
        $match->pause_time = null;
        $match->status = 'ongoing';
        $match->save();

        $match->refresh(); // ⛳ Refresh model untuk pastikan data terupdate

        broadcast(new SeniTimerUpdated($match))->toOthers(); // ✅ kirim yang fresh

        return response()->json([
            'message' => 'Pertandingan dilanjutkan.',
            'start_time' => $match->start_time->toIso8601String(), // ✅ kirim dalam format ISO
            'elapsed' => $elapsed,
            'now' => now()->toIso8601String(),
        ]);
    }


    public function reset($id)
    {
        $match = \App\Models\LocalSeniMatch::findOrFail($id);

        // Reset status match
        $match->start_time = null;
        $match->pause_time = null;
        $match->end_time = null;
        $match->status = 'not_started';
        $match->final_score = null;
        $match->disqualified = 'no';
        $match->disqualification_reason = null;
        $match->save();

        // Hapus semua nilai/deduksi dari juri
        \App\Models\LocalSeniScore::where('local_match_id', $match->id)->delete();

        // (Opsional) kalau lu punya tabel penalti, hapus juga
        \App\Models\LocalSeniPenalties::where('local_match_id', $match->id)->delete();

        // (Opsional) kalau lu punya tabel komponen skor, hapus juga
        \App\Models\LocalSeniComponentScore::where('local_match_id', $match->id)->delete();

        // (Opsional) kalau lu punya tabel skor akhir, hapus juga
        \App\Models\LocalSeniFinalScore::where('local_match_id', $match->id)->delete();

        /*if ($match) {
            // ✅ Kirim status ke server pusat
            try {
                $client = new \GuzzleHttp\Client();

                $response = $client->post($this->live_server . '/api/update-seni-match-status', [
                    'json' => [
                        'remote_match_id' => $match->remote_match_id,
                        'status' => 'not_started',
                    ],
                    'timeout' => 5,
                ]);

                \Log::info('✅ Status pertandingan seni dikirim ke server pusat', [
                    'remote_match_id' => $match->remote_match_id,
                    'status' => 'not_started',
                    'http_code' => $response->getStatusCode()
                ]);
            } catch (\Throwable $e) {
                \Log::warning('⚠️ Gagal kirim status ke server pusat', [
                    'remote_match_id' => $match->remote_match_id,
                    'error' => $e->getMessage()
                ]);
            }
        }*/

        // Broadcast timer update biar semua UI reset
        broadcast(new \App\Events\SeniTimerUpdated($match))->toOthers();

        return response()->json([
            'message' => 'Pertandingan seni direset.'
        ]);
    }

    public function finish($id, \Illuminate\Http\Request $request)
{
    // Kumpulkan event untuk dibroadcast setelah commit (data sudah konsisten)
    $eventsToBroadcast = [];

    $result = \DB::transaction(function () use ($id, $request, &$eventsToBroadcast) {

        /** @var \App\Models\LocalSeniMatch $match */
        $match = \App\Models\LocalSeniMatch::findOrFail($id);

        if ($match->status === 'finished') {
            return response()->json(['message' => 'Pertandingan sudah selesai.'], 400);
        }

        // ========== 1) DURASI (detik) ==========
        $durationSeconds = null;
        if ($request->filled('duration')) {
            $durationSeconds = (int) $request->input('duration'); // sudah detik
        } elseif ($request->filled('performance_time_seconds')) {
            $durationSeconds = (int) $request->input('performance_time_seconds');
        } elseif ($request->filled('performance_time_input')) {
            $durationSeconds = $this->parseDurationToSeconds((string) $request->input('performance_time_input')); // dukung 3,5 / 3.5 / 3:30
            if ($durationSeconds === null) {
                return response()->json([
                    'message' => 'Format durasi tidak valid. Gunakan mm:ss atau menit desimal (mis. 3.5).'
                ], 422);
            }
        }
        if ($durationSeconds !== null) {
            if ($durationSeconds < 0 || $durationSeconds > 600) {
                return response()->json(['message' => 'Durasi di luar batas wajar (0 - 10 menit).'], 422);
            }
            $match->duration = $durationSeconds;
        }

        // ========== 2) FINAL SCORE (median - total penalty) ==========
        $computeMedian = function (array $nums): float {
            $n = count($nums);
            if ($n === 0) return 0.0;
            sort($nums, SORT_NUMERIC);
            $mid = intdiv($n, 2);
            return ($n % 2 === 1) ? (float) $nums[$mid] : (float) (($nums[$mid - 1] + $nums[$mid]) / 2);
        };

        $category  = strtolower((string) $match->category);
        $baseScore = in_array($category, ['tunggal', 'regu']) ? 9.90 : 9.10;

        $juris = \App\Models\MatchPersonnelAssignment::where('tipe_pertandingan', 'seni')
            ->where('role', 'juri')
            ->where('arena_name', $match->arena_name)
            ->where('tournament_name', $match->tournament_name)
            ->orderBy('juri_number')
            ->get();

        $perJudgeTotals = [];
        foreach ($juris as $juri) {
            $jnum = (int) $juri->juri_number;

            $deduction = (float) \App\Models\LocalSeniScore::where('local_match_id', $match->id)
                ->where('judge_number', $jnum)
                ->sum('deduction');

            $final = \App\Models\LocalSeniFinalScore::where('local_match_id', $match->id)
                ->where('judge_number', $jnum)
                ->first();

            $component = \App\Models\LocalSeniComponentScore::where('local_match_id', $match->id)
                ->where('judge_number', $jnum)
                ->first();

            $kemantapan = (float) ($final->kemantapan ?? 0);
            $attack     = (float) ($component->attack_defense_technique ?? 0);
            $firmness   = (float) ($component->firmness_harmony        ?? 0);
            $soul       = (float) ($component->soulfulness             ?? 0);

            $componentTotal = $attack + $firmness + $soul;
            $total = (float) ($baseScore + $kemantapan + $componentTotal - $deduction);

            $perJudgeTotals[] = $total;
        }

        $totalPenalty       = (float) \App\Models\LocalSeniPenalties::where('local_match_id', $match->id)->sum('penalty_value');
        $medianScore        = $computeMedian($perJudgeTotals);
        $match->final_score = round($medianScore - $totalPenalty, 6);
        $match->status      = 'finished';
        $match->end_time    = now();

        \Log::debug('🎯 FINAL SCORE CALCULATION (median - penalty)', [
            'match_id'       => $match->id,
            'perJudgeTotals' => $perJudgeTotals,
            'median'         => $medianScore,
            'penalty'        => $totalPenalty,
            'final_score'    => $match->final_score,
            'duration'       => $match->duration ?? null,
        ]);

        $match->save();

        // ========== 3) Jadwalkan broadcast timer selesai (after commit) ==========
        $eventsToBroadcast[] = new \App\Events\SeniTimerFinished($match);

        // ========== 4) Battle Group: Pairing via Parent (robust) + Promotion ==========
        // NOTE: biarkan 0 valid → JANGAN pakai ?: null
        $battleGroup = $match->battle_group;
        \Log::info('🧭 battle_group?', ['group' => $battleGroup, 'match' => $match->id]);

        $shouldBroadcastGroupResult = false;
        $winnerCorner               = null; // 'blue'|'red'|null
        $winners                    = [];
        $promotedParentIds          = [];
        $resultUrl                  = null;

        if ($battleGroup !== null && $battleGroup !== '') {
            $groupMatches = \App\Models\LocalSeniMatch::where('battle_group', $battleGroup)
                ->where('tournament_name', $match->tournament_name)
                ->get();

            \Log::info('👀 Check group before broadcast', [
                'battle_group' => $battleGroup,
                'ids'          => $groupMatches->pluck('id')->all(),
                'statuses'     => $groupMatches->pluck('status','id')->all(),
                'corners'      => $groupMatches->mapWithKeys(fn($m)=>[$m->id => $m->corner])->all(),
            ]);

            // ---- Pairing via parent (akurasi utama)
            $blue = null;
            $red  = null;

            // 4.1 coba cari parent yang menunjuk current match → dapatkan sibling
            $parentTarget = \App\Models\LocalSeniMatch::query()
                ->where('tournament_name', $match->tournament_name)
                ->where(function ($q) use ($match) {
                    $q->where('parent_match_red_id',  $match->id)
                      ->orWhere('parent_match_blue_id', $match->id);
                })
                ->first();

            $sibling = null;
            if ($parentTarget) {
                $otherId = ($parentTarget->parent_match_red_id == $match->id)
                    ? $parentTarget->parent_match_blue_id
                    : $parentTarget->parent_match_red_id;

                $sibling = $otherId ? \App\Models\LocalSeniMatch::find($otherId) : null;
            }

            // 4.2 fallback: opposite corner, scope round yang sama, priority pasangan yang memang satu parent
            if (!$sibling) {
                $currentRound = (string) ($match->round_label ?? '');
                $pairScope = $currentRound !== ''
                    ? $groupMatches->filter(fn($m) => (string)($m->round_label ?? '') === $currentRound)
                    : $groupMatches;

                $cMatchId = $match->id;
                $cCorner  = strtolower((string) $match->corner);

                $sibling = $pairScope
                    ->filter(fn($m) => $m->id !== $cMatchId)
                    ->filter(fn($m) => strtolower((string) $m->corner) !== $cCorner) // corner kebalikan
                    ->sortBy(function ($m) use ($match, $cMatchId) {
                        // Skor sort: 0 kalau memang satu parent dengan current; selain itu jarak match_order
                        $sameParent = \App\Models\LocalSeniMatch::query()
                            ->where('tournament_name', $match->tournament_name)
                            ->where(function ($q) use ($match, $m, $cMatchId) {
                                $q->where(function ($q2) use ($match, $m, $cMatchId) {
                                    $q2->where('parent_match_red_id',  $cMatchId)
                                       ->where('parent_match_blue_id', $m->id);
                                })->orWhere(function ($q2) use ($match, $m, $cMatchId) {
                                    $q2->where('parent_match_red_id',  $m->id)
                                       ->where('parent_match_blue_id', $cMatchId);
                                });
                            })
                            ->exists();

                        return $sameParent ? 0 : abs((int)($m->match_order ?? 0) - (int)($match->match_order ?? 0));
                    })
                    ->first();
            }

            // 4.3 tetapkan blue/red berdasarkan corner aktual
            if ($sibling) {
                $mCorner = strtolower((string) $match->corner);
                $sCorner = strtolower((string) ($sibling->corner ?? ''));

                if ($mCorner === 'blue') { $blue = $match;   $red  = $sibling; }
                elseif ($mCorner === 'red') { $blue = $sibling; $red  = $match; }
                else {
                    if     ($sCorner === 'blue') { $blue = $sibling; $red = $match; }
                    elseif ($sCorner === 'red')  { $blue = $match;   $red = $sibling; }
                }
            }

            // Logging pairing
            \Log::info('🎯 sibling pairing', [
                'current_id'     => $match->id,
                'current_corner' => $match->corner,
                'sibling_id'     => $sibling?->id,
                'sibling_corner' => $sibling?->corner ?? null,
                'parent_target'  => $parentTarget?->id ?? null,
            ]);

            $pairDone = $blue && $red && $blue->status === 'finished' && $red->status === 'finished';
            \Log::info('✅ pair status (via parent-first)', [
                'round_label' => (string) ($match->round_label ?? ''),
                'blue_id'     => $blue?->id,  'blue_status' => $blue?->status,
                'red_id'      => $red?->id,   'red_status'  => $red?->status,
                'pairDone'    => $pairDone,
            ]);

            if ($pairDone) {
                // Optional: exclude DQ jika ada kolomnya
                $eligible = collect([$blue, $red]);
                if (\Schema::hasColumn($match->getTable(), 'disqualified')) {
                    $eligible = $eligible->filter(fn($m) => (int)($m->disqualified ?? 0) !== 1);
                }

                // helper: total penalty per match
                $sumPenalty = function($localMatchId) {
                    return (float) \App\Models\LocalSeniPenalties::where('local_match_id', $localMatchId)->sum('penalty_value');
                };

                // --- Tentukan pemenang (score → penalty → delta 180s)
                $blueScore = (float) ($blue->final_score ?? 0);
                $redScore  = (float) ($red->final_score  ?? 0);

                if     ($blueScore > $redScore) $winnerCorner = 'blue';
                elseif ($redScore  > $blueScore) $winnerCorner = 'red';
                else {
                    $bluePenalty = $sumPenalty($blue->id);
                    $redPenalty  = $sumPenalty($red->id);

                    if     ($bluePenalty < $redPenalty) $winnerCorner = 'blue';
                    elseif ($redPenalty  < $bluePenalty) $winnerCorner = 'red';
                    else {
                        $ideal = 180;
                        $bDel = abs((int)($blue->duration ?? 0) - $ideal);
                        $rDel = abs((int)($red->duration  ?? 0) - $ideal);
                        if     ($bDel < $rDel) $winnerCorner = 'blue';
                        elseif ($rDel < $bDel) $winnerCorner = 'red';
                        else                   $winnerCorner = null; // draw absolut
                    }
                }

                // Simpan winner_corner per match
                if ($winnerCorner === 'blue') {
                    $blue->winner_corner = 'blue'; $blue->save();
                    $red->winner_corner  = null;   $red->save();
                } elseif ($winnerCorner === 'red') {
                    $red->winner_corner  = 'red';  $red->save();
                    $blue->winner_corner = null;   $blue->save();
                } else {
                    $blue->winner_corner = null;   $blue->save();
                    $red->winner_corner  = null;   $red->save();
                }

                // --- Medal assignment
                $roundLabel = strtolower((string) ($match->round_label ?? ''));
                $isFinal  = (str_contains($roundLabel, 'final')
                            && !str_contains($roundLabel, 'semi')
                            && !str_contains($roundLabel, '3')
                            && !str_contains($roundLabel, 'bronze'));
                $isBronze = (str_contains($roundLabel, 'bronze') || str_contains($roundLabel, '3'));

                $setMedal = function (? \App\Models\LocalSeniMatch $m, ?string $medal) {
                    if (!$m) return;
                    $m->medal = $medal;
                    $m->save();
                };
                if ($isFinal) {
                    if     ($winnerCorner === 'blue') { $setMedal($blue, 'emas');  $setMedal($red,  'perak'); }
                    elseif ($winnerCorner === 'red')  { $setMedal($red,  'emas');  $setMedal($blue, 'perak'); }
                } elseif ($isBronze) {
                    if     ($winnerCorner === 'blue') { $setMedal($blue, 'perunggu'); $setMedal($red, null); }
                    elseif ($winnerCorner === 'red')  { $setMedal($red, 'perunggu');  $setMedal($blue, null); }
                }

                // --- Promote pemenang ke parent (isi slot sesuai parent_*_id)
                $winnerMatch = $winnerCorner === 'blue' ? $blue : ($winnerCorner === 'red' ? $red : null);
                if ($winnerMatch) {
                    $parents = \App\Models\LocalSeniMatch::query()
                        ->where(function ($q) use ($winnerMatch) {
                            $q->where('parent_match_red_id',  $winnerMatch->id)
                              ->orWhere('parent_match_blue_id', $winnerMatch->id);
                        })
                        ->where('tournament_name', $winnerMatch->tournament_name)
                        ->get();

                    if ($parents->isEmpty()) {
                        \Log::info('ℹ️ No parent match found for winner (end of bracket?)', [
                            'battle_group'    => $battleGroup,
                            'winner_match_id' => $winnerMatch->id,
                        ]);
                    } else {
                        foreach ($parents as $parent) {
                            $slot = ($parent->parent_match_red_id == $winnerMatch->id) ? 'red' : 'blue';

                            $stripInv = function (?string $s): ?string {
                                if ($s === null) return null;
                                return preg_replace('/[\x{200B}\x{200C}\x{200D}\x{2060}]/u', '', $s);
                            };

                            $parent->contingent_name = $stripInv($winnerMatch->contingent_name ?? $winnerMatch->contingent ?? null);
                            $parent->participant_1   = $stripInv($winnerMatch->participant_1 ?? null);
                            $parent->participant_2   = $stripInv($winnerMatch->participant_2 ?? null);
                            $parent->participant_3   = $stripInv($winnerMatch->participant_3 ?? null);
                            $parent->corner          = $slot;

                            // Reset nilai parent agar siap dimainkan
                            $parent->final_score = null;
                            $parent->duration    = null;
                            $parent->start_time  = null;
                            $parent->pause_time  = null;
                            $parent->end_time    = null;

                            $parent->save();
                            $promotedParentIds[] = $parent->id;
                        }
                    }
                }

                // --- winners payload (top-2 dari pasangan ini)
                $eligibleSorted = $eligible->sortByDesc(function ($m) {
                    return (float) ($m->final_score ?? 0);
                })->values();

                $winners = $eligibleSorted->take(2)->map(function ($m) {
                    $contingent = $m->contingent_name ?? ($m->contingent ?? '-');
                    $names = [];
                    foreach (['participant_1','participant_2','participant_3'] as $col) {
                        $v = is_string($m->{$col} ?? null) ? trim($m->{$col}) : null;
                        if (!empty($v)) $names[] = $v;
                    }
                    if (empty($names) && !empty($m->participant_name)) {
                        $parts = array_map('trim', preg_split('/,|\|/', $m->participant_name));
                        $names = array_values(array_filter($parts, fn($x)=>$x!==''));
                    }
                    $joined = implode(', ', $names);

                    $pen = (float) \App\Models\LocalSeniPenalties::where('local_match_id', $m->id)->sum('penalty_value');

                    return [
                        'match_id'      => $m->id,
                        'corner'        => strtolower((string)$m->corner ?? ''),
                        'contingent'    => $contingent ?: '-',
                        'participants'  => $names,
                        'display_name'  => trim(($contingent ?: '-') . ' — ' . ($joined ?: '-')),
                        'final_score'   => number_format((float)($m->final_score ?? 0), 6, '.', ''),
                        'penalty'       => $pen,
                        'duration'      => (int) ($m->duration ?? 0),
                        'medal'         => $m->medal ?? null,
                    ];
                })->all();

                $shouldBroadcastGroupResult = true;
                $resultUrl = url("/matches/seni/display-result-group/" . (string) $battleGroup);

                // Jadwalkan broadcast result setelah commit
                $eventsToBroadcast[] = new \App\Events\SeniBattleGroupCompleted(
                    $match->tournament_name,
                    $match->arena_name,
                    (string) $battleGroup,
                    $winners,
                    $resultUrl,
                    $winnerCorner
                );
            }
        }

        return [
            'match'                     => $match,
            'duration'                  => $match->duration ?? null,
            'battle_group'              => $battleGroup,
            'shouldBroadcastGroupResult'=> $shouldBroadcastGroupResult,
            'winner_corner'             => $winnerCorner,
            'promoted_parent_ids'       => $promotedParentIds,
            'winners'                   => $winners,
            'result_url'                => $resultUrl,
        ];
    });

    // ========== Broadcast AFTER COMMIT ==========
    \DB::afterCommit(function () use (&$eventsToBroadcast) {
        foreach ($eventsToBroadcast as $evt) {
            // NOTE: pakai ->toOthers() supaya tab pemicu tidak menerima echo sendiri
            broadcast($evt)->toOthers();
        }
    });

    // ========== Response ==========
    return response()->json([
        'message'                 => 'Pertandingan selesai.',
        'final_score'             => $result['match']->final_score,
        'duration'                => $result['duration'],
        'battle_group'            => $result['battle_group'],
        'battle_group_completed'  => $result['shouldBroadcastGroupResult'],
        'winner_corner'           => $result['winner_corner'],
        'promoted_parent_ids'     => $result['promoted_parent_ids'],
        'winners'                 => $result['winners'],
        'result_url'              => $result['result_url'],
    ]);
}



    

   public function finish0814($id, Request $request)
{
    $match = \App\Models\LocalSeniMatch::findOrFail($id);

    if ($match->status === 'finished') {
        return response()->json(['message' => 'Pertandingan sudah selesai.'], 400);
    }

    // ---- DURASI ----
    $durationSeconds = null;
    if ($request->filled('duration')) {
        $durationSeconds = (int) $request->input('duration'); // detik
    } elseif ($request->filled('performance_time_seconds')) {
        $durationSeconds = (int) $request->input('performance_time_seconds');
    } elseif ($request->filled('performance_time_input')) {
        $durationSeconds = $this->parseDurationToSeconds((string) $request->input('performance_time_input'));
        if ($durationSeconds === null) {
            return response()->json(['message' => 'Format durasi tidak valid. Gunakan mm:ss atau menit desimal (mis. 3.5).'], 422);
        }
    }
    if ($durationSeconds !== null) {
        if ($durationSeconds < 0 || $durationSeconds > 600) {
            return response()->json(['message' => 'Durasi di luar batas wajar (0 - 10 menit).'], 422);
        }
        $match->duration = $durationSeconds;
    }

    $match->status   = 'finished';
    $match->end_time = now();

    // ---- Helper median ----
    $computeMedian = function (array $nums): float {
        $n = count($nums);
        if ($n === 0) return 0.0;
        sort($nums, SORT_NUMERIC);
        $mid = intdiv($n, 2);
        return ($n % 2 === 1)
            ? (float) $nums[$mid]
            : (float) (($nums[$mid - 1] + $nums[$mid]) / 2);
    };

    // ---- Hitung final score (MEDIAN - PENALTY) ----
    $category  = strtolower((string) $match->category);
    $baseScore = in_array($category, ['tunggal', 'regu']) ? 9.90 : 9.10;

    $juris = \App\Models\MatchPersonnelAssignment::where('tipe_pertandingan', 'seni')
        ->where('role', 'juri')
        ->where('arena_name', $match->arena_name)
        ->where('tournament_name', $match->tournament_name)
        ->orderBy('juri_number')
        ->get();

    $perJudgeTotals = [];
    foreach ($juris as $juri) {
        $jnum = (int) $juri->juri_number;

        $deduction = (float) \App\Models\LocalSeniScore::where('local_match_id', $match->id)
            ->where('judge_number', $jnum)
            ->sum('deduction');

        $final = \App\Models\LocalSeniFinalScore::where('local_match_id', $match->id)
            ->where('judge_number', $jnum)
            ->first();

        $component = \App\Models\LocalSeniComponentScore::where('local_match_id', $match->id)
            ->where('judge_number', $jnum)
            ->first();

        $kemantapan = (float) ($final->kemantapan ?? 0);
        $attack     = (float) ($component->attack_defense_technique ?? 0);
        $firmness   = (float) ($component->firmness_harmony        ?? 0);
        $soul       = (float) ($component->soulfulness             ?? 0);

        $componentTotal = $attack + $firmness + $soul;
        $total = (float) ($baseScore + $kemantapan + $componentTotal - $deduction);

        $perJudgeTotals[] = $total;
    }

    $totalPenalty = (float) \App\Models\LocalSeniPenalties::where('local_match_id', $match->id)->sum('penalty_value');
    $medianScore  = $computeMedian($perJudgeTotals);
    $match->final_score = round($medianScore - $totalPenalty, 6);

    \Log::debug('🎯 FINAL SCORE CALCULATION (median - penalty)', [
        'match_id'       => $match->id,
        'perJudgeTotals' => $perJudgeTotals,
        'median'         => $medianScore,
        'penalty'        => $totalPenalty,
        'final_score'    => $match->final_score,
        'duration'       => $match->duration ?? null,
    ]);

    $match->save();

    // ---- Broadcast selesai timer ----
    broadcast(new \App\Events\SeniTimerFinished($match))->toOthers();

    // ---- CEK pasangan Blue/Red di battle group (per round) ----
    $battleGroup = $match->battle_group ?: null;

    \Log::info('🧭 battle_group?', ['group' => $battleGroup, 'match' => $match->id]);

    $shouldBroadcastGroupResult = false;
    $winners            = [];
    $resultUrl          = null;
    $winnerCorner       = null; // 'blue' | 'red' | null
    $promotedParentIds  = [];

    if ($battleGroup) {
        $groupMatches = \App\Models\LocalSeniMatch::where('battle_group', $battleGroup)
            ->where('tournament_name', $match->tournament_name)
            ->get();

        \Log::info('👀 Check group before broadcast', [
            'battle_group' => $battleGroup,
            'ids'          => $groupMatches->pluck('id')->all(),
            'statuses'     => $groupMatches->pluck('status','id')->all(),
            'corners'      => $groupMatches->mapWithKeys(fn($m)=>[$m->id => $m->corner])->all(),
        ]);

        // Fokus ke pasangan pada round yang sama
        $currentRound = (string) ($match->round_label ?? '');
        $pairScope = $currentRound !== ''
            ? $groupMatches->filter(fn($m) => (string)($m->round_label ?? '') === $currentRound)
            : $groupMatches;

        $blue = $pairScope->first(fn($m) => strtolower((string)$m->corner) === 'blue');
        $red  = $pairScope->first(fn($m) => strtolower((string)$m->corner) === 'red');

        $pairDone = $blue && $red && $blue->status === 'finished' && $red->status === 'finished';
        \Log::info('✅ pair status', [
            'round_label' => $currentRound,
            'blue_id'     => $blue?->id,  'blue_status' => $blue?->status,
            'red_id'      => $red?->id,   'red_status'  => $red?->status,
            'pairDone'    => $pairDone,
        ]);

        if ($pairDone) {
            // Exclude DQ jika ada kolomnya
            $eligible = collect([$blue, $red]);
            if (\Schema::hasColumn($match->getTable(), 'disqualified')) {
                $eligible = $eligible->filter(fn($m) => (int)($m->disqualified ?? 0) !== 1);
            }

            // helper penalty
            $sumPenalty = function($localMatchId) {
                return (float) \App\Models\LocalSeniPenalties::where('local_match_id', $localMatchId)->sum('penalty_value');
            };

            // --- Tentukan pemenang ---
            $blueScore = (float) ($blue->final_score ?? 0);
            $redScore  = (float) ($red->final_score  ?? 0);

            if ($blueScore > $redScore) {
                $winnerCorner = 'blue';
            } elseif ($redScore > $blueScore) {
                $winnerCorner = 'red';
            } else {
                $bluePenalty = $sumPenalty($blue->id);
                $redPenalty  = $sumPenalty($red->id);

                if ($bluePenalty < $redPenalty) {
                    $winnerCorner = 'blue';
                } elseif ($redPenalty < $bluePenalty) {
                    $winnerCorner = 'red';
                } else {
                    $idealSeconds = 180;
                    $blueDelta = abs((int)($blue->duration ?? 0) - $idealSeconds);
                    $redDelta  = abs((int)($red->duration  ?? 0) - $idealSeconds);

                    if ($blueDelta < $redDelta)       $winnerCorner = 'blue';
                    elseif ($redDelta < $blueDelta)   $winnerCorner = 'red';
                    else                               $winnerCorner = null; // draw absolut
                }
            }

            // Simpan winner_corner
            if ($winnerCorner === 'blue') {
                $blue->winner_corner = 'blue'; $blue->save();
                $red->winner_corner  = null;   $red->save();
            } elseif ($winnerCorner === 'red') {
                $red->winner_corner  = 'red';  $red->save();
                $blue->winner_corner = null;   $blue->save();
            } else {
                $blue->winner_corner = null;   $blue->save();
                $red->winner_corner  = null;   $red->save();
            }

            // --- 🏅 MEDAL ASSIGNMENT (berdasarkan round_label yg sama) ---
            $roundLabel = strtolower($currentRound);
            $setMedal = function (? \App\Models\LocalSeniMatch $m, ?string $medal) {
                if (!$m) return;
                $m->medal = $medal;
                $m->save();
            };

            $isFinal  = (str_contains($roundLabel, 'final') &&
                         !str_contains($roundLabel, 'semi') &&
                         !str_contains($roundLabel, '3') &&
                         !str_contains($roundLabel, 'bronze'));

            $isBronze = (str_contains($roundLabel, 'bronze') || str_contains($roundLabel, '3'));

            if ($isFinal) {
                if ($winnerCorner === 'blue') { $setMedal($blue, 'emas'); $setMedal($red, 'perak'); }
                elseif ($winnerCorner === 'red') { $setMedal($red, 'emas'); $setMedal($blue, 'perak'); }
            } elseif ($isBronze) {
                if ($winnerCorner === 'blue') { $setMedal($blue, 'perunggu'); $setMedal($red, null); }
                elseif ($winnerCorner === 'red') { $setMedal($red, 'perunggu'); $setMedal($blue, null); }
            }

            // --- PROMOTE pemenang ke parent ---
            $winnerMatch = $winnerCorner === 'blue' ? $blue : ($winnerCorner === 'red' ? $red : null);
            if ($winnerMatch) {
                $parents = \App\Models\LocalSeniMatch::query()
                    ->where(function ($q) use ($winnerMatch) {
                        $q->where('parent_match_red_id',  $winnerMatch->id)
                          ->orWhere('parent_match_blue_id', $winnerMatch->id);
                    })
                    ->where('tournament_name', $winnerMatch->tournament_name)
                    ->get();

                if ($parents->isEmpty()) {
                    \Log::info('ℹ️ No parent match found for winner (end of bracket?)', [
                        'battle_group'    => $battleGroup,
                        'winner_match_id' => $winnerMatch->id,
                    ]);
                } else {
                    foreach ($parents as $parent) {
                        $slot = ($parent->parent_match_red_id == $winnerMatch->id) ? 'red' : 'blue';

                        $stripInv = function (?string $s): ?string {
                            if ($s === null) return null;
                            return preg_replace('/[\x{200B}\x{200C}\x{200D}\x{2060}]/u', '', $s);
                        };

                        $parent->contingent_name = $stripInv($winnerMatch->contingent_name ?? $winnerMatch->contingent ?? null);
                        $parent->participant_1   = $stripInv($winnerMatch->participant_1 ?? null);
                        $parent->participant_2   = $stripInv($winnerMatch->participant_2 ?? null);
                        $parent->participant_3   = $stripInv($winnerMatch->participant_3 ?? null);
                        $parent->corner          = $slot;

                        // reset angka/waktu parent agar siap dimainkan
                        $parent->final_score = null;
                        $parent->duration    = null;
                        $parent->start_time  = null;
                        $parent->pause_time  = null;
                        $parent->end_time    = null;

                        $parent->save();
                        $promotedParentIds[] = $parent->id;
                    }
                }
            }

            // --- winners payload (top-2 dari pasangan ini) ---
            $eligibleSorted = $eligible->sortByDesc(function ($m) {
                return (float) ($m->final_score ?? 0);
            })->values();

            $winners = $eligibleSorted->take(2)->map(function ($m) {
                $contingent = $m->contingent_name ?? ($m->contingent ?? '-');
                $names = [];
                foreach (['participant_1','participant_2','participant_3'] as $col) {
                    $v = is_string($m->{$col} ?? null) ? trim($m->{$col}) : null;
                    if (!empty($v)) $names[] = $v;
                }
                if (empty($names) && !empty($m->participant_name)) {
                    $parts = array_map('trim', preg_split('/,|\|/', $m->participant_name));
                    $names = array_values(array_filter($parts, fn($x)=>$x!==''));
                }
                $joined = implode(', ', $names);

                $pen = (float) \App\Models\LocalSeniPenalties::where('local_match_id', $m->id)->sum('penalty_value');

                return [
                    'match_id'      => $m->id,
                    'corner'        => strtolower((string)$m->corner ?? ''),
                    'contingent'    => $contingent ?: '-',
                    'participants'  => $names,
                    'display_name'  => trim(($contingent ?: '-') . ' — ' . ($joined ?: '-')),
                    'final_score'   => number_format((float)($m->final_score ?? 0), 6, '.', ''),
                    'penalty'       => $pen,
                    'duration'      => (int) ($m->duration ?? 0),
                    'medal'         => $m->medal ?? null,
                ];
            })->all();

            $shouldBroadcastGroupResult = true;
            $resultUrl = url("/matches/seni/display-result-group/{$battleGroup}");

            broadcast(new \App\Events\SeniBattleGroupCompleted(
                $match->tournament_name,
                $match->arena_name,
                $battleGroup,
                $winners,
                $resultUrl,
                $winnerCorner
            ))->toOthers();
        }
    }

    return response()->json([
        'message'                 => 'Pertandingan selesai.',
        'final_score'             => $match->final_score,
        'duration'                => $match->duration ?? null,
        'battle_group'            => $battleGroup,
        'battle_group_completed'  => $shouldBroadcastGroupResult,
        'winner_corner'           => $winnerCorner,
        'promoted_parent_ids'     => $promotedParentIds,
        'winners'                 => $winners,
        'result_url'              => $resultUrl,
    ]);
}







    /**
     * Terima "3,5" / "3.5" (menit desimal) atau "mm:ss" → hasil detik.
     * Return null kalau format gak valid.
     */
    private function parseDurationToSeconds(?string $raw): ?int
    {
        if ($raw === null) return null;
        $s = trim($raw);
        if ($s === '') return null;

        // koma → titik
        $s = str_replace(',', '.', $s);

        // format mm:ss
        if (str_contains($s, ':')) {
            $parts = explode(':', $s);
            if (count($parts) !== 2) return null;
            $m = filter_var($parts[0], FILTER_VALIDATE_INT);
            $sec = filter_var($parts[1], FILTER_VALIDATE_INT);
            if ($m === false || $sec === false || $m < 0 || $sec < 0 || $sec >= 60) return null;
            return ($m * 60) + $sec;
        }

        // desimal menit
        if (!preg_match('/^\d+(\.\d+)?$/', $s)) return null;
        $minutes = (float) $s;
        if (!is_finite($minutes) || $minutes < 0) return null;

        return (int) round($minutes * 60);
    }


    public function changeToMatch($id)
    {
        $match = \App\Models\LocalSeniMatch::findOrFail($id);

        // Jika match yang diklik sudah berstatus "ongoing", tidak perlu pindah
        if ($match->status === 'ongoing') {
            return response()->json([
                'message' => 'Sudah di pertandingan ini.',
                'new_match_id' => $match->id,
            ]);
        }

        // Reset semua pertandingan lain di arena ini ke status not_started
       \App\Models\LocalSeniMatch::where('arena_name', $match->arena_name)
        ->where('id', '!=', $match->id)
        ->where('status', '!=', 'finished')
        ->update(['status' => 'not_started']);


        // Set pertandingan yang diklik menjadi ongoing
        $match->status = 'ongoing';
        $match->save();

        // Broadcast ke frontend
        broadcast(new \App\Events\SeniActiveMatchChanged($match->id))->toOthers();

        return response()->json([
            'message' => 'Berhasil pindah pertandingan.',
            'new_match_id' => $match->id
        ]);
    }

   public function changeToNextMatch($currentId)
{
    $currentMatch = \App\Models\LocalSeniMatch::findOrFail($currentId);

    // Helper: normalisasi flag DQ berbagai format
    $isDQ = function ($m): bool {
        if (!\Schema::hasColumn($m->getTable(), 'disqualified')) return false;
        $v = $m->disqualified ?? null;
        if ($v === null) return false;
        if (is_bool($v)) return $v;
        if (is_numeric($v)) return (int)$v === 1;
        $s = strtolower((string)$v);
        return in_array($s, ['1','yes','true','dq'], true);
    };

    // ===== Scope dasar: turnamen + arena yg sama =====
    $q = \App\Models\LocalSeniMatch::query()
        ->where('tournament_name', $currentMatch->tournament_name)
        ->where('arena_name', $currentMatch->arena_name);

    // 🔹 PERBEDAAN UTAMA:
    // Battle: JANGAN disaring pakai battle_group — urut global by match_order (1 1, 2 2, 3 3, ...)
    // Non-battle: kalau ada pool_name, batasi ke pool yang sama
    $isBattle = !empty($currentMatch->battle_group);
    if (!$isBattle && \Schema::hasColumn($currentMatch->getTable(), 'pool_name')) {
        $poolName = trim((string)($currentMatch->pool_name ?? ''));
        if ($poolName !== '') {
            $q->where('pool_name', $poolName);
        }
    }

    // Urutan stabil: match_order → (corner blue/red kalau ada) → id
    $q->orderByRaw('CAST(match_order AS UNSIGNED) ASC');

    if (\Schema::hasColumn($currentMatch->getTable(), 'corner')) {
        $q->orderByRaw(
            "CASE LOWER(COALESCE(corner,'')) " .
            "WHEN 'blue' THEN 0 WHEN 'merah' THEN 1 WHEN 'red' THEN 1 ELSE 2 END"
        );
    }

    $q->orderBy('id', 'ASC');

    $matches = $q->get();

    // Fallback: kalau scope kosong (mis. pool_name belum ke-set), pakai seluruh arena+turnamen
    if ($matches->isEmpty()) {
        $fallback = \App\Models\LocalSeniMatch::query()
            ->where('tournament_name', $currentMatch->tournament_name)
            ->where('arena_name', $currentMatch->arena_name)
            ->orderByRaw('CAST(match_order AS UNSIGNED) ASC');

        if (\Schema::hasColumn($currentMatch->getTable(), 'corner')) {
            $fallback->orderByRaw(
                "CASE LOWER(COALESCE(corner,'')) " .
                "WHEN 'blue' THEN 0 WHEN 'merah' THEN 1 WHEN 'red' THEN 1 ELSE 2 END"
            );
        }

        $matches = $fallback->orderBy('id', 'ASC')->get();
    }

    // Posisi current dalam list
    $index = $matches->search(fn($m) => (int)$m->id === (int)$currentMatch->id);

    // Cek kandidat valid
    $isCandidate = function ($m) use ($isDQ) {
        return ($m->status !== 'finished') && !$isDQ($m);
    };

    // Cari di bawah current
    $nextMatch = null;
    if ($index !== false) {
        $nextMatch = $matches->slice($index + 1)->first($isCandidate);
    }

    // Kalau belum ketemu → muter dari awal s/d current
    if (!$nextMatch) {
        $sliceEnd = ($index !== false) ? $index : 0;
        $nextMatch = $matches->slice(0, $sliceEnd)->first($isCandidate);
    }

    // Fallback terakhir: cari global (tanpa pool filter), tetap urut match_order
    if (!$nextMatch) {
        $fallbackQ = \App\Models\LocalSeniMatch::query()
            ->where('tournament_name', $currentMatch->tournament_name)
            ->where('arena_name', $currentMatch->arena_name)
            ->where('status', '!=', 'finished')
            ->orderByRaw('CAST(match_order AS UNSIGNED) ASC');

        if (\Schema::hasColumn($currentMatch->getTable(), 'corner')) {
            $fallbackQ->orderByRaw(
                "CASE LOWER(COALESCE(corner,'')) " .
                "WHEN 'blue' THEN 0 WHEN 'merah' THEN 1 WHEN 'red' THEN 1 ELSE 2 END"
            );
        }

        if (\Schema::hasColumn($currentMatch->getTable(), 'disqualified')) {
            $fallbackQ->where(function ($qq) {
                $qq->whereNull('disqualified')
                   ->orWhereIn('disqualified', [0, '0', 'no', 'false']);
            });
        }

        $nextMatch = $fallbackQ->orderBy('id', 'ASC')->first();
    }

    // === Ketemu kandidat ===
    if ($nextMatch) {
        // Promote status kalau masih idle
        if (in_array($nextMatch->status, ['pending','not_started','paused','stopped','awaiting_time'], true)) {
            $nextMatch->status = 'ongoing';
            $nextMatch->save();
        }

        // Broadcast perubahan active match
        broadcast(new \App\Events\SeniActiveMatchChanged(
            $nextMatch->id,
            $currentMatch->arena_name
        ))->toOthers();

        return response()->json([
            'message'      => 'Match switched',
            'new_match_id' => $nextMatch->id,
            'no_next'      => false,
        ]);
    }

    // === Tidak ada kandidat sama sekali ===
    \Log::info('ℹ️ changeToNextMatch: no candidate', [
        'current_id' => $currentMatch->id,
        'arena'      => $currentMatch->arena_name,
        'tournament' => $currentMatch->tournament_name,
        'scoped'     => $matches->pluck('id'),
    ]);

    return response()->json([
        'message'      => 'Tidak ada match berikutnya di arena ini.',
        'new_match_id' => null,
        'no_next'      => true, // biar FE bisa diam tanpa popup error
    ], 200);
}



    public function changeToNextMatch_sebelum_lss($currentId)
    {
        $currentMatch = \App\Models\LocalSeniMatch::findOrFail($currentId);

        // Pastikan kita ambil hanya pertandingan dari arena dan turnamen yang sama
        $matches = \App\Models\LocalSeniMatch::where('arena_name', $currentMatch->arena_name)
            ->where('tournament_name', $currentMatch->tournament_name)
            ->orderByRaw('CAST(match_order AS UNSIGNED) ASC')
            ->get();

        // Cari index match sekarang
        $index = $matches->search(fn($m) => $m->id === $currentMatch->id);

        if ($index === false) {
            \Log::warning('⚠️ Match sekarang tidak ditemukan dalam daftar hasil query.', [
                'current_id' => $currentMatch->id,
            ]);
            return response()->json(['message' => 'Match sekarang tidak ditemukan.'], 404);
        }

        // Cari match berikutnya setelah current
        $nextMatch = $matches->slice($index + 1)->first(fn($m) =>
            $m->status !== 'finished' && $m->disqualified !== 'yes'
        );

        // Kalau tidak ada di bawahnya, cari dari awal sampai current
        if (!$nextMatch) {
            $nextMatch = $matches->slice(0, $index)->first(fn($m) =>
                $m->status !== 'finished' && $m->disqualified !== 'yes'
            );
        }

        // Kalau ketemu, update status dan broadcast
        if ($nextMatch) {
            $nextMatch->status = 'ongoing';
            $nextMatch->save();

            //broadcast(new \App\Events\SeniActiveMatchChanged($nextMatch->id))->toOthers();
            broadcast(new \App\Events\SeniActiveMatchChanged($nextMatch->id, $currentMatch->arena_name))->toOthers();


            return response()->json([
                'message' => 'Match switched',
                'new_match_id' => $nextMatch->id
            ]);
        }

        return response()->json([
            'message' => 'No next match available'
        ], 404);
    }

    public function skipPerformance($id, Request $request)
    {
        $match = \App\Models\LocalSeniMatch::findOrFail($id);

        if ($match->status === 'finished') {
            return response()->json(['message' => 'Pertandingan sudah selesai.'], 400);
        }

        // 1) Tandai match sekarang sebagai skip/selesai (0 skor, 0 durasi)
        $match->status         = 'finished';
        $match->end_time       = now();
        $match->duration       = 0;
        $match->final_score    = 0.0;
        $match->winner_corner  = null; // skip = tidak ada pemenang

        if ($request->filled('reason')) {
            $match->disqualification_reason = (string) $request->input('reason'); // opsional catatan
        }

        $match->save();

        // Broadcast selesai agar display arena stop timer (opsional)
        broadcast(new \App\Events\SeniTimerFinished($match))->toOthers();

        // 2) Cari NEXT MATCH — LOGIKA SAMA DENGAN changeToNextMatch()
        $matches = \App\Models\LocalSeniMatch::where('arena_name', $match->arena_name)
            ->where('tournament_name', $match->tournament_name)
            ->orderByRaw('CAST(match_order AS UNSIGNED) ASC')
            ->get();

        $index = $matches->search(fn($m) => $m->id === $match->id);

        $nextMatch = null;
        if ($index !== false) {
            // Cari di bawah current
            $nextMatch = $matches->slice($index + 1)->first(function ($m) {
                // status belum selesai dan tidak didiskualifikasi
                return $m->status !== 'finished' && ($m->disqualified ?? null) !== 'yes';
            });
            // Kalau nggak ada, wrap ke atas
            if (!$nextMatch) {
                $nextMatch = $matches->slice(0, $index)->first(function ($m) {
                    return $m->status !== 'finished' && ($m->disqualified ?? null) !== 'yes';
                });
            }
        }

        $nextMatchId = $nextMatch?->id;

        if ($nextMatch) {
            // Pastikan next berstatus ongoing (kalau belum)
            if ($nextMatch->status !== 'ongoing') {
                $nextMatch->status = 'ongoing';
                $nextMatch->save();
            }

            // Penting: broadcast agar DISPLAY ARENA pindah
            broadcast(new \App\Events\SeniActiveMatchChanged($nextMatch->id, $match->arena_name))->toOthers();
        }

        // 3) Cek battle group selesai -> broadcast result
        $battleGroup = $match->battle_group ?: null;
        $shouldBroadcastGroupResult = false;
        $resultUrl = null;

        if ($battleGroup) {
            $groupMatches = \App\Models\LocalSeniMatch::where('battle_group', $battleGroup)
                ->where('tournament_name', $match->tournament_name)
                ->get();

            $allFinished = $groupMatches->every(fn($m) => $m->status === 'finished');

            if ($allFinished) {
                $shouldBroadcastGroupResult = true;

                // winners untuk tampilan (top-2 final_score, exclude DQ jika ada kolomnya)
                $eligible = $groupMatches;
                if (\Schema::hasColumn($match->getTable(), 'disqualified')) {
                    $eligible = $eligible->filter(fn($m) => (int)($m->disqualified ?? 0) !== 1);
                }

                $eligibleSorted = $eligible->sortByDesc(function ($m) {
                    return (float) ($m->final_score ?? 0);
                })->values();

                $winners = $eligibleSorted->take(2)->map(function ($m) {
                    $contingent = $m->contingent_name ?? ($m->contingent ?? '-');
                    $names = [];
                    foreach (['participant_1','participant_2','participant_3'] as $col) {
                        $v = is_string($m->{$col} ?? null) ? trim($m->{$col}) : null;
                        if (!empty($v)) $names[] = $v;
                    }
                    if (empty($names) && !empty($m->participant_name)) {
                        $parts = array_map('trim', preg_split('/,|\|/', $m->participant_name));
                        $names = array_values(array_filter($parts, fn($x)=>$x!==''));
                    }
                    $joined = implode(', ', $names);
                    $pen = \App\Models\LocalSeniPenalties::where('local_match_id', $m->id)->sum('penalty_value');

                    return [
                        'match_id'      => $m->id,
                        'corner'        => strtolower((string)$m->corner ?? ''),
                        'contingent'    => $contingent ?: '-',
                        'participants'  => $names,
                        'display_name'  => trim(($contingent ?: '-') . ' — ' . ($joined ?: '-')),
                        'final_score'   => number_format((float)($m->final_score ?? 0), 6, '.', ''),
                        'penalty'       => (float) $pen,
                        'duration'      => (int) ($m->duration ?? 0),
                        'medal'         => $m->medal ?? null,
                    ];
                })->all();

                $resultUrl = url("/matches/seni/display-result-group/{$battleGroup}");

                event(new \App\Events\SeniBattleGroupCompleted(
                    $match->tournament_name,
                    $match->arena_name,
                    $battleGroup,
                    $winners,
                    $resultUrl,
                    null // winnerCorner (tidak ditentukan saat skip)
                ));
            }
        }

        return response()->json([
            'message'                => 'Match di-skip (no-show).',
            'match_id'               => $match->id,
            'next_match_id'          => $nextMatchId,                // FE/operator bisa redirect
            'battle_group'           => $battleGroup,
            'battle_group_completed' => $shouldBroadcastGroupResult, // display arena akan buka result via broadcast
            'result_url'             => $resultUrl,
        ]);
    }





   public function changeToNextMatch_($currentId)
    {
        $currentMatch = \App\Models\LocalSeniMatch::findOrFail($currentId);

        // ✅ Ambil semua match di arena yang sama dan URUT berdasarkan match_order dari DB
        $matches = \App\Models\LocalSeniMatch::where('arena_name', $currentMatch->arena_name)
            ->orderByRaw('CAST(match_order AS UNSIGNED) ASC')
            ->get();

        // Debug log
        

        // Cari index match sekarang
        $index = $matches->search(fn($m) => $m->id === $currentMatch->id);

        if ($index === false) {
            \Log::warning('⚠️ Match sekarang tidak ditemukan dalam daftar hasil query.', [
                'current_id' => $currentMatch->id,
            ]);
            return response()->json(['message' => 'Match sekarang tidak ditemukan.'], 404);
        }

        // Cari match berikutnya (setelah current)
        $nextMatch = $matches->slice($index + 1)->first(fn($m) =>
            $m->status !== 'finished' && $m->disqualified !== 'yes'
        );

        // Kalau tidak ada di bawahnya, cari dari awal sampai current
        if (!$nextMatch) {
            $nextMatch = $matches->slice(0, $index)->first(fn($m) =>
                $m->status !== 'finished' && $m->disqualified !== 'yes'
            );
        }

        // Kalau ketemu, update status dan broadcast
        if ($nextMatch) {
            $nextMatch->status = 'ongoing';
            $nextMatch->save();

            broadcast(new \App\Events\SeniActiveMatchChanged($nextMatch->id))->toOthers();

            return response()->json([
                'message' => 'Match switched',
                'new_match_id' => $nextMatch->id
            ]);
        }

        // Kalau semua match udah selesai
        return response()->json([
            'message' => 'No next match available'
        ], 404);
    }




    public function changeToNextMatch_asli($currentId)
    {
        $currentMatch = \App\Models\LocalSeniMatch::findOrFail($currentId);

        // Ambil semua match di arena yang sama
        $matches = \App\Models\LocalSeniMatch::where('arena_name', $currentMatch->arena_name)
            // ->where('match_type', $currentMatch->match_type) // optional filter
            ->orderBy('category')
            ->orderBy('gender')
            ->orderBy('pool_name')
            ->orderBy('match_order')
            ->get();

        // Debug log
        \Log::debug('🔍 Total match ditemukan:', ['total' => $matches->count()]);
        \Log::debug('🔍 Semua ID match:', ['ids' => $matches->pluck('id')->toArray()]);
        \Log::debug('🔍 Current ID:', ['id' => $currentMatch->id]);

        // Cari index match sekarang
        $index = $matches->search(fn($m) => $m->id === $currentMatch->id);

        if ($index === false) {
            \Log::warning('⚠️ Match sekarang tidak ditemukan dalam daftar hasil query.', [
                'current_id' => $currentMatch->id,
            ]);
            return response()->json(['message' => 'Match sekarang tidak ditemukan.'], 404);
        }

        // Cari match berikutnya (setelah current)
        $nextMatch = $matches->slice($index + 1)->first(fn($m) =>
            $m->status !== 'finished' && $m->disqualified !== 'yes'
        );

        // Kalau tidak ada di bawahnya, cari dari awal sampai current
        if (!$nextMatch) {
            $nextMatch = $matches->slice(0, $index)->first(fn($m) =>
                $m->status !== 'finished' && $m->disqualified !== 'yes'
            );
        }

        // Kalau ketemu, update status dan broadcast
        if ($nextMatch) {
            $nextMatch->status = 'ongoing';
            $nextMatch->save();

            broadcast(new \App\Events\SeniActiveMatchChanged($nextMatch->id))->toOthers();

            return response()->json([
                'message' => 'Match switched',
                'new_match_id' => $nextMatch->id
            ]);
        }

        // Kalau semua match udah selesai
        return response()->json([
            'message' => 'No next match available'
        ], 404);
    }





    


    // app/Http/Controllers/Api/SeniMatchController.php
    public function getJuriCount(Request $request)
    {
        $request->validate([
            'tournament' => 'required|string',
            'arena' => 'required|string',
        ]);

        $count = MatchPersonnelAssignment::where('tournament_name', $request->tournament)
            ->where('arena_name', $request->arena)
            ->where('tipe_pertandingan', 'seni')
            ->where('role', 'juri')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function getJudgeScores__(Request $request)
    {
        $request->validate([
            'match_id' => 'required|integer',
        ]);

        $matchId = $request->match_id;

        $juris = MatchPersonnelAssignment::where('tipe_pertandingan', 'seni')
            ->where('role', 'juri')
            ->where('arena_name', $request->arena)
            ->where('tournament_name', $request->tournament)
            ->get();

        $results = [];

        foreach ($juris as $juri) {
            $deduction = LocalSeniScore::where('local_match_id', $matchId)
                ->where('judge_number', $juri->juri_number)
                ->sum('deduction');

            $score = 9.90 - $deduction;

            $results[] = [
                'juri_number' => $juri->juri_number,
                'score' => round($score, 2),
                'deduction' => round($deduction, 2)
            ];
        }

        $penalty = LocalSeniPenalties::where('local_match_id', $matchId)->sum('penalty_value');

        $penalties = LocalSeniPenalties::where('local_match_id', $matchId)
            ->select('reason', 'penalty_value')
            ->get();

        // Ambil match
        $match = \App\Models\LocalSeniMatch::find($matchId);

        return response()->json([
            'judges' => $results,
            'penalty' => round($penalty, 2),
            'penalties' => $penalties,
            'start_time' => optional($match->start_time)->toDateTimeString(),
            'end_time' => optional($match->end_time)->toDateTimeString(),
        ]);
    }

    public function getJudgeScores(Request $request)
    {
        $request->validate([
            'match_id' => 'required|integer',
        ]);

        $matchId = $request->match_id;

        $juris = MatchPersonnelAssignment::where('tipe_pertandingan', 'seni')
            ->where('role', 'juri')
            ->where('arena_name', $request->arena)
            ->where('tournament_name', $request->tournament)
            ->get();

        $results = [];
        $match = LocalSeniMatch::find($matchId);
        $category = strtolower($match->category);
        $baseScore = in_array($category, ['tunggal', 'regu']) ? 9.90 : 9.10;

        foreach ($juris as $juri) {
            $deduction = LocalSeniScore::where('local_match_id', $matchId)
                ->where('judge_number', $juri->juri_number)
                ->sum('deduction');

            $final = LocalSeniFinalScore::where('local_match_id', $matchId)
                ->where('judge_number', $juri->juri_number)
                ->first();

            $component = LocalSeniComponentScore::where('local_match_id', $matchId)
                ->where('judge_number', $juri->juri_number)
                ->first();

            $additional = $final?->kemantapan ?? 0;

            $componentTotal = 0;
            if ($component) {
                $componentTotal += $component->attack_defense_technique ?? 0;
                $componentTotal += $component->firmness_harmony ?? 0;
                $componentTotal += $component->soulfulness ?? 0;
            }

            $truthScore = $baseScore + $componentTotal - $deduction;
            $totalScore = $truthScore + $additional;

            $results[] = [
                'juri_number' => $juri->juri_number,
                'truth_score' => round($truthScore, 2),
                'additional_score' => round($additional, 2),
                'score' => round($totalScore, 2),
                'deduction' => round($deduction, 2),
            ];
        }

        $penalty = LocalSeniPenalties::where('local_match_id', $matchId)->sum('penalty_value');

        $penalties = LocalSeniPenalties::where('local_match_id', $matchId)
            ->select('reason', 'penalty_value')
            ->get();

        return response()->json([
            'judges' => $results,
            'penalty' => round($penalty, 2),
            'penalties' => $penalties,
            'start_time' => optional($match->start_time)->toDateTimeString(),
            'end_time' => optional($match->end_time)->toDateTimeString(),
        ]);
    }






}

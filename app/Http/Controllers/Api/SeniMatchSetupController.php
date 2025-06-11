<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Events\SeniMatchStarted;
use App\Events\SeniTimerStarted;
use App\Events\SeniTimerUpdated;
use App\Events\SeniTimerFinished;
use App\Events\SeniActiveMatchChanged;
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

        if ($match) {
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
        }

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

        if ($match) {
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
        }

        // Broadcast timer update biar semua UI reset
        broadcast(new \App\Events\SeniTimerUpdated($match))->toOthers();

        return response()->json([
            'message' => 'Pertandingan seni direset.'
        ]);
    }

    

    public function finish($id)
    {
        $match = \App\Models\LocalSeniMatch::findOrFail($id);

        if ($match->status === 'finished') {
            return response()->json(['message' => 'Pertandingan sudah selesai.'], 400);
        }

        $match->status = 'finished';
        $match->end_time = now();

        // Tentukan base score berdasarkan kategori
        $category = strtolower($match->category);
        $baseScore = in_array($category, ['tunggal', 'regu']) ? 9.90 : 9.10;

        // Ambil semua juri yang bertugas di pertandingan ini
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

        // Ambil penalty SELALU, tidak tergantung skor juri
        $totalPenalty = \App\Models\LocalSeniPenalties::where('local_match_id', $match->id)->sum('penalty_value');

        \Log::debug('🧾 Penalty debug', [
            'match_id' => $match->id,
            'penalty_total' => $totalPenalty,
            'raw_penalty_rows' => \App\Models\LocalSeniPenalties::where('local_match_id', $match->id)->get()
        ]);

        if (count($finalScores) > 0) {
            $rawAverage = collect($finalScores)->avg();
            $match->final_score = round($rawAverage - $totalPenalty, 6);
        } else {
            // Tidak ada skor dari juri, penalti tetap dihitung
            $match->final_score = round(0 - $totalPenalty, 6);
        }

        // Log untuk debugging
        \Log::debug('🎯 FINAL SCORE CALCULATION', [
            'match_id' => $match->id,
            'finalScores' => $finalScores,
            'rawAverage' => $rawAverage ?? null,
            'penalty' => $totalPenalty,
            'final_score' => $match->final_score,
        ]);

        $match->save();

        try {
            $client = new \GuzzleHttp\Client();

            $response = $client->post($this->live_server . '/api/update-seni-match-status', [
                'json' => [
                    'remote_match_id' => $match->remote_match_id,
                    'status' => 'finished',
                    'final_score' => $match->final_score,
                ],
                'timeout' => 5,
            ]);

            \Log::info('✅ Final score dikirim ke server pusat', [
                'remote_match_id' => $match->remote_match_id,
                'final_score' => $match->final_score,
                'http_code' => $response->getStatusCode()
            ]);
        } catch (\Throwable $e) {
            \Log::warning('⚠️ Gagal kirim final score ke server pusat', [
                'remote_match_id' => $match->remote_match_id,
                'error' => $e->getMessage()
            ]);
        }

        // Broadcast event selesai
        broadcast(new \App\Events\SeniTimerFinished($match))->toOthers();

        return response()->json([
            'message' => 'Pertandingan selesai.',
            'final_score' => $match->final_score,
        ]);
    }

   public function changeToNextMatch($currentId)
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

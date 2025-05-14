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
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class SeniMatchSetupController extends Controller
{
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

        // Broadcast timer update biar semua UI reset
        broadcast(new \App\Events\SeniTimerUpdated($match))->toOthers();

        return response()->json([
            'message' => 'Pertandingan seni direset.'
        ]);
    }

   public function finish_($id)
    {
        $match = \App\Models\LocalSeniMatch::findOrFail($id);

        if ($match->status === 'finished') {
            return response()->json(['message' => 'Pertandingan sudah selesai.'], 400);
        }

        $match->status = 'finished';
        $match->end_time = now();

        // ✅ Hitung final score dari total deduction juri
        $startingScore = 9.75;

        // Ambil semua skor juri untuk match ini
        $deductions = \App\Models\LocalSeniScore::where('local_match_id', $match->id)->get();

        // Group by juri_number, hitung masing-masing total deduction
        $finalScores = $deductions
            ->groupBy('judge_number')
            ->map(function ($items) use ($startingScore) {
                $totalDeduction = $items->sum('deduction');
                return round($startingScore - $totalDeduction, 2);
            });

        // ✅ Hitung rata-rata nilai akhir dari semua juri
        if ($finalScores->count() > 0) {
            $averageFinalScore = round($finalScores->avg(), 2);
            $match->final_score = $averageFinalScore;
        }

        $match->save();

        // ✅ Kirim event selesai
        broadcast(new \App\Events\SeniTimerFinished($match))->toOthers();

        return response()->json([
            'message' => 'Pertandingan selesai.',
            'final_score' => $match->final_score,
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

        $startingScore = 9.75;

        // Ambil semua skor juri untuk match ini
        $deductions = \App\Models\LocalSeniScore::where('local_match_id', $match->id)->get();

        // Hitung final score per juri: 9.75 - total_deduction (tanpa round)
        $finalScores = $deductions
            ->groupBy('judge_number')
            ->map(function ($items) use ($startingScore) {
                $totalDeduction = $items->sum('deduction');
                return $startingScore - $totalDeduction;
            })
            ->values(); // buang key supaya .avg() konsisten

        if ($finalScores->count() > 0) {
            // Ambil total penalty umum
            $totalPenalty = \App\Models\LocalSeniPenalties::where('local_match_id', $match->id)->sum('penalty_value');

            // Hitung rata-rata nilai juri lalu kurangi penalty
            $rawAverage = $finalScores->avg();
            $match->final_score = round($rawAverage - $totalPenalty, 2);
        }

        $match->save();

        // Broadcast event selesai
        broadcast(new \App\Events\SeniTimerFinished($match))->toOthers();

        return response()->json([
            'message' => 'Pertandingan selesai.',
            'final_score' => $match->final_score,
        ]);
    }




    public function changeToNextMatch($currentId)
    {

        // Ambil current match
        $currentMatch = LocalSeniMatch::findOrFail($currentId);

        // Cari pertandingan selanjutnya di arena dan turnamen yang sama, belum selesai
        $nextMatch = LocalSeniMatch::where('id', '>', $currentMatch->id)
            ->where('arena_name', $currentMatch->arena_name)
            ->where('tournament_name', $currentMatch->tournament_name)
            ->where('status', '!=', 'finished')
            ->where(function ($q) {
                $q->whereNull('disqualified')->orWhere('disqualified', '!=', 'yes');
            })
            ->orderBy('id')
            ->first();

        if ($nextMatch) {
            $nextMatch->status = 'ongoing';
            $nextMatch->save();

            broadcast(new \App\Events\SeniActiveMatchChanged($nextMatch->id))->toOthers();

            return response()->json([
                'message' => 'Match switched',
                'new_match_id' => $nextMatch->id
            ]);
        }

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




}

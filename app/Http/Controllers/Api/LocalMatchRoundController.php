<?php

namespace App\Http\Controllers\Api;

use App\Models\LocalMatchRound;
use App\Models\LocalMatch;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Events\LocalMatchTimerUpdated;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use App\Events\TimerUpdated; // kita bikin event baru nanti
use App\Events\ActiveMatchChanged;


class LocalMatchRoundController extends Controller
{
    private $live_server;

    public function __construct()
    {
        $this->live_server = config('app_settings.data_source');
    }
    public function show($id)
    {
        $round = LocalMatchRound::findOrFail($id);
        $duration = 180;

        $start = $round->start_time instanceof Carbon
            ? $round->start_time
            : Carbon::parse($round->start_time);

        $elapsed = 0;
        if ($round->status === 'in_progress' || $round->status === 'paused') {
            $end = $round->end_time ?? now();
            $end = $end instanceof Carbon ? $end : Carbon::parse($end);
            $elapsed = $start ? $start->diffInRealSeconds($end) : 0;
        }

        $remaining = max(0, intval(round($duration - $elapsed)));

        // ✅ Kirim event jika sedang berjalan
        if ($round->status === 'in_progress') {
            broadcast(new LocalMatchTimerUpdated(
                $round->local_match_id,
                $round->id,
                $remaining
            ));
        }

        return response()->json([
            'id' => $round->id,
            'local_match_id' => $round->local_match_id,
            'round_number' => $round->round_number,
            'status' => $round->status,
            'start_time' => $round->start_time,
            'end_time' => $round->end_time,
            'duration' => $duration,
            'elapsed' => $elapsed,
            'remaining' => $remaining,
            'now' => now(),
        ]);
    }

    public function start($id, Request $request)
    {
        $request->validate([
            'duration' => 'required|integer|min:60|max:600'
        ]);

        $round = LocalMatchRound::findOrFail($id);
        $match = LocalMatch::findOrFail($round->local_match_id);

        if ($round->status !== 'not_started' && $round->status !== 'paused') {
            return response()->json(['message' => 'Ronde sudah berjalan atau selesai.'], 400);
        }

        // ✅ Ambil durasi dari request (default 180 detik jika tidak dikirim)
        $duration = intval($request->input('duration', 180));

        $round->start_time = now();
        $round->status = 'in_progress';
        $round->save();

        $match->round_duration = $duration;
        $match->status = 'in_progress'; // ✅ update status lokal
        $match->save();

        // Set match aktif (nonaktifkan yang lain)
        \App\Models\LocalMatch::where('is_active', true)->update(['is_active' => false]);

        $match = \App\Models\LocalMatch::find($round->local_match_id);
        if ($match) {
            $match->is_active = true;
            $match->save();

            // ✅ Kirim status ke server pusat
            /*try {
                $client = new \GuzzleHttp\Client();

                $response = $client->post($this->live_server . '/api/update-tanding-match-status', [
                    'json' => [
                        'remote_match_id' => $match->remote_match_id,
                        'status' => 'in_progress',
                    ],
                    'timeout' => 5,
                ]);

                \Log::info('✅ Status pertandingan dikirim ke server pusat', [
                    'remote_match_id' => $match->remote_match_id,
                    'status' => 'in_progress',
                    'http_code' => $response->getStatusCode()
                ]);
            } catch (\Throwable $e) {
                \Log::warning('⚠️ Gagal kirim status ke server pusat', [
                    'remote_match_id' => $match->remote_match_id,
                    'error' => $e->getMessage()
                ]);
            }*/
        }

       

        broadcast(new \App\Events\TimerStarted($match->id, [
            'start_time' => $round->start_time->toIso8601String(),
            'duration' => $duration,
            'round_id' => $round->id,
            'round_number' => $round->round_number,
        ]));

        return response()->json([
            'message' => 'Ronde dimulai.',
            'start_time' => $round->start_time,
            'duration' => $duration
        ]);
    }

    public function changeToMatch($matchId)
    {
        // Matikan semua match aktif
        LocalMatch::where('is_active', true)->update(['is_active' => false]);

        // Ambil match berdasarkan ID yang dipilih
        $match = LocalMatch::find($matchId);
        if (!$match) {
            return response()->json(['message' => 'Match not found'], 404);
        }

        // Aktifkan match tersebut
        $match->is_active = true;
        $match->save();

        // Broadcast ke semua listener
        broadcast(new ActiveMatchChanged($match->id))->toOthers();

        return response()->json([
            'message' => 'Match switched',
            'new_match_id' => $match->id
        ]);
    }

    public function changeToNextMatch($currentId)
    {
        // Matikan semua match aktif
        LocalMatch::where('is_active', true)->update(['is_active' => false]);

        // Ambil match sekarang
        $currentMatch = LocalMatch::find($currentId);
        if (!$currentMatch) {
            return response()->json(['message' => 'Current match not found'], 404);
        }

        $arena = $currentMatch->arena_name;
        $tournament = $currentMatch->tournament_name;
        $currentNumber = $currentMatch->match_number;

        // Cari match berikutnya di arena dan turnamen yang sama
        $nextMatch = LocalMatch::where('match_number', '>', $currentNumber)
            ->where('arena_name', $arena)
            ->where('tournament_name', $tournament)
            ->orderBy('match_number', 'asc')
            ->first();

        if ($nextMatch) {
            $nextMatch->is_active = true;
            $nextMatch->save();

            broadcast(new ActiveMatchChanged($nextMatch->id))->toOthers();

            return response()->json([
                'message' => 'Match switched',
                'new_match_id' => $nextMatch->id
            ]);
        }

        return response()->json([
            'message' => 'No next match available in the same arena'
        ], 404);
    }



    public function changeToNextMatch_($currentId)
    {
        // Matikan semua match aktif
        LocalMatch::where('is_active', true)->update(['is_active' => false]);

        // Ambil match sekarang
        $currentMatch = LocalMatch::find($currentId);
        if (!$currentMatch) {
            return response()->json(['message' => 'Current match not found'], 404);
        }

        $arena = $currentMatch->arena_name;
        $currentNumber = $currentMatch->match_number;

        // Cari match dengan match_number lebih besar dan arena yang sama
        $nextMatch = LocalMatch::where('match_number', '>', $currentNumber)
            ->where('arena_name', $arena)
            ->orderBy('match_number', 'asc')
            ->first();

        if ($nextMatch) {
            $nextMatch->is_active = true;
            $nextMatch->save();

            broadcast(new ActiveMatchChanged($nextMatch->id))->toOthers();

            return response()->json([
                'message' => 'Match switched',
                'new_match_id' => $nextMatch->id
            ]);
        }

        return response()->json([
            'message' => 'No next match available in the same arena'
        ], 404);
    }



    





    

    public function pause($id)
    {
        $round = LocalMatchRound::findOrFail($id);

        if ($round->status !== 'in_progress') {
            return response()->json(['message' => 'Ronde tidak sedang berjalan.'], 400);
        }

        $round->end_time = now();
        $round->status = 'paused';
        $round->save();

        broadcast(new \App\Events\TimerUpdated($round))->toOthers(); // ⬅️ broadcast Reverb
        return response()->json(['message' => 'Ronde dipause.', 'end_time' => $round->end_time]);
    }

    

    public function resume($id)
    {
         
        $round = LocalMatchRound::findOrFail($id);

        if ($round->status !== 'paused') {
            return response()->json(['message' => 'Ronde tidak dalam keadaan pause.'], 400);
        }

        $start = $round->start_time instanceof Carbon ? $round->start_time : Carbon::parse($round->start_time);
        $end = $round->end_time instanceof Carbon ? $round->end_time : Carbon::parse($round->end_time);
        $elapsed = $start->diffInSeconds($end);

        $round->start_time = now()->subSeconds($elapsed);
        $round->end_time = null;
        $round->status = 'in_progress';
        $round->save();

        // Ambil durasi dari request atau fallback ke 180
        $duration = request()->input('duration', 180);

        broadcast(new \App\Events\TimerUpdated($round, $duration))->toOthers();

        return response()->json([
            'message' => 'Ronde dilanjutkan',
            'start_time' => $round->start_time,
            'now' => now(),
            'duration' => $duration,
            'elapsed' => $elapsed,
        ]);
    }

    public function reset($id)
    {
        $round = LocalMatchRound::findOrFail($id);

        // Reset data waktu & status ronde
        $round->start_time = null;
        $round->end_time = null;
        $round->status = 'not_started';
        $round->save();

        // Reset status pertandingan lokal ke not_started
        $match = \App\Models\LocalMatch::find($round->local_match_id);
        if ($match) {
            $match->status = 'not_started';
            $match->is_active = false;
            $match->save();

            // ✅ Kirim status ke server pusat
           /* try {
                $client = new \GuzzleHttp\Client();
                $baseUrl = $this->live_server;

                $response = $client->post($baseUrl . '/api/update-tanding-match-status', [
                    'json' => [
                        'remote_match_id' => $match->remote_match_id,
                        'status' => 'not_started',
                    ],
                    'timeout' => 5,
                ]);

                \Log::info('✅ Status reset match dikirim ke server pusat', [
                    'remote_match_id' => $match->remote_match_id,
                    'status' => 'not_started',
                    'http_code' => $response->getStatusCode()
                ]);
            } catch (\Throwable $e) {
                \Log::warning('⚠️ Gagal kirim status reset ke server pusat', [
                    'remote_match_id' => $match->remote_match_id,
                    'error' => $e->getMessage()
                ]);
            }*/
        }

        // Hapus semua skor juri, valid, wasit
        \App\Models\LocalJudgeScore::where('round_id', $round->id)->delete();
        \App\Models\LocalRefereeAction::where('round_id', $round->id)->delete();
        \App\Models\LocalValidScore::where('round_id', $round->id)->delete();

        // Broadcast timer reset
        broadcast(new \App\Events\TimerUpdated($round))->toOthers();

        // Broadcast skor reset (semua ke 0)
        broadcast(new \App\Events\ScoreUpdated(
            $round->local_match_id,
            $round->id,
            0, // blue score
            0, // red score
            0, // blue adjustment
            0  // red adjustment
        ))->toOthers();

        return response()->json(['message' => 'Ronde dan skor direset.']);
    }

    

    public function finish($id)
    {
        $round = LocalMatchRound::findOrFail($id);
        if ($round->status === 'finished') {
            return response()->json(['message' => 'Ronde sudah selesai.'], 400);
        }
        $round->status = 'finished';
        $round->end_time = now();
        $round->save();
        broadcast(new \App\Events\TimerUpdated($round))->toOthers(); // ⬅️ broadcast Reverb
        return response()->json(['message' => 'Ronde selesai.']);
    }





}

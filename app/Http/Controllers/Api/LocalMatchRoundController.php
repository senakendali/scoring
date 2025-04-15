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

    public function start($id)
    {
        $round = LocalMatchRound::findOrFail($id);

        if ($round->status !== 'not_started' && $round->status !== 'paused') {
            return response()->json(['message' => 'Ronde sudah berjalan atau selesai.'], 400);
        }

        $round->start_time = now();
        $round->status = 'in_progress';
        $round->save();

        // Set match aktif
        \App\Models\LocalMatch::where('is_active', true)->update(['is_active' => false]);

        $match = \App\Models\LocalMatch::find($round->local_match_id);
        if ($match) {
            $match->is_active = true;
            $match->save();
        }

        \Log::info('🔔 Mengirim event timer.started', [
            'channel' => 'match.' . $match->id,
            'start_time' => $round->start_time,
            'duration' => 180,
        ]);
        
       /* broadcast(new \App\Events\TimerStarted($match->id, [
            'start_time' => $round->start_time->toIso8601String(),
            'duration' => 180
        ]));*/

        broadcast(new \App\Events\TimerStarted($match->id, [
            'start_time' => $round->start_time->toIso8601String(),
            'duration' => 180,
            'round_id' => $round->id,
            'round_number' => $round->round_number,
        ]));
        
        
        

        return response()->json([
            'message' => 'Ronde dimulai.',
            'start_time' => $round->start_time
        ]);
    }

    public function changeToNextMatch($currentId)
    {
        // Matikan semua match aktif
        LocalMatch::where('is_active', true)->update(['is_active' => false]);

        // Cari next match berdasarkan ID
        $nextMatch = LocalMatch::where('id', '>', $currentId)->orderBy('id')->first();

        if ($nextMatch) {
            $nextMatch->is_active = true;
            $nextMatch->save();

            // Broadcast perubahan match aktif
            broadcast(new ActiveMatchChanged($nextMatch->id))->toOthers();


            return response()->json([
                'message' => 'Match switched',
                'new_match_id' => $nextMatch->id
            ]);
        }

        return response()->json([
            'message' => 'No next match available'
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

        broadcast(new \App\Events\TimerUpdated($round))->toOthers(); // ⬅️ broadcast Reverb
        return response()->json([
            'message' => 'Ronde dilanjutkan',
            'start_time' => $round->start_time,
            'now' => now(),
        ]);
    }



    public function reset($id)
    {
        $round = LocalMatchRound::findOrFail($id);
    
        // Reset data waktu & status
        $round->start_time = null;
        $round->end_time = null;
        $round->status = 'not_started';
        $round->save();
    
        // Hapus semua skor juri yang berkaitan
        \App\Models\LocalJudgeScore::where('round_id', $round->id)->delete();
    
        // Hapus semua tindakan wasit yang berkaitan
        \App\Models\LocalRefereeAction::where('round_id', $round->id)->delete();
    
        // Broadcast timer reset
        broadcast(new \App\Events\TimerUpdated($round))->toOthers();
    
        // Broadcast skor reset (biar UI client juga refresh ke 0)
        broadcast(new \App\Events\ScoreUpdated($round->local_match_id, 0, 0))->toOthers();
    
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

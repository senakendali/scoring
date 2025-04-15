<?php

namespace App\Http\Controllers\Api;

use App\Models\LocalMatchRound;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LocalMatchRoundController extends Controller
{
    public function show($id)
    {
        $round = LocalMatchRound::findOrFail($id);

        return response()->json([
            'id' => $round->id,
            'local_match_id' => $round->local_match_id,
            'round_number' => $round->round_number,
            'status' => $round->status,
            'start_time' => $round->start_time,
            'end_time' => $round->end_time,
            'duration' => 180,
            'elapsed' => $round->start_time ? now()->diffInRealSeconds($round->start_time) : 0,
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

        return response()->json(['message' => 'Ronde dimulai.', 'start_time' => $round->start_time]);
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

        return response()->json([
            'message' => 'Ronde dilanjutkan',
            'start_time' => $round->start_time,
            'now' => now(),
        ]);
    }



    public function reset($id)
    {
        $round = LocalMatchRound::findOrFail($id);
        $round->start_time = null;
        $round->end_time = null;
        $round->status = 'not_started';
        $round->save();

        return response()->json(['message' => 'Ronde direset.']);
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
        return response()->json(['message' => 'Ronde selesai.']);
    }





}

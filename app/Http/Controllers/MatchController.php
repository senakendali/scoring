<?php

namespace App\Http\Controllers;

use App\Models\LocalMatch;
use App\Models\LocalMatchRound;
use App\Models\LocalSeniMatch;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function index(){
        return view('pages.matches.index', [
            'js' => 'matches/index.js'
        ]);
    }

    public function tandingLive(){
        return view('pages.matches.tanding-live', [
            'js' => 'matches/tanding-live.js'
        ]);
    }

    public function seni(){
        return view('pages.matches.seni.index', [
            'js' => 'matches/seni/index.js'
        ]);
    }

    public function seniLive(){
        return view('pages.matches.seni.live', [
            'js' => 'matches/seni/seni-live.js'
        ]);
    }


    public function show($match_id)
    {
        // Reset semua jadi false dulu
        \App\Models\LocalMatch::query()->update(['is_active' => false]);

        // Set match ini jadi aktif
        //\App\Models\LocalMatch::where('id', $id)->update(['is_active' => true]);

        $match = LocalMatch::with('rounds')->findOrFail($match_id);

        // Cek apakah sudah ada ronde, kalau belum auto generate
        if (!$match->rounds()->exists()) {
            for ($i = 1; $i <= $match->total_rounds; $i++) {
                LocalMatchRound::create([
                    'local_match_id' => $match->id,
                    'round_number' => $i,
                ]);
            }
        }

        return view('pages.matches.operator', [
            'js' => 'matches/operator.js'
        ]);
    }

    public function showSeni($match_id)
    {
        return view('pages.matches.seni.operator', [
            'js' => 'matches/seni/operator.js'
        ]);
    }

    public function displayJudge($match_id)
    {
        $match = LocalMatch::with('rounds')->findOrFail($match_id);

        // Cek apakah sudah ada ronde, kalau belum auto generate
        if (!$match->rounds()->exists()) {
            for ($i = 1; $i <= $match->total_rounds; $i++) {
                LocalMatchRound::create([
                    'local_match_id' => $match->id,
                    'round_number' => $i,
                ]);
            }
            $match->load('rounds'); // reload ulang dengan ronde baru
        }

        return view('pages.matches.judges', [
            'match_id' => $match->id,
            'js' => 'matches/judges.js'
        ]);
    }

    public function displaySeniJudge($match_id)
    {
        $match = LocalSeniMatch::findOrFail($match_id);

        

        return view('pages.matches.seni.judges', [
            'match_id' => $match->id,
            'js' => 'matches/seni/judges.js'
        ]);
    }

     public function displaySeniArena($match_id){
        $match = LocalSeniMatch::findOrFail($match_id);

       
        return view('pages.matches.seni.arena', [
            'match_id' => $match->id,
            'js' => 'matches/seni/arena.js'
        ]); 
    }

    public function displaySeniReferee($match_id)
    {
        $match = LocalSeniMatch::findOrFail($match_id);
        return view('pages.matches.seni.referees', [
            'match_id' => $match->id,
            'js' => 'matches/seni/referees.js'
        ]);
    }

    public function displaySeniRecapitulation($match_id)
    {
        return view('pages.matches.seni.recapitulation', [
            'match_id' => $match_id,
            'js' => 'matches/seni/recapitulation.js'
        ]);
    }

    public function displayReferee($match_id)
    {
        $match = LocalMatch::with('rounds')->findOrFail($match_id);

        // Cek apakah sudah ada ronde, kalau belum auto generate
        if (!$match->rounds()->exists()) {
            for ($i = 1; $i <= $match->total_rounds; $i++) {
                LocalMatchRound::create([
                    'local_match_id' => $match->id,
                    'round_number' => $i,
                ]);
            }
            $match->load('rounds'); // reload ulang dengan ronde baru
        }

        return view('pages.matches.referees', [
            'match_id' => $match->id,
            'round_id' => $match->rounds()->first()->id,
            'js' => 'matches/referees.js'
        ]);
    }

    public function displayRecapitulation($match_id)
    {
        return view('pages.matches.recapitulation', [
            'match_id' => $match_id,
            'js' => 'matches/recapitulation.js'
        ]);
    }


    public function displayArena($match_id){
        $match = LocalMatch::with('rounds')->findOrFail($match_id);

        // Cek apakah sudah ada ronde, kalau belum auto generate
        if (!$match->rounds()->exists()) {
            for ($i = 1; $i <= $match->total_rounds; $i++) {
                LocalMatchRound::create([
                    'local_match_id' => $match->id,
                    'round_number' => $i,
                ]);
            }
            $match->load('rounds'); // reload ulang dengan ronde baru
        }
        return view('pages.matches.arena', [
            'match_id' => $match->id,
            'js' => 'matches/arena.js'
        ]); 
    }

   

    
    
    
}

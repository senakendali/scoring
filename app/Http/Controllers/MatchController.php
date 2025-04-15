<?php

namespace App\Http\Controllers;

use App\Models\LocalMatch;
use App\Models\LocalMatchRound;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function index(){
        return view('pages.matches.index', [
            'js' => 'matches/index.js'
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

    
    
    // Contoh penggunaan:
    
    
}

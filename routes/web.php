<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\DrawingController;
use App\Http\Controllers\Api\LocalMatchController;
use App\Http\Controllers\Api\LocalMatchRoundController;
use App\Http\Controllers\Api\MatchSetupController;
use Illuminate\Support\Facades\Auth;



Route::get('/', function () {
    return view('pages/setup', [
        'js' => 'setup.js'
    ]);
});

// Match Routes
Route::prefix('matches')->group(function () {
    Route::get('/', [MatchController::class, 'index']);
    Route::get('/{match_id}', [MatchController::class, 'show']);
    Route::get('/display-arena/{match_id}', [MatchController::class, 'displayArena']);
    Route::get('/judges/{match_id}', [MatchController::class, 'displayJudge']);
    Route::get('/referees/{match_id}', [MatchController::class, 'displayReferee']);
    Route::get('/{match_id}/recap', [MatchController::class, 'displayRecapitulation']);
});

// API Routes (should typically be in api.php)
Route::prefix('api')->group(function () {

    Route::get('/bracket', [LocalMatchController::class, 'getBracket']);

    Route::get('/local-matches', [LocalMatchController::class, 'index']);
    Route::get('/local-matches/tournaments', [LocalMatchController::class, 'getTournaments']);
    Route::get('/local-matches/arenas', [LocalMatchController::class, 'getArenas']);
    Route::get('/local-matches/{id}', [LocalMatchController::class, 'show']);
    Route::post('/local-matches/{id}/end-match', [LocalMatchController::class, 'endMatch']);
    Route::get('/local-matches/{id}/live-score', [LocalMatchController::class, 'liveScore']);
    Route::get('/local-matches/{id}/recap', [LocalMatchController::class, 'getRecap']);

    

   
    Route::get('/local-match-rounds/{id}', [LocalMatchRoundController::class, 'show']);
    Route::post('/local-match-rounds/{id}/start', [LocalMatchRoundController::class, 'start']);
    Route::post('/local-match-rounds/{id}/pause', [LocalMatchRoundController::class, 'pause']);
    Route::post('/local-match-rounds/{id}/resume', [LocalMatchRoundController::class, 'resume']);
    Route::post('/local-match-rounds/{id}/reset', [LocalMatchRoundController::class, 'reset']);
    Route::post('/local-match-rounds/{id}/finish', [LocalMatchRoundController::class, 'finish']);
    Route::post('/matches/{id}/next', [LocalMatchRoundController::class, 'changeToNextMatch']);


    Route::post('/local-judge-scores', [LocalMatchController::class, 'submitPoint']);
    Route::get('local-matches/{id}/judge-recap', [LocalMatchController::class, 'judgeRecap']);
    Route::post('/local-referee-actions', [LocalMatchController::class, 'refereeAction']);
    Route::post('/match-personnel-assignments', [MatchSetupController::class, 'store']);
    Route::get('/active-juris', [MatchSetupController::class, 'getActiveJuriNumbers']);
    Route::post('/matches/start', [MatchSetupController::class, 'start']);

    
    Route::post('/request-verification', [LocalMatchController::class, 'requestVerification']);
    Route::post('/submit-verification-vote', [LocalMatchController::class, 'submitVerificationVote']);
    
    Route::get('/judge/current-match', function () {
        $match = \App\Models\LocalMatch::where('is_active', true)->first();
    
        return response()->json([
            'current_match_id' => $match?->id
        ]);
    });

   
    
    
    
});

Route::post('/logout', function () {
    Auth::logout(); // logout user (jika pakai auth)
    session()->flush(); // bersihkan semua session
    return redirect('/'); // redirect ke halaman awal atau login
});





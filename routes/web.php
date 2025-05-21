<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\DrawingController;
use App\Http\Controllers\Api\LocalMatchController;
use App\Http\Controllers\Api\LocalMatchRoundController;
use App\Http\Controllers\Api\MatchSetupController;
use App\Http\Controllers\Api\LocalImportController;
use App\Http\Controllers\Api\LocalMatchSeniController;
use App\Http\Controllers\Api\SeniMatchSetupController;
use App\Http\Controllers\Api\LocalSeniScoreController;
use Illuminate\Support\Facades\Auth;



Route::get('/', function () {
    return view('pages/setup', [
        'js' => 'setup.js'
    ]);
});

Route::get('/import', function () {
    return view('pages/import', [
        'js' => 'import.js'
    ]);
});

// Match Routes
Route::prefix('matches')->group(function () {
    // Seni
    Route::get('/seni', [MatchController::class, 'seni']);
    Route::get('/seni/{match_id}', [MatchController::class, 'showSeni']);
    Route::get('/seni/judges/{match_id}', [MatchController::class, 'displaySeniJudge']);
    Route::get('/seni/display-arena/{match_id}', [MatchController::class, 'displaySeniArena']);
    Route::get('/seni/referees/{match_id}', [MatchController::class, 'displaySeniReferee']);
    Route::get('/seni/{match_id}/recap', [MatchController::class, 'displaySeniRecapitulation']);
   


    // Tanding
    Route::get('/tanding', [MatchController::class, 'index']);
    Route::get('/{match_id}', [MatchController::class, 'show']); 
    Route::get('/display-arena/{match_id}', [MatchController::class, 'displayArena']);
    Route::get('/judges/{match_id}', [MatchController::class, 'displayJudge']);
    Route::get('/referees/{match_id}', [MatchController::class, 'displayReferee']);
    Route::get('/{match_id}/recap', [MatchController::class, 'displayRecapitulation']);

    
});

// API Routes (should typically be in api.php)
Route::prefix('api')->group(function () {
    Route::get('/local-matches/seni', [LocalMatchSeniController::class, 'index']);
    Route::get('/local-matches/seni/{id}', [LocalMatchSeniController::class, 'show']);
    Route::patch('/local-seni-matches/{id}/disqualify', [LocalMatchSeniController::class, 'disqualify']);
    Route::patch('/local-seni-matches/{id}/finish', [SeniMatchSetupController::class, 'finish']);

    //Route::get('/local-match-rounds/{id}', [LocalMatchSeniController::class, 'show']);
    Route::get('/seni/juri-count', [SeniMatchSetupController::class, 'getJuriCount']);
    Route::get('/seni/judges-score', [SeniMatchSetupController::class, 'getJudgeScores']);

    Route::post('/matches/seni/{id}/start', [SeniMatchSetupController::class, 'startPerformance']);
    Route::post('/matches/seni/{id}/pause', [SeniMatchSetupController::class, 'pause']);
    Route::post('/matches/seni/{id}/resume', [SeniMatchSetupController::class, 'resume']);
    Route::post('/matches/seni/{id}/reset', [SeniMatchSetupController::class, 'reset']);
    Route::post('/local-seni-matches/{id}/next', [SeniMatchSetupController::class, 'changeToNextMatch']);

    Route::post('/seni-scores', [LocalSeniScoreController::class, 'store']);
    // routes/api.php
    Route::post('/seni-penalties', [LocalSeniScoreController::class, 'storePenalties']);
    Route::post('/seni-additional-score', [LocalSeniScoreController::class, 'storeAdditionalScore']);
    Route::post('/seni-component-score', [LocalSeniScoreController::class, 'storeComponentScore']);

    

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
    Route::post('/local-referee-actions/cancel', [LocalMatchController::class, 'cancelRefereeAction']);


    Route::post('/match-personnel-assignments', [MatchSetupController::class, 'store']);
    Route::get('/active-juris', [MatchSetupController::class, 'getActiveJuriNumbers']);
    Route::post('/matches/start', [MatchSetupController::class, 'start']);

    Route::post('/matches/seni/start', [SeniMatchSetupController::class, 'start']);



    
    Route::post('/request-verification', [LocalMatchController::class, 'requestVerification']);
    Route::post('/submit-verification-vote', [LocalMatchController::class, 'submitVerificationVote']);

    Route::post('/import-matches', [ImportMatchController::class, 'store']);
    
    Route::get('/judge/current-match', function () {
        $match = \App\Models\LocalMatch::where('is_active', true)->first();
    
        return response()->json([
            'current_match_id' => $match?->id
        ]);
    });

    Route::post('/import-matches', [LocalImportController::class, 'store']);

   
    
    
    
});

Route::post('/logout', function () {
    Auth::logout(); // logout user (jika pakai auth)
    session()->flush(); // bersihkan semua session
    return redirect('/'); // redirect ke halaman awal atau login
});







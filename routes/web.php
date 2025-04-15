<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\DrawingController;
use App\Http\Controllers\Api\LocalMatchController;
use App\Http\Controllers\Api\LocalMatchRoundController;

Route::get('/', function () {
    return view('welcome');
});

// Match Routes
Route::prefix('matches')->group(function () {
    Route::get('/', [MatchController::class, 'index']);
    Route::get('/{match_id}', [MatchController::class, 'show']);
    Route::get('/display-arena/{match_id}', [MatchController::class, 'displayArena']);
    Route::get('/judges/{match_id}', [MatchController::class, 'displayJudge']);
});

// API Routes (should typically be in api.php)
Route::prefix('api')->group(function () {
    Route::get('/local-matches', [LocalMatchController::class, 'index']);
    Route::get('/local-matches/{id}', [LocalMatchController::class, 'show']);
    Route::post('/local-matches/{id}/end-match', [LocalMatchController::class, 'endMatch']);
    Route::get('/local-matches/{id}/live-score', [LocalMatchController::class, 'liveScore']);

   
    Route::get('/local-match-rounds/{id}', [LocalMatchRoundController::class, 'show']);
    Route::post('/local-match-rounds/{id}/start', [LocalMatchRoundController::class, 'start']);
    Route::post('/local-match-rounds/{id}/pause', [LocalMatchRoundController::class, 'pause']);
    Route::post('/local-match-rounds/{id}/resume', [LocalMatchRoundController::class, 'resume']);
    Route::post('/local-match-rounds/{id}/reset', [LocalMatchRoundController::class, 'reset']);
    Route::post('/local-match-rounds/{id}/finish', [LocalMatchRoundController::class, 'finish']);
});





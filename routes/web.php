<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MatchController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/matches', [MatchController::class, 'index']);
Route::get('/matches/{match_id}', [MatchController::class, 'show']);
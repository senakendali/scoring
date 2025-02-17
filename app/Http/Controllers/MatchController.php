<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function index(){
        return view('pages.matches.index');
    }

    public function show($match_id){
        return view('pages.matches.start');
    }
}

@extends('layouts.app')
@section('content')
<input type="hidden" id="session-arena" value="{{ session('arena_name') }}">
<input type="hidden" id="session-role" value="{{ ucfirst(session('role')) }}">
<input type="hidden" id="session-juri-number" value="{{ session('juri_number') }}">
<input type="hidden" id="session-role" value="{{ session('role') }}">
<input type="hidden" id="session-tournament" value="{{ session('tournament_name') }}">


<div class="container-fluid">
    <div class="row">
        <div class="col-12">
        <div class="content">
      
            <div id="match-tables"></div>
        </div>
        </div>
    </div>
</div>
    
    
@endsection
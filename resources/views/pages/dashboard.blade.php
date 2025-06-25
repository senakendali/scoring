@extends('layouts.app')
@section('content')
<div class="fix-match-info dark" id="tournament-name">{{ session('tournament_name') }}</div>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
        <div class="content">
            <h4 class="text-white mb-5 mt-5">REKAPITULASI PEROLEHAN MENDALI</h4>
            <div id="recap-tables"></div>   
        </div>
        </div>
    </div>
    
</div>
@endsection

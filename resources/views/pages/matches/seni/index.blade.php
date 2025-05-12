@extends('layouts.app')
@section('content')
<!-- Modal Bracket -->
<div class="modal fade" id="bracketModal" tabindex="-1" aria-labelledby="bracketModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title" id="bracketModalLabel">Bracket Pertandingan</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
      
        <div id="bracket-container" class="bracket w-100 h-100 overflow-auto">
            <svg id="bracket-svg-layer" width="100%" height="100%" style="position:absolute; top:0; left:0; z-index:0; pointer-events:none;"></svg>
        </div>
       

      </div>
    </div>
  </div>
</div>


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
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

<div class="modal fade" id="rankingModal" tabindex="-1">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title" id="rankingModalLabel">Ranking</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <ul id="ranking-list" class="list-group list-group-flush"></ul>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modal-final-match" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Pilih Juara Tiap Pool</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="final-match-body">
        <!-- Diisi lewat jQuery -->
      </div>
      <div class="modal-footer">
        <button id="submit-final-match" class="btn btn-success">Buat Match</button>
      </div>
    </div>
  </div>
</div>

<input type="hidden" id="session-arena" value="{{ session('arena_name') }}">
<input type="hidden" id="session-role" value="{{ ucfirst(session('role')) }}">
<input type="hidden" id="session-juri-number" value="{{ session('juri_number') }}">
<input type="hidden" id="session-role" value="{{ session('role') }}">
<input type="hidden" id="session-tournament" value="{{ session('tournament_name') }}">
<div class="fix-match-info dark" id="tournament-name">{{ session('tournament_name') }}</div>
@if(session('role') === 'operator')
<div class="container-fluid">
    <div class="row mt-4">
      <div class="col-lg-12 d-flex justify-content-end">
        <button id="btn-create-match-final" class="btn btn-primary mb-4">
            Buat Match Antar Juara Pool
        </button>
      </div>
    </div>
    <div class="row">
        <div class="col-12">
        <div class="content">
      
            <div id="match-tables"></div>
        </div>
        </div>
    </div>
</div>
@else
<div class="bg-white text-dark d-flex align-items-center justify-content-center" style="height: calc(100vh - 180px);">
    <h4 id="typing-text" class="roboto-bold text-uppercase"></h4>
</div>
    
@endif    
    
@endsection
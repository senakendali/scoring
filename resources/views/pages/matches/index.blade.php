@extends('layouts._app')
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

<div class="modal fade" id="winnerModal" tabindex="-1" aria-labelledby="winnerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title" id="winnerModalLabel">Daftar Pemenang</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <table class="table table-dark table-striped">
          <thead>
            <tr>
              <th>No Partai</th>
              <th>Pemenang</th>
              <th>Kontingen</th>
              <th>Score</th>
              <th>Arena</th>
            </tr>
          </thead>
          <tbody id="winner-list"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>



<input type="hidden" id="session-arena" value="{{ session('arena_name') }}">
<input type="hidden" id="session-role" value="{{ ucfirst(session('role')) }}">
<input type="hidden" id="session-juri-number" value="{{ session('juri_number') }}">
<input type="hidden" id="session-role" value="{{ session('role') }}">
<input type="hidden" id="session-tournament" value="{{ session('tournament_name') }}">


@if(session('role') === 'operator' || session('role') === 'admin')
<div class="fix-match-info dark" id="tournament-name">{{ session('tournament_name') }}</div>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
        <div class="content">
      
             
                <div id="match-tables"></div>
                
            
        </div>
        </div>
    </div>
</div>
@else
<div class="bg-white text-dark d-flex flex-column align-items-center justify-content-center" 
     style="position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: url('{{ asset('images/bg-hero.png') }}') no-repeat center center;
            background-size: cover;">
    <h1 class="roboto-bold text-uppercase">{{ session('tournament_name') }}</h1>
    @if(session('juri_number'))
    <div class="text-uppercase fw-bold" style="font-style:italic;">Juri {{ session('juri_number') }}</div>
    @endif
</div>
    
@endif
    
    
@endsection
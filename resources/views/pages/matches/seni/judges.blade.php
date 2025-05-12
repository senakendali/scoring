@extends('layouts.app')
@section('content')
<div class="modal fade" id="disqualifiedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-danger text-white text-center">
      <div class="modal-header border-0">
        <h5 class="modal-title w-100">Diskualifikasi</h5>
      </div>
      <div class="modal-body">
        Peserta telah didiskualifikasi oleh juri. Pertandingan dihentikan.
      </div>
      <div class="modal-footer justify-content-center border-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="finishedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-success text-white text-center">
      <div class="modal-header border-0">
        <h5 class="modal-title w-100">Waktu Habis</h5>
      </div>
      <div class="modal-body">
        Penampilan telah selesai.
      </div>
      <div class="modal-footer justify-content-center border-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>


<div class="fix-match-info light roboto-bold" id="tournament-name">-</div>
<div class="judges-container d-flex flex-column"  style="height:calc(100vh - 100px);">
    
    <input type="hidden" id="match-id" value="{{ $match_id }}">
    <input type="hidden" id="judge-number" value="{{ session('juri_number') }}">

    <input type="hidden" id="session-arena" value="{{ session('arena_name') }}">
    <input type="hidden" id="session-role" value="{{ ucfirst(session('role')) }}">
    <input type="hidden" id="session-juri-number" value="{{ session('juri_number') }}">
    <input type="hidden" id="session-role" value="{{ session('role') }}">
    <input type="hidden" id="session-tournament" value="{{ session('tournament_name') }}">


    <!-- Header Match -->
    <div class="match-header">
        <input type="hidden" id="round-id">

        
        <div class="match-details">
            <div class="detail-item" id="match-code">-</div>
            <div class="detail-item" id="age-category">-</div>
            <div class="detail-item" id="gender">-</div>
        </div>

        <div class="match-item">
            <div class="seni-participant-detail">
                <div id="contingent-name" class="contingent-name item">AA</div>
                <div id="participant-1" class="participant-1 item">AAAA</div>
                <div id="participant-2" class="participant-2 item">BBBB</div>
                <div id="participant-3" class="participant-3 item">CCCC</div>
                
            </div>
        </div>
    </div>

   <div class="judges-display d-flex flex-column h-100 bg-dark">   
        <div class="d-flex gap-2 h-100">
            <div class="flex-fill d-flex flex-column gap-2 judges-seni-score">
                <div id="starting-score" class="starting-score d-flex align-items-center justify-content-center">
                    -
                </div>
                <div id="deduction" class="deduction d-flex align-items-center justify-content-center">
                    -
                </div>
            </div>
            <div class="flex-fill d-flex align-items-center justify-content-center judges-seni-button">
                <button class="wrong-move">
                    WRONG MOVE
                </button>
            </div>
        </div>
    </div>






    


</div>
@endsection

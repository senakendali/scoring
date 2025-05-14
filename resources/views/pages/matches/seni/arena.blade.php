@extends('layouts.seni')
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
      
    </div>
  </div>
</div>


 <div class="fix-match-info" id="tournament-name">-</div>
 <div class="fix-match-detail">
    <div class="detail-item" id="match-code">-</div>
    <div class="detail-item" id="age-category">-</div>
    <div class="detail-item" id="gender">-</div>
</div>
<div class="d-flex flex-column" style="height:calc(100vh - 180px);">
    <input type="hidden" id="match-id" value="{{ $match_id }}">

    <input type="hidden" id="session-arena" value="{{ session('arena_name') }}">
    <input type="hidden" id="session-role" value="{{ ucfirst(session('role')) }}">
    <input type="hidden" id="session-juri-number" value="{{ session('juri_number') }}">
    <input type="hidden" id="session-role" value="{{ session('role') }}">
    <input type="hidden" id="session-tournament" value="{{ session('tournament_name') }}">

     <div class="match-header">
        <div class="match-item">
            <div class="seni-participant-detail">
                <div id="contingent-name" class="contingent-name item">-</div>
                <div id="participant-1" class="participant-1 item">-</div>
                <div id="participant-2" class="participant-2 item">-</div>
                <div id="participant-3" class="participant-3 item">-</div>   
            </div>
        </div>
    </div>

    <div class="arena-main-container d-flex flex-column overflow-auto bg-dark ">
   
        <div id="judges-preview" class="d-flex seni-judges">
            
        </div>
        <div class="d-flex bg-success seni-judges">
            <div class="flex-fill judge-score-detail">
                <div class="judge-title fw-bold">
                    MEDIAN
                </div>
                <div id="median-score" class="judge-score fw-bold">
                    8.20
                </div>
            </div>
             <div class="flex-fill judge-score-detail">
                <div class="judge-title fw-bold">
                    HUKUMAN
                </div>
                <div id="penalty" class="judge-score fw-bold">
                    0
                </div>
            </div>
             <div class="flex-fill judge-score-detail">
                <div class="judge-title fw-bold">
                    STANDAR DEVIASI
                </div>
                <div id="standar-deviasi" class="judge-score fw-bold">
                    0
                </div>
            </div>  
        </div>
        <div class="d-flex bg-dark seni-judges">
            <div id="total-score" class="flex-fill text-white fw-bold d-flex align-items-center justify-content-center seni-footer">
                8.2000
            </div>
            <div class="flex-fill text-white fw-bold d-flex align-items-center justify-content-center seni-footer">
                <div id="timer" class="timer">
                    00:00
                </div>
            </div>
            
        </div>
        
    </div>

    

    
    

    
    
</div>   
@endsection
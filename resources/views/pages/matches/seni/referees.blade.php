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
<div class="modal fade" id="matchListModal" tabindex="-1" aria-labelledby="matchListModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-sm">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title" id="matchListModalLabel">Daftar Partai</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-2">
        <ul class="list-group list-group-flush" id="match-list">
          <!-- Isi list di JS -->
        </ul>
      </div>
    </div>
  </div>
</div>


 <div class="fix-match-info" id="tournament-name">-</div>
 <div class="fix-match-detail">
    <div class="detail-item" id="match-code" style="cursor:pointer;">-</div>
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

   <div class="arena-main-container d-flex flex-column bg-dark" style="height: 100vh; overflow: hidden;">

        <div class="table-responsive w-100 h-100">
            <table class="mytable table table-dark w-100 h-100 mb-0">
                <thead>
                    <tr>
                    <th style="width: 50%">PENALTY</th>
                    <th style="width: 25%">PENGURANGAN SCORE</th>
                    <th style="width: 25%">TOTAL PENGURANGAN</th>
                    </tr>
                </thead>
                <tbody>
                   <tr>
                    <td>Waktu</td>
                    <td>
                        <button class="btn btn-success btn-sm reset-deduction">CLEAR</button>
                        <button class="btn btn-danger btn-sm poin-deduction">-0.50</button>
                    </td>
                    <td>
                        <input type="text" class="form-control text-center" readonly value="0.00">
                    </td>
                    </tr>
                    <tr>
                    <td>Performa Melebihi Arena 10m x 10m</td>
                    <td>
                        <button class="btn btn-success btn-sm reset-deduction">CLEAR</button>
                        <button class="btn btn-danger btn-sm poin-deduction">-0.50</button>
                    </td>
                    <td>
                        <input type="text" class="form-control text-center" readonly value="0.00">
                    </td>
                    </tr>
                    <tr>
                    <td>Menjatuhkan senjata, menyentuh lantai</td>
                    <td>
                        <button class="btn btn-success btn-sm reset-deduction">CLEAR</button>
                        <button class="btn btn-danger btn-sm poin-deduction">-0.50</button>
                    </td>
                    <td>
                        <input type="text" class="form-control text-center" readonly value="0.00">
                    </td>
                    </tr>
                    <tr>
                    <td>Pakaian tidak sesuai dengan aturan (Tanjak atau samping terlepas)</td>
                    <td>
                        <button class="btn btn-success btn-sm reset-deduction">CLEAR</button>
                        <button class="btn btn-danger btn-sm poin-deduction">-0.50</button>
                    </td>
                    <td>
                        <input type="text" class="form-control text-center" readonly value="0.00">
                    </td>
                    </tr>
                    <tr>
                    <td>Atlet bertahan pada satu gerakan selama lebih dari 5 detik</td>
                    <td>
                        <button class="btn btn-success btn-sm reset-deduction">CLEAR</button>
                        <button class="btn btn-danger btn-sm poin-deduction">-0.50</button>
                    </td>
                    <td>
                        <input type="text" class="form-control text-center" readonly value="0.00">
                    </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                    <td colspan="2" class="text-start">Total Pengurangan</td>
                    <td id="penalty-total">
                        <input type="text" class="form-control text-center" readonly value="0.00">
                    </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        
        
        
       
        
    </div>

    

    
    

    
    
</div>   
@endsection
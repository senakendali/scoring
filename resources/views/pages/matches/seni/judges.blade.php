@extends('layouts.seni')
@section('content')
<!-- Modal Notifikasi -->
<div class="modal fade" id="scoreSubmitModal" tabindex="-1" aria-labelledby="scoreSubmitModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="scoreSubmitModalLabel">Info</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body" id="scoreSubmitModalBody">
        <!-- Pesan akan diisi dari JS -->
      </div>
      <div class="modal-footer border-0 justify-content-center">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

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

<div class="fix-match-info dark" id="tournament-name">-</div>
<div class="fix-match-detail">
    <div class="detail-item" id="match-code" style="cursor:pointer;">-</div>
    <div class="detail-item" id="age-category">-</div>
    <div class="detail-item" id="gender">-</div>
</div>

<!--div class="fix-match-info light roboto-bold" id="tournament-name">-</div-->
<div class="judges-container d-flex flex-column"  style="height:calc(100vh - 180px);">
    
    <input type="hidden" id="match-id" value="{{ $match_id }}">
    <input type="hidden" id="judge-number" value="{{ session('juri_number') }}">
    <input type="hidden" name="seni_base_score" id="seni_base_score" value="9.90">

    <input type="hidden" id="session-arena" value="{{ session('arena_name') }}">
    <input type="hidden" id="session-role" value="{{ ucfirst(session('role')) }}">
    <input type="hidden" id="session-juri-number" value="{{ session('juri_number') }}">
    <input type="hidden" id="session-role" value="{{ session('role') }}">
    <input type="hidden" id="session-tournament" value="{{ session('tournament_name') }}">


    <!-- Header Match -->
    <div class="match-header">
        <input type="hidden" id="round-id">

        <!--div class="match-details judges-page" style="font-size: 12px;">
            <div class="detail-item" id="match-code">-</div>
            <div class="detail-item" id="age-category">-</div>
            <div class="detail-item" id="gender">-</div>
        </div-->

        <div class="match-item judges-page" style="font-size: 12px;">
            <div class="seni-participant-detail">
                <div id="contingent-name" class="contingent-name item">-</div>
                <div id="participant-1" class="participant-1 item">-</div>
                <div id="participant-2" class="participant-2 item">-</div>
                <div id="participant-3" class="participant-3 item">-</div>
            </div>
        </div>
    </div>

   <div class="judges-display d-flex flex-column h-100 bg-dark"> 
       
       
        <div id="mode_one" class="d-flex flex-column flex-grow-1 overflow-auto" style="min-height: 0; padding-bottom: 1.5rem;">
            <div class="d-flex gap-2" style="height:170px;">
              <div class="flex-fill d-flex flex-column gap-2 judges-seni-score">
                  <div id="starting-score" class="starting-score d-flex align-items-center justify-content-center">
                      -
                  </div>
                  <div id="deduction" class="deduction d-flex align-items-center justify-content-center">
                      -
                  </div>  
              </div>
              <div class="flex-fill d-flex flex-column gap-2 align-items-center justify-content-center judges-seni-button">
                  <button class="wrong-move">
                      WRONG MOVE
                  </button>
              </div>
            </div>
            

            <div class="additional-seni-score d-flex align-items-center justify-content-end">
              <!-- Label -->
              <div class="text-white" style="white-space: nowrap; font-size: 12px; margin-left: 10px; text-align:left; ">
                    <strong>Kemantapan / Penghayatan / Stamina</strong>
              </div>
              <div class="additional-score-wrapper d-flex align-items-center justify-content-end gap-2 px-2">
              

                <!-- Minus Button -->
                <button type="button" class="btn btn-danger btn-decrease-additional">
                    <i class="bi bi-dash-circle"></i>
                </button>

                <!-- Score Input -->
                <input type="text" id="additional_score" class="form-control text-center"  style="width: 80px;" value="0.00" readonly>

                <!-- Plus Button -->
                <button type="button" class="btn btn-primary btn-increase-additional">
                    <i class="bi bi-plus-circle"></i>
                </button>

                <!-- Reset Button -->
                <button type="button" class="btn btn-secondary btn-reset-additional">
                    Reset
                </button>

                <!-- Submit Button -->
                <button type="button" class="btn btn-success btn-submit-additional">
                    Submit
                </button>
              </div>
            </div>
            
        </div>
        
        <div id="mode_two" class="d-flex gap-2 h-100">
          <div class="table-responsive w-100 h-100">
               <table class="judges_table table table-dark w-100 mb-0">
                  <thead>
                      <tr style="display: none">
                      <th style="width: 15%">SCORING ELEMENET</th>
                      <th style="width: 70%"></th>
                      <th style="width: 15%">SCORE</th>
                      </tr>
                  </thead>
                 <tbody>
                    <tr data-component="attack_defense_technique">
                      <td style="font-size:11px; ">Attack Defense Technique (0.01 - 0.30)</td>
                      <td class="score-buttons d-flex flex-wrap gap-1">
                        <!-- Button 0.01 - 0.30 -->
                        <!-- Isi pakai JS atau generate manual -->
                      </td>
                      <td><input type="text" class="form-control text-center component-total" readonly value="0.00"></td>
                    </tr>
                    <tr data-component="firmness_harmony">
                      <td style="font-size:11px; ">Firmness and Harmony (0.01 - 0.30)</td>
                      <td class="score-buttons d-flex flex-wrap gap-1"></td>
                      <td><input type="text" class="form-control text-center component-total" readonly value="0.00"></td>
                    </tr>
                    <tr data-component="soulfulness">
                      <td style="font-size:11px; ">Soulfullness (0.01 - 0.30)</td>
                      <td class="score-buttons d-flex flex-wrap gap-1"></td>
                      <td><input type="text" class="form-control text-center component-total" readonly value="0.00"></td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr style="display: none">
                      <td colspan="2" style="font-size:11px; border:none; text-align: right"></td>
                      <td style="border:none; ">Total Score <input type="text" id="component-total" class="form-control text-center" readonly value="0.00"></td>
                    </tr>
                    <tr style="display: none">
                      <td colspan="2" style="font-size:11px; border:none; ">Base Score</td>
                      <td style="border:none; "><input type="text" id="base-score" class="form-control text-center" readonly value="9.10"></td>
                    </tr>
                    <tr>
                      <td colspan="2" style="font-size:11px; border:none;  text-align: left">Score</td>
                      <td style="border:none; "><input type="text" id="final-score" class="form-control text-center" readonly value="9.10"></td>
                    </tr>
                  </tfoot>
              </table>
          </div>
        </div>
        
      </div>
</div>
@endsection

@extends('layouts.seni_layout')
@section('content')
<input type="hidden" id="match-id" value="">
<div class="modal fade" id="confirmResetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white text-center">
      <div class="modal-header border-0">
        <h5 class="modal-title w-100">Reset</h5>
      </div>
      <div class="modal-body">
        Yakin ingin mereset penampilan ini? Timer dan skor akan kembali ke awal.
      </div>
      <div class="modal-footer justify-content-center border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-warning" id="confirm-reset-btn">Ya, Reset</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="confirmEndModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white text-center">
      <div class="modal-header border-0">
        <h5 class="modal-title w-100">Diskualifikasi Peserta?</h5>
      </div>
      <div class="modal-body">
       Apakah peserta akan diskualifikasi?
      </div>
      <div class="modal-footer justify-content-center border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger" id="confirm-end-btn">Ya, Akhiri</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="endedMatchModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white text-center">
      <div class="modal-header border-0">
        <h5 class="modal-title w-100">Pertandingan Dihentikan</h5>
      </div>
      <div class="modal-body">
        Pertandingan telah dihentikan.
      </div>
      <div class="modal-footer justify-content-center border-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Notifikasi -->
<div class="modal fade" id="nextMatchModalInfo" tabindex="-1" aria-labelledby="nextMatchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white text-center">
      <div class="modal-header">
        <h5 class="modal-title" id="nextMatchModalLabel">Info</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body" id="nextMatchModalBody">
        <!-- Isi pesan akan dimasukkan via JS -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
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

<!-- Modal Input Waktu Perform -->
<div class="modal fade" id="performanceTimeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header border-0">
        <h5 class="modal-title">Waktu Perform</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <label for="performance-time-input" class="form-label">Masukkan durasi perform</label>
        <input 
          type="text" 
          id="performance-time-input" 
          class="form-control bg-secondary text-white" 
          placeholder="contoh: 3,5  atau  3.5  atau  3:30"
          inputmode="decimal"
        />
        <div class="form-text text-light mt-2">
          Format yang didukung:
          <ul class="mb-0">
            <li><code>3,5</code> atau <code>3.5</code> = 3,5 menit</li>
            <li><code>3:30</code> = 3 menit 30 detik</li>
          </ul>
          (Tanda koma otomatis diubah jadi titik)
        </div>
        <div id="performance-time-error" class="text-danger mt-2 d-none"></div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" id="save-performance-time" class="btn btn-primary">
          Simpan & Akhiri
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Set Winner -->
<div class="modal fade" id="setWinnerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header border-0">
        <h5 class="modal-title">Set Winner</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="set-winner-match-id" value="">
        <div class="mb-3">
          <label class="form-label">Pilih Pemenang</label>
          <select id="winner-participant" class="form-select bg-secondary text-white">
            <!-- diisi via JS: option value=participant_key (1/2/3), text=nama -->
          </select>
        </div>
       
        <div class="mb-3">
          <label class="form-label">Alasan Menang</label>
          <select id="winner-reason" class="form-select bg-secondary text-white">
             <option value="mutlak">Menang Mutlak</option>
            <option value="undur_diri">Menang Undur Diri</option>
            <option value="diskualifikasi">Menang Diskualifikasi</option>
          </select>
        </div>
        <div id="set-winner-error" class="text-danger d-none"></div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" id="submit-set-winner" class="btn btn-primary">Simpan</button>
      </div>
    </div>
  </div>
</div>


<div class="match-header">
    <input type="hidden" id="round-id">
    <div class="match-item">
        <div class="seni-participant-detail">
            <div id="contingent-name" class="contingent-name item">-</div>
            <div id="participant-1" class="participant-1 item">-</div>
            <div id="participant-2" class="participant-2 item">-</div>
            <div id="participant-3" class="participant-3 item">-</div>
            
        </div>
    </div>
</div>

<div class="match-content">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-6">
                <div class="match-panel text-center">
                    <div class="d-flex flex-column gap-2 mb-2 duration d-none">
                        <label for="round-duration" class="text-white text-center">Durasi Ronde (menit)</label>
                        <select id="round-duration" class="form-select text-center">
                            <option value="60">1 Menit</option>
                            <option value="90">1.5 Menit</option>
                            <option value="120">2 Menit</option>
                            <option value="150">2.5 Menit</option>
                            <option value="180" selected>3 Menit</option>
                            <option value="210">3.5 Menit</option>
                            <option value="240">4 Menit</option>
                            <option value="270">4.5 Menit</option>
                            <option value="300">5 Menit</option>
                        </select>
                    </div>
                    
                    <div id="display-timer" class="panel-content d-flex justify-content-center align-items-center">
                        <div class="timer roboto-bold" id="timer">00:00</div>
                    </div>
                    <div class="panel-footer">
                          <div class="d-flex gap-2">
                              <button class="pause btn d-flex justify-content-center align-items-center text-white roboto-bold border-0">PAUSE</button>
                              <button class="start btn btn-success d-flex justify-content-center align-items-center text-white roboto-bold border-0">
                                  <i class="bi bi-play-fill me-1"></i> START
                              </button>
                              <button class="btn reset d-flex justify-content-center align-items-center text-white roboto-bold border-0">RESET</button>
                          </div>

                          <div class="d-flex mt-2 gap-2">
                              <button class="btn skip-match d-flex justify-content-center align-items-center text-white roboto-bold border-0">SKIP THIS MATCH</button>
                              <button class="btn set-winner justify-content-center align-items-center text-white roboto-bold border-0">SET WINNER</button>
                          </div>
                          
                          <div class="d-flex mt-2">
                              <button class="btn end-match d-flex justify-content-center align-items-center text-white roboto-bold border-0">DISQUALIFY</button>
                          </div>
                          <div class="d-flex mt-2">
                              <button class="btn next-match d-flex justify-content-center align-items-center text-white roboto-bold border-0 d-none">NEXT MATCH</button>
                          </div>
                      </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
@extends('layouts.app')
@section('content')
<div class="modal fade" id="nextRoundModal" tabindex="-1" aria-labelledby="nextRoundLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white text-center">
      <div class="modal-header">
        <h5 class="modal-title" id="nextRoundLabel">Ronde Selesai</h5>
      </div>
      <div class="modal-body">
        Ronde <span id="modal-round-number">1</span> telah selesai.<br>
        Lanjut ke ronde berikutnya?
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tidak</button>
        <button type="button" class="btn btn-primary" id="confirm-next-round">Ya, Lanjut</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="matchEndModal" tabindex="-1" aria-labelledby="matchEndLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white text-center">
      <div class="modal-header border-0">
        <h5 class="modal-title w-100" id="matchEndLabel">Pertandingan Selesai</h5>
      </div>
      <div class="modal-body">
        Semua ronde telah dijalankan. Silakan lanjut ke pertandingan berikutnya.
      </div>
      <div class="modal-footer justify-content-center border-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="confirmEndModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white text-center">
      <div class="modal-header border-0">
        <h5 class="modal-title w-100">Akhiri Pertandingan?</h5>
      </div>
      <div class="modal-body">
        Yakin ingin mengakhiri seluruh pertandingan lebih awal?
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

<div class="modal fade" id="confirmResetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white text-center">
      <div class="modal-header border-0">
        <h5 class="modal-title w-100">Reset Ronde</h5>
      </div>
      <div class="modal-body">
        Yakin ingin mereset ronde ini? Timer dan skor akan kembali ke awal ronde.
      </div>
      <div class="modal-footer justify-content-center border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-warning" id="confirm-reset-btn">Ya, Reset</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Pilih Pemenang -->
<div class="modal fade" id="selectWinnerModal" tabindex="-1" aria-labelledby="selectWinnerLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header border-0">
        <h5 class="modal-title w-100 text-center" id="selectWinnerLabel">Konfirmasi Hasil Pertandingan</h5>
      </div>
      <div class="modal-body text-start px-4">
        <div class="mb-3">
          <label class="form-label">Pemenang:</label>
          <select id="winner" class="form-select">
            <option value="">-- Pilih --</option>
            <option value="red" id="option-red">-</option>
            <option value="blue" id="option-blue">-</option>
            <option value="draw">Seri</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Alasan Menang:</label>
          <select id="win_reason" class="form-select">
            <option value="">-- Pilih Alasan --</option>
            <option value="score">Menang Angka</option>
            <option value="technique">Menang Tehnik</option>
            <option value="absolute">Menang Mutlak</option>
            <option value="stop">Menang dengan wasit menghentikan pertandingan</option>
            <option value="forfeit">Menang undur diri</option>
            <option value="disqualify">Menang dengan diskualifikasi</option>
            <option value="other">Lainnya</option>
          </select>
        </div>
        <div class="mb-3 d-none" id="other_reason_box">
          <label class="form-label">Alasan Lainnya:</label>
          <input type="text" id="other_reason" class="form-control" placeholder="Masukkan alasan lainnya">
        </div>
      </div>
      <div class="modal-footer border-0 justify-content-center">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-success" id="confirm-winner-btn">Simpan Hasil</button>
      </div>
    </div>
  </div>
</div>


<div class="fix-match-info light roboto-bold" id="tournament-name">-</div>
<div class="match-header">
    <input type="hidden" id="round-id">

    
    <div class="match-details">
        <div class="detail-item" id="match-code">-</div>
        <div class="detail-item" id="stage">-</div>
        <div class="detail-item" id="class-name">-</div>
    </div>

    <div class="match-item">
        <div class="blue d-flex">
            <div class="name d-flex flex-column w-100 roboto-bold justify-content-center align-items-center" id="blue-name">-</div>
            <div class="score d-flex text-white roboto-bold justify-content-center align-items-center" id="blue-score">-</div>
        </div>
        <div class="round d-flex justify-content-center align-items-center roboto-bold" id="current-round">-</div>
        <div class="red d-flex">
            <div class="score d-flex text-white roboto-bold justify-content-center align-items-center" id="red-score">-</div>
            <div class="name d-flex flex-column w-100 roboto-bold justify-content-center align-items-center" id="red-name" >-</div>
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
                    <div class="panel-header">
                        <div class="round d-flex justify-content-center align-items-center active">ROUND 1</div>
                        <div class="round d-flex justify-content-center align-items-center">ROUND 2</div>
                        <div class="round d-flex justify-content-center align-items-center">ROUND 3</div>
                    </div>
                    <div class="panel-content d-flex justify-content-center align-items-center">
                        <div class="timer roboto-bold" id="timer">00:00</div>
                    </div>
                    <div class="panel-footer">
                        <div class="d-flex gap-2">
                            <button class="pause btn d-flex justify-content-center align-items-center text-white roboto-bold border-0"><i class="bi bi-pause-fill"></i> PAUSE</button>
                            <button class="start btn d-flex justify-content-center align-items-center text-white roboto-bold border-0"><i class="bi bi-play-fill"></i> START</button>
                            <button class="reset btn d-flex justify-content-center align-items-center text-white roboto-bold border-0"><i class="bi bi-arrow-clockwise"></i> RESET</button>
                        </div>
                        

                        <div class="d-flex mt-2">
                            <button class="end-match btn d-flex justify-content-center align-items-center text-white roboto-bold border-0"><i class="bi bi-stop-fill"></i> END MATCH</button>
                        </div>
                        <div class="d-flex mt-2">
                            <button class="next-match btn d-flex justify-content-center align-items-center text-white roboto-bold border-0 d-none"><i class="bi bi-arrow-right-circle-fill"></i> NEXT MATCH</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
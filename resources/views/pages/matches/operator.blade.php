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
        Pertandingan telah dihentikan lebih awal oleh operator.
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




<div class="match-header">
    <input type="hidden" id="round-id">

    <div class="match-info" id="tournament-name">-</div>
    <div class="match-details">
        <div class="detail-item" id="match-code">-</div>
        <div class="detail-item" id="match-stage">-</div>
        <div class="detail-item" id="class-name">-</div>
    </div>

    <div class="match-item">
        <div class="blue d-flex">
            <div class="name d-flex w-100 roboto-bold justify-content-center align-items-center" id="blue-name">-</div>
            <div class="score d-flex text-white roboto-bold justify-content-center align-items-center" id="blue-score">-</div>
        </div>
        <div class="round d-flex justify-content-center align-items-center roboto-bold" id="current-round">-</div>
        <div class="red d-flex">
            <div class="score d-flex text-white roboto-bold justify-content-center align-items-center" id="red-score">-</div>
            <div class="name d-flex w-100 roboto-bold justify-content-center align-items-center" id="red-name">-</div>
        </div>
    </div>
</div>

<div class="match-content">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-6">
                <div class="match-panel text-center">
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
                            <button class="pause d-flex justify-content-center align-items-center text-white roboto-bold border-0">PAUSE</button>
                            <button class="start d-flex justify-content-center align-items-center text-white roboto-bold border-0">START</button>
                            <button class="reset d-flex justify-content-center align-items-center text-white roboto-bold border-0">RESET</button>
                        </div>
                        <div class="d-flex mt-2">
                            <button class="end-match d-flex justify-content-center align-items-center text-white roboto-bold border-0">END MATCH</button>
                        </div>
                        <div class="d-flex mt-2">
                            <button class="next-match d-flex justify-content-center align-items-center text-white roboto-bold border-0 d-none">NEXT MATCH</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
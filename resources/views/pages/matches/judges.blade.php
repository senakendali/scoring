@extends('layouts.app')
@section('content')
<input type="hidden" id="match-id" value="{{ $match_id }}">

<!-- Modal Pilih Juri -->
<div class="modal fade" id="setupJudgeModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h5 class="modal-title w-100 text-center">Masukkan Identitas Juri</h5>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="judge_number" class="form-label">Nomor Juri</label>
                    <select id="judge_number" class="form-select">
                        <option value="1">Juri 1</option>
                        <option value="2">Juri 2</option>
                        <option value="3">Juri 3</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="judge_name" class="form-label">Nama Juri</label>
                    <input type="text" id="judge_name" class="form-control" placeholder="Masukkan nama anda" />
                </div>
            </div>
            <div class="modal-footer justify-content-end">
                <button id="startJudgeBtn" class="btn btn-success">Mulai</button>
            </div>
        </div>
    </div>
</div>

<!-- Header Match -->
<div class="match-header">
    <div class="match-info" id="tournament-name">-</div>
    <div class="match-details">
        <div class="detail-item" id="match-code">-</div>
        <div class="detail-item">-</div>
        <div class="detail-item" id="class-name">-</div>
    </div>
    <div class="match-item">
        <div class="blue d-flex">
            <div class="name d-flex w-100 roboto-bold justify-content-center align-items-center" id="blue-name">
                -
            </div>
            <div class="score d-flex text-white roboto-bold justify-content-center align-items-center" id="blue-score">
                0
            </div>
        </div>
        <div class="round d-flex justify-content-center align-items-center roboto-bold" id="current-round">
            -
        </div>
        <div class="red d-flex">
            <div class="score d-flex text-white roboto-bold justify-content-center align-items-center" id="red-score">
                0
            </div>
            <div class="name d-flex w-100 roboto-bold justify-content-center align-items-center" id="red-name">
                -
            </div>
        </div>
    </div>
</div>

<!-- List Poin -->
<div class="judges-display d-flex justify-content-between mt-4 px-3">
    <!--div class="blue-point-list w-50">
        <h6 class="text-center text-primary">Poin Biru</h6>
        <div id="blue-points"></div>
    </div>
    <div class="red-point-list w-50">
        <h6 class="text-center text-danger">Poin Merah</h6>
        <div id="red-points"></div>
    </div-->
</div>

<!-- Tombol Scoring -->
<div class="judges-button d-flex justify-content-between">
    <div class="blue-button text-center w-50">
        <button class="button-item btn btn-primary rounded-0" data-corner="blue" data-type="kick">TENDANGAN</button>
        <button class="button-item btn btn-primary rounded-0" data-corner="blue" data-type="punch">PUKULAN</button>
    </div>
    <div class="red-button text-center w-50">
        <button class="button-item btn btn-danger rounded-0" data-corner="red" data-type="kick">TENDANGAN</button>
        <button class="button-item btn btn-danger rounded-0" data-corner="red" data-type="punch">PUKULAN</button>
    </div>
</div>

@endsection

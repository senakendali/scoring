@extends('layouts.app')
@section('content')
<!-- Modal -->
<div class="modal fade" id="alertModal" tabindex="-1" aria-labelledby="alertModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title" id="alertModalLabel">Informasi</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="alertModalBody">
        Pesan akan ditampilkan di sini.
      </div>
      
    </div>
  </div>
</div>

<div class="container mt-5 text-white d-flex justify-content-center">
    <button class="btn btn-outline-primary me-2" id="mode-admin"><i class="bi bi-person-badge"></i> Masuk Sebagai Administrator</button>
    <button class="btn btn-outline-success" id="mode-user"> <i class="bi bi-person-fill"></i> Masuk Sebagai Perangkat Pertandingan</button>
    <a href="{{ url('import-matches') }}" class="btn btn-outline-info ms-2"><i class="bi bi-upload"></i> Import Matches</a>
</div>

<div class="container mt-4 text-white" id="match-setup-wrapper" style="display: none;">
    <form id="match-setup-form">
        <!-- Dropdown turnamen (selalu ditampilkan) -->
        <div class="mb-3">
            <label for="tournament_name" class="form-label">Pilih Turnamen</label>
            <select id="tournament_name" name="tournament_name" class="form-select">
                <option value="">-- Pilih Turnamen --</option>
            </select>
        </div>
        <!-- Elemen khusus User -->
        <div id="user-fields" style="display: none;">
            <div class="mb-3">
                <label for="match_type" class="form-label">Tipe Pertandingan</label>
                <select id="match_type" name="match_type" class="form-select">
                    <option value="">-- Pilih --</option>
                    <option value="tanding">Tanding</option>
                    <option value="seni">Seni</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Masuk Sebagai</label>
                <select id="role" name="role" class="form-select">
                    <option value="">-- Pilih --</option>
                    <option value="operator">Operator</option>
                    <option value="juri">Juri</option>
                    <option value="dewan">Dewan Pertandingan</option>
                    <option value="ketua">Ketua Pertandingan</option>
                    <option value="penonton">Penonton (Big Screen)</option>
                </select>
            </div>
            <div id="seni-category-wrapper" class="mb-3" style="display: none;">
                <label for="seni_category" class="form-label">Kategori Seni</label>
                <select class="form-select" id="seni_category" name="seni_category">
                    <option value="">-- Pilih Kategori --</option>
                    <option value="tunggal">Tunggal</option>
                    <option value="regu">Regu</option>
                    <option value="ganda">Ganda</option>
                    <option value="solo_kreatif">Solo Kreatif</option>
                </select>
            </div>
            <div class="mb-3 d-none" id="juri-number-group">
                <label for="juri_number" class="form-label">Nomor Juri</label>
                <select id="juri_number" name="juri_number" class="form-select"></select>
            </div>
            <div class="mb-3">
                <label for="arena_name" class="form-label">Pilih Arena</label>
                <select id="arena_name" name="arena_name" class="form-select">
                    <option value="">-- Pilih Arena --</option>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-success">Masuk</button>
    </form>
</div>

@endsection

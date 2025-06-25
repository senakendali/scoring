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

<div class="container mt-5 text-white text-end">
    <button class="btn btn-primary me-2" id="mode-admin">Masuk Sebagai Administrator</button>
    <button class="btn btn-success" id="mode-user">Masuk Sebagai User</button>
</div>


<div class="container mt-5 text-white">
    <form id="match-setup-form">
        <div class="mb-3">
            <label for="tournament_name" class="form-label">Pilih Tournament</label>
            <select id="tournament_name" name="tournament_name" class="form-select">
                <option value="">-- Pilih Tournament --</option>
            </select>
        </div>

       

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
                <option value="penonton">Penonton (Big Screen)</option> <!-- ✅ Tambahan -->
            </select>

        </div>

        <div id="seni-category-wrapper" class="mt-3" style="display: none;">
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
            <select id="juri_number" name="juri_number" class="form-select">
                <!-- JS akan mengisi berdasarkan tipe pertandingan -->
            </select>
        </div>

        <div class="mb-3">
            <label for="arena_name" class="form-label">Pilih Arena</label>
            <select id="arena_name" name="arena_name" class="form-select">
                <option value="">-- Pilih Arena --</option>
            </select>
        </div>

        <button type="submit" class="btn btn-success">Masuk</button>
    </form>
</div>
@endsection

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

<div class="container mt-5 text-white">
    <h2 class="mb-4">Pilih Mode Pertandingan</h2>

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

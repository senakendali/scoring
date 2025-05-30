@extends('layouts.app')
@section('content')
<!-- Modal Notifikasi -->
<div class="modal fade" id="alertModal" tabindex="-1" aria-labelledby="alertModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header border-secondary">
        <h5 class="modal-title" id="alertModalLabel">Notifikasi</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body" id="alertModalBody">
        ...
      </div>
      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Bracket -->
<div class="container mt-5 text-white">
    <input type="hidden" id="data_source" value="{{ $data_source }}">
    <h2 class="mb-4">Import Data Pertandingan (Tanding)</h2>

    <form id="import-form">
        <div class="mb-3">
            <label for="tournament_name" class="form-label">Pilih Tournament</label>
            <select id="tournament_name_tanding" name="tournament_name" class="form-select">
                <option value="">-- Pilih Tournament --</option>
            </select>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="display_timer_tanding" name="is_display_timer" value="1">
          <label class="form-check-label" for="display_timer_tanding">
            Tampilkan Timer di Display
          </label>
        </div>
        <button type="submit" class="btn btn-success">Sync Data dari Server</button>
    </form>
</div>

<div class="container mt-5 text-white">
    <h2 class="mb-4">Import Data Pertandingan (Seni)</h2>

    <form id="import-form-seni">
        <div class="mb-3">
            <label for="tournament_name" class="form-label">Pilih Tournament</label>
            <select id="tournament_name_seni" name="tournament_name" class="form-select">
                <option value="">-- Pilih Tournament --</option>
            </select>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="display_timer_seni" name="is_display_timer" value="1">
          <label class="form-check-label" for="display_timer_seni">
            Tampilkan Timer di Display
          </label>
        </div>


       

        

        <button type="submit" class="btn btn-success">Sync Data dari Server</button>
    </form>
</div>
    
    
@endsection
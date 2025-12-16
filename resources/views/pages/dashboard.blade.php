@extends('layouts.admin')
@section('content')

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
        <div class="content">
            <div class="d-flex flex-column gap-2 justify-content-center align-items-center mb-4 mt-5">
                <h4 class="text-white mb-0">REKAPITULASI PEROLEHAN MENDALI</h4>
                <button class="btn btn-danger btn-sm" id="export-all">
                    <i class="bi bi-file-earmark-pdf-fill"></i> Export All Recap
                </button>
            </div>

            {{-- Medal Recap Filter --}}
            <div class="card bg-dark border-secondary mb-4">
                <div class="card-body">
                    <h6 class="text-white mb-1">Tandai Kategori Full Prestasi</h6>
                    <p class="text-secondary mb-3" style="max-width: 900px;">
                        Centang kategori usia yang menggunakan <b>bagan Full Prestasi</b>.
                        Kategori yang dicentang akan <b>mengabaikan perolehan EMAS</b> dari kelas yang hanya memiliki <b>2 peserta</b> (langsung Final),
                        supaya rekap juara umum tetap adil.
                    </p>

                    <div class="d-flex flex-wrap gap-3" id="full-prestasi-options">
                        @php
                            $cats = ['Usia Dini','Pra Remaja','Remaja','Dewasa','Master'];
                        @endphp

                        @foreach ($cats as $cat)
                            <label class="text-white d-flex align-items-center gap-2">
                                <input type="checkbox" class="form-check-input full-prestasi-check" value="{{ $cat }}">
                                <span>{{ $cat }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-3 d-flex gap-2">
                        <button class="btn btn-outline-light btn-sm" id="apply-full-prestasi">
                            Terapkan Filter
                        </button>
                        <small class="text-secondary d-flex align-items-center">
                            (Disimpan di browser, jadi gak perlu centang ulang)
                        </small>
                    </div>
                </div>
            </div>
            <div id="recap-tables"></div>   
        </div>

        </div>
    </div>
</div>
@endsection

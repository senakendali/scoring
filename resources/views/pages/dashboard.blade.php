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
            <div id="recap-tables"></div>   
        </div>

        </div>
    </div>
</div>
@endsection

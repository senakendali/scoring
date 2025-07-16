@extends('layouts.tanding_layout')
@section('content')
<!-- Modal Voting Verifikasi -->
<div class="modal fade" id="verificationVoteModal" tabindex="-1" aria-labelledby="verificationVoteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="verificationVoteModalLabel">Verifikasi</h5>
      </div>
      <div class="modal-body text-center">
        <p id="verificationVoteQuestion" class="mb-4">Menunggu permintaan verifikasi...</p>
        <div class="d-grid gap-2">
          <button class="btn btn-primary" id="voteBlue">Biru</button>
          <button class="btn btn-warning text-dark" id="voteInvalid">Tidak Sah</button>
          <button class="btn btn-danger" id="voteRed">Merah</button>
        </div>
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


<div class="judges-container d-flex flex-column"  style="height:calc(100vh - 100px);">
    
    <input type="hidden" id="match-id" value="{{ $match_id }}">
    <input type="hidden" id="judge-number" value="{{ session('juri_number') }}">


    <!-- Header Match -->
    <div class="match-header">
        
        <!--div class="match-details judges-page fighting" style="font-size: 12px;">
            <div class="detail-item" id="match-code">-</div>
            <div class="detail-item" id="stage">-</div>
            <div class="detail-item" id="class-name">-</div>
        </div-->
        <div class="match-item judges-page" style="font-size: 12px;">
            <div class="blue d-flex">
                <div class="name d-flex flex-column w-100 roboto-bold justify-content-center align-items-center" id="blue-name">
                    -
                </div>
                <!--div class="score d-flex text-white roboto-bold justify-content-center align-items-center" id="blue-score">
                    0
                </div-->
            </div>
            <div class="round d-flex justify-content-center align-items-center roboto-bold" id="current-round">
                -
            </div>
            <div class="red d-flex">
                <!--div class="score d-flex text-white roboto-bold justify-content-center align-items-center" id="red-score">
                    0
                </div-->
                <div class="name d-flex flex-column w-100 roboto-bold justify-content-center align-items-center" id="red-name">
                    -
                </div>
            </div>
        </div>
    </div>

    <!-- List Poin -->
   <!-- List Poin (ambil sisa tinggi otomatis) -->
   <div class="judges-display d-flex flex-column h-100">
        <div class="judges-recapitulation d-flex">
            <div class="blue-recapitulation d-flex align-items-center gap-2"></div>
            <div class="round roboto-bold d-flex justify-content-center align-items-center" style="font-size: 12px;">
                ROUND 1
            </div>
            <div class="red-recapitulation d-flex align-items-center gap-2"></div>
        </div>
        <div class="judges-recapitulation d-flex">
            <div class="blue-recapitulation d-flex align-items-center gap-2"></div>
            <div class="round roboto-bold d-flex justify-content-center align-items-center" style="font-size: 12px;">
                ROUND 2
            </div>
            <div class="red-recapitulation d-flex align-items-center gap-2"></div>
        </div>
        <div class="judges-recapitulation d-flex">
            <div class="blue-recapitulation d-flex align-items-center gap-2"></div>
            <div class="round roboto-bold d-flex justify-content-center align-items-center" style="font-size: 12px;">
                ROUND 3
            </div>
            <div class="red-recapitulation d-flex align-items-center gap-2"></div>
        </div>
    </div>





    <!-- Tombol Scoring -->
    <div class="judges-button d-flex justify-content-between align-items-center">
        <div class="blue-button text-center" style="width: 250px;">
            <button class="button-item d-flex gap-2" data-corner="blue" data-type="punch">
                <img src="{{ asset('images/punch-icon.png') }}" width="30px" alt=""> PUNCH
            </button>
            <button class="button-item  d-flex gap-2" data-corner="blue" data-type="kick">
                <img src="{{ asset('images/kick-icon.png') }}" width="30px" alt=""> KICK
            </button>  
        </div>

       

        <div class="red-button text-center" style="width: 250px;">
            <button class="button-item d-flex gap-2" data-corner="red" data-type="punch">
                PUNCH 
                <img src="{{ asset('images/punch-icon.png') }}" width="30px" alt="">
            </button>
            <button class="button-item d-flex gap-2" data-corner="red" data-type="kick">
                KICK 
                <img src="{{ asset('images/kick-icon.png') }}" width="30px" alt="">
            </button>
        </div>
    </div>


</div>
@endsection

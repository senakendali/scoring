@extends('layouts.app')
@section('content')
<!-- Modal Result Verifikasi -->
<div class="modal fade" id="verificationResultModal" tabindex="-1" aria-labelledby="verificationResultModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="verificationResultModalLabel">Hasil Verifikasi</h5>
      </div>
      <div class="modal-body text-center">
        <div id="verificationResultContent">
          <!-- Progress bar voting akan muncul di sini -->
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Menunggu Verifikasi -->
<div class="modal fade" id="waitingVerificationModal" tabindex="-1" aria-labelledby="waitingVerificationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="waitingVerificationModalLabel">Informasi</h5>
      </div>
        <div class="modal-body text-center">
            <p id="waitingVerificationMessage" class="mb-0">
            Menunggu hasil verifikasi...
            </p>
        </div>
        <!-- Progress Bar Animasi -->
        <div class="progress" style="height: 10px;">
            <div id="waitingVerificationProgress" class="progress-bar bg-info progress-bar-striped progress-bar-animated" 
                role="progressbar" style="width: 0%"></div>
        </div>
    </div>
  </div>
</div>



<div class="fix-match-info" id="tournament-name">-</div>
 <div class="fix-match-detail">
    <div class="detail-item" id="match-code">-</div>
    <div class="detail-item" id="stage">-</div>
    <div class="detail-item" id="class-name">-</div>
</div>
<div class="judges-container d-flex flex-column" style="height:calc(100vh - 180px);">
    <input type="hidden" id="match-id" value="{{ $match_id }}">
    <input type="hidden" id="round-id" value="{{ $round_id }}">

    <div class="match-header">
        <!--div class="match-info" id="tournament-name">-</div>
        <div class="match-details">
            <div class="detail-item" id="match-code">-</div>
            <div class="detail-item" id="stage">-</div>
            <div class="detail-item" id="class-name">-</div>
            <div id="timer" class="detail-item arena roboto-bold">
                03:00
            </div>
        </div-->
        <div class="match-item">
            <div class="blue d-flex">
                <div id="blue-name" class="name d-flex flex-column w-100 roboto-bold justify-content-center align-items-center">
                    -
                </div>
               
            </div>
            <div id="current-round" class="round d-flex justify-content-center align-items-center roboto-bold">
                -
            </div>
            <div class="red d-flex">
                <div id="red-name" class="name d-flex flex-column w-100 roboto-bold justify-content-center align-items-center">
                    - 
                </div>
            
            </div>
        </div>
        
    </div>
    <div class="main-container flex-grow-1 overflow-auto">

        <!-- Display Score dan Timer -->
        

        
        <div class="arena-container">
            <div class="blue">
            <div class="additional-point">
                    <div class="score-items">
                        <div class="item text-white" data-action="binaan_1" data-point="0" data-corner="blue">
                            <img src="{{ asset('images/binaan-1.png') }}">
                        </div>
                        <div class="item text-white" data-action="binaan_2" data-point="0" data-corner="blue">
                            <img src="{{ asset('images/binaan-2.png') }}" style="transform: rotate(65deg);">
                        </div>
                    </div>
                    <div class="score-items">
                        <div class="item text-white" data-action="teguran_1" data-point="-1" data-corner="blue">
                            <img src="{{ asset('images/teguran-1.png') }}">
                        </div>
                        <div class="item text-white" data-action="teguran_2" data-point="-2" data-corner="blue">
                            <img src="{{ asset('images/teguran-2.png') }}">
                        </div>
                    </div>
                    <div class="score-items">
                        <div class="item text-white" data-action="peringatan_1" data-point="-5" data-corner="blue">
                            <img src="{{ asset('images/peringatan.png') }}">
                        </div>
                        <div class="item text-white" data-action="peringatan_2" data-point="-10" data-corner="blue">
                            <img src="{{ asset('images/peringatan.png') }}">
                            <img src="{{ asset('images/peringatan.png') }}">
                        </div>
                    </div>
                    <div class="score-items">
                        <div class="drop text-white" data-action="jatuhan" data-point="3" data-corner="blue">Jatuhan</div>
                    </div>
                   
                </div>
            </div>
            <div class="score-container">
                <div class="display-score">
                    <div class="referee-score blue">
                        <div id="blue-score" class="d-flex justify-content-center align-items-center roboto-bold">-</div>
                    </div>
                    <div class="referee-score red">
                        <div id="red-score" class="d-flex justify-content-center align-items-center roboto-bold">-</div>
                    </div>
                </div>
                <div class="verification">
                     <div class="score-items">
                        <div class="drop text-white black" data-action="verifikasi_jatuhan" data-point="3" data-corner="blue">Verifikasi Jatuhan</div>
                    </div>
                    <div class="score-items">
                        <div class="drop text-white black" data-action="verifikasi_hukuman" data-point="3" data-corner="red">Verifikasi Hukuman</div>
                    </div>
                </div>
            </div>
            <div class="red">
            <div class="additional-point">
                    <div class="score-items">
                        <div class="item text-white" data-action="binaan_1" data-point="0" data-corner="red">
                            <img src="{{ asset('images/binaan-1.png') }}">
                        </div>
                        <div class="item text-white" data-action="binaan_2" data-point="0" data-corner="red">
                            <img src="{{ asset('images/binaan-2.png') }}" style="transform: rotate(65deg);">
                        </div>
                    </div>
                    <div class="score-items">
                        <div class="item text-white" data-action="teguran_1" data-point="-1" data-corner="red">
                            <img src="{{ asset('images/teguran-1.png') }}">
                        </div>
                        <div class="item text-white" data-action="teguran_2" data-point="-2" data-corner="red">
                            <img src="{{ asset('images/teguran-2.png') }}">
                        </div>
                    </div>
                    <div class="score-items">
                        <div class="item text-white" data-action="peringatan_1" data-point="-5" data-corner="red">
                            <img src="{{ asset('images/peringatan.png') }}">
                        </div>
                        <div class="item text-white" data-action="peringatan_2" data-point="-10" data-corner="red">
                            <img src="{{ asset('images/peringatan.png') }}">
                            <img src="{{ asset('images/peringatan.png') }}">
                        </div>
                    </div>
                    <div class="score-items">
                        <div class="drop text-white" data-action="jatuhan" data-point="3" data-corner="red">Jatuhan</div>
                    </div>
                    
                </div>
            </div>

            

            
            
        </div>
        <!--div class="additional-point d-flex flex-column">
            <div class="score-items">
                <div class="drop text-white" data-action="jatuhan" data-point="3" data-corner="blue">Verifikasi Jatuhan</div>
            </div>
            
            <div class="score-items">
                <div class="drop text-white" data-action="jatuhan" data-point="3" data-corner="red">Verifikasi Hukuman</div>
            </div>
        </div-->
    </div>
</div>    
@endsection
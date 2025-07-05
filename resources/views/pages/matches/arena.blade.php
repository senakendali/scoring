@extends('layouts.app')
@section('content')
<!-- Modal Hasil Verifikasi -->
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


 <div class="fix-match-info" id="tournament-name">-</div>
 <div class="fix-match-detail">
    <div class="detail-item" id="match-code" style="cursor:pointer;">-</div>
    <div class="detail-item" id="stage">-</div>
    <div class="detail-item" id="class-name">-</div>
</div>
<div class="blue-fall">
    <div>Jatuhan:</div> <div id="blue-fall-count" class="f-count">0</div>
</div> 
<div class="red-fall">
    <div>Jatuhan:</div> <div id="red-fall-count" class="f-count">0</div>
</div>

<div class="d-flex flex-column" style="height:calc(100vh - 180px);">
    <input type="hidden" id="match-id" value="{{ $match_id }}">
    <div class="match-header">
       
        <!--div class="match-details">
            <div class="detail-item" id="match-code">-</div>
            <div class="detail-item" id="stage">-</div>
            <div class="detail-item" id="class-name">-</div>
        </div-->
        <div class="match-item">
            <div class="blue d-flex">
                <div id="blue-name" class="display-arena name d-flex flex-column w-100 roboto-bold justify-content-center align-items-center">
                    -
                </div>
                
            </div>
            <div id="current-round" class="display-arena round d-flex justify-content-center align-items-center roboto-bold">
                -
            </div>
            <div class="red d-flex">
               
                <div id="red-name" class="display-arena name d-flex flex-column w-100 roboto-bold justify-content-center align-items-center">
                    - 
                </div>
            
            </div>
        </div>
        
    </div>

    <div class="arena-main-container flex-grow-1 overflow-auto">
   
        <div class="display-arena-container">
            <div class="blue">
                <div class="additional-score">
                    <div class="score-items">
                        <div class="item text-white" data-action="binaan_1" data-corner="blue">
                            <img src="{{ asset('images/binaan-1.png') }}">
                        </div>
                        <div class="item text-white" data-action="binaan_2" data-corner="blue">
                            <img src="{{ asset('images/binaan-2.png') }}" style="transform: rotate(65deg);">
                        </div>
                    </div>
                    <div class="score-items">
                        <div class="item text-white" data-action="teguran_1" data-corner="blue">
                            <img src="{{ asset('images/teguran-1.png') }}">
                        </div>
                        <div class="item text-white" data-action="teguran_2" data-corner="blue">
                            <img src="{{ asset('images/teguran-2.png') }}">
                        </div>
                    </div>
                    <div class="score-items">
                        <div class="item text-white"  data-action="peringatan_1" data-corner="blue">
                            <img src="{{ asset('images/peringatan-1.png') }}">
                        </div>
                        <div class="item text-white"  data-action="peringatan_2" data-corner="blue">
                            <img src="{{ asset('images/peringatan-2.png') }}">
                           
                        </div>
                    </div>
                    <div class="judge-container">
                        <div class="judges blue">
                            <div id="judge-blue-1" class="judge" data-type="kick" data-corner="blue" data-judge="1">J1</div>
                            <div id="judge-blue-2" class="judge" data-type="kick" data-corner="blue" data-judge="2">J2</div>
                            <div id="judge-blue-3" class="judge" data-type="kick" data-corner="blue" data-judge="3">J3</div>
                            
                        </div>

                       
                    </div>
                </div>
                <!--div class="fall-counter text-center text-dark mb-2" style="background-color: #transparent; height:100%">
                    <small>Jatuhan:</small> <span id="blue-fall-count" class="fw-bold">0</span>
                </div-->
                
                <div id="blue-score" class="score">
                    -
                </div>
                
                
            </div>
            <div class="red">
                
                <div id="red-score" class="score">
                    -
                </div>
                <!--div class="fall-counter text-center text-dark mb-2" style="background-color: #transparent; height:100%">
                    <small>Jatuhan:</small> <span id="red-fall-count" class="fw-bold">0</span>
                </div-->  
                <div class="additional-score">
                    <div class="score-items">
                        <div class="item text-white" data-action="binaan_1" data-corner="red">
                            <img src="{{ asset('images/binaan-1.png') }}">
                        </div>
                        <div class="item text-white" data-action="binaan_2" data-corner="red">
                            <img src="{{ asset('images/binaan-2.png') }}" style="transform: rotate(65deg);">
                        </div>
                    </div>
                    <div class="score-items">
                        <div class="item text-white" data-action="teguran_1" data-corner="red">
                            <img src="{{ asset('images/teguran-1.png') }}">
                        </div>
                        <div class="item text-white" data-action="teguran_2" data-corner="red">
                            <img src="{{ asset('images/teguran-2.png') }}">
                        </div>
                    </div>
                    <div class="score-items">
                        <div class="item text-white" data-action="peringatan_1" data-corner="red">
                            <img src="{{ asset('images/peringatan-1.png') }}">
                        </div>
                        <div class="item text-white" data-action="peringatan_2" data-corner="red">
                            <img src="{{ asset('images/peringatan-2.png') }}">
                          
                        </div>
                    </div>
                    <div class="judge-container">
                       
                        <!--div class="point-type">PUKULAN</div-->
                        <div class="judges red">
                            
                            <div id="judge-red-1" class="judge" data-type="punch" data-corner="red" data-judge="1">J1</div>
                            <div id="judge-red-2" class="judge" data-type="punch" data-corner="red" data-judge="2">J2</div>
                            <div id="judge-red-3" class="judge" data-type="punch" data-corner="red" data-judge="3">J3</div>
                             
                        </div>

                    </div>
                </div>
            </div>
        </div>
        
    </div>

    

    
    

    
    
</div>   

@endsection
@extends('layouts.app')
@section('content')
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
<input type="hidden" id="match-id" value="{{ $match_id }}">
<div class="match-header">
    <!--div class="match-info" id="tournament-name">-</div>
    <div class="match-details">
        <div class="detail-item" id="match-code">-</div>
        <div class="detail-item" id="stage">-</div>
        <div class="detail-item" id="class-name">-</div>
    </div-->
    <div class="match-item">
        <div class="blue d-flex">
            <div id="blue-name" class="name d-flex w-100 roboto-bold justify-content-center align-items-center">
                -
            </div>
            <div id="blue-score" class="score d-flex text-white roboto-bold justify-content-center align-items-center">
                -
            </div>
        </div>
        
        <div class="red d-flex">
            <div id="red-score" class="score d-flex text-white roboto-bold justify-content-center align-items-center">
                -
            </div>
            <div id="red-name" class="name d-flex w-100 roboto-bold justify-content-center align-items-center">
                - 
            </div>
        
        </div>
    </div>
    
</div>

<div class="container-fluid" style="background-color:#FFF !important; padding:20px; ">
    <div class="row">
        <div class="col-12">
        
      
            <div id="match-tables">
                <table class="table table-striped mb-5">
                    <thead>
                        <tr>
                            <th colspan="9" class="table-title">Rekapitulasi Ronde 1</th>
                        </tr>
                        <tr>
                           
                            <th scope="col" class="blue">Nilai</th>
                            <th scope="col" class="blue">Total</th>
                            <th scope="col" class="bg-secondary">Juri</th>
                            <th scope="col" class="red">Total</th>
                            <th scope="col" class="red">Nilai</th>
                           
            
                        </tr>
                        
                    </thead>
                    <tbody>
                        <tr>
                          
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Juri 1</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                           
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Juri 2</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Juri 3</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                           
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Nilai Sah</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Jatuhan</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Hukuman</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Nilai Final</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                    </tbody>
                </table>

                <table class="table table-dark mb-5">
                    <thead>
                        <tr>
                            <th colspan="9" class="table-title">Rekapitulasi Ronde 2</th>
                        </tr>
                        <tr>
                            <th scope="col" class="blue">Nilai</th>
                            <th scope="col" class="blue">Total</th>
                            <th scope="col" class="bg-secondary">Juri</th>
                            <th scope="col" class="red">Total</th>
                            <th scope="col" class="red">Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Juri 1</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Juri 2</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Juri 3</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Nilai Sah</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Jatuhan</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Hukuman</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Nilai Final</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                    </tbody>
                </table>


                <table class="table table-dark mb-5">
                    <thead>
                        <tr>
                            <th colspan="9" class="table-title">Rekapitulasi Ronde 3</th>
                        </tr>
                        <tr>
                            <th scope="col" class="blue">Nilai</th>
                            <th scope="col" class="blue">Total</th>
                            <th scope="col" class="bg-secondary">Juri</th>
                            <th scope="col" class="red">Total</th>
                            <th scope="col" class="red">Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Juri 1</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Juri 2</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Juri 3</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Nilai Sah</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Jatuhan</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Hukuman</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td>Nilai Final</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                    </tbody>
                </table>

            </div>
        
        </div>
    </div>
</div>
    
    
@endsection
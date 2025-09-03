@extends('layouts.seni_layout')
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

<input type="hidden" id="match-id" value="{{ $match_id }}">

<input type="hidden" id="session-arena" value="{{ session('arena_name') }}">
<input type="hidden" id="session-role" value="{{ ucfirst(session('role')) }}">
<input type="hidden" id="session-juri-number" value="{{ session('juri_number') }}">
<input type="hidden" id="session-role" value="{{ session('role') }}">
<input type="hidden" id="session-tournament" value="{{ session('tournament_name') }}">

<!--div class="fix-match-info" id="tournament-name">-</div>
 <div class="fix-match-detail">
    <div class="detail-item" id="match-code" style="cursor:pointer;">-</div>
    <div class="detail-item" id="age-category">-</div>
    <div class="detail-item" id="gender">-</div>
</div-->
<input type="hidden" id="match-id" value="{{ $match_id }}">
<div class="match-header">
        <div class="match-item">
            <div class="seni-participant-detail">
                <div id="contingent-name" class="contingent-name item">-</div>
                <div id="participant-1" class="participant-1 item">-</div>
                <div id="participant-2" class="participant-2 item">-</div>
                <div id="participant-3" class="participant-3 item">-</div>   
            </div>
        </div>
    </div>

    <div class="container-fluid bg-dark" style="padding:20px; ">
        <div class="row">
            <div class="col-12">
            
        
                <div id="match-tables">
                   <table class="mytable table">
                        <thead>
                            <tr>
                                <th rowspan="2" class="table-title bg-secondary text-center">UNSUR</th>
                                <th colspan="4" class="table-title bg-dark-gradient text-center">JURI</th>
                            </tr>
                            <tr id="unsur-header">
                                <th scope="col" class="bg-secondary text-center">J1</th>
                                <th scope="col" class="bg-secondary text-center">J2</th>
                                <th scope="col" class="bg-secondary text-center">J3</th>
                                <th scope="col" class="bg-secondary text-center">J4</th>
                            </tr>
                        </thead>
                        <tbody id="unsur-tunggal-ganda">
                            <tr id="truth-row">
                                <td class="blue">Kebenaran</td>
                                <td class="blue text-center">-</td>
                                <td class="blue text-center">-</td>
                                <td class="blue text-center">-</td>
                                <td class="blue text-center">-</td>
                            </tr>
                            <tr id="additional-row">
                                <td class="blue">Kemantapan</td>
                                <td class="blue text-center">-</td>
                                <td class="blue text-center">-</td>
                                <td class="blue text-center">-</td>
                                <td class="blue text-center">-</td>
                            </tr>
                        </tbody>

                        <tbody id="unsur-regu-solo" style="display:none;">
                            <tr id="attack-row">
                                <td class="blue">Attack Defense Technique</td>
                                <td class="blue text-center">-</td>
                                <td class="blue text-center">-</td>
                                <td class="blue text-center">-</td>
                                <td class="blue text-center">-</td>
                            </tr>
                            <tr id="firmness-row">
                                <td class="blue">Firmness and Harmony</td>
                                <td class="blue text-center">-</td>
                                <td class="blue text-center">-</td>
                                <td class="blue text-center">-</td>
                                <td class="blue text-center">-</td>
                            </tr>
                            <tr id="soulfulness-row">
                                <td class="blue">Soulfullness</td>
                                <td class="blue text-center">-</td>
                                <td class="blue text-center">-</td>
                                <td class="blue text-center">-</td>
                                <td class="blue text-center">-</td>
                            </tr>
                        </tbody>

                        <tr id="total-row">
                            <td class="blue">Total Nilai</td>
                            <td class="blue text-center">-</td>
                            <td class="blue text-center">-</td>
                            <td class="blue text-center">-</td>
                            <td class="blue text-center">-</td>
                        </tr>

                    </table>


                    


                    

                </div>
            
            </div>
        </div>
        <div class="row">
            <div class="col-12">
            
        
                <div id="match-tables">
                    <table class="mytable table mytable-gabungan">
                        <thead>
                            <tr>
                                <th colspan="4" class="table-title bg-dark-gradient">JURI</th>
                            </tr>
                            <tr>
                                <th scope="col" class="bg-secondary text-center">1</th>
                                <th scope="col" class="bg-secondary text-center">2</th>
                                <th scope="col" class="bg-secondary text-center">3</th>
                                <th scope="col" class="bg-secondary text-center">4</th>
                               
                            </tr>
                            
                        </thead>
                        <tbody>
                            <tr>
                            
                                <td class="blue">-</td>
                                <td class="blue">-</td>
                                <td class="blue">-</td>
                                <td class="red">-</td>
                            </tr>
                
                        </tbody>
                    </table>

                    


                    

                </div>
            
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <div class="equal-height-box gap-2">
                    <div class="w-50 d-flex flex-column">
                        <table class="mytable table h-100">
                            <thead>
                                <tr>
                                
                                    <th scope="col" class="bg-dark-gradient text-center">MEDIAN</th>
                                    <th scope="col" class="bg-dark-gradient text-center">HUKUMAN</th>
                                    <th scope="col" class="bg-dark-gradient text-center">NILAI AKHIR</th>
                            
                                
                    
                                </tr>
                                
                            </thead>
                            <tbody>
                                <tr>
                                
                                    <td class="blue" id="median">-</td>
                                    <td class="blue" id="punishment">-</td>
                                    <td class="blue" id="final-score">-</td>
                                    
                                </tr>
                    
                            </tbody>
                        </table> 
                        <table class="mytable table h-100">
                            <thead>
                                <tr>
                                
                                    <th scope="col" class="bg-dark-gradient text-center">STANDAR DEVIASI</th>
                                    <th scope="col" class="bg-dark-gradient text-center">WAKTU</th>
                                   
                            
                                
                    
                                </tr>
                                
                            </thead>
                            <tbody>
                                <tr>
                                
                                    <td class="blue" id="standar-deviasi">-</td>
                                    <td class="blue" id="time">-</td>
                                  
                                    
                                </tr>
                    
                            </tbody>
                        </table> 
                    </div>
                    <div class="w-50">
                        <table class="mytable table h-100">
                            <thead>
                                <tr>
                                
                                    <th scope="col" class="bg-dark-gradient text-start" style="width:80%;">HUKUMAN</th>
                                    <th scope="col" class="bg-dark-gradient text-start">POTONGAN</th>
                            
                                
                    
                                </tr>
                                
                            </thead>
                            <tbody>
                                <tr>
                                
                                    <td class="blue">Waktu</td>
                                    <td class="blue">-</td>
                                  
                                    
                                </tr>
                                <tr>
                                
                                    <td class="blue">Performa Melebihi Arena 10m x 10m</td>
                                    <td class="blue">-</td>
                                  
                                    
                                </tr>
                                 <tr>
                                
                                    <td class="blue">Menjatuhkan senjata, menyentuh lantai</td>
                                    <td class="blue">-</td>
                                  
                                    
                                </tr>
                                 <tr>
                                
                                    <td class="blue">Pakaian tidak sesuai dengan aturan (Tanjak atau samping terlepas)</td>
                                    <td class="blue">-</td>
                                  
                                    
                                </tr>
                                 <tr>
                                
                                    <td class="blue">Atlet bertahan pada satu gerakan selama lebih dari 5 detik</td>
                                    <td class="blue">-</td>
                                  
                                    
                                </tr>
                    
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    
@endsection
@extends('layouts.app')
@section('content')
<input type="hidden" id="match-id" value="{{ $match_id }}">
<div class="match-header">
    <div class="match-info" id="tournament-name">-</div>
    <div class="match-details">
        <div class="detail-item" id="match-code">-</div>
        <div class="detail-item" id="stage">-</div>
        <div class="detail-item" id="class-name">-</div>
    </div>
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

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
        <div class="content">
      
            <div id="match-tables">
                <table class="table table-dark mb-5">
                    <thead>
                        <tr>
                            <th colspan="9" class="table-title">Rekapitulasi Ronde 1</th>
                        </tr>
                        <tr>
                            <th scope="col">Juri</th>
                            <th scope="col" class="blue">Nilai</th>
                            <th scope="col" class="blue">Total</th>
                            <th scope="col" class="red">Nilai</th>
                            <th scope="col" class="red">Total</th>
            
                        </tr>
                        
                    </thead>
                    <tbody>
                        <tr>
                            <td>Juri 1</td>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td>Juri 2</td>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td>Juri 3</td>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td>Nilai Sah</td>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td>Jatuhan</td>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td>Hukuman</td>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td>Nilai Final</td>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
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
                            <th scope="col">Juri</th>
                            <th scope="col" class="blue">Nilai</th>
                            <th scope="col" class="blue">Total</th>
                            <th scope="col" class="red">Nilai</th>
                            <th scope="col" class="red">Total</th>
            
                        </tr>
                        
                    </thead>
                    <tbody>
                        <tr>
                            <td>Juri 1</td>
                            <td class="blue">1, 2, 2, 3, 2, 2</td>
                            <td class="blue">10</td>
                            <td class="blue">1, 2, 2, 3, 2, 2</td>
                            <td class="blue">10</td>
                        </tr>
                        <tr>
                            <td>Juri 2</td>
                            <td class="blue">1, 2, 2, 3, 2, 2</td>
                            <td class="blue">10</td>
                            <td class="blue">1, 2, 2, 3, 2, 2</td>
                            <td class="blue">10</td>
                        </tr>
                        <tr>
                            <td>Juri 3</td>
                            <td class="blue">1, 2, 2, 3, 2, 2</td>
                            <td class="blue">10</td>
                            <td class="blue">1, 2, 2, 3, 2, 2</td>
                            <td class="blue">10</td>
                        </tr>
                        <tr>
                            <td>Nilai Sah</td>
                            <td class="blue">1, 2, 2, 3, 2, 2</td>
                            <td class="blue">10</td>
                            <td class="blue">1, 2, 2, 3, 2, 2</td>
                            <td class="blue">10</td>
                        </tr>
                        <tr>
                            <td>Jatuhan</td>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td>Hukuman</td>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td>Nilai Final</td>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
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
                            <th scope="col">Juri</th>
                            <th scope="col" class="blue">Nilai</th>
                            <th scope="col" class="blue">Total</th>
                            <th scope="col" class="red">Nilai</th>
                            <th scope="col" class="red">Total</th>
            
                        </tr>
                        
                    </thead>
                    <tbody>
                        <tr>
                            <td>Juri 1</td>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td>Juri 2</td>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td>Juri 3</td>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td>Nilai Sah</td>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td>Jatuhan</td>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td>Hukuman</td>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                        <tr>
                            <td>Nilai Final</td>
                            <td class="blue">-</td>
                            <td class="blue">-</td>
                            <td class="red">-</td>
                            <td class="red">-</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </div>
</div>
    
    
@endsection
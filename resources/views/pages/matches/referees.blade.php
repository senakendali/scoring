@extends('layouts.app')
@section('content')
    <input type="hidden" id="match-id" value="{{ $match_id }}">
    <input type="hidden" id="round-id" value="{{ $round_id }}">

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
            <div id="current-round" class="round d-flex justify-content-center align-items-center roboto-bold">
                -
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
   
    <div class="arena-container">
        <div class="blue">
            <div class="additional-point">
                <div class="score-items">
                    <div class="item text-white" data-action="binaan_1" data-point="0" data-corner="blue">Binaan 1</div>
                    <div class="item text-white" data-action="binaan_2" data-point="0" data-corner="blue">Binaan 2</div>
                </div>
                <div class="score-items">
                    <div class="item text-white" data-action="teguran_1" data-point="-1" data-corner="blue">Teguran 1</div>
                    <div class="item text-white" data-action="teguran_2" data-point="-2" data-corner="blue">Teguran 2</div>
                </div>
                <div class="score-items">
                    <div class="item text-white" data-action="peringatan_1" data-point="-5" data-corner="blue">Peringatan 1</div>
                    <div class="item text-white" data-action="peringatan_2" data-point="-10" data-corner="blue">Peringatan 2</div>
                </div>
                <div class="score-items">
                    <div class="item text-white" data-action="jatuhan" data-point="3" data-corner="blue">Jatuhan</div>
                    <div class="item text-white" data-action="verifikasi_jatuhan" data-point="0" data-corner="blue">Verifikasi Jatuhan</div>
                </div>
            </div>
        </div>

        <div class="red">
            <div class="additional-point">
                <div class="score-items">
                    <div class="item text-white" data-action="binaan_1" data-point="0" data-corner="red">Binaan 1</div>
                    <div class="item text-white" data-action="binaan_2" data-point="0" data-corner="red">Binaan 2</div>
                </div>
                <div class="score-items">
                    <div class="item text-white" data-action="teguran_1" data-point="-1" data-corner="red">Teguran 1</div>
                    <div class="item text-white" data-action="teguran_2" data-point="-2" data-corner="red">Teguran 2</div>
                </div>
                <div class="score-items">
                    <div class="item text-white" data-action="peringatan_1" data-point="-5" data-corner="red">Peringatan 1</div>
                    <div class="item text-white" data-action="peringatan_2" data-point="-10" data-corner="red">Peringatan 2</div>
                </div>
                <div class="score-items">
                    <div class="item text-white" data-action="jatuhan" data-point="3" data-corner="red">Jatuhan</div>
                    <div class="item text-white" data-action="verifikasi_jatuhan" data-point="0" data-corner="red">Verifikasi Jatuhan</div>
                </div>
            </div>
        </div>
    </div>

   

    

    
    

    
    
    
@endsection
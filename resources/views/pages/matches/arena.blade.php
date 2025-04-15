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
            <div class="additional-score">
                <div class="score-items">
                    <div class="item text-white" data-action="binaan_1" data-corner="blue">Binaan 1</div>
                    <div class="item text-white" data-action="binaan_2" data-corner="blue">Binaan 2</div>
                </div>
                <div class="score-items">
                    <div class="item text-white" data-action="teguran_1" data-corner="blue">Teguran 1</div>
                    <div class="item text-white" data-action="teguran_2" data-corner="blue">Teguran 2</div>
                </div>
                <div class="score-items">
                    <div class="item text-white"  data-action="peringatan_1" data-corner="blue">Peringatan 1</div>
                    <div class="item text-white"  data-action="peringatan_2" data-corner="blue">Peringatan 2</div>
                </div>
            </div>
            <div class="score">
                -
            </div>
        </div>
        <div class="red">
            <div class="score">
                -
            </div>  
            <div class="additional-score">
                <div class="score-items">
                    <div class="item text-white" data-action="binaan_1" data-corner="red">Binaan 1</div>
                    <div class="item text-white" data-action="binaan_2" data-corner="red">Binaan 2</div>
                </div>
                <div class="score-items">
                    <div class="item text-white" data-action="teguran_1" data-corner="red">Teguran 1</div>
                    <div class="item text-white" data-action="teguran_2" data-corner="red">Teguran 2</div>
                </div>
                <div class="score-items">
                    <div class="item text-white" data-action="peringatan_1" data-corner="red">Peringatan 1</div>
                    <div class="item text-white" data-action="peringatan_2" data-corner="red">Peringatan 2</div>
                </div>
            </div>
        </div>
    </div>
    <!-- 🦵 Juri TENDANGAN -->
    <div class="judge-container">
        <div class="judges judges-kick blue">
            <div class="judge" data-type="kick" data-corner="blue" data-judge="1">J1</div>
            <div class="judge" data-type="kick" data-corner="blue" data-judge="2">J2</div>
            <div class="judge" data-type="kick" data-corner="blue" data-judge="3">J3</div>
        </div>
        <div class="point-type">TENDANGAN</div>
        <div class="judges judges-kick red">
            <div class="judge" data-type="kick" data-corner="red" data-judge="1">J1</div>
            <div class="judge" data-type="kick" data-corner="red" data-judge="2">J2</div>
            <div class="judge" data-type="kick" data-corner="red" data-judge="3">J3</div>
        </div>
    </div>
    <!-- 💥 Juri PUKULAN -->
    <div class="judge-container">
        <div class="judges judges-punch blue">
            <div class="judge" data-type="punch" data-corner="blue" data-judge="1">J1</div>
            <div class="judge" data-type="punch" data-corner="blue" data-judge="2">J2</div>
            <div class="judge" data-type="punch" data-corner="blue" data-judge="3">J3</div>
        </div>
        <div class="point-type">PUKULAN</div>
        <div class="judges judges-punch red">
            <div class="judge" data-type="punch" data-corner="red" data-judge="1">J1</div>
            <div class="judge" data-type="punch" data-corner="red" data-judge="2">J2</div>
            <div class="judge" data-type="punch" data-corner="red" data-judge="3">J3</div>
        </div>
    </div>

    

    
    

    
    
    
@endsection
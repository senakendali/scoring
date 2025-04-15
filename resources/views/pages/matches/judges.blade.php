@extends('layouts.app')
@section('content')
    
    <div class="match-header">
        <div class="match-info">
            TOURNAMENT NAME
        </div>
        <div class="match-details">
            <div class="detail-item">
                MATCH 1
            </div>
            <div class="detail-item">
                1/8 Final
            </div>
            <div class="detail-item">
               USIA DINI - C
            </div>
        </div>
        <div class="match-item">
            <div class="blue d-flex">
                <div class="name d-flex w-100 roboto-bold justify-content-center align-items-center">
                    SENA KENDALI
                </div>
                <div class="score d-flex text-white roboto-bold justify-content-center align-items-center">
                    10
                </div>
            </div>
            <div class="round d-flex justify-content-center align-items-center roboto-bold">
                ROUND 1
            </div>
            <div class="red d-flex">
                <div class="score d-flex text-white roboto-bold justify-content-center align-items-center">
                    5
                </div>
                <div class="name d-flex w-100 roboto-bold justify-content-center align-items-center">
                    BUDI 
                </div>
            
            </div>
        </div>
        
    </div>
   <div class="judges-display">
        <div class="blue-point-list">
            <div class="point-item">
                1
            </div>
            <div class="point-item">
                2
            </div>
            <div class="point-item">
                3
            </div>
        </div>
        <div class="red-point-list">
            <div class="point-item">
                1
            </div>
            <div class="point-item">
                2
            </div>
            <div class="point-item">
                3
            </div>
        </div>
   </div>
   <div class="judges-button">
        <div class="blue-button">
            <button class="button-item">
                TENDANGAN
            </button>
            <button class="button-item">
                PUKULAN
            </button>   
        </div> 
        <div class="red-button">
            <button class="button-item">
                TENDANGAN
            </button>
            <button class="button-item">
                PUKULAN
            </button>   
        </div>   
   </div>
@endsection
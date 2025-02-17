@extends('layouts.app')
@section('content')
    <div class="match-header">
        <div class="blue d-flex">
            <div class="name d-flex w-100 text-white roboto-bold justify-content-center align-items-center">
                SENA KENDALI
            </div>
            <div class="score d-flex text-white roboto-bold justify-content-center align-items-center">
                10
            </div>
        </div>
        <div class="round d-flex justify-content-center align-items-center text-white roboto-bold">
            ROUND 1
        </div>
        <div class="red d-flex">
            <div class="score d-flex text-white roboto-bold justify-content-center align-items-center">
                5
            </div>
            <div class="name d-flex w-100  text-white roboto-bold justify-content-center align-items-center">
                BUDI 
            </div>
           
        </div>
    </div>
    <div class="match-content">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-6">
                    <div class="match-panel text-center">
                        <div class="panel-header">
                            <div class="round d-flex justify-content-center align-items-center active">
                                ROUND 1
                            </div>
                            <div class="round d-flex justify-content-center align-items-center">
                                ROUND 2
                            </div>
                            <div class="round d-flex justify-content-center align-items-center">
                                ROUND 3
                            </div>
                        </div>
                        <div class="panel-content d-flex justify-content-center align-items-center">
                            <div class="timer roboto-bold">
                                00:00
                            </div>                          
                        </div>
                        <div class="panel-footer">
                            <div class="d-flex">
                                <button class="reset d-flex justify-content-center align-items-center text-white roboto-bold border-0">
                                    RESET
                                </button>
                                <button class="start d-flex justify-content-center align-items-center text-white roboto-bold border-0">
                                    START
                                </button>
                            </div>
                            <div class="d-flex">
                                <button class="stop d-flex justify-content-center align-items-center text-white roboto-bold border-0">
                                    END MATCH
                                </button>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="match-footer d-flex justify-content-end">
        
    </div>
    
    
@endsection
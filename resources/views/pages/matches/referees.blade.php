@extends('layouts.tanding_layout')

@section('content')
    <!-- Modal Result Verifikasi -->
    <div class="modal fade" id="verificationResultModal" tabindex="-1" aria-labelledby="verificationResultModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="verificationResultModalLabel">Hasil Verifikasi</h5>
                </div>
                <div class="modal-body text-center">
                    <div id="verificationResultContent"></div>
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
                    <p id="waitingVerificationMessage" class="mb-0">Menunggu hasil verifikasi...</p>
                </div>
                <div class="progress" style="height: 10px;">
                    <div id="waitingVerificationProgress"
                        class="progress-bar bg-info progress-bar-striped progress-bar-animated"
                        role="progressbar" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Daftar Partai -->
    <div class="modal fade" id="matchListModal" tabindex="-1" aria-labelledby="matchListModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-sm">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="matchListModalLabel">Daftar Partai</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-2">
                    <ul class="list-group list-group-flush" id="match-list"></ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Pilih Round -->
    <div class="modal fade" id="roundPickerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title">Pilih Round</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-0">
                    <div id="roundList" class="list-group list-group-flush"></div>
                </div>

                <div class="modal-footer">
                    <small class="text-secondary">
                        Pilih round berapa yang akan anda lihat
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="judges-container d-flex flex-column" style="height:calc(100vh - 100px);">
        <input type="hidden" id="match-id" value="{{ $match_id }}">
        <input type="hidden" id="round-id" value="{{ $round_id }}">

        <div class="match-header">
            <div class="match-item judges-page" style="font-size: 12px;">
                <div class="blue d-flex">
                    <div id="blue-name" class="name d-flex flex-column w-100 roboto-bold justify-content-center align-items-center">-</div>
                </div>

                <div id="current-round"
                    class="round d-flex justify-content-center align-items-center roboto-bold"
                    style="cursor:pointer;">
                    -
                </div>

                <div class="red d-flex">
                    <div id="red-name" class="name d-flex flex-column w-100 roboto-bold justify-content-center align-items-center">-</div>
                </div>
            </div>
        </div>

        <div class="main-container flex-grow-1 overflow-auto">
            <div class="arena-container">
                <!-- BLUE -->
                <div class="blue">
                    <div class="additional-point">
                        <div class="score-items">
                            <div class="item text-white d-flex flex-column" data-action="binaan_1" data-point="0" data-corner="blue">
                                <div><img src="{{ asset('images/binaan-1.png') }}"></div>
                                <div>Binaan 1</div>
                            </div>
                            <div class="item text-white d-flex flex-column" data-action="binaan_2" data-point="0" data-corner="blue">
                                <div><img src="{{ asset('images/binaan-2.png') }}" style="transform: rotate(65deg);"></div>
                                <div>Binaan 2</div>
                            </div>
                        </div>

                        <div class="score-items">
                            <div class="item text-white d-flex flex-column" data-action="teguran_1" data-point="-1" data-corner="blue">
                                <div><img src="{{ asset('images/teguran-1.png') }}"></div>
                                <div>Teguran 1</div>
                            </div>
                            <div class="item text-white d-flex flex-column" data-action="teguran_2" data-point="-2" data-corner="blue">
                                <div><img src="{{ asset('images/teguran-2.png') }}"></div>
                                <div>Teguran 2</div>
                            </div>
                        </div>

                        <div class="score-items">
                            <div class="item text-white d-flex flex-column" data-action="peringatan_1" data-point="-5" data-corner="blue">
                                <div><img src="{{ asset('images/peringatan-1.png') }}"></div>
                                <div>Peringatan 1</div>
                            </div>
                            <div class="item text-white d-flex flex-column" data-action="peringatan_2" data-point="-10" data-corner="blue">
                                <div><img src="{{ asset('images/peringatan-2.png') }}"></div>
                                <div>Peringatan 2</div>
                            </div>
                        </div>

                        <div class="score-items d-flex gap-2">
                            <div class="drop text-white" data-action="jatuhan" data-point="3" data-corner="blue">Jatuhan</div>
                            <div class="drop text-white" data-action="hapus-jatuhan" data-point="3" data-corner="blue">Hapus Jatuhan</div>
                        </div>
                    </div>
                </div>

                <!-- CENTER -->
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

                <!-- RED -->
                <div class="red">
                    <div class="additional-point">
                        <div class="score-items">
                            <div class="item text-white d-flex flex-column" data-action="binaan_1" data-point="0" data-corner="red">
                                <div><img src="{{ asset('images/binaan-1.png') }}"></div>
                                <div>Binaan 1</div>
                            </div>
                            <div class="item text-white d-flex flex-column" data-action="binaan_2" data-point="0" data-corner="red">
                                <div><img src="{{ asset('images/binaan-2.png') }}" style="transform: rotate(65deg);"></div>
                                <div>Binaan 2</div>
                            </div>
                        </div>

                        <div class="score-items">
                            <div class="item text-white d-flex flex-column" data-action="teguran_1" data-point="-1" data-corner="red">
                                <div><img src="{{ asset('images/teguran-1.png') }}"></div>
                                <div>Teguran 1</div>
                            </div>
                            <div class="item text-white d-flex flex-column" data-action="teguran_2" data-point="-2" data-corner="red">
                                <div><img src="{{ asset('images/teguran-2.png') }}"></div>
                                <div>Teguran 2</div>
                            </div>
                        </div>

                        <div class="score-items">
                            <div class="item text-white d-flex flex-column" data-action="peringatan_1" data-point="-5" data-corner="red">
                                <div><img src="{{ asset('images/peringatan-1.png') }}"></div>
                                <div>Peringatan 1</div>
                            </div>
                            <div class="item text-white d-flex flex-column" data-action="peringatan_2" data-point="-10" data-corner="red">
                                <div><img src="{{ asset('images/peringatan-2.png') }}"></div>
                                <div>Peringatan 2</div>
                            </div>
                        </div>

                        <div class="score-items d-flex gap-2">
                            <div class="drop text-white" data-action="jatuhan" data-point="3" data-corner="red">Jatuhan</div>
                            <div class="drop text-white" data-action="hapus-jatuhan" data-point="3" data-corner="red">Hapus Jatuhan</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

$(document).ready(function () {
    const url = window.location.origin;
    const matchId = window.location.pathname.split("/").pop();
    const timerEl = $("#timer");
    
    let duration = 600;
    
    let interval = null;
    let roundId = null;
    let currentRoundNumber = 1;
    let totalRounds = 3;
    let allRounds = [];
    let elapsed = 0;
    let isPaused = false;
    


    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Setup Pusher
    Pusher.logToConsole = true;
    const host = window.location.hostname;
    const pusher = new Pusher('reverb', {
        wsHost: host,
        wsPort: 6001,
        forceTLS: false,
        encrypted: false,
        enabledTransports: ['ws'],
        disableStats: true,
    });

    // Subscribe ke channel dan event
    const channel = pusher.subscribe(`match.${matchId}`);

    channel.bind_global(function (event, data) {
        console.log("🌍 Global Event:", event, data);
    });

    channel.bind('score.updated', function (data) {
        console.log("🎯 Score updated:", data); // HARUS MUNCUL DULU DI CONSOLE
    
        $("#blue-score").text(data.blueScore).addClass("flash");
        setTimeout(() => $("#blue-score").removeClass("flash"), 500);
    
        $("#red-score").text(data.redScore).addClass("flash");
        setTimeout(() => $("#red-score").removeClass("flash"), 500);
    });


    function setButtonLoading(button, isLoading) {
        if (isLoading) {
            button.data("original-text", button.html());
            button.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
            button.prop("disabled", true);
        } else {
            button.html(button.data("original-text"));
            button.prop("disabled", false);
        }
    }

    function fetchMatch() {
        $(".loader-bar").show();
        $.get(`${url}/api/local-matches/seni/${matchId}`, function (data) {
             if(data.is_display_timer != 0){
               
                $("#timer").show();
            }else{
                $("#display-timer").css('height', '0');
                $("#timer").hide();
            }
            
            $("#match-id").val(data.id);
            $("#tournament-name").text(data.tournament_name);
            $("#match-code").text(data.arena_name + " Partai " + data.match_order);
            $("#class-name").text(data.category);
            $("#age-category").text(data.age_category);
            $("#gender").text(data.category + "  " + (data.gender === 'male' ? 'PUTRA' : 'PUTRI'));
            $("#contingent-name").text(data.contingent);

            //isPaused = data.status === 'paused';

            // 🔥 Reset semua dulu
            $("#participant-1").text('-').hide();
            $("#participant-2").text('-').hide();
            $("#participant-3").text('-').hide();

            // ✅ Tampilkan peserta sesuai match_type
            if (data.match_type === 'seni_tunggal') {
                $("#participant-1").text(data.team_members[0] || '-').show();
            } else if (data.match_type === 'seni_ganda') {
                $("#participant-1").text(data.team_members[0] || '-').show();
                $("#participant-2").text(data.team_members[1] || '-').show();
            } else if (data.match_type === 'seni_regu') {
                $("#participant-1").text(data.team_members[0] || '-').show();
                $("#participant-2").text(data.team_members[1] || '-').show();
                $("#participant-3").text(data.team_members[2] || '-').show();
            }

            $(".loader-bar").hide();
        });
    }


    function fetchAndStartTimer() {
        // matchId disiapin dari global/hidden input
        if (!matchId) return;

        $.get(`${url}/api/local-matches/seni/${matchId}`, function (data) {
            const status = data.status;
            
            elapsed = Math.max(0, Math.floor(parseFloat(data.elapsed) || 0));

            if (status === 'ongoing') {
                runTimer();
            } else if (status === 'pending') {
                stopTimer();
                timerEl.text("00:00");
            } else if (status === 'finished') {
                stopTimer();
                timerEl.text("SELESAI");
            }
        });
    }

    function runTimer() {
        clearInterval(interval);
        const maxDuration = 600; // 3 menit batas maksimal

        function updateTimer() {
            timerEl.text(formatTime(elapsed));

            if (elapsed >= maxDuration) {
                clearInterval(interval);
                // ⏱️ Auto finish jika lewat 3 menit

                $.ajax({
                    url: `${url}/api/local-seni-matches/${matchId}/finish`,
                    method: 'PATCH',
                    success: function () {
                        console.log("⏱️ Pertandingan seni selesai otomatis (max 3 menit)");
                        $(".end-match").addClass("d-none");
                        $(".next-match").removeClass("d-none");
                    },
                    error: function (err) {
                        console.error("❌ Gagal finish pertandingan otomatis:", err);
                    }
                });

            }

            elapsed++;
        }

        updateTimer();
        interval = setInterval(updateTimer, 1000);
    }

    function stopTimer() {
        clearInterval(interval);
        interval = null;
        console.log("⏹️ Timer dihentikan (diskualifikasi)");
    }

    function cleanupModals() {
        $(".modal-backdrop").remove();
        $("body").removeClass("modal-open").removeAttr("style");

        $(".modal.show").each(function () {
            const modalInstance = bootstrap.Modal.getInstance(this);
            if (modalInstance) modalInstance.hide();
        });
    }

    function formatTime(seconds) {
        const min = Math.floor(seconds / 60).toString().padStart(2, '0');
        const sec = (seconds % 60).toString().padStart(2, '0');
        return `${min}:${sec}`;
    }


   let isRunning = false;

    $(document).on("click", ".start", function () {
        const matchId = $("#match-id").val();
        if (!matchId) return;

        const btn = $(this);
        btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.post(`${url}/api/matches/seni/${matchId}/start`, {
            duration: 600 // ⏱️ 10 menit
        }, function () {
            setTimeout(fetchAndStartTimer, 500);

            // Ganti jadi STOP
            btn
                .removeClass("start btn-success")
                .addClass("stop btn-danger")
                .html('<i class="bi bi-stop-fill me-1"></i> STOP');
        }).fail(() => {
            btn.html("START");
        }).always(() => {
            btn.prop("disabled", false);
        });
    });



    $(document).on("click", ".stop", function () {
        const matchId = $("#match-id").val();
        const btn = $(this);
        if (!matchId) return;

        btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: `${url}/api/local-seni-matches/${matchId}/finish`,
            method: 'PATCH',
            success: function () {
                stopTimer();
                timerEl.text("SELESAI");

                // Balik jadi START
                btn
                    .removeClass("stop btn-danger")
                    .addClass("start btn-success")
                    .html('<i class="bi bi-play-fill me-1"></i> START');

                $(".end-match").addClass("d-none");
                $(".next-match").removeClass("d-none");
            },
            complete: function () {
                btn.prop("disabled", false);
            }
        });
    });






    

    

   /*$(".pause").on("click", function () {
        const matchId = $("#match-id").val();
        if (!matchId) return;

        const btn = $(this);
        setButtonLoading(btn, true);

        if (isPaused) {
            // 🔄 RESUME
            $.post(`${url}/api/matches/seni/${matchId}/resume`, function (res) {
                isPaused = false;
                elapsed = typeof res.elapsed === 'number' ? res.elapsed : 0;
                runTimer();
            }).fail(() => {
                alert("❌ Gagal melanjutkan pertandingan.");
            }).always(() => {
                setButtonLoading(btn, false);
                btn.text("PAUSE"); // ✅ pindah ke bawah setelah loading selesai
            });
        } else {
            // ⏸️ PAUSE
            stopTimer();
            $.post(`${url}/api/matches/seni/${matchId}/pause`, function () {
                isPaused = true;
            }).fail(() => {
                alert("❌ Gagal mem-pause pertandingan.");
            }).always(() => {
                setButtonLoading(btn, false);
                btn.text("RESUME"); // ✅ pindah ke bawah setelah loading selesai
            });
        }
    });*/

    $(document).on("click touchstart", ".pause", function (e) {
        e.preventDefault();
        e.stopPropagation();

        const matchId = $("#match-id").val();
        if (!matchId) {
            console.warn("⛔ matchId belum ada");
            return;
        }

        const btn = $(this);
        setButtonLoading(btn, true);

        if (isPaused) {
            // 🔄 RESUME
            $.post(`${url}/api/matches/seni/${matchId}/resume`)
                .done((res) => {
                    isPaused = false;
                    elapsed = typeof res.elapsed === 'number' ? res.elapsed : 0;
                    runTimer();
                })
                .fail(() => {
                    alert("❌ Gagal melanjutkan pertandingan.");
                })
                .always(() => {
                    setButtonLoading(btn, false);
                    btn.text("PAUSE");
                });
        } else {
            // ⏸️ PAUSE
            stopTimer();
            $.post(`${url}/api/matches/seni/${matchId}/pause`)
                .done(() => {
                    isPaused = true;
                })
                .fail(() => {
                    alert("❌ Gagal mem-pause pertandingan.");
                })
                .always(() => {
                    setButtonLoading(btn, false);
                    btn.text("RESUME");
                });
        }
    });




    

    $(".reset").on("click", function () {
        const matchId = $("#match-id").val(); // Ambil dari hidden input atau variabel
    
        if (!matchId) return;

        const modal = new bootstrap.Modal(document.getElementById('confirmResetModal'));
        modal.show();
    
        $("#confirm-reset-btn").off("click").on("click", function () {
            const btn = $(".reset");
            setButtonLoading(btn, true);
            modal.hide();
    
            $.post(`${url}/api/matches/seni/${matchId}/reset`, function () {
                stopTimer();
                timerEl.text("00:00");

                const $startStopBtn = $(".panel-footer .stop, .panel-footer .start");
                $startStopBtn
                    .removeClass("stop btn-danger")
                    .addClass("start btn-success")
                    .html('<i class="bi bi-play-fill me-1"></i> START');
                
                $(".end-match").show();
                $(".next-match").hide();
                //timerEl.text(formatTime(duration));
            }).always(() => setButtonLoading(btn, false));
        });
    });

    

   $(".end-match").off("click").on("click", function () {
        const confirmModalEl = document.getElementById('confirmEndModal');
        const endedModalEl = document.getElementById('endedMatchModal');

        const confirmModal = new bootstrap.Modal(confirmModalEl);
        const endedModal = new bootstrap.Modal(endedModalEl);

        confirmModal.show();

        $("#confirm-end-btn").off("click").on("click", function () {
            const matchId = $("#match-id").val();
            const reason = "Diskualifikasi";

            const instance = bootstrap.Modal.getInstance(confirmModalEl);
            if (instance) instance.hide();

            $.ajax({
                url: `/api/local-seni-matches/${matchId}/disqualify`,
                method: 'PATCH',
                data: {
                    reason: reason
                },
                success: function (res) {
                    console.log("✅ Peserta didiskualifikasi:", res);

                    stopTimer();
                    elapsed = 0;
                    timerEl.text("00:00");

                    $(".end-match").addClass("d-none");
                    $(".next-match").removeClass("d-none");

                    // Tampilkan modal "Pertandingan Dihentikan"
                    $(confirmModalEl).on('hidden.bs.modal', function () {
                        endedModal.show();

                        // ⏱️ Cleanup backdrop supaya tombol bisa diklik
                        setTimeout(() => {
                            cleanupModals(); // prevent backdrop tertinggal
                        }, 300);
                    });
                },
                error: function (err) {
                    console.error("❌ Gagal diskualifikasi:", err);
                    alert("Gagal mendiskualifikasi peserta!");
                }
            });
        });
    });


    
    
    
    
    
    // Handle alasan lainnya
    $("#win_reason").on("change", function () {
        if ($(this).val() === "other") {
            $("#other_reason_box").removeClass("d-none");
        } else {
            $("#other_reason_box").addClass("d-none");
        }
    });
    
    // Submit hasil akhir pertandingan
    $("#confirm-winner-btn").on("click", function () {
        const btn = $(this);
        setButtonLoading(btn, true);
    
        const winner = $("#winner").val();
        const reason = $("#win_reason").val();
        const otherReason = $("#other_reason").val();
        const finalReason = reason === "other" ? otherReason : reason;
    
        if (!winner || !finalReason) {
            alert("Pilih pemenang dan alasan kemenangan.");
            setButtonLoading(btn, false);
            return;
        }
    
        $.post(`${url}/api/local-matches/${matchId}/end-match`, {
            winner: winner,
            reason: finalReason
        }, function () {
            stopTimer();
    
            // PATCH: Hapus semua modal/overlay dulu
            cleanupModals();
    
            // Baru tampilkan modal hasil
            const endedModal = new bootstrap.Modal(document.getElementById('endedMatchModal'));
            endedModal.show();

            // PATCH: kalau modal endedMatchModal di close, bersihin semua overlay
            endedModal._element.addEventListener('hidden.bs.modal', function () {
                cleanupModals();
            });

    
            $(".end-match").addClass("d-none");
            $(".next-match").removeClass("d-none");
        }).always(() => setButtonLoading(btn, false));
    });
    
    
    
    
    // Toggle field alasan lainnya
    $("#win_reason").on("change", function () {
        if ($(this).val() === "other") {
            $("#other_reason_box").removeClass("d-none");
        } else {
            $("#other_reason_box").addClass("d-none");
        }
    });
    
    // Submit hasil pertandingan
    $("#confirm-winner-btn").on("click", function () {
        const btn = $(this);
        setButtonLoading(btn, true);
    
        const winner = $("#winner").val();
        const reason = $("#win_reason").val();
        const otherReason = $("#other_reason").val();
    
        if (!winner || !reason) {
            alert("Pilih pemenang dan alasan kemenangan.");
            setButtonLoading(btn, false);
            return;
        }
    
        const finalReason = reason === "other" ? otherReason : reason;
    
        $.post(`${url}/api/local-matches/${matchId}/end-match`, {
            winner: winner,
            reason: finalReason
        }, function () {
            stopTimer();
    
            const winnerModal = bootstrap.Modal.getInstance(document.getElementById('selectWinnerModal'));
            winnerModal.hide();
    
            const endedModal = new bootstrap.Modal(document.getElementById('endedMatchModal'));
            endedModal.show();
    
            $(".end-match").addClass("d-none");
            $(".next-match").removeClass("d-none");
        }).always(() => setButtonLoading(btn, false));
    });
    
    

    $(".next-match").on("click", function () {
        const $btn = $(this);
        const originalHtml = $btn.html();

        // Tampilkan spinner dan disable tombol
        $btn.prop("disabled", true).html(`
            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            Loading...
        `);

        $.post(`/api/local-seni-matches/${matchId}/next`, function (res) {
            if (res.new_match_id) {
                window.location.href = `/matches/seni/${res.new_match_id}`;
            } else {
                alert("Tidak ada pertandingan berikutnya.");
            }
        }).fail(function (xhr) {
            $("#nextMatchModalBody").text("Tidak ada pertandingan berikutnya. Semua pertandingan telah selesai.");
            const modal = new bootstrap.Modal(document.getElementById('nextMatchModalInfo'));
            modal.show();
            console.error("❌ Gagal ganti match:", xhr.responseJSON?.message || xhr.statusText);
        }).always(function () {
            // Kembalikan tombol ke semula
            $btn.prop("disabled", false).html('Redirecting to Next Match');
        });
    });

    

    let lastBlue = 0;
    let lastRed = 0;

    function cleanupModals() {
        $(".modal-backdrop").remove();
        $("body").removeClass("modal-open").removeAttr("style");
    
        // Tutup semua modal yang mungkin masih terbuka
        $(".modal.show").each(function () {
            const modalInstance = bootstrap.Modal.getInstance(this);
            if (modalInstance) {
                modalInstance.hide();
            }
        });
    }
    
    
    

    


    fetchMatch();
});

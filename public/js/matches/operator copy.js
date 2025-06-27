$(document).ready(function () {
    const url = window.location.origin;
    const matchId = window.location.pathname.split("/").pop();
    const timerEl = $("#timer");
    
    let duration = 180;
    
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
        $.get(`${url}/api/local-matches/${matchId}`, function (data) {
           
            if(data.is_display_timer != 0){
                $(".timer").css('color', '#FFFFFF');
                $("#timer").show();
            }else{
                $("#display-timer").css('height', '20px');
                $("#timer").hide();
            }
           
            $("#tournament-name").text(data.tournament_name);
            $("#match-code").text(data.arena_name + " Partai " + data.match_number);
            $("#class-name").text(data.class_name);
            $("#match-stage").text("-");
            $("#round-duration").val(300);

            $("#blue-name").html(`
                ${data.blue.name}<br>
                <small>${data.blue.contingent}</small>
            `);
            //$("#blue-score").text(data.blue.score);
            $("#red-name").html(`
                ${data.red.name}<br>
                <small>${data.red.contingent}</small>
            `);
           // $("#red-score").text(data.red.score);

           const maxRound = Math.max(...data.rounds.map(r => r.round_number));
            const roundLabels = getRoundLabels(maxRound);

            $("#stage").text(data.round_label);
   

            allRounds = data.rounds;
            totalRounds = data.total_rounds;

            const active = data.rounds.find(r => r.status === 'in_progress') || data.rounds.find(r => r.status === 'not_started') || data.rounds[0];
            if (active) {
                roundId = active.id;
                currentRoundNumber = active.round_number;
                $("#round-id").val(roundId);
                $("#current-round").text(`ROUND ${currentRoundNumber}`);
                $(".panel-header .round").removeClass("active");
                $(".panel-header .round").eq(currentRoundNumber - 1).addClass("active");
                fetchAndStartTimer();
            }

            setWinnerOptions(data); 
            
            $(".loader-bar").hide();
        });
    }

    function fetchAndStartTimer() {
        if (!roundId) return;
        $.get(`${url}/api/local-match-rounds/${roundId}`, function (data) {
            const status = data.status;
            elapsed = Math.max(0, Math.floor(parseFloat(data.elapsed) || 0));

            if (status === 'in_progress') {
                runTimer();
            } else if (status === 'not_started') {
                stopTimer();
                timerEl.text("03:00");
            } else if (status === 'finished') {
                stopTimer();
                timerEl.text("00:00");
            }
        });
    }

    function runTimer() {
        clearInterval(interval);

        function updateTimer() {
            const remaining = duration - elapsed;
            timerEl.text(formatTime(Math.max(0, remaining)));

            if (remaining <= 0) {
                clearInterval(interval);

                $.post(`${url}/api/local-match-rounds/${roundId}/finish`, function () {

                    if (currentRoundNumber < totalRounds) {
                        // 🔥 Masih ada ronde berikutnya, tampilkan modal Next Round
                        $("#modal-round-number").text(currentRoundNumber);
                        const modal = new bootstrap.Modal(document.getElementById('nextRoundModal'));
                        modal.show();
                    
                        $("#confirm-next-round").off("click").on("click", function () {
                            modal.hide();
                            moveToNextRound();
                        });
                    
                    } else {
                        // 🔥 Ini ronde terakhir bro, langsung tampilkan PILIH PEMENANG
                        const winnerModal = new bootstrap.Modal(document.getElementById('selectWinnerModal'));
                        winnerModal.show();
                        $(".end-match").addClass("d-none");
                        $(".next-match").removeClass("d-none");
                    }
                    
                });
            }

            elapsed++;
        }

        updateTimer();
        interval = setInterval(updateTimer, 1000);
    }

    function moveToNextRound() {
        const next = allRounds.find(r => r.round_number === currentRoundNumber + 1);
        if (next) {
            roundId = next.id;
            currentRoundNumber = next.round_number;
            $("#round-id").val(roundId);
            $("#current-round").text(`ROUND ${currentRoundNumber}`);
            $(".panel-header .round").removeClass("active");
            $(".panel-header .round").eq(currentRoundNumber - 1).addClass("active");
            //timerEl.text("03:00");
            timerEl.text(formatTime(duration));

        }
    }

    function stopTimer() {
        clearInterval(interval);
    }

    function formatTime(seconds) {
        const min = Math.floor(seconds / 60).toString().padStart(2, '0');
        const sec = (seconds % 60).toString().padStart(2, '0');
        return `${min}:${sec}`;
    }

    function setWinnerOptions(data) {
        $("#option-blue").text(`Sudut Biru - ${data.blue.name} (${data.blue.contingent})`);
        $("#option-red").text(`Sudut Merah - ${data.red.name} (${data.red.contingent})`);

        

         $("#winner").empty().append(`
            <option value="blue">Sudut Biru - ${data.blue.name} (${data.blue.contingent})</option>
            <option value="red">Sudut Merah - ${data.red.name} (${data.red.contingent})</option>
        `);

        

        
    }
    

    $("#round-duration").on("change", function () {
        duration = parseInt($(this).val() || 180); // update variabel global
        timerEl.text(formatTime(duration)); // update tampilan timer
    });

    $(".start").on("click", function () {
        if (!roundId) return;
        const btn = $(this);

        // ✅ Ubah caption jadi "Ongoing"
        btn.html(`<i class="bi bi-clock-history me-1"></i> <span class="label">ONGOING</span>`);

        // ✅ Ambil durasi dari dropdown
        duration = parseInt($('#round-duration').val() || 180);

        setButtonLoading(btn, true);

        $.post(`${url}/api/local-match-rounds/${roundId}/start`, {
            duration: duration
        }, function () {
            setTimeout(fetchAndStartTimer, 500);
        }).always(() => setButtonLoading(btn, false));
    });


    $(".stop-round").on("click", function () {
        if (!roundId) return;

        const confirmStop = confirm("Yakin ingin menghentikan ronde ini sekarang?");
        if (!confirmStop) return;

        stopTimer(); // Berhentiin timer manual
       

        $.post(`${url}/api/local-match-rounds/${roundId}/finish`, function () {
            if (currentRoundNumber < totalRounds) {
                // 🔥 Masih ada ronde berikutnya
                $("#modal-round-number").text(currentRoundNumber);
                const modal = new bootstrap.Modal(document.getElementById('nextRoundModal'));
                modal.show();

                $("#confirm-next-round").off("click").on("click", function () {
                    modal.hide();
                    moveToNextRound();
                });
                $(".start i").attr("class", "bi bi-play-fill me-2");
                $(".start .label").text("START");


            } else {
                // 🔥 Ini ronde terakhir bro, langsung tampilkan modal PILIH PEMENANG
                const winnerModal = new bootstrap.Modal(document.getElementById('selectWinnerModal'));
                winnerModal.show();
                $(".end-match").addClass("d-none");
                $(".next-match").removeClass("d-none");
                $(".start i").attr("class", "bi bi-play-fill me-2");
                $(".start .label").text("START");

            }
        }).fail(function (xhr) {
            console.error("❌ Gagal stop round:", xhr.responseJSON?.message || xhr.statusText);
        });
    });

    

    

    /*$(".pause").on("click", function () {
        const btn = $(this);
        setButtonLoading(btn, true);
        if (isPaused) {
            $.post(`${url}/api/local-match-rounds/${roundId}/resume`, {
                duration: duration
            }, function (res) {
                isPaused = false;
                const startTime = new Date(res.start_time).getTime();
                const serverNow = new Date(res.now).getTime();
    
                elapsed = Math.max(0, Math.floor((serverNow - startTime) / 1000));
                runTimer();
            }).always(() => {
                setButtonLoading(btn, false);
                btn.text("PAUSE");
            });
        } else {
            stopTimer();
            $.post(`${url}/api/local-match-rounds/${roundId}/pause`, function () {
                isPaused = true;
            }).always(() => {
                setButtonLoading(btn, false);
                btn.text("RESUME");
            });
        }
    });*/
    $(".pause").on("click touchstart", function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (!roundId) {
            console.warn("⛔ roundId belum di-set");
            return;
        }

        // Pastikan duration selalu keisi
        duration = parseInt($('#round-duration').val() || 180);

        const btn = $(this);
        setButtonLoading(btn, true);

        if (isPaused) {
            $.post(`${url}/api/local-match-rounds/${roundId}/resume`, {
                duration: duration
            }, function (res) {
                isPaused = false;
                const startTime = new Date(res.start_time).getTime();
                const serverNow = new Date(res.now).getTime();

                elapsed = Math.max(0, Math.floor((serverNow - startTime) / 1000));
                runTimer();
            })
            .fail(err => console.error("❌ Resume Error:", err))
            .always(() => {
                setButtonLoading(btn, false);
                btn.text("PAUSE");
            });
        } else {
            stopTimer();
            $.post(`${url}/api/local-match-rounds/${roundId}/pause`)
            .done(() => {
                isPaused = true;
            })
            .fail(err => console.error("❌ Pause Error:", err))
            .always(() => {
                setButtonLoading(btn, false);
                btn.text("RESUME");
            });
        }
    });

    

    $(".reset").on("click", function () {
        const modal = new bootstrap.Modal(document.getElementById('confirmResetModal'));
        modal.show();
    
        $("#confirm-reset-btn").off("click").on("click", function () {
            const btn = $(".reset");
            setButtonLoading(btn, true);
            modal.hide();
    
            $.post(`${url}/api/local-match-rounds/${roundId}/reset`, function () {
                stopTimer();
                //timerEl.text("03:00");
                timerEl.text(formatTime(duration));

                $(".start i").attr("class", "bi bi-play-fill me-2");
                $(".start .label").text("START");

            }).always(() => setButtonLoading(btn, false));
        });
    });

    

    $(".end-match").off("click").on("click", function () {
        const confirmModalEl = document.getElementById('confirmEndModal');
        const winnerModalEl = document.getElementById('selectWinnerModal');
    
        const confirmModal = new bootstrap.Modal(confirmModalEl);
        const winnerModal = new bootstrap.Modal(winnerModalEl);
    
        confirmModal.show();
    
        $("#confirm-end-btn").off("click").on("click", function () {
            const instance = bootstrap.Modal.getInstance(confirmModalEl);
            if (instance) instance.hide();
    
            // Perbaiki urutan
            $(confirmModalEl).off("hidden.bs.modal").on('hidden.bs.modal', function () {
                winnerModal.show(); // 👉 TAMPILKAN DULU
                setTimeout(() => {
                    cleanupModals(); // 👉 HAPUS backdrop SETELAH modal pemenang muncul
                }, 300);
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
        $.post(`/api/matches/${matchId}/next`, function (res) {
            if (res.new_match_id) {
                window.location.href = `/matches/${res.new_match_id}`;
            } else {
                alert("Tidak ada pertandingan berikutnya.");
            }
        }).fail(function (xhr) {
            console.error("❌ Gagal ganti match:", xhr.responseJSON?.message || xhr.statusText);
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

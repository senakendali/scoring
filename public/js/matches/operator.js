$(document).ready(function () {
    const url = window.location.origin;
    const matchId = window.location.pathname.split("/").pop();
    const timerEl = $("#timer");
    const duration = 180;
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
            $("#tournament-name").text(data.tournament_name);
            $("#match-code").text(data.match_code);
            $("#class-name").text(data.class_name);
            $("#match-stage").text("-");

            $("#blue-name").text(data.blue.name);
            $("#blue-score").text(data.blue.score);
            $("#red-name").text(data.red.name);
            $("#red-score").text(data.red.score);

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
                        // Show modal for next round
                        $("#modal-round-number").text(currentRoundNumber);
                        const modal = new bootstrap.Modal(document.getElementById('nextRoundModal'));
                        modal.show();

                        $("#confirm-next-round").off("click").on("click", function () {
                            modal.hide();
                            moveToNextRound();
                        });

                    } else {
                        const endModal = new bootstrap.Modal(document.getElementById('matchEndModal'));
                        endModal.show();

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
            timerEl.text("03:00");
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

    $(".start").on("click", function () {
        if (!roundId) return;
        const btn = $(this);
        setButtonLoading(btn, true);
        $.post(`${url}/api/local-match-rounds/${roundId}/start`, function () {
            setTimeout(fetchAndStartTimer, 500);
        }).always(() => setButtonLoading(btn, false));
    });

    $(".pause").on("click", function () {
        const btn = $(this);
        setButtonLoading(btn, true);
        if (isPaused) {
            $.post(`${url}/api/local-match-rounds/${roundId}/resume`, function (res) {
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
                timerEl.text("03:00");
            }).always(() => setButtonLoading(btn, false));
        });
    });
    
    

    $(".end-match").on("click", function () {
        const confirmModal = new bootstrap.Modal(document.getElementById('confirmEndModal'));
        confirmModal.show();
    
        $("#confirm-end-btn").off("click").on("click", function () {
            const btn = $(".end-match");
            setButtonLoading(btn, true);
            confirmModal.hide();
    
            $.post(`${url}/api/local-matches/${matchId}/end-match`, function () {
                stopTimer();
    
                const endedModal = new bootstrap.Modal(document.getElementById('endedMatchModal'));
                endedModal.show();
    
                $(".end-match").addClass("d-none");
                $(".next-match").removeClass("d-none");
            }).always(() => setButtonLoading(btn, false));
        });
    });
    

    $(".next-match").on("click", function () {
        const nextMatchId = parseInt(matchId) + 1;
        window.location.href = `/matches/${nextMatchId}`;
    });

    let lastBlue = 0;
    let lastRed = 0;

    function startLiveScorePolling() {
        setInterval(() => {
            $.get(`${url}/api/local-matches/${matchId}/live-score`, function (data) {
                // Blue
                if (data.blue_score !== lastBlue) {
                    $("#blue-score").text(data.blue_score).addClass("flash");
                    setTimeout(() => $("#blue-score").removeClass("flash"), 500);
                    lastBlue = data.blue_score;
                }

                // Red
                if (data.red_score !== lastRed) {
                    $("#red-score").text(data.red_score).addClass("flash");
                    setTimeout(() => $("#red-score").removeClass("flash"), 500);
                    lastRed = data.red_score;
                }
            });
        }, 1500); // bisa disesuaikan intervalnya
    }

    startLiveScorePolling();


    fetchMatch();
});

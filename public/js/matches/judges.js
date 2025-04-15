$(document).ready(function () {
    let matchId = parseInt($("#match-id").val());
    let roundId = null;
    let judgeNumber = localStorage.getItem("judge_number");
    let judgeName = localStorage.getItem("judge_name");
    let lastBlue = 0;
    let lastRed = 0;
    let countdownInterval = null;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    Pusher.logToConsole = true;

    // ✅ Setup WebSocket Reverb
    const pusher = new Pusher('reverb', {
        wsHost: '192.168.1.3',
        wsPort: 6001,
        forceTLS: false,
        encrypted: false,
        enabledTransports: ['ws'],
        disableStats: true,
    });
    
    const globalChannel = pusher.subscribe('global.match');
    const channel = pusher.subscribe(`match.${matchId}`);

    channel.bind_global(function (event, data) {
        console.log("🌍 Global Event:", event, data);
    });

    // ✅ Timer started
    channel.bind('timer.started', function (data) {
        console.log('🔥 Timer started triggered:', data);
        
        // ✅ Update round ID dan tampilkan nomor ronde baru
        roundId = data.round_id;
        $("#current-round").text(`ROUND ${data.round_number}`);
    
        // ✅ Reset skor tampilan ke 0
        $("#blue-score").text("0");
        $("#red-score").text("0");
    
        // ✅ Reset skor cache juga
        lastBlue = 0;
        lastRed = 0;
    
        // ✅ Jalankan countdown timer
        const start = new Date(data.start_time).getTime();
        const duration = data.duration || 180;
        startCountdown(start, duration);
    });
    
    
    

   // 🎯 Update Timer dari Operator
   channel.bind('timer.updated', function (data) {
        if (parseInt(data.round_id) !== roundId) return;

        console.log("🕒 Timer updated:", data);

        if (data.status === 'in_progress') {
            const start = new Date(data.start_time).getTime();
            const now = new Date(data.now).getTime();

            const elapsed = Math.floor((now - start) / 1000);
            const remaining = Math.max(0, data.remaining);

            // Sinkron ke server, bukan pakai Date.now()
            startCountdown(now - (elapsed * 1000), 180);
        } else if (data.status === 'paused') {
            clearInterval(countdownInterval);
            $(".timer").text("PAUSED");
        } else if (data.status === 'finished') {
            clearInterval(countdownInterval);
            $(".timer").text("00:00");
        } else if (data.status === 'not_started') {
            clearInterval(countdownInterval);
            $(".timer").text("03:00");
        }
    });


    // ✅ Score update
    channel.bind('score.updated', function (data) {
        console.log("🎯 Score updated:", data); // HARUS MUNCUL DULU DI CONSOLE
    
        $("#blue-score").text(data.blueScore).addClass("flash");
        setTimeout(() => $("#blue-score").removeClass("flash"), 500);
    
        $("#red-score").text(data.redScore).addClass("flash");
        setTimeout(() => $("#red-score").removeClass("flash"), 500);
    });

    globalChannel.bind('match.changed', function (data) {
        console.log("🎯 Match changed to:", data.new_match_id);
        window.location.href = `/matches/judges/${data.new_match_id}`; // atau operator, screen, sesuai page lo
    });
    
    
    

    // ✅ Setup juri jika belum
    if (!judgeNumber || !judgeName) {
        const modal = new bootstrap.Modal(document.getElementById("setupJudgeModal"), {
            backdrop: 'static',
            keyboard: false
        });
        modal.show();

        $("#startJudgeBtn").on("click", function () {
            const selected = $("#judge_number").val();
            const name = $("#judge_name").val().trim();

            if (!name) {
                alert("Nama juri wajib diisi.");
                return;
            }

            localStorage.setItem("judge_number", selected);
            localStorage.setItem("judge_name", name);

            judgeNumber = selected;
            judgeName = name;

            modal.hide();
            initializeScoring();
        });
    } else {
        initializeScoring();
    }

    function initializeScoring() {
        fetchMatchData();
        

        $(".button-item").on("click", function () {
            const corner = $(this).data("corner");
            const type = $(this).data("type");
            submitPoint(corner, type);
        });
    }

    function fetchMatchData() {
        $(".loader-bar").show();
        $.get(`/api/local-matches/${matchId}`, function (data) {
            $("#tournament-name").text(data.tournament_name);
            $("#match-code").text(data.match_code);
            $("#class-name").text(data.class_name);
            $("#blue-name").text(data.blue.name);
            $("#red-name").text(data.red.name);
            $("#blue-score").text(data.blue.score);
            $("#red-score").text(data.red.score);

            const activeRound = data.rounds.find(r => r.status === 'in_progress') || data.rounds[0];
            roundId = activeRound?.id || null;
            $("#current-round").text(`ROUND ${activeRound?.round_number || 1}`);
            $(".loader-bar").hide();
        });
    }

    function startCountdown(startTime, duration = 180) {
        clearInterval(countdownInterval);
    
        countdownInterval = setInterval(() => {
            const now = Date.now();
            const elapsed = Math.floor((now - startTime) / 1000);
            const remaining = duration - elapsed;
    
            if (remaining <= 0) {
                clearInterval(countdownInterval);
                $(".timer").text("00:00");
            } else {
                $(".timer").text(formatTime(remaining));
            }
        }, 1000);
    }
    

    function updateTimer(remaining) {
        $(".timer").text(formatTime(remaining));
    }

    function formatTime(seconds) {
        if (isNaN(seconds) || seconds < 0) seconds = 0;
        const min = Math.floor(seconds / 60).toString().padStart(2, '0');
        const sec = (seconds % 60).toString().padStart(2, '0');
        return `${min}:${sec}`;
    }

    function submitPoint(corner, type) {
        if (!matchId || !roundId || !judgeNumber || !judgeName) {
            console.warn("❌ Data belum lengkap untuk submit skor");
            return;
        }
    
        $.post(`/api/local-judge-scores`, {
            match_id: matchId,
            round_id: roundId,
            judge_number: judgeNumber,
            judge_name: judgeName,
            corner: corner,
            type: type
        }).fail(function (xhr) {
            console.error("❌ Gagal submit point:", xhr.responseJSON?.message || xhr.statusText);
        });
    }
    

    function startMatchChangePolling() {
        const initialMatchId = matchId;

        setInterval(() => {
            $.get('/api/judge/current-match', function (res) {
                const newMatchId = parseInt(res.current_match_id);
                if (newMatchId && newMatchId !== initialMatchId) {
                    window.location.href = `/matches/${newMatchId}/judges`;
                }
            });
        }, 5000);
    }
});

$(document).ready(function () {
    let matchId = parseInt($("#match-id").val());

    console.log("🟢 Arena JS Ready, Match ID:", matchId);

    let roundId = null;
    let countdownInterval = null;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    fetchMatchData();

    // ✅ WebSocket via Reverb
    const pusher = new Pusher('reverb', {
        wsHost: '192.168.1.3',
        wsPort: 6001,
        forceTLS: false,
        disableStats: true,
        enabledTransports: ['ws'],
    });

    const channel = pusher.subscribe(`match.${matchId}`);
    const globalChannel = pusher.subscribe('global.match');

    // 🔥 Saat juri tekan tombol
    channel.bind('judge.point.submitted', function (data) {
        console.log("👊 Judge point submitted:", data);

        const { judge_number, corner, type } = data;

        // Target elemen juri berdasarkan corner, type, dan judge_number
        const selector = `.judges-${type}.${corner} .judge[data-judge="${judge_number}"]`;

        const $el = $(selector);

        $el.addClass("active");

        setTimeout(() => {
            $el.removeClass("active");
        }, 1000);
    });

    // ✅ Global Match Change
    globalChannel.bind('match.changed', function (data) {
        console.log("🎯 Match changed:", data);
        window.location.href = `/matches/${data.new_match_id}/screen`; // Sesuaikan path kalau perlu
    });

    // ✅ Timer Started
    channel.bind('timer.started', function (data) {
        console.log("🔥 Timer started:", data);
        roundId = data.round_id;

        $("#current-round").text(`ROUND ${data.round_number}`);
        $("#blue-score").text("0");
        $("#red-score").text("0");
        startCountdown(new Date(data.start_time).getTime(), data.duration || 180);
    });

    // ✅ Timer Updated (Pause, Resume, Finish)
    channel.bind('timer.updated', function (data) {
        if (parseInt(data.round_id) !== roundId) return;

        console.log("🕒 Timer updated:", data);

        if (data.status === 'in_progress') {
            const start = new Date(data.start_time).getTime();
            const now = new Date(data.now).getTime();
            const elapsed = Math.floor((now - start) / 1000);
            const remaining = Math.max(0, data.remaining);
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

    // ✅ Score Updated
    channel.bind('score.updated', function (data) {
        console.log("🎯 Score updated:", data);
    
        $("#blue-score").text(data.blueScore).addClass("flash");
        setTimeout(() => $("#blue-score").removeClass("flash"), 500);
    
        $("#red-score").text(data.redScore).addClass("flash");
        setTimeout(() => $("#red-score").removeClass("flash"), 500);
    
        // Tambahan poin di sisi masing-masing
        $(".arena-container .blue .score").text(data.blueAdjustment > 0 ? "+" + data.blueAdjustment : data.blueAdjustment);
        $(".arena-container .red .score").text(data.redAdjustment > 0 ? "+" + data.redAdjustment : data.redAdjustment);
    });
    

    // ✅ Countdown Timer Handler
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

    // ✅ Format Time Helper
    function formatTime(seconds) {
        if (isNaN(seconds) || seconds < 0) seconds = 0;
        const min = Math.floor(seconds / 60).toString().padStart(2, '0');
        const sec = (seconds % 60).toString().padStart(2, '0');
        return `${min}:${sec}`;
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

    
});

$(document).ready(function () {
    const url = window.location.origin;
    let matchId = parseInt($("#match-id").val());

    console.log("🟢 Arena JS Ready, Match ID:", matchId);

    let roundId = null;
    let countdownInterval = null;

    const arena = $("#session-arena").val();
    const tournament = $("#session-tournament").val();

    const tournamentSlug = slugify(tournament);
    const arenaSlug = slugify(arena);
    

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    fetchMatchData();
    fetchJuriCount(tournament, arena);
    //pollSeniJudgeScores(matchId, tournament, arena);
    initSeniJudgeRealtime(matchId, tournament, arena);


    const host = window.location.hostname;
    const pusher = new Pusher('reverb', {
        wsHost: host,
        wsPort: 6001,
        forceTLS: false,
        encrypted: false,
        enabledTransports: ['ws'],
        disableStats: true,
    });

    const channel = pusher.subscribe(`match.${matchId}`);
    const globalChannel = pusher.subscribe('global.seni.match');
    

    // ✅ Global Match Change
    globalChannel.bind('seni.match.changed', function (data) {
        console.log("🎯 Match changed:", data);
        window.location.href = `/matches/seni/display-arena/${data.new_match_id}`; // Sesuaikan path kalau perlu
    });

    function slugify(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-')      // Ganti spasi jadi -
            .replace(/[^\w\-]+/g, '')  // Hapus karakter non-word
            .replace(/\-\-+/g, '-')    // Ganti -- jadi -
            .replace(/^-+/, '')        // Hapus - di awal
            .replace(/-+$/, '');       // Hapus - di akhir
    }

   
   window.Echo.channel(`seni-timer.${tournamentSlug}.${arenaSlug}`)
    .listen('.SeniTimerStarted', (e) => {
        console.log("🔥 Timer started raw:", e);
        console.log("⏱️ e.start_time:", e.start_time);

        const startTimestamp = new Date(e.start_time).getTime();

        if (isNaN(startTimestamp)) {
            console.error("❌ Invalid start_time:", e.start_time);
            return;
        }

        startCountUp(startTimestamp, e.duration || 180);
    });

    
    window.Echo.channel(`seni-timer.${tournamentSlug}.${arenaSlug}`)
        .listen('.SeniTimerUpdated', (data) => {
            console.log("🕒 Event SeniTimerUpdated diterima:", data);

            if (data.status === 'ongoing') {
                clearInterval(countdownInterval); // biar gak dobel interval
                $(".wrong-move").prop("disabled", false);

                const startTimestamp = Date.parse(data.start_time);
                if (!isNaN(startTimestamp)) {
                    console.log("▶️ Melanjutkan timer dari:", data.start_time);
                    startCountUp(startTimestamp, data.duration || 180);
                } else {
                    console.warn("❌ start_time tidak valid saat resume:", data.start_time);
                }
            }

            else if (data.status === 'paused') {
                clearInterval(countdownInterval);
                $(".wrong-move").prop("disabled", true);
                console.log("⏸️ Timer dipause");
            }

            else if (data.status === 'finished') {
                clearInterval(countdownInterval);
                $(".wrong-move").prop("disabled", true);
                $("#timer").text("SELESAI");
                console.log("🏁 Timer selesai");
            }

            else if (data.status === 'not_started') {
                clearInterval(countdownInterval);
                $(".wrong-move").prop("disabled", true);
                $("#timer").text("00:00");

                totalDeduction = 0;
                currentScore = 9.75;
                $("#starting-score").text("9.75");
                $("#deduction").text("-0.00");

                console.log("🔁 Timer direset");
            }
        });



     window.Echo.channel(`seni-timer.${tournamentSlug}.${arenaSlug}`)
        .listen('.SeniTimerFinished', function (data) {
            console.log("🏁 Match selesai:", data);

            $(".wrong-move").prop("disabled", true);

            let modalInstance = null;

            if (data.status === 'finished' && data.disqualified === true) {
                const disqualifiedModalEl = document.getElementById('disqualifiedModal');
                if (disqualifiedModalEl) {
                    modalInstance = new bootstrap.Modal(disqualifiedModalEl);
                    modalInstance.show();
                }
            } else {
                const finishedModalEl = document.getElementById('finishedModal');
                if (finishedModalEl) {
                    modalInstance = new bootstrap.Modal(finishedModalEl);
                    modalInstance.show();
                }
            }

            // ✅ Tutup semua modal setelah 2 detik
            if (modalInstance) {
                setTimeout(() => {
                    modalInstance.hide();
                }, 2000);
            }
        });


    
    
    
    

   


    
    
        
        


   
    
    
    
    

    // ✅ Countdown Timer Handler
    function startCountUp(startTime, maxDuration = 180) {
        clearInterval(countdownInterval);

        countdownInterval = setInterval(() => {
            const now = Date.now();
            const elapsed = Math.floor((now - startTime) / 1000);

            if (elapsed >= maxDuration) {
                clearInterval(countdownInterval);
                $("#timer").text(formatTime(maxDuration));
            } else {
                $("#timer").text(formatTime(elapsed));
            }

            console.log("⏱️ Updating:", formatTime(elapsed));
            console.log("⛳ Element found:", $("#timer").length);
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
         $.get(`${url}/api/local-matches/seni/${matchId}`, function (data) {
            $("#tournament-name").text(data.tournament_name);
          
            $("#match-code").text(data.arena_name + " Partai " + data.match_order);
            $("#class-name").text(data.category);
            $("#age-category").text(data.age_category);
            $("#gender").text(data.category + "  " + (data.gender === 'male' ? 'PUTRA' : 'PUTRI'));

            $("#contingent-name").text(data.contingent);



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

    function renderSeniJudges(juriCount) {
        const $container = $("#judges-preview");
        const default_score = 9.75;
        $container.empty(); // Kosongkan dulu

        for (let i = 1; i <= juriCount; i++) {
            const judgeHtml = `
                <div class="flex-fill judge-score-detail">
                    <div class="judge-title fw-bold">J${i}</div>
                    <div class="judge-score fw-bold" id="judge-score-${i}">${default_score.toFixed(2)}</div></div>
                </div>
            `;
            $container.append(judgeHtml);
        }
    }


    function fetchJuriCount(tournament, arena) {
        $.get(`${url}/api/seni/juri-count?tournament=${encodeURIComponent(tournament)}&arena=${encodeURIComponent(arena)}`, function (data) {
            renderSeniJudges(data.count);
        }).fail(function () {
            console.error("❌ Gagal fetch jumlah juri");
            $(".seni-judges").html('<div class="text-danger">Gagal memuat juri</div>');
        });
    }

    function pollSeniJudgeScores(matchId, tournament, arena) {
        setInterval(() => {
            $.get(`${url}/api/seni/judges-score?tournament=${encodeURIComponent(tournament)}&arena=${encodeURIComponent(arena)}&match_id=${matchId}`, function (data) {
                const judges = data.judges || [];
                const totalPenalty = parseFloat(data.penalty ?? 0);
                const $container = $("#judges-preview");

                // Urutkan berdasarkan skor kecil ke besar
                judges.sort((a, b) => a.score - b.score);
                const scores = [];

                $container.empty();

                judges.forEach(j => {
                    scores.push(j.score);

                    const html = `
                        <div class="flex-fill judge-score-detail">
                            <div class="judge-title fw-bold">J${j.juri_number}</div>
                            <div class="judge-score fw-bold">${j.score.toFixed(2)}</div>
                        </div>
                    `;
                    $container.append(html);
                });

                const $allCards = $container.find(".judge-score-detail");
                let median = 0;

                if (scores.length > 0) {
                    if (scores.length % 2 === 0) {
                        // Genap → rata-rata 2 tengah
                        const mid1 = (scores.length / 2) - 1;
                        const mid2 = (scores.length / 2);
                        $allCards.eq(mid1).addClass("median");
                        $allCards.eq(mid2).addClass("median");
                        median = (scores[mid1] + scores[mid2]) / 2;
                    } else {
                        // Ganjil → ambil tengah
                        const mid = Math.floor(scores.length / 2);
                        $allCards.eq(mid).addClass("median");
                        median = scores[mid];
                    }
                }

                $("#median-score").text(median.toFixed(2));
                $("#penalty").text("-" + totalPenalty.toFixed(2));

                const mean = scores.reduce((a, b) => a + b, 0) / scores.length;
                const variance = scores.reduce((sum, v) => sum + Math.pow(v - mean, 2), 0) / scores.length;
                const stddev = Math.sqrt(variance);
                $("#standar-deviasi").text(stddev.toFixed(2));

                const totalScore = mean - totalPenalty;
                $("#total-score").text(totalScore.toFixed(2));
            });
        }, 1500);
    }

    function initSeniJudgeRealtime(matchId, tournament, arena) {
        const tournamentSlug = slugify(tournament);
        const arenaSlug = slugify(arena);

        window.Echo.channel(`seni-score.${tournamentSlug}.${arenaSlug}`)
            .listen('.SeniScoreUpdated', function (data) {
                console.log("🎯 Arena UI received update:", data);

                const judges = data.judges || [];
                const totalPenalty = parseFloat(data.penalty ?? 0);
                const $container = $("#judges-preview");

                judges.sort((a, b) => a.score - b.score);
                const scores = [];

                $container.empty();

                judges.forEach(j => {
                    scores.push(j.score);
                    const html = `
                        <div class="flex-fill judge-score-detail">
                            <div class="judge-title fw-bold">J${j.juri_number}</div>
                            <div class="judge-score fw-bold">${j.score.toFixed(2)}</div>
                        </div>
                    `;
                    $container.append(html);
                });

                const $allCards = $container.find(".judge-score-detail");
                let median = 0;

                if (scores.length > 0) {
                    if (scores.length % 2 === 0) {
                        const mid1 = (scores.length / 2) - 1;
                        const mid2 = (scores.length / 2);
                        $allCards.eq(mid1).addClass("median");
                        $allCards.eq(mid2).addClass("median");
                        median = (scores[mid1] + scores[mid2]) / 2;
                    } else {
                        const mid = Math.floor(scores.length / 2);
                        $allCards.eq(mid).addClass("median");
                        median = scores[mid];
                    }
                }

                $("#median-score").text(median.toFixed(2));
                $("#penalty").text("-" + totalPenalty.toFixed(2));

                const mean = scores.reduce((a, b) => a + b, 0) / scores.length;
                const variance = scores.reduce((sum, v) => sum + Math.pow(v - mean, 2), 0) / scores.length;
                const stddev = Math.sqrt(variance);

                $("#standar-deviasi").text(stddev.toFixed(2));
                const totalScore = mean - totalPenalty;
                $("#total-score").text(totalScore.toFixed(2));
            });
    }








    

    
});

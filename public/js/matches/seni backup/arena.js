$(document).ready(function () {
    const url = APP.baseUrl;
    let matchId = parseInt($("#match-id").val());

    console.log("🟢 Arena JS Ready, Match ID:", matchId);

    let roundId = null;
    let countdownInterval = null;

    const arena = $("#session-arena").val();
    const tournament = $("#session-tournament").val();

    const tournamentSlug = slugify(tournament);
    const arenaSlug = slugify(arena);

    let currentArena = null;

    

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
    //const globalChannel = pusher.subscribe('global.seni.match');
    //const globalChannel = pusher.subscribe(`arena.seni.match.${matchId}`);
    const slugArena = $("#session-arena").val()?.toLowerCase().replace(/\s+/g, '-');
    const globalChannel = pusher.subscribe(`arena.seni.match.${slugArena}`);
    
    

    // ✅ Global Match Change
    globalChannel.bind('seni.match.changed', function (data) {
        console.log("🎯 Match changed:", data);
        window.location.href = url + `/matches/seni/display-arena/${data.new_match_id}`; // Sesuaikan path kalau perlu
    });

    function slugify(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-')      // Ganti spasi jadi -
            .replace(/[^\w\-]+/g, '')  // Hapus karakter non-word
            .replace(/\-\-+/g, '-')    // Ganti -- jadi -
            .replace(/^-+/, '')        // Hapus - di awal
            .replace(/-+$/, '');       // Hapus - di akhir
    }

   
    console.log("🎯 Channel yang dipakai:", `seni-timer.${tournamentSlug}.${arenaSlug}`);

   window.Echo.channel(`seni-timer.${tournamentSlug}.${arenaSlug}`)
    .listen('.SeniTimerStarted', (e) => {
        console.log("🔥 Timer started raw:", e);
        console.log("⏱️ e.start_time:", e.start_time);

        const startTimestamp = new Date(e.start_time).getTime();

        if (isNaN(startTimestamp)) {
            console.error("❌ Invalid start_time:", e.start_time);
            return;
        }

        startCountUp(startTimestamp, e.duration || 600);
    });

    
    window.Echo.channel(`seni-timer.${tournamentSlug}.${arenaSlug}`)
        .listen('.SeniTimerUpdated', (data) => {
            console.log("🕒 Event SeniTimerUpdated diterima:", data);

             // ✅ Stop interval dulu apapun statusnya
            clearInterval(countdownInterval);

            if (data.status === 'ongoing') {
                clearInterval(countdownInterval); // biar gak dobel interval
                $(".wrong-move").prop("disabled", false);

                const startTimestamp = Date.parse(data.start_time);
                if (!isNaN(startTimestamp)) {
                    console.log("▶️ Melanjutkan timer dari:", data.start_time);
                    startCountUp(startTimestamp, data.duration || 600);
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

                const category = $("#gender").text().toLowerCase(); // atau pakai data lain yang lebih spesifik
                const baseScore = (category.includes('tunggal') || category.includes('regu')) ? 9.90 : 9.10;

                totalDeduction = 0;
                currentScore = baseScore;

                $("#starting-score").text(baseScore.toFixed(2));
                $("#deduction").text("-0.00");

                console.log("🔁 Timer direset dengan base score", baseScore);
            }

        });



     window.Echo.channel(`seni-timer.${tournamentSlug}.${arenaSlug}`)
        .listen('.SeniTimerFinished', function (data) {
            console.log("🏁 Match selesai:", data);
            clearInterval(countdownInterval); // ✅ ⬅️ tambahkan di sini juga

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
    function startCountUp(startTime, maxDuration = 600) {
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

            const category = data.category?.toLowerCase(); // contoh: "Tunggal", "Regu", "Ganda"

            const baseScore = ['tunggal', 'regu'].includes(category) ? 9.90 : 9.10;

            if(data.is_display_timer != 0){
                $("#timer").show();
               
            }else{
                /* perbaiki ini */
                $("#timer").hide();
            }

            // Set UI
            $("#starting-score").text(baseScore.toFixed(2));
            $("#seni_base_score").val(baseScore.toFixed(2));
            startingScore = baseScore;

            currentArena = data.arena_name;

            $("#tournament-name").text(data.tournament_name.replace("Pencak Silat", "").trim());

          
            $("#match-code").text(data.arena_name + " Partai " + data.match_order);
            $("#class-name").text(data.category);
            $("#age-category").text(data.age_category);
            $("#gender").text(data.category + "  " + (data.gender === 'male' ? 'PUTRA' : 'PUTRI'));

            $("#contingent-name").text(data.contingent).css({
                    'font-size': '23px',
                    'font-weight': 'bold'
                });



            // 🔥 Reset semua dulu
            $("#participant-1").text('-').hide();
            $("#participant-2").text('-').hide();
            $("#participant-3").text('-').hide();

            // ✅ Tampilkan peserta sesuai match_type
            if (data.match_type === 'seni_tunggal' || data.match_type === 'solo_kreatif') {
                $("#participant-1").text(data.team_members[0] || '-').show().css({
                    'font-size': '23px',
                    'font-weight': 'bold'
                });
            } else if (data.match_type === 'seni_ganda') {
                $("#participant-1").text(data.team_members[0] || '-').show() .css({
                    'font-size': '23px',
                    'font-weight': 'bold'
                });
                $("#participant-2").text(data.team_members[1] || '-').show() .css({
                    'font-size': '23px',
                    'font-weight': 'bold'
                });
            } else if (data.match_type === 'seni_regu') {
                $("#participant-1").text(data.team_members[0] || '-').show() .css({
                    'font-size': '23px',
                    'font-weight': 'bold'
                });
                $("#participant-2").text(data.team_members[1] || '-').show() .css({
                    'font-size': '23px',
                    'font-weight': 'bold'
                });
                $("#participant-3").text(data.team_members[2] || '-').show() .css({
                    'font-size': '23px',
                    'font-weight': 'bold'
                });
            }

             pollSeniJudgeScores(matchId, tournament, arena);
            $(".loader-bar").hide();
        });
    }

    $("#match-code").on("click", function () {
        const matchList = $("#match-list");
        matchList.empty();

        $.get(`${url}/api/local-matches/seni`, function (data) {
            const arenaMatches = [];

            data.forEach(categoryGroup => {
                categoryGroup.age_categories.forEach(ageGroup => {
                    ageGroup.pools.forEach(pool => {
                        arenaMatches.push(...pool.matches);
                    });
                });
            });

            arenaMatches.sort((a, b) => a.match_order - b.match_order);

            arenaMatches.forEach(match => {
                const li = $(`
                    <li class="list-group-item list-group-item-action bg-dark text-white"
                        style="cursor:pointer;" data-id="${match.id}">
                        PARTAI ${match.match_order}
                    </li>
                `);

                li.on("click", function () {
                    const selectedId = $(this).data("id");
                    

                    $("#matchListModal").modal("hide");

                    window.location.href = `${url}/matches/seni/display-arena/${selectedId}`;
                });

                matchList.append(li);
            });

            $("#matchListModal").modal("show");
        });
    });

    function renderSeniJudges(juriCount, baseScore = 9.90) {
        const $container = $("#judges-preview");
        $container.empty();

        for (let i = 1; i <= juriCount; i++) {
            const judgeHtml = `
                <div class="flex-fill judge-score-detail">
                    <div class="judge-title fw-bold">J${i}</div>
                    <div class="judge-score fw-bold" id="judge-score-${i}">${baseScore.toFixed(2)}</div>
                </div>
            `;
            $container.append(judgeHtml);
        }
    }

    function fetchJuriCount(tournament, arena) {
        const matchId = $("#match-id").val();

        $.when(
            $.get(`${url}/api/seni/juri-count?tournament=${encodeURIComponent(tournament)}&arena=${encodeURIComponent(arena)}`),
            $.get(`${url}/api/local-matches/seni/${matchId}`)
        ).done(function (juriRes, matchRes) {
            const juriCount = juriRes[0].count;
            const matchCategory = matchRes[0].category?.toLowerCase();

            const baseScore = ['tunggal', 'regu'].includes(matchCategory) ? 9.90 : 9.10;

            renderSeniJudges(juriCount, baseScore);
        }).fail(function () {
            console.error("❌ Gagal fetch juri atau match");
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

                $("#median-score").text(median.toFixed(6));
                $("#penalty").text("-" + totalPenalty.toFixed(2));

                const mean = scores.reduce((a, b) => a + b, 0) / scores.length;
                const variance = scores.reduce((sum, v) => sum + Math.pow(v - mean, 2), 0) / scores.length;
                const stddev = Math.sqrt(variance);
                $("#standar-deviasi").text(stddev.toFixed(6));

                const totalScore = data.final_score ?? (mean - totalPenalty);

                $("#total-score").text(median.toFixed(6));
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

                $("#median-score").text(median.toFixed(6));
                $("#penalty").text("-" + totalPenalty.toFixed(2));

                const mean = scores.reduce((a, b) => a + b, 0) / scores.length;
                const variance = scores.reduce((sum, v) => sum + Math.pow(v - mean, 2), 0) / scores.length;
                const stddev = Math.sqrt(variance);

                $("#standar-deviasi").text(stddev.toFixed(6));
                const totalScore = data.final_score ?? (mean - totalPenalty);

                $("#total-score").text(median.toFixed(6));
            });
    }
    
});

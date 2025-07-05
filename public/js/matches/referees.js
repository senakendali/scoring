$(document).ready(function () {
    const url = window.location.origin;
    let matchId = parseInt($("#match-id").val());

    let currentArena = null;
   
    console.log("🟢 Arena JS Ready, Match ID:", matchId);

    let roundId = null;
    let countdownInterval = null;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    fetchMatchData();
    //$(".item, .drop").prop("disabled", true).addClass("disabled");


    const host = window.location.hostname;
    const pusher = new Pusher('reverb', {
        wsHost: host,
        wsPort: 6001,
        forceTLS: false,
        disableStats: true,
        enabledTransports: ['ws'],
    });

    const channel = pusher.subscribe(`match.${matchId}`);
    //const globalChannel = pusher.subscribe('global.match');
    const globalChannel = pusher.subscribe(`global.match.${matchId}`);

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
        window.location.href = `/matches/referees/${data.new_match_id}`; // Sesuaikan path kalau perlu
    });

    // ✅ Timer Started
    channel.bind('timer.started', function (data) {
        console.log("🔥 Timer started:", data);
        roundId = data.round_id;

        $("#current-round").text(`ROUND ${data.round_number}`);
        //$("#blue-score").text("0");
        //$("#red-score").text("0");
        startCountdown(new Date(data.start_time).getTime(), data.duration || 180);
        $(".item, .drop").prop("disabled", false).removeClass("disabled");
    });

    
    channel.bind('timer.updated', function (data) {
        if (parseInt(data.round_id) !== roundId) return;

        console.log("🕒 Timer updated:", data);

        if (data.status === 'in_progress') {
            const start = new Date(data.start_time).getTime();
            const now = new Date(data.now).getTime();

            const elapsed = Math.floor((now - start) / 1000);
            const remaining = Math.max(0, data.remaining || 0);

            // ✅ Pakai duration dari event
            startCountdown(now - (elapsed * 1000), data.duration || 180);
        } else if (data.status === 'paused') {
            clearInterval(countdownInterval);
            $(".timer").text("PAUSED");
        } else if (data.status === 'finished') {
            clearInterval(countdownInterval);
            $(".timer").text("00:00");
            resetRefereeActions();
            //$(".item, .drop").prop("disabled", true).addClass("disabled");
        } else if (data.status === 'not_started') {
            clearInterval(countdownInterval);
            $(".timer").text("00:00");
        }
    });

    // ✅ Score Updated
    channel.bind('score.updated', function (data) {
        console.log("🎯 Score updated:", data);
    
        const blueScore = data.blueScore;
        const redScore = data.redScore;
    
        $("#blue-score").text(blueScore).addClass("flash");
        setTimeout(() => $("#blue-score").removeClass("flash"), 500);
    
        $("#red-score").text(redScore).addClass("flash");
        setTimeout(() => $("#red-score").removeClass("flash"), 500);
    
        // 🔥 Update score tambahan di sisi masing-masing
        $(".arena-container .blue .score").text(data.blueAdjustment > 0 ? "+" + data.blueAdjustment : data.blueAdjustment);
        $(".arena-container .red .score").text(data.redAdjustment > 0 ? "+" + data.redAdjustment : data.redAdjustment);
    
        
    });

    let waitingModalInstance = null;
    let waitingProgressInterval = null;
    Echo.channel('match.' + matchId)
        .listen('.verification.requested', (e) => {
            console.log('Verification Requested (Arena/Dewan):', e);

            // Update teks sesuai jenis verifikasi
            let description = '';
            if (e.type === 'jatuhan') {
                description = 'Menunggu hasil verifikasi Jatuhan...';
            } else if (e.type === 'hukuman') {
                description = 'Menunggu hasil verifikasi Hukuman...';
            } else {
                description = 'Menunggu hasil verifikasi...';
            }

            $('#waitingVerificationMessage').html(`<b>${description}</b>`);
            // Reset Progress
            $('#waitingVerificationProgress').css('width', '0%');

            // Tampilkan modal
            waitingModalInstance = new bootstrap.Modal(document.getElementById('waitingVerificationModal'), {
                backdrop: 'static',   // Tidak bisa klik luar
                keyboard: false       // Tidak bisa ESC close
            });
            waitingModalInstance.show();

            // Jalankan animasi progress bar
            let progress = 0;
            waitingProgressInterval = setInterval(() => {
                progress += 2; // tambah 2% tiap 300ms
                if (progress > 100) progress = 100;
                $('#waitingVerificationProgress').css('width', `${progress}%`);
            }, 300);
        });


    let verificationResultModalInstance = null;
    let verificationResultTimer = null;

    Echo.channel('match.' + matchId)
        .listen('.verification.resulted', (e) => {
            console.log('Verification Resulted:', e);

            // Jika modal menunggu terbuka, tutup
            if (waitingModalInstance) {
                waitingModalInstance.hide();
            }

            // Hitung jumlah masing-masing vote
            let totalVotes = e.results.length;
            let blueVotes = e.results.filter(v => v.vote === 'blue').length;
            let redVotes = e.results.filter(v => v.vote === 'red').length;
            let invalidVotes = e.results.filter(v => v.vote === 'invalid').length;

            // Hitung persentase
            let bluePercent = totalVotes ? (blueVotes / totalVotes * 100).toFixed(0) : 0;
            let redPercent = totalVotes ? (redVotes / totalVotes * 100).toFixed(0) : 0;
            let invalidPercent = totalVotes ? (invalidVotes / totalVotes * 100).toFixed(0) : 0;

            // Generate description
            let actionLabel = e.type === 'jatuhan' ? 'Jatuhan' : 'Hukuman';
            let cornerLabel = e.corner === 'blue' ? 'sudut Biru' : 'sudut Merah';
            let titleText = `${actionLabel} untuk ${cornerLabel}`;

            // 🔷 Ganti warna modal sesuai corner
            let modalEl = document.getElementById('verificationResultModal');
            modalEl.classList.remove('bg-blue', 'bg-red');
            if (e.corner === 'blue') {
                modalEl.classList.add('bg-blue');
            } else {
                modalEl.classList.add('bg-red');
            }


            // Generate HTML progress bar
            let resultHtml = `
                <div class="text-center fw-bold mb-3">${titleText}</div>
                <div class="text-start mb-2">Biru (${blueVotes} vote)</div>
                <div class="progress mb-3" style="height: 50px;">
                <div class="progress-bar bg-primary" role="progressbar" style="width: ${bluePercent}%;">${bluePercent}%</div>
                </div>

                <div class="text-start mb-2">Merah (${redVotes} vote)</div>
                <div class="progress mb-3" style="height: 50px;">
                <div class="progress-bar bg-danger" role="progressbar" style="width: ${redPercent}%;">${redPercent}%</div>
                </div>

                <div class="text-start mb-2">Tidak Sah (${invalidVotes} vote)</div>
                <div class="progress mb-2" style="height: 50px;">
                <div class="progress-bar bg-secondary" role="progressbar" style="width: ${invalidPercent}%;">${invalidPercent}%</div>
                </div>
            `;

            $('#verificationResultContent').html(resultHtml);

            // Tampilkan Modal
            verificationResultModalInstance = new bootstrap.Modal(document.getElementById('verificationResultModal'));
            verificationResultModalInstance.show();

            // Clear timer sebelumnya kalau ada
            if (verificationResultTimer) {
                clearTimeout(verificationResultTimer);
            }

            // Modal auto-close setelah 5 detik
            verificationResultTimer = setTimeout(() => {
                verificationResultModalInstance.hide();
            }, 5000);
        });


    
    

    $(".item[data-action], .drop[data-action]").on("click", function () {
        const $btn = $(this);
        const action = $btn.data("action");
        const point = $btn.data("point");
        const corner = $btn.data("corner");

        // 🔁 DROP boleh diklik berkali-kali
        if (action === 'drop') {
            $.post("/api/local-referee-actions", {
                local_match_id: matchId,
                round_id: roundId,
                action: action,
                point_change: point,
                corner: corner,
            }).done(function (res) {
                console.log("✅ Drop action sent", res);
            }).fail(function (xhr) {
                console.error("❌ Gagal kirim drop:", xhr.responseJSON?.message || xhr.statusText);
            });

            return;
        }

        // 🔁 JATUHAN juga bisa diklik berkali-kali
        if (action === 'jatuhan') {
            $.post("/api/local-referee-actions", {
                local_match_id: matchId,
                round_id: roundId,
                action: action,
                point_change: point,
                corner: corner,
            }).done(function (res) {
                console.log("✅ Jatuhan action sent", res);
            }).fail(function (xhr) {
                console.error("❌ Gagal kirim jatuhan:", xhr.responseJSON?.message || xhr.statusText);
            });

            return;
        }

        // 🔁 HAPUS JATUHAN
        if (action === 'hapus-jatuhan') {
            $.ajax({
                url: "/api/local-referee-actions/remove-jatuhan",
                method: "POST",
                data: {
                    local_match_id: matchId,
                    round_id: roundId,
                    corner: corner,
                },
                success: function (res) {
                    console.log("🧹 Jatuhan berhasil dihapus:", res);
                },
                error: function (xhr) {
                    console.error("❌ Gagal hapus jatuhan:", xhr.responseJSON?.message || xhr.statusText);
                }
            });

            return;
        }


        // 🔄 Aksi biasa (toggle)
        if ($btn.hasClass("active")) {
            $btn.removeClass("active");

            $.ajax({
                url: "/api/local-referee-actions/cancel",
                method: "POST",
                data: {
                    match_id: matchId,
                    round_id: roundId,
                    action: action,
                    corner: corner
                },
                success: function (res) {
                    console.log("🧹 Undo berhasil:", res);
                },
                error: function (xhr) {
                    console.error("❌ Gagal undo:", xhr.responseJSON?.message || xhr.statusText);
                }
            });
        } else {
            $btn.addClass("active");

            if (action === 'verifikasi_jatuhan' || action === 'verifikasi_hukuman') {
                $.post("/api/request-verification", {
                    match_id: matchId,
                    round_id: roundId,
                    type: action === 'verifikasi_jatuhan' ? 'jatuhan' : 'hukuman',
                    corner: corner,
                }).done(function (res) {
                    console.log("✅ Verification request sent", res);
                }).fail(function (xhr) {
                    console.error("❌ Gagal kirim request verifikasi:", xhr.responseJSON?.message || xhr.statusText);
                });
            } else {
                $.post("/api/local-referee-actions", {
                    local_match_id: matchId,
                    round_id: roundId,
                    action: action,
                    point_change: point,
                    corner: corner,
                }).done(function (res) {
                    console.log("✅ Referee action sent", res);
                }).fail(function (xhr) {
                    console.error("❌ Gagal kirim tindakan:", xhr.responseJSON?.message || xhr.statusText);
                });
            }
        }
    });



        

    function resetRefereeActions() {
        $(".item, .drop").removeClass('active');
        //$(".item, .drop").prop("disabled", true).addClass("disabled");
    }
    
    

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
            currentArena = data.arena_name;
            $("#tournament-name").text(data.tournament_name);
            $("#match-code").text(data.arena_name + " Partai " + data.match_number);
            $("#class-name").text(data.class_name);
            $("#blue-name").html(`
                ${data.blue.name}<br>
                <small>${data.blue.contingent}</small>
            `);
            $("#red-name").html(`
                ${data.red.name}<br>
                <small>${data.red.contingent}</small>
            `);
            $("#blue-score").text(data.blue.score);
            $("#red-score").text(data.red.score);

            const maxRound = Math.max(...data.rounds.map(r => r.round_number));
            const roundLabels = getRoundLabels(maxRound);

            $("#stage").text(data.round_label);
  

            const activeRound = data.rounds.find(r => r.status === 'in_progress') || data.rounds[0];
            roundId = activeRound?.id || null;
            $("#current-round").text(`ROUND ${activeRound?.round_number || 1}`);
            $(".loader-bar").hide();
        });
    }

    $("#match-code").on("click", function () {
        if (!currentArena) return;

        const matchList = $("#match-list");
        matchList.empty();

        // Fetch dan tampilkan modal SETELAH data siap
        $.get(`${url}/api/local-matches`, function (groupedMatches) {
            const arenaMatches = [];
            $.each(groupedMatches[currentArena], function (poolName, matches) {
                arenaMatches.push(...matches);
            });

            arenaMatches.sort((a, b) => a.match_number - b.match_number);

            arenaMatches.forEach(match => {
                const li = $(`
                    <li class="list-group-item list-group-item-action bg-dark text-white"
                        style="cursor:pointer;" data-id="${match.id}">
                        PARTAI ${match.match_number}
                    </li>
                `);

                li.on("click", function () {
                    const selectedId = $(this).data("id");

                    $("#matchListModal").modal("hide");

                    // ✅ Redirect ke halaman detail partai
                    window.location.href = `/matches/referees/${selectedId}`;
                });

                matchList.append(li);
            });

            // ✅ Modal baru ditampilkan setelah data selesai di-append
            $("#matchListModal").modal("show");
        });
    });

    
});

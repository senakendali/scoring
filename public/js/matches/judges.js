$(document).ready(function () {
    const url = APP.baseUrl;
    let currentRoundNumber = 1;

    let matchId = parseInt($("#match-id").val());
    let roundId = null;
    let judgeNumber = $("#judge-number").val();

    let currentArena = null;

    let lastBlue = 0;
    let lastRed = 0;
    

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

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
    
    //const globalChannel = pusher.subscribe('global.match');
    const arenaSlug = $("#session-arena").val()?.toLowerCase().replace(/\s+/g, '-');
    const globalChannel = pusher.subscribe(`arena.match.${arenaSlug}`);
    const channel = pusher.subscribe(`match.${matchId}`);

    channel.bind_global(function (event, data) {
        console.log("🌍 Global Event:", event, data);
    });

    globalChannel.bind('match.changed', function (data) {
        console.log("🎯 Match changed to:", data.new_match_id);
        window.location.href = url + `/matches/judges/${data.new_match_id}`; // atau operator, screen, sesuai page lo
    });
    
    channel.bind('timer.started', function (data) {
        console.log("🔥 Timer started event di juri:", data);
    
        // Update roundId
        roundId = data.round_id;

        // Update currentRoundNumber
        currentRoundNumber = data.round_number || 1;

        
        const roundNumber = data.round_number || 1;
        
        // Tampilkan tulisan "START" lalu balik ke "ROUND x"
        const originalText = `ROUND ${roundNumber}`;
        $("#current-round").text("START");
        setTimeout(() => {
            $("#current-round").text(originalText);
        }, 3000);
    
        // ✅ ENABLE semua tombol setelah start
        $(".button-item").prop("disabled", false);
    });
    
    
    channel.bind('timer.updated', function (data) {
        console.log("🕒 Timer updated:", data);
    
        roundId = data.round_id;
    
        if (data.status === 'in_progress') {
            $("#current-round").text(`ROUND ${currentRoundNumber}`);
            $(".button-item").prop("disabled", false);
        } else if (data.status === 'paused') {
            $("#current-round").text("PAUSED");
            $(".button-item").prop("disabled", true);
        } else if (data.status === 'finished') {
            $("#current-round").text("FINISHED");
            //$(".button-item").prop("disabled", true);
        } else if (data.status === 'not_started') {
            $("#current-round").text(`ROUND ${currentRoundNumber}`);
        }
    });

    // Variabel global untuk simpan data verifikasi
    let currentVerification = {};

    // Dengerin event VerificationRequested
    let myModal = null;

    Echo.channel('match.' + matchId)
        .listen('.verification.requested', (e) => {
            console.log('Verification Requested:', e);

            // Simpan data untuk submit nanti
            currentVerification = {
                match_id: e.match_id,
                round_id: e.round_id,
                type: e.type,
            };

            // Update teks deskriptif
            let description = '';
            if (e.type === 'jatuhan') {
                description = 'Verifikasi Jatuhan';
            } else if (e.type === 'hukuman') {
                description = 'Verifikasi Hukuman';
            } else {
                description = 'Verifikasi';
            }

            $('#verificationVoteQuestion').html(`<b>${description}</b>`);

            // Buka Modal + Set agar tidak bisa ditutup
            myModal = new bootstrap.Modal(document.getElementById('verificationVoteModal'), {
                backdrop: 'static',    // Klik di luar modal tidak menutup
                keyboard: false        // Tombol ESC tidak menutup
            });
            myModal.show();
        });


    
    $('#voteBlue').click(function () {
        submitVerificationVote('blue');
    });
    
    $('#voteInvalid').click(function () {
        submitVerificationVote('invalid');
    });
    
    $('#voteRed').click(function () {
        submitVerificationVote('red');
    });

    /*$('.button-item').on('mouseenter', function () {
        alert('hovering');
        $(this).addClass('hovering');
    });

    $('.button-item').on('mouseleave', function () {
        $(this).removeClass('hovering');
    });

    $('.button-item').on('click touchstart', function () {
        const $btn = $(this);
        $btn.removeClass('hovering');

        // Biarkan efek :active dari CSS jalan, bersihkan hovering setelah delay kecil
        setTimeout(() => {
            $btn.removeClass('hovering');
        }, 100);
    });*/

    
    function submitVerificationVote(vote) {
        if (!currentVerification.match_id || !currentVerification.round_id) return;
    
        $.ajax({
            url: url + '/api/submit-verification-vote',
            method: 'POST',
            data: {
                match_id: currentVerification.match_id,
                round_id: currentVerification.round_id,
                vote: vote,
                judge_name: 'Juri ' + judgeNumber,
            },
            success: function (response) {
                console.log('Vote submitted:', response);
                if (myModal) {
                    myModal.hide();
                }
            },
            error: function (xhr) {
                console.error('Vote failed:', xhr.responseText);
                alert('Gagal mengirim vote.');
            }
        });
    }
        


   
    
    
    
    
    //$(".button-item").prop("disabled", true); // Disable semua tombol diawal

    
    
    

    // ✅ Setup juri jika belum
    initializeScoring();
    loadJudgeRecap();

    function updateJudgeIcon(corner, judgeNumber, type) {
        const judgeEl = $(`#judge-${corner}-${judgeNumber}`);
    
        if (!judgeEl.length) return; // Kalau elemen gak ketemu, skip
    
        // Simpan teks asli dulu
        const originalText = `J${judgeNumber}`;
    
        // Ganti jadi ikon
        if (type === 'kick') {
            judgeEl.html('<img src="/images/kick-icon.png" style="height:40px;">');
        } else if (type === 'punch') {
            judgeEl.html('<img src="/images/punch-icon.png" style="height:40px;">');
        }
    
        // Setelah 2 detik, kembalikan lagi ke tulisan "J1", "J2", dll
        setTimeout(() => {
            judgeEl.text(originalText);
        }, 2000);
    }
    

    function initializeScoring() {
        fetchMatchData();
        
        $(".button-item").on("click", function () {
            const $btn = $(this);
            const corner = $btn.data("corner");
            const type = $btn.data("type");

            submitPoint(corner, type);

            // ✅ Efek aktif: transform + background
            if (corner === 'blue') {
                $btn.css({
                    color: "#FFFFFF",
                    borderColor: "#FFFFFF",
                    transform: "scale(0.90)" // efek kecil
                });
            } else if (corner === 'red') {
                $btn.css({
                    color: "#FFFFFF",
                    borderColor: "#FFFFFF",
                    transform: "scale(0.90)" // juga kasih efek kecil
                });
            }

            // ✅ Setelah 200ms (lebih realistis untuk klik), reset styling
            setTimeout(() => {
                $btn.css({
                    backgroundColor: "",
                    color: "",
                    borderColor: "",
                    transform: "" // reset efek scale biar balik normal
                });
            }, 200);
        });

        
    }

    function fetchMatchData() {
        $(".loader-bar").show();
        $.get(url + `/api/local-matches/${matchId}`, function (data) {
            currentArena = data.arena_name;
            $("#tournament-name").text(data.tournament_name.replace("Pencak Silat", "").trim());
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
                    window.location.href = url + `/matches/judges/${selectedId}`;
                });

                matchList.append(li);
            });

            // ✅ Modal baru ditampilkan setelah data selesai di-append
            $("#matchListModal").modal("show");
        });
    });

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
        if (!matchId || !roundId || !judgeNumber) {
            console.warn("❌ Data belum lengkap untuk submit skor");
            return;
        }
        
    
        // 🔥 Submit ke server
        $.post(url + `/api/local-judge-scores`, {
            match_id: matchId,
            round_id: roundId,
            judge_number: judgeNumber,
            judge_name: 'Juri ' + judgeNumber,
            corner: corner,
            type: type
        })
        .done(function (response) {
            console.log("✅ Point submitted", response);
    
            // 1. Inject langsung ke UI dulu biar user ngerasa responsif
            const roundNumber = parseInt($("#current-round").text().replace('ROUND', '').trim());
            const container = response.corner === 'blue'
                ? $(`.judges-recapitulation:nth-child(${roundNumber}) .blue-recapitulation`)
                : $(`.judges-recapitulation:nth-child(${roundNumber}) .red-recapitulation`);
    
            const value = response.value; // 1 = punch, 2 = kick
            const colorClass = response.valid ? 'btn-success' : 'btn-secondary';
    
            const span = $(`<span class="roboto-bold btn btn-sm ${colorClass} pop-animate">${value}</span>`);
            container.append(span);
    
            // 2. 🔥 Tetap reload full recap dari server supaya data fix
            setTimeout(() => {
                loadJudgeRecap();
            }, 500); // kasih delay dikit biar smooth
        })
        .fail(function (xhr) {
            console.error("❌ Gagal submit point:", xhr.responseJSON?.message || xhr.statusText);
        });
    }
    
    
    function loadJudgeRecap() {
        const matchId = $("#match-id").val();
    
        $.get(url + `/api/local-matches/${matchId}/judge-recap`, function(response) {
            response.rounds.forEach((round, index) => {
                const roundNumber = index + 1;
    
                // BLUE
                const blueRecapContainer = $(`.judges-recapitulation:nth-child(${roundNumber}) .blue-recapitulation`);
                const blueSpans = blueRecapContainer.children();
                round.blue.forEach((point, idx) => {
                    const value = point.type === 'kick' ? 2 : 1;
                    const colorClass = point.valid ? 'btn-success' : 'btn-secondary';
    
                    if (blueSpans[idx]) {
                        // ✅ Update span lama
                        $(blueSpans[idx])
                            .removeClass('btn-success btn-secondary')
                            .addClass(colorClass)
                            .text(value);
                    } else {
                        // ✅ Kalau belum ada, baru append baru
                        const span = $(`<span class="roboto-bold btn ${colorClass} pop-animate">${value}</span>`);
                        blueRecapContainer.append(span);
                    }
                });
    
                // RED
                const redRecapContainer = $(`.judges-recapitulation:nth-child(${roundNumber}) .red-recapitulation`);
                const redSpans = redRecapContainer.children();
                round.red.forEach((point, idx) => {
                    const value = point.type === 'kick' ? 2 : 1;
                    const colorClass = point.valid ? 'btn-success' : 'btn-secondary';
    
                    if (redSpans[idx]) {
                        $(redSpans[idx])
                            .removeClass('btn-success btn-secondary')
                            .addClass(colorClass)
                            .text(value);
                    } else {
                        const span = $(`<span class="roboto-bold btn ${colorClass} pop-animate">${value}</span>`);
                        redRecapContainer.append(span);
                    }
                });
    
            });
        });
    }
    
    
    
    
});

$(document).ready(function () {
    var url = window.location.origin + '/digital_scoring/scoring/public';
    let matchId = parseInt($("#match-id").val());

    let currentArena = null;

    const preloadImages = {
        kick: new Image(),
        punch: new Image()
    };

    let fallCounter = {
        blue: 0,
        red: 0
    };


    preloadImages.kick.src = "/images/kick-icon.png";
    preloadImages.punch.src = "/images/punch-icon.png";

    console.log("🟢 Arena JS Ready, Match ID:", matchId);

    let roundId = null;
    let countdownInterval = null;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    fetchMatchData();

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
    //const globalChannel = pusher.subscribe(`global.match.${matchId}`);
    const arenaSlug = $("#session-arena").val()?.toLowerCase().replace(/\s+/g, '-');
    const globalChannel = pusher.subscribe(`arena.match.${arenaSlug}`);

    channel.bind('referee.action.cancelled', function (data) {
        console.log("⛔ Referee Action Cancelled:", data);

        const selector = `.item[data-action="${data.action}"][data-corner="${data.corner}"], .drop[data-action="${data.action}"][data-corner="${data.corner}"]`;
        $(selector).removeClass('active');

        
    });

    /*channel.bind('referee.action', function (data) {
        console.log("📣 Referee Action Received:", data);
    
        const selector = `.item[data-action="${data.action}"][data-corner="${data.corner}"]`;
    
        const $item = $(selector);
        $item.addClass("active");
    
        // Kasih efek sebentar (misalnya 2 detik)
        setTimeout(() => {
            $item.removeClass("active");
        }, 1000);
    });*/

    // Referee Action dari Dewan
    // Backup score sebelumnya
    // Siapkan backup score global
    let backupScores = {
        blue: null,
        red: null
    };
    
    let lockScore = {
        blue: false,
        red: false
    };
    

    channel.bind('referee.action.submitted', function (data) {
        console.log("🧩 Referee Action Submitted:", data);
    
        const selector = `.item[data-action="${data.action}"][data-corner="${data.corner}"], .drop[data-action="${data.action}"][data-corner="${data.corner}"]`;
        const $el = $(selector);
        $el.addClass('active');
    
        if (data.action === 'jatuhan') {
            console.log('💧 Detected Jatuhan');

            if (fallCounter[data.corner] !== undefined) {
                fallCounter[data.corner] += 3;
                $(`#${data.corner}-fall-count`).text(fallCounter[data.corner]);
            }

    
            const dropIcon = $(`
                <div class="drop-effect-name"><img src="/images/drop-icon.png" alt="Jatuhan"></div>
            `);
    
            if (data.corner === 'blue') {
                // 🔵 Kalau blue, append setelah #blue-name
                $("#blue-name").after(dropIcon);
            } else if (data.corner === 'red') {
                // 🔴 Kalau red, prepend sebelum #red-name
                $("#red-name").before(dropIcon);
            }
    
            // 🔥 Hapus icon setelah 5 detik
            setTimeout(() => {
                dropIcon.remove();
            }, 5000);
        }
    });
    
    
    


    
    

    

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
        window.location.href = `/matches/display-arena/${data.new_match_id}`; // Sesuaikan path kalau perlu
    });

    // ✅ Timer Started
    channel.bind('timer.started', function (data) {
        console.log("🔥 Timer started:", data);
        roundId = data.round_id;

        $("#current-round").text(`ROUND ${data.round_number}`);
        //$("#blue-score").text("0");
        //$("#red-score").text("0");

        startCountdown(new Date(data.start_time).getTime(), data.duration || 180);
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

             // ✅ Reset jatuhan
            fallCounter.blue = 0;
            fallCounter.red = 0;
            $("#blue-fall-count").text(0);
            $("#red-fall-count").text(0);


            
            //resetScoreBackground();
        } else if (data.status === 'not_started') {
            clearInterval(countdownInterval);
            $(".timer").text("00:00");
        }
    });

    // ✅ Score Updated
    channel.bind('score.updated', function (data) {
        console.log("🎯 Score updated:", data);

        const blueScore = parseInt(data.blueScore) || 0;
        const redScore = parseInt(data.redScore) || 0;

        // 🔥 Update Blue Score kalau tidak lock
        if (!lockScore.blue) {
            $("#blue-score")
                .text(blueScore)
                .addClass("bounce");

            setTimeout(() => {
                $("#blue-score").removeClass("bounce");
            }, 600);
        }

        // 🔥 Update Red Score kalau tidak lock
        if (!lockScore.red) {
            $("#red-score")
                .text(redScore)
                .addClass("bounce");

            setTimeout(() => {
                $("#red-score").removeClass("bounce");
            }, 600);
        }

        if (typeof data.blueFallCount !== 'undefined') {
            fallCounter.blue = data.blueFallCount;
            $("#blue-fall-count").text(fallCounter.blue);
        }
        if (typeof data.redFallCount !== 'undefined') {
            fallCounter.red = data.redFallCount;
            $("#red-fall-count").text(fallCounter.red);
        }

        // 🔥 Tetap update penyesuaian
        $(".arena-container .blue .score").text(data.blueAdjustment > 0 ? "+" + data.blueAdjustment : data.blueAdjustment);
        $(".arena-container .red .score").text(data.redAdjustment > 0 ? "+" + data.redAdjustment : data.redAdjustment);

        // 🔥 Update background berdasarkan skor
        if (blueScore > redScore) {
            $("#blue-score").css({
                backgroundColor: "#4E25FF",
                color: "#FFFFFF",
            });
            $(".blue .additional-score").css({
                backgroundColor: "#4E25FF",
                color: "#FFFFFF",
            });

            $("#red-score").css({
                backgroundColor: "#FFFFFF",
                color: "#D32F2F"
            });
            $(".red .additional-score").css({
                backgroundColor: "#FFFFFF",
                color: "#D32F2F"
            });

        } else if (redScore > blueScore) {
            $("#red-score").css({
                backgroundColor: "#E8003F",
                color: "#FFFFFF",
            });
            $(".red .additional-score").css({
                backgroundColor: "#E8003F",
                color: "#FFFFFF",
            });

            $("#blue-score").css({
                backgroundColor: "#FFFFFF",
                color: "#4E25FF"
            });
            $(".blue .additional-score").css({
                backgroundColor: "#FFFFFF",
                color: "#4E25FF"
            });

        } else {
            // Kalau imbang, reset warna dulu
            $("#blue-score").css({
                backgroundColor: "#FFFFFF",
                color: "#4E25FF"
            });
            $(".blue .additional-score").css({
                backgroundColor: "#FFFFFF",
                color: "#4E25FF"
            });

            $("#red-score").css({
                backgroundColor: "#FFFFFF",
                color: "#D32F2F"
            });
            $(".red .additional-score").css({
                backgroundColor: "#FFFFFF",
                color: "#D32F2F"
            });

            // 💥 Jika ada winner_corner saat skor imbang, tampilkan highlight pemenang
            if (data.winner_corner === 'blue') {
                $("#blue-score").css({
                    backgroundColor: "#4E25FF",
                    color: "#FFFFFF"
                });
                $(".blue .additional-score").css({
                    backgroundColor: "#4E25FF",
                    color: "#FFFFFF"
                });

            } else if (data.winner_corner === 'red') {
                $("#red-score").css({
                    backgroundColor: "#E8003F",
                    color: "#FFFFFF"
                });
                $(".red .additional-score").css({
                    backgroundColor: "#E8003F",
                    color: "#FFFFFF"
                });
            }
        }
    });

    
    
    
    

    channel.bind('judge.action.submitted', function (data) {
        console.log("🎯 Judge Action Submitted:", data);
    
        const { corner, judge_number, type } = data;
        console.log(`⏩ Update Judge Icon: corner=${corner}, judge=${judge_number}, type=${type}`);
    
        updateJudgeIcon(corner, judge_number, type);
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
                <div class="progress mb-3" style="height: 20px;">
                <div class="progress-bar bg-primary" role="progressbar" style="width: ${bluePercent}%;">${bluePercent}%</div>
                </div>

                <div class="text-start mb-2">Merah (${redVotes} vote)</div>
                <div class="progress mb-3" style="height: 20px;">
                <div class="progress-bar bg-danger" role="progressbar" style="width: ${redPercent}%;">${redPercent}%</div>
                </div>

                <div class="text-start mb-2">Tidak Sah (${invalidVotes} vote)</div>
                <div class="progress mb-2" style="height: 20px;">
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


    
    function resetScoreBackground() {
        $("#blue-score").css({
            backgroundColor: "#FFFFFF",
            color: "#4E25FF"
        });
        $(".blue .additional-score").css({
            backgroundColor: "#FFFFFF",
            color: "#4E25FF"
        });
    
        $("#red-score").css({
            backgroundColor: "#FFFFFF",
            color: "#D32F2F"
        });
        $(".red .additional-score").css({
            backgroundColor: "#FFFFFF",
            color: "#D32F2F"
        });
    }
        
        
    
    function resetRefereeActions() {
        $(".item, .drop").removeClass('active');
    }

    function updateJudgeIcon(corner, judgeNumber, type) {
        const judgeEl = $(`#judge-${corner}-${judgeNumber}`);

        console.log(`🛠️ Try update #judge-${corner}-${judgeNumber}`);

        if (!judgeEl.length) {
            console.warn("❌ Element tidak ditemukan:", `#judge-${corner}-${judgeNumber}`);
            return;
        }

        judgeEl.addClass("active");
        const originalText = `J${judgeNumber}`;

        if (type === 'kick' || type === 'punch') {
            const img = preloadImages[type].cloneNode(); // ⬅️ gunakan gambar dari cache
            img.style.height = "40px";
            judgeEl.html(img);
        } else {
            console.warn("❌ Unknown type:", type);
        }

        setTimeout(() => {
            judgeEl.text(originalText);
            judgeEl.removeClass("active");
        }, 2000);
    }

    

    function updateJudgeIcon_(corner, judgeNumber, type) {
        const judgeEl = $(`#judge-${corner}-${judgeNumber}`);
    
        console.log(`🛠️ Try update #judge-${corner}-${judgeNumber}`);
    
        if (!judgeEl.length) {
            console.warn("❌ Element tidak ditemukan:", `#judge-${corner}-${judgeNumber}`);
            return;
        }

        // Tambah class active
        judgeEl.addClass("active");
    
        const originalText = `J${judgeNumber}`;
    
        if (type === 'kick') {
            judgeEl.html('<img src="/images/kick-icon.png" style="height:40px;">');
        } else if (type === 'punch') {
            judgeEl.html('<img src="/images/punch-icon.png" style="height:40px;">');
        } else {
            console.warn("❌ Unknown type:", type);
        }
    
        setTimeout(() => {
            judgeEl.text(originalText);
            judgeEl.removeClass("active");
        }, 2000);
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
        $.get(url + `/api/local-matches/${matchId}`, function (data) {
            if(data.is_display_timer != 0){
                $("#timer").show();
                $(".timer").css('color', '#000000');
            }else{
                $("#display-timer").css('height', '20px');
                $("#timer").hide();
            }
            //$("#tournament-name").text(data.tournament_name);
            currentArena = data.arena_name;
            $(".match-item").css('height', '80px');
            $("#tournament-name").text(data.tournament_name.replace("Pencak Silat", "").trim());
            $("#match-code").text(data.arena_name + " Partai " + data.match_number);
            $("#class-name").text(data.class_name);
            $("#blue-name").html(`
                ${data.blue.name}<br>
                <small>${data.blue.contingent}</small>
            `).css({
                    'font-size': '23px',
                    'font-weight': 'bold'
                });
            $("#red-name").html(`
                ${data.red.name}<br>
                <small>${data.red.contingent}</small>
            `).css({
                    'font-size': '23px',
                    'font-weight': 'bold'
                });

            const maxRound = Math.max(...data.rounds.map(r => r.round_number));
            const roundLabels = getRoundLabels(maxRound);

            $("#stage").text(data.round_label);
    
           
            const activeRound = data.rounds.find(r => r.status === 'in_progress') || data.rounds[0];
            roundId = activeRound?.id || null;
            $("#current-round").text(`ROUND ${activeRound?.round_number || 1}`) .css({
                'font-size': '23px',
                'font-weight': 'bold'
            });

            $("#blue-score").text(data.blue.score);
            $("#red-score").text(data.red.score);
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
                    window.location.href = `/matches/display-arena/${selectedId}`;
                });

                matchList.append(li);
            });

            // ✅ Modal baru ditampilkan setelah data selesai di-append
            $("#matchListModal").modal("show");
        });
    });

    
});

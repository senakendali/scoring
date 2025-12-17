$(document).ready(function () {
    const url = APP.baseUrl;
    let matchId = parseInt($("#match-id").val());

    let currentArena = null;

    console.log("🟢 Dewan JS Ready, Match ID:", matchId);

    // =========================
    // ✅ Round states
    // =========================
    let activeRoundId = null;            // ronde aktif operator (timer)
    let selectedRoundId = null;          // ronde yg dipakai input dewan
    let isManualRoundSelection = false;  // user pernah pilih ronde manual
    let roundsCache = [];               // list rounds dari API

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
    const arenaSlug = $("#session-arena").val()?.toLowerCase().replace(/\s+/g, '-');
    const globalChannel = pusher.subscribe(`arena.match.${arenaSlug}`);

    // 🔥 Saat juri tekan tombol
    channel.bind('judge.point.submitted', function (data) {
        console.log("👊 Judge point submitted:", data);

        const { judge_number, corner, type } = data;
        const selector = `.judges-${type}.${corner} .judge[data-judge="${judge_number}"]`;
        const $el = $(selector);

        $el.addClass("active");
        setTimeout(() => $el.removeClass("active"), 1000);
    });

    // ✅ Global Match Change
    globalChannel.bind('match.changed', function (data) {
        console.log("🎯 Match changed:", data);
        window.location.href = url + `/matches/referees/${data.new_match_id}`;
    });

    // ✅ Timer Started (operator start round baru)
    channel.bind('timer.started', function (data) {
        console.log("🔥 Timer started:", data);

        activeRoundId = parseInt(data.round_id);

        // ✅ Auto sync selected round ke active round,
        // kecuali dewan lagi manual pilih round lain
        if (!isManualRoundSelection) {
            selectedRoundId = activeRoundId;
            updateRoundLabel();
            loadRoundPenalties(selectedRoundId);
        } else {
            // tetep update label biar konsisten (kalau selectedRoundId kosong)
            updateRoundLabel();
        }

        startCountdown(new Date(data.start_time).getTime(), data.duration || 180);
        $(".item, .drop").prop("disabled", false).removeClass("disabled");
    });

    // ✅ Timer Updated (timer harus ngikut activeRoundId)
    channel.bind('timer.updated', function (data) {
        if (parseInt(data.round_id) !== parseInt(activeRoundId)) return;

        console.log("🕒 Timer updated:", data);

        if (data.status === 'in_progress') {
            const start = new Date(data.start_time).getTime();
            const now = new Date(data.now).getTime();
            const elapsed = Math.floor((now - start) / 1000);

            startCountdown(now - (elapsed * 1000), data.duration || 180);
        } else if (data.status === 'paused') {
            clearInterval(countdownInterval);
            $(".timer").text("PAUSED");
        } else if (data.status === 'finished') {
            clearInterval(countdownInterval);
            $(".timer").text("00:00");
            resetRefereeActions();
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

        $(".arena-container .blue .score").text(data.blueAdjustment > 0 ? "+" + data.blueAdjustment : data.blueAdjustment);
        $(".arena-container .red .score").text(data.redAdjustment > 0 ? "+" + data.redAdjustment : data.redAdjustment);
    });

    // =========================
    // ✅ Verification modals (unchanged)
    // =========================
    let waitingModalInstance = null;
    let waitingProgressInterval = null;

    Echo.channel('match.' + matchId)
        .listen('.verification.requested', (e) => {
            console.log('Verification Requested (Arena/Dewan):', e);

            let description = '';
            if (e.type === 'jatuhan') description = 'Menunggu hasil verifikasi Jatuhan...';
            else if (e.type === 'hukuman') description = 'Menunggu hasil verifikasi Hukuman...';
            else description = 'Menunggu hasil verifikasi...';

            $('#waitingVerificationMessage').html(`<b>${description}</b>`);
            $('#waitingVerificationProgress').css('width', '0%');

            waitingModalInstance = new bootstrap.Modal(document.getElementById('waitingVerificationModal'), {
                backdrop: 'static',
                keyboard: false
            });
            waitingModalInstance.show();

            let progress = 0;
            waitingProgressInterval = setInterval(() => {
                progress += 2;
                if (progress > 100) progress = 100;
                $('#waitingVerificationProgress').css('width', `${progress}%`);
            }, 300);
        });

    let verificationResultModalInstance = null;
    let verificationResultTimer = null;

    Echo.channel('match.' + matchId)
        .listen('.verification.resulted', (e) => {
            console.log('Verification Resulted:', e);

            if (waitingModalInstance) waitingModalInstance.hide();

            let totalVotes = e.results.length;
            let blueVotes = e.results.filter(v => v.vote === 'blue').length;
            let redVotes = e.results.filter(v => v.vote === 'red').length;
            let invalidVotes = e.results.filter(v => v.vote === 'invalid').length;

            let bluePercent = totalVotes ? (blueVotes / totalVotes * 100).toFixed(0) : 0;
            let redPercent = totalVotes ? (redVotes / totalVotes * 100).toFixed(0) : 0;
            let invalidPercent = totalVotes ? (invalidVotes / totalVotes * 100).toFixed(0) : 0;

            let actionLabel = e.type === 'jatuhan' ? 'Jatuhan' : 'Hukuman';
            let cornerLabel = e.corner === 'blue' ? 'sudut Biru' : 'sudut Merah';
            let titleText = `${actionLabel} untuk ${cornerLabel}`;

            let modalEl = document.getElementById('verificationResultModal');
            modalEl.classList.remove('bg-blue', 'bg-red');
            if (e.corner === 'blue') modalEl.classList.add('bg-blue');
            else modalEl.classList.add('bg-red');

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

            verificationResultModalInstance = new bootstrap.Modal(document.getElementById('verificationResultModal'));
            verificationResultModalInstance.show();

            if (verificationResultTimer) clearTimeout(verificationResultTimer);

            verificationResultTimer = setTimeout(() => {
                verificationResultModalInstance.hide();
            }, 5000);
        });

    // =========================
    // ✅ ROUND PICKER + LOAD STATE PER ROUND
    // =========================
    function getEffectiveRoundId() {
        return selectedRoundId ?? activeRoundId;
    }

    function updateRoundLabel() {
        const selected = roundsCache.find(r => parseInt(r.id) === parseInt(selectedRoundId));
        const active = roundsCache.find(r => parseInt(r.id) === parseInt(activeRoundId));

        const labelNumber = selected?.round_number ?? active?.round_number ?? 1;
        $("#current-round").text(`ROUND ${labelNumber}`);
    }

    function loadRoundPenalties(roundId) {
        if (!roundId) return;

        // reset semua toggle
        $('[data-action][data-corner]').removeClass('active');

        $.get(url + `/api/local-referee-actions/round-penalties`, {
            local_match_id: matchId,
            round_id: roundId,
        }).done(function (res) {
            // res: { blue:{active:[], jatuhan_total}, red:{active:[], jatuhan_total} }
            (res.blue?.active || []).forEach(action => {
                $(`[data-action="${action}"][data-corner="blue"]`).addClass('active');
            });

            (res.red?.active || []).forEach(action => {
                $(`[data-action="${action}"][data-corner="red"]`).addClass('active');
            });

        }).fail(function (xhr) {
            console.error("❌ Gagal load penalty round:", roundId, xhr.responseJSON?.message || xhr.statusText);
        });
    }


    function loadRoundPenalties_(roundId) {
        if (!roundId) return;

        // reset semua toggle
        $('[data-action][data-corner]').removeClass('active');

        $.get(url + `/api/local-referee-actions/round-penalties`, {
            local_match_id: matchId,
            round_id: roundId,
        }).done(function (res) {
            // res: { blue:{active:[], jatuhan_total}, red:{active:[], jatuhan_total} }
            (res.blue?.active || []).forEach(action => {
                $(`[data-action="${action}"][data-corner="blue"]`).addClass('active');
            });

            (res.red?.active || []).forEach(action => {
                $(`[data-action="${action}"][data-corner="red"]`).addClass('active');
            });
        }).fail(function (xhr) {
            console.error("❌ Gagal load penalty round:", roundId, xhr.responseJSON?.message || xhr.statusText);
        });
    }

    $("#current-round").css("cursor", "pointer").on("click", function () {
        if (!roundsCache.length) return;

        const sorted = [...roundsCache].sort((a, b) => (a.round_number || 0) - (b.round_number || 0));

        const html = sorted.map(r => {
            const isActive = activeRoundId && parseInt(r.id) === parseInt(activeRoundId);
            const isSelected = selectedRoundId && parseInt(r.id) === parseInt(selectedRoundId);

            return `
              <button type="button"
                class="list-group-item list-group-item-action bg-dark text-white round-pick ${isSelected ? 'active' : ''}"
                data-id="${r.id}">
                ROUND ${r.round_number}
                ${isActive ? '<span class="badge bg-success ms-2">ACTIVE</span>' : ''}
              </button>
            `;
        }).join('');

        $("#roundList").html(html);
        $("#roundPickerModal").modal("show");
    });

    $(document).on("click", ".round-pick", function () {
        selectedRoundId = parseInt($(this).data("id"));
        isManualRoundSelection = true;

        updateRoundLabel();
        loadRoundPenalties(selectedRoundId);

        $("#roundPickerModal").modal("hide");
    });

    // =========================
    // ✅ Button actions (round_id = selectedRoundId)
    // =========================
    $(".item[data-action], .drop[data-action]").on("click", function () {
        const $btn = $(this);
        const action = $btn.data("action");
        const point = $btn.data("point");
        const corner = $btn.data("corner");

        const effectiveRoundId = getEffectiveRoundId();
        if (!effectiveRoundId) {
            console.warn("⚠️ roundId belum ada, abaikan action:", action);
            return;
        }

        // 🔁 DROP boleh diklik berkali-kali
        if (action === 'drop') {
            $.post(url + "/api/local-referee-actions", {
                local_match_id: matchId,
                round_id: effectiveRoundId,
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

        // 🔁 JATUHAN boleh diklik berkali-kali (counter)
        if (action === 'jatuhan') {
            $.post(url + "/api/local-referee-actions", {
                local_match_id: matchId,
                round_id: effectiveRoundId,
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

        // 🔁 HAPUS JATUHAN (per round)
        if (action === 'hapus-jatuhan') {
            $.ajax({
                url: url + "/api/local-referee-actions/remove-jatuhan",
                method: "POST",
                data: {
                    local_match_id: matchId,
                    round_id: effectiveRoundId,
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

        // 🔄 Aksi penalty toggle (binaan/teguran/peringatan)
        if ($btn.hasClass("active")) {
            $btn.removeClass("active");

            $.ajax({
                url: url + "/api/local-referee-actions/cancel",
                method: "POST",
                data: {
                    match_id: matchId,
                    round_id: effectiveRoundId,
                    action: action,
                    corner: corner
                },
                success: function (res) {
                    console.log("🧹 Undo berhasil:", res);
                    // reload state supaya konsisten
                    loadRoundPenalties(effectiveRoundId);
                },
                error: function (xhr) {
                    console.error("❌ Gagal undo:", xhr.responseJSON?.message || xhr.statusText);
                    // revert UI kalau gagal
                    $btn.addClass("active");
                }
            });
        } else {
            $btn.addClass("active");

            if (action === 'verifikasi_jatuhan' || action === 'verifikasi_hukuman') {
                $.post(url + "/api/request-verification", {
                    match_id: matchId,
                    round_id: effectiveRoundId,
                    type: action === 'verifikasi_jatuhan' ? 'jatuhan' : 'hukuman',
                    corner: corner,
                }).done(function (res) {
                    console.log("✅ Verification request sent", res);
                }).fail(function (xhr) {
                    console.error("❌ Gagal kirim request verifikasi:", xhr.responseJSON?.message || xhr.statusText);
                    $btn.removeClass("active");
                });
            } else {
                $.post(url + "/api/local-referee-actions", {
                    local_match_id: matchId,
                    round_id: effectiveRoundId,
                    action: action,
                    point_change: point,
                    corner: corner,
                }).done(function (res) {
                    console.log("✅ Referee action sent", res);
                    // reload state supaya konsisten
                    loadRoundPenalties(effectiveRoundId);
                }).fail(function (xhr) {
                    console.error("❌ Gagal kirim tindakan:", xhr.responseJSON?.message || xhr.statusText);
                    $btn.removeClass("active");
                });
            }
        }
    });

    function resetRefereeActions() {
        // Reset UI hanya tampilan, bukan data.
        // Toggle state akan di-load lagi dari selectedRoundId
        $(".item, .drop").each(function () {
            const action = $(this).data('action');
            if (action !== 'peringatan_1' && action !== 'peringatan_2') {
                $(this).removeClass('active');
            }
        });

        // reload state round yang sedang dipilih
        const rid = getEffectiveRoundId();
        if (rid) loadRoundPenalties(rid);
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

    function formatTime(seconds) {
        if (isNaN(seconds) || seconds < 0) seconds = 0;
        const min = Math.floor(seconds / 60).toString().padStart(2, '0');
        const sec = (seconds % 60).toString().padStart(2, '0');
        return `${min}:${sec}`;
    }

    function fetchMatchData() {
        $(".loader-bar").show();

        $.get(url + `/api/local-matches/${matchId}`, function (data) {
            currentArena = data.arena_name;

            $("#tournament-name").text(data.tournament_name);
            $("#match-code").text(data.arena_name + " Partai " + data.match_number);
            $("#class-name").text(data.class_name);

            $("#blue-name").html(`${data.blue.name}<br><small>${data.blue.contingent}</small>`);
            $("#red-name").html(`${data.red.name}<br><small>${data.red.contingent}</small>`);

            $("#blue-score").text(data.blue.score);
            $("#red-score").text(data.red.score);

            roundsCache = data.rounds || [];
            $("#stage").text(data.round_label);

            const activeRound = roundsCache.find(r => r.status === 'in_progress') || roundsCache[0];
            activeRoundId = activeRound?.id || null;

            // default: selected round ikut active round saat load pertama
            if (!selectedRoundId) {
                selectedRoundId = activeRoundId;
                isManualRoundSelection = false;
            }

            updateRoundLabel();
            loadRoundPenalties(selectedRoundId);

            $(".loader-bar").hide();
        });
    }

    $("#match-code").on("click", function () {
        if (!currentArena) return;

        const matchList = $("#match-list");
        matchList.empty();

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
                    window.location.href = url + `/matches/referees/${selectedId}`;
                });

                matchList.append(li);
            });

            $("#matchListModal").modal("show");
        });
    });
});

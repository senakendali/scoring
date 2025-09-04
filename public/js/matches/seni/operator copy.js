$(document).ready(function () {
    const url = APP.baseUrl;
    const matchId = window.location.pathname.split("/").pop();
    const timerEl = $("#timer");
    
    let duration = 600;
    
    let interval = null;
    let roundId = null;
    let currentRoundNumber = 1;
    let totalRounds = 3;
    let allRounds = [];
    let elapsed = 0;
    let isPaused = false;

    let currentArena = null;
    


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

    function applyCornerBackground(corner) {
        if(corner){
             $(".seni-participant-detail").css('border-bottom', 'none');
            $(".match-header .match-item .seni-participant-detail .item").css('background', 'none');
        }
       
       
        var clsBlue = 'corner-blue-bg';
        var clsRed  = 'corner-red-bg';
        var clsNone = 'corner-none-bg';

        var $targets = $('#contingent-name, #participant-1, #participant-2, #participant-3');
        $targets.removeClass(clsBlue + ' ' + clsRed + ' ' + clsNone);

        var c = (corner || '').toString().toLowerCase();
        if (c === 'blue') {
            $targets.addClass(clsBlue);
        } else if (c === 'red') {
            $targets.addClass(clsRed);
        }
    }

    let currentMatchData = null;

    // ==== Helper ambil nama + kontingen (pakai field baru) ====
    function getParticipantName(data, key) {
    // key: '1' | '2' | '3'
    const v = data?.[`participant_${key}`];
    return (typeof v === 'string' && v.trim() !== '') ? v.trim() : null;
    }
    function getContingentName(data) {
    // langsung pakai contingent_name
    const v = data?.contingent_name;
    return (typeof v === 'string' && v.trim() !== '') ? v.trim() : '-';
    }

    // ==== Tentukan corner untuk participant_key ====
    // Prioritas:
    // 1) data.blue_participant_key / data.red_participant_key (angka/key)
    // 2) data[`participant_${key}_corner`] kalau ada
    // 3) Fallback urutan non-null: 1st = blue, 2nd = red
    function inferCornerForKey(data, key, fallbackOrderMap) {
    const k = parseInt(key, 10);

    if (data?.blue_participant_key && parseInt(data.blue_participant_key, 10) === k) return 'blue';
    if (data?.red_participant_key  && parseInt(data.red_participant_key, 10)  === k) return 'red';

    const cornerField = data?.[`participant_${k}_corner`];
    if (cornerField === 'blue' || cornerField === 'red') return cornerField;

    const order = fallbackOrderMap[key]; // 1-based
    if (order === 1) return 'blue';
    if (order === 2) return 'red';

    return null; // >2 peserta → sisanya ga punya corner
    }

    // ==== Build opsi "Nama (Kontingen) – Corner" ====
    // Handle tunggal/ganda/regu:
    // - tunggal: cuma participant_1
    // - ganda: participant_1 & participant_2 (dua peserta/entry) → dapat Blue/Red
    // - regu: kalau memang 3 entry, fallback urutan. Kalau ini sebenarnya anggota tim,
    //   tetap akan ambil 2 pertama (Blue/Red) via fallback.
    function buildWinnerOptions(data) {
    const cont = getContingentName(data);

    const participants = [
        { key: '1', name: getParticipantName(data, '1') },
        { key: '2', name: getParticipantName(data, '2') },
        { key: '3', name: getParticipantName(data, '3') },
    ].filter(p => !!p.name);

    // fallback mapping urutan non-null: 1st -> blue, 2nd -> red
    const fallbackOrderMap = {};
    let idx = 0;
    for (const p of participants) {
        idx += 1;
        fallbackOrderMap[p.key] = idx;
    }

    const options = [];
    for (const p of participants) {
        const corner = inferCornerForKey(data, p.key, fallbackOrderMap);
        if (!corner) continue; // skip kalau corner ga kebaca (entry > 2)
        options.push({
        value: `${p.key}|${corner}`,
        label: `${p.name} – ${corner === 'blue' ? 'Biru' : 'Merah'}`,
        });
    }

    // Safety: kalau kosong tapi ada peserta → paksa 2 pertama jadi Blue/Red
    if (options.length === 0 && participants.length > 0) {
        if (participants[0]) {
        options.push({
            value: `${participants[0].key}|blue`,
            label: `${participants[0].name} – Biru`,
        });
        }
        if (participants[1]) {
        options.push({
            value: `${participants[1].key}|red`,
            label: `${participants[1].name} – Merah`,
        });
        }
    }

    return options;
    }

    // ==== Open modal & isi select ====
    $(document).on('click', '.set-winner', async function () {
        const matchId = $("#match-id").val();
        if (!matchId) return;

        const $sel = $("#winner-participant").empty();
        $("#set-winner-error").addClass('d-none').text('');

        try {
            const res = await fetch(`${url}/api/seni-battle/matches/${matchId}/group-contestants`, {
            headers: { 'Accept': 'application/json' }
            });
            const json = await res.json();
            const list = Array.isArray(json.contestants) ? json.contestants : [];

            // Wajib 2 peserta
            if (list.length < 2) {
            $("#nextMatchModalBody").html(`
                <div class="text-start">
                <div class="fw-bold mb-1">Peserta belum lengkap</div>
                <div>Belum ada 2 peserta di battle group <strong>${json.battle_group ?? '-'}</strong>.</div>
                </div>
            `);
            new bootstrap.Modal(document.getElementById('nextMatchModalInfo')).show();
            return;
            }

            // Build label: "Nama (Kontingen) – Corner"
            for (const c of list) {
            const cont = (c.contingent_name || '').trim();
            const label = `${c.display_name} – ${c.corner === 'red' ? 'Merah' : 'Biru'}`;
            // value kirim kombinasi match_id|corner (aman buat submit ke API lu nanti)
            $sel.append(new Option(label, `${c.match_id}|${c.corner}`));
            }
            $sel.prop('disabled', false);

            // Tampilkan modal
            new bootstrap.Modal(document.getElementById('setWinnerModal')).show();

        } catch (e) {
            console.error(e);
            $sel.append(new Option('Gagal memuat peserta', '', true, true));
            $sel.prop('disabled', true);
            new bootstrap.Modal(document.getElementById('nextMatchModalInfo')).show();
        }
        });


    // ==== Submit (UI only) tetap sama ====
    $(document).on('click', '#submit-set-winner', async function () {
        const $btn = $(this);
        const selected = $("#winner-participant").val(); // ideal: "123|blue" (winner_match_id|corner)
        const reason = $("#winner-reason").val();

        // Ambil match id dari #set-winner-match-id ATAU #match-id (fallback)
        const baseMatchId = $("#set-winner-match-id").val() || $("#match-id").val();

        if (!selected) {
            $("#set-winner-error").removeClass('d-none').text('Pilih pemenang terlebih dahulu.');
            return;
        }
        if (!baseMatchId) {
            $("#set-winner-error").removeClass('d-none').text('Match ID tidak ditemukan.');
            return;
        }

        // Parse value option
        const [a, cornerMaybe] = selected.split('|'); // a bisa angka (winner_match_id) atau '1'/'2'/'3'
        const isNumeric = /^\d+$/.test(a);
        const payload = isNumeric
            ? { winner_match_id: Number(a), reason }                       // ✅ format baru
            : { participant_key: a, corner: cornerMaybe || 'blue', reason } // 🔙 fallback lama

        // Loading state
        $btn.prop('disabled', true).data('ori', $btn.text()).text('Menyimpan...');
            $("#set-winner-error").addClass('d-none').text('');

            try {
                const res = await fetch(`${url}/api/seni-battle/matches/${baseMatchId}/set-winner`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
                });

                const json = await res.json();
                if (!res.ok) throw new Error(json?.message || 'Gagal menyimpan pemenang');

                // Tutup modal set winner
                const modalEl = document.getElementById('setWinnerModal');
                const inst = bootstrap.Modal.getInstance(modalEl);
                if (inst) inst.hide();

                // Info sukses
                $("#nextMatchModalBody").html(
                `<div class="text-start">
                    <div class="mb-1">Pemenang: <strong>${json.winner_name || '-'}</strong></div>
                    <div class="mb-1">Alasan: <em>${json.reason_label || '-'}</em></div>
                </div>`
                );
                new bootstrap.Modal(document.getElementById('nextMatchModalInfo')).show();

                // Kunci kontrol & munculkan NEXT MATCH
                //$('.start, .pause, .reset, .skip-match, .set-winner, .end-match').prop('disabled', true);
                $('.next-match').removeClass('d-none');

                // Refresh UI match
                if (typeof fetchMatch === 'function') fetchMatch();

            } catch (err) {
                $("#set-winner-error").removeClass('d-none').text(err.message || 'Terjadi kesalahan');
            } finally {
                $btn.prop('disabled', false).text($btn.data('ori') || 'Simpan');
            }
});




    function fetchMatch() {
        $(".loader-bar").show();
        $.get(`${url}/api/local-matches/seni/${matchId}`, function (data) {
            currentMatchData = data;
            if (data.is_display_timer != 0) {
            $("#timer").show();
            } else {
            $("#display-timer").css('height', '0');
            $("#timer").hide();
            }

            $("#match-id").val(data.id);

            currentArena = data.arena_name;

           if(data.mode == 'default'){
            $(".set-winner").css('display', 'none');

           }

            $("#tournament-name").text(data.tournament_name);
            $("#match-code").text(data.arena_name + " Partai " + data.match_order);
            $("#class-name").text(data.category);
            $("#age-category").text(data.age_category);
            $("#gender").text(data.category + "  " + (data.gender === 'male' ? 'PUTRA' : 'PUTRI'));
            $("#contingent-name").text(data.contingent);

            // reset peserta
            $("#participant-1").text('-').hide();
            $("#participant-2").text('-').hide();
            $("#participant-3").text('-').hide();

            // tampilkan peserta sesuai tipe
            if (data.match_type === 'seni_tunggal' || data.match_type === 'solo_kreatif') {
            $("#participant-1").text(data.team_members[0] || '-').show();
            } else if (data.match_type === 'seni_ganda') {
            $("#participant-1").text(data.team_members[0] || '-').show();
            $("#participant-2").text(data.team_members[1] || '-').show();
            } else if (data.match_type === 'seni_regu') {
            $("#participant-1").text(data.team_members[0] || '-').show();
            $("#participant-2").text(data.team_members[1] || '-').show();
            $("#participant-3").text(data.team_members[2] || '-').show();
            }

            // <<< APPLY WARNA SESUAI CORNER DARI DB >>>
            // pastikan API detail balikin field `corner` = 'blue' | 'red' | null
            applyCornerBackground(data.corner);

            $(".loader-bar").hide();
        });
    }

    // helper: buang zero-width chars biar gak “kosong tapi ada”
    const stripZW = s => (typeof s === 'string'
    ? s.replace(/[\u200B-\u200D\u2060]/g, '').trim()
    : s);

    // helper: ambil kontingen (dukung 2 bentuk response)
    function getContingent(match){
        // v1: {contingent: {name: "..."}}  | v2: {contingent_name: "..."} | fallback: {contingent: "..."}
        return stripZW(
            match?.contingent?.name ??
            match?.contingent_name ??
            match?.contingent ??
            '-'
        ) || '-';
    }

    // helper: ambil daftar peserta (dukung 2 bentuk response)
    function getParticipants(match){
        // v1: team_member1.name / team_member2.name / team_member3.name
        const v1 = [
            stripZW(match?.team_member1?.name || ''),
            stripZW(match?.team_member2?.name || ''),
            stripZW(match?.team_member3?.name || ''),
        ].filter(Boolean);

        if (v1.length) return v1;

        // v2: participant_1 / participant_2 / participant_3 (string)
        const v2 = [
            stripZW(match?.participant_1 || ''),
            stripZW(match?.participant_2 || ''),
            stripZW(match?.participant_3 || ''),
        ].filter(Boolean);

        // v3 (fallback): participant_name (dipisah koma / pipe)
        if (!v2.length && match?.participant_name) {
            const parts = String(match.participant_name)
            .split(/[,|]/).map(x => stripZW(x)).filter(Boolean);
            if (parts.length) return parts;
        }

        return [];
    }

    $("#match-code").on("click", function () {
        const matchList = $("#match-list");
        matchList.empty();

        $.get(`${url}/api/local-matches/seni`, function (data) {
            const arenaMatches = [];

            // flatten sesuai struktur response lu
            (data || []).forEach(categoryGroup => {
            (categoryGroup.age_categories || []).forEach(ageGroup => {
                (ageGroup.pools || []).forEach(pool => {
                (pool.matches || []).forEach(m => arenaMatches.push(m));
                });
            });
            });

            arenaMatches.sort((a, b) => (a.match_order ?? 0) - (b.match_order ?? 0));

            arenaMatches.forEach(match => {
            const contingent  = getContingent(match);
            const participantsArr = getParticipants(match);
            const participants = participantsArr.join(', ');
            const corner = (match.corner || '').toString().trim();

            let label = `PARTAI ${match.match_order}`;
            // tambah kontingen & peserta kalau ada
            if (contingent !== '-' || participants) {
                label += ` — ${contingent}`;
                if (participants) label += ` (${participants})`;
            }
            // tambah corner kalau ada
            if (corner) {
                label += ` [${corner.toUpperCase()}]`;
            }

            const li = $(`
                <li class="list-group-item list-group-item-action bg-dark text-white"
                    style="cursor:pointer;" data-id="${match.id}">
                ${label}
                </li>
            `);

            li.on("click", function () {
                const selectedId = $(this).data("id");
                $("#matchListModal").modal("hide");
                window.location.href = url + `/matches/seni/${selectedId}`;
            });
            

            matchList.append(li);
            });

            $("#matchListModal").modal("show");
        });
    });



    $("#match-code__").on("click", function () {
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
                    /*$.post(`/api/local-seni-matches/${selectedId}/change`, function (res) {
                        if (res.new_match_id) {
                            window.location.href = `/matches/seni/${res.new_match_id}`;
                        } else {
                            alert("Tidak ada pertandingan berikutnya.");
                        }
                    }).fail(function (xhr) {
                        $("#nextMatchModalBody").text("Tidak ada pertandingan berikutnya. Semua pertandingan telah selesai.");
                        const modal = new bootstrap.Modal(document.getElementById('nextMatchModalInfo'));
                        modal.show();
                        console.error("❌ Gagal ganti match:", xhr.responseJSON?.message || xhr.statusText);
                    }).always(function () {
                        // Kembalikan tombol ke semula
                        $btn.prop("disabled", false).html('Redirecting to Next Match');
                    });*/

                    

                    $("#matchListModal").modal("hide");
                    window.location.href = url + `/matches/seni/${selectedId}`;
                });

                matchList.append(li);
            });

            $("#matchListModal").modal("show");
        });
    });




    function fetchAndStartTimer() {
        // matchId disiapin dari global/hidden input
        if (!matchId) return;

        $.get(`${url}/api/local-matches/seni/${matchId}`, function (data) {
            const status = data.status;
            
            elapsed = Math.max(0, Math.floor(parseFloat(data.elapsed) || 0));

            if (status === 'ongoing') {
                runTimer();
            } else if (status === 'pending') {
                stopTimer();
                timerEl.text("00:00");
            } else if (status === 'finished') {
                stopTimer();
                timerEl.text("SELESAI");
            }
        });
    }

    function runTimer() {
        clearInterval(interval);
        const maxDuration = 600; // 3 menit batas maksimal

        function updateTimer() {
            timerEl.text(formatTime(elapsed));

            if (elapsed >= maxDuration) {
                clearInterval(interval);
                // ⏱️ Auto finish jika lewat 3 menit

                 $.ajax({
                    url: `${url}/api/local-seni-matches/${matchId}/finish`,
                    method: 'PATCH',
                    data: {
                    duration: maxDuration   // ← kirim durasi final
                    },
                    success: function (resp) {
                    console.log("⏱️ Auto-finish seni (max 3 menit)");
                    stopTimer();
                    $("#timer").text("SELESAI");
                    $(".end-match").addClass("d-none");
                    $(".next-match").removeClass("d-none");
                    // (Cadangan) sama seperti di manual, boleh cek resp.battle_group_completed
                    },
                    error: function (err) {
                    console.error("❌ Gagal finish otomatis:", err);
                    // fallback UI
                    stopTimer();
                    $("#timer").text("SELESAI");
                    }
                });

            }

            elapsed++;
        }

        updateTimer();
        interval = setInterval(updateTimer, 1000);
    }

    function stopTimer() {
        clearInterval(interval);
        interval = null;
        console.log("⏹️ Timer dihentikan (diskualifikasi)");
    }

    function cleanupModals() {
        $(".modal-backdrop").remove();
        $("body").removeClass("modal-open").removeAttr("style");

        $(".modal.show").each(function () {
            const modalInstance = bootstrap.Modal.getInstance(this);
            if (modalInstance) modalInstance.hide();
        });
    }

    function formatTime(seconds) {
        const min = Math.floor(seconds / 60).toString().padStart(2, '0');
        const sec = (seconds % 60).toString().padStart(2, '0');
        return `${min}:${sec}`;
    }

    fetchMatch();


   let isRunning = false;

    $(document).on("click", ".start", function () {
        const matchId = $("#match-id").val();
        if (!matchId) return;

        const btn = $(this);
        btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.post(`${url}/api/matches/seni/${matchId}/start`, {
            duration: 600 // ⏱️ 10 menit
        }, function () {
            setTimeout(fetchAndStartTimer, 500);

            // Ganti jadi STOP
            btn
                .removeClass("start btn-success")
                .addClass("stop btn-danger")
                .html('<i class="bi bi-stop-fill me-1"></i> STOP');
        }).fail(() => {
            btn.html("START");
        }).always(() => {
            btn.prop("disabled", false);
        });
    });

    // Normalisasi input waktu (ganti koma -> titik) dan hitung detik
    function parsePerformanceTime(raw) {
        if (!raw) return { ok:false, msg:'Waktu tidak boleh kosong.' };

        let s = String(raw).trim();
        // ganti koma menjadi titik
        s = s.replace(',', '.');

        // Format mm:ss
        if (s.includes(':')) {
            const parts = s.split(':');
            if (parts.length !== 2) {
                return { ok:false, msg:'Format tidak valid. Gunakan mm:ss atau menit desimal (mis. 3.5).' };
            }
            const m = parseInt(parts[0], 10);
            const sec = parseInt(parts[1], 10);
            if (isNaN(m) || isNaN(sec) || m < 0 || sec < 0 || sec >= 60) {
                return { ok:false, msg:'Format mm:ss tidak valid.' };
            }
            const totalSeconds = (m * 60) + sec;
            return { ok:true, normalized: `${m}:${String(sec).padStart(2,'0')}`, seconds: totalSeconds };
        }

        // Format desimal menit (contoh: 3.5)
        // Hanya angka + titik diperbolehkan
        if (!/^\d+(\.\d+)?$/.test(s)) {
            return { ok:false, msg:'Hanya angka, titik, atau format mm:ss yang diperbolehkan.' };
        }

        const minutesFloat = parseFloat(s);
        if (isNaN(minutesFloat) || minutesFloat < 0) {
            return { ok:false, msg:'Nilai menit tidak valid.' };
        }

        // 3.5 menit = 3 menit 30 detik
        const totalSeconds = Math.round(minutesFloat * 60);

        // normalized pakai titik (sudah)
        return { ok:true, normalized: s, seconds: totalSeconds };
    }


    $(document).on("click", ".stop", function () {
        // Simpan ref button kalau perlu restore UI
        const $btnStop = $(this).data('ref', $(this));
        // Buka modal input waktu
        const modal = new bootstrap.Modal(document.getElementById('performanceTimeModal'));
        $('#performance-time-input').val('');                // reset input
        $('#performance-time-error').addClass('d-none').text('');
        modal.show();

        // Fokus ke input saat modal tampil
        setTimeout(() => { $('#performance-time-input').trigger('focus'); }, 250);
    });

    $(document).on('click', '#save-performance-time', function () {
        const $btn = $(this);
        const matchId = $("#match-id").val();
        if (!matchId) return;

        const val = $('#performance-time-input').val();
        const res = parsePerformanceTime(val);

        const $err = $('#performance-time-error');
        if (!res.ok) {
            $err.removeClass('d-none').text(res.msg);
            return;
        }
        $err.addClass('d-none').text('');

        // Optional: validasi batas (misal 0s - 10 menit)
        if (res.seconds < 0 || res.seconds > 10 * 60) {
            $err.removeClass('d-none').text('Durasi di luar batas wajar (0 - 10 menit).');
            return;
        }

        // Disable tombol dan tampilkan spinner
        const original = $btn.html();
        $btn.prop('disabled', true).html(`
        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
        Menyimpan...
        `);

        // Tutup timer lokal, set label SELESAI lebih dulu biar responsif
        stopTimer();
        $("#timer").text("SELESAI");

        // Kirim ke backend bareng data waktu (detik + normalized string)
        $.ajax({
            url: `${url}/api/local-seni-matches/${matchId}/finish`,
            method: 'PATCH',
            data: {
                performance_time_seconds: res.seconds,
                performance_time_input: res.normalized
            }
        }).done(function () {
            // Tutup modal
            const modalEl = document.getElementById('performanceTimeModal');
            const instance = bootstrap.Modal.getInstance(modalEl);
            if (instance) instance.hide();

            // Ubah tombol STOP jadi START lagi
            const $startStopBtn = $(".panel-footer .stop, .panel-footer .start");
            $startStopBtn
                .removeClass("stop btn-danger")
                .addClass("start btn-success")
                .html('<i class="bi bi-play-fill me-1"></i> START');

            $(".end-match").addClass("d-none");
            $(".next-match").removeClass("d-none");
        }).fail(function (xhr) {
            // Revert UI kalau gagal
            $("#timer").text(formatTime(elapsed || 0));
            $err.removeClass('d-none').text(xhr.responseJSON?.message || 'Gagal menyimpan waktu perform.');
        }).always(function () {
            $btn.prop('disabled', false).html(original);
        });
    });

    // Enter untuk submit di modal
    $('#performanceTimeModal').on('shown.bs.modal', function () {
    $('#performance-time-input').off('keydown').on('keydown', function(e) {
        if (e.key === 'Enter') {
        e.preventDefault();
        $('#save-performance-time').click();
        }
    });
    });

    // Realtime: ganti koma -> titik
    $(document).on('input', '#performance-time-input', function () {
    const v = $(this).val();
    if (v.includes(',')) {
        $(this).val(v.replace(',', '.'));
    }
    });






    $(document).on("click", ".stop_backup", function () {
        const matchId = $("#match-id").val();
        const btn = $(this);
        if (!matchId) return;

        btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: `${url}/api/local-seni-matches/${matchId}/finish`,
            method: 'PATCH',
            data: {
                duration: res.seconds   // ← kirim detik hasil parse (dari 3,5 / 3.5 / 3:30)
            }
            }).done(function (resp) {
            // Tutup modal
            const modalEl = document.getElementById('performanceTimeModal');
            const instance = bootstrap.Modal.getInstance(modalEl);
            if (instance) instance.hide();

            // UI selesai
            stopTimer();
            $("#timer").text("SELESAI");

            const $startStopBtn = $(".panel-footer .stop, .panel-footer .start");
            $startStopBtn
                .removeClass("stop btn-danger")
                .addClass("start btn-success")
                .html('<i class="bi bi-play-fill me-1"></i> START');

            $(".end-match").addClass("d-none");
            $(".next-match").removeClass("d-none");

            // (Cadangan) Kalau backend kasih sinyal grup selesai, boleh redirect di sini juga
            if (resp?.battle_group_completed && resp?.winners?.length) {
                // optional: window.location.href = resp.result_url;  // kalau kamu ikut balikin result_url
                // tapi utamanya kita mengandalkan broadcast event ke Display Arena
            }
            }).fail(function (xhr) {
            $("#timer").text(formatTime(elapsed || 0));
            $('#performance-time-error').removeClass('d-none').text(
                xhr.responseJSON?.message || 'Gagal menyimpan waktu perform.'
            );
            }).always(function () {
            $btn.prop('disabled', false).html(original);
            });

    });






    

    

   /*$(".pause").on("click", function () {
        const matchId = $("#match-id").val();
        if (!matchId) return;

        const btn = $(this);
        setButtonLoading(btn, true);

        if (isPaused) {
            // 🔄 RESUME
            $.post(`${url}/api/matches/seni/${matchId}/resume`, function (res) {
                isPaused = false;
                elapsed = typeof res.elapsed === 'number' ? res.elapsed : 0;
                runTimer();
            }).fail(() => {
                alert("❌ Gagal melanjutkan pertandingan.");
            }).always(() => {
                setButtonLoading(btn, false);
                btn.text("PAUSE"); // ✅ pindah ke bawah setelah loading selesai
            });
        } else {
            // ⏸️ PAUSE
            stopTimer();
            $.post(`${url}/api/matches/seni/${matchId}/pause`, function () {
                isPaused = true;
            }).fail(() => {
                alert("❌ Gagal mem-pause pertandingan.");
            }).always(() => {
                setButtonLoading(btn, false);
                btn.text("RESUME"); // ✅ pindah ke bawah setelah loading selesai
            });
        }
    });*/

    $(document).on("click touchstart", ".pause", function (e) {
        e.preventDefault();
        e.stopPropagation();

        const matchId = $("#match-id").val();
        if (!matchId) {
            console.warn("⛔ matchId belum ada");
            return;
        }

        const btn = $(this);
        setButtonLoading(btn, true);

        if (isPaused) {
            // 🔄 RESUME
            $.post(`${url}/api/matches/seni/${matchId}/resume`)
                .done((res) => {
                    isPaused = false;
                    elapsed = typeof res.elapsed === 'number' ? res.elapsed : 0;
                    runTimer();
                })
                .fail(() => {
                    alert("❌ Gagal melanjutkan pertandingan.");
                })
                .always(() => {
                    setButtonLoading(btn, false);
                    btn.text("PAUSE");
                });
        } else {
            // ⏸️ PAUSE
            stopTimer();
            $.post(`${url}/api/matches/seni/${matchId}/pause`)
                .done(() => {
                    isPaused = true;
                })
                .fail(() => {
                    alert("❌ Gagal mem-pause pertandingan.");
                })
                .always(() => {
                    setButtonLoading(btn, false);
                    btn.text("RESUME");
                });
        }
    });




    

    $(".reset").on("click", function () {
        const matchId = $("#match-id").val(); // Ambil dari hidden input atau variabel
    
        if (!matchId) return;

        const modal = new bootstrap.Modal(document.getElementById('confirmResetModal'));
        modal.show();
    
        $("#confirm-reset-btn").off("click").on("click", function () {
            const btn = $(".reset");
            setButtonLoading(btn, true);
            modal.hide();
    
            $.post(`${url}/api/matches/seni/${matchId}/reset`, function () {
                stopTimer();
                timerEl.text("00:00");

                const $startStopBtn = $(".panel-footer .stop, .panel-footer .start");
                $startStopBtn
                    .removeClass("stop btn-danger")
                    .addClass("start btn-success")
                    .html('<i class="bi bi-play-fill me-1"></i> START');
                
                $(".end-match").show();
                $(".next-match").hide();
                //timerEl.text(formatTime(duration));
            }).always(() => setButtonLoading(btn, false));
        });
    });

    

   $(".end-match").off("click").on("click", function () {
        const confirmModalEl = document.getElementById('confirmEndModal');
        const endedModalEl = document.getElementById('endedMatchModal');

        const confirmModal = new bootstrap.Modal(confirmModalEl);
        const endedModal = new bootstrap.Modal(endedModalEl);

        confirmModal.show();

        $("#confirm-end-btn").off("click").on("click", function () {
            const matchId = $("#match-id").val();
            const reason = "Diskualifikasi";

            const instance = bootstrap.Modal.getInstance(confirmModalEl);
            if (instance) instance.hide();

            $.ajax({
                url: url + `/api/local-seni-matches/${matchId}/disqualify`,
                method: 'PATCH',
                data: {
                    reason: reason
                },
                success: function (res) {
                    console.log("✅ Peserta didiskualifikasi:", res);

                    stopTimer();
                    elapsed = 0;
                    timerEl.text("00:00");

                    $(".end-match").addClass("d-none");
                    $(".next-match").removeClass("d-none");

                    // Tampilkan modal "Pertandingan Dihentikan"
                    $(confirmModalEl).on('hidden.bs.modal', function () {
                        endedModal.show();

                        // ⏱️ Cleanup backdrop supaya tombol bisa diklik
                        setTimeout(() => {
                            cleanupModals(); // prevent backdrop tertinggal
                        }, 300);
                    });
                },
                error: function (err) {
                    console.error("❌ Gagal diskualifikasi:", err);
                    alert("Gagal mendiskualifikasi peserta!");
                }
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

    $(document).on("click", ".skip-match", function () {
        const matchId = $("#match-id").val();
        if (!matchId) return;

        const $btn = $(this);
        const original = $btn.html();
        $btn.prop("disabled", true).html(`
            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            Skipping...
        `);

        $.ajax({
            url: `${url}/api/local-seni-matches/${matchId}/skip`,
            method: 'PATCH',
            data: { reason: 'no_show' } // opsional
        })
        .done(function (res) {
            // Stop timer & mark selesai secara visual
            if (typeof stopTimer === 'function') stopTimer();
            $("#timer").text("SELESAI");

            // Kalau masih ada match berikutnya -> langsung redirect
            if (res.next_match_id) {
            window.location.href = `${url}/matches/seni/${res.next_match_id}`;
            return;
            }

            // Kalau battle group selesai -> ARENA yang akan buka result via broadcast.
            // Di operator cukup tampilkan info ringan (opsional) + munculkan tombol NEXT.
            if (res.battle_group_completed) {
            console.log("✅ Group completed. Arena akan buka result. battle_group:", res.battle_group);
            // contoh info ringan (tanpa modal besar):
            $("#nextMatchModalBody").text("Battle group selesai. Silakan cek Display Arena untuk hasil.");
            new bootstrap.Modal(document.getElementById('nextMatchModalInfo')).show();
            } else {
            // Tidak ada next & belum complete group
            $("#nextMatchModalBody").text("Tidak ada pertandingan berikutnya.");
            new bootstrap.Modal(document.getElementById('nextMatchModalInfo')).show();
            }

            // Tampilkan tombol NEXT (biar operator punya kontrol)
            $(".end-match, .skip-match, .disqualify").addClass("d-none");
            $(".next-match").removeClass("d-none");
        })
        .fail(function (xhr) {
            console.error("❌ Gagal skip:", xhr.responseJSON?.message || xhr.statusText);
            alert(xhr.responseJSON?.message || "Gagal melakukan skip performance.");
        })
        .always(function () {
            $btn.prop("disabled", false).html(original);
        });
        });


    
    

    $(".next-match").on("click", function () {
        const $btn = $(this);
        const originalHtml = $btn.html();

        // Tampilkan spinner dan disable tombol
        $btn.prop("disabled", true).html(`
            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            Loading...
        `);

        $.post(url + `/api/local-seni-matches/${matchId}/next`, function (res) {
            if (res.new_match_id) {
                window.location.href = url + `/matches/seni/${res.new_match_id}`;
            } else {
                alert("Tidak ada pertandingan berikutnya.");
            }
        }).fail(function (xhr) {
            $("#nextMatchModalBody").text("Tidak ada pertandingan berikutnya. Semua pertandingan telah selesai.");
            const modal = new bootstrap.Modal(document.getElementById('nextMatchModalInfo'));
            modal.show();
            console.error("❌ Gagal ganti match:", xhr.responseJSON?.message || xhr.statusText);
        }).always(function () {
            // Kembalikan tombol ke semula
            $btn.prop("disabled", false).html('Redirecting to Next Match');
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
    
    
    

    


    
});

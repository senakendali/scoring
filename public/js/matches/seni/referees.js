$(document).ready(function () {
    const url = window.location.origin;
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
   // $(".reset-deduction, .poin-deduction").prop("disabled", true).addClass("disabled");


    const host = window.location.hostname;
    const pusher = new Pusher('reverb', {
        wsHost: host,
        wsPort: 6001,
        forceTLS: false,
        disableStats: true,
        enabledTransports: ['ws'],
    });

    const channel = pusher.subscribe(`match.${matchId}`);
     const globalChannel = pusher.subscribe('global.seni.match');

    globalChannel.bind('seni.match.changed', function (data) {
        console.log("🎯 Match aktif berubah:", data);

        const newMatchId = data.new_match_id;

        // Contoh aksi:
        // 1. Redirect ke halaman match baru
        // 2. Atau fetch ulang data match aktif

        window.location.href = `/matches/seni/referees/${newMatchId}`;
    });

    // Buat slug versi JS
    function slugify(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-')      // Ganti spasi jadi -
            .replace(/[^\w\-]+/g, '')  // Hapus karakter non-word
            .replace(/\-\-+/g, '-')    // Ganti -- jadi -
            .replace(/^-+/, '')        // Hapus - di awal
            .replace(/-+$/, '');       // Hapus - di akhir
    }

    const arena = $("#session-arena").val();
    const tournament = $("#session-tournament").val();

    const tournamentSlug = slugify(tournament);
    const arenaSlug = slugify(arena);

    console.log("🔍 Subscribing to:", `seni-timer.${tournamentSlug}.${arenaSlug}`);

    window.Echo.channel(`seni-timer.${tournamentSlug}.${arenaSlug}`)
    .listen('.SeniTimerStarted', (e) => {
       
        console.log("🔥 Event SeniTimerStarted diterima di jQuery:", e);
       
         $(".reset-deduction, .poin-deduction").prop("disabled", false).removeClass("disabled");
    });

     window.Echo.channel(`seni-timer.${tournamentSlug}.${arenaSlug}`)
    .listen('.SeniTimerUpdated', function (data) {
        console.log("🕒 Event SeniTimerUpdated diterima:", data);

        // Update tampilan status ronde
        if (data.status === 'ongoing') {
            
             $(".reset-deduction, .poin-deduction").prop("disabled", false).removeClass("disabled");
        } else if (data.status === 'paused') {
            
           $(".reset-deduction, .poin-deduction").prop("disabled", true).addClass("disabled");
            
        } else if (data.status === 'finished') {
            
           $(".reset-deduction, .poin-deduction").prop("disabled", true).addClass("disabled");
            
        } else if (data.status === 'not_started') {

            // ✅ Reset hanya input pengurangan per baris (tbody)
            $("table.mytable tbody input[type='text']").val("0.00");

            
            $(".reset-deduction, .poin-deduction").prop("disabled", true).addClass("disabled");
        }
    });

    window.Echo.channel(`seni-timer.${tournamentSlug}.${arenaSlug}`)
    .listen('.SeniTimerFinished', function (data) {
        console.log("🏁 Match selesai:", data);

        $(".wrong-move").prop("disabled", true);

        // 🟢 Otomatis submit penalties saat pertandingan selesai
        submitPenalties();

        if (data.status === 'finished' && data.disqualified === true) {
            const disqualifiedModalEl = document.getElementById('disqualifiedModal');
            if (disqualifiedModalEl) {
                const modal = new bootstrap.Modal(disqualifiedModalEl);
                modal.show();
            }
        } else {
            const finishedModalEl = document.getElementById('finishedModal');
            if (finishedModalEl) {
                const modal = new bootstrap.Modal(finishedModalEl);
                modal.show();
            }
        }
    });







   

   

    // Saat klik tombol -0.50
    $(document).on("click", ".poin-deduction", function () {
        const $row = $(this).closest("tr");
        const $input = $row.find("td:nth-child(3) input");
        const currentVal = parseFloat($input.val()) || 0;
        const newVal = currentVal + 0.50;
        $input.val(newVal.toFixed(2));

        updateTotalPenalty(); // update total bawah
    });

    // Saat klik tombol CLEAR
    $(document).on("click", ".reset-deduction", function () {
        const $row = $(this).closest("tr");
        const $input = $row.find("td:nth-child(3) input");
        $input.val("0.00");

        updateTotalPenalty(); // update total bawah
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

    function submitPenalties() {
        const matchId = $("#match-id").val();         // hidden input
        const judgeNumber = $("#judge-number").val(); // hidden input
        const penalties = [];

        $("table tbody tr").each(function () {
            const type = $(this).find("td:nth-child(1)").text().trim();
            const valueRaw = $(this).find("td:nth-child(3) input").val().trim();
            const value = parseFloat(valueRaw);

            if (!isNaN(value) && value > 0) {
            penalties.push({ type: type, value: value });
            }
        });

        // Kirim ke backend
        $.ajax({
            url: "/api/seni-penalties",
            method: "POST",
            data: {
            match_id: matchId,
            judge_number: judgeNumber,
            penalties: penalties
            },
            success: function (res) {
                console.log("✅ Penalties submitted:", res);
                $.post(`/api/recalculate-final-score/${matchId}`, function (response) {
                    console.log("🎯 Final score recalculated:", response.final_score);
                }).fail(function (err) {
                    console.error("❌ Gagal hitung ulang final score:", err);
                });
            },
            error: function (err) {
            console.error("❌ Gagal submit penalties:", err);
            }
        });
    }

    function updateTotalPenalty() {
        let total = 0;
        $("table tbody tr").each(function () {
        const val = parseFloat($(this).find("td:nth-child(3) input").val()) || 0;
        total += val;
        });

        $("#penalty-total input").val(total.toFixed(2)).addClass("text-danger");
    }

    
});

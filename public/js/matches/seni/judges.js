$(document).ready(function () {
    const url = window.location.origin;
    let currentRoundNumber = 1;

    let startingScore = 9.75;
    const deduction = 0.50;

    let matchId = parseInt($("#match-id").val());
    let roundId = null;
    let judgeNumber = $("#judge-number").val();

    let totalDeduction = 0;

    const arena = $("#session-arena").val();
    const tournament = $("#session-tournament").val();


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
    
    const globalChannel = pusher.subscribe('global.seni.match');
    const channel = pusher.subscribe(`match.${matchId}`);

    channel.bind_global(function (event, data) {
        console.log("🌍 Global Event:", event, data);
    });


   

    globalChannel.bind('seni.match.changed', function (data) {
        console.log("🎯 Match aktif berubah:", data);

        const newMatchId = data.new_match_id;

        // Contoh aksi:
        // 1. Redirect ke halaman match baru
        // 2. Atau fetch ulang data match aktif

        window.location.href = `/matches/seni/judges/${newMatchId}`;
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

    const tournamentSlug = slugify("Kejuaraan Pencak Silat Nasional 2025");
    const arenaSlug = slugify("Arena 1");

    console.log("🔍 Subscribing to:", `seni-timer.${tournamentSlug}.${arenaSlug}`);

    window.Echo.channel(`seni-timer.${tournamentSlug}.${arenaSlug}`)
    .listen('.SeniTimerStarted', (e) => {
       
        console.log("🔥 Event SeniTimerStarted diterima di jQuery:", e);
        $(".wrong-move").prop("disabled", false);
    });


    
    
    window.Echo.channel(`seni-timer.${tournamentSlug}.${arenaSlug}`)
        .listen('.SeniTimerUpdated', function (data) {
            console.log("🕒 Event SeniTimerUpdated diterima:", data);

            // Update tampilan status ronde
            if (data.status === 'ongoing') {
                
                $(".wrong-move").prop("disabled", false); // aktifkan tombol wrong move
            } else if (data.status === 'paused') {
              
                $(".wrong-move").prop("disabled", true); // matikan tombol wrong move
               
            } else if (data.status === 'finished') {
               
                $(".wrong-move").prop("disabled", true); // matikan tombol wrong move
                
            } else if (data.status === 'not_started') {

                // ✅ Reset variabel skor lokal
                totalDeduction = 0;
                currentScore = 9.75;

                // ✅ Update UI
                $("#starting-score").text("9.75");
                $("#deduction").text("-0.00");
                
                $(".wrong-move").prop("disabled", true); // matikan tombol wrong move
            }
        });

    window.Echo.channel(`seni-timer.${tournamentSlug}.${arenaSlug}`)
    .listen('.SeniTimerFinished', function (data) {
        console.log("🏁 Match selesai:", data);

        $(".wrong-move").prop("disabled", true);

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





    


    updateScoreUI();
    
    $(".wrong-move").on("click", function () {
        const finalScore = startingScore - totalDeduction - deduction;

        if (finalScore < 0) return;

        totalDeduction += deduction;

       $.ajax({
            url: "/api/seni-scores",
            method: "POST",
            data: {
                local_match_id: matchId,
                judge_number: judgeNumber,
                deduction: deduction // dari let deduction = 0.50
            },
            success: function (res) {
                updateScoreUI(); // update DOM
            },
            error: function (err) {
                console.error("❌ Gagal kirim deduksi:", err);
            }
        });

    });

    function updateScoreUI() {
        const currentScore = startingScore - totalDeduction;

        $("#starting-score").text(currentScore.toFixed(2));
        $("#deduction").text("-" + totalDeduction.toFixed(2));
    }   


   
    
    
    
    
    $(".wrong-move").prop("disabled", true); // Disable semua tombol diawal

    
    
    

    // ✅ Setup juri jika belum
    initializeScoring();
   
    

    function initializeScoring() {
        fetchMatchData();
        

        
        
    }

    function fetchMatchData() {
        $(".loader-bar").show();
        
        $.get(`${url}/api/local-matches/seni/${matchId}`, function (data) {
            $("#match-id").val(data.id);
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

    

    function submitPoint(corner, type) {
        if (!matchId || !roundId || !judgeNumber) {
            console.warn("❌ Data belum lengkap untuk submit skor");
            return;
        }
        
    
        // 🔥 Submit ke server
        $.post(`/api/local-judge-scores`, {
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
    
            const span = $(`<span class="roboto-bold btn ${colorClass} pop-animate">${value}</span>`);
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
    
    
    
    
    
    
    
});

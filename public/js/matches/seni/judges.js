$(document).ready(function () {
    const url = window.location.origin;

   

    let matchId = parseInt($("#match-id").val());
    let roundId = null;
    let judgeNumber = $("#judge-number").val();
    

    let startingScore = parseFloat($("#seni_base_score").val());
    const deduction = 0.01;
    let additionalScore = 0.00;
    let totalDeduction = 0;
    let hasClickedPlus = false;

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

    const arena = $("#session-arena").val();
    const tournament = $("#session-tournament").val();

    const tournamentSlug = slugify(tournament);
    const arenaSlug = slugify(arena);

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

                // ✅ Update UI
                $("#starting-score").text($("#seni_base_score").val());
                $("#deduction").text("-0.00");
                
                $(".wrong-move").prop("disabled", false); // matikan tombol wrong move
            }
        });

    window.Echo.channel(`seni-timer.${tournamentSlug}.${arenaSlug}`)
    .listen('.SeniTimerFinished', function (data) {
        console.log("🏁 Match selesai:", data);

        $(".wrong-move").prop("disabled", true);

        const judgeNumbers = $('#judge-number').val(); // ✅ dipanggil DI SINI

        if (!judgeNumbers) {
            console.warn("❌ Juri number tidak ditemukan");
            return;
        }

        if (data.status === 'finished' && data.disqualified === true) {
           

            const disqualifiedModalEl = document.getElementById('disqualifiedModal');
            if (disqualifiedModalEl) {
                const modal = new bootstrap.Modal(disqualifiedModalEl);
                modal.show();
            }
        } else {

            // ⬇️ Submit Component Scores
            $(".judges_table tbody tr").each(function () {
                const $row = $(this);
                const component = $row.data("component");
                const value = parseFloat($row.find("input").val()) || 0;

                $.post('/api/seni-component-score', {
                    match_id: matchId,
                    judge_number: judgeNumbers,
                    component: component,
                    value: value,
                }, function (res) {
                    console.log(`✅ Component [${component}] submitted:`, res);
                }).fail(function (xhr) {
                    console.error(`❌ Gagal simpan komponen ${component}`);
                });
            });

            

            const finishedModalEl = document.getElementById('finishedModal');
            if (finishedModalEl) {
                const modal = new bootstrap.Modal(finishedModalEl);
                modal.show();
            }
        }
    });

    
    updateScoreUI();

    $(".judges_table tbody tr").each(function () {
        const $row = $(this);
        const $buttonTd = $row.find("td").eq(1);
        const $scoreCell = $row.find("td").eq(2);
        const $scoreInput = $scoreCell.find("input");

        // Bungkus input + tombol reset
        const $scoreWrapper = $('<div class="d-flex align-items-center gap-2 justify-content-center"></div>');
        $scoreInput.addClass('flex-grow-1');

        const $resetBtn = $('<button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>');
        $resetBtn.on("click", function () {
            $scoreInput.val("0.00");
            updateTotals();

            // 🔁 Reset semua tombol ke hijau
            $row.find(".score-buttons-inner button").removeClass("btn-warning").addClass("btn-success");
        });

        $scoreWrapper.append($scoreInput).append($resetBtn);
        $scoreCell.empty().append($scoreWrapper);

        // Buat wrapper tombol nilai
        const $buttonContainer = $(`
            <div class="score-buttons-wrapper">
                <div class="score-buttons-inner d-flex flex-wrap justify-content-center gap-1"></div>
            </div>
        `);
        const $inner = $buttonContainer.find(".score-buttons-inner");

        // Generate tombol nilai 0.01 - 0.30
        for (let i = 1; i <= 30; i++) {
            const val = (i / 100).toFixed(2);
            const $btn = $(`<button class="btn btn-sm btn-success">${val}</button>`);

            $btn.on("click", function () {
                let current = parseFloat($scoreInput.val()) || 0;
                current += parseFloat(val);
                $scoreInput.val(current.toFixed(2));
                updateTotals();

                // ✅ Ganti warna tombol jadi warning
                $btn.removeClass("btn-success").addClass("btn-warning");
            });

            $inner.append($btn);

            // Break baris tiap 15 tombol
            if (i % 15 === 0) {
                $inner.append('<div class="w-100"></div>');
            }
        }

        $buttonTd.append($buttonContainer);
    });




  // Fungsi untuk hitung total + final score
  function updateTotals() {
    let total = 0;

    $(".judges_table tbody input").each(function () {
      total += parseFloat($(this).val()) || 0;
    });

    const base = parseFloat($(".judges_table tfoot input").eq(1).val()) || 0;
    const final = total + base;

    $(".judges_table tfoot input").eq(0).val(total.toFixed(2));  // Total Score
    $(".judges_table tfoot input").eq(2).val(final.toFixed(2));  // Final Score
  }
    
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
        const displayScore = startingScore + additionalScore - totalDeduction;
        $("#starting-score").text(displayScore.toFixed(2));
        $("#deduction").text("-" + totalDeduction.toFixed(2));
    }

    $(document).ready(function () {
        $('#additional_score').val('0.00');
        updateScoreUI();
    });

    $(document).on('click', '.btn-increase-additional', function () {
        let current = parseFloat($('#additional_score').val()) || 0;
        let next;

        if (!hasClickedPlus && current === 0) {
            next = 0.05;
            hasClickedPlus = true;
        } else {
            next = current + 0.01;
        }

        // ✅ Batas maksimum 0.10
        if (next > 0.10) {
            next = 0.10;
        }

        next = Math.round(next * 100) / 100;
        additionalScore = next;
        $('#additional_score').val(next.toFixed(2));
        updateScoreUI();
    });

    $(document).on('click', '.btn-decrease-additional', function () {
        let current = parseFloat($('#additional_score').val()) || 0;
        let next = current - 0.01;

        if (next < 0.05) {
            hasClickedPlus = false; // reset loncatan jika nilai < 0.05
        }

        next = Math.max(0, Math.round(next * 100) / 100);
        additionalScore = next;
        $('#additional_score').val(next.toFixed(2));
        updateScoreUI();
    });

    $(document).on('click', '.btn-reset-additional', function () {
        additionalScore = 0.00;
        hasClickedPlus = false;
        $('#additional_score').val('0.00');
        updateScoreUI();
    });


   $(document).on('click', '.btn-submit-additional', function () {
        const $btn = $(this);
        const originalText = $btn.html(); // simpan isi tombol awal

        const score = parseFloat($('#additional_score').val());

        // ✅ Ganti jadi loader dan disable tombol
        $btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Menyimpan...');
        $btn.prop('disabled', true);

        $.post('/api/seni-additional-score', {
            match_id: matchId,
            judge_number: judgeNumber,
            additional_score: score,
        })
        .done(function (res) {
            showScoreModal('Score berhasil disimpan!');
        })
        .fail(function (xhr) {
            showScoreModal('Gagal menyimpan score tambahan.');
        })
        .always(function () {
            // ✅ Kembalikan tombol seperti semula
            $btn.html(originalText);
            $btn.prop('disabled', false);
        });
    });



    // Fungsi helper untuk tampilkan modal
    function showScoreModal(message) {
        $('#scoreSubmitModalBody').text(message);
        const modal = new bootstrap.Modal(document.getElementById('scoreSubmitModal'));
        modal.show();
    }
    
    

    // ✅ Setup juri jika belum
    initializeScoring();
   
    

    function initializeScoring() {
        fetchMatchData();
        

        
        
    }

    function fetchMatchData() {
        $(".loader-bar").show();
        
        $.get(`${url}/api/local-matches/seni/${matchId}`, function (data) {
            if(data.match_type === 'seni_tunggal' || data.match_type === 'seni_regu'){
               
                $("#seni_base_score").val((9.9).toFixed(2)); // hasil: "9.20"

                //$(".app-header").addClass("d-block"); 
                //$(".fix-match-info").addClass("d-block");
                $("#mode_one").show();
                $("#mode_two").addClass('d-none');
            }else if(data.match_type === 'seni_ganda' || data.match_type === 'solo_kreatif'){
                
                 $("#seni_base_score").val((9.1).toFixed(2)); // hasil: "9.20"

                //$(".app-header").addClass("d-none");
                //$(".fix-match-info").addClass("d-none");
                $("#mode_one").addClass('d-none');
                $("#mode_two").show();
            }

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
    
    
});

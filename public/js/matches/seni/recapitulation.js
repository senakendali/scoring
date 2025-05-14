$(document).ready(function () {
    var url = window.location.origin;
    var matchId = $("#match-id").val();
    
    const arena = $("#session-arena").val();
    const tournament = $("#session-tournament").val();

    const tournamentSlug = slugify(tournament);
    const arenaSlug = slugify(arena);

    console.log("🟢 Recapitulation JS Ready, Match ID:", matchId);  

    fetchMatchData();
    //loadRekapitulasi(matchId);
    updateRekapitulasiTable(matchId, tournament, arena);
    /*setInterval(() => {
        updateRekapitulasiTable(matchId, tournament, arena);
    }, 1500);*/

    function slugify(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-')      // Ganti spasi jadi -
            .replace(/[^\w\-]+/g, '')  // Hapus karakter non-word
            .replace(/\-\-+/g, '-')    // Ganti -- jadi -
            .replace(/^-+/, '')        // Hapus - di awal
            .replace(/-+$/, '');       // Hapus - di akhir
    }

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
        window.location.href = `/matches/seni/${data.new_match_id}/recap`; // Sesuaikan path kalau perlu
    });

    window.Echo.channel(`seni-score.${tournamentSlug}.${arenaSlug}`)
    .listen('.SeniScoreUpdated', function (data) {
        console.log("🔥 Real-time score updated:", data);

        // Langsung pakai data dari event
        updateRekapitulasiTableFromData(data);
    });

    function updateRekapitulasiTableFromData(data) {
        const judges = data.judges || [];
        const totalPenalty = parseFloat(data.penalty ?? 0);
        const penalties = data.penalties || [];

        updatePenaltyRecapTable(penalties);

        judges.sort((a, b) => a.score - b.score);
        const scores = judges.map(j => j.score);

        const $jurisHeader = $(".mytable thead tr").eq(1).find("th");
        const $jurisRow = $(".mytable tbody tr").first().find("td");

        $jurisHeader.removeClass("median-cell");
        $jurisRow.removeClass("median-cell");

        judges.forEach((j, index) => {
            $jurisHeader.eq(index).text(`J${j.juri_number}`).addClass("text-center");
            $jurisRow.eq(index).text(j.score.toFixed(2)).addClass("text-center");
        });

        let median = 0;
        if (scores.length % 2 === 0) {
            const mid1 = (scores.length / 2) - 1;
            const mid2 = (scores.length / 2);
            $jurisRow.eq(mid1).addClass("median-cell");
            $jurisRow.eq(mid2).addClass("median-cell");
            $jurisHeader.eq(mid1).addClass("median-cell");
            $jurisHeader.eq(mid2).addClass("median-cell");
            median = (scores[mid1] + scores[mid2]) / 2;
        } else {
            const mid = Math.floor(scores.length / 2);
            $jurisRow.eq(mid).addClass("median-cell");
            $jurisHeader.eq(mid).addClass("median-cell");
            median = scores[mid];
        }

        const mean = scores.reduce((a, b) => a + b, 0) / scores.length;
        const variance = scores.reduce((sum, val) => sum + Math.pow(val - mean, 2), 0) / scores.length;
        const stddev = Math.sqrt(variance);

        $("#median").text(median.toFixed(2)).addClass("text-center");
        $("#punishment").text("-" + totalPenalty.toFixed(2)).addClass("text-center");
        $("#standar-deviasi").text(stddev.toFixed(2)).addClass("text-center");
        $("#final-score").text((mean - totalPenalty).toFixed(2)).addClass("text-center");
    }



    // 🔥 Handle hide/show info saat scroll
    let lastScrollTop = 0;
    $(window).on("scroll", function () {
        const st = $(this).scrollTop();
        const matchInfo = $(".fix-match-info");
        const matchDetail = $(".fix-match-detail");

        if (st > 50) { 
            matchInfo.fadeOut(200);
            matchDetail.fadeOut(200);
        } else {
            matchInfo.fadeIn(200);
            matchDetail.fadeIn(200);
        }
        lastScrollTop = st;
    });


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

    function updatePenaltyRecapTable(penalties) {
        $("table tbody tr").each(function () {
            const row = $(this);
            const reason = row.find("td").eq(0).text().trim();

            // Cari apakah reason ini ada di data penalties
            const matched = penalties.find(p => p.reason === reason);

            // Update kolom kedua
            if (matched) {
                row.find("td").eq(1).text(parseFloat(matched.penalty_value).toFixed(2));

            } else {
                row.find("td").eq(1).text("-");
            }
        });
    }


    
    
    

    
    function updateRekapitulasiTable(matchId, tournament, arena) {
        
    
        $.get(`${url}/api/seni/judges-score?tournament=${encodeURIComponent(tournament)}&arena=${encodeURIComponent(arena)}&match_id=${matchId}`, function (data) {
            const judges = data.judges || [];
            const totalPenalty = parseFloat(data.penalty ?? 0);

            console.log("🧠 Judges:", data.judges);

            const penalties = data.penalties || [];
            updatePenaltyRecapTable(penalties);

            // Urutkan berdasarkan skor kecil → besar
            judges.sort((a, b) => a.score - b.score);
            const scores = judges.map(j => j.score);

            // Ambil elemen header dan isi baris
            const $jurisHeader = $(".mytable thead tr").eq(1).find("th");
            const $jurisRow = $(".mytable tbody tr").first().find("td");

            // Reset tampilan lama
            $jurisHeader.removeClass("median-cell");
            $jurisRow.removeClass("median-cell");

            // Render nomor juri (header) & skor (isi)
            judges.forEach((j, index) => {
                $jurisHeader.eq(index).text(`J${j.juri_number}`).addClass("text-center");
                $jurisRow.eq(index).text(j.score.toFixed(2)).addClass("text-center");
            });

            // Hitung median
            let median = 0;
            if (scores.length % 2 === 0) {
                const mid1 = (scores.length / 2) - 1;
                const mid2 = (scores.length / 2);
                $jurisRow.eq(mid1).addClass("median-cell");
                $jurisRow.eq(mid2).addClass("median-cell");
                $jurisHeader.eq(mid1).addClass("median-cell");
                $jurisHeader.eq(mid2).addClass("median-cell");
                median = (scores[mid1] + scores[mid2]) / 2;
            } else {
                const mid = Math.floor(scores.length / 2);
                $jurisRow.eq(mid).addClass("median-cell");
                $jurisHeader.eq(mid).addClass("median-cell");
                median = scores[mid];
            }

            // Hitung standar deviasi
            const mean = scores.reduce((a, b) => a + b, 0) / scores.length;
            const variance = scores.reduce((sum, val) => sum + Math.pow(val - mean, 2), 0) / scores.length;
            const stddev = Math.sqrt(variance);

            // Update ke ringkasan bawah
            $("#median").text(median.toFixed(2)).addClass("text-center");
            $("#punishment").text("-" + totalPenalty.toFixed(2)).addClass("text-center");
            $("#standar-deviasi").text(stddev.toFixed(2)).addClass("text-center");


            $("#final-score").text((mean - totalPenalty).toFixed(2)).addClass("text-center");

            const start = new Date(data.start_time);
            const end = new Date(data.end_time);
            const durasiDetik = (end - start) / 1000;


            $("#time").text(durasiDetik).addClass("text-center");
        });
    }


    
    
    
    

});

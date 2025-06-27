$(document).ready(function () {
    var url = window.location.origin;

    const arenaName = $("#session-arena").val();
    const roleName = $("#session-role").val();
    const juriNumber = $("#session-juri-number").val();

    const role = $("#session-role").val()?.toLowerCase(); // amanin pakai optional chaining
    const isOperator = role === 'operator';
    const isKetua = role === 'ketua';

    const arena = $("#session-arena").val();
    const tournament = $("#session-tournament").val();
    
    const tournamentSlug = slugify(tournament); // pastikan disamakan
    const arenaSlug = slugify(arena);

    const text = "Ikatan Pencak Silat Indonesia";
    let index = 0;
    const $target = $("#typing-text");

    function typeWriter() {
        if (index < text.length) {
            $target.append(text.charAt(index));
            index++;
            setTimeout(typeWriter, 100); // kecepatan ketik
        }
    }

    typeWriter();
   
    window.Echo.channel(`match-start.${tournamentSlug}.${arenaSlug}`)
    .listen('.MatchStarted', (e) => {
        const role = $("#session-role").val().toLowerCase(); // 🔥 ini penting bro
        const arena = $("#session-arena").val();
        const tournament = $("#session-tournament").val();

        if (e.arena_name === arena && e.tournament_name === tournament) {
            let route = "/matches";

            switch (role) {
                case "juri": route += `/judges/${e.match_id}`; break;
                case "dewan": route += `/referees/${e.match_id}`; break;
                case "ketua": route += `/${e.match_id}/recap`; break;
                case "operator": route += `/${e.match_id}`; break;
                case "penonton": route += `/display-arena/${e.match_id}`; break; // ✅ redirect ke layar besar
            }
            

            window.location.href = route;
        }
    });

    let roleLabel = roleName;

    if (roleName.toLowerCase() === 'juri' && juriNumber) {
        roleLabel += ` ${juriNumber}`;
    }

    

    $.get(url + "/api/local-matches", function (data) {
        $(".loader-bar").show();
        $('#match-tables').empty();

        /*$('#match-tables').before(`
            <div class="mb-3 text-end">
                <button class="btn btn-primary" id="show-winners-btn">
                    <i class="bi bi-star-fill"></i> Lihat Pemenang Tertinggi
                </button>
            </div>
        `);*/


        $.each(data, function (arenaName, pools) {
            let matches = [];

            // Gabung semua match dari setiap pool di arena ini
            $.each(pools, function (poolName, poolMatches) {
                matches = matches.concat(poolMatches);
            });

            if (!matches.length) return;

            // Urutkan berdasarkan match_number (atau match_order)
            matches.sort((a, b) => a.match_number - b.match_number);

            let arenaSection = `<div class="mb-5">
                <h4 class="text-white mb-3">${arenaName.toUpperCase()}</h4>
                <table class="table table-dark">
                    <thead>
                        <tr>
                            <th>No Partai</th>
                            <th>Babak</th>
                            <th colspan="2" class="text-center">Peserta</th>
                            <th>Pemenang</th>
                            <th>Keterangan</th>
                            ${isOperator || isKetua ? '<th class="text-nowrap">Action</th>' : ''}
                        </tr>
                        <tr>
                            <th></th>
                            <th></th>
                            <th class="text-center text-primary">Sudut Biru</th>
                            <th class="text-center text-danger">Sudut Merah</th>
                            <th></th>
                            <th></th>
                            ${isOperator || isKetua ? '<th></th>' : ''}
                        </tr>
                    </thead>
                    <tbody>`;

            $.each(matches, function (index, match) {
                arenaSection += `
                    <tr>
                        <td>${match.match_number}</td>
                        <td>${match.round_label}</td>
                        <td class="text-primary fw-bold">
                            ${match.round_level === 1 && match.blue_name == 'TBD' ? 'BYE' : match.blue_name || 'TBD'}<br>
                            <small>${match.blue_contingent || '-'}</small><br>
                            <small class="text-info">Score: ${match.participant_1_score ?? '-'}</small>
                        </td>
                        <td class="text-danger fw-bold">
                            ${match.round_level === 1 && match.red_name == 'TBD' ? 'BYE' : match.red_name || 'TBD'}<br>
                            <small>${match.red_contingent || '-'}</small><br>
                            <small class="text-info">Score: ${match.participant_2_score ?? '-'}</small>
                        </td>
                        <td>
                        ${
                            match.winner_name
                            ? `<div class="btn btn-success"><i class="bi bi-trophy"></i> ${match.winner_name}</div>`
                            : `<div>-</div>`
                        }
                        </td>

                        <td>${match.status}</td>
                        ${isOperator || isKetua ? `
                            <td class="text-nowrap">
                                <div class="d-flex gap-1">
                                    <a href="#" class="btn btn-outline-success btn-enter-match"
                                        data-id="${match.id}" data-arena="${match.arena_name}" data-tournament="${match.tournament_name}">
                                        Masuk
                                    </a>
                                    ${match.status === 'finished' && match.winner_name ? `
                                        <a href="/matches/${match.id}/recap" class="btn btn-outline-warning btn-sm btn-recap-match">Rekap</a>
                                    ` : ''}
                                </div>
                            </td>` : ''}
                    </tr>`;
            });

            arenaSection += `</tbody></table></div>`;
            $('#match-tables').append(arenaSection);
            $(".loader-bar").hide();
        });

    });

    let allWinners = []; // simpan di luar .each untuk global

    // di dalam $.each(matches, function...) setelah if (!matches.length) return;
    /*matches.forEach(match => {
        if (match.winner_name) {
            let winnerScore = match.winner_corner === 'blue'
                ? (match.participant_1_score ?? 0)
                : (match.participant_2_score ?? 0);

            allWinners.push({
                match_number: match.match_number,
                winner_name: match.winner_name,
                contingent: match.winner_contingent,
                score: winnerScore,
                arena: match.arena_name
            });
        }
    });*/

    // setelah render selesai
    $('#show-winners-btn').off('click').on('click', function () {
        let sorted = allWinners.sort((a, b) => b.score - a.score);

        $('#winner-list').empty();
        sorted.forEach((w, i) => {
            $('#winner-list').append(`
                <tr>
                    <td>${w.match_number}</td>
                    <td>${w.winner_name}</td>
                    <td>${w.contingent || '-'}</td>
                    <td><span class="badge bg-success">${w.score}</span></td>
                    <td>${w.arena}</td>
                </tr>
            `);
        });

        new bootstrap.Modal(document.getElementById('winnerModal')).show();
    });



    
    
    

    // Event klik tombol Masuk
    $(document).on("click", ".btn-enter-match", function (e) {
        e.preventDefault();

        const $btn = $(this);
        const originalHtml = $btn.html();
        
        // Tampilkan loader
        $btn.prop("disabled", true).html(`<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Loading...`);

        const matchId = $btn.data("id");
        const arena = $btn.data("arena");
        const tournament = $btn.data("tournament");

        fetch("/api/matches/start", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
            },
            body: JSON.stringify({
                match_id: matchId,
                arena_name: arena,
                tournament_name: tournament
            })
        })
        .then(res => {
            // Kembalikan tombol ke semula setelah request selesai
            $btn.html(originalHtml).prop("disabled", false);

            // Tambahkan logic redirect atau update UI di sini
            console.log("Match started");
        })
        .catch(error => {
            console.error("Error:", error);
            $btn.html(originalHtml).prop("disabled", false);
            alert("Gagal memulai pertandingan!");
        });
    });


    // === Handler untuk tombol View Bracket ===
    $(document).on("click", ".btn-view-bracket", function () {
        const pool = $(this).data("pool");
        const className = $(this).data("class");
        const arena = $(this).data("arena");
        const tournament = $("#session-tournament").val();
    
        const apiUrl = `/api/bracket?tournament=${encodeURIComponent(tournament)}&arena=${encodeURIComponent(arena)}&pool=${encodeURIComponent(pool)}`;
    
        fetch(apiUrl)
            .then(res => res.json())
            .then(data => {
                const matchData = data.sort((a, b) => a.match_number - b.match_number);
    
                const rounds = {};
                let maxRound = 1;
    
                for (const match of matchData) {
                    const level = match.round_level;
                    if (!rounds[level]) rounds[level] = [];
                    rounds[level].push(match);
                    if (level > maxRound) maxRound = level;
                }
    
                renderManualBracket(rounds);
    
                $('#bracketModalLabel').text(`Pool ${pool} – ${className}`);
                $('#bracketModal').modal('show');
            })
            .catch(err => {
                console.error("Gagal ambil data bracket:", err);
                alert("Gagal memuat bagan pertandingan.");
            });
    });

    function slugify(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-')           // Ganti spasi jadi -
            .replace(/[^\w\-]+/g, '')       // Hapus karakter non-word
            .replace(/\-\-+/g, '-')         // Ganti -- jadi -
            .replace(/^-+/, '')             // Hapus - di awal
            .replace(/-+$/, '');            // Hapus - di akhir
    }
    
    
    function renderManualBracket(rounds) {
        const svgId = 'bracket-svg-layer';
        const $container = $('#bracket-container');
        $container.empty().addClass('bracket').css('position', 'relative');
    
        const $svg = $(`<svg id="${svgId}" style="position:absolute;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none;"></svg>`);
        $container.append($svg);
    
        const maxRound = Math.max(...Object.keys(rounds).map(Number));
        const matchRefs = {};
    
        for (let i = 1; i <= maxRound; i++) {
            const $round = $('<div class="round"></div>');
            const matches = rounds[i] || [];
    
            for (let j = 0; j < matches.length; j++) {
                const match = matches[j];
                //const blue = match.blue_name || 'TBD';
                //const red = match.red_name || 'TBD';

                //{match.round_level === 1 && match.red_name == 'TBD' ? 'BYE' : match.red_name || 'TBD'}<br></br>

                const blue = match.round_level === 1 && match.blue_name == 'TBD' ? 'BYE' : match.blue_name || 'TBD';
                const red =  match.round_level === 1 && match.red_name == 'TBD' ? 'BYE' : match.red_name || 'TBD';
                

                const isTBD = (blue === 'TBD' && red === 'TBD');
    
                const winner = !isTBD
                    ? (match.winner_id === match.blue_id ? 'blue' :
                       match.winner_id === match.red_id ? 'red' : null)
                    : null;
    
                const $match = $(`
                    <div class="match-wrapper" data-match-id="${match.id}" style="position: relative;">
                        <div class="match">
                            <div class="team ${winner === 'blue' ? 'winner' : ''} team-blue">${blue}</div>
                            <div class="team ${winner === 'red' ? 'winner' : ''} team-red">${red}</div>
                        </div>
                    </div>
                `);
    
                if (i > 1) {
                    const gap = 80 * Math.pow(2, i - 2);
                    $match.css('margin-top', `${gap}px`);
                }
    
                matchRefs[match.id] = $match;
                $round.append($match);
            }
    
            $container.append($round);
        }
    
        // PANGGIL konektor setelah semua DOM render
        // Tunggu 2 frame supaya layout bener-bener siap
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            renderSvgConnectors(rounds, matchRefs, svgId);
        });
    });

    }

    function renderSvgConnectors(rounds, matchRefs, svgId) {
        const svg = document.getElementById(svgId);
        svg.innerHTML = ''; // clear old
    
        const maxRound = Math.max(...Object.keys(rounds).map(Number));
    
        for (let i = 2; i <= maxRound; i++) {
            const matches = rounds[i] || [];
    
            for (const match of matches) {
                const child = matchRefs[match.id]?.find('.match');
                if (!child.length) continue;
    
                const childOffset = child.offset();
                const childX = childOffset.left;
                const childY = childOffset.top + child.outerHeight() / 2;
    
                const parentRed = matchRefs[match.parent_match_red_id]?.find('.match');
                const parentBlue = matchRefs[match.parent_match_blue_id]?.find('.match');
    
                if (!parentRed?.length || !parentBlue?.length) continue;
    
                const pRedOffset = parentRed.offset();
                const pBlueOffset = parentBlue.offset();
    
                const pRedX = pRedOffset.left + parentRed.outerWidth();
                const pRedY = pRedOffset.top + parentRed.outerHeight() / 2;
                const pBlueY = pBlueOffset.top + parentBlue.outerHeight() / 2;
    
                const centerY = (pRedY + pBlueY) / 2;
    
                console.log(`Garis dari ${pRedX},${pRedY} ke ${childX},${centerY}`);
    
                svg.innerHTML += `
                    <line x1="${pRedX}" y1="${pRedY}" x2="${pRedX}" y2="${pBlueY}" stroke="#fff" stroke-width="2"/>
                    <line x1="${pRedX}" y1="${centerY}" x2="${childX}" y2="${centerY}" stroke="#fff" stroke-width="2"/>
                `;
            }
        }
    }

    
    
    
    
    
    
    
    
    
    

    
    
    
    
    
    
    
    
    
    
    
    
    
    

});

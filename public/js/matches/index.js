$(document).ready(function () {
    var url = window.location.origin;

    const arenaName = $("#session-arena").val();
    const roleName = $("#session-role").val();
    const juriNumber = $("#session-juri-number").val();

    const role = $("#session-role").val()?.toLowerCase(); // amanin pakai optional chaining
    const isOperator = role === 'operator';

    const arena = $("#session-arena").val();
    const tournament = $("#session-tournament").val();

   

    window.Echo.channel('match-start')
    .listen('.MatchStarted', (e) => {
        const role = $("#session-role").val().toLowerCase(); // 🔥 ini penting bro
        const arena = $("#session-arena").val();
        const tournament = $("#session-tournament").val();

        if (e.arena_name === arena && e.tournament_name === tournament) {
            let route = "/matches";

            switch (role) {
                case "juri": route += `/judges/${e.match_id}`; break;
                case "dewan": route += `/referees/${e.match_id}`; break;
                case "ketua": route += `/matches/${e.match_id}/recap`; break;
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
    
        const roundLabels = {
            1: "Penyisihan",
            2: "Perempat Final",
            3: "Semifinal",
            4: "Final"
        };
    
        $.each(data, function (arenaName, pools) {
            let arenaSection = `<div class="mb-5">
                <h4 class="text-white mb-3">${arenaName.toUpperCase()}</h4>`;
    
            $.each(pools, function (poolName, matches) {
                if (matches.length === 0) return;
    
                // Sort by round_level & match_number
                matches.sort((a, b) => {
                    if (a.round_level === b.round_level) {
                        return a.match_number - b.match_number;
                    }
                    return a.round_level - b.round_level;
                });
    
                let tableHtml = `
                <div class="mb-4">
                <div class="d-flex justify-content-end align-items-center mb-2">
                    
                    <button class="btn btn-success btn-view-bracket" 
                           data-arena="${arenaName}"
                            data-pool="${poolName}"
                            data-class="${matches[0].class_name}">
                            View Bagan Pertandingan
                    </button>
                </div>
                <table class="table table-dark">
                    <thead>
                        <tr>
                            <th colspan="7" class="text-warning text-uppercase">
                                Pool ${poolName} – Kelas ${matches[0].class_name}
                            </th>
                        </tr>
                        <tr>
                            
                            <th>No Partai</th>
                            <th colspan="2" class="text-center">Peserta</th>
                            <th>Pemenang</th>
                            <th>Keterangan</th>
                            ${isOperator ? '<th class="text-nowrap">Action</th>' : ''}
                        </tr>
                        <tr>
                            
                            <th></th>
                            <th class="text-center text-primary">Sudut Biru</th>
                            <th class="text-center text-danger">Sudut Merah</th>
                            <th></th>
                            <th></th>
                            ${isOperator ? '<th></th>' : ''}
                        </tr>
                    </thead>
                    <tbody>`;
    
                let lastRoundLevel = null;
    
                $.each(matches, function (index, match) {
                    if (match.round_level !== lastRoundLevel) {
                        lastRoundLevel = match.round_level;
                        tableHtml += `
                            <tr>
                                <td colspan="7" class="text-warning text-uppercase bg-black">
                                    Babak ${roundLabels[match.round_level] || match.round_level}
                                </td>
                            </tr>`;
                    }
    
                    tableHtml += `
                    <tr>
                        <td>${match.match_number}</td>
                        <td class="text-primary fw-bold">
                            ${match.blue_name}<br><small>${match.blue_contingent}</small>
                        </td>
                        <td class="text-danger fw-bold">
                            ${match.red_name}<br><small>${match.red_contingent}</small>
                        </td>
                        <td>${match.winner_name ?? '-'}</td>
                        <td>${match.status}</td>
                        ${isOperator ? `
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
    
                tableHtml += `</tbody></table></div>`;
                arenaSection += tableHtml;
            });
    
            arenaSection += `</div>`;
            $('#match-tables').append(arenaSection);
            $(".loader-bar").hide();
        });
    });
    
    
    

    // Event klik tombol Masuk
    $(document).on("click", ".btn-enter-match", function (e) {
        e.preventDefault();
    
        const matchId = $(this).data("id");
        const arena = $(this).data("arena");
        const tournament = $(this).data("tournament");
    
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
    
    function renderManualBracket(rounds) {
        const $container = $('#bracket-container');
        $container.empty().addClass('bracket');
    
        const maxRound = Math.max(...Object.keys(rounds));
    
        for (let i = 1; i <= maxRound; i++) {
            const $round = $('<div class="round"></div>');
            const matches = rounds[i] || [];
    
            for (let j = 0; j < matches.length; j++) {
                const match = matches[j];
                const blue = match.blue_name || 'TBD';
                const red = match.red_name || 'TBD';
                const winner = match.winner_id === match.blue_id ? 'blue' : match.winner_id === match.red_id ? 'red' : null;
    
                const $match = $(`
                    <div class="match-wrapper">
                      <div class="match">
                          <div class="team ${winner === 'blue' ? 'winner' : ''} team-blue">${blue}</div>
                          <div class="team ${winner === 'red' ? 'winner' : ''} team-red">${red}</div>
                      </div>
                    </div>
                `);
    
                // Untuk round ke-2 dan seterusnya, kasih margin-top buat align ke tengah
                if (i > 1) {
                    const gap = 80 * Math.pow(2, i - 2); // makin tinggi round, makin besar gap
                    $match.css('margin-top', `${gap}px`);
                }
    
                $round.append($match);
            }
    
            $container.append($round);
        }
    }
    
    

    
    
    
    
    
    
    
    
    
    
    
    
    
    

});

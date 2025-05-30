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





    let roleLabel = roleName;

    if (roleName.toLowerCase() === 'juri' && juriNumber) {
        roleLabel += ` ${juriNumber}`;
    }

    

   function loadLiveMatchDisplay() {
        $.get(url + "/api/local-matches/live", function (data) {
            $(".loader-bar").show();
            $('#match-tables').empty();

            if ($.isEmptyObject(data)) {
                $('#match-tables').html(`
                    <div class="alert alert-warning text-center text-uppercase">
                        Belum ada pertandingan yang berlangsung
                    </div>
                `);
                $(".loader-bar").hide();
                return;
            }

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

                    matches.sort((a, b) => {
                        if (a.round_level === b.round_level) {
                            return a.match_number - b.match_number;
                        }
                        return a.round_level - b.round_level;
                    });

                    const maxRound = Math.max(...matches.map(m => m.round_level));
                    const roundLabels = getRoundLabels(maxRound);

                    let tableHtml = `
                    <div class="mb-4">
                        <table class="table table-dark">
                            <thead>
                                <tr>
                                    <th colspan="3" class="text-warning text-uppercase">
                                        ${poolName} – Kelas ${matches[0].class_name}
                                    </th>
                                </tr>
                                <tr>
                                    <th>No Partai</th>
                                    <th class="text-center text-primary">Sudut Biru</th>
                                    <th class="text-center text-danger">Sudut Merah</th>
                                </tr>
                            </thead>
                            <tbody>`;

                    let lastRoundLevel = null;

                    $.each(matches, function (index, match) {
                        if (match.round_level !== lastRoundLevel) {
                            lastRoundLevel = match.round_level;
                            tableHtml += `
                                <tr>
                                    <td colspan="3" class="text-warning text-uppercase bg-black">
                                        Babak ${roundLabels[match.round_level] || match.round_level}
                                    </td>
                                </tr>`;
                        }

                        tableHtml += `
                        <tr>
                            <td>${match.match_number}</td>
                            <td class="text-primary fw-bold">
                                ${match.round_level === 1 && match.blue_name == 'TBD' ? 'BYE' : match.blue_name || 'TBD'}<br>
                                <small>${match.round_level === 1 && match.blue_contingent == 'TBD' ? '-' : match.blue_contingent || 'TBD'}</small><br>
                                <small class="text-info">Score: ${match.participant_1_score ?? '-'}</small>
                            </td>
                            <td class="text-danger fw-bold">
                                ${match.round_level === 1 && match.red_name == 'TBD' ? 'BYE' : match.red_name || 'TBD'}<br>
                                <small>${match.round_level === 1 && match.red_contingent == 'TBD' ? '-' : match.red_contingent || 'TBD'}</small><br>
                                <small class="text-info">Score: ${match.participant_2_score ?? '-'}</small>
                            </td>
                        </tr>`;
                    });

                    tableHtml += `</tbody></table></div>`;
                    arenaSection += tableHtml;
                });

                arenaSection += `</div>`;
                $('#match-tables').append(arenaSection);
            });

            $(".loader-bar").hide();
        });
    }

    // ✅ Jalankan pertama kali
    loadLiveMatchDisplay();

    // ✅ Polling setiap 3 detik
    setInterval(loadLiveMatchDisplay, 3000);


    
    
    

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

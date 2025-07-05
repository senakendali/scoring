$(document).ready(function () {
    var url = window.location.origin;
    var matchId = $("#match-id").val();

    let currentArena = null;

    const host = window.location.hostname;
    const pusher = new Pusher('reverb', {
        wsHost: host,
        wsPort: 6001,
        forceTLS: false,
        disableStats: true,
        enabledTransports: ['ws'],
    });

    console.log("🟢 Recapitulation JS Ready, Match ID:", matchId);  

    //const globalChannel = pusher.subscribe('global.match');
    const arenaSlug = $("#session-arena").val()?.toLowerCase().replace(/\s+/g, '-');
    const globalChannel = pusher.subscribe(`arena.match.${arenaSlug}`);
     globalChannel.bind('match.changed', function (data) {
        console.log("🎯 Match changed:", data);
        window.location.href = `/matches/${data.new_match_id}/recap`; // Sesuaikan path kalau perlu
    });

    fetchMatchData();
    loadRekapitulasi(matchId);

    setInterval(function () {
        loadRekapitulasi(matchId);
    }, 2000);

    

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
        $.get(`/api/local-matches/${matchId}`, function (data) {
            currentArena = data.arena_name;
            $("#tournament-name").text(data.tournament_name).css('font-size', '18px');
            $("#match-code").text(data.arena_name + " Partai " + data.match_number);
            $("#class-name").text(data.class_name);
            $("#blue-name").text(data.blue.name);
            $("#red-name").text(data.red.name);
            $("#blue-score").text(data.blue.score);
            $("#red-score").text(data.red.score);

            const maxRound = Math.max(...data.rounds.map(r => r.round_number));
            const roundLabels = getRoundLabels(maxRound);

            $("#stage").text(roundLabels[data.rounds[0].round_number] || `Babak ${data.rounds[0].round_number}`);
    

            const activeRound = data.rounds.find(r => r.status === 'in_progress') || data.rounds[0];
            roundId = activeRound?.id || null;
           
           
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
                    window.location.href = `/matches/${selectedId}/recap`;
                });

                matchList.append(li);
            });

            // ✅ Modal baru ditampilkan setelah data selesai di-append
            $("#matchListModal").modal("show");
        });
    });

    function loadRekapitulasi(matchId) {
        $.get(`/api/local-matches/${matchId}/recap`, function (data) {
            $('#match-tables').html('');
            let finalBlue = 0;
            let finalRed = 0;
            data.forEach((round, index) => {
                let html = `
                    <table class="table table-striped mb-5">
                        <colgroup>
                            <col style="width: 22.5%;">
                            <col style="width: 12.5%;">
                            <col style="width: 30%;">
                            <col style="width: 12.5%;">
                            <col style="width: 22.5%;">
                        </colgroup>
                        <thead>
                            <tr><th colspan="5" class="table-title text-dark">Rekapitulasi Ronde ${round.round_number}</th></tr>
                            <tr>
                                <th class="blue text-center">Nilai</th>
                                <th class="blue text-center">Total</th>
                                <th class="text-center bg-secondary">Juri</th>
                                <th class="red text-center">Total</th>
                                <th class="red text-center">Nilai</th>
                            </tr>
                        </thead>
                        <tbody>`;
    
                // Baris Juri
                for (let i = 1; i <= 3; i++) {
                    const blue = round.judges.find(j => j.judge === `Juri ${i}` && j.corner === 'blue');
                    const red = round.judges.find(j => j.judge === `Juri ${i}` && j.corner === 'red');
    
                    html += `<tr>
                        <td class="blue text-center">${renderPoints(blue?.points)}</td>
                        <td class="blue text-center">${blue?.total || 0}</td>
                        <td class="text-center">Juri ${i}</td>
                        <td class="red text-center">${red?.total || 0}</td>
                        <td class="red text-center">${renderPoints(red?.points)}</td>
                    </tr>`;
                }
    
                // Baris Nilai Sah
                html += `<tr>
                    <td class="blue text-center">${renderPoints(round.valid_scores.blue.points, true)}</td>
                    <td class="blue text-center">${round.valid_scores.blue.total}</td>
                    <td class="text-center">Nilai Sah</td>
                    <td class="red text-center">${round.valid_scores.red.total}</td>
                    <td class="red text-center">${renderPoints(round.valid_scores.red.points, true)}</td>
                </tr>`;
    
                // Baris Jatuhan
                html += `<tr>
                    <td class="blue text-center">${round.jatuhan.blue}</td>
                    <td class="blue text-center">${round.jatuhan.blue}</td>
                    <td class="text-center">Jatuhan</td>
                    <td class="red text-center">${round.jatuhan.red}</td>
                    <td class="red text-center">${round.jatuhan.red}</td>
                </tr>`;
    
                // Baris Hukuman
                html += `<tr>
                    <td class="blue text-center">${round.hukuman.blue}</td>
                    <td class="blue text-center">${round.hukuman.blue}</td>
                    <td class="text-center">Hukuman</td>
                    <td class="red text-center">${round.hukuman.red}</td>
                    <td class="red text-center">${round.hukuman.red}</td>
                </tr>`;
    
                // Baris Nilai Final
                html += `<tr class="final-row">
                    <td class="blue text-center">${round.final.blue}</td>
                    <td class="blue text-center">${round.final.blue}</td>
                    <td class="text-center">Nilai Final</td>
                    <td class="red text-center">${round.final.red}</td>
                    <td class="red text-center">${round.final.red}</td>
                </tr>`;
    
                html += `</tbody></table>`;
                $('#match-tables').append(html);

                finalBlue += round.final.blue;
                finalRed += round.final.red;
            });

            $('#blue-score').text(finalBlue);
            $('#red-score').text(finalRed);
            $(".loader-bar").hide();
        });
    }
    
    

    function renderPoints(points, isValid = false) {
        if (!points || points.length === 0) return '-';

        return points.map(p => {
            if (isValid) {
                // 🔹 Untuk nilai sah (array angka)
                return `<span class="badge bg-success me-1">${p}</span>`;
            } else {
                // 🔹 Untuk poin juri (object), tampilkan angkanya aja
                const badgeClass = p.valid ? 'bg-success' : 'bg-secondary';
                return `<span class="badge ${badgeClass} me-1" title="${p.type} – ${p.valid ? 'Valid' : 'Tidak Valid'}">${p.point}</span>`;
            }
        }).join('');
    }


    
    
    
    

});

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

    const infoHtml = `
        <div class="d-flex justify-content-between align-items-center alert alert-info text-dark">
            <div>
                <strong>Arena:</strong> ${arenaName} <br>
                <strong>Peran:</strong> ${roleLabel}
            </div>
            <div>
                <form action="/logout" method="POST">
                    <input type="hidden" name="_token" value="${$('meta[name="csrf-token"]').attr('content')}">
                    <button type="submit" class="btn btn-sm btn-danger">Logout</button>
                </form>
            </div>
        </div>
    `;

    $("#match-tables").before(infoHtml);

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
                                        <a href="/matches/${match.id}/recap" class="btn btn-outline-warning btn-sm btn-recap-match">Rekap</a>
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
    

});

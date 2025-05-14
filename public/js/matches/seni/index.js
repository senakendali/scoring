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


    
    window.Echo.channel(`seni-match-start.${tournamentSlug}.${arenaSlug}`)
    .listen('.SeniMatchStarted', (e) => {
        const role = $("#session-role").val().toLowerCase(); // 🔥 ini penting bro
        const arena = $("#session-arena").val();
        const tournament = $("#session-tournament").val();

        if (e.arena_name === arena && e.tournament_name === tournament) {
            let route = "/matches/seni";

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

    

   $.get(url + "/api/local-matches/seni", function (data) {
        $(".loader-bar").show();
        $('#match-tables').empty();

        data.forEach(categoryGroup => {
            const categoryLabel = `${categoryGroup.category} - ${categoryGroup.gender === 'male' ? 'PUTRA' : 'PUTRI'}`;

            let groupHtml = `<h4 class="text-uppercase text-primary mb-3">${categoryLabel}</h4>`;

            categoryGroup.pools.forEach(pool => {
                const poolName = pool.name;
                const ageCategory = pool.matches[0]?.pool?.age_category?.name?.toUpperCase() || '-';

                let tableHtml = `
                    <div class="mb-5">
                        <table class="table table-dark mt-4">
                            <thead>
                                <tr>
                                    <th colspan="6" class="table-header text-start text-uppercase">${poolName}</th>
                                </tr>
                                <tr>
                                    <th colspan="6" class="table-header text-start text-uppercase">${ageCategory}</th>
                                </tr>
                                <tr class="table-sub-header">
                                    <th>Match</th>
                                    <th>Kontingen</th>
                                    <th colspan="3">Peserta</th>
                                    ${isOperator ? '<th>Action</th>' : '<th></th>'}
                                </tr>

                            </thead>
                            <tbody>
                `;

                pool.matches.forEach(match => {
                    tableHtml += `
                        <tr>
                            <td>${match.match_order}</td>
                            <td>${match.contingent?.name || '-'}</td>
                    `;

                    if (match.match_type === 'seni_tunggal') {
                        tableHtml += `
                            <td>${match.team_member1?.name || '-'}</td>
                            <td colspan="2">-</td>
                        `;
                    } else if (match.match_type === 'seni_ganda') {
                        tableHtml += `
                            <td>${match.team_member1?.name || '-'}</td>
                            <td>${match.team_member2?.name || '-'}</td>
                            <td>-</td>
                        `;
                    } else if (match.match_type === 'seni_regu') {
                        tableHtml += `
                            <td>${match.team_member1?.name || '-'}</td>
                            <td>${match.team_member2?.name || '-'}</td>
                            <td>${match.team_member3?.name || '-'}</td>
                        `;
                    }

                    if (isOperator) {
                        tableHtml += `
                            <td>
                                <button 
                                    class="btn btn-sm btn-success btn-enter-match"
                                    data-id="${match.id}"
                                    data-arena="${arenaName}"
                                    data-tournament="${tournament}">
                                    Mulai
                                </button>
                            </td>
                        `;
                    } else {
                        tableHtml += `<td>-</td>`;
                    }

                    tableHtml += `</tr>`;

                });

                tableHtml += `
                            </tbody>
                        </table>
                    </div>
                `;

                groupHtml += tableHtml;
            });

            $('#match-tables').append(groupHtml);
        });

        $(".loader-bar").hide();
    });

    
    
    

    // Event klik tombol Masuk
    $(document).on("click", ".btn-enter-match", function (e) {
        e.preventDefault();
    
        const matchId = $(this).data("id");
        const arena = $(this).data("arena");
        const tournament = $(this).data("tournament");
    
        fetch("/api/matches/seni/start", {
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

    function slugify(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-')           // Ganti spasi jadi -
            .replace(/[^\w\-]+/g, '')       // Hapus karakter non-word
            .replace(/\-\-+/g, '-')         // Ganti -- jadi -
            .replace(/^-+/, '')             // Hapus - di awal
            .replace(/-+$/, '');            // Hapus - di akhir
    }
    
    
    

    

    
    
    
    
    
    
    
    
    
    

    
    
    
    
    
    
    
    
    
    
    
    
    
    

});

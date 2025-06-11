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

            // 🔁 Loop per kategori usia (sudah diproses di backend)
            categoryGroup.age_categories.forEach(ageGroup => {
                const ageCategory = ageGroup.age_category || 'TANPA USIA';
                let groupHtml = `
                    <h4 class="text-uppercase text-primary mb-2">${categoryLabel}</h4>
                    <h5 class="text-uppercase text-secondary mb-3">${ageCategory.toUpperCase()}</h5>
                `;

                ageGroup.pools.forEach(pool => {
                    const poolName = pool.name;

                    let tableHtml = `
                        <div class="mb-5">
                            <table class="table table-dark mt-2">
                                <thead>
                                    <tr>
                                        <th colspan="6" class="table-header text-uppercase">
                                            <span>${poolName}</span>
                                        </th>
                                        <th class="table-header text-end">
                                            <button class="btn btn-sm btn-outline-info btn-view-rank" data-category="${categoryLabel}" data-age="${ageCategory}" data-pool="${poolName}" data-matches='${JSON.stringify(pool.matches)}'>
                                                Lihat Peringkat
                                            </button>
                                        </th>
                                    </tr>
                                    <tr class="table-sub-header">
                                        <th>Match</th>
                                        <th>Kontingen</th>
                                        <th colspan="3">Peserta</th>
                                        <th>Score</th>
                                        <th>Action</th>
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

                        if (match.match_type === 'seni_tunggal' || match.match_type === 'solo_kreatif') {
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

                        const scoreValue = parseFloat(match.final_score);
                        const scoreText = match.status === 'finished' && !isNaN(scoreValue)
                            ? scoreValue.toFixed(6)
                            : '-';

                        tableHtml += `<td>${scoreText}</td>`;

                        if (match.status === 'finished') {
                            tableHtml += `
                                <td>
                                    <button class="btn btn-sm btn-outline-warning btn-recap-match" data-id="${match.id}">
                                        Recap
                                    </button>
                                </td>
                            `;
                        } else if (isOperator) {
                            tableHtml += `
                                <td>
                                    <button 
                                        class="btn btn-sm btn-success btn-enter-match"
                                        data-id="${match.id}"
                                        data-arena="${arenaName}"
                                        data-tournament="${tournament}">
                                        Masuk
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
        });

        $(".loader-bar").hide();
    });




    $(document).on("click", ".btn-view-rank", function () {
        const rawMatches = $(this).data("matches");
        const matches = Array.isArray(rawMatches) ? rawMatches : JSON.parse(rawMatches);

        const category = $(this).data("category") || '';
        const age = $(this).data("age") || '';
        const pool = $(this).data("pool") || '';

        const sorted = matches
            .filter(m => m.status === 'finished' && !isNaN(parseFloat(m.final_score)))
            .sort((a, b) => parseFloat(b.final_score) - parseFloat(a.final_score));

        const $list = $("#ranking-list");
        $list.empty();

        if (sorted.length === 0) {
            $list.append('<li class="list-group-item bg-dark text-white">Belum ada skor.</li>');
        } else {
            sorted.forEach((match, idx) => {
                const peserta = [match.team_member1?.name, match.team_member2?.name, match.team_member3?.name]
                    .filter(Boolean).join(' / ');
                const contingent = match.contingent?.name || '-';

                $list.append(`
                    <li class="list-group-item bg-dark text-white text-start">
                        <strong>#${idx + 1}</strong> ${peserta} (${contingent})<br>
                        <small>${parseFloat(match.final_score).toFixed(6)}</small>
                    </li>
                `);
            });
        }

        // 👇 Set judul sesuai kategori
        const title = `${category} ${age} ${pool}`;
        $("#rankingModalLabel").text('Ranking Peserta ' + title).addClass('text-uppercase');

        // Tampilkan modal fullscreen
        const modal = new bootstrap.Modal(document.getElementById("rankingModal"));
        modal.show();
    });








    // ✅ Handler tombol Recap
    $(document).on("click", ".btn-recap-match", function () {
        const matchId = $(this).data("id");
        window.location.href = `/matches/seni/${matchId}/recap`;
    });

    
    
    

    // Event klik tombol Masuk
    $(document).on("click", ".btn-enter-match", function (e) {
        e.preventDefault();

        const $btn = $(this);
        const originalHtml = $btn.html();

        const matchId = $btn.data("id");
        const arena = $btn.data("arena");
        const tournament = $btn.data("tournament");

        // Tampilkan loader dan disable tombol
        $btn.prop("disabled", true).html(`
            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            Loading...
        `);

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
        })
        .then(response => {
            // ✅ Optional: Tambahkan redirect atau modal jika perlu
            console.log("✅ Match started");
        })
        .catch(err => {
            console.error("❌ Gagal mulai match:", err);
        })
        .finally(() => {
            // Kembalikan tombol ke semula
            $btn.prop("disabled", false).html(originalHtml);
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

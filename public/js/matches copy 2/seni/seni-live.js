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

    

    function loadLiveSeniMatches() {
        $.get(url + "/api/local-matches/seni/live", function (data) {
            $(".loader-bar").show();
            $('#match-tables').empty();

            if ($.isEmptyObject(data)) {
                $('#match-tables').html(`
                    <div class="alert alert-warning text-center text-uppercase">
                        Belum ada peserta yang tampil
                    </div>
                `);
                $(".loader-bar").hide();
                return;
            }

            $.each(data, function (arenaName, categoryGroups) {
                let arenaSection = `<div class="mb-5">
                    <h4 class="text-warning text-center text-uppercase mb-3">${arenaName.toUpperCase()}</h4>`;

                categoryGroups.forEach(categoryGroup => {
                    const categoryLabel = `${categoryGroup.category} - ${categoryGroup.gender === 'male' ? 'PUTRA' : 'PUTRI'}`;
                    let groupHtml = `<h5 class="text-uppercase text-primary mb-2">${categoryLabel}</h5>`;

                    categoryGroup.pools.forEach(pool => {
                        const poolName = pool.name;
                        const ageCategory = pool.matches[0]?.pool?.age_category?.name?.toUpperCase() || '-';

                        let tableHtml = `
                            <div class="mb-4">
                                <table class="table table-dark mt-3">
                                    <thead>
                                        <tr>
                                            <th colspan="6" class="table-header text-uppercase">
                                                <span>${poolName}</span>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th colspan="6" class="table-header text-start text-uppercase">${ageCategory}</th>
                                        </tr>
                                        <tr class="table-sub-header">
                                            <th>Match</th>
                                            <th>Kontingen</th>
                                            <th colspan="3">Peserta</th>
                                            <th>Score</th>
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
                            const scoreText =  !isNaN(scoreValue)
                                ? scoreValue.toFixed(6)
                                : '-';

                            tableHtml += `<td>${scoreText}</td></tr>`;
                        });

                        tableHtml += `
                                    </tbody>
                                </table>
                            </div>
                        `;

                        groupHtml += tableHtml;
                    });

                    arenaSection += groupHtml;
                });

                arenaSection += `</div>`;
                $('#match-tables').append(arenaSection);
            });

            $(".loader-bar").hide();
        });
    }

    // ✅ Jalankan pertama kali saat halaman load
    loadLiveSeniMatches();

    // ✅ Polling setiap 3 detik
    setInterval(loadLiveSeniMatches, 3000);




    $(document).on("click", ".btn-view-rank", function () {
        const rawMatches = $(this).data("matches");
        const matches = Array.isArray(rawMatches) ? rawMatches : JSON.parse(rawMatches);

        const sorted = matches
            .filter(m => m.status === 'finished' && !isNaN(parseFloat(m.final_score)))
            .sort((a, b) => parseFloat(b.final_score) - parseFloat(a.final_score))
            .slice(0, 3);

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
                    <li class="list-group-item bg-dark text-white d-flex justify-content-between">
                        <strong>#${idx + 1}</strong> ${peserta} (${contingent})<br>
                        <small>${parseFloat(match.final_score).toFixed(6)}</small>
                    </li>
                `);
            });
        }

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

$(document).ready(function () {
    const url = APP.baseUrl;

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
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });


    
    window.Echo.channel(`seni-match-start.${tournamentSlug}.${arenaSlug}`)
    .listen('.SeniMatchStarted', (e) => {
        const role = $("#session-role").val().toLowerCase(); // 🔥 ini penting bro
        const arena = $("#session-arena").val();
        const tournament = $("#session-tournament").val();

        if (e.arena_name === arena && e.tournament_name === tournament) {
            let route = url + "/matches/seni";

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

        (data || []).forEach(function (categoryGroup) {
            var categoryLabel = categoryGroup.category + ' - ' + (categoryGroup.gender === 'male' ? 'PUTRA' : 'PUTRI');

            (categoryGroup.age_categories || []).forEach(function (ageGroup) {
            var ageCategory = ageGroup.age_category || 'TANPA USIA';

            var groupHtml = `
                <h4 class="text-uppercase text-primary mb-2">${categoryLabel}</h4>
                <h5 class="text-uppercase text-secondary mb-3">${String(ageCategory).toUpperCase()}</h5>
            `;

            (ageGroup.pools || []).forEach(function (pool) {
                var poolName = pool.name || '-';

                var tableHtml = `
                <div class="mb-5">
                    <table class="table table-dark mt-2">
                    <thead>
                        <tr>
                        <th colspan="6" class="table-header text-uppercase">
                            <span>${poolName}</span>
                        </th>
                        <th class="table-header text-end">
                            <button
                            class="btn btn-sm btn-outline-info btn-view-rank"
                            data-category="${categoryLabel}"
                            data-age="${ageCategory}"
                            data-pool="${poolName}"
                            data-matches='${JSON.stringify(pool.matches || [])}'>
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
                `;

                var rows = (pool.matches || []).slice();

                // Kelompokkan per pasangan:
                // Kunci utama: battle_group kalau ada, fallback ke match_order (kalau dua baris share order → satu pasangan)
                var groupsMap = {};
                rows.forEach(function (m, i) {
                var key = (m.battle_group != null) ? 'G' + m.battle_group
                        : (m.match_order != null) ? 'O' + m.match_order
                        : 'X' + i; // fallback unik
                if (!groupsMap[key]) groupsMap[key] = [];
                groupsMap[key].push(m);
                });

                // Urutkan grup berdasarkan angka di key
                var groupKeys = Object.keys(groupsMap).sort(function (a, b) {
                var na = parseInt(a.slice(1), 10);
                var nb = parseInt(b.slice(1), 10);
                if (isNaN(na) && isNaN(nb)) return a.localeCompare(b);
                if (isNaN(na)) return 1;
                if (isNaN(nb)) return -1;
                return na - nb;
                });

                var tbodyHtml = '<tbody>';

                groupKeys.forEach(function (gkey) {
                var arr = groupsMap[gkey];

                // Fallback: kalau corner kosong, tetapkan index 0=blue, 1=red
                // Fallback corner & flag battle, HANYA kalau memang pasangan (>= 2)
                arr.forEach(function (r, idx) {
                    var c = (r.corner || '').toString().toLowerCase();

                    // hanya inject corner otomatis jika ini benar-benar battle pair
                    if (arr.length >= 2 && c !== 'blue' && c !== 'red') {
                        if (idx === 0) r.corner = 'blue';
                        else if (idx === 1) r.corner = 'red';
                    }

                    // tandai mode battle hanya untuk grup dengan >= 2 baris
                    if (!r.mode && arr.length >= 2) r.mode = 'battle';

                    // battle_group jangan dipaksa untuk non-battle (biarkan apa adanya)
                    if (arr.length >= 2 && r.battle_group == null) {
                        r.battle_group = parseInt(gkey.slice(1), 10);
                    }
                });


                // Sort isi grup: BLUE dulu, RED berikutnya, lainnya belakangan
                arr.sort(function (a, b) {
                    var rank = function (v) {
                    var s = (v || '').toString().toLowerCase();
                    if (s === 'blue') return 0;
                    if (s === 'red')  return 1;
                    return 2;
                    };
                    var ra = rank(a.corner);
                    var rb = rank(b.corner);
                    if (ra !== rb) return ra - rb;
                    // tie-break stabil
                    var ia = a.id || 0, ib = b.id || 0;
                    return ia - ib;
                });

                // Separator sekali per grup
                var first = arr[0] || {};
                var numberForSep = (first.match_order != null) ? first.match_order : '-';
                var roundLabel = first.round_label || '';
                tbodyHtml += `
                    <tr class="battle-group-sep">
                    <td colspan="7">
                        <span class="match-pill">Match ${numberForSep}</span>
                        ${roundLabel ? `<span class="ms-2">${roundLabel}</span>` : ``}
                    </td>
                    </tr>
                `;

                // Render baris: warna sesuai corner (BLUE dulu karena hasil sort)
                arr.forEach(function (match) {
                    var corner = (match.corner || '').toString().toLowerCase();
                    var rowClass = corner === 'blue' ? 'battle-blue' : (corner === 'red' ? 'battle-red' : '');

                    tbodyHtml += `<tr class="${rowClass}">`;

                    // Kolom "Match": kosong karena sudah ada di separator grup
                    tbodyHtml += `<td></td>`;

                    var conting = (match.contingent && match.contingent.name)
                    ? match.contingent.name
                    : (match.contingent_name || '-');
                    tbodyHtml += `<td>${conting}</td>`;

                    var tm1 = (match.team_member1 && match.team_member1.name) ? match.team_member1.name : (match.participant_1 || '-');
                    var tm2 = (match.team_member2 && match.team_member2.name) ? match.team_member2.name : (match.participant_2 || '-');
                    var tm3 = (match.team_member3 && match.team_member3.name) ? match.team_member3.name : (match.participant_3 || '-');

                    if (match.match_type === 'seni_tunggal' || match.match_type === 'solo_kreatif') {
                    tbodyHtml += `<td>${tm1}</td><td colspan="2">-</td>`;
                    } else if (match.match_type === 'seni_ganda') {
                    tbodyHtml += `<td>${tm1}</td><td>${tm2}</td><td>-</td>`;
                    } else if (match.match_type === 'seni_regu') {
                    tbodyHtml += `<td>${tm1}</td><td>${tm2}</td><td>${tm3}</td>`;
                    } else {
                    tbodyHtml += `<td colspan="3">-</td>`;
                    }

                    var scoreVal = parseFloat(match.final_score);
                    var scoreText = (match.status === 'finished' && !isNaN(scoreVal)) ? scoreVal.toFixed(6) : '-';
                    tbodyHtml += `<td>${scoreText}</td>`;

                    if (match.status === 'finished') {
                    tbodyHtml += `
                        <td>
                        <button class="btn btn-sm btn-outline-warning btn-recap-match" data-id="${match.id}">
                            Recap
                        </button>
                        </td>
                    `;
                    } else if (typeof isOperator !== 'undefined' && isOperator) {
                        var isBattleRow =
                            (match.mode && match.mode.toLowerCase() === 'battle') ||
                            (['blue','red'].indexOf((match.corner || '').toLowerCase()) !== -1);

                        // battle → tombol netral supaya kontras di baris biru/merah
                        var enterBtnClass = isBattleRow
                            ? 'btn btn-sm btn-outline-light btn-enter-match'
                            : 'btn btn-sm btn-success btn-enter-match';

                        tbodyHtml += `
                            <td>
                            <button
                                class="${enterBtnClass}"
                                data-id="${match.id}"
                                data-arena="${typeof arenaName !== 'undefined' ? arenaName : ''}"
                                data-tournament="${typeof tournament !== 'undefined' ? tournament : ''}">
                                Masuk
                            </button>
                            </td>
                        `;
                    } else {
                    tbodyHtml += `<td>-</td>`;
                    }

                    tbodyHtml += `</tr>`;
                });
                });

                tbodyHtml += `</tbody>`;
                tableHtml += tbodyHtml;
                tableHtml += `
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




    $('#btn-create-match-final').on('click', function () {
        $('#final-match-body').html('Loading...');

        $.get(url + '/api/local-seni-matches/pool-winners', function (res) {
            let html = '';

            res.forEach(pool => {
                const options = pool.participants.map(p => `
                    <option value="${p.id}">${p.name} - ${p.contingent}</option>
                `).join('');

                html += `
                    <div class="mb-3">
                        <label class="form-label">${pool.pool_name} (${pool.category} - ${pool.gender.toUpperCase()} - ${pool.age_category})</label>
                        <select class="form-select winner-select" data-pool-id="${pool.pool_id}" data-category="${pool.category}" data-gender="${pool.gender}" data-age="${pool.age_category}">
                            <option value="">-- Pilih Juara Pool --</option>
                            ${options}
                        </select>
                    </div>
                `;
            });

            $('#final-match-body').html(html);
            $('#modal-final-match').modal('show');
        });
    });

    $('#submit-final-match').on('click', function () {
        const selected = [];

        $('.winner-select').each(function () {
            const poolId = $(this).data('pool-id');
            const memberId = $(this).val();

            if (memberId) {
                selected.push({
                    pool_id: poolId,
                    member_id: memberId,
                    category: $(this).data('category'),
                    gender: $(this).data('gender'),
                    age_category: $(this).data('age'),
                });
            }
        });

        if (selected.length < 2) {
            alert('Minimal pilih 2 juara pool untuk membuat match.');
            return;
        }

        $.ajax({
            url: url + '/api/local-seni-matches/create-pool-final-match',
            method: 'POST',
            data: { winners: selected },
            success: function (res) {
                alert('Match berhasil dibuat!');
                location.reload();
            },
            error: function () {
                alert('Gagal membuat match.');
            }
        });
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

        fetch(url + "/api/matches/seni/start", {
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

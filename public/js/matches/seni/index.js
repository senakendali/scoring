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

        // 1) Flatten semua match + meta
        const flat = [];
        (data || []).forEach((catGroup) => {
            const catLabel = `${catGroup.category} - ${catGroup.gender === 'male' ? 'PUTRA' : 'PUTRI'}`;
            (catGroup.age_categories || []).forEach((ageGroup) => {
            const ageLabel = ageGroup.age_category || 'TANPA USIA';
            (ageGroup.pools || []).forEach((pool) => {
                const poolName = pool.name || '-';
                (pool.matches || []).forEach((m) => {
                flat.push({
                    row: m,
                    meta: { cat: catLabel, age: ageLabel, pool: poolName }
                });
                });
            });
            });
        });

        if (flat.length === 0) {
            $('#match-tables').html(`<div class="text-center text-muted py-5">Belum ada pertandingan.</div>`);
            $(".loader-bar").hide();
            return;
        }

        // 2) Grouping dengan COMPOSITE KEY supaya battle_group tidak nyampur antar gender/usia/pool
        const groupsMap = {};
        flat.forEach((item, idx) => {
            const m = item.row;
            const meta = item.meta || {};
            const mode = (m.mode || 'default').toLowerCase();
            const cornerStr = (m.corner || '').toLowerCase();
            const hasCorner = (cornerStr === 'blue' || cornerStr === 'red');

            // metaKey memastikan scope pairing tetap dalam bracket yg sama
            const metaKey = `${meta.cat}||${meta.age}||${meta.pool}`;

            let key;
            if (mode === 'battle' && m.battle_group != null) {
            // Composite battle key
            key = `B|${metaKey}|${m.battle_group}`;
            } else if (hasCorner && m.match_order != null) {
            // Non-battle pairing by order tapi tetap per scope meta
            key = `O|${metaKey}|${m.match_order}`;
            } else {
            // Single row (default)
            key = `S|${metaKey}|${m.id ?? `X${idx}`}`;
            }

            if (!groupsMap[key]) groupsMap[key] = [];
            groupsMap[key].push(item);
        });

        // Helper urutan grup
        const minOrderOfGroup = (arr) => {
            const nums = arr.map(it => parseInt(it.row.match_order, 10)).filter(n => !isNaN(n));
            return nums.length ? Math.min(...nums) : Number.MAX_SAFE_INTEGER;
        };
        const maxRoundPrio = (arr) => {
            const nums = arr.map(it => parseInt(it.row.round_priority, 10)).filter(n => !isNaN(n));
            return nums.length ? Math.max(...nums) : -1;
        };

        // 3) Urutkan grup (berdasarkan match_order terkecil, lalu round_priority)
        const groupKeys = Object.keys(groupsMap).sort((a, b) => {
            const A = groupsMap[a], B = groupsMap[b];
            const ao = minOrderOfGroup(A);
            const bo = minOrderOfGroup(B);
            if (ao !== bo) return ao - bo;

            const ap = maxRoundPrio(A);
            const bp = maxRoundPrio(B);
            if (ap !== bp) return bp - ap;

            return a.localeCompare(b);
        });

        // 4) Build satu tabel global
        let html = `
            <div class="mb-5">
            <table class="table table-dark mt-2">
                <thead>
                <tr class="table-sub-header" style="height:60px;">
                    <th style="width:90px;">Match</th>
                    <th class="contingent-col">Kontingen</th>
                    <th class="participant-col">Peserta 1</th>
                    <th class="participant-col">Peserta 2</th>
                    <th class="participant-col">Peserta 3</th>
                    <th style="width:120px;">Score</th>
                    <th style="width:120px;">Action</th>
                </tr>
                </thead>
                <tbody>
        `;

        groupKeys.forEach((gk) => {
            const items = groupsMap[gk].slice();
            const isBattle = gk.startsWith('B|');

            // Fallback corner hanya untuk battle group yang berpasangan (2 baris)
            if (isBattle && items.length === 2) {
            items.forEach((it, idx) => {
                const c = (it.row.corner || '').toLowerCase();
                if (c !== 'blue' && c !== 'red') it.row.corner = (idx === 0) ? 'blue' : 'red';
            });
            }

            // Urutkan: BLUE → RED → others
            items.sort((A, B) => {
            const rank = (v) => {
                const s = (v || '').toLowerCase();
                if (s === 'blue') return 0;
                if (s === 'red')  return 1;
                return 2;
            };
            const ra = rank(A.row.corner), rb = rank(B.row.corner);
            if (ra !== rb) return ra - rb;
            const ia = A.row.id || 0, ib = B.row.id || 0;
            return ia - ib;
            });

            const sepOrder = minOrderOfGroup(items);
            const head = items[0] || {};
            const r0   = head.row || {};
            const meta = head.meta || {};
            const roundLabel = r0.round_label || '';

            // Badge info (tetap tampilkan info agar konteks nggak hilang, walau tabelnya global)
            const bCat  = meta.cat  ? ` <span class="match-pill">${meta.cat}</span>`   : '';
            const bAge  = meta.age  ? ` <span class="match-pill">${meta.age}</span>`         : '';
            const bPool = meta.pool ? ` <span class="match-pill">${meta.pool}</span>` : '';
            const bRound = ` <span class="match-pill text-uppercase">${roundLabel}</span>`;

            html += `
            <tr class="battle-group-sep">
                <td colspan="7">
                <span class="match-pill">Match ${Number.isFinite(sepOrder) ? sepOrder : '-'}</span>
                
                ${bCat}${bRound}${bAge}${bPool}
                </td>
            </tr>
            `;

            // Render isi grup (pairing terjaga: Blue dahulu, Red berikutnya)
            items.forEach(({row: m}) => {
            let rowClass = '';
            if (isBattle) {
                const c = (m.corner || '').toLowerCase();
                if (c === 'blue') rowClass = 'battle-blue';
                else if (c === 'red') rowClass = 'battle-red';
            }

            html += `<tr class="${rowClass}">`;

            // Kolom Match (kosong — sudah di separator)
            html += `<td></td>`;

            // Kontingen
            const conting = (m.contingent && m.contingent.name) ? m.contingent.name : (m.contingent_name || '-');
            html += `<td class="contingent-col">${conting}</td>`;

            // Peserta 1-3
            const tm1 = (m.team_member1 && m.team_member1.name) ? m.team_member1.name : (m.participant_1 || '-');
            const tm2 = (m.team_member2 && m.team_member2.name) ? m.team_member2.name : (m.participant_2 || '-');
            const tm3 = (m.team_member3 && m.team_member3.name) ? m.team_member3.name : (m.participant_3 || '-');

            if (m.match_type === 'seni_tunggal' || m.match_type === 'solo_kreatif') {
                html += `<td class="participant-col">${tm1}</td><td class="participant-col">-</td><td class="participant-col">-</td>`;
            } else if (m.match_type === 'seni_ganda') {
                html += `<td class="participant-col">${tm1}</td><td class="participant-col">${tm2}</td><td class="participant-col">-</td>`;
            } else if (m.match_type === 'seni_regu') {
                html += `<td class="participant-col">${tm1}</td><td class="participant-col">${tm2}</td><td class="participant-col">${tm3}</td>`;
            } else {
                html += `<td class="participant-col">-</td><td class="participant-col">-</td><td class="participant-col">-</td>`;
            }

            // Score
            const scoreVal  = parseFloat(m.final_score);
            const scoreText = (m.status === 'finished' && !isNaN(scoreVal)) ? scoreVal.toFixed(6) : '-';
            html += `<td>${scoreText}</td>`;

            // Action
            if (m.status === 'finished') {
                html += `
                <td>
                    <button class="btn btn-sm btn-outline-warning btn-recap-match" data-id="${m.id}">
                    Recap
                    </button>
                </td>
                `;
            } else if (typeof isOperator !== 'undefined' && isOperator) {
                const enterBtnClass = isBattle ? 'btn btn-sm btn-outline-light btn-enter-match'
                                            : 'btn btn-sm btn-success btn-enter-match';
                html += `
                <td>
                    <button
                    class="${enterBtnClass}"
                    data-id="${m.id}"
                    data-arena="${typeof arenaName !== 'undefined' ? arenaName : ''}"
                    data-tournament="${typeof tournament !== 'undefined' ? tournament : ''}">
                    Masuk
                    </button>
                </td>
                `;
            } else {
                html += `<td>-</td>`;
            }

            html += `</tr>`;
            });
        });

        html += `
                </tbody>
            </table>
            </div>
        `;

        $('#match-tables').append(html);
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

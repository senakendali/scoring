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

    $("#tournament-name").css('border-top-left-radius', '0');
    $("#tournament-name").css('border-top-right-radius', '0');


    
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

    
    loadSeniMatchesAdmin();
    


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
                const score = parseFloat(match.final_score).toFixed(6);
                const medal = match.medal || '';

                $list.append(`
                    <li class="list-group-item bg-dark text-white text-start d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <div class="flex-grow-1">
                            <strong>#${idx + 1}</strong> ${peserta} (${contingent})<br>
                            <small>Score: ${score}</small>
                        </div>
                        <div>
                            <select class="form-select form-select-sm medal-dropdown" data-match-id="${match.id}">
                                <option value="">-- Pilih Medali --</option>
                                <option value="emas" ${medal === 'emas' ? 'selected' : ''}>Emas</option>
                                <option value="perak" ${medal === 'perak' ? 'selected' : ''}>Perak</option>
                                <option value="perunggu" ${medal === 'perunggu' ? 'selected' : ''}>Perunggu</option>
                            </select>
                        </div>
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

    $(document).on("change", ".medal-dropdown", function () {
        const matchId = $(this).data("match-id");
        const medal = $(this).val();
        const $select = $(this);

        fetch(url + `/api/seni-matches/${matchId}/set-medal`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            body: JSON.stringify({ medal }),
        })
        .then(res => {
            if (!res.ok) throw res;
            return res.json();
        })
        .then(data => {
            //showAlert("✅ Medali berhasil disimpan!", "Informasi");

            // Tutup modal ranking
            const modal = bootstrap.Modal.getInstance(document.getElementById("rankingModal"));
            modal.hide();

            // Reload ulang data pertandingan seni
            loadSeniMatchesAdmin(); // pastikan fungsi ini ada dan dipakai untuk ambil ulang data dari API
        })
        .catch(async err => {
            let message = "❌ Gagal menyimpan medali.";

            if (err instanceof Response) {
                const json = await err.json().catch(() => null);
                message = json?.message || message;
            }

            showAlert(message, "Peringatan");
            $select.val(""); // reset jika gagal
        });

    }); 











    // ✅ Handler tombol Recap
    $(document).on("click", ".btn-recap-match", function () {
        const matchId = $(this).data("id");
        window.location.href = url + `/matches/seni/${matchId}/recap`;
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
    
    $(document).on('click', '.btn-set-score', function () {
    const matchId = $(this).data('id');
    const name = $(this).data('name'); // Kontingen
    const participant = $(this).data('participant'); // Peserta
    const score = $(this).data('score');

    $('#match-id-for-score').val(matchId);
    $('#contingent-name').val(name);
    $('#participant-name').val(participant); // Tambahkan ini
    $('#final-score').val(score);

    $('#manualScoreModal').modal('show');
});


// Saat blur, pastikan format 6 angka di belakang koma
$('#final-score').on('blur', function () {
    let raw = $(this).val().replace(/\D/g, ''); // hanya ambil digit angka
    if (!raw) return;

    let parsed = parseInt(raw, 10);
    let finalScore = parsed / 100000; // bagi 100000
    $(this).val(finalScore.toFixed(6)); // format ke 6 desimal
});


// Saat submit, pastikan parse aman
$('#submit-score-btn').on('click', function () {
    const matchId = $('#match-id-for-score').val();
    let value = $('#final-score').val().replace(',', '.');
    const score = parseFloat(value);

    if (isNaN(score)) {
        alert('Nilai tidak valid');
        return;
    }

    $.ajax({
        url: url + `/api/local-seni-matches/${matchId}/set-score-manual`,
        method: 'POST',
        data: {
            score: score.toFixed(6),
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function (res) {
            $('#manualScoreModal').modal('hide');
            alert(res.message || 'Skor berhasil disimpan');
            loadSeniMatchesAdmin(); // reload data
        },
        error: function () {
            alert('Gagal menyimpan skor');
        }
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

   function loadSeniMatchesAdmin() {
  $.get(url + "/api/local-matches/seni/admin", function (data) {
    $(".loader-bar").show();
    $('#match-tables').empty();

    // --- BACA FILTER (opsional) ---
    const parseIntSafe = (v) => {
      const n = parseInt(String(v || '').trim(), 10);
      return Number.isFinite(n) ? n : null;
    };
    const selectedArena = String($('#filter-arena').val() || '').trim(); // "" = semua arena
    let fromPartai = parseIntSafe($('#filter-from').val());
    let toPartai   = parseIntSafe($('#filter-to').val());

    // normalisasi kalau user kebalik
    if (fromPartai !== null && toPartai !== null && fromPartai > toPartai) {
      const t = fromPartai; fromPartai = toPartai; toPartai = t;
    }
    const hasFrom = fromPartai !== null;
    const hasTo   = toPartai   !== null;

    // Helper: escape JSON untuk atribut HTML
    const escAttr = (s) =>
      String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    // -- Filter daftar arena sesuai dropdown --
    const arenaEntries = Object.entries(data || {}).filter(
      ([arenaName]) => !selectedArena || arenaName === selectedArena
    );

    if (arenaEntries.length === 0) {
      $('#match-tables').html(`<div class="text-center text-muted py-5">Belum ada pertandingan.</div>`);
      $(".loader-bar").hide();
      return;
    }

    // 🔁 Loop per arena → SATU tabel per arena
    arenaEntries.forEach(([arenaName, categoryGroups]) => {
      let arenaSection = `<div class="mb-4">
        <h3 class="text-white pb-2 mb-3">${(arenaName || '').toUpperCase()}</h3>`;

      // 1) Flatten semua match + meta (cat, age, pool)
      const flat = [];
      (categoryGroups || []).forEach(catGroup => {
        const catLabel = `${catGroup.category} - ${catGroup.gender === 'male' ? 'PUTRA' : 'PUTRI'}`;
        (catGroup.age_categories || []).forEach(ageGroup => {
          const ageLabel = ageGroup.age_category || 'TANPA USIA';
          (ageGroup.pools || []).forEach(pool => {
            const poolName = pool.name || '-';
            (pool.matches || []).forEach(m => {
              flat.push({ row: m, meta: { cat: catLabel, age: ageLabel, pool: poolName } });
            });
          });
        });
      });

      if (flat.length === 0) {
        arenaSection += `<div class="text-center text-muted py-5">Belum ada pertandingan.</div>`;
        arenaSection += `</div>`;
        $('#match-tables').append(arenaSection);
        return;
      }

      // ===== Pool → daftar match NON-BATTLE (buat tombol rank per pool) =====
      const poolKeyOf = (meta) => `${meta.cat}||${meta.age}||${meta.pool}`;
      const poolRankMap = {};
      (flat || []).forEach(item => {
        const m = item.row || {};
        const mode = (m.mode || 'default').toLowerCase();
        const isBattle = (mode === 'battle' && m.battle_group != null);
        if (isBattle) return; // tombol rank hanya untuk non-battle
        const pk = poolKeyOf(item.meta || {});
        if (!poolRankMap[pk]) poolRankMap[pk] = [];
        poolRankMap[pk].push(m);
      });
      const rankBtnPrintedPools = new Set(); // supaya 1x per pool

      // 2) Grouping dengan COMPOSITE KEY supaya battle tidak nyampur antar cat/age/pool
      const groupsMap = {};
      flat.forEach((item, idx) => {
        const m = item.row || {};
        const meta = item.meta || {};
        const mode = (m.mode || 'default').toLowerCase();
        const cornerStr = (m.corner || '').toLowerCase();
        const hasCorner = (cornerStr === 'blue' || cornerStr === 'red');

        const metaKey = `${meta.cat}||${meta.age}||${meta.pool}`;

        let key;
        if (mode === 'battle' && m.battle_group != null) {
          key = `B|${metaKey}|${m.battle_group}`;
        } else if (hasCorner && m.match_order != null) {
          key = `O|${metaKey}|${m.match_order}`;
        } else {
          key = `S|${metaKey}|${m.id ?? `X${idx}`}`;
        }

        if (!groupsMap[key]) groupsMap[key] = [];
        groupsMap[key].push(item);
      });

      // 3) Urutkan & FILTER antar grup:
      const minOrderOfGroup = (arr) => {
        const nums = arr.map(it => parseInt(it.row.match_order, 10)).filter(n => Number.isFinite(n));
        return nums.length ? Math.min(...nums) : null; // null kalau tak bernomor
      };
      const maxRoundPrio = (arr) => {
        const nums = arr.map(it => parseInt(it.row.round_priority, 10)).filter(n => Number.isFinite(n));
        return nums.length ? Math.max(...nums) : -1;
      };
      const inRange = (ord) => {
        if (!Number.isFinite(ord)) {
          // kalau filter aktif dan ord null → sembunyikan
          return !(hasFrom || hasTo);
        }
        if (hasFrom && ord < fromPartai) return false;
        if (hasTo   && ord > toPartai)   return false;
        return true;
      };

      let groupKeys = Object.keys(groupsMap)
        // Filter dulu berdasarkan range partai (pakai min match_order grup)
        .filter(k => inRange(minOrderOfGroup(groupsMap[k])))
        // Lalu sort stabil: min(match_order), round_priority desc, lalu key
        .sort((a, b) => {
          const A = groupsMap[a], B = groupsMap[b];
          const ao = minOrderOfGroup(A);
          const bo = minOrderOfGroup(B);

          // null (tak bernomor) dianggap di belakang
          if (ao !== bo) {
            if (ao === null) return 1;
            if (bo === null) return -1;
            return ao - bo;
          }

          const ap = maxRoundPrio(A);
          const bp = maxRoundPrio(B);
          if (ap !== bp) return bp - ap;

          return a.localeCompare(b);
        });

      // 4) Build SATU tabel global untuk arena ini
      let html = `
        <div class="mb-5">
          <table class="table table-dark mt-2">
            <thead>
              <tr class="table-sub-header" style="height:60px;">
                <th style="width:90px;">Match</th>
                <th>Kontingen</th>
                <th colspan="3">Peserta</th>
                <th style="width:120px;">Score</th>
                <th style="width:110px;">Medali</th>
                <th style="width:140px;">Action</th>
              </tr>
            </thead>
            <tbody>
      `;

      groupKeys.forEach((gk) => {
        const items = groupsMap[gk].slice();
        const isBattle = gk.startsWith('B|');

        // Fallback corner untuk battle berpasangan (2 baris)
        if (isBattle && items.length === 2) {
          items.forEach((it, idx) => {
            const c = (it.row.corner || '').toLowerCase();
            if (c !== 'blue' && c !== 'red') it.row.corner = (idx === 0) ? 'blue' : 'red';
          });
        }

        // Urutkan isi grup: BLUE → RED → lainnya, lalu by match_order → id
        items.sort((A, B) => {
          const rank = (v) => {
            const s = (v || '').toLowerCase();
            if (s === 'blue') return 0;
            if (s === 'red')  return 1;
            return 2;
          };
          const ra = rank(A.row.corner), rb = rank(B.row.corner);
          if (ra !== rb) return ra - rb;

          const oa = parseInt(A.row.match_order, 10);
          const ob = parseInt(B.row.match_order, 10);
          if (Number.isFinite(oa) && Number.isFinite(ob) && oa !== ob) return oa - ob;

          return (A.row.id || 0) - (B.row.id || 0);
        });

        // Separator: tampilkan Match#, badges, + tombol Rank (non-battle: 1x per pool)
        const sepOrder = (function(){ 
          const v = minOrderOfGroup(items); 
          return (v === null ? NaN : v);
        })();
        const head = items[0] || {};
        const r0   = head.row || {};
        const meta = head.meta || {};
        const roundLabel = r0.round_label || '';

        const bCat   = meta.cat  ? ` <span class="match-pill">${escAttr(meta.cat)}</span>`   : '';
        const bAge   = meta.age  ? ` <span class="match-pill">${escAttr(meta.age)}</span>`   : '';
        const bPool  = meta.pool ? ` <span class="match-pill">${escAttr(meta.pool)}</span>`  : '';
        const bRound = roundLabel ? ` <span class="match-pill text-uppercase">${escAttr(roundLabel)}</span>` : '';

        // tombol rank per pool (hanya non-battle)
        const poolKey = `${meta.cat}||${meta.age}||${meta.pool}`;
        let rankBtn = '';
        if (!isBattle && !rankBtnPrintedPools.has(poolKey)) {
          const poolMatches = poolRankMap[poolKey] || [];
          rankBtn = `
            <button class="btn btn-sm btn-outline-info btn-view-rank ms-2"
                    data-category="${escAttr(meta.cat || '')}"
                    data-age="${escAttr(meta.age || '')}"
                    data-pool="${escAttr(meta.pool || '')}"
                    data-matches="${escAttr(JSON.stringify(poolMatches))}">
              Lihat Peringkat
            </button>`;
          rankBtnPrintedPools.add(poolKey);
        }

        html += `
          <tr class="battle-group-sep">
            <td colspan="8">
              <span class="match-pill">Match ${Number.isFinite(sepOrder) ? sepOrder : '-'}</span>
              ${bCat}${bRound}${bAge}${bPool}
              ${isBattle ? ` <span class="match-pill">BATTLE</span>` : ''}
              ${rankBtn}
            </td>
          </tr>
        `;

        // Render baris isi grup
        items.forEach(({row: match}) => {
          let rowClass = '';
          if (isBattle) {
            const c = (match.corner || '').toLowerCase();
            if (c === 'blue') rowClass = 'battle-blue';
            else if (c === 'red') rowClass = 'battle-red';
          }

          html += `<tr class="${rowClass}">`;

          // Kolom "Match" dikosongkan (sudah di separator)
          html += `<td></td>`;

          // Kontingen
          const contingentName = match.contingent?.name || match.contingent_name || '-';
          html += `<td>${escAttr(contingentName)}</td>`;

          // Peserta 1-3
          const tm1 = match.team_member1?.name || match.participant_1 || '-';
          const tm2 = match.team_member2?.name || match.participant_2 || '-';
          const tm3 = match.team_member3?.name || match.participant_3 || '-';

          if (match.match_type === 'seni_tunggal' || match.match_type === 'solo_kreatif') {
            html += `<td>${escAttr(tm1)}</td><td colspan="2">-</td>`;
          } else if (match.match_type === 'seni_ganda') {
            html += `<td>${escAttr(tm1)}</td><td>${escAttr(tm2)}</td><td>-</td>`;
          } else if (match.match_type === 'seni_regu') {
            html += `<td>${escAttr(tm1)}</td><td>${escAttr(tm2)}</td><td>${escAttr(tm3)}</td>`;
          } else {
            html += `<td>-</td><td>-</td><td>-</td>`;
          }

          // Score
          const scoreValue = parseFloat(match.final_score);
          const scoreText = match.status === 'finished' && !isNaN(scoreValue)
            ? scoreValue.toFixed(6)
            : '-';
          html += `<td>${scoreText}</td>`;

          // Medali
          html += `<td>${match.status === 'finished' ? (match.medal || '-') : '-'}</td>`;

          // Action
          if (match.status === 'finished') {
            html += `
              <td>
                <button class="btn btn-sm btn-outline-warning btn-recap-match" data-id="${match.id}">
                  Recap
                </button>
              </td>
            `;
          } else {
            html += `
              <td>
                <button 
                  class="btn btn-sm btn-outline-light btn-set-score"
                  data-id="${match.id}"
                  data-name="${escAttr(contingentName)}"
                  data-participant="${escAttr(tm1)}"
                  data-score="${isNaN(scoreValue) ? '' : scoreValue}">
                  Input Skor
                </button>
              </td>
            `;
          }

          html += `</tr>`;
        });
      });

      html += `
            </tbody>
          </table>
        </div>
      `;

      arenaSection += html + `</div>`;
      $('#match-tables').append(arenaSection);
    });

    $(".loader-bar").hide();
  });
}


$('#apply-filter').on('click', function () {
  loadSeniMatchesAdmin();
});

$(document).on('click', '#btn-export-seni', function (e) {
  e.preventDefault();

  const base = $(this).data('base-href') || $(this).attr('href') || '/export/local-seni-matches';

  const arena = ($('#filter-arena').val() || '').trim();
  const from  = ($('#filter-from').val()   || '').trim();
  const to    = ($('#filter-to').val()     || '').trim();

  const qs = new URLSearchParams();
  if (arena) qs.set('arena_name', arena);
  if (from)  qs.set('from_partai', from);
  if (to)    qs.set('to_partai', to);

  const url = qs.toString() ? `${base}?${qs.toString()}` : base;
  window.open(url, '_blank');
});





    
    
    

    

    
    
    
    
    
    
    
    
    
    

    
    
    
    
    
    
    
    
    
    
    
    
    
    

});

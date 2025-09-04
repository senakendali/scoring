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
   
    window.Echo.channel(`match-start.${tournamentSlug}.${arenaSlug}`)
    .listen('.MatchStarted', (e) => {
        const role = $("#session-role").val().toLowerCase(); // 🔥 ini penting bro
        const arena = $("#session-arena").val();
        const tournament = $("#session-tournament").val();

        if (e.arena_name === arena && e.tournament_name === tournament) {
            let route = "/matches";

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

    

    loadMatches();


    

    // ✅ Tampilkan Modal
    $(document).on('click', '.set-winner-btn', function () {
        const matchId = $(this).data('id');
        const blueName = $(this).data('blue');
        const redName = $(this).data('red');

        $('#match-id-for-winner').val(matchId);
        $('#manual-win-blue').text(`🏅 ${blueName || 'Biru'} Menang`);
        $('#manual-win-red').text(`🏅 ${redName || 'Merah'} Menang`);

        $('#manualWinnerModal').modal('show');
    });

    $('#manual-win-blue, #manual-win-red').on('click', function () {
        const matchId = $('#match-id-for-winner').val();
        const winnerCorner = $(this).attr('id') === 'manual-win-blue' ? 'blue' : 'red';

        $.ajax({
            url: url + `/api/local-matches/${matchId}/set-winner-manual`,
            method: 'POST',
            data: {
                corner: winnerCorner,
                _token: $('meta[name="csrf-token"]').attr('content') // pastikan ada di head
            },
            success: function (res) {
                $('#manualWinnerModal').modal('hide');
                alert(res.message || 'Pemenang berhasil diatur');
               loadMatches();
            },
            error: function (xhr) {
                alert('Gagal mengatur pemenang');
            }
        });
    });

    function loadMatches() {
  // Baca filter
  const selectedArena = ($('#filter-arena').val() || '').trim();
  const parseIntSafe = (v) => {
    const n = parseInt(String(v || '').trim(), 10);
    return Number.isFinite(n) ? n : null;
  };
  let fromPartai = parseIntSafe($('#filter-from').val());
  let toPartai   = parseIntSafe($('#filter-to').val());

  if (fromPartai !== null && toPartai !== null && fromPartai > toPartai) {
    const t = fromPartai; fromPartai = toPartai; toPartai = t;
  }
  const hasFrom = fromPartai !== null;
  const hasTo   = toPartai   !== null;

  const inRange = (num) => {
    if (!Number.isFinite(num)) {
      // Saat filter aktif, yang tak bernomor disembunyikan
      return !(hasFrom || hasTo);
    }
    if (hasFrom && num < fromPartai) return false;
    if (hasTo   && num > toPartai)   return false;
    return true;
  };

  $(".loader-bar").show();

  $.get(url + "/api/local-matches/admin", function (data) {
    $('#match-tables').empty();

    // --- Build daftar arena dari data + isi dropdown (sekali per load) ---
    const arenas = Object.keys(data || {});
    const current = selectedArena; // simpan agar tetap kepilih
    let optHtml = `<option value="">Semua Arena</option>`;
    arenas.forEach(a => {
      const safe = String(a || '');
      optHtml += `<option value="${safe.replace(/"/g,'&quot;')}">${safe}</option>`;
    });
    // Set hanya kalau opsi berubah/pertama kali (idempotent—aman dipanggil berulang)
    $('#filter-arena').html(optHtml);
    if (current) $('#filter-arena').val(current);

    // --- Render per arena (atau filter satu arena) ---
    arenas.forEach((arenaName) => {
      if (current && arenaName !== current) return; // skip arena lain kalau diseleksi

      const pools = data[arenaName] || {};
      let matches = [];

      // Gabung semua match dari setiap pool di arena ini
      $.each(pools, function (poolName, poolMatches) {
        if (Array.isArray(poolMatches)) matches = matches.concat(poolMatches);
      });

      if (!matches.length) return;

      // Casting & filter by partai
      matches = matches
        .map(m => {
          const num = Number.isFinite(parseInt(m.match_number, 10))
            ? parseInt(m.match_number, 10)
            : null;
          return { ...m, _match_no: num };
        })
        .filter(m => inRange(m._match_no));

      if (!matches.length) return;

      // Urutkan berdasarkan _match_no (null di belakang), lalu id
      matches.sort((a, b) => {
        const an = a._match_no, bn = b._match_no;
        if (an !== bn) {
          if (an === null) return 1;
          if (bn === null) return -1;
          return an - bn;
        }
        return (a.id || 0) - (b.id || 0);
      });

      // Render tabel
      let arenaSection = `<div class="mb-5">
        <h4 class="text-white mb-3">${String(arenaName || '').toUpperCase()}</h4>
        <table class="table table-dark">
          <thead>
            <tr>
              <th>No Partai</th>
              <th>Babak</th>
              <th>Kelas</th>
              <th colspan="2" class="text-center">Peserta</th>
              <th>Pemenang</th>
              <th>Keterangan</th>
              <th class="text-nowrap">Action</th>
            </tr>
            <tr>
              <th></th>
              <th></th>
              <th></th>
              <th class="text-center text-primary">Sudut Biru</th>
              <th class="text-center text-danger">Sudut Merah</th>
              <th></th>
              <th></th>
              <th></th>
            </tr>
          </thead>
          <tbody>`;

      $.each(matches, function (index, match) {
        const blueName = (match.round_level === 1 && match.blue_name === 'TBD') ? 'BYE' : (match.blue_name || 'TBD');
        const redName  = (match.round_level === 1 && match.red_name  === 'TBD') ? 'BYE' : (match.red_name  || 'TBD');

        arenaSection += `
          <tr>
            <td>${match._match_no ?? ''}</td>
            <td>${match.round_label ?? ''}</td>
            <td>${match.class_name ?? ''}</td>
            <td class="text-primary fw-bold">
              ${blueName}<br>
              <small>${match.blue_contingent || '-'}</small><br>
              <small class="text-info">Score: ${match.participant_1_score ?? '-'}</small>
            </td>
            <td class="text-danger fw-bold">
              ${redName}<br>
              <small>${match.red_contingent || '-'}</small><br>
              <small class="text-info">Score: ${match.participant_2_score ?? '-'}</small>
            </td>
            <td>
              ${
                match.winner_name
                  ? `<div class="btn btn-success"><i class="bi bi-trophy"></i> ${match.winner_name}</div>`
                  : `<div>-</div>`
              }
            </td>
            <td>${match.status ?? ''}</td>
            <td class="text-nowrap">
              <div class="d-flex gap-1">
                ${
                  match.status !== 'finished' ? `
                    <button class="btn btn-outline-info btn-sm set-winner-btn"
                      data-id="${match.id}"
                      data-blue="${(match.blue_name || '').replace(/"/g,'&quot;')}"
                      data-red="${(match.red_name  || '').replace(/"/g,'&quot;')}">
                      Set Winner
                    </button>
                  ` : ''
                }
                ${
                  match.status === 'finished' && match.winner_name ? `
                    <a href="/matches/${match.id}/recap" class="btn btn-outline-warning btn-sm btn-recap-match">Rekap</a>
                  ` : ''
                }
              </div>
            </td>
          </tr>`;
      });

      arenaSection += `</tbody></table></div>`;
      $('#match-tables').append(arenaSection);
    });

    $(".loader-bar").hide();
  });
}

// Handler tombol "Terapkan"
$(document).on('click', '#apply-filter', function () {
  loadMatches();
});

// Opsional: enter di input from/to langsung apply
$(document).on('keypress', '#filter-from, #filter-to', function (e) {
  if (e.which === 13) {
    $('#apply-filter').click();
  }
});

$(document).on('click', '#export-tanding-pdf', function (e) {
  e.preventDefault();
  const base = $(this).data('export-url');

  const arena = ($('#filter-arena').val() || '').trim();
  const from  = ($('#filter-from').val()  || '').trim();
  const to    = ($('#filter-to').val()    || '').trim();

  const qs = new URLSearchParams();
  if (arena) qs.set('arena_name', arena);
  if (from)  qs.set('from', from);
  if (to)    qs.set('to', to);

  const urlFinal = qs.toString() ? `${base}?${qs.toString()}` : base;
  window.open(urlFinal, '_blank');
});





    // setelah render selesai
    $('#show-winners-btn').off('click').on('click', function () {
        let sorted = allWinners.sort((a, b) => b.score - a.score);

        $('#winner-list').empty();
        sorted.forEach((w, i) => {
            $('#winner-list').append(`
                <tr>
                    <td>${w.match_number}</td>
                    <td>${w.winner_name}</td>
                    <td>${w.contingent || '-'}</td>
                    <td><span class="badge bg-success">${w.score}</span></td>
                    <td>${w.arena}</td>
                </tr>
            `);
        });

        new bootstrap.Modal(document.getElementById('winnerModal')).show();
    });



    
    
    

    // Event klik tombol Masuk
    $(document).on("click", ".btn-enter-match", function (e) {
        e.preventDefault();

        const $btn = $(this);
        const originalHtml = $btn.html();
        
        // Tampilkan loader
        $btn.prop("disabled", true).html(`<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Loading...`);

        const matchId = $btn.data("id");
        const arena = $btn.data("arena");
        const tournament = $btn.data("tournament");

        fetch(url + "/api/matches/start", {
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
        .then(res => {
            // Kembalikan tombol ke semula setelah request selesai
            $btn.html(originalHtml).prop("disabled", false);

            // Tambahkan logic redirect atau update UI di sini
            console.log("Match started");
        })
        .catch(error => {
            console.error("Error:", error);
            $btn.html(originalHtml).prop("disabled", false);
            alert("Gagal memulai pertandingan!");
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
    
    
    

    
    
    
    
    
    
    
    
    
    

    
    
    
    
    
    
    
    
    
    
    
    
    
    

});

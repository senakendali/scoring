$(document).ready(function () {
    const url = APP.baseUrl;
    let matchId = parseInt($("#match-id").val());

    console.log("🟢 Arena JS Ready, Match ID:", matchId);

    let roundId = null;
    let countdownInterval = null;

    const arena = $("#session-arena").val();
    const tournament = $("#session-tournament").val();

    const tournamentSlug = slugify(tournament);
    const arenaSlug = slugify(arena);

    let currentArena = null;
    let currentCorner = null;

    

    

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    fetchMatchData();
    fetchJuriCount(tournament, arena);
    //pollSeniJudgeScores(matchId, tournament, arena);
    initSeniJudgeRealtime(matchId, tournament, arena);


    const host = window.location.hostname;
    const pusher = new Pusher('reverb', {
        wsHost: host,
        wsPort: 6001,
        forceTLS: false,
        encrypted: false,
        enabledTransports: ['ws'],
        disableStats: true,
    });

    const channel = pusher.subscribe(`match.${matchId}`);
    //const globalChannel = pusher.subscribe('global.seni.match');
    //const globalChannel = pusher.subscribe(`arena.seni.match.${matchId}`);
    const slugArena = $("#session-arena").val()?.toLowerCase().replace(/\s+/g, '-');
    const globalChannel = pusher.subscribe(`arena.seni.match.${slugArena}`);

    //showModalById('winnerModal');
    
    //alert(slugArena);

    // ✅ Global Match Change
    globalChannel.bind('seni.match.changed', function (data) {
        console.log("🎯 Match changed:", data);
        window.location.href = url + `/matches/seni/display-arena/${data.new_match_id}`; // Sesuaikan path kalau perlu
    });

    /*globalChannel.bind('seni.group.completed', function (data) {
        alert("Group selesai!");
        console.log("🏁 Group completed:", data);
        if (data?.result_url) {
            window.location.href = data.result_url;
        }
    });*/

    // ---- TEMPLATE skeleton untuk modal result (header + 2 tabel) ----
const GROUP_RESULT_TEMPLATE = `
    <div class="result-match-header">
            <div class="result-match-item">
                <div class="d-flex w-100">
                    <!-- BLUE -->
                    <div class="result-item flex-fill corner-blue-bg p-3">
                      <div id="participant-blue-name" class="fw-bold" style="font-size: 40px;">-</div>
                      <div id="participant-blue-contingent" class="small opacity-75 mt-1" style="font-size: 20px;">-</div>
                    </div>
                    <!-- RED -->
                    <div class="result-item flex-fill corner-red-bg p-3">
                      <div id="participant-red-name" class="fw-bold" style="font-size: 40px;">-</div>
                      <div id="participant-red-contingent" class="small opacity-75 mt-1" style="font-size: 20px;">-</div>
                    </div>
                      
                </div>
            </div>
          </div>

            <div class="d-flex w-100 flex-column flex-md-row gap-3 mt-3 result-tables">
                        <!-- BLUE SIDE -->
                        <div class="flex-fill">
                        <table class="table result table-bordered table-sm mb-0">
                            
                            <tbody>
                            <tr>
                                <td class="w-50 corner-blue-bg text-white roboto-bold text-uppercase">Time</td>
                                <td id="blue-performance" class="text-end roboto-bold text-uppercase">-</td>
                            </tr>
                            <tr>
                                <td class="corner-blue-bg text-white roboto-bold text-uppercase">Penalty</td>
                                <td id="blue-penalty" class="text-end roboto-bold text-uppercase">-</td>
                            </tr>
                            <tr>
                                <td class="corner-blue-bg text-white roboto-bold text-uppercase">Winning Point</td>
                                <td id="blue-winning" class="text-end roboto-bold text-uppercase">-</td>
                            </tr>
                            </tbody>
                        </table>
                        </div>

                        <!-- RED SIDE -->
                        <div class="flex-fill">
                        <table class="table result table-bordered table-sm mb-0">
                            
                            <tbody>
                            <tr>
                                <td class="w-50 corner-red-bg text-white roboto-bold text-uppercase">Time</td>
                                <td id="red-performance" class="text-end roboto-bold text-uppercase">-</td>
                            </tr>
                            <tr>
                                <td class="corner-red-bg text-white roboto-bold text-uppercase">Penalty</td>
                                <td id="red-penalty" class="text-end roboto-bold text-uppercase">-</td>
                            </tr>
                            <tr>
                                <td class="corner-red-bg text-white roboto-bold text-uppercase">Winning Point</td>
                                <td id="red-winning" class="text-end roboto-bold text-uppercase">-</td>
                            </tr>
                            </tbody>
                        </table>
                        </div>
                    </div>
`;

// ---- helper render header participants ----
function setHeaderNames(res) {
  try {
    const blue = res?.participants?.blue ?? {};
    const red  = res?.participants?.red ?? {};

    // isi nama peserta (gabungan array jadi string)
    $('#participant-blue-name').text(blue.participants_joined || '-');
    $('#participant-red-name').text(red.participants_joined || '-');

    

    // isi kontingen
    $('#participant-blue-contingent').text(blue.contingent || '-');
    $('#participant-red-contingent').text(red.contingent || '-');
  } catch (e) {
    console.error('setHeaderNames error:', e, res);
  }
}

function determineWinnerCornerFromRes(res) {
  const blue = res?.participants?.blue || {};
  const red  = res?.participants?.red  || {};



  // 1) Cek winner dari payload per-corner dulu
  //    (kalau backend udah set)
  if (blue.winning_corner === 'blue') return 'blue';
  if (red.winning_corner === 'red')   return 'red';

  // 2) Fallback: hitung lokal
  const bp = Number(blue.winning_point ?? 0); // string "9.900000" -> number
  const rp = Number(red.winning_point ?? 0);

  if (!Number.isNaN(bp) && !Number.isNaN(rp)) {
    if (bp > rp) return 'blue';
    if (rp > bp) return 'red';
  }

  const bpen = Number(blue.penalty ?? 0);
  const rpen = Number(red.penalty ?? 0);
  if (bp === rp) {
    if (bpen < rpen) return 'blue';
    if (rpen < bpen) return 'red';
  }

  const ideal = 180; // 3 menit—ubah kalau aturannya beda
  const bt = Math.abs(Number(blue.performance_time ?? 0) - ideal);
  const rt = Math.abs(Number(red.performance_time ?? 0)  - ideal);
  if (bp === rp && bpen === rpen) {
    if (bt < rt) return 'blue';
    if (rt < bt) return 'red';
  }

  return null; // draw / butuh keputusan manual
}


function applyWinner(res) {
  const corner = determineWinnerCornerFromRes(res);
  console.log("🎯 Winner corner:", corner);

  // Ambil elemen kolom biru & merah
  const $blueCol = $('.result-tables .flex-fill').eq(0);
  const $redCol  = $('.result-tables .flex-fill').eq(1);

  // Sel value
  const $blueVals = $blueCol.find('#blue-performance, #blue-penalty, #blue-winning');
  const $redVals  = $redCol.find('#red-performance, #red-penalty, #red-winning');

  // Sel label (td kiri tiap baris)
  const $blueLabels = $blueCol.find('tbody tr td:first-child');
  const $redLabels  = $redCol.find('tbody tr td:first-child');

  // Bersihkan semua kelas warna
  $blueVals.add($blueLabels).removeClass('corner-blue-bg corner-red-bg text-white loser-dim');
  $redVals.add($redLabels).removeClass('corner-blue-bg corner-red-bg text-white loser-dim');

  // Helper untuk set pemenang
  function setWinner($labels, $vals, color) {
    const bg = color === 'blue' ? 'corner-blue-bg' : 'corner-red-bg';
    $labels.addClass(bg + ' text-white');
    $vals.addClass(bg + ' text-white');
  }

  // Helper untuk set kalah
  function setLoser($labels, $vals) {
    $labels.addClass('loser-dim');
    $vals.addClass('loser-dim');
  }

  if (corner === 'blue') {
    setWinner($blueLabels, $blueVals, 'blue');
    setLoser($redLabels, $redVals);
  } else if (corner === 'red') {
    setWinner($redLabels, $redVals, 'red');
    setLoser($blueLabels, $blueVals);
  } else {
    // draw → dua-duanya redup
    setLoser($blueLabels, $blueVals);
    setLoser($redLabels, $redVals);
  }
}








// ---- helper format detik -> mm:ss ----
function fmtTime(s) {
  s = +s || 0;
  const m = String(Math.floor(s/60)).padStart(2,'0');
  const sec = String(s%60).padStart(2,'0');
  return `${m}:${sec}`;
}

// ---- fungsi utama: render skeleton + fetch data ----
function loadGroupResults(group) {
  if (group === null || group === undefined || group === '') {
    console.warn('⚠️ loadGroupResults: group kosong, batal fetch.');
    return;
  }

  const base = (typeof APP !== 'undefined' && APP.baseUrl) ? APP.baseUrl : '';
  const reqUrl = `${base}/api/local-matches/seni/battle-group/${encodeURIComponent(group)}`;
  const tournament = $("#session-tournament").val();
  const arena      = $("#session-arena").val();

  console.log('🔄 Fetch battle result:', { group, reqUrl, tournament, arena });

  // Tampilkan modal & langsung render skeleton (bukan loader plain text)
  const modalEl = document.getElementById('groupResultModal');
  if (!modalEl) {
    console.error('❌ #groupResultModal tidak ditemukan.');
    return;
  }
  const modal = new bootstrap.Modal(modalEl);
  modal.show();

  // Render skeleton supaya element #blue-*, #red-* sudah ada
  $('#groupResultBody').html(GROUP_RESULT_TEMPLATE);

  // Baru fetch data dan isi nilai
  $.get(reqUrl, { tournament, arena })
    .done(function (res) {
      console.log('✅ Battle result loaded:', res);
        
      setHeaderNames(res);
      applyWinner(res);

      const blue = res?.participants?.blue ?? {};
      const red  = res?.participants?.red ?? {};

      $('#blue-performance').text(blue.performance_time != null ? fmtTime(blue.performance_time) : '-');
      $('#blue-penalty').text(blue.penalty ?? '-');
      $('#blue-winning').text(
        blue.winning_point != null ? Number(blue.winning_point).toFixed(6) : '-'
      );

      $('#red-performance').text(red.performance_time != null ? fmtTime(red.performance_time) : '-');
      $('#red-penalty').text(red.penalty ?? '-');
      $('#red-winning').text(
        red.winning_point != null ? Number(red.winning_point).toFixed(6) : '-'
      );
    })
    .fail(function (xhr, status, err) {
      console.error('❌ Gagal load battle result:', { reqUrl, status, err, response: xhr?.responseText });
      $('#groupResultBody').html(
        `<div class="text-center py-5 text-danger">
           Gagal memuat hasil group.<br>
           <small>${xhr?.status || ''} ${xhr?.statusText || ''}</small>
         </div>`
      );
    });
}

// ==== jalan saat halaman direload (ada ?battle_group=...) ====
(function checkUrlBattleGroupOnLoad() {
  try {
    const u = new URL(window.location.href);
    const battleGroup = u.searchParams.get('battle_group');
    if (battleGroup) {
      console.log('🔗 battle_group terdeteksi di URL:', battleGroup);
      loadGroupResults(battleGroup);
    }
  } catch (e) {
    console.warn('URL parse error (on load):', e);
  }
})();

// ==== jalan saat broadcast ====
globalChannel.bind('seni.group.completed', function (data) {
  console.log('🏁 Group completed (event payload):', data);

  const group = data?.battle_group ?? data?.battle_group_id ?? data?.group;
  if (!group && group !== 0) {
    console.warn('⚠️ Event tidak membawa battle_group. Keys:', Object.keys(data || {}));
    return;
  }

  // Update URL tanpa reload
  try {
    const current = new URL(window.location.href);
    current.searchParams.set('battle_group', group);
    window.history.replaceState({ battle_group: group }, '', current.toString());
    console.log('🔗 URL updated with ?battle_group=', group);
  } catch (e) {
    console.warn('replaceState error:', e);
  }

  // Load & render
  loadGroupResults(group);
});

function showModalById(id) {
  const el = document.getElementById(id);
  if (!el) return null;
  const inst = bootstrap.Modal.getOrCreateInstance(el, { backdrop: 'static', keyboard: false });
  inst.show();
  return inst;
}
function hideModalById(id) {
  const el = document.getElementById(id);
  if (!el) return;
  const inst = bootstrap.Modal.getInstance(el);
  if (inst) inst.hide();
}

function renderWinnerModal(data) {
    //alert('Gagal mendiskualifikasi peserta!');
    console.log('🏆 Winner announced:', data);

  // data: { winner_name, contingent, corner('blue'|'red'|null), reason_label, ... }
  const nameEl = document.getElementById('winner-name');
  const contEl = document.getElementById('winner-contingent');
  const badgeEl = document.getElementById('winner-corner-badge');
  const reasonEl = document.getElementById('winner-reason');

  if (!nameEl || !contEl || !badgeEl || !reasonEl) {
    console.warn('Winner modal elements not found');
    return;
  }

  const name = (data?.winner_name || '-').toString();
  const cont = (data?.contingent || '-').toString();
  const corner = (data?.corner || '').toLowerCase();
  const reasonLabel = (data?.reason_label || '').toString();

  

  nameEl.textContent = name;
  contEl.textContent = cont;



  reasonEl.textContent = reasonLabel;
}

globalChannel.bind('seni.battle.winner.announced', function (data) {
  console.log('🏆 Winner announced:', data);

  // 1) Tutup modal hasil group jika lagi terbuka
  hideModalById('groupResultModal');

  // 2) Isi konten winner & buka winner modal
  renderWinnerModal(data);
  showModalById('winnerModal');

  // 3) (opsional) bersihkan query ?battle_group di URL
  try {
    const u = new URL(window.location.href);
    if (u.searchParams.has('battle_group')) {
      u.searchParams.delete('battle_group');
      window.history.replaceState({}, '', u.toString());
    }
  } catch (e) {}
});








    function slugify(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-')      // Ganti spasi jadi -
            .replace(/[^\w\-]+/g, '')  // Hapus karakter non-word
            .replace(/\-\-+/g, '-')    // Ganti -- jadi -
            .replace(/^-+/, '')        // Hapus - di awal
            .replace(/-+$/, '');       // Hapus - di akhir
    }

   
    console.log("🎯 Channel yang dipakai:", `seni-timer.${tournamentSlug}.${arenaSlug}`);

   window.Echo.channel(`seni-timer.${tournamentSlug}.${arenaSlug}`)
    .listen('.SeniTimerStarted', (e) => {
        console.log("🔥 Timer started raw:", e);
        console.log("⏱️ e.start_time:", e.start_time);

        const startTimestamp = new Date(e.start_time).getTime();

        if (isNaN(startTimestamp)) {
            console.error("❌ Invalid start_time:", e.start_time);
            return;
        }

        startCountUp(startTimestamp, e.duration || 600);
    });

    
    window.Echo.channel(`seni-timer.${tournamentSlug}.${arenaSlug}`)
        .listen('.SeniTimerUpdated', (data) => {
            console.log("🕒 Event SeniTimerUpdated diterima:", data);

             // ✅ Stop interval dulu apapun statusnya
            clearInterval(countdownInterval);

            if (data.status === 'ongoing') {
                clearInterval(countdownInterval); // biar gak dobel interval
                $(".wrong-move").prop("disabled", false);

                const startTimestamp = Date.parse(data.start_time);
                if (!isNaN(startTimestamp)) {
                    console.log("▶️ Melanjutkan timer dari:", data.start_time);
                    startCountUp(startTimestamp, data.duration || 600);
                } else {
                    console.warn("❌ start_time tidak valid saat resume:", data.start_time);
                }
            }

            else if (data.status === 'paused') {
                clearInterval(countdownInterval);
                $(".wrong-move").prop("disabled", true);
                console.log("⏸️ Timer dipause");
            }

            else if (data.status === 'finished') {
                clearInterval(countdownInterval);
                $(".wrong-move").prop("disabled", true);
                $("#timer").text("SELESAI");
                console.log("🏁 Timer selesai");
            }

            else if (data.status === 'not_started') {
                clearInterval(countdownInterval);
                $(".wrong-move").prop("disabled", true);
                $("#timer").text("00:00");

                const category = $("#gender").text().toLowerCase(); // atau pakai data lain yang lebih spesifik
                const baseScore = (category.includes('tunggal') || category.includes('regu')) ? 9.90 : 9.10;

                totalDeduction = 0;
                currentScore = baseScore;

                $("#starting-score").text(baseScore.toFixed(2));
                $("#deduction").text("-0.00");

                console.log("🔁 Timer direset dengan base score", baseScore);
            }

        });



     window.Echo.channel(`seni-timer.${tournamentSlug}.${arenaSlug}`)
        .listen('.SeniTimerFinished', function (data) {
            console.log("🏁 Match selesai:", data);
            clearInterval(countdownInterval); // ✅ ⬅️ tambahkan di sini juga

            $(".wrong-move").prop("disabled", true);

            let modalInstance = null;

            if (data.status === 'finished' && data.disqualified === true) {
                const disqualifiedModalEl = document.getElementById('disqualifiedModal');
                if (disqualifiedModalEl) {
                    modalInstance = new bootstrap.Modal(disqualifiedModalEl);
                    modalInstance.show();
                }
            } else {
                const finishedModalEl = document.getElementById('finishedModal');
                if (finishedModalEl) {
                    modalInstance = new bootstrap.Modal(finishedModalEl);
                    modalInstance.show();
                }
            }

            // ✅ Tutup semua modal setelah 2 detik
            if (modalInstance) {
                setTimeout(() => {
                    modalInstance.hide();
                }, 2000);
            }
        });


    // ✅ Countdown Timer Handler
    function startCountUp(startTime, maxDuration = 600) {
        clearInterval(countdownInterval);

        countdownInterval = setInterval(() => {
            const now = Date.now();
            const elapsed = Math.floor((now - startTime) / 1000);

            if (elapsed >= maxDuration) {
                clearInterval(countdownInterval);
                $("#timer").text(formatTime(maxDuration));
            } else {
                $("#timer").text(formatTime(elapsed));
            }

            console.log("⏱️ Updating:", formatTime(elapsed));
            console.log("⛳ Element found:", $("#timer").length);
        }, 1000);
    }


    // ✅ Format Time Helper
    function formatTime(seconds) {
        if (isNaN(seconds) || seconds < 0) seconds = 0;
        const min = Math.floor(seconds / 60).toString().padStart(2, '0');
        const sec = (seconds % 60).toString().padStart(2, '0');
        return `${min}:${sec}`;
    }

    function applyCornerBackground(corner) {
        $("#tournament-name").css('background', 'linear-gradient(to bottom, #2a2a2a, #000000)');
       $(".seni-participant-detail").css('border-bottom', 'none');
       $(".seni-participant-detail").css('height', '70px');
        const classes = ['corner-blue', 'corner-red', 'corner-none'];
        const c = (corner || '').toString().toLowerCase();
        const picked = c === 'blue' ? 'corner-blue' : c === 'red' ? 'corner-red' : 'corner-none';
        $('body').removeClass(classes.join(' ')).addClass(picked);
    }


    function fetchMatchData() {
        $(".loader-bar").show();
         $.get(`${url}/api/local-matches/seni/${matchId}`, function (data) {

            const category = data.category?.toLowerCase(); // contoh: "Tunggal", "Regu", "Ganda"

            const baseScore = ['tunggal', 'regu'].includes(category) ? 9.90 : 9.10;

            if(data.is_display_timer != 0){
                $("#timer").show();
               
            }else{
                /* perbaiki ini */
                $("#timer-container").remove();
                $("#timer").hide();

            }

            // Set UI
            $("#starting-score").text(baseScore.toFixed(2));
            $("#seni_base_score").val(baseScore.toFixed(2));
            startingScore = baseScore;

            currentArena = data.arena_name;

            $("#tournament-name").text(data.tournament_name).css({
                    'font-size': '23px',
                    'font-weight': 'bold',
                    'width': '774px'
                });

          
            $("#match-code").text(data.arena_name + " Partai " + data.match_order);
            $("#class-name").text(data.category);
            $("#age-category").text(data.age_category);
            $("#gender").text(data.category + "  " + (data.gender === 'male' ? 'PUTRA' : 'PUTRI'));

            $("#contingent-name").text(data.contingent).css({
                    'font-size': '23px',
                    'font-weight': 'bold'
                });

            $(".detail-item").css({
                    'font-size': '23px',
                    'font-weight': 'bold',
                    'height': '70px',
                    'width':'250px'
                });



            // 🔥 Reset semua dulu
            $("#participant-1").text('-').hide();
            $("#participant-2").text('-').hide();
            $("#participant-3").text('-').hide();

            // ✅ Tampilkan peserta sesuai match_type
            if (data.match_type === 'seni_tunggal' || data.match_type === 'solo_kreatif') {
                $("#participant-1").text(data.team_members[0] || '-').show().css({
                    'font-size': '23px',
                    'font-weight': 'bold'
                });
            } else if (data.match_type === 'seni_ganda') {
                $("#participant-1").text(data.team_members[0] || '-').show() .css({
                    'font-size': '23px',
                    'font-weight': 'bold'
                });
                $("#participant-2").text(data.team_members[1] || '-').show() .css({
                    'font-size': '23px',
                    'font-weight': 'bold'
                });
            } else if (data.match_type === 'seni_regu') {
                $("#participant-1").text(data.team_members[0] || '-').show() .css({
                    'font-size': '23px',
                    'font-weight': 'bold'
                });
                $("#participant-2").text(data.team_members[1] || '-').show() .css({
                    'font-size': '23px',
                    'font-weight': 'bold'
                });
                $("#participant-3").text(data.team_members[2] || '-').show() .css({
                    'font-size': '23px',
                    'font-weight': 'bold'
                });
            }

            pollSeniJudgeScores(matchId, tournament, arena);

            currentCorner = (data.corner || '').toString().toLowerCase();
            applyCornerBackground(data.corner);
            $(".loader-bar").hide();
        });
    }

    // helper: buang zero-width chars biar gak “kosong tapi ada”
    const stripZW = s => (typeof s === 'string'
    ? s.replace(/[\u200B-\u200D\u2060]/g, '').trim()
    : s);

    // helper: ambil kontingen (dukung 2 bentuk response)
    function getContingent(match){
        // v1: {contingent: {name: "..."}}  | v2: {contingent_name: "..."} | fallback: {contingent: "..."}
        return stripZW(
            match?.contingent?.name ??
            match?.contingent_name ??
            match?.contingent ??
            '-'
        ) || '-';
    }

    // helper: ambil daftar peserta (dukung 2 bentuk response)
    function getParticipants(match){
        // v1: team_member1.name / team_member2.name / team_member3.name
        const v1 = [
            stripZW(match?.team_member1?.name || ''),
            stripZW(match?.team_member2?.name || ''),
            stripZW(match?.team_member3?.name || ''),
        ].filter(Boolean);

        if (v1.length) return v1;

        // v2: participant_1 / participant_2 / participant_3 (string)
        const v2 = [
            stripZW(match?.participant_1 || ''),
            stripZW(match?.participant_2 || ''),
            stripZW(match?.participant_3 || ''),
        ].filter(Boolean);

        // v3 (fallback): participant_name (dipisah koma / pipe)
        if (!v2.length && match?.participant_name) {
            const parts = String(match.participant_name)
            .split(/[,|]/).map(x => stripZW(x)).filter(Boolean);
            if (parts.length) return parts;
        }

        return [];
    }

    $("#match-code").on("click", function () {
        const matchList = $("#match-list");
        matchList.empty();

        $.get(`${url}/api/local-matches/seni`, function (data) {
            const arenaMatches = [];

            // flatten sesuai struktur response lu
            (data || []).forEach(categoryGroup => {
            (categoryGroup.age_categories || []).forEach(ageGroup => {
                (ageGroup.pools || []).forEach(pool => {
                (pool.matches || []).forEach(m => arenaMatches.push(m));
                });
            });
            });

            arenaMatches.sort((a, b) => (a.match_order ?? 0) - (b.match_order ?? 0));

            arenaMatches.forEach(match => {
            const contingent  = getContingent(match);
            const participantsArr = getParticipants(match);
            const participants = participantsArr.join(', ');
            const corner = (match.corner || '').toString().trim();

            let label = `PARTAI ${match.match_order}`;
            // tambah kontingen & peserta kalau ada
            if (contingent !== '-' || participants) {
                label += ` — ${contingent}`;
                if (participants) label += ` (${participants})`;
            }
            // tambah corner kalau ada
            if (corner) {
                label += ` [${corner.toUpperCase()}]`;
            }

            const li = $(`
                <li class="list-group-item list-group-item-action bg-dark text-white"
                    style="cursor:pointer;" data-id="${match.id}">
                ${label}
                </li>
            `);

            li.on("click", function () {
                const selectedId = $(this).data("id");
                $("#matchListModal").modal("hide");
                window.location.href = url + `/matches/seni/display-arena/${selectedId}`;
            });
            

            matchList.append(li);
            });

            $("#matchListModal").modal("show");
        });
    });

    function renderSeniJudges(juriCount, baseScore = 9.90) {
        const $container = $("#judges-preview");
        $container.empty();

        for (let i = 1; i <= juriCount; i++) {
            const judgeHtml = `
                <div class="flex-fill judge-score-detail">
                    <div class="judge-title roboto-bold" style="font-size: 30px;">J${i}</div>
                    <div class="judge-score roboto-bold" id="judge-score-${i}" style="font-size: 30px;">${baseScore.toFixed(2)}</div>
                </div>
            `;
            $container.append(judgeHtml);

             
        }
    }

    function fetchJuriCount(tournament, arena) {
        const matchId = $("#match-id").val();

        $.when(
            $.get(`${url}/api/seni/juri-count?tournament=${encodeURIComponent(tournament)}&arena=${encodeURIComponent(arena)}`),
            $.get(`${url}/api/local-matches/seni/${matchId}`)
        ).done(function (juriRes, matchRes) {
            const juriCount = juriRes[0].count;
            const matchCategory = matchRes[0].category?.toLowerCase();

            const baseScore = ['tunggal', 'regu'].includes(matchCategory) ? 9.90 : 9.10;

            renderSeniJudges(juriCount, baseScore);

            
        }).fail(function () {
            console.error("❌ Gagal fetch juri atau match");
            $(".seni-judges").html('<div class="text-danger">Gagal memuat juri</div>');
        });
    }


    function pollSeniJudgeScores(matchId, tournament, arena) {
        setInterval(() => {
            $.get(`${url}/api/seni/judges-score?tournament=${encodeURIComponent(tournament)}&arena=${encodeURIComponent(arena)}&match_id=${matchId}`, function (data) {
                const judges = data.judges || [];
                const totalPenalty = parseFloat(data.penalty ?? 0);
                const $container = $("#judges-preview");

                // Urutkan berdasarkan skor kecil ke besar
                judges.sort((a, b) => a.score - b.score);
                const scores = [];

                $container.empty();

                judges.forEach(j => {
                    scores.push(j.score);

                    const html = `
                        <div class="flex-fill judge-score-detail">
                            <div class="judge-title fw-bold">J${j.juri_number}</div>
                            <div class="judge-score fw-bold">${j.score.toFixed(2)}</div>
                        </div>
                    `;
                    $container.append(html);
                });

                const $allCards = $container.find(".judge-score-detail");
                let median = 0;

                if (scores.length > 0) {
                    if (scores.length % 2 === 0) {
                        // Genap → rata-rata 2 tengah
                        const mid1 = (scores.length / 2) - 1;
                        const mid2 = (scores.length / 2);
                        $allCards.eq(mid1).addClass("median");
                        $allCards.eq(mid2).addClass("median");
                        median = (scores[mid1] + scores[mid2]) / 2;
                    } else {
                        // Ganjil → ambil tengah
                        const mid = Math.floor(scores.length / 2);
                        $allCards.eq(mid).addClass("median");
                        median = scores[mid];
                    }
                }

                $("#median-score").text(median.toFixed(6));
                $("#penalty").text("-" + totalPenalty.toFixed(2));

                const mean = scores.reduce((a, b) => a + b, 0) / scores.length;
                const variance = scores.reduce((sum, v) => sum + Math.pow(v - mean, 2), 0) / scores.length;
                const stddev = Math.sqrt(variance);
                $("#standar-deviasi").text(stddev.toFixed(6));

                //const totalScore = data.final_score ?? (mean - totalPenalty); <--awal
                const totalScore = data.final_score ?? (median - totalPenalty);

                //$("#total-score").text(median.toFixed(6)); <-- revisi pak deny
                $("#total-score").text(totalScore.toFixed(6));
            });
        }, 1500);
    }

    function initSeniJudgeRealtime(matchId, tournament, arena) {
        const tournamentSlug = slugify(tournament);
        const arenaSlug = slugify(arena);

        window.Echo.channel(`seni-score.${tournamentSlug}.${arenaSlug}`)
            .listen('.SeniScoreUpdated', function (data) {
                console.log("🎯 Arena UI received update:", data);

                const judges = data.judges || [];
                const totalPenalty = parseFloat(data.penalty ?? 0);
                const $container = $("#judges-preview");

                judges.sort((a, b) => a.score - b.score);
                const scores = [];

                $container.empty();

                judges.forEach(j => {
                    scores.push(j.score);
                    const html = `
                        <div class="flex-fill judge-score-detail">
                            <div class="judge-title fw-bold">J${j.juri_number}</div>
                            <div class="judge-score fw-bold">${j.score.toFixed(2)}</div>
                        </div>
                    `;
                    $container.append(html);
                });

                const $allCards = $container.find(".judge-score-detail");
                let median = 0;

                if (scores.length > 0) {
                    if (scores.length % 2 === 0) {
                        const mid1 = (scores.length / 2) - 1;
                        const mid2 = (scores.length / 2);
                        $allCards.eq(mid1).addClass("median");
                        $allCards.eq(mid2).addClass("median");
                        median = (scores[mid1] + scores[mid2]) / 2;
                    } else {
                        const mid = Math.floor(scores.length / 2);
                        $allCards.eq(mid).addClass("median");
                        median = scores[mid];
                    }
                }

                $("#median-score").text(median.toFixed(6));
                $("#penalty").text("-" + totalPenalty.toFixed(2));

                const mean = scores.reduce((a, b) => a + b, 0) / scores.length;
                const variance = scores.reduce((sum, v) => sum + Math.pow(v - mean, 2), 0) / scores.length;
                const stddev = Math.sqrt(variance);

                $("#standar-deviasi").text(stddev.toFixed(6));
                const totalScore = data.final_score ?? (mean - totalPenalty);

                $("#total-score").text(median.toFixed(6));
            });
    }
    
});

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

    $(".navbar").hide();





    

    function loadLiveSeniMatches() {
        $.get(url + "/api/local-matches/seni/live", function (data) {
            $(".loader-bar").show();
            $('#seni-match-tables').empty();

            if ($.isEmptyObject(data)) {
                $('#seni-match-tables').html(`
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
                                        
                                       
                                        <tr class="table-sub-header">
                                            <th class="text-center" style="width:300px; ">
                                            <th>Kontingen</th>
                                            <th colspan="3">Peserta</th>
                                            
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;

                        pool.matches.forEach(match => {
                            tableHtml += `
                                <tr>
                                    <td class="text-warning" style="font-weight: bold; font-size: 40px; text-align: center; ">PARTAI #${match.match_order}</td>
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

                            tableHtml += `</tr>`;
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
                $('#seni-match-tables').append(arenaSection); // sebelumnya lu pakai #match-tables

            });

            $(".loader-bar").hide();
        });
    }

    

   function loadLiveMatchDisplay() {
        $.get(url + "/api/local-matches/live", function (data) {
            $(".loader-bar").show();
            $('#tanding-tables').empty(); // ganti dari #match-tables


            if ($.isEmptyObject(data)) {
                $('#match-tables').html(`
                    <div class="alert alert-warning text-center text-uppercase">
                        Belum ada pertandingan yang berlangsung
                    </div>
                `);
                $(".loader-bar").hide();
                return;
            }

            

            $.each(data, function (arenaName, pools) {
            let matches = [];

            // Gabung semua match dari setiap pool di arena ini
            $.each(pools, function (poolName, poolMatches) {
                matches = matches.concat(poolMatches);
            });

            if (!matches.length) return;

            // Urutkan berdasarkan match_number (atau match_order)
            matches.sort((a, b) => a.match_number - b.match_number);

            let arenaSection = `<div class="mb-5">
                <h4 class="text-warning mb-3 text-center">${arenaName.toUpperCase()}</h4>
                <table class="table table-dark">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:300px; ">No Partai</th>
                            <th style="width:300px; ">Babak</th>
                            <th colspan="2" class="text-center">Peserta</th>
                           
                            ${isOperator || isKetua ? '<th class="text-nowrap">Action</th>' : ''}
                        </tr>
                        <tr>
                            <th></th>
                            <th></th>
                            <th class="text-center text-primary">Sudut Biru</th>
                            <th class="text-center text-danger">Sudut Merah</th>
                            
                            ${isOperator || isKetua ? '<th></th>' : ''}
                        </tr>
                    </thead>
                    <tbody>`;

                    $.each(matches, function (index, match) {
                        arenaSection += `
                            <tr>
                                <td class="text-warning" style="font-weight: bold; font-size: 40px; text-align: center; ">PARTAI #${match.match_number}</td>
                                <td class="text-warning text-uppercase" style="font-weight: bold; font-size: 40px; text-align: left; ">${match.round_label}</td>
                                <td class="text-primary fw-bold text-center">
                                    ${match.round_level === 1 && match.blue_name == 'TBD' ? 'BYE' : match.blue_name || 'TBD'}<br>
                                    <small>${match.blue_contingent || '-'}</small><br>
                                    <small class="text-info">Score: ${match.participant_1_score ?? '-'}</small>
                                </td>
                                <td class="text-danger fw-bold text-center">
                                    ${match.round_level === 1 && match.red_name == 'TBD' ? 'BYE' : match.red_name || 'TBD'}<br>
                                    <small>${match.red_contingent || '-'}</small><br>
                                    <small class="text-info">Score: ${match.participant_2_score ?? '-'}</small>
                                </td>
                                
                                ${isOperator || isKetua ? `
                                    <td class="text-nowrap">
                                        <div class="d-flex gap-1">
                                            <a href="#" class="btn btn-outline-success btn-enter-match"
                                                data-id="${match.id}" data-arena="${match.arena_name}" data-tournament="${match.tournament_name}">
                                                Masuk
                                            </a>
                                            ${match.status === 'finished' && match.winner_name ? `
                                                <a href="/matches/${match.id}/recap" class="btn btn-outline-warning btn-sm btn-recap-match">Rekap</a>
                                            ` : ''}
                                        </div>
                                    </td>` : ''}
                            </tr>`;
                    });

                    arenaSection += `</tbody></table></div>`;
                    $('#tanding-tables').append(arenaSection); // ganti dari #match-tables

                    $(".loader-bar").hide();
                });


            $(".loader-bar").hide();
        });
    }

    // ✅ Jalankan pertama kali
    loadLiveMatchDisplay();
    loadLiveSeniMatches();

    // ✅ Polling setiap 3 detik
   setInterval(() => {
        loadLiveMatchDisplay();
        loadLiveSeniMatches();
    }, 3000);

     function slugify(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-')           // Ganti spasi jadi -
            .replace(/[^\w\-]+/g, '')       // Hapus karakter non-word
            .replace(/\-\-+/g, '-')         // Ganti -- jadi -
            .replace(/^-+/, '')             // Hapus - di awal
            .replace(/-+$/, '');            // Hapus - di akhir
    }
    


    
    
    

    
    
    
    

    

    
    
    
    
    
    
    
    
    
    

    
    
    
    
    
    
    
    
    
    
    
    
    
    

});

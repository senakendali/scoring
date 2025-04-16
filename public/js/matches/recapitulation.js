$(document).ready(function () {
    var url = window.location.origin;
    var matchId = $("#match-id").val();

    console.log("🟢 Recapitulation JS Ready, Match ID:", matchId);  

    fetchMatchData();
    loadRekapitulasi(matchId);


    function fetchMatchData() {
        $(".loader-bar").show();
        $.get(`/api/local-matches/${matchId}`, function (data) {
            $("#tournament-name").text(data.tournament_name);
            $("#match-code").text(data.match_code);
            $("#class-name").text(data.class_name);
            $("#blue-name").text(data.blue.name);
            $("#red-name").text(data.red.name);
            $("#blue-score").text(data.blue.score);
            $("#red-score").text(data.red.score);

            const activeRound = data.rounds.find(r => r.status === 'in_progress') || data.rounds[0];
            roundId = activeRound?.id || null;
           
           
        });
    }

    function loadRekapitulasi(matchId) {
        $.get(`/api/local-matches/${matchId}/recap`, function (data) {
            $('#match-tables').html('');
            data.forEach((round, index) => {
                let html = `
                    <table class="table table-dark mb-5">
                        <colgroup>
                            <col style="width: 10%;">  <!-- Juri -->
                            <col style="width: 22.5%;"> <!-- Nilai Biru -->
                            <col style="width: 12.5%;"> <!-- Total Biru -->
                            <col style="width: 22.5%;"> <!-- Nilai Merah -->
                            <col style="width: 12.5%;"> <!-- Total Merah -->
                        </colgroup>

                        <thead>
                            <tr><th colspan="5" class="table-title">Rekapitulasi Ronde ${round.round_number}</th></tr>
                            <tr>
                                <th>Juri</th>
                                <th class="blue">Nilai</th>
                                <th class="blue">Total</th>
                                <th class="red">Nilai</th>
                                <th class="red">Total</th>
                            </tr>
                        </thead>
                        <tbody>`;
    
                // ⛳ Baris Juri
                for (let i = 1; i <= 3; i++) {
                    const blue = round.judges.find(j => j.judge === `Juri ${i}` && j.corner === 'blue');
                    const red = round.judges.find(j => j.judge === `Juri ${i}` && j.corner === 'red');
    
                    html += `<tr>
                        <td>Juri ${i}</td>
                        <td class="blue">${blue?.points.join(', ') || '-'}</td>
                        <td class="blue">${blue?.total || 0}</td>
                        <td class="red">${red?.points.join(', ') || '-'}</td>
                        <td class="red">${red?.total || 0}</td>
                    </tr>`;
                }
    
                // ⛳ Nilai Sah
                html += `<tr>
                    <td>Nilai Sah</td>
                    <td class="blue">${round.valid_scores.blue.points.join(', ')}</td>
                    <td class="blue">${round.valid_scores.blue.total}</td>
                    <td class="red">${round.valid_scores.red.points.join(', ')}</td>
                    <td class="red">${round.valid_scores.red.total}</td>
                </tr>`;
    
                // ⛳ Jatuhan
                html += `<tr>
                    <td>Jatuhan</td>
                    <td class="blue">${round.jatuhan.blue}</td>
                    <td class="blue">${round.jatuhan.blue}</td>
                    <td class="red">${round.jatuhan.red}</td>
                    <td class="red">${round.jatuhan.red}</td>
                </tr>`;
    
                // ⛳ Hukuman
                html += `<tr>
                    <td>Hukuman</td>
                    <td class="blue">${round.hukuman.blue}</td>
                    <td class="blue">${round.hukuman.blue}</td>
                    <td class="red">${round.hukuman.red}</td>
                     <td class="red">${round.hukuman.red}</td>
                </tr>`;
    
                // ⛳ Nilai Final
                html += `<tr>
                    <td>Nilai Final</td>
                    <td class="blue">${round.final.blue}</td>
                    <td class="blue">${round.final.blue}</td>
                    <td class="red">${round.final.red}</td>
                    <td class="red">${round.final.red}</td>
                </tr>`;
    
                html += `</tbody></table>`;
                $('#match-tables').append(html);
            });
        });

        $(".loader-bar").hide();
    }
    

});

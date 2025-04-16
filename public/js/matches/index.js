$(document).ready(function () {
    var url = window.location.origin;

    $.get(url + "/api/local-matches", function (data) {
        $(".loader-bar").show();
        $('#match-tables').empty(); // clear sebelum render

        $.each(data, function (arenaName, matches) {
            var tableHtml = `
            <table class="table table-dark mb-5">
                <thead>
                    <tr>
                        <th colspan="9" class="table-title">${arenaName.toUpperCase()}</th>
                    </tr>
                    <tr>
                        <th scope="col" rowspan="2">#</th>
                        <th scope="col" rowspan="2">Kelas</th>
                        <th scope="col" colspan="2" class="border text-center">Peserta</th>
                        <th scope="col" rowspan="2">Pemenang</th>
                        <th scope="col" rowspan="2">Keterangan</th>
                        <th scope="col" rowspan="2">Action</th>
                    </tr>
                    <tr>
                        <th scope="col" class="border text-center blue">Sudut Biru</th>
                        <th scope="col" class="border text-center red">Sudut Merah</th>
                    </tr>
                </thead>
                <tbody>`;

            $.each(matches, function (index, match) {
                tableHtml += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${match.class_name}</td>
                        <td class="text-primary fw-bold">${match.blue_name}<br><small>${match.blue_contingent}</small></td>
                        <td class="text-danger fw-bold">${match.red_name}<br><small>${match.red_contingent}</small></td>
                        <td>-</td>
                        <td>${match.status}</td>
                        <td>
                        <a href="#" class="btn btn-outline-success btn-enter-match" data-id="${match.id}">Masuk</a>
                        <a href="/matches/${match.id}/recap" class="btn btn-outline-warning btn-recap-match ms-1">Rekapitulasi</a>
                        </td>
                    </tr>`;
            });

            tableHtml += `</tbody></table>`;
            $('#match-tables').append(tableHtml);
            $(".loader-bar").hide();
        });
    });

    // Event klik tombol Masuk
    $(document).on("click", ".btn-enter-match", function (e) {
        e.preventDefault();
        const matchId = $(this).data("id");
        window.location.href = `${url}/matches/${matchId}`;
    });

});

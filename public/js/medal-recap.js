$(document).ready(function () {
    var url = APP.baseUrl;

    $('#export-all').on('click', function () {
        window.open(`${url}/winner-recap/export-pdf/all`, '_blank');
    });


    $.get(url + "/api/medal-recap-per-atlet", function (data) {
        $('#recap-tables').empty();

        $.each(data, function (ageCategory, rows) {
            if (rows.length === 0) return;

            const tournamentName = rows[0].tournament_name ?? "NAMA TURNAMEN";
            const title = `DAFTAR NAMA PEMENANG ${tournamentName.toUpperCase()} - KATEGORI ${ageCategory.toUpperCase()}`;

            let table = `
            <div class="mb-5">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0 text-white">${title}</h5>
                    <button class="btn btn-danger btn-sm export-atlet-btn" data-category="${encodeURIComponent(ageCategory)}">
                        <i class="bi bi-file-earmark-pdf-fill"></i> Export
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-dark table-bordered">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Atlet</th>
                                <th>Kontingen</th>
                                <th colspan="3">Sebagai</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            $.each(rows, function (i, row) {
                const warnaMedali = {
                    'Juara I': 'text-warning',
                    'Juara II': 'text-secondary',
                    'Juara III': 'text-info'
                };

                table += `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${row.nama}</td>
                        <td>${row.kontingen}</td>
                        <td class="${warnaMedali[row.medali] ?? ''} fw-bold">${row.medali}</td>
                        <td class="text-uppercase">Kelas ${row.kelas}</td>
                        <td class="text-uppercase">${row.gender}</td>
                    </tr>
                `;
            });

            table += `</tbody></table></div></div>`;
            $('#recap-tables').append(table);
        });

        // 🔥 Bind tombol export PDF
        $(document).on('click', '.export-atlet-btn', function () {
            const category = $(this).data('category');
            window.open(`${url}/medal-recap-per-atlet/export-pdf/${category}`, '_blank');
        });
    });








    
    
    
    
    
    
    
    
    
    
    

    
    
    
    
    
    
    
    
    
    
    
    
    
    

});

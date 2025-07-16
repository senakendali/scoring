$(document).ready(function () {
    var url = APP.baseUrl;

    $('#export-all').on('click', function () {
        window.open(`${url}/medal-recap/export-pdf-all`, '_blank');
    });


    $.get(url + "/api/medal-recap", function (data) {
        $('#recap-tables').empty();

        $.each(data, function (ageCategory, rows) {
            let table = `
            <div class="mb-5">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0 text-white">PEROLEHAN MEDALI ${ageCategory.toUpperCase()}</h5>
                    <button class="btn btn-danger btn-sm export-btn" data-category="${ageCategory}">
                        <i class="bi bi-file-earmark-pdf-fill"></i> Export
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-dark table-bordered">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Contingent Name</th>
                                <th class="text-warning">EMAS</th>
                                <th class="text-secondary">PERAK</th>
                                <th class="text-info">PERUNGGU</th>
                                <th>TOTAL</th>
                                <th>KETERANGAN</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            $.each(rows, function (i, row) {
                table += `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${row.kontingen}</td>
                        <td class="text-warning fw-bold">${row.emas ? row.emas : '-'}</td>
                        <td class="text-secondary fw-bold">${row.perak ? row.perak : '-'}</td>
                        <td class="text-info fw-bold">${row.perunggu ? row.perunggu : '-'}</td>
                        <td>${row.total ? row.total : '-'}</td>
                        <td>${row.keterangan ?? '-'}</td>
                    </tr>
                `;
            });

            table += `</tbody></table></div></div>`;
            $('#recap-tables').append(table);
        });

        // 🧠 Bind event untuk export
        $('.export-btn').on('click', function () {
            const ageCategory = $(this).data('category');
            window.open(`${url}/medal-recap/export-pdf/${encodeURIComponent(ageCategory)}`, '_blank');
        });
    });




    
    
    
    
    
    
    
    
    
    
    

    
    
    
    
    
    
    
    
    
    
    
    
    
    

});

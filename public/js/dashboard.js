$(document).ready(function () {
    var url = window.location.origin;

    $.get(url + "/api/medal-recap", function (data) {
        $('#recap-tables').empty();

        $.each(data, function (ageCategory, rows) {
            let table = `
            <div class="mb-5">
                
                <div class="table-responsive">
                    <table class="table table-dark table-bordered">
                        <thead>
                            <tr>
                                <th colspan="7" class="text-white mb-3">PEROLEHAN MENDALI ${ageCategory.toUpperCase()}</tr>
                            </tr>
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
    });



    
    
    
    
    
    
    
    
    
    
    

    
    
    
    
    
    
    
    
    
    
    
    
    
    

});

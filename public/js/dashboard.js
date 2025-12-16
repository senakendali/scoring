$(document).ready(function () {
    var url = APP.baseUrl;

    // =========================
    // Full Prestasi helpers
    // =========================
    function getSelectedFullPrestasi() {
        const selected = [];
        $('.full-prestasi-check:checked').each(function () {
            selected.push($(this).val());
        });
        return selected;
    }

    function saveSelectedFullPrestasi(selected) {
        localStorage.setItem('full_prestasi_categories', JSON.stringify(selected || []));
    }

    function loadSelectedFullPrestasi() {
        try {
            return JSON.parse(localStorage.getItem('full_prestasi_categories') || '[]');
        } catch (e) {
            return [];
        }
    }

    function buildQuery(selected) {
        const params = new URLSearchParams();
        (selected || []).forEach(v => params.append('full_prestasi[]', v));
        const qs = params.toString();
        return qs ? `?${qs}` : '';
    }

    // =========================
    // Export all
    // =========================
    $('#export-all').on('click', function () {
        window.open(`${url}/medal-recap/export-pdf-all`, '_blank');
    });

    // =========================
    // Render function
    // =========================
    function renderTables(data) {
        $('#recap-tables').empty();

        $.each(data, function (ageCategory, rows) {
            let table = `
            <div class="mb-5">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0 text-white">PEROLEHAN MEDALI ${String(ageCategory).toUpperCase()}</h5>
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

        // 🧠 Bind event untuk export (hindari double bind)
        /*$('.export-btn').off('click').on('click', function () {
            const ageCategory = $(this).data('category');
            window.open(`${url}/medal-recap/export-pdf/${encodeURIComponent(ageCategory)}`, '_blank');
        });*/
        $('.export-btn').off('click').on('click', function () {
            const ageCategory = $(this).data('category');

            const selected = getSelectedFullPrestasi();
            const qs = buildQuery(selected);

            window.open(`${url}/medal-recap/export-pdf/${encodeURIComponent(ageCategory)}${qs}`, '_blank');
        });

    }

    // =========================
    // Fetch function (pakai query full_prestasi[])
    // =========================
    function fetchMedalRecap() {
        const selected = getSelectedFullPrestasi();
        const qs = buildQuery(selected);

        $.get(url + "/api/medal-recap" + qs, function (data) {
            renderTables(data);
        });
    }

    // =========================
    // Apply button (save + refresh)
    // =========================
    $('#apply-full-prestasi').on('click', function () {
        const selected = getSelectedFullPrestasi();
        saveSelectedFullPrestasi(selected);
        fetchMedalRecap();
    });

    // =========================
    // Init checkbox state from localStorage
    // =========================
    const saved = loadSelectedFullPrestasi();
    saved.forEach(v => {
        $(`.full-prestasi-check[value="${v}"]`).prop('checked', true);
    });

    // First load
    fetchMedalRecap();
});

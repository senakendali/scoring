function showAlert(message, title = 'Notifikasi') {
    $('#alertModalLabel').text(title);
    $('#alertModalBody').html(message);
    const modal = new bootstrap.Modal(document.getElementById('alertModal'));
    modal.show();
}

$(document).ready(function () {
    const $tournamentSelect = $('#tournament_name');
    $(".loader-bar").show();
    // 1. Load turnamen dari API pusat
    $.ajax({
        url: 'http://127.0.0.1:8002/api/tournaments/all',
        method: 'GET',
        success: function (data) {
            $tournamentSelect.empty().append('<option value="">-- Pilih Tournament --</option>');
            $.each(data, function (i, tournament) {
                $tournamentSelect.append(
                    $('<option>', {
                        value: tournament.slug,
                        text: tournament.name
                    })
                );
            });
            $(".loader-bar").hide();
        },
        error: function () {
            $tournamentSelect.empty().append('<option value="">Gagal memuat turnamen</option>');
            showAlert("Gagal mengambil daftar turnamen dari server pusat.", "Gagal Load Turnamen");
            $(".loader-bar").hide();
        }
    });

    // 2. Handle form submit untuk sync pertandingan
    $('#import-form').on('submit', function (e) {
        e.preventDefault();
        $(".loader-bar").show();
        const slug = $tournamentSelect.val();
        if (!slug) {
            showAlert("Silakan pilih turnamen terlebih dahulu.");
            $(".loader-bar").hide();
            return;
        }

        if (!confirm(`Yakin ingin mengimpor data pertandingan dari turnamen "${slug}"?`)) return;

        // Ambil data pertandingan dari server pusat
        $.get(`http://127.0.0.1:8002/api/sync/matches?tournament=${encodeURIComponent(slug)}`, function (matchData) {
            if (!Array.isArray(matchData) || matchData.length === 0) {
                showAlert("Data pertandingan kosong.", "Tidak Ada Data");
                $(".loader-bar").hide();
                return;
            }

            // Kirim ke API lokal
            $.ajax({
                url: '/api/import-matches',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                contentType: 'application/json',
                data: JSON.stringify(matchData),
                success: function () {
                    $(".loader-bar").hide();
                    showAlert("Import pertandingan berhasil ✅", "Berhasil");
                },
                error: function (xhr) {
                    $(".loader-bar").hide();
                    console.error(xhr);
                    showAlert("Gagal mengimpor data ke lokal.", "Gagal Simpan");
                }
            });
        }).fail(function () {
            $(".loader-bar").hide();
            showAlert("Gagal mengambil data dari server pusat.", "Gagal Sinkronisasi");
        });
    });
});
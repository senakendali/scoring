function showAlert(message, title = 'Notifikasi') {
    $('#alertModalLabel').text(title);
    $('#alertModalBody').html(message);
    const modal = new bootstrap.Modal(document.getElementById('alertModal'));
    modal.show();
}

$(document).ready(function () {
    const $tournamentSelectTanding = $('#tournament_name_tanding');
    const $tournamentSelectSeni = $('#tournament_name_seni');
    const dataSource = $('#data_source').val();

    
    $(".loader-bar").show();

    // 1. Load turnamen dari API pusat dan isi ke dua select
    $.ajax({
        url: `${dataSource}/api/tournaments/all`,
        method: 'GET',
        success: function (data) {
            $tournamentSelectTanding.empty().append('<option value="">-- Pilih Tournament --</option>');
            $tournamentSelectSeni.empty().append('<option value="">-- Pilih Tournament --</option>');

            $.each(data, function (i, tournament) {
                const option = $('<option>', {
                    value: tournament.slug,
                    text: tournament.name
                });
                $tournamentSelectTanding.append(option.clone());
                $tournamentSelectSeni.append(option.clone());
            });

            $(".loader-bar").hide();
        },
        error: function () {
            $tournamentSelectTanding.empty().append('<option value="">Gagal memuat turnamen</option>');
            $tournamentSelectSeni.empty().append('<option value="">Gagal memuat turnamen</option>');
            showAlert("Gagal mengambil daftar turnamen dari server pusat.", "Gagal Load Turnamen");
            $(".loader-bar").hide();
        }
    });

    // 2. Submit Form Tanding
   $('#import-form').on('submit', function (e) {
        e.preventDefault();
        $(".loader-bar").show();

        const slug = $tournamentSelectTanding.val();
        if (!slug) {
            showAlert("Silakan pilih turnamen terlebih dahulu.");
            $(".loader-bar").hide();
            return;
        }

        if (!confirm(`Yakin ingin mengimpor data pertandingan dari turnamen "${slug}"?`)) return;

        // Ambil nilai dari checkbox
        const isDisplayTimer = $('#display_timer_tanding').is(':checked') ? 1 : 0;

        $.get(`${dataSource}/api/sync/matches?tournament=${encodeURIComponent(slug)}`, function (matchData) {
            if (!Array.isArray(matchData) || matchData.length === 0) {
                showAlert("Data pertandingan kosong.", "Tidak Ada Data");
                $(".loader-bar").hide();
                return;
            }

            // Inject nilai checkbox ke semua item
            matchData = matchData.map(item => ({
                ...item,
                is_display_timer: isDisplayTimer
            }));

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
                    showAlert("Import pertandingan tanding berhasil ✅", "Berhasil");
                },
                error: function (xhr) {
                    $(".loader-bar").hide();
                    console.error(xhr);
                    showAlert("Gagal mengimpor data ke lokal (tanding).", "Gagal Simpan");
                }
            });
        }).fail(function () {
            $(".loader-bar").hide();
            showAlert("Gagal mengambil data dari server pusat (tanding).", "Gagal Sinkronisasi");
        });
    });


    // 3. Submit Form Seni
    $('#import-form-seni').on('submit', function (e) {
        e.preventDefault();
        $(".loader-bar").show();

        const slug = $tournamentSelectSeni.val();
        if (!slug) {
            showAlert("Silakan pilih turnamen terlebih dahulu.");
            $(".loader-bar").hide();
            return;
        }

        if (!confirm(`Yakin ingin mengimpor data pertandingan SENI dari turnamen "${slug}"?`)) return;

        // Ambil nilai checkbox untuk tampilan timer
        const isDisplayTimer = $('#display_timer_seni').is(':checked') ? 1 : 0;

        $.get(`${dataSource}/api/sync/matches/seni?tournament=${encodeURIComponent(slug)}`, function (matchData) {
            if (!Array.isArray(matchData) || matchData.length === 0) {
                showAlert("Data pertandingan seni kosong.", "Tidak Ada Data");
                $(".loader-bar").hide();
                return;
            }

            // Inject flag is_display_timer ke setiap item
            matchData = matchData.map(item => ({
                ...item,
                is_display_timer: isDisplayTimer
            }));

            $.ajax({
                url: '/api/import-seni-matches',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                contentType: 'application/json',
                data: JSON.stringify(matchData),
                success: function () {
                    $(".loader-bar").hide();
                    showAlert("Import pertandingan seni berhasil ✅", "Berhasil");
                },
                error: function (xhr) {
                    $(".loader-bar").hide();
                    console.error(xhr);
                    showAlert("Gagal mengimpor data ke lokal (seni).", "Gagal Simpan");
                }
            });
        }).fail(function () {
            $(".loader-bar").hide();
            showAlert("Gagal mengambil data dari server pusat (seni).", "Gagal Sinkronisasi");
        });
    });

});

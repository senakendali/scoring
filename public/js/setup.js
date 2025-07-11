$(document).ready(function () {
    var url = window.location.origin + '/digital_scoring/scoring/public';
    let activeJuris = [];

    function fetchActiveJuris(tournament, arena, matchType) {
        return fetch(url + `/api/active-juris?tournament_name=${encodeURIComponent(tournament)}&arena_name=${encodeURIComponent(arena)}&tipe_pertandingan=${matchType}`)
            .then(res => res.json())
            .then(data => {
                activeJuris = data;
                renderJuriOptions(matchType);
            });
    }

    function renderJuriOptions(matchType) {
        let juriOptions = '';
        const max = matchType === 'seni' ? 10 : 3;

        for (let i = 1; i <= max; i++) {
            if (!activeJuris.includes(i)) {
                juriOptions += `<option value="${i}">Juri ${i}</option>`;
            }
        }

        $("#juri_number").html(juriOptions);
    }

    function showAlert(message, title = "Informasi") {
        $("#alertModalLabel").text(title);
        $("#alertModalBody").text(message);
        const modal = new bootstrap.Modal(document.getElementById("alertModal"));
        modal.show();
    }
    

    // Ambil tournament list
    fetch(url + '/api/local-matches/tournaments')
        .then(res => res.json())
        .then(tournaments => {
            const select = document.getElementById("tournament_name");
            select.innerHTML = '<option value="">-- Pilih Turnamen --</option>';
            tournaments.forEach(name => {
                const opt = document.createElement("option");
                opt.value = name;
                opt.textContent = name;
                select.appendChild(opt);
            });
        });
    
    $('#mode-admin').on('click', function () {
        $('#match-setup-wrapper').show();
        $('#user-fields').hide();
        $('#match-setup-form').find('input[name="mode"]').remove();
        $('#match-setup-form').prepend('<input type="hidden" name="mode" value="admin">');
    });

    $('#mode-user').on('click', function () {
        $('#match-setup-wrapper').show();
        $('#user-fields').show();
        $('#match-setup-form').find('input[name="mode"]').remove();
        $('#match-setup-form').prepend('<input type="hidden" name="mode" value="user">');
    });



    // Ambil arena berdasarkan tournament
    $("#tournament_name, #match_type").on("change", function () {
        const tournament = $("#tournament_name").val();
        const matchType = $("#match_type").val(); // 'tanding' atau 'seni'

        // Jangan fetch kalau belum pilih keduanya
        if (!tournament || !matchType) return;

        fetch(url + `/api/local-matches/arenas?tournament=${encodeURIComponent(tournament)}&type=${matchType}`)
            .then(res => res.json())
            .then(arenas => {
                const arenaSelect = document.getElementById("arena_name");
                arenaSelect.innerHTML = '<option value="">-- Pilih Arena --</option>';

                arenas.forEach(arena => {
                    const opt = document.createElement("option");
                    opt.value = arena;
                    opt.textContent = arena;
                    arenaSelect.appendChild(opt);
                });
            });
    });

    function toggleSeniCategory() {
        const matchType = $('#match_type').val();
        const role = $('#role').val();

        if (matchType === 'seni' && role === 'juri') {
            $('#seni-category-wrapper').show();
        } else {
            $('#seni-category-wrapper').hide();
            $('#seni_category').val('');
        }
    }

    $('#match_type').on('change', toggleSeniCategory);
    $('#role').on('change', toggleSeniCategory);



    function shouldFetchJuri() {
        return $("#role").val() === "juri"
            && $("#match_type").val()
            && $("#tournament_name").val()
            && $("#arena_name").val();
    }

    $("#match_type, #role, #arena_name").on("change", function () {
        const role = $("#role").val();
        const matchType = $("#match_type").val();

        if (role === "juri") {
            $("#juri-number-group").removeClass("d-none");
            if (shouldFetchJuri()) {
                fetchActiveJuris(
                    $("#tournament_name").val(),
                    $("#arena_name").val(),
                    matchType
                );
            } else {
                renderJuriOptions(matchType); // fallback
            }
        } else {
            $("#juri-number-group").addClass("d-none");
        }
    });

    // Submit
    $("#match-setup-form").on("submit", function (e) {
        e.preventDefault();
        $(".loader-bar").show();

        const $submitBtn = $(this).find('button[type="submit"]');
        $submitBtn.prop("disabled", true).text("Memproses");

        // Ambil mode
        const mode = $(this).find('input[name="mode"]').val() || 'user';

        const formData = {
            tournament_name: $("#tournament_name").val(),
            arena_name: $("#arena_name").val(),
            match_type: $("#match_type").val(),
            seni_category: $("#match_type").val() === "seni" ? $("#seni_category").val() : null,
            role: $("#role").val(),
            juri_number: $("#juri_number").val() || null,
            mode: mode,
        };

        // ✅ Validasi: jika admin, hanya butuh tournament_name
        if (mode === 'admin') {
            if (!formData.tournament_name) {
                $(".loader-bar").hide();
                showAlert("Pilih turnamen terlebih dahulu.", "Peringatan");
                $submitBtn.prop("disabled", false).text("Masuk");
                return;
            }
        } else {
            // ✅ Validasi normal (user)
            if (!formData.tournament_name || !formData.arena_name || !formData.match_type || !formData.role) {
                $(".loader-bar").hide();
                showAlert("Lengkapi semua isian terlebih dahulu.", "Peringatan");
                $submitBtn.prop("disabled", false).text("Masuk");
                return;
            }

            if (formData.role === 'juri' && !formData.juri_number) {
                $(".loader-bar").hide();
                showAlert("Nomor juri wajib diisi.", "Peringatan");
                $submitBtn.prop("disabled", false).text("Masuk");
                return;
            }
        }

        fetch(url + '/api/match-personnel-assignments', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
            },
            body: JSON.stringify(formData),
        })
        .then(res => {
            $(".loader-bar").hide();
            if (!res.ok) throw res;
            return res.json();
        })
        .then(data => {
            $(".loader-bar").hide();
            console.log("✅ Active Juri saved", data);
            showAlert("Data berhasil disimpan, redirect ke interface sesuai role", "Informasi");
            setTimeout(() => {
                // Admin → redirect ke dashboard
                if (mode === 'admin') {
                    window.location.href = url + "/dashboard";
                    return;
                }

                const matchType = formData.match_type;
                const redirectBase = url + '/matches';

                if (matchType === 'seni') {
                    window.location.href = `${redirectBase}/seni`;
                } else {
                    window.location.href = `${redirectBase}/tanding`;
                }
            }, 1000);
        })
        .catch(async err => {
            $(".loader-bar").hide();
            let message = "Gagal menyimpan data, coba lagi.";
            if (err.json) {
                const json = await err.json();
                message = json.message || message;
            }
            console.error("❌ Gagal menyimpan:", err);
            showAlert(message, "Peringatan");
            $submitBtn.prop("disabled", false).text("Masuk");
        });
    });

    
});

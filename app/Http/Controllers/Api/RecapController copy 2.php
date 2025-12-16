<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class RecapController extends Controller
{
    public function medalRecap()
    {
        $tournamentName = session('tournament_name'); // ✅ Ambil dari session

        // ====== Ambil sumber data ======
        $matches = DB::table('local_matches')
            ->where('status', 'finished')
            ->whereIn('round_label', ['Final', 'Semifinal'])
            ->when($tournamentName, fn($q) => $q->where('tournament_name', $tournamentName))
            ->get();

        $seniMatches = DB::table('local_seni_matches')
            ->whereNotNull('medal')
            ->when($tournamentName, fn($q) => $q->where('tournament_name', $tournamentName))
            ->get();

        // ====== Validasi kelas TANDING: minimal 4 peserta ⇒ minimal ada 2 Semifinal di class ======
        // key = class_name
        $tandingByClass = collect($matches)->groupBy('class_name');

        // Kelas yang valid: punya >= 2 semifinal
        $validTandingClasses = $tandingByClass->filter(function ($rows) {
            $semiCount = $rows->where('round_label', 'Semifinal')->count();
            return $semiCount >= 2;
        })->keys()->all();

        // ====== Validasi pool/kelas SENI: minimal 4 peserta dalam pool ======
        // Prefer group by pool_id; fallback kalau kolom tidak ada
        $seniGroupKey = function ($m) {
            if (isset($m->pool_id) && $m->pool_id !== null) {
                return 'pool:' . $m->pool_id;
            }
            // fallback yang masih “masuk akal” kalau struktur ada:
            $age = $m->age_category ?? 'UnknownAge';
            $gender = $m->gender ?? 'UnknownGender';
            $type = $m->match_type ?? ($m->category ?? 'UnknownType'); // sesuaikan kalau ada
            return "grp:{$age}|{$gender}|{$type}";
        };

        $seniGrouped = collect($seniMatches)->groupBy($seniGroupKey);

        // Hitung distinct peserta per group (pakai participant_name / contingent_name yang tersedia)
        $validSeniGroups = $seniGrouped->filter(function ($rows) {
            // prioritas nama peserta; kalau tidak ada, pakai kontingen
            $names = $rows->map(function ($r) {
                return $r->participant_name
                    ?? $r->athlete_name
                    ?? $r->contingent_name
                    ?? $r->team_name
                    ?? null;
            })->filter()->unique();

            return $names->count() >= 4;
        });

        // ====== Gabungkan sumber yang SUDAH tervalidasi ======
        $grouped = [];

        // Tanding → hanya class yang valid
        foreach ($matches as $match) {
            if (!in_array($match->class_name, $validTandingClasses, true)) {
                continue; // skip kelas yang pesertanya < 4
            }

            $baseCategory = match (true) {
                Str::startsWith($match->class_name, 'Usia Dini') => 'Usia Dini',
                Str::startsWith($match->class_name, 'Pra Remaja') => 'Pra Remaja',
                Str::startsWith($match->class_name, 'Remaja') => 'Remaja',
                Str::startsWith($match->class_name, 'Dewasa') => 'Dewasa',
                Str::startsWith($match->class_name, 'Master') => 'Master',
                default => 'Lainnya',
            };

            $grouped[$baseCategory]['tanding'][] = $match;
        }

        // Seni → hanya group/pool yang valid (≥4 peserta)
        $validSeniMatches = $validSeniGroups->flatten(1);
        foreach ($validSeniMatches as $match) {
            $age = Str::of($match->age_category)->trim()->ucfirst();
            $baseCategory = match (true) {
                Str::startsWith($age, 'Usia Dini') => 'Usia Dini',
                Str::startsWith($age, 'Pra Remaja') => 'Pra Remaja',
                Str::startsWith($age, 'Remaja') => 'Remaja',
                Str::startsWith($age, 'Dewasa') => 'Dewasa',
                Str::startsWith($age, 'Master') => 'Master',
                default => 'Lainnya',
            };

            $grouped[$baseCategory]['seni'][] = $match;
        }

        // ====== Rekapitulasi ======
        $result = [];

        foreach ($grouped as $ageCategory => $sources) {
            $emas = [];
            $perak = [];
            $perunggu = [];

            // --- Tanding ---
            foreach ($sources['tanding'] ?? [] as $match) {
                $isInvalidMedal = in_array($match->win_reason, ['forfeit', 'disqualify']);

                if ($match->round_label === 'Final') {
                    $winner = $match->winner_corner === 'red' ? $match->red_contingent : $match->blue_contingent;
                    $loser  = $match->winner_corner === 'red' ? $match->blue_contingent : $match->red_contingent;

                    if ($winner) $emas[] = $winner;
                    if (!$isInvalidMedal && $loser) $perak[] = $loser;
                }

                if ($match->round_label === 'Semifinal') {
                    $loser = $match->winner_corner === 'red' ? $match->blue_contingent : $match->red_contingent;
                    if (!$isInvalidMedal && $loser) $perunggu[] = $loser;
                }
            }

            // --- Seni ---
            foreach ($sources['seni'] ?? [] as $match) {
                $kontingen = $match->contingent_name
                    ?? $match->team_name
                    ?? $match->participant_team
                    ?? null;
                if (!$kontingen) continue;

                if ($match->medal === 'emas')     $emas[] = $kontingen;
                elseif ($match->medal === 'perak')   $perak[] = $kontingen;
                elseif ($match->medal === 'perunggu')$perunggu[] = $kontingen;
            }

            // Hitung jumlah medali
            $rekap = [];

            foreach ($emas as $c)     { $rekap[$c]['emas']     = ($rekap[$c]['emas']     ?? 0) + 1; }
            foreach ($perak as $c)    { $rekap[$c]['perak']    = ($rekap[$c]['perak']    ?? 0) + 1; }
            foreach ($perunggu as $c) { $rekap[$c]['perunggu'] = ($rekap[$c]['perunggu'] ?? 0) + 1; }

            // Format final per kontingen
            $rekapList = [];
            foreach ($rekap as $kontingen => $data) {
                $g = $data['emas'] ?? 0;
                $s = $data['perak'] ?? 0;
                $b = $data['perunggu'] ?? 0;

                $rekapList[] = [
                    'kontingen'   => $kontingen,
                    'emas'        => $g,
                    'perak'       => $s,
                    'perunggu'    => $b,
                    'total'       => $g + $s + $b,
                    'keterangan'  => '',
                ];
            }

            usort($rekapList, fn($a, $b) =>
                [$b['emas'], $b['perak'], $b['perunggu']] <=> [$a['emas'], $a['perak'], $a['perunggu']]
            );

            foreach ($rekapList as $i => &$row) {
                if ($i === 0) $row['keterangan'] = 'JUARA UMUM 1';
                else if ($i === 1) $row['keterangan'] = 'JUARA UMUM 2';
                else if ($i === 2) $row['keterangan'] = 'JUARA UMUM 3';
            }

            $result[$ageCategory] = $rekapList;
        }

        return response()->json($result);
    }


    public function medalRecap__()
    {
        $tournamentName = session('tournament_name'); // ✅ Ambil dari session

        $matches = DB::table('local_matches')
            ->where('status', 'finished')
            ->whereIn('round_label', ['Final', 'Semifinal'])
            ->when($tournamentName, fn($q) =>
                $q->where('tournament_name', $tournamentName)
            )
            ->get();

        $seniMatches = DB::table('local_seni_matches')
            ->whereNotNull('medal')
            ->when($tournamentName, fn($q) =>
                $q->where('tournament_name', $tournamentName)
            )
            ->get();

        $grouped = [];

        // Gabung dari tanding
        foreach ($matches as $match) {
            $baseCategory = match (true) {
                Str::startsWith($match->class_name, 'Usia Dini') => 'Usia Dini',
                Str::startsWith($match->class_name, 'Pra Remaja') => 'Pra Remaja',
                Str::startsWith($match->class_name, 'Remaja') => 'Remaja',
                Str::startsWith($match->class_name, 'Dewasa') => 'Dewasa',
                Str::startsWith($match->class_name, 'Master') => 'Master',
                default => 'Lainnya',
            };

            $grouped[$baseCategory]['tanding'][] = $match;
        }

        // Gabung dari seni
        foreach ($seniMatches as $match) {
            $age = Str::of($match->age_category)->trim()->ucfirst();
            $baseCategory = match (true) {
                Str::startsWith($age, 'Usia Dini') => 'Usia Dini',
                Str::startsWith($age, 'Pra Remaja') => 'Pra Remaja',
                Str::startsWith($age, 'Remaja') => 'Remaja',
                Str::startsWith($age, 'Dewasa') => 'Dewasa',
                Str::startsWith($age, 'Master') => 'Master',
                default => 'Lainnya',
            };

            $grouped[$baseCategory]['seni'][] = $match;
        }

        $result = [];

        foreach ($grouped as $ageCategory => $sources) {
            $emas = [];
            $perak = [];
            $perunggu = [];

            // Tanding
            foreach ($sources['tanding'] ?? [] as $match) {
                $isInvalidMedal = in_array($match->win_reason, ['forfeit', 'disqualify']);

                if ($match->round_label === 'Final') {
                    $winner = $match->winner_corner === 'red' ? $match->red_contingent : $match->blue_contingent;
                    $loser = $match->winner_corner === 'red' ? $match->blue_contingent : $match->red_contingent;

                    $emas[] = $winner;

                    if (!$isInvalidMedal && $loser) {
                        $perak[] = $loser;
                    }
                }

                if ($match->round_label === 'Semifinal') {
                    $loser = $match->winner_corner === 'red' ? $match->blue_contingent : $match->red_contingent;

                    if (!$isInvalidMedal && $loser) {
                        $perunggu[] = $loser;
                    }
                }
            }

            // Seni
            foreach ($sources['seni'] ?? [] as $match) {
                $kontingen = $match->contingent_name;
                if ($match->medal === 'emas') $emas[] = $kontingen;
                if ($match->medal === 'perak') $perak[] = $kontingen;
                if ($match->medal === 'perunggu') $perunggu[] = $kontingen;
            }

            // Hitung jumlah medali
            $rekap = [];

            foreach ($emas as $c) {
                $rekap[$c]['emas'] = ($rekap[$c]['emas'] ?? 0) + 1;
            }

            foreach ($perak as $c) {
                $rekap[$c]['perak'] = ($rekap[$c]['perak'] ?? 0) + 1;
            }

            foreach ($perunggu as $c) {
                $rekap[$c]['perunggu'] = ($rekap[$c]['perunggu'] ?? 0) + 1;
            }

            // Format final per kontingen
            $rekapList = [];

            foreach ($rekap as $kontingen => $data) {
                $emas = $data['emas'] ?? 0;
                $perak = $data['perak'] ?? 0;
                $perunggu = $data['perunggu'] ?? 0;

                $rekapList[] = [
                    'kontingen' => $kontingen,
                    'emas' => $emas,
                    'perak' => $perak,
                    'perunggu' => $perunggu,
                    'total' => $emas + $perak + $perunggu,
                    'keterangan' => '',
                ];
            }

            usort($rekapList, fn($a, $b) =>
                [$b['emas'], $b['perak'], $b['perunggu']]
                <=> [$a['emas'], $a['perak'], $a['perunggu']]
            );

            foreach ($rekapList as $i => &$row) {
                if ($i === 0) $row['keterangan'] = 'JUARA UMUM 1';
                else if ($i === 1) $row['keterangan'] = 'JUARA UMUM 2';
                else if ($i === 2) $row['keterangan'] = 'JUARA UMUM 3';
            }

            $result[$ageCategory] = $rekapList;
        }

        return response()->json($result);
    }




    public function exportAllPDF()
    {
        $data = $this->medalRecap()->getData(true); // panggil fungsi yang sama
        $pdf = Pdf::loadView('exports.medal-recap-all', [
            'rekapPerUsia' => $data,
        ]);

        return $pdf->download('rekap_medali_semua_kategori.pdf');
    }


    public function exportPDF($ageCategory)
    {
        $tournamentName = session('tournament_name'); // ✅ Ambil dari session

        $matches = DB::table('local_matches')
            ->where('status', 'finished')
            ->whereIn('round_label', ['Final', 'Semifinal'])
            ->when($tournamentName, fn($q) =>
                $q->where('tournament_name', $tournamentName)
            )
            ->get();

        $seniMatches = DB::table('local_seni_matches')
            ->whereNotNull('medal')
            ->when($tournamentName, fn($q) =>
                $q->where('tournament_name', $tournamentName)
            )
            ->get();

        $grouped = [];

        foreach ($matches as $match) {
            $baseCategory = match (true) {
                Str::startsWith($match->class_name, 'Usia Dini') => 'Usia Dini',
                Str::startsWith($match->class_name, 'Pra Remaja') => 'Pra Remaja',
                Str::startsWith($match->class_name, 'Remaja') => 'Remaja',
                Str::startsWith($match->class_name, 'Dewasa') => 'Dewasa',
                Str::startsWith($match->class_name, 'Master') => 'Master',
                default => 'Lainnya',
            };

            if ($baseCategory === $ageCategory) {
                $grouped[$baseCategory]['tanding'][] = $match;
            }
        }

        foreach ($seniMatches as $match) {
            $age = Str::of($match->age_category)->trim()->ucfirst();
            $baseCategory = match (true) {
                Str::startsWith($age, 'Usia Dini') => 'Usia Dini',
                Str::startsWith($age, 'Pra Remaja') => 'Pra Remaja',
                Str::startsWith($age, 'Remaja') => 'Remaja',
                Str::startsWith($age, 'Dewasa') => 'Dewasa',
                Str::startsWith($age, 'Master') => 'Master',
                default => 'Lainnya',
            };

            if ($baseCategory === $ageCategory) {
                $grouped[$baseCategory]['seni'][] = $match;
            }
        }

        // 🔄 Proses perhitungan medali
        $emas = [];
        $perak = [];
        $perunggu = [];

        $sources = $grouped[$ageCategory] ?? [];

        foreach ($sources['tanding'] ?? [] as $match) {
            $isInvalidMedal = in_array($match->win_reason, ['forfeit', 'disqualify']);

            if ($match->round_label === 'Final') {
                $winner = $match->winner_corner === 'red' ? $match->red_contingent : $match->blue_contingent;
                $loser = $match->winner_corner === 'red' ? $match->blue_contingent : $match->red_contingent;

                $emas[] = $winner;

                if (!$isInvalidMedal && $loser) {
                    $perak[] = $loser;
                }
            }

            if ($match->round_label === 'Semifinal') {
                $loser = $match->winner_corner === 'red' ? $match->blue_contingent : $match->red_contingent;

                if (!$isInvalidMedal && $loser) {
                    $perunggu[] = $loser;
                }
            }
        }

        foreach ($sources['seni'] ?? [] as $match) {
            $kontingen = $match->contingent_name;
            if ($match->medal === 'emas') $emas[] = $kontingen;
            if ($match->medal === 'perak') $perak[] = $kontingen;
            if ($match->medal === 'perunggu') $perunggu[] = $kontingen;
        }

        $rekap = [];

        foreach ($emas as $c) {
            $rekap[$c]['emas'] = ($rekap[$c]['emas'] ?? 0) + 1;
        }

        foreach ($perak as $c) {
            $rekap[$c]['perak'] = ($rekap[$c]['perak'] ?? 0) + 1;
        }

        foreach ($perunggu as $c) {
            $rekap[$c]['perunggu'] = ($rekap[$c]['perunggu'] ?? 0) + 1;
        }

        $rekapList = [];

        foreach ($rekap as $kontingen => $data) {
            $emas = $data['emas'] ?? 0;
            $perak = $data['perak'] ?? 0;
            $perunggu = $data['perunggu'] ?? 0;

            $rekapList[] = [
                'kontingen' => $kontingen,
                'emas' => $emas,
                'perak' => $perak,
                'perunggu' => $perunggu,
                'total' => $emas + $perak + $perunggu,
                'keterangan' => '',
            ];
        }

        usort($rekapList, fn($a, $b) =>
            [$b['emas'], $b['perak'], $b['perunggu']]
            <=> [$a['emas'], $a['perak'], $a['perunggu']]
        );

        foreach ($rekapList as $i => &$row) {
            if ($i === 0) $row['keterangan'] = 'JUARA UMUM 1';
            else if ($i === 1) $row['keterangan'] = 'JUARA UMUM 2';
            else if ($i === 2) $row['keterangan'] = 'JUARA UMUM 3';
        }

        $pdf = Pdf::loadView('exports.medal-recap', [
            'ageCategory' => $ageCategory,
            'rekap' => $rekapList,
        ]);

        return $pdf->download('rekap_medali_' . strtolower($ageCategory) . '.pdf');
    }


    public function medalRecapPerAtlet()
    {
        $tournamentName = session('tournament_name'); // ✅ ambil dari session

        $matches = DB::table('local_matches')
            ->where('status', 'finished')
            ->whereIn('round_label', ['Final', 'Semifinal'])
            ->when($tournamentName, fn($q) => $q->where('tournament_name', $tournamentName))
            ->get();

        $seniMatches = DB::table('local_seni_matches')
            ->where('status', 'finished')
            ->when($tournamentName, fn($q) => $q->where('tournament_name', $tournamentName))
            ->get();

        $result = [];
        $finalists = [];

        foreach ($matches as $match) {
            $isInvalidMedal = in_array($match->win_reason, ['forfeit', 'disqualify']);

            preg_match('/^(.*)\s+([A-Z]+)\s+\((Putra|Putri)\)$/i', $match->class_name, $info);
            if (!$info) continue;

            $usiaRaw = trim($info[1]);
            $kelas = trim($info[2]);
            $gender = trim($info[3]);

            $usia = match (true) {
                Str::startsWith($usiaRaw, 'Usia Dini') => 'Usia Dini',
                Str::startsWith($usiaRaw, 'Pra Remaja') => 'Pra Remaja',
                Str::startsWith($usiaRaw, 'Remaja') => 'Remaja',
                Str::startsWith($usiaRaw, 'Dewasa') => 'Dewasa',
                Str::startsWith($usiaRaw, 'Master') => 'Master',
                default => $usiaRaw,
            };

            $winner = $match->winner_corner === 'red'
                ? ['name' => $match->red_name, 'contingent' => $match->red_contingent]
                : ['name' => $match->blue_name, 'contingent' => $match->blue_contingent];

            $loser = $match->winner_corner === 'red'
                ? ['name' => $match->blue_name, 'contingent' => $match->blue_contingent]
                : ['name' => $match->red_name, 'contingent' => $match->red_contingent];

            if ($match->round_label === 'Final') {
                $finalists[] = $winner['name'];
                $finalists[] = $loser['name'];

                $result[$usia][] = [
                    'nama' => $winner['name'],
                    'kontingen' => $winner['contingent'],
                    'kelas' => $kelas,
                    'gender' => $gender,
                    'medali' => 'Juara I',
                    'sort' => 1,
                    'tournament_name' => $match->tournament_name,
                ];

                if (!$isInvalidMedal) {
                    $result[$usia][] = [
                        'nama' => $loser['name'],
                        'kontingen' => $loser['contingent'],
                        'kelas' => $kelas,
                        'gender' => $gender,
                        'medali' => 'Juara II',
                        'sort' => 2,
                        'tournament_name' => $match->tournament_name,
                    ];
                }
            }

            if ($match->round_label === 'Semifinal' && !$isInvalidMedal) {
                if (!in_array($loser['name'], $finalists)) {
                    $result[$usia][] = [
                        'nama' => $loser['name'],
                        'kontingen' => $loser['contingent'],
                        'kelas' => $kelas,
                        'gender' => $gender,
                        'medali' => 'Juara III',
                        'sort' => 3,
                        'tournament_name' => $match->tournament_name,
                    ];
                }
            }
        }

        foreach ($seniMatches as $match) {
            if (!in_array($match->medal, ['emas', 'perak', 'perunggu'])) continue;

            $usia = match (true) {
                Str::startsWith($match->age_category, 'Usia Dini') => 'Usia Dini',
                Str::startsWith($match->age_category, 'Pra Remaja') => 'Pra Remaja',
                Str::startsWith($match->age_category, 'Remaja') => 'Remaja',
                Str::startsWith($match->age_category, 'Dewasa') => 'Dewasa',
                Str::startsWith($match->age_category, 'Master') => 'Master',
                default => $match->age_category,
            };

            $medalText = match ($match->medal) {
                'emas' => 'Juara I',
                'perak' => 'Juara II',
                'perunggu' => 'Juara III',
                default => ucfirst($match->medal),
            };

            $sort = match ($match->medal) {
                'emas' => 1,
                'perak' => 2,
                'perunggu' => 3,
                default => 4,
            };

            $kelas = ucfirst($match->category);

            $nama = match ($match->match_type) {
                'seni_tunggal', 'solo_kreatif' => $match->participant_1,
                'seni_ganda' => $match->participant_1 . ' & ' . $match->participant_2,
                'seni_regu' => $match->participant_1 . ' & ' . $match->participant_2 . ' & ' . $match->participant_3,
                default => $match->participant_1,
            };

            $gender = $match->gender === 'male' ? 'Putra' : 'Putri';

            $result[$usia][] = [
                'nama' => $nama,
                'kontingen' => $match->contingent_name,
                'kelas' => $kelas,
                'gender' => $gender,
                'medali' => $medalText,
                'sort' => $sort,
                'tournament_name' => $match->tournament_name,
            ];
        }

        foreach ($result as $usia => &$rows) {
            usort($rows, fn($a, $b) => $a['sort'] <=> $b['sort']);
        }

        return response()->json($result);
    }



    

    public function exportMedalRecapPerAtletPDF($ageCategory)
    {
        $tournamentName = session('tournament_name'); // ✅ Ambil nama turnamen aktif dari session

        $matches = DB::table('local_matches')
            ->where('status', 'finished')
            ->whereIn('round_label', ['Final', 'Semifinal'])
            ->when($tournamentName, fn($q) => $q->where('tournament_name', $tournamentName))
            ->get();

        $seniMatches = DB::table('local_seni_matches')
            ->where('status', 'finished')
            ->when($tournamentName, fn($q) => $q->where('tournament_name', $tournamentName))
            ->get();

        $result = [];
        $finalists = [];

        foreach ($matches as $match) {
            $isInvalidMedal = in_array($match->win_reason, ['forfeit', 'disqualify']);

            preg_match('/^(.*)\s+([A-Z]+)\s+\((Putra|Putri)\)$/i', $match->class_name, $info);
            if (!$info) continue;

            $usiaRaw = trim($info[1]);
            $kelas = trim($info[2]);
            $gender = trim($info[3]);

            $usia = match (true) {
                Str::startsWith($usiaRaw, 'Usia Dini') => 'Usia Dini',
                Str::startsWith($usiaRaw, 'Pra Remaja') => 'Pra Remaja',
                Str::startsWith($usiaRaw, 'Remaja') => 'Remaja',
                Str::startsWith($usiaRaw, 'Dewasa') => 'Dewasa',
                Str::startsWith($usiaRaw, 'Master') => 'Master',
                default => $usiaRaw,
            };

            if ($usia !== $ageCategory) continue;

            $winner = $match->winner_corner === 'red'
                ? ['name' => $match->red_name, 'contingent' => $match->red_contingent]
                : ['name' => $match->blue_name, 'contingent' => $match->blue_contingent];

            $loser = $match->winner_corner === 'red'
                ? ['name' => $match->blue_name, 'contingent' => $match->blue_contingent]
                : ['name' => $match->red_name, 'contingent' => $match->red_contingent];

            if ($match->round_label === 'Final') {
                $finalists[] = $winner['name'];
                $finalists[] = $loser['name'];

                $result[] = [
                    'nama' => $winner['name'],
                    'kontingen' => $winner['contingent'],
                    'kelas' => $kelas,
                    'gender' => $gender,
                    'medali' => 'Juara I',
                    'sort' => 1,
                    'tournament_name' => $match->tournament_name,
                ];

                if (!$isInvalidMedal) {
                    $result[] = [
                        'nama' => $loser['name'],
                        'kontingen' => $loser['contingent'],
                        'kelas' => $kelas,
                        'gender' => $gender,
                        'medali' => 'Juara II',
                        'sort' => 2,
                        'tournament_name' => $match->tournament_name,
                    ];
                }
            }

            if ($match->round_label === 'Semifinal' && !$isInvalidMedal) {
                if (!in_array($loser['name'], $finalists)) {
                    $result[] = [
                        'nama' => $loser['name'],
                        'kontingen' => $loser['contingent'],
                        'kelas' => $kelas,
                        'gender' => $gender,
                        'medali' => 'Juara III',
                        'sort' => 3,
                        'tournament_name' => $match->tournament_name,
                    ];
                }
            }
        }

        foreach ($seniMatches as $match) {
            if (!in_array($match->medal, ['emas', 'perak', 'perunggu'])) continue;

            $usia = match (true) {
                Str::startsWith($match->age_category, 'Usia Dini') => 'Usia Dini',
                Str::startsWith($match->age_category, 'Pra Remaja') => 'Pra Remaja',
                Str::startsWith($match->age_category, 'Remaja') => 'Remaja',
                Str::startsWith($match->age_category, 'Dewasa') => 'Dewasa',
                Str::startsWith($match->age_category, 'Master') => 'Master',
                default => $match->age_category,
            };

            if ($usia !== $ageCategory) continue;

            $medalText = match ($match->medal) {
                'emas' => 'Juara I',
                'perak' => 'Juara II',
                'perunggu' => 'Juara III',
                default => ucfirst($match->medal),
            };

            $sort = match ($match->medal) {
                'emas' => 1,
                'perak' => 2,
                'perunggu' => 3,
                default => 4,
            };

            $kelas = ucfirst($match->category);

            $nama = match ($match->match_type) {
                'seni_tunggal', 'solo_kreatif' => $match->participant_1,
                'seni_ganda' => $match->participant_1 . ' & ' . $match->participant_2,
                'seni_regu' => $match->participant_1 . ' & ' . $match->participant_2 . ' & ' . $match->participant_3,
                default => $match->participant_1,
            };

            $gender = match (strtolower($match->gender)) {
                'male' => 'Putra',
                'female' => 'Putri',
                default => ucfirst($match->gender),
            };

            $result[] = [
                'nama' => $nama,
                'kontingen' => $match->contingent_name,
                'kelas' => $kelas,
                'gender' => $gender,
                'medali' => $medalText,
                'sort' => $sort,
                'tournament_name' => $match->tournament_name,
            ];
        }

        usort($result, fn($a, $b) => $a['sort'] <=> $b['sort']);

        $tournamentName = $result[0]['tournament_name'] ?? 'Turnamen';

        $pdf = Pdf::loadView('exports.medal-recap-per-atlet', [
            'ageCategory' => $ageCategory,
            'tournamentName' => $tournamentName,
            'rows' => $result,
        ]);

        return $pdf->download('rekap-pemenang-' . Str::slug($ageCategory) . '.pdf');
    }



    public function exportMedalRecapPerAtletAllPDF()
    {
        $tournamentName = session('tournament_name'); // ✅ Ambil nama turnamen aktif dari session

        $matches = DB::table('local_matches')
            ->where('status', 'finished')
            ->whereIn('round_label', ['Final', 'Semifinal'])
            ->when($tournamentName, fn($q) => $q->where('tournament_name', $tournamentName))
            ->get();

        $seniMatches = DB::table('local_seni_matches')
            ->where('status', 'finished')
            ->when($tournamentName, fn($q) => $q->where('tournament_name', $tournamentName))
            ->get();

        $grouped = [];
        $finalists = [];

        foreach ($matches as $match) {
            $isInvalidMedal = in_array($match->win_reason, ['forfeit', 'disqualify']);

            preg_match('/^(.*)\s+([A-Z]+)\s+\((Putra|Putri)\)$/i', $match->class_name, $info);
            if (!$info) continue;

            $usiaRaw = trim($info[1]);
            $kelas = trim($info[2]);
            $gender = trim($info[3]);

            $usia = match (true) {
                Str::startsWith($usiaRaw, 'Usia Dini') => 'Usia Dini',
                Str::startsWith($usiaRaw, 'Pra Remaja') => 'Pra Remaja',
                Str::startsWith($usiaRaw, 'Remaja') => 'Remaja',
                Str::startsWith($usiaRaw, 'Dewasa') => 'Dewasa',
                Str::startsWith($usiaRaw, 'Master') => 'Master',
                default => $usiaRaw,
            };

            $winner = $match->winner_corner === 'red'
                ? ['name' => $match->red_name, 'contingent' => $match->red_contingent]
                : ['name' => $match->blue_name, 'contingent' => $match->blue_contingent];

            $loser = $match->winner_corner === 'red'
                ? ['name' => $match->blue_name, 'contingent' => $match->blue_contingent]
                : ['name' => $match->red_name, 'contingent' => $match->red_contingent];

            if ($match->round_label === 'Final') {
                $finalists[] = $winner['name'];
                $finalists[] = $loser['name'];

                $grouped[$usia][] = [
                    'nama' => $winner['name'],
                    'kontingen' => $winner['contingent'],
                    'kelas' => $kelas,
                    'gender' => $gender,
                    'medali' => 'Juara I',
                    'sort' => 1,
                    'tournament_name' => $match->tournament_name,
                ];

                if (!$isInvalidMedal) {
                    $grouped[$usia][] = [
                        'nama' => $loser['name'],
                        'kontingen' => $loser['contingent'],
                        'kelas' => $kelas,
                        'gender' => $gender,
                        'medali' => 'Juara II',
                        'sort' => 2,
                        'tournament_name' => $match->tournament_name,
                    ];
                }
            }

            if ($match->round_label === 'Semifinal' && !$isInvalidMedal) {
                if (!in_array($loser['name'], $finalists)) {
                    $grouped[$usia][] = [
                        'nama' => $loser['name'],
                        'kontingen' => $loser['contingent'],
                        'kelas' => $kelas,
                        'gender' => $gender,
                        'medali' => 'Juara III',
                        'sort' => 3,
                        'tournament_name' => $match->tournament_name,
                    ];
                }
            }
        }

        foreach ($seniMatches as $match) {
            if (!in_array($match->medal, ['emas', 'perak', 'perunggu'])) continue;

            $usia = match (true) {
                Str::startsWith($match->age_category, 'Usia Dini') => 'Usia Dini',
                Str::startsWith($match->age_category, 'Pra Remaja') => 'Pra Remaja',
                Str::startsWith($match->age_category, 'Remaja') => 'Remaja',
                Str::startsWith($match->age_category, 'Dewasa') => 'Dewasa',
                Str::startsWith($match->age_category, 'Master') => 'Master',
                default => $match->age_category,
            };

            $medalText = match ($match->medal) {
                'emas' => 'Juara I',
                'perak' => 'Juara II',
                'perunggu' => 'Juara III',
                default => ucfirst($match->medal),
            };

            $sort = match ($match->medal) {
                'emas' => 1,
                'perak' => 2,
                'perunggu' => 3,
                default => 4,
            };

            $kelas = ucfirst($match->category);

            $nama = match ($match->match_type) {
                'seni_tunggal', 'solo_kreatif' => $match->participant_1,
                'seni_ganda' => $match->participant_1 . ' & ' . $match->participant_2,
                'seni_regu' => $match->participant_1 . ' & ' . $match->participant_2 . ' & ' . $match->participant_3,
                default => $match->participant_1,
            };

            $gender = match (strtolower($match->gender)) {
                'male' => 'Putra',
                'female' => 'Putri',
                default => ucfirst($match->gender),
            };

            $grouped[$usia][] = [
                'nama' => $nama,
                'kontingen' => $match->contingent_name,
                'kelas' => $kelas,
                'gender' => $gender,
                'medali' => $medalText,
                'sort' => $sort,
                'tournament_name' => $match->tournament_name,
            ];
        }

        foreach ($grouped as $usia => &$rows) {
            usort($rows, fn($a, $b) => $a['sort'] <=> $b['sort']);
        }

        $tournamentName = collect($grouped)->first()[0]['tournament_name'] ?? 'Turnamen';

        $pdf = Pdf::loadView('exports.rekap-pemenang-all', [
            'tournamentName' => $tournamentName,
            'rekap' => $grouped,
        ]);

        return $pdf->download('rekap-medali-semua-kategori.pdf');
    }






}

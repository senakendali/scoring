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
        $matches = DB::table('local_matches')
            ->where('status', 'finished')
            ->whereIn('round_label', ['Final', 'Semifinal'])
            ->get();

        $seniMatches = DB::table('local_seni_matches')
            ->whereNotNull('medal')
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

                    // Emas tetap diberikan
                    $emas[] = $winner;

                    // Perak hanya jika lawan tidak kalah karena WO/Diskualifikasi
                    if (!$isInvalidMedal && $loser) {
                        $perak[] = $loser;
                    }
                }

                if ($match->round_label === 'Semifinal') {
                    $loser = $match->winner_corner === 'red' ? $match->blue_contingent : $match->red_contingent;

                    // Perunggu hanya jika kalah bukan karena WO/Diskualifikasi
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

            // Urutkan berdasar emas > perak > perunggu
            usort($rekapList, fn($a, $b) =>
                [$b['emas'], $b['perak'], $b['perunggu']]
                <=> [$a['emas'], $a['perak'], $a['perunggu']]
            );

            // Tandai juara umum
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
        $matches = DB::table('local_matches')
            ->where('status', 'finished')
            ->whereIn('round_label', ['Final', 'Semifinal'])
            ->get();

        $seniMatches = DB::table('local_seni_matches')
            ->whereNotNull('medal')
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

            if ($baseCategory === $ageCategory) {
                $grouped[$baseCategory]['tanding'][] = $match;
            }
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

            if ($baseCategory === $ageCategory) {
                $grouped[$baseCategory]['seni'][] = $match;
            }
        }

        // Bangun rekap
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

        // Urutkan berdasarkan emas > perak > perunggu
        usort($rekapList, fn($a, $b) =>
            [$b['emas'], $b['perak'], $b['perunggu']]
            <=> [$a['emas'], $a['perak'], $a['perunggu']]
        );

        // Tandai juara umum
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
        $matches = DB::table('local_matches')
            ->where('status', 'finished')
            ->whereIn('round_label', ['Final', 'Semifinal'])
            ->get();

        $result = [];

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

            $tournamentName = $match->tournament_name;

            $winner = $match->winner_corner === 'red'
                ? ['name' => $match->red_name, 'contingent' => $match->red_contingent]
                : ['name' => $match->blue_name, 'contingent' => $match->blue_contingent];

            $result[$usia][] = [
                'nama' => $winner['name'],
                'kontingen' => $winner['contingent'],
                'kelas' => $kelas,
                'gender' => $gender,
                'medali' => 'Juara I',
                'sort' => 1,
                'tournament_name' => $tournamentName,
            ];

            if ($match->round_label === 'Final' && !$isInvalidMedal) {
                $loser = $match->winner_corner === 'red'
                    ? ['name' => $match->blue_name, 'contingent' => $match->blue_contingent]
                    : ['name' => $match->red_name, 'contingent' => $match->red_contingent];

                $result[$usia][] = [
                    'nama' => $loser['name'],
                    'kontingen' => $loser['contingent'],
                    'kelas' => $kelas,
                    'gender' => $gender,
                    'medali' => 'Juara II',
                    'sort' => 2,
                    'tournament_name' => $tournamentName,
                ];
            }

            if ($match->round_label === 'Semifinal' && !$isInvalidMedal) {
                $loser = $match->winner_corner === 'red'
                    ? ['name' => $match->blue_name, 'contingent' => $match->blue_contingent]
                    : ['name' => $match->red_name, 'contingent' => $match->red_contingent];

                $result[$usia][] = [
                    'nama' => $loser['name'],
                    'kontingen' => $loser['contingent'],
                    'kelas' => $kelas,
                    'gender' => $gender,
                    'medali' => 'Juara III',
                    'sort' => 3,
                    'tournament_name' => $tournamentName,
                ];
            }
        }

        // ✅ Urutkan per kategori usia berdasarkan medali
        foreach ($result as $usia => &$rows) {
            usort($rows, fn($a, $b) => $a['sort'] <=> $b['sort']);
        }

        return response()->json($result);
    }

    

    public function exportMedalRecapPerAtletPDF($ageCategory)
    {
        $matches = DB::table('local_matches')
            ->where('status', 'finished')
            ->whereIn('round_label', ['Final', 'Semifinal'])
            ->get();

        $result = [];

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

            $tournamentName = $match->tournament_name;

            $winner = $match->winner_corner === 'red'
                ? ['name' => $match->red_name, 'contingent' => $match->red_contingent]
                : ['name' => $match->blue_name, 'contingent' => $match->blue_contingent];

            $result[] = [
                'nama' => $winner['name'],
                'kontingen' => $winner['contingent'],
                'kelas' => $kelas,
                'gender' => $gender,
                'medali' => 'Juara I',
                'sort' => 1,
                'tournament_name' => $tournamentName,
            ];

            if ($match->round_label === 'Final' && !$isInvalidMedal) {
                $loser = $match->winner_corner === 'red'
                    ? ['name' => $match->blue_name, 'contingent' => $match->blue_contingent]
                    : ['name' => $match->red_name, 'contingent' => $match->red_contingent];

                $result[] = [
                    'nama' => $loser['name'],
                    'kontingen' => $loser['contingent'],
                    'kelas' => $kelas,
                    'gender' => $gender,
                    'medali' => 'Juara II',
                    'sort' => 2,
                    'tournament_name' => $tournamentName,
                ];
            }

            if ($match->round_label === 'Semifinal' && !$isInvalidMedal) {
                $loser = $match->winner_corner === 'red'
                    ? ['name' => $match->blue_name, 'contingent' => $match->blue_contingent]
                    : ['name' => $match->red_name, 'contingent' => $match->red_contingent];

                $result[] = [
                    'nama' => $loser['name'],
                    'kontingen' => $loser['contingent'],
                    'kelas' => $kelas,
                    'gender' => $gender,
                    'medali' => 'Juara III',
                    'sort' => 3,
                    'tournament_name' => $tournamentName,
                ];
            }
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
        $matches = DB::table('local_matches')
            ->where('status', 'finished')
            ->whereIn('round_label', ['Final', 'Semifinal'])
            ->get();

        $grouped = [];

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

            $tournamentName = $match->tournament_name;

            $winner = $match->winner_corner === 'red'
                ? ['name' => $match->red_name, 'contingent' => $match->red_contingent]
                : ['name' => $match->blue_name, 'contingent' => $match->blue_contingent];

            $grouped[$usia][] = [
                'nama' => $winner['name'],
                'kontingen' => $winner['contingent'],
                'kelas' => $kelas,
                'gender' => $gender,
                'medali' => 'Juara I',
                'sort' => 1,
                'tournament_name' => $tournamentName,
            ];

            if ($match->round_label === 'Final' && !$isInvalidMedal) {
                $loser = $match->winner_corner === 'red'
                    ? ['name' => $match->blue_name, 'contingent' => $match->blue_contingent]
                    : ['name' => $match->red_name, 'contingent' => $match->red_contingent];

                $grouped[$usia][] = [
                    'nama' => $loser['name'],
                    'kontingen' => $loser['contingent'],
                    'kelas' => $kelas,
                    'gender' => $gender,
                    'medali' => 'Juara II',
                    'sort' => 2,
                    'tournament_name' => $tournamentName,
                ];
            }

            if ($match->round_label === 'Semifinal' && !$isInvalidMedal) {
                $loser = $match->winner_corner === 'red'
                    ? ['name' => $match->blue_name, 'contingent' => $match->blue_contingent]
                    : ['name' => $match->red_name, 'contingent' => $match->red_contingent];

                $grouped[$usia][] = [
                    'nama' => $loser['name'],
                    'kontingen' => $loser['contingent'],
                    'kelas' => $kelas,
                    'gender' => $gender,
                    'medali' => 'Juara III',
                    'sort' => 3,
                    'tournament_name' => $tournamentName,
                ];
            }
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

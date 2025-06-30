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
                if ($match->round_label === 'Final') {
                    $winner = $match->winner_corner === 'red' ? $match->red_contingent : $match->blue_contingent;
                    $loser = $match->winner_corner === 'red' ? $match->blue_contingent : $match->red_contingent;
                    $emas[] = $winner;
                    $perak[] = $loser;
                }

                if ($match->round_label === 'Semifinal') {
                    $loser = $match->winner_corner === 'red' ? $match->blue_contingent : $match->red_contingent;
                    $perunggu[] = $loser;
                }
            }

            // Seni
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
                [$b['total'], $b['emas'], $b['perak'], $b['perunggu']]
                <=> [$a['total'], $a['emas'], $a['perak'], $a['perunggu']]
            );

            foreach ($rekapList as $i => &$row) {
                if ($i == 0) $row['keterangan'] = 'JUARA UMUM 1';
                else if ($i == 1) $row['keterangan'] = 'JUARA UMUM 2';
                else if ($i == 2) $row['keterangan'] = 'JUARA UMUM 3';
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
        // Ambil semua match (filter hanya yang selesai dan relevan)
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
            if ($match->round_label === 'Final') {
                $winner = $match->winner_corner === 'red' ? $match->red_contingent : $match->blue_contingent;
                $loser = $match->winner_corner === 'red' ? $match->blue_contingent : $match->red_contingent;
                $emas[] = $winner;
                $perak[] = $loser;
            }

            if ($match->round_label === 'Semifinal') {
                $loser = $match->winner_corner === 'red' ? $match->blue_contingent : $match->red_contingent;
                $perunggu[] = $loser;
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
            [$b['total'], $b['emas'], $b['perak'], $b['perunggu']]
            <=> [$a['total'], $a['emas'], $a['perak'], $a['perunggu']]
        );

        foreach ($rekapList as $i => &$row) {
            if ($i == 0) $row['keterangan'] = 'JUARA UMUM 1';
            else if ($i == 1) $row['keterangan'] = 'JUARA UMUM 2';
            else if ($i == 2) $row['keterangan'] = 'JUARA UMUM 3';
        }

        $pdf = Pdf::loadView('exports.medal-recap', [
            'ageCategory' => $ageCategory,
            'rekap' => $rekapList,
        ]);

       return $pdf->download('rekap_medali_' . strtolower($ageCategory) . '.pdf');

    }

}

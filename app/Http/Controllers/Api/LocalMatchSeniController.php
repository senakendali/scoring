<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Events\SeniMatchStarted;
use App\Events\SeniTimerStarted;
use App\Events\SeniTimerUpdated;
use App\Events\SeniTimerFinished;
use App\Events\SeniBattleWinnerAnnounced;
use App\Events\SeniBattleGroupCompleted;
use App\Models\LocalSeniMatch;
use App\Models\LocalSeniScore;
use App\Models\LocalSeniPenalties;
use App\Models\MatchPersonnelAssignment;
use App\Models\LocalSeniFinalScore;
use App\Models\LocalSeniComponentScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rule; 
use Illuminate\Support\Facades\File;


class LocalMatchSeniController extends Controller
{
    private $live_server;

    public function __construct()
    {
        $this->live_server = config('app_settings.data_source');
    }
    public function fetchLiveMatches(Request $request)
    {
        $arenaName = session('arena_name');
        $matchType = session('match_type'); // 'seni' atau 'tanding'
        $tournament = session('tournament_name');

        $query = \App\Models\LocalSeniMatch::query();

        if ($arenaName) {
            $query->where('arena_name', $arenaName);
        }

        if ($matchType) {
            $query->where('match_type', 'like', 'seni_%');
        }

        if ($tournament) {
            $query->where('tournament_name', $tournament);
        }

         // ✅ Filter hanya match yang sedang berlangsung
        $query->where('status', 'ongoing');

        $matches = $query->orderBy('match_date')
            ->orderBy('match_time')
            ->get();

        $groupedByArena = [];

        foreach ($matches as $match) {
            $arena = $match->arena_name ?? 'Tanpa Arena';

            if (!isset($groupedByArena[$arena])) {
                $groupedByArena[$arena] = [];
            }

            // Cari apakah kategori + gender sudah ada
            $existingCategoryIndex = collect($groupedByArena[$arena])->search(function ($item) use ($match) {
                return $item['category'] === $match->category && $item['gender'] === $match->gender;
            });

            if ($existingCategoryIndex === false) {
                $groupedByArena[$arena][] = [
                    'category' => $match->category,
                    'gender' => $match->gender,
                    'pools' => [],
                ];
                $existingCategoryIndex = count($groupedByArena[$arena]) - 1;
            }

            $categoryGroup =& $groupedByArena[$arena][$existingCategoryIndex];

            // Cari pool
            $existingPoolIndex = collect($categoryGroup['pools'])->search(function ($pool) use ($match) {
                return $pool['name'] === $match->pool_name;
            });

            if ($existingPoolIndex === false) {
                $categoryGroup['pools'][] = [
                    'name' => $match->pool_name,
                    'matches' => [],
                ];
                $existingPoolIndex = count($categoryGroup['pools']) - 1;
            }

            $categoryGroup['pools'][$existingPoolIndex]['matches'][] = [
                'id' => $match->id,
                'match_order' => $match->match_order,
                'match_type' => $match->match_type,
                'contingent' => ['name' => $match->contingent_name],
                'final_score' => $match->final_score,
                'status' => $match->status,
                'team_member1' => ['name' => $match->participant_1],
                'team_member2' => ['name' => $match->participant_2],
                'team_member3' => ['name' => $match->participant_3],
                'pool' => [
                    'age_category' => [
                        'name' => $match->age_category ?? '-'
                    ]
                ]
            ];
        }

        return response()->json($groupedByArena);
    }

    public function index(Request $request)
{
    $arenaName = session('arena_name');
    $matchType = session('match_type'); // 'seni' atau 'tanding'
    $tournament = session('tournament_name');

    $query = \App\Models\LocalSeniMatch::query();

    if ($arenaName) {
        $query->where('arena_name', $arenaName);
    }

    if ($matchType === 'seni') {
        $query->whereIn('match_type', ['seni_tunggal', 'seni_ganda', 'seni_regu', 'solo_kreatif']);
    }

    if ($tournament) {
        $query->where('tournament_name', $tournament);
    }

    // urutkan dasar by match_order numeric
    $matches = $query->orderBy(DB::raw('CAST(match_order AS UNSIGNED)'))->get();

    // map cepat: local_match_id => match_order (buat lookup parent → nomor partai)
    $matchOrderById = $matches->pluck('match_order', 'id')
        ->map(fn($o) => is_null($o) ? null : (int)$o)
        ->toArray();

    // helper: cek “TBD” (bersihin zero-width char juga)
    $isTbd = function (?string $name): bool {
        if ($name === null) return true;
        $clean = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}\x{2060}]/u', '', $name);
        $clean = strtoupper(trim($clean));
        return ($clean === 'TBD' || $clean === '' || $clean === '-');
    };

    // Bangun map group berdasarkan kategori
    $groupedMap = [];
    $matchGroupMap = [];

    foreach ($matches as $match) {
        $category    = ucwords(strtolower(trim($match->category ?? '-')));
        $gender      = strtolower(trim($match->gender ?? '-'));
        $ageCategory = ucwords(strtolower(trim($match->age_category ?? 'Tanpa Usia')));
        $poolName    = trim($match->pool_name ?? '-');

        $groupKey = $category . '|' . $gender . '|' . $ageCategory;
        $matchGroupMap[$match->id] = $groupKey;

        if (!isset($groupedMap[$groupKey])) {
            $groupedMap[$groupKey] = [
                'category'       => $category,
                'gender'         => $gender,
                'age_categories' => []
            ];
        }

        if (!isset($groupedMap[$groupKey]['age_categories'][$ageCategory])) {
            $groupedMap[$groupKey]['age_categories'][$ageCategory] = [
                'age_category' => $ageCategory,
                'pools'        => []
            ];
        }

        if (!isset($groupedMap[$groupKey]['age_categories'][$ageCategory]['pools'][$poolName])) {
            $groupedMap[$groupKey]['age_categories'][$ageCategory]['pools'][$poolName] = [
                'name'    => $poolName,
                'matches' => []
            ];
        }

        // record match lengkap (ikut battle fields)
        $groupedMap[$groupKey]['age_categories'][$ageCategory]['pools'][$poolName]['matches'][] = [
            'id'                   => (int) $match->id,
            'match_order'          => is_null($match->match_order) ? null : (int) $match->match_order,
            'match_type'           => $match->match_type,
            'contingent'           => ['name' => $match->contingent_name],
            'final_score'          => $match->final_score,
            'status'               => $match->status,

            'team_member1'         => ['name' => $match->participant_1],
            'team_member2'         => ['name' => $match->participant_2],
            'team_member3'         => ['name' => $match->participant_3],

            'pool'                 => ['age_category' => ['name' => $ageCategory]],

            // battle fields (dari lokal)
            'mode'                 => $match->mode,                 // 'default' | 'battle' | null
            'battle_group'         => $match->battle_group,         // int|null
            'round'                => is_null($match->round) ? null : (int) $match->round,
            'round_label'          => $match->round_label,          // string|null
            'round_priority'       => is_null($match->round_priority) ? null : (int) $match->round_priority,
            'corner'               => $match->corner,               // 'red'|'blue'|null
            'winner_corner'        => $match->winner_corner,        // 'red'|'blue'|null
            'parent_match_red_id'  => $match->parent_match_red_id,  // local id|null
            'parent_match_blue_id' => $match->parent_match_blue_id, // local id|null
        ];
    }

    // Bikin response
    $result = [];
    $used = [];

    foreach ($matches as $match) {
        $groupKey = $matchGroupMap[$match->id];

        if (in_array($groupKey, $used, true)) continue;
        $used[] = $groupKey;

        $group = $groupedMap[$groupKey];

        $ageCategories = [];

        foreach ($group['age_categories'] as $ageCategory => $ageData) {
            $pools = [];

            foreach ($ageData['pools'] as $pool) {
                $rows = $pool['matches'];

                // sort by match_order ASC (null di akhir)
                usort($rows, function ($a, $b) {
                    $ao = $a['match_order'] ?? PHP_INT_MAX;
                    $bo = $b['match_order'] ?? PHP_INT_MAX;
                    if ($ao === $bo) return 0;
                    return $ao <=> $bo;
                });

                // Kelompokkan per GRUP: pakai battle_group jika ada, fallback ke match_order
                $groupsMap = [];
                foreach ($rows as $idx => $row) {
                    $key = !is_null($row['battle_group'])
                        ? 'G' . $row['battle_group']
                        : (!is_null($row['match_order']) ? 'O' . $row['match_order'] : 'X' . $idx);
                    $groupsMap[$key][] = $row;
                }

                // Sort grup by angka pada key
                $groupKeys = array_keys($groupsMap);
                usort($groupKeys, function ($a, $b) {
                    $na = (int) substr($a, 1);
                    $nb = (int) substr($b, 1);
                    return $na <=> $nb;
                });

                // Rekonstruksi rows dengan fallback battle info + REPLACE TBD
                $finalRows = [];
                foreach ($groupKeys as $gkey) {
                    $arr = $groupsMap[$gkey];

                    // fallback corner/mode + ganti TBD sesuai parent
                    foreach ($arr as $i => &$r) {
                        if (is_null($r['battle_group'])) {
                            $r['battle_group'] = (int) substr($gkey, 1);
                        }

                        $corner = strtolower((string) ($r['corner'] ?? ''));
                        if ($corner !== 'blue' && $corner !== 'red') {
                            if ($i === 0)      { $r['corner'] = 'blue'; $corner = 'blue'; }
                            elseif ($i === 1)  { $r['corner'] = 'red';  $corner = 'red'; }
                        }

                        if (empty($r['mode']) && count($arr) >= 2) {
                            $r['mode'] = 'battle';
                        }

                        // === Replace TBD contingent name dengan "Pemenang Partai No X"
                        $currName = $r['contingent']['name'] ?? null;
                        if ($isTbd($currName)) {
                            $parentId = null;
                            if ($corner === 'blue') {
                                $parentId = $r['parent_match_blue_id'] ?? null;
                            } elseif ($corner === 'red') {
                                $parentId = $r['parent_match_red_id'] ?? null;
                            }
                            if ($parentId && isset($matchOrderById[$parentId]) && $matchOrderById[$parentId] !== null) {
                                $r['contingent']['name'] = 'Pemenang Partai No #' . (int) $matchOrderById[$parentId];
                            } else {
                                $r['contingent']['name'] = 'Pemenang Partai Sebelumnya';
                            }
                        }
                    }
                    unset($r);

                    // Sort isi grup: BLUE dulu, RED lalu lainnya
                    usort($arr, function ($a, $b) {
                        $rank = function ($v) {
                            $s = strtolower((string) ($v ?? ''));
                            if ($s === 'blue') return 0;
                            if ($s === 'red')  return 1;
                            return 2;
                        };
                        $ra = $rank($a['corner']);
                        $rb = $rank($b['corner']);
                        if ($ra !== $rb) return $ra <=> $rb;
                        return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
                    });

                    foreach ($arr as $r) {
                        $finalRows[] = $r;
                    }
                }

                // Replace matches untuk pool ini
                $pool['matches'] = $finalRows;
                $pools[] = $pool;
            }

            $ageCategories[] = [
                'age_category' => $ageCategory,
                'pools'        => $pools
            ];
        }

        $result[] = [
            'category'       => $group['category'],
            'gender'         => $group['gender'],
            'age_categories' => $ageCategories
        ];
    }

    return response()->json($result);
}

    public function fetchMatchForAdmin(Request $request)
{
    $tournamentName = session('tournament_name'); // ✅ wajib: scope turnamen

    // ---- FILTER INPUT DARI FE ----
    $arenaFilter = trim((string) $request->input('arena_name', '')); // opsional: satu arena
    $fromPartai  = $request->filled('from_partai') ? (int) $request->input('from_partai') : null;
    $toPartai    = $request->filled('to_partai')   ? (int) $request->input('to_partai')   : null;

    // normalisasi range kalau user kebalik
    if (!is_null($fromPartai) && !is_null($toPartai) && $fromPartai > $toPartai) {
        [$fromPartai, $toPartai] = [$toPartai, $fromPartai];
    }
    $hasRange = !is_null($fromPartai) || !is_null($toPartai);

    $query = \App\Models\LocalSeniMatch::query();

    if ($tournamentName) {
        $query->where('tournament_name', $tournamentName);
    }

    // ✅ Filter satu arena kalau dikirim ?arena_name=...
    if ($arenaFilter !== '') {
        $query->where('arena_name', $arenaFilter);
    }

    // Khusus S E N I
    $query->whereIn('match_type', ['seni_tunggal', 'seni_ganda', 'seni_regu', 'solo_kreatif']);

    // ✅ Terapkan filter range partai di level DB
    if ($hasRange) {
        // singkirkan partai tanpa nomor (null/empty/non-numeric akan CAST jadi 0; paksa exclude)
        $query->whereRaw("NULLIF(TRIM(match_order), '') IS NOT NULL")
              ->whereRaw("CAST(match_order AS UNSIGNED) > 0");

        if (!is_null($fromPartai)) {
            $query->whereRaw('CAST(match_order AS UNSIGNED) >= ?', [$fromPartai]);
        }
        if (!is_null($toPartai)) {
            $query->whereRaw('CAST(match_order AS UNSIGNED) <= ?', [$toPartai]);
        }
    }

    // Urut dasar by match_order numeric ASC
    $matches = $query->orderBy(\DB::raw('CAST(match_order AS UNSIGNED)'))->get();

    // Map cepat: local_match_id => match_order (untuk replace "TBD" → "Pemenang Partai No #X")
    $matchOrderById = $matches->pluck('match_order', 'id')
        ->map(fn($o) => is_null($o) ? null : (int) $o)
        ->toArray();

    // Helper "TBD" (bersihkan zero width)
    $isTbd = function (?string $name): bool {
        if ($name === null) return true;
        $clean = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}\x{2060}]/u', '', $name);
        $clean = strtoupper(trim($clean));
        return ($clean === 'TBD' || $clean === '' || $clean === '-');
    };

    // ====== Bangun struktur per A R E N A ======
    $groupedArena = [];

    foreach ($matches as $match) {
        $arena       = $match->arena_name ?: 'UNKNOWN ARENA';
        $category    = ucwords(strtolower(trim($match->category ?? '-')));
        $gender      = strtolower(trim($match->gender ?? '-'));
        $ageCategory = ucwords(strtolower(trim($match->age_category ?? 'Tanpa Usia')));
        $poolName    = trim($match->pool_name ?? '-');

        $groupKey = $category . '|' . $gender . '|' . $ageCategory;

        // Init arena bucket
        if (!isset($groupedArena[$arena])) {
            $groupedArena[$arena] = [];
        }

        // Init kategori-gender-usia
        if (!isset($groupedArena[$arena][$groupKey])) {
            $groupedArena[$arena][$groupKey] = [
                'category'       => $category,
                'gender'         => $gender,
                'age_categories' => []
            ];
        }

        // Init usia
        if (!isset($groupedArena[$arena][$groupKey]['age_categories'][$ageCategory])) {
            $groupedArena[$arena][$groupKey]['age_categories'][$ageCategory] = [
                'age_category' => $ageCategory,
                'pools'        => []
            ];
        }

        // Init pool
        if (!isset($groupedArena[$arena][$groupKey]['age_categories'][$ageCategory]['pools'][$poolName])) {
            $groupedArena[$arena][$groupKey]['age_categories'][$ageCategory]['pools'][$poolName] = [
                'name'    => $poolName,
                'matches' => []
            ];
        }

        // Push match (ikut battle fields)
        $groupedArena[$arena][$groupKey]['age_categories'][$ageCategory]['pools'][$poolName]['matches'][] = [
            'id'                   => (int) $match->id,
            'match_order'          => is_null($match->match_order) ? null : (int) $match->match_order,
            'match_type'           => $match->match_type,
            'contingent'           => ['name' => $match->contingent_name],
            'final_score'          => $match->final_score,
            'medal'                => $match->medal,
            'status'               => $match->status,

            'team_member1'         => ['name' => $match->participant_1],
            'team_member2'         => ['name' => $match->participant_2],
            'team_member3'         => ['name' => $match->participant_3],

            'pool'                 => ['age_category' => ['name' => $ageCategory]],

            // battle fields
            'mode'                 => $match->mode,                 // 'default' | 'battle' | null
            'battle_group'         => $match->battle_group,         // int|null
            'round'                => is_null($match->round) ? null : (int) $match->round,
            'round_label'          => $match->round_label,
            'round_priority'       => is_null($match->round_priority) ? null : (int) $match->round_priority,
            'corner'               => $match->corner,               // 'blue'|'red'|null
            'winner_corner'        => $match->winner_corner,        // 'blue'|'red'|null
            'parent_match_red_id'  => $match->parent_match_red_id,
            'parent_match_blue_id' => $match->parent_match_blue_id,
        ];
    }

    // ====== Post-process: urutan & grouping per pool (samakan dengan index()) ======
    $finalResult = [];

    foreach ($groupedArena as $arena => $groupedMap) {
        $groupedList = [];

        foreach ($groupedMap as $groupKey => $group) {
            $ageCategories = [];

            foreach ($group['age_categories'] as $ageCategory => $ageData) {
                $pools = [];

                foreach ($ageData['pools'] as $pool) {
                    $rows = $pool['matches'];

                    // sort by match_order ASC (null di akhir)
                    usort($rows, function ($a, $b) {
                        $ao = $a['match_order'] ?? PHP_INT_MAX;
                        $bo = $b['match_order'] ?? PHP_INT_MAX;
                        if ($ao === $bo) return 0;
                        return $ao <=> $bo;
                    });

                    // Kelompokkan per GRUP: battle_group jika ada, fallback match_order
                    $groupsMap = [];
                    foreach ($rows as $idx => $row) {
                        $key = !is_null($row['battle_group'])
                            ? 'G' . $row['battle_group']
                            : (!is_null($row['match_order']) ? 'O' . $row['match_order'] : 'X' . $idx);
                        $groupsMap[$key][] = $row;
                    }

                    // Sort grup by angka pada key
                    $groupKeys = array_keys($groupsMap);
                    usort($groupKeys, function ($a, $b) {
                        $na = (int) substr($a, 1);
                        $nb = (int) substr($b, 1);
                        return $na <=> $nb;
                    });

                    // Rekonstruksi rows: fallback battle info + replace TBD + BLUE→RED
                    $finalRows = [];
                    foreach ($groupKeys as $gkey) {
                        $arr = $groupsMap[$gkey];

                        foreach ($arr as $i => &$r) {
                            // isi battle_group dari key bila null
                            if (is_null($r['battle_group'])) {
                                $r['battle_group'] = (int) substr($gkey, 1);
                            }

                            // fallback corner (BLUE dulu, RED kedua)
                            $corner = strtolower((string) ($r['corner'] ?? ''));
                            if ($corner !== 'blue' && $corner !== 'red') {
                                if ($i === 0)      { $r['corner'] = 'blue'; $corner = 'blue'; }
                                elseif ($i === 1)  { $r['corner'] = 'red';  $corner = 'red';  }
                            }

                            // mode battle bila ada 2 baris dalam grup
                            if (empty($r['mode']) && count($arr) >= 2) {
                                $r['mode'] = 'battle';
                            }

                            // Replace "TBD" → "Pemenang Partai No #X" berdasar parent
                            $currName = $r['contingent']['name'] ?? null;
                            if ($isTbd($currName)) {
                                $parentId = null;
                                if ($corner === 'blue') {
                                    $parentId = $r['parent_match_blue_id'] ?? null;
                                } elseif ($corner === 'red') {
                                    $parentId = $r['parent_match_red_id'] ?? null;
                                }
                                if ($parentId && isset($matchOrderById[$parentId]) && $matchOrderById[$parentId] !== null) {
                                    $r['contingent']['name'] = 'Pemenang Partai No #' . (int) $matchOrderById[$parentId];
                                } else {
                                    $r['contingent']['name'] = 'Pemenang Partai Sebelumnya';
                                }
                            }
                        }
                        unset($r);

                        // Urutkan isi grup: BLUE → RED → lainnya (stabil)
                        usort($arr, function ($a, $b) {
                            $rank = function ($v) {
                                $s = strtolower((string) ($v ?? ''));
                                if ($s === 'blue') return 0;
                                if ($s === 'red')  return 1;
                                return 2;
                            };
                            $ra = $rank($a['corner']);
                            $rb = $rank($b['corner']);
                            if ($ra !== $rb) return $ra <=> $rb;
                            return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
                        });

                        foreach ($arr as $r) {
                            $finalRows[] = $r;
                        }
                    }

                    // Replace matches pool ini dengan hasil final
                    $pool['matches'] = $finalRows;
                    $pools[] = $pool;
                }

                $ageCategories[] = [
                    'age_category' => $ageCategory,
                    'pools'        => $pools
                ];
            }

            $groupedList[] = [
                'category'       => $group['category'],
                'gender'         => $group['gender'],
                'age_categories' => $ageCategories
            ];
        }

        // Simpan per arena
        $finalResult[$arena] = $groupedList;
    }

    return response()->json($finalResult);
}



  

public function exportSeniPdf(Request $request)
{
    $sessionArena   = session('arena_name');       // default scope (opsional)
    $matchType      = session('match_type');       // 'seni' | 'tanding'
    $tournamentName = session('tournament_name');  // wajibnya turnamen

    // ==== FILTER DARI QUERY ====
    $arenaName  = $request->filled('arena_name') ? trim((string)$request->input('arena_name')) : $sessionArena;
    $fromPartai = $request->filled('from_partai') ? (int)$request->input('from_partai') : null;
    $toPartai   = $request->filled('to_partai')   ? (int)$request->input('to_partai')   : null;

    if (!is_null($fromPartai) && !is_null($toPartai) && $fromPartai > $toPartai) {
        [$fromPartai, $toPartai] = [$toPartai, $fromPartai];
    }
    $hasRange = !is_null($fromPartai) || !is_null($toPartai);

    $query = \App\Models\LocalSeniMatch::query();

    if ($tournamentName) {
        $query->where('tournament_name', $tournamentName);
    }
    if (!empty($arenaName)) {
        $query->where('arena_name', $arenaName);
    }
    if ($matchType === 'seni') {
        $query->whereIn('match_type', ['seni_tunggal', 'seni_ganda', 'seni_regu', 'solo_kreatif']);
    }

    // ✅ Filter range partai di level DB (selaras FE)
    if ($hasRange) {
        $query->whereRaw("NULLIF(TRIM(match_order), '') IS NOT NULL")
              ->whereRaw("CAST(match_order AS UNSIGNED) > 0");

        if (!is_null($fromPartai)) {
            $query->whereRaw('CAST(match_order AS UNSIGNED) >= ?', [$fromPartai]);
        }
        if (!is_null($toPartai)) {
            $query->whereRaw('CAST(match_order AS UNSIGNED) <= ?', [$toPartai]);
        }
    }

    $matches = $query
        ->orderBy(\DB::raw('CAST(match_order AS UNSIGNED)'))
        ->orderBy('id')
        ->get();

    // Map cepat: local_match_id => match_order (untuk fallback penamaan pemenang parent)
    $matchOrderById = $matches->pluck('match_order', 'id')
        ->map(fn($o) => is_null($o) ? null : (int) $o)
        ->toArray();

    // helper: cek “TBD” (bersihin zero-width char juga)
    $isTbd = function (?string $name): bool {
        if ($name === null) return true;
        $clean = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}\x{2060}]/u', '', $name);
        $clean = strtoupper(trim($clean));
        return ($clean === 'TBD' || $clean === '' || $clean === '-');
    };

    // ===================== Grouping awal by arena/category/age/pool =====================
    $groupedArena = [];

    foreach ($matches as $match) {
        $arena       = $match->arena_name ?? 'UNKNOWN ARENA';
        $category    = ucwords(strtolower(trim($match->category ?? '-')));
        $gender      = strtolower(trim($match->gender ?? '-'));
        $ageCategory = ucwords(strtolower(trim($match->age_category ?? 'Tanpa Usia')));
        $poolName    = trim($match->pool_name ?? '-');

        $groupKey = $category . '|' . $gender . '|' . $ageCategory;

        if (!isset($groupedArena[$arena])) {
            $groupedArena[$arena] = [];
        }
        if (!isset($groupedArena[$arena][$groupKey])) {
            $groupedArena[$arena][$groupKey] = [
                'category'       => $category,
                'gender'         => $gender,
                'age_categories' => [],
            ];
        }
        if (!isset($groupedArena[$arena][$groupKey]['age_categories'][$ageCategory])) {
            $groupedArena[$arena][$groupKey]['age_categories'][$ageCategory] = [
                'age_category' => $ageCategory,
                'pools'        => [],
            ];
        }
        if (!isset($groupedArena[$arena][$groupKey]['age_categories'][$ageCategory]['pools'][$poolName])) {
            $groupedArena[$arena][$groupKey]['age_categories'][$ageCategory]['pools'][$poolName] = [
                'name'    => $poolName,
                'matches' => [],
            ];
        }

        // Simpan match + field battle (biar siap diurutkan pair-aware)
        $groupedArena[$arena][$groupKey]['age_categories'][$ageCategory]['pools'][$poolName]['matches'][] = [
            'id'                   => (int) $match->id,
            'match_order'          => is_null($match->match_order) ? null : (int) $match->match_order,
            'match_type'           => $match->match_type,
            'contingent'           => ['name' => $match->contingent_name],
            'final_score'          => $match->final_score,
            'medal'                => $match->medal,
            'status'               => $match->status,
            'team_member1'         => ['name' => $match->participant_1],
            'team_member2'         => ['name' => $match->participant_2],
            'team_member3'         => ['name' => $match->participant_3],
            'pool'                 => ['age_category' => ['name' => $ageCategory]],

            // battle-info (dipakai untuk pengurutan)
            'mode'                 => $match->mode,                 // 'default' | 'battle' | null
            'battle_group'         => $match->battle_group,         // int|null
            'round'                => is_null($match->round) ? null : (int) $match->round,
            'round_label'          => $match->round_label,
            'round_priority'       => is_null($match->round_priority) ? null : (int) $match->round_priority,
            'corner'               => $match->corner,               // 'blue' | 'red' | null
            'winner_corner'        => $match->winner_corner,        // 'blue' | 'red' | null
            'parent_match_red_id'  => $match->parent_match_red_id,  // local id|null
            'parent_match_blue_id' => $match->parent_match_blue_id, // local id|null
        ];
    }

    // ===================== Rekonstruksi final (battle paired & ordered) =====================
    $finalResult = [];

    foreach ($groupedArena as $arena => $groupedMap) {
        $groupedList = [];

        foreach ($groupedMap as $group) {
            $ageCategories = [];

            foreach ($group['age_categories'] as $ageCategory => $ageData) {
                $pools = [];

                foreach ($ageData['pools'] as $pool) {
                    $rows = $pool['matches'];

                    // Sort dasar by match_order ASC (null di akhir)
                    usort($rows, function ($a, $b) {
                        $ao = $a['match_order'] ?? PHP_INT_MAX;
                        $bo = $b['match_order'] ?? PHP_INT_MAX;
                        if ($ao === $bo) return 0;
                        return $ao <=> $bo;
                    });

                    // Kelompok per GRUP → battle_group atau fallback match_order
                    $groupsMap = [];
                    foreach ($rows as $idx => $row) {
                        $key = !is_null($row['battle_group'])
                            ? 'G' . $row['battle_group']
                            : (!is_null($row['match_order']) ? 'O' . $row['match_order'] : 'X' . $idx);
                        $groupsMap[$key][] = $row;
                    }

                    // Urutkan antar grup berdasarkan angka key
                    $groupKeys = array_keys($groupsMap);
                    usort($groupKeys, function ($a, $b) {
                        $na = (int) substr($a, 1);
                        $nb = (int) substr($b, 1);
                        return $na <=> $nb;
                    });

                    // Rekonstruksi rows final: BLUE → RED → lainnya, fallback corner/mode & replace TBD
                    $finalRows = [];
                    foreach ($groupKeys as $gkey) {
                        $arr = $groupsMap[$gkey];

                        foreach ($arr as $i => &$r) {
                            if (is_null($r['battle_group'])) {
                                $r['battle_group'] = (int) substr($gkey, 1);
                            }

                            $corner = strtolower((string) ($r['corner'] ?? ''));
                            if ($corner !== 'blue' && $corner !== 'red' && count($arr) >= 2) {
                                if     ($i === 0) { $r['corner'] = 'blue'; $corner = 'blue'; }
                                elseif ($i === 1) { $r['corner'] = 'red';  $corner = 'red';  }
                            }

                            if (empty($r['mode']) && count($arr) >= 2) {
                                $r['mode'] = 'battle';
                            }

                            $currName = $r['contingent']['name'] ?? null;
                            if ($isTbd($currName)) {
                                $parentId = null;
                                if ($corner === 'blue') {
                                    $parentId = $r['parent_match_blue_id'] ?? null;
                                } elseif ($corner === 'red') {
                                    $parentId = $r['parent_match_red_id'] ?? null;
                                }
                                if ($parentId && isset($matchOrderById[$parentId]) && $matchOrderById[$parentId] !== null) {
                                    $r['contingent']['name'] = 'Pemenang Partai No #' . (int) $matchOrderById[$parentId];
                                } else {
                                    $r['contingent']['name'] = 'Pemenang Partai Sebelumnya';
                                }
                            }
                        }
                        unset($r);

                        usort($arr, function ($a, $b) {
                            $rank = function ($v) {
                                $s = strtolower((string) ($v ?? ''));
                                if ($s === 'blue') return 0;
                                if ($s === 'red')  return 1;
                                return 2;
                            };
                            $ra = $rank($a['corner']);
                            $rb = $rank($b['corner']);
                            if ($ra !== $rb) return $ra <=> $rb;
                            return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
                        });

                        foreach ($arr as $r) {
                            $finalRows[] = $r;
                        }
                    }

                    $pool['matches'] = $finalRows;
                    $pools[] = $pool;
                }

                $ageCategories[] = [
                    'age_category' => $ageCategory,
                    'pools'        => $pools,
                ];
            }

            $groupedList[] = [
                'category'       => $group['category'],
                'gender'         => $group['gender'],
                'age_categories' => $ageCategories,
            ];
        }

        $finalResult[$arena] = $groupedList;
    }

    // ===== Dompdf TEMP & CACHE (fix imagepng permission) =====
    $dompdfTmp = storage_path('app/dompdf_tmp');
    if (!File::isDirectory($dompdfTmp)) {
        File::makeDirectory($dompdfTmp, 0775, true);
    }

    // ===== Generate PDF
    $pdf = Pdf::setOptions([
            'isRemoteEnabled' => true,      // aman untuk img/file local/remote
            'temp_dir'        => $dompdfTmp,
            'font_cache'      => $dompdfTmp,
        ])
        ->loadView('exports.seni-matches', [
            'data'         => $finalResult,
            'tournament'   => $tournamentName,
            // (opsional) kirim balik filter untuk header template kalau perlu
            'arena_name'   => $arenaName,
            'from_partai'  => $fromPartai,
            'to_partai'    => $toPartai,
        ])
        ->setPaper('a4', 'portrait');

    // Nama file enak dibaca
    $suffixArena = $arenaName ? ('-'.$arenaName) : '';
    $suffixRange = ($hasRange)
        ? ('-partai'.($fromPartai ?? '').'-'.($toPartai ?? ''))
        : '';

    return $pdf->download("jadwal-pertandingan-seni{$suffixArena}{$suffixRange}.pdf");
}




public function setScoreManual(Request $request, $id)
{
    $request->validate([
        'score' => 'required|numeric|between:0,100.999999',
    ]);


    $match = LocalSeniMatch::findOrFail($id);

    if ($match->status === 'finished') {
        return response()->json(['message' => 'Pertandingan sudah selesai'], 400);
    }

    $match->final_score = $request->score;
    $match->status = 'finished'; // Opsional, tergantung kamu mau langsung anggap selesai atau tidak
    $match->save();

    return response()->json(['message' => 'Skor berhasil disimpan.']);
}


    public function setMedal(Request $request, $id)
    {
        $validated = $request->validate([
            'medal' => 'required|in:emas,perak,perunggu'
        ]);

        $match = LocalSeniMatch::find($id);

        if (! $match) {
            return response()->json([
                'message' => 'Pertandingan tidak ditemukan.'
            ], 404);
        }

        $match->medal = $validated['medal'];
        $match->save();

        return response()->json([
            'message' => 'Medali berhasil disimpan.',
            'data' => [
                'id' => $match->id,
                'medal' => $match->medal,
            ]
        ]);
    }










    public function index_gokil(Request $request)
    {
        $arenaName = session('arena_name');
        $matchType = session('match_type'); // 'seni' atau 'tanding'

        $query = \App\Models\LocalSeniMatch::query();

        if ($arenaName) {
            $query->where('arena_name', $arenaName);
        }

        /*if ($matchType) {
            $query->where('match_type', 'like', 'seni_%');
        }*/

        if ($matchType === 'seni') {
            $query->whereIn('match_type', ['seni_tunggal', 'seni_ganda', 'seni_regu', 'solo_kreatif']);
        }


        // Urutkan berdasarkan match_order sebagai angka
        $matches = $query->orderBy(DB::raw('CAST(match_order AS UNSIGNED)'))->get();

        // Format: [group_key => [first_order, group_data]]
        $groupedMap = [];

        foreach ($matches as $match) {
            $groupKey = $match->category . '|' . $match->gender . '|' . ($match->age_category ?? '-');
            $poolName = $match->pool_name;

            if (!isset($groupedMap[$groupKey])) {
                $groupedMap[$groupKey] = [
                    'first_order' => (int)$match->match_order,
                    'data' => [
                        'category' => $match->category,
                        'gender' => $match->gender,
                        'age_categories' => []
                    ]
                ];
            }

            $group = &$groupedMap[$groupKey]['data'];

            if (!isset($group['age_categories'][$match->age_category])) {
                $group['age_categories'][$match->age_category] = [
                    'age_category' => $match->age_category,
                    'pools' => []
                ];
            }

            $age = &$group['age_categories'][$match->age_category];

            if (!isset($age['pools'][$poolName])) {
                $age['pools'][$poolName] = [
                    'name' => $poolName,
                    'matches' => []
                ];
            }

            $age['pools'][$poolName]['matches'][] = [
                'id' => $match->id,
                'match_order' => (int)$match->match_order,
                'match_type' => $match->match_type,
                'contingent' => ['name' => $match->contingent_name],
                'final_score' => $match->final_score,
                'status' => $match->status,
                'team_member1' => ['name' => $match->participant_1],
                'team_member2' => ['name' => $match->participant_2],
                'team_member3' => ['name' => $match->participant_3],
                'pool' => [
                    'age_category' => ['name' => $match->age_category ?? '-']
                ]
            ];
        }

        // Urutkan berdasarkan match_order pertama dari tiap group
        usort($groupedMap, fn($a, $b) => $a['first_order'] <=> $b['first_order']);

        // Build result
        $result = [];

        foreach ($groupedMap as $item) {
            $group = $item['data'];
            $ageCategories = [];

            foreach ($group['age_categories'] as $ac) {
                // Urutkan match berdasarkan match_order di dalam pool
                foreach ($ac['pools'] as &$pool) {
                    usort($pool['matches'], fn($a, $b) => $a['match_order'] <=> $b['match_order']);
                }

                $ac['pools'] = array_values($ac['pools']);
                $ageCategories[] = $ac;
            }

            $result[] = [
                'category' => $group['category'],
                'gender' => $group['gender'],
                'age_categories' => $ageCategories
            ];
        }

        return response()->json($result);
    }









  public function index_backup(Request $request)
{
    $arenaName = session('arena_name');
    $matchType = session('match_type'); // 'seni' atau 'tanding'

    $query = \App\Models\LocalSeniMatch::query();

    if ($arenaName) {
        $query->where('arena_name', $arenaName);
    }

    if ($matchType) {
        $query->where('match_type', 'like', 'seni_%');
    }

    // Ambil semua match dan urutkan global
    $matches = $query->orderBy('match_order')->get();

    // Format: [group_key => [first_order, group_data]]
    $groupedMap = [];

    foreach ($matches as $match) {
        $groupKey = $match->category . '|' . $match->gender . '|' . ($match->age_category ?? '-');
        $poolName = $match->pool_name;

        if (!isset($groupedMap[$groupKey])) {
            $groupedMap[$groupKey] = [
                'first_order' => $match->match_order,
                'data' => [
                    'category' => $match->category,
                    'gender' => $match->gender,
                    'age_categories' => []
                ]
            ];
        }

        $group = &$groupedMap[$groupKey]['data'];

        if (!isset($group['age_categories'][$match->age_category])) {
            $group['age_categories'][$match->age_category] = [
                'age_category' => $match->age_category,
                'pools' => []
            ];
        }

        $age = &$group['age_categories'][$match->age_category];

        if (!isset($age['pools'][$poolName])) {
            $age['pools'][$poolName] = [
                'name' => $poolName,
                'matches' => []
            ];
        }

        $age['pools'][$poolName]['matches'][] = [
            'id' => $match->id,
            'match_order' => $match->match_order,
            'match_type' => $match->match_type,
            'contingent' => ['name' => $match->contingent_name],
            'final_score' => $match->final_score,
            'status' => $match->status,
            'team_member1' => ['name' => $match->participant_1],
            'team_member2' => ['name' => $match->participant_2],
            'team_member3' => ['name' => $match->participant_3],
            'pool' => [
                'age_category' => ['name' => $match->age_category ?? '-']
            ]
        ];
    }

    // Urutkan berdasarkan match_order pertama dari tiap group
    usort($groupedMap, fn($a, $b) => $a['first_order'] <=> $b['first_order']);

    // Build result
    $result = [];

    foreach ($groupedMap as $item) {
        $group = $item['data'];

        // Reindex and sort inside
        $ageCategories = [];

        foreach ($group['age_categories'] as $ac) {
            // Urutkan matches di dalam tiap pool
            foreach ($ac['pools'] as &$pool) {
                usort($pool['matches'], fn($a, $b) => $a['match_order'] <=> $b['match_order']);
            }

            $ac['pools'] = array_values($ac['pools']);
            $ageCategories[] = $ac;
        }

        $result[] = [
            'category' => $group['category'],
            'gender' => $group['gender'],
            'age_categories' => $ageCategories
        ];
    }

    return response()->json($result);
}














    public function index___asli(Request $request)
    {
        $arenaName = session('arena_name');
        $matchType = session('match_type'); // 'seni' atau 'tanding'

        $query = \App\Models\LocalSeniMatch::query();

        if ($arenaName) {
            $query->where('arena_name', $arenaName);
        }

        if ($matchType) {
            $query->where('match_type', 'like', "seni_%"); // karena match_type bisa seni_tunggal, seni_ganda, dll
        }

        $matches = $query->orderBy('match_date')
            ->orderBy('match_time')
            ->get();

        $grouped = $matches->groupBy([
            fn ($match) => $match->category,
            fn ($match) => $match->gender,
            fn ($match) => $match->pool_name,
        ]);

        $result = [];

        foreach ($grouped as $category => $byGender) {
            foreach ($byGender as $gender => $byPool) {
                $groupData = [
                    'category' => $category,
                    'gender' => $gender,
                    'pools' => []
                ];

                $sortedPools = collect($byPool)->sortKeys();

                foreach ($sortedPools as $poolName => $matchesInPool) {
                    $matchesInPool = $matchesInPool->sortBy('match_order');

                    $poolData = [
                        'name' => $poolName,
                        'matches' => [],
                    ];

                    foreach ($matchesInPool as $match) {
                        $poolData['matches'][] = [
                            'id' => $match->id,
                            'match_order' => $match->match_order,
                            'match_type' => $match->match_type,
                            'contingent' => [
                                'name' => $match->contingent_name
                            ],
                            'final_score' => $match->final_score,
                            'status' => $match->status,
                            'team_member1' => ['name' => $match->participant_1],
                            'team_member2' => ['name' => $match->participant_2],
                            'team_member3' => ['name' => $match->participant_3],
                            'pool' => [
                                'age_category' => [
                                    'name' => $match->age_category ?? '-'
                                ]
                            ]
                        ];
                    }

                    $groupData['pools'][] = $poolData;
                }

                $result[] = $groupData;
            }
        }

        return response()->json($result);
    }

   public function getBattleResults(Request $request, $battle_group)
    {
        $tournament = $request->query('tournament');
        $arena      = $request->query('arena');

        // Ambil semua match dalam battle_group
        $query = \App\Models\LocalSeniMatch::query()
            ->where('battle_group', $battle_group);

        if (!empty($tournament)) {
            $query->where('tournament_name', $tournament);
        }
        if (!empty($arena)) {
            $query->where('arena_name', $arena);
        }

        $matches = $query->get();

        if ($matches->isEmpty()) {
            return response()->json([
                'message'       => 'Battle group tidak ditemukan atau belum ada match.',
                'battle_group'  => $battle_group,
            ], 404);
        }

        // --- helper: ambil daftar nama peserta dari participant_1..3 (fallback ke participant_name bila ada) ---
        $extractMembers = function ($m) {
            $names = [];
            foreach (['participant_1', 'participant_2', 'participant_3'] as $col) {
                $val = $m->{$col} ?? null;
                if (is_string($val)) {
                    $val = trim($val);
                    if ($val !== '') $names[] = $val;
                }
            }

            // Fallback: jika tetap kosong, coba pecah participant_name (bisa "A, B, C" atau "A|B|C")
            if (empty($names) && !empty($m->participant_name)) {
                $parts = array_map('trim', preg_split('/,|\|/', $m->participant_name));
                $names = array_values(array_filter($parts, fn ($x) => $x !== ''));
            }

            return $names;
        };

        // --- helper: format blok participant untuk response ---
        $buildParticipant = function (\App\Models\LocalSeniMatch $m) use ($extractMembers) {
            // total penalty
            $totalPenalty = \App\Models\LocalSeniPenalties::where('local_match_id', $m->id)
                ->sum('penalty_value');

            $members = $extractMembers($m);
            $joined  = implode(', ', $members);

            // gunakan contingent_name sesuai skema DB
            $contingentName = $m->contingent_name ?? '-';

            return [
                'match_id'             => $m->id,
                'contingent'           => $contingentName,                     // ✅ nama kontingen
                'participants'         => $members,                            // ✅ array nama peserta
                'participants_joined'  => $joined,                             // ✅ "A, B, C"
                'display_name'         => trim(($contingentName ?: '-') . ' — ' . ($joined ?: '-')),
                'corner'               => strtolower((string)($m->corner ?? '')),
                'performance_time'     => (int)($m->duration ?? 0),            // detik
                'penalty'              => (float)$totalPenalty,
                'winning_point'        => isset($m->final_score)
                                ? number_format((float)$m->final_score, 6, '.', '')
                                : null,
                'status'               => $m->status,
                'winning_corner'       => strtolower((string)($m->winner_corner ?? '')),
            ];
        };

        // Cari berdasarkan corner kalau sudah diset
        $blueMatch = $matches->first(fn ($m) => strtolower((string)$m->corner) === 'blue');
        $redMatch  = $matches->first(fn ($m) => strtolower((string)$m->corner) === 'red');

        // Fallback: kalau belum ada corner, ambil top-2 by final_score
        if (!$blueMatch || !$redMatch) {
            $top = $matches
                ->sortByDesc(fn ($m) => $m->final_score ?? -INF)
                ->values();

            if (!$blueMatch && isset($top[0])) $blueMatch = $top[0];
            if (!$redMatch  && isset($top[1])) $redMatch  = $top[1];
        }

        return response()->json([
            'battle_group' => $battle_group,
            'filters' => [
                'tournament' => $tournament,
                'arena'      => $arena,
            ],
            'participants' => [
                'blue' => $blueMatch ? $buildParticipant($blueMatch) : null,
                'red'  => $redMatch  ? $buildParticipant($redMatch)  : null,
            ],
            'summary' => [
                'total_matches' => $matches->count(),
                'all_finished'  => $matches->every(fn ($m) => $m->status === 'finished'),
            ],
        ]);
    }

    public function show($id)
    {
        $match = LocalSeniMatch::findOrFail($id);

        return response()->json([
            'id' => $match->id,
            'tournament_name' => $match->tournament_name,
            'arena_name' => $match->arena_name,
            'match_order' => $match->match_order,
            'pool_name' => $match->pool_name,
            'match_type' => $match->match_type,
            'category' => $match->category,
            'age_category' => $match->age_category,
            'status' => $match->status,
            'contingent' => $match->contingent_name,
            'final_score' => $match->final_score,
            'gender' => $match->gender,
            'is_display_timer' => $match->is_display_timer,
            'corner' => $match->corner,
            'mode' => $match->mode,
            'battle_group' => $match->battle_group,
            'team_members' => array_filter([
                $match->participant_1,
                $match->participant_2,
                $match->participant_3,
            ]),
        ]);
    }

    public function groupContestants(\App\Models\LocalSeniMatch $match)
    {
        // Ambil semua entry di battle_group yang sama
        $q = \App\Models\LocalSeniMatch::query()
            ->where('mode', 'battle')
            //->where('tournament_id', $match->tournament_id)
           // ->where('match_category_id', $match->match_category_id)
            //->where('age_category_id', $match->age_category_id)
            //->where('gender', $match->gender)
            ->when($match->pool_id ?? null, fn($qq) => $qq->where('pool_id', $match->pool_id))
            ->where('battle_group', $match->battle_group)
            // opsional: exclude yg sudah jelas selesai total/DQ kalau ga relevan
            //->whereNotIn('status', ['canceled'])
            ->orderBy('id');

        $rows = $q->get();

        // Bentuk kontestan per entry (tunggal/ganda/regu)
        $contestants = [];
        foreach ($rows as $row) {
            // Ambil nama tampil: tunggal (participant_1), ganda/regu gabung
            $names = array_values(array_filter([
                $row->participant_1 ?? null,
                $row->participant_2 ?? null,
                $row->participant_3 ?? null,
            ], fn($v) => filled($v)));

            // Fallback kalau backend masih kirim team_members (array json)
            if (empty($names) && is_array($row->team_members ?? null)) {
                $names = array_values(array_filter($row->team_members));
            }

            $displayName = $names ? implode(' & ', $names) : ($row->display_name ?? 'Peserta');

            $contestants[] = [
                'match_id'         => $row->id,
                'display_name'     => $displayName,
                'contingent_name'  => $row->contingent_name ?? $row->contingent,
                'corner'           => in_array($row->corner, ['blue','red'], true) ? $row->corner : null,
                'status'           => $row->status,       // buat FE filter opsional
                'is_present'       => $row->is_present ?? null, // kalau punya
            ];
        }

        // Prefer 2 peserta yang paling relevan:
        // 1) punya corner eksplisit; 2) status ready/ongoing; 3) sisanya
        $priority = ['ready','ongoing','scheduled','finished'];
        usort($contestants, function($a,$b) use ($priority) {
            // has corner desc
            $ac = $a['corner'] ? 1 : 0; $bc = $b['corner'] ? 1 : 0;
            if ($ac !== $bc) return $bc <=> $ac;

            // status priority
            $ai = array_search($a['status'] ?? '', $priority, true);
            $bi = array_search($b['status'] ?? '', $priority, true);
            $ai = $ai === false ? 99 : $ai;
            $bi = $bi === false ? 99 : $bi;
            if ($ai !== $bi) return $ai <=> $bi;

            // id asc
            return ($a['match_id'] <=> $b['match_id']);
        });

        // Ambil maksimal 2
        $contestants = array_slice($contestants, 0, 2);

        // Fallback corner jika dua-duanya belum punya
        if (count($contestants) === 2) {
            if (!$contestants[0]['corner'] && !$contestants[1]['corner']) {
                $contestants[0]['corner'] = 'blue';
                $contestants[1]['corner'] = 'red';
            } elseif (!$contestants[0]['corner'] && $contestants[1]['corner']) {
                $contestants[0]['corner'] = $contestants[1]['corner'] === 'blue' ? 'red' : 'blue';
            } elseif ($contestants[0]['corner'] && !$contestants[1]['corner']) {
                $contestants[1]['corner'] = $contestants[0]['corner'] === 'blue' ? 'red' : 'blue';
            }
        }

        return response()->json([
            'match_id'    => $match->id,
            'battle_group'=> $match->battle_group,
            'contestants' => $contestants,
        ]);
    }

    public function setWinner(Request $request, LocalSeniMatch $match)
{
    // 1) Validasi input
    $data = $request->validate([
        'winner_match_id' => ['required','integer','exists:local_seni_matches,id'],
        'reason'          => ['required', Rule::in(['mutlak','undur_diri','diskualifikasi'])],
    ]);

    // 2) Ambil semua entry battle group yang sama
    $group = LocalSeniMatch::query()
        ->where('mode', 'battle')
        ->where('battle_group', $match->battle_group)
        ->orderBy('id')
        ->get();

    if ($group->count() < 2) {
        return response()->json(['message' => 'Peserta belum lengkap (minimal 2) di battle group ini.'], 422);
    }

    $winner = $group->firstWhere('id', (int) $data['winner_match_id']);
    if (!$winner) {
        return response()->json(['message' => 'winner_match_id bukan bagian dari battle group ini.'], 422);
    }

    // Ambil lawan: prioritas corner berbeda → fallback selain winner
    $loser = $group->first(fn($m) => $m->id !== $winner->id && (!isset($winner->corner) || $m->corner !== $winner->corner))
          ?? $group->first(fn($m) => $m->id !== $winner->id);

    if (!$loser) {
        return response()->json(['message' => 'Tidak ditemukan pasangan lawan di battle group ini.'], 422);
    }

    // 3) Label alasan
    $reasonLabel = match ($data['reason']) {
        'mutlak'         => 'Menang Mutlak',
        'undur_diri'     => 'Menang Undur Diri',
        'diskualifikasi' => 'Menang Diskualifikasi',
        default          => ucfirst(str_replace('_',' ', $data['reason'])),
    };

    // 4) Nama pemenang & kontingen (untuk display)
    $winnerName = implode(' & ', array_values(array_filter([
        $winner->participant_1, $winner->participant_2, $winner->participant_3
    ], fn($v) => filled($v)))) ?: ($winner->display_name ?? 'Peserta');

    $contingent = $winner->contingent_name ?? ($winner->contingent ?? '-');

    DB::beginTransaction();
    try {
        // 5) Tandai keduanya finished + broadcast sinkronisasi timer
        foreach ([$winner, $loser] as $m) {
            if ($m->status !== 'finished') {
                $m->status   = 'finished';
                $m->end_time = now();
            }
            $m->save();
            broadcast(new SeniTimerFinished($m))->toOthers();
        }

        // 6) Tentukan winnerCorner (untuk promote & UI)
        $winnerCorner = in_array($winner->corner, ['blue','red'], true)
            ? $winner->corner
            : (in_array($loser->corner, ['blue','red'], true) ? ($loser->corner === 'blue' ? 'red' : 'blue') : 'blue');

        // Persist winner_corner & winner_reason jika kolom ada
        if (Schema::hasColumn($winner->getTable(), 'winner_corner')) {
            $winner->winner_corner = $winnerCorner;
        }
        if (Schema::hasColumn($winner->getTable(), 'winner_reason')) {
            $winner->winner_reason = $data['reason']; // 'mutlak'|'undur_diri'|'diskualifikasi'
        }
        $winner->save();

        if (Schema::hasColumn($loser->getTable(), 'winner_reason')) {
            $loser->winner_reason = $data['reason'];
        }
        $loser->save();

        // 7) Medal assignment (final / bronze)
        $roundLabel = strtolower((string)($match->round_label ?? ''));
        $setMedal = function (?LocalSeniMatch $m, ?string $medal) {
            if (!$m) return;
            $m->medal = $medal; // 'emas'|'perak'|'perunggu'|null
            $m->save();
        };
        $isFinal  = (str_contains($roundLabel, 'final') && !str_contains($roundLabel, 'semi') && !str_contains($roundLabel, '3') && !str_contains($roundLabel, 'bronze'));
        $isBronze = (str_contains($roundLabel, 'bronze') || str_contains($roundLabel, '3'));
        if ($isFinal) {
            $setMedal($winner, 'emas');
            $setMedal($loser,  'perak');
        } elseif ($isBronze) {
            $setMedal($winner, 'perunggu');
            $setMedal($loser,  null);
        }

        // 8) PROMOTE → cari parent berdasarkan SEMUA id di battle group, RE-LINK & OVERWRITE SELALU
        $groupIds = $group->pluck('id')->all();

        $parents = LocalSeniMatch::query()
            ->where(function ($q) use ($groupIds) {
                $q->whereIn('parent_match_red_id',  $groupIds)
                  ->orWhereIn('parent_match_blue_id', $groupIds);
            })
            ->where('tournament_name', $winner->tournament_name)
            ->get();

        $promotedParentIds = [];

        if ($parents->isEmpty()) {
            \Log::info('ℹ️ [setWinner] Tidak ada parent match ditemukan untuk group', [
                'group_ids' => $groupIds,
                'arena'     => $winner->arena_name,
                'tour'      => $winner->tournament_name,
                'group'     => $winner->battle_group,
            ]);
        } else {
            foreach ($parents as $parent) {
                // Tentukan slot parent yang terkait group ini
                $slot = null;
                if (in_array($parent->parent_match_red_id, $groupIds, true))  $slot = 'red';
                if (in_array($parent->parent_match_blue_id, $groupIds, true)) $slot = $slot ?: 'blue'; // prioritas red jika dua-duanya match

                if (!$slot) {
                    \Log::warning('⚠️ [setWinner] Parent terdeteksi tapi slot tidak jelas', [
                        'parent_id'=>$parent->id, 'group_ids'=>$groupIds
                    ]);
                    continue;
                }

                // RE-LINK: pastikan parent menunjuk ke pemenang pada slot tersebut
                $beforeLink = [
                    'red'  => $parent->parent_match_red_id,
                    'blue' => $parent->parent_match_blue_id,
                ];
                if ($slot === 'red'  && (int)$parent->parent_match_red_id  !== (int)$winner->id) $parent->parent_match_red_id  = $winner->id;
                if ($slot === 'blue' && (int)$parent->parent_match_blue_id !== (int)$winner->id) $parent->parent_match_blue_id = $winner->id;

                // Sanitizer karakter tak terlihat
                $stripInv = function (?string $s): ?string {
                    if ($s === null) return null;
                    return preg_replace('/[\x{200B}\x{200C}\x{200D}\x{2060}]/u', '', $s);
                };

                // Simpan keadaan lama (audit)
                $alreadyFilled = [
                    'contingent_name' => $parent->contingent_name,
                    'participant_1'   => $parent->participant_1,
                    'participant_2'   => $parent->participant_2,
                    'participant_3'   => $parent->participant_3,
                    'corner'          => $parent->corner,
                ];

                // OVERWRITE SELALU dengan data pemenang
                $parent->contingent_name = $stripInv($winner->contingent_name ?? $winner->contingent ?? null);
                $parent->participant_1   = $stripInv($winner->participant_1 ?? null);
                $parent->participant_2   = $stripInv($winner->participant_2 ?? null);
                $parent->participant_3   = $stripInv($winner->participant_3 ?? null);
                $parent->corner          = $slot;

                // Reset supaya siap dimainkan
                $parent->final_score = null;
                $parent->duration    = null;
                $parent->start_time  = null;
                $parent->pause_time  = null;
                $parent->end_time    = null;

                $parent->save();
                $promotedParentIds[] = $parent->id;

                \Log::info('✍️ [setWinner] Parent di-overwrite & relink', [
                    'parent_id'      => $parent->id,
                    'slot'           => $slot,
                    'before_link'    => $beforeLink,
                    'after_link'     => [
                        'red'  => $parent->parent_match_red_id,
                        'blue' => $parent->parent_match_blue_id,
                    ],
                    'was_filled'     => $alreadyFilled,
                    'now_contingent' => $parent->contingent_name,
                    'now_p1'         => $parent->participant_1,
                    'now_p2'         => $parent->participant_2,
                    'now_p3'         => $parent->participant_3,
                ]);
            }
        }

        // 9) Broadcast popup pemenang
        event(new SeniBattleWinnerAnnounced(
            $match->tournament_name,
            $match->arena_name,
            $match->battle_group,
            [
                'winner_name'  => $winnerName,
                'contingent'   => $contingent ?: '-',
                'corner'       => $winnerCorner,
                'reason'       => $data['reason'],
                'reason_label' => $reasonLabel,
                'match_id'     => $match->id,
                'round_label'  => $match->round_label ?? null,
            ]
        ));

        DB::commit();

        // 10) Jika seluruh match di group selesai → kirim event group result
        $groupMatches = LocalSeniMatch::where('battle_group', $match->battle_group)
            ->where('tournament_name', $match->tournament_name)
            ->get();

        $allFinished = $groupMatches->every(fn($m) => $m->status === 'finished');

        $winnersPayload = [];
        $resultUrl = null;

        if ($allFinished) {
            $eligible = $groupMatches;
            if (Schema::hasColumn($match->getTable(), 'disqualified')) {
                $eligible = $eligible->filter(fn($m) => (int)($m->disqualified ?? 0) !== 1);
            }

            $eligibleSorted = $eligible->sortByDesc(fn($m) => (float) ($m->final_score ?? 0))->values();

            $winnersPayload = $eligibleSorted->take(2)->map(function ($m) {
                $cont = $m->contingent_name ?? ($m->contingent ?? '-');
                $names = [];
                foreach (['participant_1','participant_2','participant_3'] as $col) {
                    $v = is_string($m->{$col} ?? null) ? trim($m->{$col}) : null;
                    if ($v !== null && $v !== '') $names[] = $v;
                }
                if (empty($names) && !empty($m->participant_name)) {
                    $parts = array_map('trim', preg_split('/,|\|/', $m->participant_name));
                    $names = array_values(array_filter($parts, fn($x)=>$x!==''));
                }
                $joined = implode(', ', $names);
                $pen = LocalSeniPenalties::where('local_match_id', $m->id)->sum('penalty_value');

                return [
                    'match_id'     => $m->id,
                    'corner'       => strtolower((string)$m->corner ?? ''),
                    'contingent'   => $cont ?: '-',
                    'participants' => $names,
                    'display_name' => trim(($cont ?: '-') . ' — ' . ($joined ?: '-')),
                    'final_score'  => number_format((float)($m->final_score ?? 0), 6, '.', ''),
                    'penalty'      => (float) $pen,
                    'duration'     => (int) ($m->duration ?? 0),
                    'medal'        => $m->medal ?? null,
                ];
            })->all();

            $resultUrl = url("/matches/seni/display-result-group/{$match->battle_group}");

            event(new SeniBattleGroupCompleted(
                $match->tournament_name,
                $match->arena_name,
                $match->battle_group,
                $winnersPayload,
                $resultUrl,
                $winnerCorner
            ));
        }

        // 11) Response sukses
        return response()->json([
            'success'               => true,
            'message'               => 'Pemenang diset.',
            'match_id'              => $match->id,
            'battle_group'          => $match->battle_group,
            'winner_entry'          => $winner->id,
            'loser_entry'           => $loser->id,
            'winner_name'           => $winnerName,
            'winner_corner'         => $winnerCorner,
            'reason'                => $data['reason'],
            'reason_label'          => $reasonLabel,
            'promoted_parent_ids'   => $promotedParentIds ?? [],
            'battle_group_completed'=> $allFinished,
            'winners'               => $winnersPayload,
            'result_url'            => $resultUrl,
        ]);

    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Gagal menyimpan pemenang',
            'error'   => $e->getMessage()
        ], 500);
    }
}




   public function disqualify($id, Request $request)
    {
        $match = \App\Models\LocalSeniMatch::findOrFail($id);

        $match->disqualified = 'yes';
        $match->disqualification_reason = $request->input('reason', 'Diskualifikasi oleh juri');
        $match->status = 'finished';
        $match->save();

        if ($match) {
            // ✅ Kirim status ke server pusat
            try {
                $client = new \GuzzleHttp\Client();

                $response = $client->post($this->live_server . '/api/update-seni-match-status', [
                    'json' => [
                        'remote_match_id' => $match->remote_match_id,
                        'status' => 'finished',
                        'final_score' => null
                    ],
                    'timeout' => 5,
                ]);

                \Log::info('✅ Status pertandingan seni dikirim ke server pusat', [
                    'remote_match_id' => $match->remote_match_id,
                    'status' => 'finished',
                    'http_code' => $response->getStatusCode()
                ]);
            } catch (\Throwable $e) {
                \Log::warning('⚠️ Gagal kirim status ke server pusat', [
                    'remote_match_id' => $match->remote_match_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // 🔁 Kirim broadcast sebagai match selesai
        broadcast(new SeniTimerFinished($match))->toOthers();

        return response()->json([
            'message' => 'Peserta telah didiskualifikasi',
            'match_id' => $match->id,
            'disqualified' => true
        ]);
    }

    public function getPoolWinners()
    {
        $matches = \App\Models\LocalSeniMatch::all();

        $grouped = $matches->groupBy(function ($match) {
            return $match->pool_name . '|' . $match->category . '|' . $match->gender . '|' . $match->age_category;
        });

        $result = [];

        foreach ($grouped as $key => $group) {
            [$poolName, $category, $gender, $ageCategory] = explode('|', $key);

            $participants = [];

            foreach ($group as $match) {
                $peserta = [
                    ['id' => $match->remote_team_member_1, 'name' => $match->participant_1, 'contingent' => $match->contingent_name],
                    ['id' => $match->remote_team_member_2, 'name' => $match->participant_2, 'contingent' => $match->contingent_name],
                    ['id' => $match->remote_team_member_3, 'name' => $match->participant_3, 'contingent' => $match->contingent_name],
                ];

                foreach ($peserta as $p) {
                    if ($p['id'] && !collect($participants)->contains('id', $p['id'])) {
                        $participants[] = $p;
                    }
                }
            }

            $result[] = [
                'pool_id' => null,
                'pool_name' => $poolName,
                'category' => $category,
                'match_type' => $group->first()->match_type,
                'gender' => $gender,
                'age_category' => $ageCategory,
                'participants' => $participants
            ];
        }

        return response()->json($result);
    }

   public function createPoolFinalMatch(Request $request)
{
    try {
        $request->validate([
            'winners' => 'required|array|min:1',
            'winners.*.member_id' => 'required|integer',
            'winners.*.category' => 'required|string',
            'winners.*.gender' => 'required|string',
            'winners.*.age_category' => 'required|string',
        ]);

        $winners = collect($request->winners);

        // Ambil match_order tertinggi global (pastikan tipe data INT atau cast dulu)
        $currentMaxOrder = DB::table('local_seni_matches')
            ->select(DB::raw('MAX(CAST(match_order AS UNSIGNED)) as max_order'))
            ->value('max_order') ?? 0;

        foreach ($winners as $index => $winner) {
            $category = $winner['category'];
            $gender = $winner['gender'];
            $ageCategory = $winner['age_category'];

            $matchTypeMap = [
                'Tunggal' => 'seni_tunggal',
                'Ganda' => 'seni_ganda',
                'Regu' => 'seni_regu',
                'Solo Kreatif' => 'solo_kreatif'
            ];

            $matchType = $matchTypeMap[$category] ?? 'seni_tunggal';
            $poolName = "Pool Final - $category - $ageCategory - $gender";

            $data = $this->getParticipantDataFromLocalMatch($winner['member_id']);

            if (!$data) continue;

            $arena = $data->arena_name ?? session('arena_name') ?? 'Arena Final';
            $tournament = $data->tournament_name ?? 'UNKNOWN TOURNAMENT';

            $currentMaxOrder++; // Increment sebelum insert

            DB::table('local_seni_matches')->insert([
                'remote_match_id' => null,
                'remote_contingent_id' => $data->remote_contingent_id,
                'remote_team_member_1' => $data->remote_team_member_1,
                'remote_team_member_2' => $data->remote_team_member_2,
                'remote_team_member_3' => $data->remote_team_member_3,

                'tournament_name' => $tournament,
                'arena_name' => $arena,
                'match_date' => now()->format('Y-m-d'),
                'match_time' => now()->addMinutes(30)->format('H:i:00'),
                'pool_name' => $poolName,
                'match_order' => $currentMaxOrder,

                'category' => $category,
                'match_type' => $matchType,
                'gender' => $gender,
                'contingent_name' => $data->contingent_name,

                'participant_1' => $data->participant_1,
                'participant_2' => $data->participant_2,
                'participant_3' => $data->participant_3,

                'age_category' => $ageCategory,
                'final_score' => null,
                'is_display_timer' => 0,
                'status' => 'not_started',
                'duration' => 180,
                'disqualified' => 'no',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Match berhasil dibuat.']);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Internal Server Error: ' . $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
        ], 500);
    }
}



private function getParticipantDataFromLocalMatch($memberId)
{
    return DB::table('local_seni_matches')
        ->where(function ($q) use ($memberId) {
            $q->where('remote_team_member_1', $memberId)
              ->orWhere('remote_team_member_2', $memberId)
              ->orWhere('remote_team_member_3', $memberId);
        })
        ->orderByDesc('id')
        ->first([
            'remote_contingent_id',
            'remote_team_member_1',
            'remote_team_member_2',
            'remote_team_member_3',
            'participant_1',
            'participant_2',
            'participant_3',
            'contingent_name',
            'arena_name',
            'tournament_name'
        ]);
}

















}


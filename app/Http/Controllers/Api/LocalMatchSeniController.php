<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Events\SeniMatchStarted;
use App\Events\SeniTimerStarted;
use App\Events\SeniTimerUpdated;
use App\Events\SeniTimerFinished;
use App\Models\LocalSeniMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;


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

    $matches = $query->orderBy(DB::raw('CAST(match_order AS UNSIGNED)'))->get();

    // Bangun map group berdasarkan kategori
    $groupedMap = [];
    $matchGroupMap = [];

    foreach ($matches as $match) {
        $category = ucwords(strtolower(trim($match->category ?? '-')));
        $gender = strtolower(trim($match->gender ?? '-'));
        $ageCategory = ucwords(strtolower(trim($match->age_category ?? 'Tanpa Usia')));
        $poolName = trim($match->pool_name ?? '-');

        $groupKey = $category . '|' . $gender . '|' . $ageCategory;
        $matchGroupMap[$match->id] = $groupKey;

        if (!isset($groupedMap[$groupKey])) {
            $groupedMap[$groupKey] = [
                'category' => $category,
                'gender' => $gender,
                'age_categories' => []
            ];
        }

        if (!isset($groupedMap[$groupKey]['age_categories'][$ageCategory])) {
            $groupedMap[$groupKey]['age_categories'][$ageCategory] = [
                'age_category' => $ageCategory,
                'pools' => []
            ];
        }

        if (!isset($groupedMap[$groupKey]['age_categories'][$ageCategory]['pools'][$poolName])) {
            $groupedMap[$groupKey]['age_categories'][$ageCategory]['pools'][$poolName] = [
                'name' => $poolName,
                'matches' => []
            ];
        }

        $groupedMap[$groupKey]['age_categories'][$ageCategory]['pools'][$poolName]['matches'][] = [
            'id' => $match->id,
            'match_order' => (int) $match->match_order,
            'match_type' => $match->match_type,
            'contingent' => ['name' => $match->contingent_name],
            'final_score' => $match->final_score,
            'status' => $match->status,
            'team_member1' => ['name' => $match->participant_1],
            'team_member2' => ['name' => $match->participant_2],
            'team_member3' => ['name' => $match->participant_3],
            'pool' => [
                'age_category' => ['name' => $ageCategory]
            ]
        ];
    }

    // Bikin response berdasarkan urutan match_order dari $matches
    $result = [];
    $used = [];

    foreach ($matches as $match) {
        $groupKey = $matchGroupMap[$match->id];

        if (in_array($groupKey, $used)) continue;
        $used[] = $groupKey;

        $group = $groupedMap[$groupKey];

        $ageCategories = [];

        foreach ($group['age_categories'] as $ageCategory => $ageData) {
            $pools = [];

            foreach ($ageData['pools'] as $pool) {
                usort($pool['matches'], fn($a, $b) => $a['match_order'] <=> $b['match_order']);
                $pools[] = $pool;
            }

            $ageCategories[] = [
                'age_category' => $ageCategory,
                'pools' => $pools
            ];
        }

        $result[] = [
            'category' => $group['category'],
            'gender' => $group['gender'],
            'age_categories' => $ageCategories
        ];
    }

    return response()->json($result);
}

    public function fetchMatchForAdmin(Request $request)
{
    $arenaName = session('arena_name');
    $matchType = session('match_type'); // 'seni' atau 'tanding'
    $tournamentName = session('tournament_name'); // ✅ ambil dari session

    $query = \App\Models\LocalSeniMatch::query();

    if ($arenaName) {
        $query->where('arena_name', $arenaName);
    }

    if ($tournamentName) {
        $query->where('tournament_name', $tournamentName);
    }

    if ($matchType === 'seni') {
        $query->whereIn('match_type', ['seni_tunggal', 'seni_ganda', 'seni_regu', 'solo_kreatif']);
    }

    $matches = $query->orderBy(DB::raw('CAST(match_order AS UNSIGNED)'))->get();

    $groupedArena = [];

    foreach ($matches as $match) {
        $arena = $match->arena_name ?? 'UNKNOWN ARENA';

        $category = ucwords(strtolower(trim($match->category ?? '-')));
        $gender = strtolower(trim($match->gender ?? '-'));
        $ageCategory = ucwords(strtolower(trim($match->age_category ?? 'Tanpa Usia')));
        $poolName = trim($match->pool_name ?? '-');

        $groupKey = $category . '|' . $gender . '|' . $ageCategory;

        if (!isset($groupedArena[$arena])) {
            $groupedArena[$arena] = [];
        }

        if (!isset($groupedArena[$arena][$groupKey])) {
            $groupedArena[$arena][$groupKey] = [
                'category' => $category,
                'gender' => $gender,
                'age_categories' => []
            ];
        }

        if (!isset($groupedArena[$arena][$groupKey]['age_categories'][$ageCategory])) {
            $groupedArena[$arena][$groupKey]['age_categories'][$ageCategory] = [
                'age_category' => $ageCategory,
                'pools' => []
            ];
        }

        if (!isset($groupedArena[$arena][$groupKey]['age_categories'][$ageCategory]['pools'][$poolName])) {
            $groupedArena[$arena][$groupKey]['age_categories'][$ageCategory]['pools'][$poolName] = [
                'name' => $poolName,
                'matches' => []
            ];
        }

        $groupedArena[$arena][$groupKey]['age_categories'][$ageCategory]['pools'][$poolName]['matches'][] = [
            'id' => $match->id,
            'match_order' => (int) $match->match_order,
            'match_type' => $match->match_type,
            'contingent' => ['name' => $match->contingent_name],
            'final_score' => $match->final_score,
            'medal' => $match->medal,
            'status' => $match->status,
            'team_member1' => ['name' => $match->participant_1],
            'team_member2' => ['name' => $match->participant_2],
            'team_member3' => ['name' => $match->participant_3],
            'pool' => [
                'age_category' => ['name' => $ageCategory]
            ]
        ];
    }

    // Final struktur response per arena
    $finalResult = [];

    foreach ($groupedArena as $arena => $groupedMap) {
        $groupedList = [];

        foreach ($groupedMap as $groupKey => $group) {
            $ageCategories = [];

            foreach ($group['age_categories'] as $ageCategory => $ageData) {
                $pools = [];

                foreach ($ageData['pools'] as $pool) {
                    usort($pool['matches'], fn($a, $b) => $a['match_order'] <=> $b['match_order']);
                    $pools[] = $pool;
                }

                $ageCategories[] = [
                    'age_category' => $ageCategory,
                    'pools' => $pools
                ];
            }

            $groupedList[] = [
                'category' => $group['category'],
                'gender' => $group['gender'],
                'age_categories' => $ageCategories
            ];
        }

        $finalResult[$arena] = $groupedList;
    }

    return response()->json($finalResult);
}

    public function exportSeniPdf(Request $request)
    {
        $arenaName = session('arena_name');
        $matchType = session('match_type');
        $tournamentName = session('tournament_name');

        $query = LocalSeniMatch::query();

        if ($arenaName) {
            $query->where('arena_name', $arenaName);
        }

        if ($tournamentName) {
            $query->where('tournament_name', $tournamentName);
        }

        if ($matchType === 'seni') {
            $query->whereIn('match_type', ['seni_tunggal', 'seni_ganda', 'seni_regu', 'solo_kreatif']);
        }

        $query->whereRaw('CAST(match_order AS UNSIGNED) >= ?', [89]);

$matches = $query
    ->orderByRaw('CAST(match_order AS UNSIGNED)')
    ->get();


        // Grouping
        $groupedArena = [];

        foreach ($matches as $match) {
            $arena = $match->arena_name ?? 'UNKNOWN ARENA';

            $category = ucwords(strtolower(trim($match->category ?? '-')));
            $gender = strtolower(trim($match->gender ?? '-'));
            $ageCategory = ucwords(strtolower(trim($match->age_category ?? 'Tanpa Usia')));
            $poolName = trim($match->pool_name ?? '-');

            $groupKey = $category . '|' . $gender . '|' . $ageCategory;

            if (!isset($groupedArena[$arena])) $groupedArena[$arena] = [];
            if (!isset($groupedArena[$arena][$groupKey])) {
                $groupedArena[$arena][$groupKey] = [
                    'category' => $category,
                    'gender' => $gender,
                    'age_categories' => []
                ];
            }

            if (!isset($groupedArena[$arena][$groupKey]['age_categories'][$ageCategory])) {
                $groupedArena[$arena][$groupKey]['age_categories'][$ageCategory] = [
                    'age_category' => $ageCategory,
                    'pools' => []
                ];
            }

            if (!isset($groupedArena[$arena][$groupKey]['age_categories'][$ageCategory]['pools'][$poolName])) {
                $groupedArena[$arena][$groupKey]['age_categories'][$ageCategory]['pools'][$poolName] = [
                    'name' => $poolName,
                    'matches' => []
                ];
            }

            $groupedArena[$arena][$groupKey]['age_categories'][$ageCategory]['pools'][$poolName]['matches'][] = [
                'id' => $match->id,
                'match_order' => (int) $match->match_order,
                'match_type' => $match->match_type,
                'contingent' => ['name' => $match->contingent_name],
                'final_score' => $match->final_score,
                'medal' => $match->medal,
                'status' => $match->status,
                'team_member1' => ['name' => $match->participant_1],
                'team_member2' => ['name' => $match->participant_2],
                'team_member3' => ['name' => $match->participant_3],
                'pool' => ['age_category' => ['name' => $ageCategory]]
            ];
        }

        // Final structure
        $finalResult = [];

        foreach ($groupedArena as $arena => $groupedMap) {
            $groupedList = [];

            foreach ($groupedMap as $group) {
                $ageCategories = [];

                foreach ($group['age_categories'] as $ageCategory => $ageData) {
                    $pools = [];

                    foreach ($ageData['pools'] as $pool) {
                        usort($pool['matches'], fn($a, $b) => $a['match_order'] <=> $b['match_order']);
                        $pools[] = $pool;
                    }

                    $ageCategories[] = [
                        'age_category' => $ageCategory,
                        'pools' => $pools
                    ];
                }

                $groupedList[] = [
                    'category' => $group['category'],
                    'gender' => $group['gender'],
                    'age_categories' => $ageCategories
                ];
            }

            $finalResult[$arena] = $groupedList;
        }

        // Generate PDF pakai Barryvdh\DomPDF\Facade\Pdf
        $pdf = Pdf::loadView('exports.seni-matches', [
            'data' => $finalResult,
            'tournament' => $tournamentName
        ])->setPaper('a4', 'portrait');

        return $pdf->download('jadwal-pertandingan-seni.pdf');
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
            'team_members' => array_filter([
                $match->participant_1,
                $match->participant_2,
                $match->participant_3,
            ]),
        ]);
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


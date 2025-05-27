<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Events\SeniMatchStarted;
use App\Events\SeniTimerStarted;
use App\Events\SeniTimerUpdated;
use App\Events\SeniTimerFinished;
use App\Models\LocalSeniMatch;
use Illuminate\Http\Request;

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

        $query = \App\Models\LocalSeniMatch::query();

        if ($arenaName) {
            $query->where('arena_name', $arenaName);
        }

        if ($matchType) {
            $query->where('match_type', 'like', 'seni_%');
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


}


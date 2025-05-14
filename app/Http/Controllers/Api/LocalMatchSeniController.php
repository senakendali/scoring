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
    public function index(Request $request)
    {
        $matches = \App\Models\LocalSeniMatch::orderBy('match_date')
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

                // 🔃 Urutkan nama pool (kunci) ASC
                $sortedPools = collect($byPool)->sortKeys();

                foreach ($sortedPools as $poolName => $matchesInPool) {
                    // 🔃 Urutkan pertandingan berdasarkan match_order
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

        // 🔁 Kirim broadcast sebagai match selesai
        broadcast(new SeniTimerFinished($match))->toOthers();

        return response()->json([
            'message' => 'Peserta telah didiskualifikasi',
            'match_id' => $match->id,
            'disqualified' => true
        ]);
    }


}


<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\LocalMatch;
use Illuminate\Support\Str;

class KnockoutStructureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $grouped = LocalMatch::where('round_level', 1)
            ->orderBy('match_number')
            ->get()
            ->groupBy(['class_name', 'pool_name']); // Group berdasarkan kelas & pool

        foreach ($grouped as $className => $pools) {
            foreach ($pools as $poolName => $matches) {
                $round = 1;
                $currentMatches = $matches->values();
                $nextRoundMatches = [];

                while ($currentMatches->count() > 1) {
                    $round++;

                    for ($i = 0; $i < $currentMatches->count(); $i += 2) {
                        $match1 = $currentMatches[$i];
                        $match2 = $currentMatches[$i + 1] ?? null;

                        $newMatchData = [
                            'tournament_name'       => $match1->tournament_name,
                            'arena_name'            => $match1->arena_name,
                            'class_name'            => $className,
                            'pool_name'             => $poolName,
                            'match_code'            => 'M-' . strtoupper(Str::random(5)),
                            'total_rounds'          => 3,
                            'round_level'           => $round,
                            'status'                => 'not_started',
                            'parent_match_red_id'   => $match1->id,
                            'parent_match_blue_id'  => $match2?->id,
                            'match_number'          => null, // bisa diisi belakangan
                            'red_id'                => null,
                            'red_name'              => '',
                            'red_contingent'        => '',
                            'blue_id'               => null,
                            'blue_name'             => '',
                            'blue_contingent'       => '',
                            'created_at'            => now(),
                            'updated_at'            => now(),
                        ];

                        $newMatchId = DB::table('local_matches')->insertGetId($newMatchData);
                        $nextRoundMatches[] = LocalMatch::find($newMatchId);
                    }

                    $currentMatches = collect($nextRoundMatches);
                    $nextRoundMatches = [];
                }
            }
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class LocalMatchSeeder extends Seeder
{
    public function run(): void
    {
        // 🔥 Disable FK constraint dulu
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // 🔥 Truncate semua tabel terkait
        DB::table('local_match_rounds')->truncate();
        DB::table('local_judge_scores')->truncate();
        DB::table('local_valid_scores')->truncate();
        DB::table('match_personnel_assignments')->truncate();
        DB::table('local_referee_actions')->truncate();
        DB::table('local_matches')->truncate();

        // 🔒 Aktifkan kembali FK
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        
        $arenas = ['Arena A', 'Arena B', 'Arena C'];
        $pools = ['A', 'B'];
        $class = 'Kelas A';
        $contingents = ['Garuda', 'Rajawali', 'Cendrawasih', 'Elang', 'Harimau'];
        $faker = \Faker\Factory::create();

        foreach ($arenas as $arena) {
            foreach ($pools as $pool) {
                $matchIdMap = [
                    'r1' => [],
                    'r2' => [],
                ];

                // Round 1: 4 matches
                for ($i = 0; $i < 4; $i++) {
                    $matchId = DB::table('local_matches')->insertGetId([
                        'tournament_name' => "Indonesia National Championships 2025",
                        'arena_name' => $arena,
                        'class_name' => $class,
                        'pool_name' => $pool,
                        'match_code' => 'M-' . strtoupper(Str::random(5)),
                        'total_rounds' => 3,
                        'round_level' => 1,
                        'match_number' => $i + 1,
                        'status' => 'not_started',

                        'red_id' => $faker->unique()->numberBetween(1, 1000),
                        'red_name' => $faker->name(),
                        'red_contingent' => $faker->randomElement($contingents),

                        'blue_id' => $faker->unique()->numberBetween(1, 1000),
                        'blue_name' => $faker->name(),
                        'blue_contingent' => $faker->randomElement($contingents),

                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $matchIdMap['r1'][] = $matchId;
                }

                // Round 2: 2 matches
                for ($i = 0; $i < 2; $i++) {
                    $matchId = DB::table('local_matches')->insertGetId([
                        'tournament_name' => "Indonesia National Championships 2025",
                        'arena_name' => $arena,
                        'class_name' => $class,
                        'pool_name' => $pool,
                        'match_code' => 'M-' . strtoupper(Str::random(5)),
                        'total_rounds' => 3,
                        'round_level' => 2,
                        'match_number' => $i + 5,
                        'status' => 'not_started',

                        'parent_match_red_id' => $matchIdMap['r1'][$i * 2],
                        'parent_match_blue_id' => $matchIdMap['r1'][$i * 2 + 1],

                        'red_name' => '',
                        'red_contingent' => '',
                        'blue_name' => '',
                        'blue_contingent' => '',

                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $matchIdMap['r2'][] = $matchId;
                }

                // Final: 1 match
                DB::table('local_matches')->insert([
                    'tournament_name' => "Indonesia National Championships 2025",
                    'arena_name' => $arena,
                    'class_name' => $class,
                    'pool_name' => $pool,
                    'match_code' => 'M-' . strtoupper(Str::random(5)),
                    'total_rounds' => 3,
                    'round_level' => 3,
                    'match_number' => 7,
                    'status' => 'not_started',

                    'parent_match_red_id' => $matchIdMap['r2'][0],
                    'parent_match_blue_id' => $matchIdMap['r2'][1],

                    'red_name' => '',
                    'red_contingent' => '',
                    'blue_name' => '',
                    'blue_contingent' => '',

                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}

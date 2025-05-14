<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocalSeniMatchSeeder extends Seeder
{
    private function getRandomDate(): string
    {
        $start = strtotime('2025-05-17');
        $end = strtotime('2025-05-19');
        return date('Y-m-d', rand($start, $end));
    }

    public function run()
    {
        $matchId = 1;
        $data = [];

        // 🔸 Tunggal: 3 Pool × 5 match
        foreach (['Pool A', 'Pool B', 'Pool C'] as $poolIndex => $poolName) {
            $ageCategories = ['Usia Dini 1', 'Usia Dini 2', 'Pra Remaja'];

            for ($order = 1; $order <= 5; $order++) {
                $data[] = [
                    'remote_match_id' => $matchId++,
                    'remote_contingent_id' => 10 + $order + $poolIndex,
                    'remote_team_member_1' => 100 + $order + $poolIndex,
                    'remote_team_member_2' => null,
                    'remote_team_member_3' => null,

                    'tournament_name' => 'Indonesia National Championships 2025',
                    'arena_name' => 'Arena 1',
                    'match_date' => $this->getRandomDate(),
                    'match_time' => sprintf('09:%02d:00', rand(0, 59)),
                    'pool_name' => $poolName,
                    'match_order' => $order,

                    'category' => 'Tunggal',
                    'match_type' => 'seni_tunggal',
                    'gender' => $order % 2 === 0 ? 'female' : 'male',
                    'contingent_name' => 'Kontingen ' . Str::random(5),

                    'participant_1' => 'Peserta ' . $matchId,
                    'participant_2' => null,
                    'participant_3' => null,

                    'age_category' => $ageCategories[$poolIndex % 3],
                    'final_score' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // 🔸 Ganda: 2 Pool × 5 match
        foreach (['Pool D', 'Pool E'] as $poolIndex => $poolName) {
            for ($order = 1; $order <= 5; $order++) {
                $ageCategory = $order % 2 === 0 ? 'Remaja' : 'Pra Remaja';

                $data[] = [
                    'remote_match_id' => $matchId++,
                    'remote_contingent_id' => 30 + $order + $poolIndex,
                    'remote_team_member_1' => 200 + $order,
                    'remote_team_member_2' => 201 + $order,
                    'remote_team_member_3' => null,

                    'tournament_name' => 'Indonesia National Championships 2025',
                    'arena_name' => 'Arena 2',
                    'match_date' => $this->getRandomDate(),
                    'match_time' => sprintf('11:%02d:00', rand(0, 59)),
                    'pool_name' => $poolName,
                    'match_order' => $order,

                    'category' => 'Ganda',
                    'match_type' => 'seni_ganda',
                    'gender' => $order % 2 === 0 ? 'male' : 'female',
                    'contingent_name' => 'Kontingen ' . Str::random(5),

                    'participant_1' => 'Pasangan ' . $matchId . 'A',
                    'participant_2' => 'Pasangan ' . $matchId . 'B',
                    'participant_3' => null,

                    'age_category' => $ageCategory,
                    'final_score' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // 🔸 Regu: 2 Pool × 5 match
        foreach (['Pool F', 'Pool G'] as $poolIndex => $poolName) {
            for ($order = 1; $order <= 5; $order++) {
                $ageCategory = $order % 2 === 0 ? 'Remaja' : 'Usia Dini 2';

                $data[] = [
                    'remote_match_id' => $matchId++,
                    'remote_contingent_id' => 50 + $order + $poolIndex,
                    'remote_team_member_1' => 300 + $order,
                    'remote_team_member_2' => 301 + $order,
                    'remote_team_member_3' => 302 + $order,

                    'tournament_name' => 'Indonesia National Championships 2025',
                    'arena_name' => 'Arena 3',
                    'match_date' => $this->getRandomDate(),
                    'match_time' => sprintf('13:%02d:00', rand(0, 59)),
                    'pool_name' => $poolName,
                    'match_order' => $order,

                    'category' => 'Regu',
                    'match_type' => 'seni_regu',
                    'gender' => $order % 2 === 0 ? 'female' : 'male',
                    'contingent_name' => 'Kontingen ' . Str::random(5),

                    'participant_1' => 'Anggota ' . $matchId . 'A',
                    'participant_2' => 'Anggota ' . $matchId . 'B',
                    'participant_3' => 'Anggota ' . $matchId . 'C',

                    'age_category' => $ageCategory,
                    'final_score' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('local_seni_matches')->insert($data);
    }

}

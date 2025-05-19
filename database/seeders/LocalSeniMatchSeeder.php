<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocalSeniMatchSeeder extends Seeder
{
    private array $randomNames = [
        'Kiki Anggraini', 'Ahmad Saputra', 'Putri Nurhaliza', 'Indah Nurhaliza', 'Dewi Putra',
        'Eko Maulana', 'Citra Susanti', 'Fajar Wulandari', 'Ahmad Nurhaliza', 'Citra Utami',
        'Indah Maulana', 'Dewi Putra', 'Santi Maulana', 'Nina Anggraini', 'Budi Ardiansyah',
        'Budi Maulana', 'Citra Wijaya', 'Dewi Nurhaliza', 'Rizky Putra', 'Made Gunawan',
        'Made Rahmawati', 'Indah Wulandari', 'Rizky Utami', 'Joko Hartono', 'Gita Putra',
        'Fajar Hidayat', 'Citra Gunawan', 'Ahmad Syahputra', 'Rizky Maulana', 'Umi Putra',
        'Fajar Maulana', 'Umi Rahmawati', 'Umi Anggraini', 'Rizky Nurhaliza', 'Lina Nurhaliza',
        'Budi Anjani', 'Citra Hartono', 'Eko Hartono', 'Santi Pratama', 'Fajar Kurniawan'
    ];

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
                $nama1 = $this->randomNames[array_rand($this->randomNames)];

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
                    'contingent_name' => 'Kontingen ' . substr(md5(rand()), 0, 5),

                    'participant_1' => $nama1,
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
                $nama1 = $this->randomNames[array_rand($this->randomNames)];
                $nama2 = $this->randomNames[array_rand($this->randomNames)];

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
                    'contingent_name' => 'Kontingen ' . substr(md5(rand()), 0, 5),

                    'participant_1' => $nama1,
                    'participant_2' => $nama2,
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
                $nama1 = $this->randomNames[array_rand($this->randomNames)];
                $nama2 = $this->randomNames[array_rand($this->randomNames)];
                $nama3 = $this->randomNames[array_rand($this->randomNames)];

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
                    'contingent_name' => 'Kontingen ' . substr(md5(rand()), 0, 5),

                    'participant_1' => $nama1,
                    'participant_2' => $nama2,
                    'participant_3' => $nama3,

                    'age_category' => $ageCategory,
                    'final_score' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // 🔸 Solo Kreatif: 1 Pool × 5 match
        foreach (['Pool H'] as $poolIndex => $poolName) {
            for ($order = 1; $order <= 5; $order++) {
                $ageCategory = $order % 2 === 0 ? 'Remaja' : 'Pra Remaja';
                $nama1 = $this->randomNames[array_rand($this->randomNames)];

                $data[] = [
                    'remote_match_id' => $matchId++,
                    'remote_contingent_id' => 70 + $order + $poolIndex,
                    'remote_team_member_1' => 400 + $order,
                    'remote_team_member_2' => null,
                    'remote_team_member_3' => null,

                    'tournament_name' => 'Indonesia National Championships 2025',
                    'arena_name' => 'Arena 4',
                    'match_date' => $this->getRandomDate(),
                    'match_time' => sprintf('15:%02d:00', rand(0, 59)),
                    'pool_name' => $poolName,
                    'match_order' => $order,

                    'category' => 'Solo Kreatif',
                    'match_type' => 'solo_kreatif',
                    'gender' => $order % 2 === 0 ? 'female' : 'male',
                    'contingent_name' => 'Kontingen ' . substr(md5(rand()), 0, 5),

                    'participant_1' => $nama1,
                    'participant_2' => null,
                    'participant_3' => null,

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

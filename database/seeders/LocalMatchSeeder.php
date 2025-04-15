<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocalMatchSeeder extends Seeder
{
    public function run(): void
    {
        $arenas = ['Arena 1', 'Arena 2', 'Arena 3'];
        $classes = ['Kelas A', 'Kelas B', 'Kelas C', 'Kelas D'];
        $contingents = ['Garuda', 'Rajawali', 'Cendrawasih', 'Elang', 'Harimau'];
        $athletes = range(1, 100); // Generate array of athlete IDs from 1 to 100

        for ($i = 1; $i <= 30; $i++) {
            DB::table('local_matches')->insert([
                'remote_match_id' => rand(1000, 2000),
                'arena_name' => $arenas[array_rand($arenas)],
                'class_name' => $classes[array_rand($classes)],
                'match_code' => 'M-' . strtoupper(Str::random(5)),
                'total_rounds' => 3,
                'status' => 'not_started', // Set all matches to not_started

                'red_id' => $athletes[array_rand($athletes)], // Random athlete ID for red
                'red_name' => 'Atlet Merah ' . $i,
                'red_contingent' => $contingents[array_rand($contingents)],

                'blue_id' => $athletes[array_rand($athletes)], // Random athlete ID for blue
                'blue_name' => 'Atlet Biru ' . $i,
                'blue_contingent' => $contingents[array_rand($contingents)],

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
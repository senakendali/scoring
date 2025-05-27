<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocalImportController extends Controller
{

    public function store(Request $request)
    {
        $data = $request->all();

        // mapping match_id dari pusat ➜ id lokal
        $matchIdMap = [];

        // ✅ Truncate semua data dulu DI LUAR transaksi
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('local_match_rounds')->truncate();
        DB::table('local_judge_scores')->truncate();
        DB::table('local_valid_scores')->truncate();
        DB::table('match_personnel_assignments')->truncate();
        DB::table('local_referee_actions')->truncate();
        DB::table('local_matches')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        try {
            DB::beginTransaction();

            foreach ($data as $match) {
                $insert = [
                    'tournament_name' => $match['tournament'],
                    'remote_match_id' => $match['match_id'],
                    'arena_name' => $match['arena'],
                    'pool_name' => $match['pool'],
                    'class_name' => $match['class'],
                    'match_code' => 'M-' . strtoupper(Str::random(5)),
                    'total_rounds' => 3,
                    'round_level' => $match['round_level'],
                    'match_number' => $match['match_number'],
                    'round_duration' => $match['match_duration'],
                    'status' => 'not_started',
                    'is_display_timer' => filter_var($match['is_display_timer'] ?? false, FILTER_VALIDATE_BOOLEAN),

                    'red_id' => $match['red_id'] ?? null,
                    'red_name' => $match['red_name'] ?? '',
                    'red_contingent' => $match['red_contingent'] ?? '',
                    'blue_id' => $match['blue_id'] ?? null,
                    'blue_name' => $match['blue_name'] ?? '',
                    'blue_contingent' => $match['blue_contingent'] ?? '',

                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $localId = DB::table('local_matches')->insertGetId($insert);
                $matchIdMap[$match['match_id']] = $localId;
            }

            foreach ($data as $match) {
                $localId = $matchIdMap[$match['match_id']];
                DB::table('local_matches')->where('id', $localId)->update([
                    'parent_match_red_id' => $match['parent_match_red_id'] ? ($matchIdMap[$match['parent_match_red_id']] ?? null) : null,
                    'parent_match_blue_id' => $match['parent_match_blue_id'] ? ($matchIdMap[$match['parent_match_blue_id']] ?? null) : null,
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Matches imported successfully.']);
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    public function storeSeni(Request $request)
    {
        $data = $request->all();

        try {
            // ✅ Jalankan FK disable dan truncate di luar transaction
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table('local_seni_matches')->truncate();
            DB::table('local_seni_scores')->truncate();
            DB::table('local_seni_penalties')->truncate();
            DB::table('local_seni_final_scores')->truncate();
            DB::table('local_seni_component_scores')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            // ✅ Baru mulai transaksi insert
            DB::transaction(function () use ($data) {
                foreach ($data as $match) {
                    DB::table('local_seni_matches')->insert([
                        'remote_match_id' => $match['remote_match_id'],
                        'remote_contingent_id' => $match['remote_contingent_id'],
                        'remote_team_member_1' => $match['remote_team_member_1'],
                        'remote_team_member_2' => $match['remote_team_member_2'],
                        'remote_team_member_3' => $match['remote_team_member_3'],
                        'tournament_name' => $match['tournament_name'],
                        'arena_name' => $match['arena_name'],
                        'match_date' => $match['match_date'],
                        'match_time' => $match['match_time'],
                        'pool_name' => $match['pool_name'],
                        'match_order' => $match['match_order'],
                        'category' => $match['category'],
                        'match_type' => $match['match_type'],
                        'gender' => $match['gender'],
                        'contingent_name' => $match['contingent_name'],
                        'participant_1' => $match['participant_1'],
                        'participant_2' => $match['participant_2'],
                        'participant_3' => $match['participant_3'],
                        'age_category' => $match['age_category'],
                        'final_score' => $match['final_score'] ?? null,
                        'is_display_timer' => filter_var($match['is_display_timer'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

            return response()->json(['message' => 'Seni matches imported successfully.']);
        } catch (\Throwable $e) {
            \Log::error('Import seni gagal', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }






}

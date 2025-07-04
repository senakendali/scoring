<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocalImportController extends Controller
{

    

    public function store_backup(Request $request)
    {
        $data = $request->all();

        // Mapping match_id pusat → id lokal
        $matchIdMap = [];

        // ✅ Kosongkan tabel terkait SEBELUM transaksi
        /* 
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('local_match_rounds')->truncate();
        DB::table('local_judge_scores')->truncate();
        DB::table('local_valid_scores')->truncate();
        DB::table('match_personnel_assignments')->truncate();
        DB::table('local_referee_actions')->truncate();
        DB::table('local_matches')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        */

        try {
            DB::beginTransaction();

            foreach ($data as $match) {
                $insert = [
                    'match_date' => $match['scheduled_date'],
                    'tournament_name' => $match['tournament_name'] ?? '-',
                    'remote_match_id' => $match['match_id'],
                    'arena_name' => $match['arena_name'] ?? '-',
                    'pool_name' => $match['pool_name'] ?? '-',
                    'class_name' => $match['class_name'] ?? '-',
                    'match_code' => 'M-' . strtoupper(Str::random(5)),
                    'total_rounds' => 3,
                    'round_level' => $match['round_level'] ?? 0,
                    'round_label' => $match['round_label'] ?? '-',
                    'match_number' => $match['match_number'] ?? 0,
                    'round_duration' => $match['round_duration'] ?? 180, // default 180 detik
                    'status' => 'not_started',
                    'is_display_timer' => isset($match['is_display_timer']) && $match['is_display_timer'] == '1' ? 1 : 0,

                    'red_id' => $match['red_id'] ?? null,
                    'red_name' => $match['red_name'] ?? 'TBD',
                    'red_contingent' => $match['red_contingent'] ?? 'TBD',

                    'blue_id' => $match['blue_id'] ?? null,
                    'blue_name' => $match['blue_name'] ?? 'TBD',
                    'blue_contingent' => $match['blue_contingent'] ?? 'TBD',

                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $localId = DB::table('local_matches')->insertGetId($insert);
                $matchIdMap[$match['match_id']] = $localId;
            }

            // Update parent match jika ada
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

    public function store(Request $request)
    {
        $data = $request->all();

        // 🧠 Ambil nama turnamen dari data pertama
        $tournamentName = $data[0]['tournament_name'] ?? null;

        if (!$tournamentName) {
            return response()->json(['error' => 'Tournament name is required.'], 422);
        }

        try {
            DB::beginTransaction();

            // 🧹 Hapus semua data yang berkaitan dengan turnamen yang sama
            $matchIdsToDelete = DB::table('local_matches')
                ->where('tournament_name', $tournamentName)
                ->pluck('id');

            if ($matchIdsToDelete->isNotEmpty()) {
                DB::table('local_match_rounds')->whereIn('local_match_id', $matchIdsToDelete)->delete();
                DB::table('local_judge_scores')->whereIn('local_match_id', $matchIdsToDelete)->delete();
                DB::table('local_valid_scores')->whereIn('local_match_id', $matchIdsToDelete)->delete();
                DB::table('match_personnel_assignments')->where('tournament_name', $tournamentName)->delete();
                DB::table('local_referee_actions')->whereIn('local_match_id', $matchIdsToDelete)->delete();
                DB::table('local_matches')->whereIn('id', $matchIdsToDelete)->delete();
            }

            // 🏗️ Mapping match_id pusat → id lokal
            $matchIdMap = [];

            foreach ($data as $match) {
                $insert = [
                    'match_date' => $match['scheduled_date'],
                    'tournament_name' => $tournamentName,
                    'remote_match_id' => $match['match_id'],
                    'arena_name' => $match['arena_name'] ?? '-',
                    'pool_name' => $match['pool_name'] ?? '-',
                    'class_name' => $match['class_name'] ?? '-',
                    'match_code' => 'M-' . strtoupper(Str::random(5)),
                    'total_rounds' => 3,
                    'round_level' => $match['round_level'] ?? 0,
                    'round_label' => $match['round_label'] ?? '-',
                    'match_number' => $match['match_number'] ?? 0,
                    'round_duration' => $match['round_duration'] ?? 180,
                    'status' => 'not_started',
                    'is_display_timer' => isset($match['is_display_timer']) && $match['is_display_timer'] == '1' ? 1 : 0,

                    'red_id' => $match['red_id'] ?? null,
                    'red_name' => $match['red_name'] ?? 'TBD',
                    'red_contingent' => $match['red_contingent'] ?? 'TBD',

                    'blue_id' => $match['blue_id'] ?? null,
                    'blue_name' => $match['blue_name'] ?? 'TBD',
                    'blue_contingent' => $match['blue_contingent'] ?? 'TBD',

                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $localId = DB::table('local_matches')->insertGetId($insert);
                $matchIdMap[$match['match_id']] = $localId;
            }

            // 🔁 Update parent match jika ada
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

        // 🧠 Ambil nama turnamen dari data pertama
        $tournamentName = $data[0]['tournament_name'] ?? null;

        if (!$tournamentName) {
            return response()->json(['error' => 'Tournament name is required.'], 422);
        }

        try {
            // 🔍 Ambil semua match ID lama untuk turnamen ini
            $matchIdsToDelete = DB::table('local_seni_matches')
                ->where('tournament_name', $tournamentName)
                ->pluck('id');

            if ($matchIdsToDelete->isNotEmpty()) {
                // 🧹 Bersihkan data terkait
                DB::table('local_seni_component_scores')->whereIn('local_match_id', $matchIdsToDelete)->delete();
                DB::table('local_seni_scores')->whereIn('local_match_id', $matchIdsToDelete)->delete();
                DB::table('local_seni_penalties')->whereIn('local_match_id', $matchIdsToDelete)->delete();
                DB::table('local_seni_final_scores')->whereIn('local_match_id', $matchIdsToDelete)->delete();
                DB::table('local_seni_component_scores')->whereIn('local_match_id', $matchIdsToDelete)->delete();
                DB::table('local_seni_matches')->whereIn('id', $matchIdsToDelete)->delete();
            }

            // 🚀 Mulai insert baru
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






    public function storeSeni_bakup(Request $request)
    {
        $data = $request->all();

        try {
            // ✅ Jalankan FK disable dan truncate di luar transaction
            /*
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table('local_seni_matches')->truncate();
            DB::table('local_seni_scores')->truncate();
            DB::table('local_seni_penalties')->truncate();
            DB::table('local_seni_final_scores')->truncate();
            DB::table('local_seni_component_scores')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            */

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

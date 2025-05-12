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

        DB::beginTransaction();
        try {
            foreach ($data as $match) {
                $insert = [
                    'tournament_name' => $match['tournament'],
                    'arena_name' => $match['arena'],
                    'pool_name' => $match['pool'],
                    'class_name' => $match['class'],
                    'match_code' => 'M-' . strtoupper(Str::random(5)),
                    'total_rounds' => 3,
                    'round_level' => $match['round_level'],
                    'match_number' => $match['match_number'],
                    'status' => 'not_started',

                    'red_id' => $match['red_id'] ?? null,
                    'red_name' => $match['red_name'] ?? '',
                    'red_contingent' => $match['red_contingent'] ?? '',
                    'blue_id' => $match['blue_id'] ?? null,
                    'blue_name' => $match['blue_name'] ?? '',
                    'blue_contingent' => $match['blue_contingent'] ?? '',

                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Simpan dulu, tanpa parent
                $localId = DB::table('local_matches')->insertGetId($insert);
                $matchIdMap[$match['match_id']] = $localId;
            }

            // Loop kedua: update parent match
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
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

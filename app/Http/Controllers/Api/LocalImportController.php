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
                    'match_date'        => $match['scheduled_date'] ?? null,
                    'tournament_name'   => $tournamentName,
                    'remote_match_id'   => $match['match_id'] ?? null,
                    'arena_name'        => $match['arena_name'] ?? '-',
                    'pool_name'         => $match['pool_name'] ?? '-',
                    'class_name'        => $match['class_name'] ?? '-',
                    'match_code'        => 'M-' . strtoupper(Str::random(5)),
                    'total_rounds'      => 3,
                    'round_level'       => $match['round_level'] ?? 0,
                    'round_label'       => $match['round_label'] ?? '-',
                    'match_number'      => $match['match_number'] ?? 0,
                    'round_duration'    => $match['round_duration'] ?? 180,
                    'status'            => 'not_started',
                    'is_display_timer'  => !empty($match['is_display_timer']) && (string)$match['is_display_timer'] === '1' ? 1 : 0,

                    'red_id'            => $match['red_id'] ?? null,
                    'red_name'          => $match['red_name'] ?? 'TBD',
                    'red_contingent'    => $match['red_contingent'] ?? 'TBD',

                    'blue_id'           => $match['blue_id'] ?? null,
                    'blue_name'         => $match['blue_name'] ?? 'TBD',
                    'blue_contingent'   => $match['blue_contingent'] ?? 'TBD',

                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];

                $localId = DB::table('local_matches')->insertGetId($insert);

                if (isset($match['match_id'])) {
                    $matchIdMap[$match['match_id']] = $localId;
                }
            }

            // 🔁 Update parent match (FIX: swap mapping yang sebelumnya ketuker)
            foreach ($data as $match) {
                if (!isset($match['match_id'])) {
                    continue;
                }
                $localId = $matchIdMap[$match['match_id']] ?? null;
                if (!$localId) {
                    continue;
                }

                // Remote kirim: parent_match_red_id & parent_match_blue_id
                // Yang benar: local.parent_match_red_id <- remote.parent_match_blue_id
                //             local.parent_match_blue_id <- remote.parent_match_red_id
                $remoteRed  = $match['parent_match_red_id']  ?? null; // ini aslinya lawan untuk BLUE
                $remoteBlue = $match['parent_match_blue_id'] ?? null; // ini aslinya lawan untuk RED

                $localParentRedId  = $remoteBlue && isset($matchIdMap[$remoteBlue]) ? $matchIdMap[$remoteBlue] : null;
                $localParentBlueId = $remoteRed  && isset($matchIdMap[$remoteRed])  ? $matchIdMap[$remoteRed]  : null;

                DB::table('local_matches')->where('id', $localId)->update([
                    'parent_match_red_id'  => $localParentRedId,
                    'parent_match_blue_id' => $localParentBlueId,
                    'updated_at'           => now(),
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


    public function store_lss(Request $request)
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
            DB::transaction(function () use ($data, $tournamentName) {

                // 1) Bersihkan data lama turnamen ini (sekali dalam transaksi)
                $oldIds = DB::table('local_seni_matches')
                    ->where('tournament_name', $tournamentName)
                    ->pluck('id');

                if ($oldIds->isNotEmpty()) {
                    DB::table('local_seni_component_scores')->whereIn('local_match_id', $oldIds)->delete();
                    DB::table('local_seni_scores')->whereIn('local_match_id', $oldIds)->delete();
                    DB::table('local_seni_penalties')->whereIn('local_match_id', $oldIds)->delete();
                    DB::table('local_seni_final_scores')->whereIn('local_match_id', $oldIds)->delete();
                    DB::table('local_seni_matches')->whereIn('id', $oldIds)->delete();
                }

                // 2) Siapkan insert baru (fase 1: tanpa parent pointers)
                $now = now();
                $rowsToInsert = [];
                // simpan parent remote ids sementara utk fase-2
                $parentLinks = []; // key: remote_match_id => ['red_remote' => ..., 'blue_remote' => ...]
                $validCorner = ['red','blue'];

                foreach ($data as $match) {
                    // Normalisasi
                    $mode = in_array(($match['mode'] ?? 'default'), ['default','battle'], true)
                            ? $match['mode'] : 'default';

                    $corner = $match['corner'] ?? null;
                    $corner = in_array($corner, $validCorner, true) ? $corner : null;

                    $winnerCorner = $match['winner_corner'] ?? null;
                    $winnerCorner = in_array($winnerCorner, $validCorner, true) ? $winnerCorner : null;

                    // Kalau non-battle, kosongkan field battle yang tidak relevan
                    $battleGroup   = $mode === 'battle' ? ($match['battle_group']   ?? null) : null;
                    $round         = $mode === 'battle' ? ($match['round']          ?? null) : null;
                    $roundLabel    = $match['round_label'] ?? null; // label dari JADWAL (boleh untuk default maupun battle)
                    $roundPriority = $match['round_priority'] ?? null; // angka dari API (opsional)

                    $rowsToInsert[] = [
                        // remote mapping (yang lama)
                        'remote_match_id'      => $match['remote_match_id']      ?? null,
                        'remote_contingent_id' => $match['remote_contingent_id'] ?? null,
                        'remote_team_member_1' => $match['remote_team_member_1'] ?? null,
                        'remote_team_member_2' => $match['remote_team_member_2'] ?? null,
                        'remote_team_member_3' => $match['remote_team_member_3'] ?? null,

                        // identitas & context
                        'tournament_name' => $match['tournament_name'] ?? '-',
                        'arena_name'      => $match['arena_name']      ?? '-',
                        'match_date'      => $match['match_date']      ?? null,
                        'match_time'      => $match['match_time']      ?? null,
                        'pool_name'       => $match['pool_name']       ?? '-',

                        // nomor partai
                        'match_order'     => $match['match_order']     ?? null,

                        // kategori & peserta (yang lama)
                        'category'        => $match['category']        ?? 'Unknown',
                        'match_type'      => $match['match_type']      ?? 'unknown',
                        'gender'          => $match['gender']          ?? null,
                        'contingent_name' => $match['contingent_name'] ?? 'TBD',
                        'participant_1'   => $match['participant_1']   ?? null,
                        'participant_2'   => $match['participant_2']   ?? null,
                        'participant_3'   => $match['participant_3']   ?? null,
                        'age_category'    => $match['age_category']    ?? '-',

                        // nilai & flag
                        'final_score'      => $match['final_score'] ?? null,
                        'is_display_timer' => filter_var($match['is_display_timer'] ?? false, FILTER_VALIDATE_BOOLEAN),

                        // ====== FIELD BARU (battle-ready) ======
                        'mode'            => $mode,           // 'default'|'battle'
                        'battle_group'    => $battleGroup,    // int|null
                        'round'           => $round,          // int|null
                        'round_label'     => $roundLabel,     // string|null (dari JADWAL)
                        'round_priority'  => $roundPriority,  // int|null
                        'corner'          => $corner,         // 'red'|'blue'|null
                        'winner_corner'   => $winnerCorner,   // 'red'|'blue'|null

                        // parent pointer DIISI NANTI (fase-2) pakai local id
                        'parent_match_red_id'  => null,
                        'parent_match_blue_id' => null,

                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    // simpan remote parent untuk fase-2 mapping
                    $parentLinks[$match['remote_match_id']] = [
                        'red_remote'  => $match['parent_match_red_id']  ?? null,
                        'blue_remote' => $match['parent_match_blue_id'] ?? null,
                    ];
                }

                // Insert batch (chunk biar aman)
                foreach (array_chunk($rowsToInsert, 500) as $chunk) {
                    DB::table('local_seni_matches')->insert($chunk);
                }

                // 3) Fase-2: isi parent pointer (map remote → local)
                // Ambil mapping remote_match_id -> local_id utk turnamen ini
                $localMap = DB::table('local_seni_matches')
                    ->where('tournament_name', $tournamentName)
                    ->pluck('id', 'remote_match_id'); // ['remote_id' => local_id]

                // Update tiap baris dengan parent local id (kalau ada)
                // Ambil semua baris turnamen ini biar dapat pair local id & remote id-nya
                $locals = DB::table('local_seni_matches')
                    ->select('id', 'remote_match_id')
                    ->where('tournament_name', $tournamentName)
                    ->get();

                foreach ($locals as $row) {
                    $remoteId = $row->remote_match_id;
                    $links    = $parentLinks[$remoteId] ?? null;
                    if (!$links) continue;

                    $parentRedRemote  = $links['red_remote'];
                    $parentBlueRemote = $links['blue_remote'];

                    $update = [];
                    if ($parentRedRemote && isset($localMap[$parentRedRemote])) {
                        $update['parent_match_red_id'] = $localMap[$parentRedRemote];
                    }
                    if ($parentBlueRemote && isset($localMap[$parentBlueRemote])) {
                        $update['parent_match_blue_id'] = $localMap[$parentBlueRemote];
                    }

                    if (!empty($update)) {
                        $update['updated_at'] = $now;
                        DB::table('local_seni_matches')->where('id', $row->id)->update($update);
                    }
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

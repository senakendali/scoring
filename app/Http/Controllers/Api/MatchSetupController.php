<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MatchPersonnelAssignment;
use App\Events\MatchStarted;
use Illuminate\Support\Facades\Log;



class MatchSetupController extends Controller
{
   public function store(Request $request)
{
    try {
        $data = $request->validate([
            'tournament_name' => 'required|string',
            'arena_name' => 'required|string',
            'match_type' => 'required|in:tanding,seni',
            'role' => 'required|in:juri,operator,dewan,ketua,penonton',
            'juri_number' => 'nullable|integer',
            'seni_category' => 'nullable|in:tunggal,regu,ganda,solo_kreatif',
        ]);

        // 🔍 Cek apakah sudah pernah disimpan (unik berdasarkan: turnamen + arena + role + match_type [+ juri_number])
        $query = MatchPersonnelAssignment::where('tournament_name', $data['tournament_name'])
            ->where('arena_name', $data['arena_name'])
            ->where('role', $data['role'])
            ->where('tipe_pertandingan', $data['match_type']); // ✅ tambahkan ini

        if ($data['role'] === 'juri') {
            if (!isset($data['juri_number'])) {
                return response()->json([
                    'message' => 'Nomor juri wajib diisi untuk role juri.'
                ], 422);
            }

            $query->where('juri_number', $data['juri_number']);
        }

        $alreadyExists = $query->exists();

        // ✅ Simpan session dasar
        session([
            'role' => $data['role'],
            'arena_name' => $data['arena_name'],
            'tournament_name' => $data['tournament_name'],
            'match_type' => $data['match_type'],
            'juri_number' => $data['role'] === 'juri' ? $data['juri_number'] : null,
        ]);

        // ✅ Set base score jika kategori seni dan role juri
        if (
            $data['match_type'] === 'seni' &&
            $data['role'] === 'juri' &&
            isset($data['seni_category'])
        ) {
            $baseScore = in_array($data['seni_category'], ['tunggal', 'regu']) ? 9.90 : 9.10;

            session([
                'seni_category' => $data['seni_category'],
                'seni_base_score' => $baseScore,
            ]);
        }

        // ✅ Simpan ke DB jika belum ada
        if (! $alreadyExists) {
            MatchPersonnelAssignment::create([
                'tournament_name' => $data['tournament_name'],
                'arena_name' => $data['arena_name'],
                'tipe_pertandingan' => $data['match_type'],
                'role' => $data['role'],
                'juri_number' => $data['role'] === 'juri' ? $data['juri_number'] : null,
            ]);
        }

        // ✅ Tentukan redirect sesuai role
        $redirectUrl = match ($data['role']) {
            'juri' => '/matches/judges',
            'dewan' => '/matches/referees',
            'ketua' => '/matches/recap',
            'penonton' => '/matches/display-arena',
            'operator' => '/matches',
        };

        return response()->json([
            'message' => $alreadyExists
                ? 'Role sudah terdaftar, langsung redirect.'
                : 'Setup berhasil disimpan',
            'redirect_url' => $redirectUrl,
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Terjadi kesalahan saat menyimpan data.',
            'error' => $e->getMessage(),
            'trace' => config('app.debug') ? $e->getTrace() : [],
        ], 500);
    }
}


    public function store__(Request $request)
    {
        try {
            $data = $request->validate([
                'tournament_name' => 'required|string',
                'arena_name' => 'required|string',
                'match_type' => 'required|in:tanding,seni',
                'role' => 'required|in:juri,operator,dewan,ketua,penonton',
                'juri_number' => 'nullable|integer',
                'seni_category' => 'nullable|in:tunggal,regu,ganda,solo_kreatif',
            ]);

            $query = MatchPersonnelAssignment::where('tournament_name', $data['tournament_name'])
                ->where('arena_name', $data['arena_name'])
                ->where('role', $data['role']);

            if ($data['role'] === 'juri') {
                $query->where('juri_number', $data['juri_number']);
            }

            $alreadyExists = $query->exists();

            // ✅ Set session dasar
            session([
                'role' => $data['role'],
                'arena_name' => $data['arena_name'],
                'tournament_name' => $data['tournament_name'],
                'match_type' => $data['match_type'],
                'juri_number' => $data['role'] === 'juri' ? $data['juri_number'] : null,
            ]);

            // ✅ Set base score jika seni DAN role juri
            if ($data['match_type'] === 'seni' && $data['role'] === 'juri' && isset($data['seni_category'])) {
                $baseScore = in_array($data['seni_category'], ['tunggal', 'regu']) ? 9.90 : 9.10;
                session([
                    'seni_category' => $data['seni_category'],
                    'seni_base_score' => $baseScore,
                ]);
            }

            // ✅ Simpan ke DB jika belum ada
            if (! $alreadyExists) {
                MatchPersonnelAssignment::create([
                    'tournament_name' => $data['tournament_name'],
                    'arena_name' => $data['arena_name'],
                    'tipe_pertandingan' => $data['match_type'],
                    'role' => $data['role'],
                    'juri_number' => $data['role'] === 'juri' ? $data['juri_number'] : null,
                ]);
            }

            // ✅ Redirect sesuai role
            $redirectUrl = match ($data['role']) {
                'juri' => '/matches/judges',
                'dewan' => '/matches/referees',
                'ketua' => '/matches/recap',
                'penonton' => '/matches/display-arena',
                'operator' => '/matches',
            };

            return response()->json([
                'message' => $alreadyExists ? 'Role sudah terdaftar, langsung redirect.' : 'Setup berhasil disimpan',
                'redirect_url' => $redirectUrl,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan data.',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTrace() : [],
            ], 500);
        }
    }



    

    public function start(Request $request)
    {
        $request->validate([
            'match_id' => 'required|integer',
            'arena_name' => 'required|string',
            'tournament_name' => 'required|string',
        ]);

        try {
            event(new MatchStarted(
                $request->match_id,
                $request->arena_name,
                $request->tournament_name
            ));

            return response()->json(['message' => 'Match started']);
        } catch (\Throwable $e) {
            Log::error('Gagal memulai match: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Gagal memulai match',
                'error' => $e->getMessage()
            ], 500);
        }
    }




    public function getActiveJuriNumbers(Request $request)
    {
        $request->validate([
            'tournament_name' => 'required|string',
            'arena_name' => 'required|string',
            'match_type' => 'required|in:tanding,seni',
        ]);

        $juris = MatchPersonnelAssignment::where('tournament_name', $request->tournament_name)
            ->where('arena_name', $request->arena_name)
            ->where('tipe_pertandingan', $request->match_type)
            ->where('role', 'juri')
            ->pluck('juri_number');

        return response()->json($juris);
    }

}


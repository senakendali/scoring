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
            ]);

            $query = MatchPersonnelAssignment::where('tournament_name', $data['tournament_name'])
                ->where('arena_name', $data['arena_name'])
                ->where('role', $data['role']);

            if ($data['role'] === 'juri') {
                $query->where('juri_number', $data['juri_number']);
            }

            $alreadyExists = $query->exists();

            // Set session (baik sudah ada atau baru dibuat)
            session([
                'role' => $data['role'],
                'arena_name' => $data['arena_name'],
                'tournament_name' => $data['tournament_name'],
                'match_type' => $data['match_type'],
                'juri_number' => $data['role'] === 'juri' ? $data['juri_number'] : null,
            ]);

            // Jika belum ada, insert
            if (! $alreadyExists) {
                MatchPersonnelAssignment::create([
                    'tournament_name' => $data['tournament_name'],
                    'arena_name' => $data['arena_name'],
                    'tipe_pertandingan' => $data['match_type'],
                    'role' => $data['role'],
                    'juri_number' => $data['role'] === 'juri' ? $data['juri_number'] : null,
                ]);
            }

            // Tentukan URL redirect berdasarkan role
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

    public function store_(Request $request)
    {
        try {
            $data = $request->validate([
                'tournament_name' => 'required|string',
                'arena_name' => 'required|string',
                'match_type' => 'required|in:tanding,seni',
                'role' => 'required|in:juri,operator,dewan,ketua,penonton',
                'juri_number' => 'nullable|integer',
            ]);

            // Cek apakah role ini sudah terdaftar di turnamen + arena ini
            $query = MatchPersonnelAssignment::where('tournament_name', $data['tournament_name'])
                ->where('arena_name', $data['arena_name'])
                ->where('role', $data['role']);

            if ($data['role'] === 'juri') {
                $query->where('juri_number', $data['juri_number']);
            }

            if ($query->exists()) {
                return response()->json([
                    'message' => 'Role ini sudah digunakan di arena dan turnamen yang sama.'
                ], 409);
            }

            // Simpan data baru
            MatchPersonnelAssignment::create([
                'tournament_name' => $data['tournament_name'],
                'arena_name' => $data['arena_name'],
                'tipe_pertandingan' => $data['match_type'],
                'role' => $data['role'],
                'juri_number' => $data['role'] === 'juri' ? $data['juri_number'] : null,
            ]);

            session([
                'role' => $data['role'],
                'arena_name' => $data['arena_name'],
                'tournament_name' => $data['tournament_name'],
                'match_type' => $data['match_type'],
                'juri_number' => $data['role'] === 'juri' ? $data['juri_number'] : null,
            ]);
            

            return response()->json(['message' => 'Setup berhasil disimpan']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan data.',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTrace() : [],
            ], 500);
        }
    }

    
    public function start_(Request $request)
    {
        $request->validate([
            'match_id' => 'required|integer',
            'arena_name' => 'required|string',
            'tournament_name' => 'required|string',
        ]);

        event(new MatchStarted(
            $request->match_id,
            $request->arena_name,
            $request->tournament_name
        ));

        return response()->json(['message' => 'Match started']);
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


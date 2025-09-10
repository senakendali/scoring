<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller; // Added this import
use App\Models\LocalMatch;
use App\Models\LocalJudgeScore;
use App\Models\LocalRefereeAction;
use App\Models\LocalValidScore;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Events\ScoreUpdated;
use App\Events\JudgePointSubmitted;
use App\Events\RefereeActionSubmitted;
use Illuminate\Support\Facades\Cache;
use App\Events\VerificationRequested;
use App\Events\VerificationResulted;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Events\TandingWinnerAnnounced;
use Illuminate\Support\Str;

class LocalMatchController extends Controller
{
    private $live_server;

    public function __construct()
    {
        $this->live_server = config('app_settings.data_source');
    }
    // Menampilkan semua pertandingan
    public function index(Request $request)
    {
        $arena = session('arena_name');
        $tournament = session('tournament_name');

        $query =  $query = LocalMatch::query();

        if ($arena) {
            $query->where('arena_name', $arena);
        }

        if ($tournament) {
            $query->where('tournament_name', $tournament);
        }

        // Urutkan berdasarkan arena, pool, kelas, dan round_level
        $matches = $query->orderBy('arena_name')
            ->orderBy('pool_name')
            ->orderBy('class_name')
            ->orderBy('round_level')
            ->orderBy('match_number')
            ->get();

        // Group by arena → pool (1 pool = 1 kelas)
        $grouped = $matches->groupBy(['arena_name', 'pool_name']);

        return response()->json($grouped);
    }

   public function fetchMatchForAdmin(Request $request)
    {
        $sessionArena = session('arena_name');            // default (opsional)
        $tournament   = session('tournament_name');       // wajibnya turnamen

        // ⤵️ Filter dari FE (opsional)
        $arenaFilter  = trim((string) $request->input('arena_name', ''));
        $from         = $request->input('from');  // no partai awal
        $to           = $request->input('to');    // no partai akhir

        $query = \App\Models\LocalMatch::query();

        if (!empty($tournament)) {
            $query->where('tournament_name', $tournament);
        }

        // Prefer filter dari request; kalau kosong, baru fallback ke session arena (jika ada)
        if ($arenaFilter !== '') {
            $query->where('arena_name', $arenaFilter);
        } elseif (!empty($sessionArena)) {
            $query->where('arena_name', $sessionArena);
        }

        // Filter range partai (match_number)
        if (is_numeric($from)) {
            $query->where('match_number', '>=', (int) $from);
        }
        if (is_numeric($to)) {
            $query->where('match_number', '<=', (int) $to);
        }

        // Urutan stabil
        $matches = $query
            ->orderBy('arena_name')
            ->orderBy('pool_name')
            ->orderBy('class_name')
            ->orderBy('round_level')
            ->orderBy('match_number')
            ->get();

        // Group by arena → pool
        $grouped = [];
        foreach ($matches as $m) {
            $arena = $m->arena_name ?: 'UNKNOWN ARENA';
            $pool  = $m->pool_name  ?: '-';

            if (!isset($grouped[$arena])) {
                $grouped[$arena] = [];
            }
            if (!isset($grouped[$arena][$pool])) {
                $grouped[$arena][$pool] = [];
            }

            // Kirim field yang dipakai FE (boleh tambah jika diperlukan)
            $grouped[$arena][$pool][] = [
                'id'                  => (int) $m->id,
                'match_number'        => is_null($m->match_number) ? null : (int) $m->match_number,
                'round_label'         => $m->round_label,
                'class_name'          => $m->class_name,
                'round_level'         => is_null($m->round_level) ? null : (int) $m->round_level,

                'blue_name'           => $m->blue_name,
                'red_name'            => $m->red_name,
                'blue_contingent'     => $m->blue_contingent,
                'red_contingent'      => $m->red_contingent,

                'participant_1_score' => $m->participant_1_score,
                'participant_2_score' => $m->participant_2_score,

                'winner_name'         => $m->winner_name,
                'status'              => $m->status,
            ];
        }

        return response()->json($grouped);
    }


  public function exportLocalMatches(\Illuminate\Http\Request $request)
    {
        $tournament = session('tournament_name');                 // wajibnya turnamen
        $arenaReq   = trim((string) $request->input('arena_name', ''));
        $arenaSess  = session('arena_name');                      // fallback kalau perlu

        // filter range partai dari query
        $from = $request->input('from'); // no partai awal
        $to   = $request->input('to');   // no partai akhir

        // normalisasi range kalau kebalik
        if (is_numeric($from) && is_numeric($to) && (int)$from > (int)$to) {
            [$from, $to] = [$to, $from];
        }

        $query = \App\Models\LocalMatch::query();

        if (!empty($tournament)) {
            $query->where('tournament_name', $tournament);
        }

        // Kalau ada filter arena dari request → pakai itu; kalau tidak, fallback ke session
        if ($arenaReq !== '') {
            $query->where('arena_name', $arenaReq);
        } elseif (!empty($arenaSess)) {
            $query->where('arena_name', $arenaSess);
        }

        // Terapkan filter range partai (match_number)
        if (is_numeric($from)) {
            $query->where('match_number', '>=', (int)$from);
        }
        if (is_numeric($to)) {
            $query->where('match_number', '<=', (int)$to);
        }

        // Urutan stabil
        $matches = $query
            ->orderBy('arena_name')
            ->orderBy('pool_name')
            ->orderBy('class_name')
            ->orderBy('round_level')
            ->orderBy('match_number')
            ->orderBy('id')
            ->get();

        // Gabungkan per arena (JS di admin juga menggabungkan semua pool)
        $grouped = $matches->groupBy('arena_name')->map(function ($arenaMatches) {
            return $arenaMatches->sortBy([
                ['match_number', 'asc'],
                ['id', 'asc'],
            ])->values();
        });

        // Info header PDF
        $selectedArena = $arenaReq !== '' ? $arenaReq : ($arenaSess ?: null);

        $pdf = \PDF::loadView('exports.local-matches', [
            'grouped'    => $grouped,         // "Arena X" => [match...]
            'arena'      => $selectedArena,   // bisa null → tampilkan "Semua Arena"
            'tournament' => $tournament,
            'from'       => is_numeric($from) ? (int)$from : null,
            'to'         => is_numeric($to)   ? (int)$to   : null,
        ])->setPaper('a4', 'landscape');

        $suffixArena = $selectedArena ? ('-'.$selectedArena) : '';
        $suffixRange = (is_numeric($from) || is_numeric($to))
            ? ('-partai'.($from ?? '').'-'.($to ?? ''))
            : '';

        return $pdf->download("daftar-pertandingan{$suffixArena}{$suffixRange}-{$tournament}.pdf");
    }










    public function exportLocalMatches_()
    {
        $arena = session('arena_name');
        $tournament = session('tournament_name');
        

        $query = \App\Models\LocalMatch::query();

        if ($arena) {
            $query->where('arena_name', $arena);
        }

        if ($tournament) {
            $query->where('tournament_name', $tournament);
        }

        $matches = $query->orderBy('arena_name')
            ->orderBy('pool_name')
            ->orderBy('class_name')
            ->orderBy('round_level')
            ->orderBy('match_number')
            ->get();

        $grouped = $matches->groupBy(['arena_name', 'pool_name'])->map(function ($pools) {
            return $pools->map(function ($matches) {
                return $matches->sortBy('match_number');
            });
        });


        $pdf = \PDF::loadView('exports.local-matches', [
            'grouped' => $grouped,
            'arena' => $arena,
            'tournament' => $tournament,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("daftar-pertandingan-{$arena}.pdf");
    }

     


    public function fetchLiveMatches(Request $request)
    {
        $arena = session('arena_name');

        $query = LocalMatch::query();

        if ($arena) {
            $query->where('arena_name', $arena);
        }

        // ✅ Filter hanya match yang sedang berlangsung
        $query->where('status', 'in_progress');

        // Urutkan berdasarkan arena, pool, kelas, dan round_level
        $matches = $query->orderBy('arena_name')
            ->orderBy('pool_name')
            ->orderBy('class_name')
            ->orderBy('round_level')
            ->orderBy('match_number')
            ->get();

        // Group by arena → pool
        $grouped = $matches->groupBy(['arena_name', 'pool_name']);

        return response()->json($grouped);
    }


    public function getBracket(Request $request)
    {
        $tournament = $request->query('tournament');
        $arena = $request->query('arena');
        $pool = $request->query('pool');

        $matches = LocalMatch::where('tournament_name', $tournament)
            ->where('arena_name', $arena)
            ->where('pool_name', $pool)
            ->orderBy('round_level')
            ->orderBy('match_number')
            ->get();

        return response()->json($matches);
    }



    // Menampilkan pertandingan berdasarkan ID
    public function show_asli($id)
    {
        $match = LocalMatch::with([
            'rounds' => function ($query) {
                $query->orderBy('round_number');
            }
        ])->findOrFail($id);

        // Hitung skor total dari judge_scores & referee_actions
        $red_score = $this->calculateScore($id, 'red');
        $blue_score = $this->calculateScore($id, 'blue');

        return response()->json([
            'id' => $match->id,
            'tournament_name' => $match->tournament_name,
            'arena_name' => $match->arena_name,
            'match_code' => $match->match_code,
            'match_number' => $match->match_number,
            'class_name' => $match->class_name,
            'status' => $match->status,
            'is_display_timer' => $match->is_display_timer,
            'round_level' => $match->round_level,
            'round_label' => $match->round_label,
            'round_duration' => $match->round_duration,
            'blue' => [
                'name' => $match->blue_name,
                'contingent' => $match->blue_contingent,
                'score' => $blue_score,
            ],
            'red' => [
                'name' => $match->red_name,
                'contingent' => $match->red_contingent,
                'score' => $red_score,
            ],
            'rounds' => $match->rounds,
            'total_rounds' => $match->total_rounds,
        ]);
    }

   public function show($id)
    {
        $match = LocalMatch::with([
            'rounds' => function ($query) {
                $query->orderBy('round_number');
            }
        ])->findOrFail($id);

        // Skor
        $red_score = $this->calculateScore($id, 'red');
        $blue_score = $this->calculateScore($id, 'blue');

        // Total penalti semua ronde (selain jatuhan)
        $bluePenalty = LocalRefereeAction::where('local_match_id', $id)
            ->where('corner', 'blue')
            ->where('action', '!=', 'jatuhan')
            ->sum('point_change');

        $redPenalty = LocalRefereeAction::where('local_match_id', $id)
            ->where('corner', 'red')
            ->where('action', '!=', 'jatuhan')
            ->sum('point_change');

        // Jatuhan
        $blueFallCount = LocalRefereeAction::where('local_match_id', $id)
            ->where('corner', 'blue')
            ->where('action', 'jatuhan')
            ->count();

        $redFallCount = LocalRefereeAction::where('local_match_id', $id)
            ->where('corner', 'red')
            ->where('action', 'jatuhan')
            ->count();

        // Warnings (global count)
        $blueWarnings = LocalRefereeAction::where('local_match_id', $id)
            ->where('corner', 'blue')
            ->whereIn('action', ['peringatan_1', 'peringatan_2'])
            ->count();

        $redWarnings = LocalRefereeAction::where('local_match_id', $id)
            ->where('corner', 'red')
            ->whereIn('action', ['peringatan_1', 'peringatan_2'])
            ->count();

        // Penalties detail untuk display
        $penaltyTypes = [
            'binaan_1', 'binaan_2',
            'teguran_1', 'teguran_2',
            'peringatan_1', 'peringatan_2'
        ];

        $penalties = LocalRefereeAction::where('local_match_id', $id)
            ->whereIn('action', $penaltyTypes)
            ->select('corner', 'action')
            ->get()
            ->groupBy('corner')
            ->map(function ($group) {
                return $group->pluck('action')->unique()->values();
            });

        // Winner logic
        $winner = null;
        if ($blue_score > $red_score) {
            $winner = 'blue';
        } elseif ($red_score > $blue_score) {
            $winner = 'red';
        } else {
            if ($bluePenalty < $redPenalty) {
                $winner = 'blue';
            } elseif ($redPenalty < $bluePenalty) {
                $winner = 'red';
            }
        }

        return response()->json([
            'id' => $match->id,
            'tournament_name' => $match->tournament_name,
            'arena_name' => $match->arena_name,
            'match_code' => $match->match_code,
            'match_number' => $match->match_number,
            'class_name' => $match->class_name,
            'status' => $match->status,
            'is_display_timer' => $match->is_display_timer,
            'round_level' => $match->round_level,
            'round_label' => $match->round_label,
            'round_duration' => $match->round_duration,
            'blue' => [
                'name' => $match->blue_name,
                'contingent' => $match->blue_contingent,
                'score' => $blue_score,
            ],
            'red' => [
                'name' => $match->red_name,
                'contingent' => $match->red_contingent,
                'score' => $red_score,
            ],
            'rounds' => $match->rounds,
            'total_rounds' => $match->total_rounds,

            // Tambahan untuk display
            'blueFallCount' => $blueFallCount,
            'redFallCount' => $redFallCount,
            'blueWarnings' => $blueWarnings,
            'redWarnings' => $redWarnings,
            'winner_corner' => $winner,
            'penalties' => $penalties,
        ]);
    }

    public function endMatch(Request $request, $id)
{
    $match = LocalMatch::findOrFail($id);

    // ===== Hitung skor akhir =====
    $blueScore = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point');
    $redScore  = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'red')->sum('point');

    $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point_change');
    $redAdjustment  = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'red')->sum('point_change');

    $totalBlue = $blueScore + $blueAdjustment;
    $totalRed  = $redScore  + $redAdjustment;

    // ===== Simpan status & skor =====
    $match->status = 'finished';
    $match->participant_1_score = $totalBlue; // BLUE
    $match->participant_2_score = $totalRed;  // RED

    // ===== Winner & reason (opsional) =====
    $request->validate([
        'winner' => 'nullable|in:red,blue,draw',
        'reason' => 'nullable|string|max:255',
    ]);

    // 1) Tentukan pemenang (request > skor)
    if ($request->filled('winner')) {
        $match->winner_corner = $request->winner === 'draw' ? null : $request->winner;
    } else {
        if     ($totalBlue > $totalRed) $match->winner_corner = 'blue';
        elseif ($totalRed  > $totalBlue) $match->winner_corner = 'red';
        else                             $match->winner_corner = null; // draw
    }

    // 2) Isi detail pemenang SESUAI corner (NORMAL)
    if (is_null($match->winner_corner)) {
        $match->winner_id         = null;
        $match->winner_name       = null;
        $match->winner_contingent = null;
    } elseif ($match->winner_corner === 'blue') {
        $match->winner_id         = $match->blue_id;
        $match->winner_name       = $match->blue_name;
        $match->winner_contingent = $match->blue_contingent;
    } else { // 'red'
        $match->winner_id         = $match->red_id;
        $match->winner_name       = $match->red_name;
        $match->winner_contingent = $match->red_contingent;
    }

    if ($request->filled('reason')) {
        $match->win_reason = $request->reason;
    }

    $match->save();

    // ===== Tutup ronde berjalan =====
    $match->rounds()->where('status', 'in_progress')->update([
        'status'   => 'finished',
        'end_time' => now(),
    ]);

    // ===== PROMOSI pemenang (STRICT: parent → slot NORMAL) =====
    if (!is_null($match->winner_corner)) {
        DB::beginTransaction();
        try {
            $children = LocalMatch::where(function ($q) use ($match) {
                $q->where('parent_match_red_id',   $match->id)   // parent_red  → BLUE
                  ->orWhere('parent_match_blue_id', $match->id);  // parent_blue → RED
            })->lockForUpdate()->get();

            foreach ($children as $child) {
                $winId   = $match->winner_id;                 // boleh null
                $winName = $match->winner_name ?? '';
                $winCont = $match->winner_contingent ?? '';

                $fillBlue = ((int)$child->parent_match_red_id   === (int)$match->id); // → BLUE
                $fillRed  = ((int)$child->parent_match_blue_id  === (int)$match->id); // → RED

                if ($fillBlue) {
                    // FORCE ke BLUE (kolom blue_*)
                    $child->blue_id         = $winId;
                    $child->blue_name       = $winName;
                    $child->blue_contingent = $winCont;

                    // Bersihkan RED bila pemenang sempat nyasar
                    if (($winId !== null && (int)$child->red_id === (int)$winId) ||
                        ($winId === null && $winName !== '' && $child->red_name === $winName)) {
                        $child->red_id = null;
                        $child->red_name = null;
                        $child->red_contingent = null;
                    }
                }

                if ($fillRed) {
                    // FORCE ke RED (kolom red_*)
                    $child->red_id         = $winId;
                    $child->red_name       = $winName;
                    $child->red_contingent = $winCont;

                    // Bersihkan BLUE bila pemenang sempat nyasar
                    if (($winId !== null && (int)$child->blue_id === (int)$winId) ||
                        ($winId === null && $winName !== '' && $child->blue_name === $winName)) {
                        $child->blue_id = null;
                        $child->blue_name = null;
                        $child->blue_contingent = null;
                    }
                }

                $child->save();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('❌ Gagal promote winner ke babak selanjutnya', [
                'parent_match_id' => $match->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    // ===== Broadcast winner ke UI (NORMAL) =====
    try {
        $map = [
            'mutlak'         => 'Menang Mutlak',
            'undur_diri'     => 'Menang Undur Diri',
            'diskualifikasi' => 'Menang Diskualifikasi',
            'wo'             => 'Menang WO',
            'walkover'       => 'Menang WO',
            'disqualified'   => 'Menang Diskualifikasi',
            'draw'           => 'Seri',
        ];
        $reasonRaw   = (string) ($match->win_reason ?? '');
        $reasonKey   = Str::of($reasonRaw)->lower()->replace(' ', '_')->value();
        $reasonLabel = $map[$reasonKey] ?? Str::title(str_replace('_', ' ', $reasonRaw));

        $isDraw = is_null($match->winner_corner);

        $payload = [
            'match_id'        => $match->id,
            'tournament_name' => $match->tournament_name ?? ($match->tournament ?? ''),
            'arena_name'      => $match->arena_name ?? '',
            'corner'          => $isDraw ? null : strtolower($match->winner_corner),
            'winner_id'       => $isDraw ? null : ($match->winner_id ?? null),
            'winner_name'     => $isDraw ? 'DRAW' : ($match->winner_name ?? '-'),
            'contingent'      => $isDraw ? '' : ($match->winner_contingent ?? '-'),
            'reason'          => $isDraw ? 'draw' : $reasonKey,
            'reason_label'    => $isDraw ? 'Seri' : $reasonLabel,
            'score' => [
                'blue' => (int) $match->participant_1_score,
                'red'  => (int) $match->participant_2_score,
            ],
            // NORMAL utk UI
            'participants' => [
                'blue' => [
                    'id'         => $match->blue_id ?? null,
                    'name'       => $match->blue_name ?? null,
                    'contingent' => $match->blue_contingent ?? null,
                ],
                'red' => [
                    'id'         => $match->red_id ?? null,
                    'name'       => $match->red_name ?? null,
                    'contingent' => $match->red_contingent ?? null,
                ],
            ],
        ];

        if ($match->status === 'finished') {
            event(new TandingWinnerAnnounced(
                $match->tournament_name ?? ($match->tournament ?? ''),
                $match->arena_name ?? '',
                $payload
            ));
        }
    } catch (\Throwable $e) {
        \Log::warning('⚠️ Gagal broadcast TandingWinnerAnnounced', [
            'match_id' => $match->id,
            'error'    => $e->getMessage(),
        ]);
    }

    return response()->json(['message' => 'Pertandingan diakhiri & pemenang dipromosikan.']);
}

   public function endMatch552(Request $request, $id)
{
    $match = LocalMatch::findOrFail($id);

    // ===== Hitung skor akhir =====
    $blueScore = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point');
    $redScore  = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'red')->sum('point');

    $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point_change');
    $redAdjustment  = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'red')->sum('point_change');

    $totalBlue = $blueScore + $blueAdjustment;
    $totalRed  = $redScore  + $redAdjustment;

    // ===== Simpan status & skor =====
    $match->status = 'finished';
    $match->participant_1_score = $totalBlue; // BLUE
    $match->participant_2_score = $totalRed;  // RED

    // ===== Winner & reason (opsional) =====
    $request->validate([
        'winner' => 'nullable|in:red,blue,draw',
        'reason' => 'nullable|string|max:255',
    ]);

    // 1) Tentukan winner_corner
    if ($request->filled('winner')) {
        $match->winner_corner = $request->winner === 'draw' ? null : $request->winner;
    } else {
        if ($totalBlue > $totalRed)      $match->winner_corner = 'blue';
        elseif ($totalRed > $totalBlue)  $match->winner_corner = 'red';
        else                              $match->winner_corner = null; // draw
    }

    // 2) Set detail pemenang sesuai corner (NORMAL)
    if (is_null($match->winner_corner)) {
        $match->winner_id         = null;
        $match->winner_name       = null;
        $match->winner_contingent = null;
    } elseif ($match->winner_corner === 'blue') {
        $match->winner_id         = $match->blue_id;
        $match->winner_name       = $match->blue_name;
        $match->winner_contingent = $match->blue_contingent;
    } else { // 'red'
        $match->winner_id         = $match->red_id;
        $match->winner_name       = $match->red_name;
        $match->winner_contingent = $match->red_contingent;
    }

    if ($request->filled('reason')) {
        $match->win_reason = $request->reason;
    }

    $match->save();

    // ===== Tutup ronde berjalan =====
    $match->rounds()->where('status', 'in_progress')->update([
        'status'   => 'finished',
        'end_time' => now(),
    ]);

    // ===== Promosi pemenang (parent → slot NORMAL) =====
    if (!is_null($match->winner_corner)) {
        DB::beginTransaction();
        try {
            $children = LocalMatch::where(function ($q) use ($match) {
                $q->where('parent_match_red_id',   $match->id)   // → BLUE
                  ->orWhere('parent_match_blue_id', $match->id); // → RED
            })->lockForUpdate()->get();

            foreach ($children as $child) {
                $winId   = $match->winner_id;
                $winName = $match->winner_name ?? '';
                $winCont = $match->winner_contingent ?? '';

                $fillBlue = ((int)$child->parent_match_red_id   === (int)$match->id); // parent_red → BLUE
                $fillRed  = ((int)$child->parent_match_blue_id  === (int)$match->id); // parent_blue → RED

                if ($fillBlue) {
                    // Force isi slot BLUE (NORMAL)
                    $child->blue_id         = $winId;
                    $child->blue_name       = $winName;
                    $child->blue_contingent = $winCont;

                    // Bersihkan jika sebelumnya pemenang nyasar ke RED
                    if (($winId !== null && (int)$child->red_id === (int)$winId) ||
                        ($winId === null && $winName !== '' && $child->red_name === $winName)) {
                        $child->red_id = null;
                        $child->red_name = null;
                        $child->red_contingent = null;
                    }
                }

                if ($fillRed) {
                    // Force isi slot RED (NORMAL)
                    $child->red_id         = $winId;
                    $child->red_name       = $winName;
                    $child->red_contingent = $winCont;

                    // Bersihkan jika sebelumnya pemenang nyasar ke BLUE
                    if (($winId !== null && (int)$child->blue_id === (int)$winId) ||
                        ($winId === null && $winName !== '' && $child->blue_name === $winName)) {
                        $child->blue_id = null;
                        $child->blue_name = null;
                        $child->blue_contingent = null;
                    }
                }

                $child->save();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('❌ Gagal promote winner ke babak selanjutnya', [
                'parent_match_id' => $match->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    // ===== Broadcast winner ke UI (NORMAL) =====
    try {
        $map = [
            'mutlak'         => 'Menang Mutlak',
            'undur_diri'     => 'Menang Undur Diri',
            'diskualifikasi' => 'Menang Diskualifikasi',
            'wo'             => 'Menang WO',
            'walkover'       => 'Menang WO',
            'disqualified'   => 'Menang Diskualifikasi',
            'draw'           => 'Seri',
        ];
        $reasonRaw   = (string) ($match->win_reason ?? '');
        $reasonKey   = Str::of($reasonRaw)->lower()->replace(' ', '_')->value();
        $reasonLabel = $map[$reasonKey] ?? Str::title(str_replace('_', ' ', $reasonRaw));

        $isDraw = is_null($match->winner_corner);

        $payload = [
            'match_id'        => $match->id,
            'tournament_name' => $match->tournament_name ?? ($match->tournament ?? ''),
            'arena_name'      => $match->arena_name ?? '',
            'corner'          => $isDraw ? null : strtolower($match->winner_corner),
            'winner_id'       => $isDraw ? null : ($match->winner_id ?? null),
            'winner_name'     => $isDraw ? 'DRAW' : ($match->winner_name ?? '-'),
            'contingent'      => $isDraw ? '' : ($match->winner_contingent ?? '-'),
            'reason'          => $isDraw ? 'draw' : $reasonKey,
            'reason_label'    => $isDraw ? 'Seri' : $reasonLabel,
            'score' => [
                'blue' => (int) $match->participant_1_score,
                'red'  => (int) $match->participant_2_score,
            ],
            // NORMAL untuk UI
            'participants' => [
                'blue' => [
                    'id'         => $match->blue_id ?? null,
                    'name'       => $match->blue_name ?? null,
                    'contingent' => $match->blue_contingent ?? null,
                ],
                'red' => [
                    'id'         => $match->red_id ?? null,
                    'name'       => $match->red_name ?? null,
                    'contingent' => $match->red_contingent ?? null,
                ],
            ],
        ];

        if ($match->status === 'finished') {
            event(new TandingWinnerAnnounced(
                $match->tournament_name ?? ($match->tournament ?? ''),
                $match->arena_name ?? '',
                $payload
            ));
        }
    } catch (\Throwable $e) {
        \Log::warning('⚠️ Gagal broadcast TandingWinnerAnnounced', [
            'match_id' => $match->id,
            'error'    => $e->getMessage(),
        ]);
    }

    return response()->json(['message' => 'Pertandingan diakhiri dan pemenang dipromosikan.']);
}

    public function endMatch534(Request $request, $id)
{
    $match = LocalMatch::findOrFail($id);

    // ===== Hitung skor akhir =====
    $blueScore = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point');
    $redScore  = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'red')->sum('point');

    $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point_change');
    $redAdjustment  = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'red')->sum('point_change');

    $totalBlue = $blueScore + $blueAdjustment;
    $totalRed  = $redScore  + $redAdjustment;

    // ===== Simpan status & skor =====
    $match->status = 'finished';
    $match->participant_1_score = $totalBlue; // BLUE
    $match->participant_2_score = $totalRed;  // RED

    // ===== Winner & reason (opsional) =====
    $request->validate([
        'winner' => 'nullable|in:red,blue,draw',
        'reason' => 'nullable|string|max:255',
    ]);

    // 1) Set winner dari request (jika ada)
    if ($request->filled('winner')) {
        if ($request->winner === 'draw') {
            $match->winner_corner     = null;
            $match->winner_id         = null;
            $match->winner_name       = null;
            $match->winner_contingent = null;
        } else {
            $corner = $request->winner; // 'blue'|'red'
            $match->winner_corner     = $corner;
            $match->winner_id         = $match->{$corner . '_id'};
            $match->winner_name       = $match->{$corner . '_name'};
            $match->winner_contingent = $match->{$corner . '_contingent'};
        }
    }

    // 2) Kalau winner belum terset → auto dari skor
    if (is_null($match->winner_corner)) {
        if ($totalBlue > $totalRed) {
            $match->winner_corner     = 'blue';
            $match->winner_id         = $match->blue_id;
            $match->winner_name       = $match->blue_name;
            $match->winner_contingent = $match->blue_contingent;
        } elseif ($totalRed > $totalBlue) {
            $match->winner_corner     = 'red';
            $match->winner_id         = $match->red_id;
            $match->winner_name       = $match->red_name;
            $match->winner_contingent = $match->red_contingent;
        } else {
            // Seri → tidak promosi
            $match->winner_corner     = null;
            $match->winner_id         = null;
            $match->winner_name       = null;
            $match->winner_contingent = null;
        }
    }

    if ($request->filled('reason')) {
        $match->win_reason = $request->reason;
    }

    $match->save();

    // ===== Tutup ronde berjalan =====
    $match->rounds()->where('status', 'in_progress')->update([
        'status'   => 'finished',
        'end_time' => now(),
    ]);

    // ===== Promosi pemenang by PARENT (tanpa syarat lain) =====
    $hasWinner = !is_null($match->winner_corner) && (
        !is_null($match->winner_id) || !empty($match->winner_name) || !empty($match->winner_contingent)
    );

    if ($hasWinner) {
        DB::beginTransaction();
        try {
            // Ambil SEMUA child yang menunjuk ke match ini
            $children = LocalMatch::where(function ($q) use ($match) {
                $q->where('parent_match_red_id',  $match->id)
                  ->orWhere('parent_match_blue_id', $match->id);
            })->lockForUpdate()->get();

            \Log::info('Promote winner: found children', [
                'parent_match_id' => $match->id,
                'children_count'  => $children->count(),
            ]);

            foreach ($children as $child) {
                $winId   = $match->winner_id;          // boleh null
                $winName = $match->winner_name ?? '';  // boleh kosong
                $winCont = $match->winner_contingent ?? '';

                $isParentRed  = ((int)$child->parent_match_red_id  === (int)$match->id);  // -> BLUE
                $isParentBlue = ((int)$child->parent_match_blue_id === (int)$match->id);  // -> RED

                if (!$isParentRed && !$isParentBlue) {
                    // Harusnya gak kejadian karena query di atas
                    \Log::warning('Child does not reference this parent (skipped)', [
                        'parent_match_id' => $match->id,
                        'child_match_id'  => $child->id,
                        'child_parent_red'=> $child->parent_match_red_id,
                        'child_parent_blue'=>$child->parent_match_blue_id,
                    ]);
                    continue;
                }

                if ($isParentRed) {
                    // parent_match_red_id => isi BLUE
                    $child->blue_id         = $winId;
                    $child->blue_name       = $winName;
                    $child->blue_contingent = $winCont;

                    // Bersihkan RED jika pemenang sempat nyasar ke RED (id atau nama sama)
                    if ((int)$child->red_id === (int)$winId && $winId !== null) {
                        $child->red_id = $child->red_name = $child->red_contingent = null;
                    } elseif ($winId === null && $child->red_name === $winName && $winName !== '') {
                        $child->red_id = null;
                        $child->red_name = null;
                        $child->red_contingent = null;
                    }
                }

                if ($isParentBlue) {
                    // parent_match_blue_id => isi RED
                    $child->red_id         = $winId;
                    $child->red_name       = $winName;
                    $child->red_contingent = $winCont;

                    // Bersihkan BLUE jika pemenang sempat nyasar ke BLUE (id atau nama sama)
                    if ((int)$child->blue_id === (int)$winId && $winId !== null) {
                        $child->blue_id = $child->blue_name = $child->blue_contingent = null;
                    } elseif ($winId === null && $child->blue_name === $winName && $winName !== '') {
                        $child->blue_id = null;
                        $child->blue_name = null;
                        $child->blue_contingent = null;
                    }
                }

                $child->save();

                \Log::info('Promoted winner into child', [
                    'child_match_id' => $child->id,
                    'to_slot'        => $isParentRed ? 'BLUE' : ($isParentBlue ? 'RED' : 'UNKNOWN'),
                    'blue_id'        => $child->blue_id,
                    'blue_name'      => $child->blue_name,
                    'red_id'         => $child->red_id,
                    'red_name'       => $child->red_name,
                ]);
            }

            if ($children->isEmpty()) {
                \Log::warning('No child match found to promote into', [
                    'finished_match_id' => $match->id,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('❌ Promote winner failed', [
                'match_id' => $match->id,
                'error'    => $e->getMessage(),
            ]);
        }
    } else {
        \Log::info('Skip promotion: no winner determined', [
            'match_id'   => $match->id,
            'blue_total' => $totalBlue,
            'red_total'  => $totalRed,
            'winner'     => $match->winner_corner,
            'winner_id'  => $match->winner_id,
            'winner_name'=> $match->winner_name,
        ]);
    }

    // ===== Broadcast winner ke UI =====
    try {
        $map = [
            'mutlak'         => 'Menang Mutlak',
            'undur_diri'     => 'Menang Undur Diri',
            'diskualifikasi' => 'Menang Diskualifikasi',
            'wo'             => 'Menang WO',
            'walkover'       => 'Menang WO',
            'disqualified'   => 'Menang Diskualifikasi',
            'draw'           => 'Seri',
        ];
        $reasonRaw   = (string) ($match->win_reason ?? '');
        $reasonKey   = Str::of($reasonRaw)->lower()->replace(' ', '_')->value();
        $reasonLabel = $map[$reasonKey] ?? Str::title(str_replace('_', ' ', $reasonRaw));

        $isDraw = is_null($match->winner_corner);

        $payload = [
            'match_id'        => $match->id,
            'tournament_name' => $match->tournament_name ?? ($match->tournament ?? ''),
            'arena_name'      => $match->arena_name ?? '',
            'corner'          => $isDraw ? null : strtolower($match->winner_corner),
            'winner_id'       => $isDraw ? null : ($match->winner_id ?? null),
            'winner_name'     => $isDraw ? 'DRAW' : ($match->winner_name ?? '-'),
            'contingent'      => $isDraw ? '' : ($match->winner_contingent ?? '-'),
            'reason'          => $isDraw ? 'draw' : $reasonKey,
            'reason_label'    => $isDraw ? 'Seri' : $reasonLabel,
            'score' => [
                'blue' => (int) $match->participant_1_score,
                'red'  => (int) $match->participant_2_score,
            ],
            'participants' => [
                'blue' => [
                    'id'         => $match->blue_id ?? null,
                    'name'       => $match->blue_name ?? null,
                    'contingent' => $match->blue_contingent ?? null,
                ],
                'red' => [
                    'id'         => $match->red_id ?? null,
                    'name'       => $match->red_name ?? null,
                    'contingent' => $match->red_contingent ?? null,
                ],
            ],
        ];

        if ($match->status === 'finished') {
            event(new TandingWinnerAnnounced(
                $match->tournament_name ?? ($match->tournament ?? ''),
                $match->arena_name ?? '',
                $payload
            ));
        }
    } catch (\Throwable $e) {
        \Log::warning('⚠️ Gagal broadcast TandingWinnerAnnounced', [
            'match_id' => $match->id,
            'error'    => $e->getMessage(),
        ]);
    }

    return response()->json(['message' => 'Pertandingan diakhiri dan pemenang diproses.']);
}

    public function endMatch522(Request $request, $id)
{
    $match = LocalMatch::findOrFail($id);

    // ===== Hitung skor akhir =====
    $blueScore = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point');
    $redScore  = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'red')->sum('point');

    $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point_change');
    $redAdjustment  = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'red')->sum('point_change');

    $totalBlue = $blueScore + $blueAdjustment;
    $totalRed  = $redScore  + $redAdjustment;

    // ===== Simpan status & skor =====
    $match->status = 'finished';
    $match->participant_1_score = $totalBlue; // BLUE
    $match->participant_2_score = $totalRed;  // RED

    // ===== Winner & reason (opsional) =====
    $request->validate([
        'winner' => 'nullable|in:red,blue,draw',
        'reason' => 'nullable|string|max:255',
    ]);

    if ($request->filled('winner')) {
        if ($request->winner === 'draw') {
            $match->winner_corner     = null;
            $match->winner_id         = null;
            $match->winner_name       = null;
            $match->winner_contingent = null;
        } else {
            $corner = $request->winner; // 'blue' | 'red'
            $match->winner_corner     = $corner;
            $match->winner_id         = $match->{$corner . '_id'};
            $match->winner_name       = $match->{$corner . '_name'};
            $match->winner_contingent = $match->{$corner . '_contingent'};
        }
    }

    // Auto tentukan winner dari skor jika belum terset
    if (is_null($match->winner_corner)) {
        if ($totalBlue > $totalRed) {
            $match->winner_corner     = 'blue';
            $match->winner_id         = $match->blue_id;
            $match->winner_name       = $match->blue_name;
            $match->winner_contingent = $match->blue_contingent;
        } elseif ($totalRed > $totalBlue) {
            $match->winner_corner     = 'red';
            $match->winner_id         = $match->red_id;
            $match->winner_name       = $match->red_name;
            $match->winner_contingent = $match->red_contingent;
        } else {
            // Seri → tidak promosi
            $match->winner_corner     = null;
            $match->winner_id         = null;
            $match->winner_name       = null;
            $match->winner_contingent = null;
        }
    }

    if ($request->filled('reason')) {
        $match->win_reason = $request->reason;
    }

    $match->save();

    // ===== Tutup ronde berjalan =====
    $match->rounds()->where('status', 'in_progress')->update([
        'status'   => 'finished',
        'end_time' => now(),
    ]);

    // ===== Promosi pemenang BERDASARKAN PARENT (enforce mapping) =====
    // Syarat: bukan draw & punya data pemenang minimal nama/id
    $hasWinner = !is_null($match->winner_corner) && (
        !is_null($match->winner_id) || !empty($match->winner_name)
    );

    if ($hasWinner) {
        DB::beginTransaction();
        try {
            // Ambil semua child yang menunjuk ke match ini
            $children = LocalMatch::where(function ($q) use ($match) {
                $q->where('parent_match_red_id', $match->id)
                  ->orWhere('parent_match_blue_id', $match->id);
            })->lockForUpdate()->get();

            foreach ($children as $child) {
                $winId   = $match->winner_id;
                $winName = $match->winner_name;
                $winCont = $match->winner_contingent;

                $toBlue = ((int)$child->parent_match_red_id  === (int)$match->id); // parent_red -> BLUE
                $toRed  = ((int)$child->parent_match_blue_id === (int)$match->id); // parent_blue -> RED

                if ($toBlue) {
                    // FORCE tulis ke BLUE
                    $child->blue_id         = $winId;
                    $child->blue_name       = $winName;
                    $child->blue_contingent = $winCont;

                    // Jika pemenang sempat nyasar ke RED, kosongkan RED (hanya kalau sama orangnya)
                    if ((int)$child->red_id === (int)$winId) {
                        $child->red_id         = null;
                        $child->red_name       = null;
                        $child->red_contingent = null;
                    }
                } elseif ($toRed) {
                    // FORCE tulis ke RED
                    $child->red_id         = $winId;
                    $child->red_name       = $winName;
                    $child->red_contingent = $winCont;

                    // Jika pemenang sempat nyasar ke BLUE, kosongkan BLUE (hanya kalau sama orangnya)
                    if ((int)$child->blue_id === (int)$winId) {
                        $child->blue_id         = null;
                        $child->blue_name       = null;
                        $child->blue_contingent = null;
                    }
                } else {
                    // Tidak cocok parent manapun → kemungkinan wiring child salah saat generate bracket
                    \Log::warning('Winner does not match any parent of child match (cek wiring child)', [
                        'finished_match_id' => $match->id,
                        'child_match_id'    => $child->id,
                        'parent_red_id'     => $child->parent_match_red_id,
                        'parent_blue_id'    => $child->parent_match_blue_id,
                    ]);
                }

                $child->save();
            }

            if ($children->isEmpty()) {
                \Log::warning('No child match found for finished match (tidak ada yang menunjuk ke parent ini)', [
                    'finished_match_id' => $match->id,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('❌ Promote winner failed', [
                'match_id' => $match->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    // ===== Broadcast winner ke UI =====
    try {
        $map = [
            'mutlak'         => 'Menang Mutlak',
            'undur_diri'     => 'Menang Undur Diri',
            'diskualifikasi' => 'Menang Diskualifikasi',
            'wo'             => 'Menang WO',
            'walkover'       => 'Menang WO',
            'disqualified'   => 'Menang Diskualifikasi',
            'draw'           => 'Seri',
        ];
        $reasonRaw   = (string) ($match->win_reason ?? '');
        $reasonKey   = Str::of($reasonRaw)->lower()->replace(' ', '_')->value();
        $reasonLabel = $map[$reasonKey] ?? Str::title(str_replace('_', ' ', $reasonRaw));

        $isDraw = is_null($match->winner_corner);

        $payload = [
            'match_id'        => $match->id,
            'tournament_name' => $match->tournament_name ?? ($match->tournament ?? ''),
            'arena_name'      => $match->arena_name ?? '',
            'corner'          => $isDraw ? null : strtolower($match->winner_corner),
            'winner_id'       => $isDraw ? null : ($match->winner_id ?? null),
            'winner_name'     => $isDraw ? 'DRAW' : ($match->winner_name ?? '-'),
            'contingent'      => $isDraw ? '' : ($match->winner_contingent ?? '-'),
            'reason'          => $isDraw ? 'draw' : $reasonKey,
            'reason_label'    => $isDraw ? 'Seri' : $reasonLabel,
            'score' => [
                'blue' => (int) $match->participant_1_score,
                'red'  => (int) $match->participant_2_score,
            ],
            'participants' => [
                'blue' => [
                    'id'         => $match->blue_id ?? null,
                    'name'       => $match->blue_name ?? null,
                    'contingent' => $match->blue_contingent ?? null,
                ],
                'red' => [
                    'id'         => $match->red_id ?? null,
                    'name'       => $match->red_name ?? null,
                    'contingent' => $match->red_contingent ?? null,
                ],
            ],
        ];

        if ($match->status === 'finished') {
            event(new TandingWinnerAnnounced(
                $match->tournament_name ?? ($match->tournament ?? ''),
                $match->arena_name ?? '',
                $payload
            ));
        }
    } catch (\Throwable $e) {
        \Log::warning('⚠️ Gagal broadcast TandingWinnerAnnounced', [
            'match_id' => $match->id,
            'error'    => $e->getMessage(),
        ]);
    }

    return response()->json(['message' => 'Pertandingan diakhiri dan pemenang diproses.']);
}

    
public function endMatch___HHH(Request $request, $id)
{
    $match = LocalMatch::findOrFail($id);

    // ===== Hitung skor akhir =====
    $blueScore = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point');
    $redScore  = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'red')->sum('point');

    $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point_change');
    $redAdjustment  = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'red')->sum('point_change');

    $totalBlue = $blueScore + $blueAdjustment;
    $totalRed  = $redScore  + $redAdjustment;

    // ===== Simpan status & skor =====
    $match->status = 'finished';
    $match->participant_1_score = $totalBlue; // BLUE
    $match->participant_2_score = $totalRed;  // RED

    // ===== Winner & reason opsional =====
    $request->validate([
        'winner' => 'nullable|in:red,blue,draw',
        'reason' => 'nullable|string|max:255',
    ]);

    // Jika client kasih winner → pakai itu
    if ($request->filled('winner')) {
        if ($request->winner === 'draw') {
            $match->winner_corner     = null;
            $match->winner_id         = null;
            $match->winner_name       = null;
            $match->winner_contingent = null;
        } else {
            $corner = $request->winner; // 'blue'|'red'
            $match->winner_corner     = $corner;
            $match->winner_id         = $match->{$corner . '_id'};
            $match->winner_name       = $match->{$corner . '_name'};
            $match->winner_contingent = $match->{$corner . '_contingent'};
        }
    }
    // Kalau winner belum ada → auto tentukan dari skor
    if (is_null($match->winner_corner)) {
        if ($totalBlue > $totalRed) {
            $match->winner_corner     = 'blue';
            $match->winner_id         = $match->blue_id;
            $match->winner_name       = $match->blue_name;
            $match->winner_contingent = $match->blue_contingent;
        } elseif ($totalRed > $totalBlue) {
            $match->winner_corner     = 'red';
            $match->winner_id         = $match->red_id;
            $match->winner_name       = $match->red_name;
            $match->winner_contingent = $match->red_contingent;
        } else {
            // seri → tidak promosi
            $match->winner_corner     = null;
            $match->winner_id         = null;
            $match->winner_name       = null;
            $match->winner_contingent = null;
        }
    }

    // Reason kalau ada aja
    if ($request->filled('reason')) {
        $match->win_reason = $request->reason;
    }

    $match->save();

    // ===== Tutup ronde berjalan =====
    $match->rounds()->where('status', 'in_progress')->update([
        'status'   => 'finished',
        'end_time' => now(),
    ]);

    // ===== Promosi pemenang by PARENT (fix "nyasar ke merah") =====
    // Syarat: bukan draw & punya winner_id atau winner_name (biar minimal namanya terset)
    $hasWinner = !is_null($match->winner_corner) && (
        !is_null($match->winner_id) || !empty($match->winner_name)
    );

    if ($hasWinner) {
        DB::beginTransaction();
        try {
            // Cari child yang menunjuk ke match ini
            $nextMatches = LocalMatch::where(function ($q) use ($match) {
                $q->where('parent_match_red_id', $match->id)
                  ->orWhere('parent_match_blue_id', $match->id);
            })->lockForUpdate()->get();

            foreach ($nextMatches as $nextMatch) {
                $winId   = $match->winner_id;
                $winName = $match->winner_name;
                $winCont = $match->winner_contingent;

                $pRed  = (int) $nextMatch->parent_match_red_id;
                $pBlue = (int) $nextMatch->parent_match_blue_id;

                $goesToBlue = ($pRed  === (int)$match->id); // parent_match_red_id -> BLUE
                $goesToRed  = ($pBlue === (int)$match->id); // parent_match_blue_id -> RED

                if ($goesToBlue) {
                    // TULIS KE BLUE, hapus kalau sempat nyasar ke RED
                    $nextMatch->blue_id         = $winId;
                    $nextMatch->blue_name       = $winName;
                    $nextMatch->blue_contingent = $winCont;

                    if ((int)$nextMatch->red_id === (int)$winId) {
                        $nextMatch->red_id         = null;
                        $nextMatch->red_name       = null;
                        $nextMatch->red_contingent = null;
                    }
                } elseif ($goesToRed) {
                    // TULIS KE RED, hapus kalau sempat nyasar ke BLUE
                    $nextMatch->red_id         = $winId;
                    $nextMatch->red_name       = $winName;
                    $nextMatch->red_contingent = $winCont;

                    if ((int)$nextMatch->blue_id === (int)$winId) {
                        $nextMatch->blue_id         = null;
                        $nextMatch->blue_name       = null;
                        $nextMatch->blue_contingent = null;
                    }
                } else {
                    // Tidak cocok parent manapun → log buat debug
                    \Log::warning('Winner does not match any parent of child match', [
                        'finished_match_id' => $match->id,
                        'child_match_id'    => $nextMatch->id,
                        'parent_red_id'     => $nextMatch->parent_match_red_id,
                        'parent_blue_id'    => $nextMatch->parent_match_blue_id,
                    ]);
                }

                $nextMatch->save();
            }

            // Kalau sama sekali ga ketemu child → log juga
            if ($nextMatches->isEmpty()) {
                \Log::warning('No child match found for parent when promoting winner', [
                    'finished_match_id' => $match->id,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('❌ Gagal mempromosikan pemenang ke babak selanjutnya', [
                'match_id' => $match->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    // ===== Broadcast winner ke UI =====
    try {
        $map = [
            'mutlak'         => 'Menang Mutlak',
            'undur_diri'     => 'Menang Undur Diri',
            'diskualifikasi' => 'Menang Diskualifikasi',
            'wo'             => 'Menang WO',
            'walkover'       => 'Menang WO',
            'disqualified'   => 'Menang Diskualifikasi',
            'draw'           => 'Seri',
        ];
        $reasonRaw   = (string) ($match->win_reason ?? '');
        $reasonKey   = Str::of($reasonRaw)->lower()->replace(' ', '_')->value();
        $reasonLabel = $map[$reasonKey] ?? Str::title(str_replace('_', ' ', $reasonRaw));

        $isDraw = is_null($match->winner_corner);

        $payload = [
            'match_id'        => $match->id,
            'tournament_name' => $match->tournament_name ?? ($match->tournament ?? ''),
            'arena_name'      => $match->arena_name ?? '',
            'corner'          => $isDraw ? null : strtolower($match->winner_corner),
            'winner_id'       => $isDraw ? null : ($match->winner_id ?? null),
            'winner_name'     => $isDraw ? 'DRAW' : ($match->winner_name ?? '-'),
            'contingent'      => $isDraw ? '' : ($match->winner_contingent ?? '-'),
            'reason'          => $isDraw ? 'draw' : $reasonKey,
            'reason_label'    => $isDraw ? 'Seri' : $reasonLabel,
            'score' => [
                'blue' => (int) $match->participant_1_score,
                'red'  => (int) $match->participant_2_score,
            ],
            'participants' => [
                'blue' => [
                    'id'         => $match->blue_id ?? null,
                    'name'       => $match->blue_name ?? null,
                    'contingent' => $match->blue_contingent ?? null,
                ],
                'red' => [
                    'id'         => $match->red_id ?? null,
                    'name'       => $match->red_name ?? null,
                    'contingent' => $match->red_contingent ?? null,
                ],
            ],
        ];

        if ($match->status === 'finished') {
            event(new TandingWinnerAnnounced(
                $match->tournament_name ?? ($match->tournament ?? ''),
                $match->arena_name ?? '',
                $payload
            ));
        }
    } catch (\Throwable $e) {
        \Log::warning('⚠️ Gagal broadcast TandingWinnerAnnounced', [
            'match_id' => $match->id,
            'error'    => $e->getMessage(),
        ]);
    }

    return response()->json(['message' => 'Pertandingan diakhiri dan pemenang diproses.']);
}
public function endMatch__bisa(Request $request, $id)
{
    $match = LocalMatch::findOrFail($id);

    // ===== Hitung skor akhir =====
    $blueScore = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point');
    $redScore  = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'red')->sum('point');

    $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point_change');
    $redAdjustment  = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'red')->sum('point_change');

    $totalBlue = $blueScore + $blueAdjustment;
    $totalRed  = $redScore  + $redAdjustment;

    // ===== Simpan status & skor =====
    $match->status = 'finished';
    $match->participant_1_score = $totalBlue;
    $match->participant_2_score = $totalRed;

    // ===== Simpan winner (kalau dikirim) =====
    if ($request->filled('winner') && $request->filled('reason')) {
        $request->validate([
            'winner' => 'in:red,blue,draw',
            'reason' => 'string|max:255',
        ]);

        if ($request->winner === 'draw') {
            $match->winner_corner     = null;
            $match->winner_id         = null;
            $match->winner_name       = null;
            $match->winner_contingent = null;
        } else {
            $corner = $request->winner; // 'blue' | 'red'
            $match->winner_corner     = $corner;
            $match->winner_id         = $match->{$corner . '_id'};
            $match->winner_name       = $match->{$corner . '_name'};
            $match->winner_contingent = $match->{$corner . '_contingent'};
        }

        $match->win_reason = $request->reason;
    }

    $match->save();

    // ===== Tutup ronde berjalan =====
    $match->rounds()->where('status', 'in_progress')->update([
        'status'   => 'finished',
        'end_time' => now(),
    ]);

    // ===== Push pemenang ke babak selanjutnya (berdasarkan PARENT, bukan corner) =====
    // Syarat: ada pemenang & bukan draw
    if (!empty($match->winner_corner)) {
        DB::beginTransaction();
        try {
            // Ambil child match yang menunjuk ke match ini sebagai parent
            $nextMatches = LocalMatch::where(function ($q) use ($match) {
                $q->where('parent_match_red_id', $match->id)     // parent merah -> BLUE
                  ->orWhere('parent_match_blue_id', $match->id); // parent biru  -> RED
            })->lockForUpdate()->get();

            foreach ($nextMatches as $nextMatch) {
                $winId   = $match->winner_id;
                $winName = $match->winner_name;
                $winCont = $match->winner_contingent;

                // CASE A: parent_match_red_id -> harus isi BLUE (participant_1)
                if ((int)$nextMatch->parent_match_red_id === (int)$match->id) {
                    if (empty($nextMatch->blue_id)) {
                        // Slot BLUE kosong → isi
                        $nextMatch->blue_id         = $winId;
                        $nextMatch->blue_name       = $winName;
                        $nextMatch->blue_contingent = $winCont;

                        // Kalau sebelumnya (karena bug) pemenang ini nyasar ke RED, kosongkan RED
                        if ((int)$nextMatch->red_id === (int)$winId) {
                            $nextMatch->red_id         = null;
                            $nextMatch->red_name       = null;
                            $nextMatch->red_contingent = null;
                        }
                    } else {
                        // BLUE sudah terisi. Kalau terisi oleh pemenang yang sama (duplikasi), bersihkan RED jika sama.
                        if ((int)$nextMatch->blue_id === (int)$winId && (int)$nextMatch->red_id === (int)$winId) {
                            $nextMatch->red_id         = null;
                            $nextMatch->red_name       = null;
                            $nextMatch->red_contingent = null;
                        } elseif ((int)$nextMatch->blue_id !== (int)$winId) {
                            // Sudah terisi orang lain → jangan overwrite. Log aja.
                            \Log::warning('BLUE slot already filled by another participant, skip overwrite', [
                                'child_match_id' => $nextMatch->id,
                                'expected_parent'=> 'parent_match_red_id',
                                'winner_id'      => $winId,
                                'blue_id'        => $nextMatch->blue_id,
                                'red_id'         => $nextMatch->red_id,
                            ]);
                        }
                    }
                }

                // CASE B: parent_match_blue_id -> harus isi RED (participant_2)
                if ((int)$nextMatch->parent_match_blue_id === (int)$match->id) {
                    if (empty($nextMatch->red_id)) {
                        // Slot RED kosong → isi
                        $nextMatch->red_id         = $winId;
                        $nextMatch->red_name       = $winName;
                        $nextMatch->red_contingent = $winCont;

                        // Kalau sebelumnya (karena bug) pemenang ini nyasar ke BLUE, kosongkan BLUE
                        if ((int)$nextMatch->blue_id === (int)$winId) {
                            $nextMatch->blue_id         = null;
                            $nextMatch->blue_name       = null;
                            $nextMatch->blue_contingent = null;
                        }
                    } else {
                        // RED sudah terisi. Kalau duplikat di BLUE & RED, bersihkan BLUE.
                        if ((int)$nextMatch->red_id === (int)$winId && (int)$nextMatch->blue_id === (int)$winId) {
                            $nextMatch->blue_id         = null;
                            $nextMatch->blue_name       = null;
                            $nextMatch->blue_contingent = null;
                        } elseif ((int)$nextMatch->red_id !== (int)$winId) {
                            // Sudah terisi orang lain → jangan overwrite. Log aja.
                            \Log::warning('RED slot already filled by another participant, skip overwrite', [
                                'child_match_id' => $nextMatch->id,
                                'expected_parent'=> 'parent_match_blue_id',
                                'winner_id'      => $winId,
                                'blue_id'        => $nextMatch->blue_id,
                                'red_id'         => $nextMatch->red_id,
                            ]);
                        }
                    }
                }

                $nextMatch->save();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('❌ Gagal mempromosikan pemenang ke babak selanjutnya', [
                'match_id' => $match->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    // ===== Broadcast winner ke UI =====
    try {
        $map = [
            'mutlak'         => 'Menang Mutlak',
            'undur_diri'     => 'Menang Undur Diri',
            'diskualifikasi' => 'Menang Diskualifikasi',
            'wo'             => 'Menang WO',
            'walkover'       => 'Menang WO',
            'disqualified'   => 'Menang Diskualifikasi',
            'draw'           => 'Seri',
        ];
        $reasonRaw   = (string) ($match->win_reason ?? '');
        $reasonKey   = Str::of($reasonRaw)->lower()->replace(' ', '_')->value();
        $reasonLabel = $map[$reasonKey] ?? Str::title(str_replace('_', ' ', $reasonRaw));

        $isDraw = ($match->winner_corner === null);

        $payload = [
            'match_id'        => $match->id,
            'tournament_name' => $match->tournament_name ?? ($match->tournament ?? ''),
            'arena_name'      => $match->arena_name ?? '',
            'corner'          => $isDraw ? null : strtolower($match->winner_corner),
            'winner_id'       => $isDraw ? null : ($match->winner_id ?? null),
            'winner_name'     => $isDraw ? 'DRAW' : ($match->winner_name ?? '-'),
            'contingent'      => $isDraw ? '' : ($match->winner_contingent ?? '-'),
            'reason'          => $isDraw ? 'draw' : $reasonKey,
            'reason_label'    => $isDraw ? 'Seri' : $reasonLabel,
            'score' => [
                'blue' => (int) $match->participant_1_score,
                'red'  => (int) $match->participant_2_score,
            ],
            'participants' => [
                'blue' => [
                    'id'         => $match->blue_id ?? null,
                    'name'       => $match->blue_name ?? null,
                    'contingent' => $match->blue_contingent ?? null,
                ],
                'red' => [
                    'id'         => $match->red_id ?? null,
                    'name'       => $match->red_name ?? null,
                    'contingent' => $match->red_contingent ?? null,
                ],
            ],
        ];

        if ($match->status === 'finished') {
            event(new TandingWinnerAnnounced(
                $match->tournament_name ?? ($match->tournament ?? ''),
                $match->arena_name ?? '',
                $payload
            ));
        }
    } catch (\Throwable $e) {
        \Log::warning('⚠️ Gagal broadcast TandingWinnerAnnounced', [
            'match_id' => $match->id,
            'error'    => $e->getMessage(),
        ]);
    }

    return response()->json(['message' => 'Pertandingan diakhiri dan pemenang diproses.']);
}


    public function endMatchB(Request $request, $id)
    {
        $match = LocalMatch::findOrFail($id);

        // ===== Hitung skor akhir =====
        $blueScore = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point');
        $redScore  = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'red')->sum('point');

        $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point_change');
        $redAdjustment  = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'red')->sum('point_change');

        $totalBlue = $blueScore + $blueAdjustment;
        $totalRed  = $redScore  + $redAdjustment;

        $match->status = 'finished';
        $match->participant_1_score = $totalBlue;
        $match->participant_2_score = $totalRed;

        // ===== Simpan winner (kalau dikirim) =====
        if ($request->filled('winner') && $request->filled('reason')) {
            $request->validate([
                'winner' => 'in:red,blue,draw',
                'reason' => 'string|max:255',
            ]);

            if ($request->winner === 'draw') {
                $match->winner_corner     = null;
                $match->winner_id         = null;
                $match->winner_name       = null;
                $match->winner_contingent = null;
            } else {
                $corner = $request->winner; // 'blue'|'red'
                $match->winner_corner     = $corner;
                $match->winner_id         = $match->{$corner . '_id'};
                $match->winner_name       = $match->{$corner . '_name'};
                $match->winner_contingent = $match->{$corner . '_contingent'};
            }

            $match->win_reason = $request->reason;
        }

        $match->save();

        // Tutup ronde yang masih in_progress
        $match->rounds()->where('status', 'in_progress')->update([
            'status'   => 'finished',
            'end_time' => now(),
        ]);

        // ===== Push pemenang ke babak selanjutnya (FIXED mapping) =====
        // Syarat: ada winner dan bukan draw
        if (!empty($match->winner_corner)) {
            $nextMatches = LocalMatch::where(function ($q) use ($match) {
                $q->where('parent_match_red_id', $match->id)   // parent merah -> masuk BLUE
                ->orWhere('parent_match_blue_id', $match->id); // parent biru -> masuk RED
            })->get();

            foreach ($nextMatches as $nextMatch) {
                $slot = null;

                // ⚠️ FIX: parent_match_red_id -> BLUE (participant_1)
                if ((int) $nextMatch->parent_match_red_id === (int) $match->id) {
                    $nextMatch->blue_id         = $match->winner_id;
                    $nextMatch->blue_name       = $match->winner_name;
                    $nextMatch->blue_contingent = $match->winner_contingent;
                    $slot = 1; // participant_1 (blue)
                }

                // ⚠️ FIX: parent_match_blue_id -> RED (participant_2)
                if ((int) $nextMatch->parent_match_blue_id === (int) $match->id) {
                    $nextMatch->red_id         = $match->winner_id;
                    $nextMatch->red_name       = $match->winner_name;
                    $nextMatch->red_contingent = $match->winner_contingent;
                    $slot = 2; // participant_2 (red)
                }

                $nextMatch->save();

                // (opsional) sinkron ke server pusat pakai $slot kalau diperlukan
            }
        }

        // ===== Broadcast winner (biar UI update) =====
        try {
            $map = [
                'mutlak'         => 'Menang Mutlak',
                'undur_diri'     => 'Menang Undur Diri',
                'diskualifikasi' => 'Menang Diskualifikasi',
                'wo'             => 'Menang WO',
                'walkover'       => 'Menang WO',
                'disqualified'   => 'Menang Diskualifikasi',
                'draw'           => 'Seri',
            ];
            $reasonRaw   = (string) ($match->win_reason ?? '');
            $reasonKey   = \Illuminate\Support\Str::of($reasonRaw)->lower()->replace(' ', '_')->value();
            $reasonLabel = $map[$reasonKey] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $reasonRaw));

            $isDraw = ($match->winner_corner === null);

            $payload = [
                'match_id'        => $match->id,
                'tournament_name' => $match->tournament_name ?? ($match->tournament ?? ''),
                'arena_name'      => $match->arena_name ?? '',
                'corner'          => $isDraw ? null : strtolower($match->winner_corner),
                'winner_id'       => $isDraw ? null : ($match->winner_id ?? null),
                'winner_name'     => $isDraw ? 'DRAW' : ($match->winner_name ?? '-'),
                'contingent'      => $isDraw ? '' : ($match->winner_contingent ?? '-'),
                'reason'          => $isDraw ? 'draw' : $reasonKey,
                'reason_label'    => $isDraw ? 'Seri' : $reasonLabel,
                'score' => [
                    'blue' => (int) $match->participant_1_score,
                    'red'  => (int) $match->participant_2_score,
                ],
                'participants' => [
                    'blue' => [
                        'id'         => $match->blue_id ?? null,
                        'name'       => $match->blue_name ?? null,
                        'contingent' => $match->blue_contingent ?? null,
                    ],
                    'red' => [
                        'id'         => $match->red_id ?? null,
                        'name'       => $match->red_name ?? null,
                        'contingent' => $match->red_contingent ?? null,
                    ],
                ],
            ];

            if ($match->status === 'finished') {
                event(new TandingWinnerAnnounced(
                    $match->tournament_name ?? ($match->tournament ?? ''),
                    $match->arena_name ?? '',
                    $payload
                ));
            }
        } catch (\Throwable $e) {
            \Log::warning('⚠️ Gagal broadcast TandingWinnerAnnounced', [
                'match_id' => $match->id,
                'error'    => $e->getMessage(),
            ]);
        }

        return response()->json(['message' => 'Pertandingan diakhiri dan pemenang dipromosikan.']);
    }


    public function endMatch_sblm_batam(Request $request, $id)
    {
        $match = LocalMatch::findOrFail($id);

        $blueScore = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point');
        $redScore  = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'red')->sum('point');

        $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point_change');
        $redAdjustment  = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'red')->sum('point_change');

        $totalBlue = $blueScore + $blueAdjustment;
        $totalRed  = $redScore  + $redAdjustment;

        // Simpan status & skor akhir
        $match->status = 'finished';
        $match->participant_1_score = $totalBlue;
        $match->participant_2_score = $totalRed;

        // Validasi input winner & reason (jika dikirim)
        if ($request->filled('winner') && $request->filled('reason')) {
            $request->validate([
                'winner' => 'in:red,blue,draw',
                'reason' => 'string|max:255',
            ]);

            if ($request->winner === 'draw') {
                $match->winner_corner     = null;
                $match->winner_id         = null;
                $match->winner_name       = null;
                $match->winner_contingent = null;
            } else {
                $corner = $request->winner; // 'blue'|'red'
                $match->winner_corner     = $corner;
                $match->winner_id         = $match->{$corner . '_id'};
                $match->winner_name       = $match->{$corner . '_name'};
                $match->winner_contingent = $match->{$corner . '_contingent'};
            }

            $match->win_reason = $request->reason;
        }

        $match->save();

        // Tutup ronde berjalan
        $match->rounds()->where('status', 'in_progress')->update([
            'status'   => 'finished',
            'end_time' => now(),
        ]);

        // ===== (Optional) Sync ke server pusat - tetap dikomentari sesuai kode kamu =====
        /*
        $baseUrl = $this->live_server;
        $client = new \GuzzleHttp\Client();
        try {
            $client->post($baseUrl . '/api/update-tanding-match-status', [
                'json' => [
                    'remote_match_id'     => $match->remote_match_id,
                    'status'              => 'finished',
                    'participant_1_score' => $totalBlue,
                    'participant_2_score' => $totalRed,
                    'winner_id'           => $match->winner_id,
                    'winner_corner'       => $match->winner_corner,
                    'win_reason'          => $match->win_reason,
                ],
                'timeout' => 5,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('⚠️ Gagal kirim status finished ke server pusat', [
                'remote_match_id' => $match->remote_match_id,
                'error'           => $e->getMessage(),
            ]);
        }
        */

        // Promote pemenang ke pertandingan berikutnya (seperti kode kamu)
        if ($match->winner_corner && $match->winner_corner !== 'draw') {
            $nextMatches = LocalMatch::where(function ($query) use ($match) {
                $query->where('parent_match_red_id', $match->id)
                    ->orWhere('parent_match_blue_id', $match->id);
            })->get();

            foreach ($nextMatches as $nextMatch) {
                $slot = null;

                if ($nextMatch->parent_match_red_id == $match->id) {
                    $nextMatch->red_id         = $match->winner_id;
                    $nextMatch->red_name       = $match->winner_name;
                    $nextMatch->red_contingent = $match->winner_contingent;
                    $slot = 2; // merah = participant_2
                }

                if ($nextMatch->parent_match_blue_id == $match->id) {
                    $nextMatch->blue_id         = $match->winner_id;
                    $nextMatch->blue_name       = $match->winner_name;
                    $nextMatch->blue_contingent = $match->winner_contingent;
                    $slot = 1; // biru = participant_1
                }

                $nextMatch->save();

                // (Optional) update server pusat - tetap dikomentari
                /*
                if ($nextMatch->remote_match_id && $slot) {
                    try {
                        $client->post($baseUrl . '/api/update-next-match-slot', [
                            'json' => [
                                'remote_match_id' => $nextMatch->remote_match_id,
                                'slot'            => $slot,
                                'winner_id'       => $match->winner_id,
                            ],
                            'timeout' => 5,
                        ]);
                    } catch (\Throwable $e) {
                        \Log::warning('⚠️ Gagal update next match di server pusat', [
                            'remote_match_id' => $nextMatch->remote_match_id,
                            'slot'            => $slot,
                            'error'           => $e->getMessage(),
                        ]);
                    }
                }
                */
            }
        }

        // ======== ✅ Broadcast pengumuman pemenang ke channel arena.match.{slug} ========
        try {
            // Map label alasan (biar rapi di UI)
            $map = [
                'mutlak'         => 'Menang Mutlak',
                'undur_diri'     => 'Menang Undur Diri',
                'diskualifikasi' => 'Menang Diskualifikasi',
                'wo'             => 'Menang WO',
                'walkover'       => 'Menang WO',
                'disqualified'   => 'Menang Diskualifikasi',
                'draw'           => 'Seri',
            ];
            $reasonRaw   = (string) ($match->win_reason ?? '');
            $reasonKey   = Str::of($reasonRaw)->lower()->replace(' ', '_')->value();
            $reasonLabel = $map[$reasonKey] ?? Str::title(str_replace('_', ' ', $reasonRaw));

            $isDraw = ($match->winner_corner === null) || ($request->winner === 'draw');

            $payload = [
                'match_id'        => $match->id,
                'tournament_name' => $match->tournament_name ?? ($match->tournament ?? ''),
                'arena_name'      => $match->arena_name ?? '',
                'corner'          => $isDraw ? null : ($match->winner_corner ? strtolower($match->winner_corner) : null),
                'winner_id'       => $isDraw ? null : ($match->winner_id ?? null),
                'winner_name'     => $isDraw ? 'DRAW' : ($match->winner_name ?? '-'),
                'contingent'      => $isDraw ? '' : ($match->winner_contingent ?? '-'),
                'reason'          => $isDraw ? 'draw' : $reasonKey,
                'reason_label'    => $isDraw ? 'Seri' : $reasonLabel,
                'score' => [
                    'blue' => (int) $match->participant_1_score,
                    'red'  => (int) $match->participant_2_score,
                ],
                'participants' => [
                    'blue' => [
                        'id'         => $match->blue_id ?? null,
                        'name'       => $match->blue_name ?? null,
                        'contingent' => $match->blue_contingent ?? null,
                    ],
                    'red' => [
                        'id'         => $match->red_id ?? null,
                        'name'       => $match->red_name ?? null,
                        'contingent' => $match->red_contingent ?? null,
                    ],
                ],
            ];

            // Hanya broadcast jika status finished (harusnya iya)
            if ($match->status === 'finished') {
                event(new TandingWinnerAnnounced(
                    $match->tournament_name ?? ($match->tournament ?? ''),
                    $match->arena_name ?? '',
                    $payload
                ));
            }
        } catch (\Throwable $e) {
            \Log::warning('⚠️ Gagal broadcast TandingWinnerAnnounced', [
                'match_id' => $match->id,
                'error'    => $e->getMessage(),
            ]);
        }

        return response()->json(['message' => 'Pertandingan diakhiri dan pemenang disimpan.']);
    }


    public function endMatch_mau_lss(Request $request, $id)
    {
        $match = LocalMatch::findOrFail($id);

        $blueScore = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point');
        $redScore = \App\Models\LocalValidScore::where('local_match_id', $match->id)->where('corner', 'red')->sum('point');

        $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'blue')->sum('point_change');
        $redAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $match->id)->where('corner', 'red')->sum('point_change');

        $totalBlue = $blueScore + $blueAdjustment;
        $totalRed = $redScore + $redAdjustment;

        $match->status = 'finished';
        $match->participant_1_score = $totalBlue;
        $match->participant_2_score = $totalRed;

        if ($request->filled('winner') && $request->filled('reason')) {
            $request->validate([
                'winner' => 'in:red,blue,draw',
                'reason' => 'string|max:255',
            ]);

            if ($request->winner === 'draw') {
                $match->winner_corner = null;
                $match->winner_id = null;
                $match->winner_name = null;
                $match->winner_contingent = null;
            } else {
                $corner = $request->winner;
                $match->winner_corner = $corner;
                $match->winner_id = $match->{$corner . '_id'};
                $match->winner_name = $match->{$corner . '_name'};
                $match->winner_contingent = $match->{$corner . '_contingent'};
            }

            $match->win_reason = $request->reason;
        }

        $match->save();

        $match->rounds()->where('status', 'in_progress')->update([
            'status' => 'finished',
            'end_time' => now(),
        ]);

        /*$baseUrl = $this->live_server;
        $client = new \GuzzleHttp\Client();

        try {
            $client->post($baseUrl . '/api/update-tanding-match-status', [
                'json' => [
                    'remote_match_id' => $match->remote_match_id,
                    'status' => 'finished',
                    'participant_1_score' => $totalBlue, // biru
                    'participant_2_score' => $totalRed,  // merah
                    'winner_id' => $match->winner_id,
                    'winner_corner' => $match->winner_corner,
                    'win_reason' => $match->win_reason,
                ],
                'timeout' => 5,
            ]);

            \Log::info('✅ Status pertandingan dikirim ke server pusat', [
                'remote_match_id' => $match->remote_match_id,
                'winner_id' => $match->winner_id,
                'winner_corner' => $match->winner_corner,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('⚠️ Gagal kirim status finished ke server pusat', [
                'remote_match_id' => $match->remote_match_id,
                'error' => $e->getMessage(),
            ]);
        }*/

        if ($match->winner_corner && $match->winner_corner !== 'draw') {
            $nextMatches = LocalMatch::where(function ($query) use ($match) {
                $query->where('parent_match_red_id', $match->id)
                    ->orWhere('parent_match_blue_id', $match->id);
            })->get();

            foreach ($nextMatches as $nextMatch) {
                $slot = null;

                if ($nextMatch->parent_match_red_id == $match->id) {
                    $nextMatch->red_id = $match->winner_id;
                    $nextMatch->red_name = $match->winner_name;
                    $nextMatch->red_contingent = $match->winner_contingent;
                    $slot = 2; // merah = participant_2
                }

                if ($nextMatch->parent_match_blue_id == $match->id) {
                    $nextMatch->blue_id = $match->winner_id;
                    $nextMatch->blue_name = $match->winner_name;
                    $nextMatch->blue_contingent = $match->winner_contingent;
                    $slot = 1; // biru = participant_1
                }

                $nextMatch->save();

                if ($nextMatch->remote_match_id && $slot) {
                    try {
                        $client->post($baseUrl . '/api/update-next-match-slot', [
                            'json' => [
                                'remote_match_id' => $nextMatch->remote_match_id,
                                'slot' => $slot,             // 1 = biru, 2 = merah
                                'winner_id' => $match->winner_id,
                            ],
                            'timeout' => 5,
                        ]);
                    } catch (\Throwable $e) {
                        \Log::warning('⚠️ Gagal update next match di server pusat', [
                            'remote_match_id' => $nextMatch->remote_match_id,
                            'slot' => $slot,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        return response()->json(['message' => 'Pertandingan diakhiri dan pemenang disimpan.']);
    }

    public function setWinnerManual(Request $request, $id)
    {
        $request->validate([
            'corner' => 'required|in:blue,red',
        ]);

        $match = LocalMatch::findOrFail($id);

        // Set manual winner
        $corner = $request->corner;
        $match->winner_corner = $corner;
        $match->winner_id = $match->{$corner . '_id'};
        $match->winner_name = $match->{$corner . '_name'};
        $match->winner_contingent = $match->{$corner . '_contingent'};
        $match->win_reason = 'manual';
        $match->status = 'finished';

        $match->save();

        // Hentikan semua round yang masih aktif
        $match->rounds()->where('status', 'in_progress')->update([
            'status' => 'finished',
            'end_time' => now(),
        ]);

        // Set peserta ke pertandingan selanjutnya
        if ($match->winner_id) {
            $nextMatches = LocalMatch::where(function ($q) use ($match) {
                $q->where('parent_match_red_id', $match->id)
                ->orWhere('parent_match_blue_id', $match->id);
            })->get();

            foreach ($nextMatches as $nextMatch) {
                $slot = null;

                if ($nextMatch->parent_match_red_id == $match->id) {
                    $nextMatch->red_id = $match->winner_id;
                    $nextMatch->red_name = $match->winner_name;
                    $nextMatch->red_contingent = $match->winner_contingent;
                    $slot = 2;
                }

                if ($nextMatch->parent_match_blue_id == $match->id) {
                    $nextMatch->blue_id = $match->winner_id;
                    $nextMatch->blue_name = $match->winner_name;
                    $nextMatch->blue_contingent = $match->winner_contingent;
                    $slot = 1;
                }

                $nextMatch->save();

                // Optional: kalau pakai sinkronisasi ke server pusat
                /*
                if ($nextMatch->remote_match_id && $slot) {
                    try {
                        $client->post($baseUrl . '/api/update-next-match-slot', [
                            'json' => [
                                'remote_match_id' => $nextMatch->remote_match_id,
                                'slot' => $slot,
                                'winner_id' => $match->winner_id,
                            ],
                            'timeout' => 5,
                        ]);
                    } catch (\Throwable $e) {
                        \Log::warning('⚠️ Gagal update next match di server pusat', [
                            'remote_match_id' => $nextMatch->remote_match_id,
                            'slot' => $slot,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                */
            }
        }

        return response()->json(['message' => 'Pemenang diset manual dan diteruskan ke babak selanjutnya.']);
    }



    private function calculateScore__($matchId, $corner)
    {
        $judgePoints = \App\Models\LocalJudgeScore::where('local_match_id', $matchId)
            ->where('corner', $corner)
            ->sum('point');

        $refereePoints = \App\Models\LocalRefereeAction::where('local_match_id', $matchId)
            ->where('corner', $corner)
            ->sum('point_change');

        return $judgePoints + $refereePoints;
    }

    private function calculateScore($matchId, $corner)
    {
        // Ambil dari nilai yang sah (valid score)
        $validPoints = \App\Models\LocalValidScore::where('local_match_id', $matchId)
            ->where('corner', $corner)
            ->sum('point');

        // Ambil dari tindakan wasit (referee)
        $refereePoints = \App\Models\LocalRefereeAction::where('local_match_id', $matchId)
            ->where('corner', $corner)
            ->sum('point_change');

        return $validPoints + $refereePoints;
    }


    public function endMatch_($id)
    {
        $match = LocalMatch::findOrFail($id);
        $match->status = 'finished';
        $match->save();

        // Tandai ronde yang masih aktif jadi selesai
        $match->rounds()->where('status', 'in_progress')->update([
            'status' => 'finished',
            'end_time' => now()
        ]);

        return response()->json(['message' => 'Pertandingan diakhiri.']);
    }

    public function liveScore($matchId)
    {
        $match = LocalMatch::findOrFail($matchId);

        $scores = DB::table('local_judge_scores')
            ->where('local_match_id', $matchId)
            ->select('corner', DB::raw('SUM(point) as total'))
            ->groupBy('corner')
            ->pluck('total', 'corner');

        return response()->json([
            'blue_score' => $scores['blue'] ?? 0,
            'red_score' => $scores['red'] ?? 0,
        ]);
    }

    

    public function submitPoint_(Request $request)
    {
        $data = $request->validate([
            'match_id' => 'required|exists:local_matches,id',
            'round_id' => 'required|exists:local_match_rounds,id',
            'judge_number' => 'required|integer',
            'judge_name' => 'required|string',
            'corner' => 'required|in:red,blue',
            'type' => 'required|in:punch,kick',
        ]);

        $now = now();

        // ✅ 1. Simpan ke local_judge_scores
        \App\Models\LocalJudgeScore::create([
            'local_match_id' => $data['match_id'],
            'round_id' => $data['round_id'],
            'judge_number' => $data['judge_number'],
            'judge_name' => $data['judge_name'],
            'corner' => $data['corner'],
            'type' => $data['type'],
            'point' => $data['type'] === 'punch' ? 1 : 2,
            'scored_at' => $now,
        ]);

        // ✅ 2. Broadcast JudgePointSubmitted (untuk highlight juri)
        broadcast(new \App\Events\JudgePointSubmitted(
            $data['match_id'],
            $data['judge_number'],
            $data['corner'],
            $data['type']
        ))->toOthers();

        broadcast(new \App\Events\JudgeActionSubmitted(
            $data['match_id'],
            $data['corner'],
            $data['judge_number'],
            $data['type']
        ))->toOthers();

        // ✅ 3. Cek skor dalam 1.5 detik terakhir
        $recent = \App\Models\LocalJudgeScore::where('round_id', $data['round_id'])
            ->where('corner', $data['corner'])
            ->where('type', $data['type'])
            ->where('scored_at', '>=', $now->copy()->subMilliseconds(1500))
            ->get();

        logger('🧪 Recent scores:', $recent->toArray());

        $uniqueJudges = $recent->pluck('judge_number')->unique();
        $isValid = false;

        if ($uniqueJudges->count() >= 2) {
            // ✅ 4. Cek apakah validasi ini sudah pernah terjadi
            $alreadyExists = \App\Models\LocalValidScore::where('round_id', $data['round_id'])
                ->where('corner', $data['corner'])
                ->where('type', $data['type'])
                ->where('validated_at', '>=', $now->copy()->subMilliseconds(1500))
                ->exists();

            if (!$alreadyExists) {
                // ✅ 5. Insert ke local_valid_scores
                \App\Models\LocalValidScore::create([
                    'local_match_id' => $data['match_id'],
                    'round_id' => $data['round_id'],
                    'corner' => $data['corner'],
                    'type' => $data['type'],
                    'point' => $data['type'] === 'punch' ? 1 : 2,
                    'validated_at' => $now,
                ]);

                $isValid = true;

                // ✅ 6. Hitung total score AKUMULASI semua ronde
                $newBlue = \App\Models\LocalValidScore::where('local_match_id', $data['match_id'])
                    ->where('corner', 'blue')
                    ->sum('point');

                $newRed = \App\Models\LocalValidScore::where('local_match_id', $data['match_id'])
                    ->where('corner', 'red')
                    ->sum('point');

                // ✅ 7. Hitung total adjustment AKUMULASI semua ronde
                $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['match_id'])
                    ->where('corner', 'blue')
                    ->sum('point_change');

                $redAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['match_id'])
                    ->where('corner', 'red')
                    ->sum('point_change');

                // ✅ 8. Broadcast ScoreUpdated
                broadcast(new \App\Events\ScoreUpdated(
                    $data['match_id'],
                    $data['round_id'],
                    $newBlue + $blueAdjustment, // Blue score AKUMULASI
                    $newRed + $redAdjustment,   // Red score AKUMULASI
                    0, // blueAdjustment sementara kosongkan
                    0  // redAdjustment sementara kosongkan
                ))->toOthers();

                logger('📢 Broadcast ScoreUpdated', [
                    'match_id' => $data['match_id'],
                    'blue_score' => $newBlue + $blueAdjustment,
                    'red_score' => $newRed + $redAdjustment,
                ]);
            }
        }

        // ✅ 9. Return response
        return response()->json([
            'message' => 'Point submitted',
            'type' => $data['type'],
            'corner' => $data['corner'],
            'round_id' => $data['round_id'],
            'value' => $data['type'] === 'punch' ? 1 : 2,
            'valid' => $isValid
        ]);
    }

    public function submitPoint(Request $request)
    {
        $data = $request->validate([
            'match_id' => 'required|exists:local_matches,id',
            'round_id' => 'required|exists:local_match_rounds,id',
            'judge_number' => 'required|integer',
            'judge_name' => 'required|string',
            'corner' => 'required|in:red,blue',
            'type' => 'required|in:punch,kick',
        ]);

        $now = now();

        // ✅ 1. Simpan ke local_judge_scores
        $judgeScore = \App\Models\LocalJudgeScore::create([
            'local_match_id' => $data['match_id'],
            'round_id' => $data['round_id'],
            'judge_number' => $data['judge_number'],
            'judge_name' => $data['judge_name'],
            'corner' => $data['corner'],
            'type' => $data['type'],
            'point' => $data['type'] === 'punch' ? 1 : 2,
            'scored_at' => $now,
            'is_validated' => false,
        ]);

        // ✅ 2. Broadcast JudgePointSubmitted (highlight juri)
        broadcast(new \App\Events\JudgePointSubmitted(
            $data['match_id'],
            $data['judge_number'],
            $data['corner'],
            $data['type']
        ))->toOthers();

        broadcast(new \App\Events\JudgeActionSubmitted(
            $data['match_id'],
            $data['corner'],
            $data['judge_number'],
            $data['type']
        ))->toOthers();

        // ✅ 3. Cek skor dalam 2 detik terakhir
        /*$recent = \App\Models\LocalJudgeScore::where('local_match_id', $data['match_id'])
            ->where('round_id', $data['round_id'])
            ->where('corner', $data['corner'])
            ->where('type', $data['type'])
            ->where('scored_at', '>=', $judgeScore->scored_at->subSeconds(2)) // 🔥 2 detik
            ->get();*/

        $recent = \App\Models\LocalJudgeScore::where('local_match_id', $data['match_id'])
        ->where('round_id', $data['round_id'])
        ->where('corner', $data['corner'])
        ->where('type', $data['type'])
        ->where('scored_at', '>=', $judgeScore->scored_at->subSeconds(2)) // 🔥 2 detik
        ->where('is_validated', false) // ✅ Tambahkan ini
        ->get();


        logger('🧪 Recent scores (2 detik):', $recent->toArray());

        $uniqueJudges = $recent->pluck('judge_number')->unique();
        $isValid = false;

        if ($uniqueJudges->count() >= 2) {
            // ✅ 4. Cek apakah validasi ini sudah pernah dicatat
            $alreadyExists = \App\Models\LocalValidScore::where('local_match_id', $data['match_id'])
                ->where('round_id', $data['round_id'])
                ->where('corner', $data['corner'])
                ->where('type', $data['type'])
                ->where('validated_at', '>=', $now->copy()->subSeconds(2)) // 🔥 2 detik
                ->exists();

            if (!$alreadyExists) {
                // ✅ 5. Insert ke local_valid_scores
                \App\Models\LocalValidScore::create([
                    'local_match_id' => $data['match_id'],
                    'round_id' => $data['round_id'],
                    'corner' => $data['corner'],
                    'type' => $data['type'],
                    'point' => $data['type'] === 'punch' ? 1 : 2,
                    'validated_at' => $now,
                ]);

                $isValid = true;

                // ✅ 6. Update semua judge scores (2 detik window) -> is_validated = true
                \App\Models\LocalJudgeScore::where('local_match_id', $data['match_id'])
                    ->where('round_id', $data['round_id'])
                    ->where('corner', $data['corner'])
                    ->where('type', $data['type'])
                    ->where('scored_at', '>=', $judgeScore->scored_at->subSeconds(2))
                    ->update(['is_validated' => true]);

                // ✅ 7. Hitung total score AKUMULASI
                $newBlue = \App\Models\LocalValidScore::where('local_match_id', $data['match_id'])
                    ->where('corner', 'blue')
                    ->sum('point');

                $newRed = \App\Models\LocalValidScore::where('local_match_id', $data['match_id'])
                    ->where('corner', 'red')
                    ->sum('point');

                // ✅ 8. Adjustment
                $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['match_id'])
                    ->where('corner', 'blue')
                    ->sum('point_change');

                $redAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['match_id'])
                    ->where('corner', 'red')
                    ->sum('point_change');

                // ✅ 9. Broadcast ScoreUpdated
                broadcast(new \App\Events\ScoreUpdated(
                    $data['match_id'],
                    $data['round_id'],
                    $newBlue + $blueAdjustment,
                    $newRed + $redAdjustment,
                    0, 0
                ))->toOthers();

                logger('📢 Broadcast ScoreUpdated', [
                    'match_id' => $data['match_id'],
                    'blue_score' => $newBlue + $blueAdjustment,
                    'red_score' => $newRed + $redAdjustment,
                ]);
            }
        }

        // ✅ 10. Return
        return response()->json([
            'message' => 'Point submitted',
            'type' => $data['type'],
            'corner' => $data['corner'],
            'round_id' => $data['round_id'],
            'value' => $data['type'] === 'punch' ? 1 : 2,
            'valid' => $isValid
        ]);
    }

    public function judgeRecap($matchId)
    {
        $judgeNumber = session('juri_number');

        // Ambil semua scores dari juri ini dengan relasi localMatchRound
        $scores = \App\Models\LocalJudgeScore::with('localMatchRound') // pakai relasi
            ->where('local_match_id', $matchId)
            ->where('judge_number', $judgeNumber)
            ->orderBy('round_id')
            ->orderBy('scored_at')
            ->get();
        
        
        $recap = [];

        foreach ($scores as $score) {
            $roundNumber = $score->localMatchRound->round_number ?? 0;
            $corner = $score->corner;

            if (!isset($recap[$roundNumber])) {
                $recap[$roundNumber] = ['blue' => [], 'red' => []];
            }

            $recap[$roundNumber][$corner][] = [
                'valid' => (bool) $score->is_validated,
                'type' => $score->type,
            ];
        }

        // Susun response dengan urutan 1-3
        $responseRounds = [];

        for ($i = 1; $i <= 3; $i++) {
            $responseRounds[] = [
                'round_number' => $i,
                'blue' => $recap[$i]['blue'] ?? [],
                'red' => $recap[$i]['red'] ?? [],
            ];
        }

        return response()->json([
            'judge_number' => $judgeNumber,
            'rounds' => $responseRounds,
        ]);
    }




    
    public function judgeRecap3($matchId)
{
    $judgeNumber = session('juri_number');

    $scores = \App\Models\LocalJudgeScore::where('local_match_id', $matchId)
        ->where('judge_number', $judgeNumber)
        ->orderBy('round_id')
        ->orderBy('scored_at')
        ->get();

    $validScores = \App\Models\LocalValidScore::where('local_match_id', $matchId)
        ->orderBy('round_id')
        ->orderBy('validated_at')
        ->get();

    $recap = [
        1 => ['blue' => [], 'red' => []],
        2 => ['blue' => [], 'red' => []],
        3 => ['blue' => [], 'red' => []],
    ];

    $validCount = [
        'blue' => [],
        'red' => []
    ];

    // Hitung jumlah valid per type
    foreach ($validScores as $valid) {
        $validCount[$valid->corner][$valid->type][] = $valid;
    }

    foreach ($scores as $score) {
        $isValid = false;

        // Cek apakah ada valid untuk corner dan type ini
        if (!empty($validCount[$score->corner][$score->type])) {
            // Ambil satu valid lalu pop
            array_shift($validCount[$score->corner][$score->type]);
            $isValid = true;
        }

        $recap[$score->round_id][$score->corner][] = [
            'valid' => $isValid,
            'type' => $score->type
        ];
    }

    return response()->json([
        'rounds' => [
            [
                'round_number' => 1,
                'blue' => $recap[1]['blue'],
                'red' => $recap[1]['red'],
            ],
            [
                'round_number' => 2,
                'blue' => $recap[2]['blue'],
                'red' => $recap[2]['red'],
            ],
            [
                'round_number' => 3,
                'blue' => $recap[3]['blue'],
                'red' => $recap[3]['red'],
            ]
        ]
    ]);
}


    public function judgeRecap_($matchId)
    {
        $judgeNumber = session('juri_number'); // Ambil juri dari session

        // Ambil semua scores dari juri ini
        $scores = \App\Models\LocalJudgeScore::where('local_match_id', $matchId)
            ->where('judge_number', $judgeNumber)
            ->orderBy('round_id')
            ->orderBy('scored_at')
            ->get();

        // Ambil semua valid scores
        $validScores = \App\Models\LocalValidScore::where('local_match_id', $matchId)
            ->get();

        $recap = [
            1 => ['blue' => [], 'red' => []],
            2 => ['blue' => [], 'red' => []],
            3 => ['blue' => [], 'red' => []],
        ];

        foreach ($scores as $score) {
            $isValid = $validScores->contains(function ($valid) use ($score) {
                return 
                    $valid->round_id == $score->round_id &&
                    $valid->corner == $score->corner &&
                    $valid->type == $score->type &&
                    abs(strtotime($valid->validated_at) - strtotime($score->scored_at)) <= 2;
            });

            

            $recap[$score->round_id][$score->corner][] = [
                'valid' => $isValid,
                'type' => $score->type // 🔥 Tambahkan TYPE disini
            ];
        }

        return response()->json([
            'rounds' => [
                [
                    'round_number' => 1,
                    'blue' => $recap[1]['blue'],
                    'red' => $recap[1]['red'],
                ],
                [
                    'round_number' => 2,
                    'blue' => $recap[2]['blue'],
                    'red' => $recap[2]['red'],
                ],
                [
                    'round_number' => 3,
                    'blue' => $recap[3]['blue'],
                    'red' => $recap[3]['red'],
                ]
            ]
        ]);
    }



    

    public function refereeAction(Request $request)
    {
        $data = $request->validate([
            'local_match_id' => 'required|exists:local_matches,id',
            'round_id' => 'required|exists:local_match_rounds,id',
            'corner' => 'required|in:red,blue',
            'action' => 'required|in:jatuhan,binaan_1,binaan_2,teguran_1,teguran_2,peringatan_1,peringatan_2,verifikasi_jatuhan,verifikasi_hukuman',
        ]);

        // 🎯 Hitung perubahan poin berdasarkan action
        $actionPoints = [
            'jatuhan' => 3,
            'binaan_1' => 0,
            'binaan_2' => 0,
            'teguran_1' => -1,
            'teguran_2' => -2,
            'peringatan_1' => -5,
            'peringatan_2' => -10,
            'verifikasi_jatuhan' => 0,
            'verifikasi_hukuman' => 0,
        ][$data['action']] ?? 0;

        $data['point_change'] = $actionPoints;

        // 💾 Simpan ke DB
        \App\Models\LocalRefereeAction::create($data);

        // 🔊 Broadcast referee action
        broadcast(new \App\Events\RefereeActionSubmitted(
            $data['local_match_id'],
            $data['corner'],
            $data['action'],
            $data['point_change']
        ))->toOthers();

        // 🔢 Hitung total skor AKUMULASI seluruh pertandingan
        $blueScore = \App\Models\LocalValidScore::where('local_match_id', $data['local_match_id'])
            ->where('corner', 'blue')
            ->sum('point');

        $redScore = \App\Models\LocalValidScore::where('local_match_id', $data['local_match_id'])
            ->where('corner', 'red')
            ->sum('point');

        $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['local_match_id'])
            ->where('corner', 'blue')
            ->sum('point_change');

        $redAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['local_match_id'])
            ->where('corner', 'red')
            ->sum('point_change');

        // 🔊 Broadcast skor baru AKUMULASI
        broadcast(new \App\Events\ScoreUpdated(
            $data['local_match_id'],
            $data['round_id'], // Kirim round_id aktif saja
            $blueScore + $blueAdjustment,
            $redScore + $redAdjustment,
            $blueAdjustment,
            $redAdjustment
        ))->toOthers();

        return response()->json(['message' => 'Tindakan wasit berhasil disimpan']);
    }

    public function removeJatuhan(Request $request)
    {
        $data = $request->validate([
            'local_match_id' => 'required|exists:local_matches,id',
            'round_id' => 'required|exists:local_match_rounds,id',
            'corner' => 'required|in:red,blue',
        ]);

        // 🔥 Hapus semua jatuhan untuk sudut & ronde ini
        \App\Models\LocalRefereeAction::where('local_match_id', $data['local_match_id'])
            ->where('round_id', $data['round_id'])
            ->where('corner', $data['corner'])
            ->where('action', 'jatuhan')
            ->delete();

        // 🔢 Hitung ulang skor
        $blueScore = \App\Models\LocalValidScore::where('local_match_id', $data['local_match_id'])
            ->where('corner', 'blue')
            ->sum('point');

        $redScore = \App\Models\LocalValidScore::where('local_match_id', $data['local_match_id'])
            ->where('corner', 'red')
            ->sum('point');

        $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['local_match_id'])
            ->where('corner', 'blue')
            ->sum('point_change');

        $redAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['local_match_id'])
            ->where('corner', 'red')
            ->sum('point_change');

        // 🔊 Broadcast skor terbaru
        broadcast(new \App\Events\ScoreUpdated(
            $data['local_match_id'],
            $data['round_id'],
            $blueScore + $blueAdjustment,
            $redScore + $redAdjustment,
            $blueAdjustment,
            $redAdjustment
        ))->toOthers();

        return response()->json(['message' => 'Jatuhan berhasil dihapus dan skor diperbarui']);
    }


    public function cancelRefereeAction(Request $request)
    {
        $data = $request->validate([
            'match_id' => 'required|exists:local_matches,id',
            'round_id' => 'required|exists:local_match_rounds,id',
            'corner' => 'required|in:red,blue',
            'action' => 'required|string',
        ]);

        // 🔍 Cari record terakhir dengan action & corner yang sama
        $lastAction = \App\Models\LocalRefereeAction::where('local_match_id', $data['match_id'])
            ->where('round_id', $data['round_id'])
            ->where('corner', $data['corner'])
            ->where('action', $data['action'])
            ->latest()
            ->first();

        if (!$lastAction) {
            return response()->json(['message' => 'Tidak ada aksi untuk dibatalkan'], 404);
        }

        // 🔥 Hapus dan hitung ulang skor
        $lastAction->delete();

        broadcast(new \App\Events\RefereeActionCancelled(
            $data['match_id'],
            $data['corner'],
            $data['action']
        ))->toOthers();


        $blueScore = \App\Models\LocalValidScore::where('local_match_id', $data['match_id'])
            ->where('corner', 'blue')
            ->sum('point');

        $redScore = \App\Models\LocalValidScore::where('local_match_id', $data['match_id'])
            ->where('corner', 'red')
            ->sum('point');

        $blueAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['match_id'])
            ->where('corner', 'blue')
            ->sum('point_change');

        $redAdjustment = \App\Models\LocalRefereeAction::where('local_match_id', $data['match_id'])
            ->where('corner', 'red')
            ->sum('point_change');

        broadcast(new \App\Events\ScoreUpdated(
            $data['match_id'],
            $data['round_id'],
            $blueScore + $blueAdjustment,
            $redScore + $redAdjustment,
            $blueAdjustment,
            $redAdjustment
        ))->toOthers();

        return response()->json(['message' => 'Aksi wasit dibatalkan']);
    }




    public function getRecap($matchId)
    {
        $match = LocalMatch::with('rounds')->findOrFail($matchId);
        $recap = [];

        foreach ($match->rounds as $round) {
            $roundData = [
                'round_number' => $round->round_number,
                'judges' => [],
                'valid_scores' => ['blue' => [], 'red' => []],
                'jatuhan' => ['blue' => 0, 'red' => 0],
                'hukuman' => ['blue' => 0, 'red' => 0],
                'final' => ['blue' => 0, 'red' => 0],
            ];

            // 💥 Nilai juri
            for ($i = 1; $i <= 3; $i++) {
                foreach (['blue', 'red'] as $corner) {
                    $points = LocalJudgeScore::where([
                            'local_match_id' => $matchId,
                            'round_id' => $round->id,
                            'judge_number' => $i,
                            'corner' => $corner
                        ])
                        ->orderBy('scored_at')
                        ->get(); // ⬅️ Ambil objek, bukan pluck

                    $pointData = $points->map(function ($p) {
                        return [
                            'point' => $p->point,
                            'type' => $p->type,
                            'valid' => (bool) $p->is_validated,
                        ];
                    });

                    $roundData['judges'][] = [
                        'judge' => "Juri $i",
                        'corner' => $corner,
                        'points' => $pointData,
                        'total' => $points->sum('point'),
                    ];
                }
            }


            // ✅ Nilai Sah
            foreach (['blue', 'red'] as $corner) {
                $valid = LocalValidScore::where([
                        'local_match_id' => $matchId,
                        'round_id' => $round->id,
                        'corner' => $corner,
                    ])->pluck('point')->toArray();

                $roundData['valid_scores'][$corner] = [
                    'points' => $valid,
                    'total' => array_sum($valid),
                ];
            }

            // ✅ Jatuhan
            foreach (['blue', 'red'] as $corner) {
                $jatuhan = LocalRefereeAction::where([
                        'local_match_id' => $matchId,
                        'round_id' => $round->id,
                        'corner' => $corner,
                        'action' => 'jatuhan'
                    ])->sum('point_change');
                $roundData['jatuhan'][$corner] = $jatuhan;
            }

            // ✅ Hukuman
            foreach (['blue', 'red'] as $corner) {
                $hukuman = LocalRefereeAction::where([
                        'local_match_id' => $matchId,
                        'round_id' => $round->id,
                        'corner' => $corner
                    ])->whereIn('action', [
                        'teguran_1', 'teguran_2',
                        'peringatan_1', 'peringatan_2',
                    ])->sum('point_change');
                $roundData['hukuman'][$corner] = $hukuman;
            }

            // ✅ Nilai akhir = sah + jatuhan + hukuman
            foreach (['blue', 'red'] as $corner) {
                $roundData['final'][$corner] =
                    $roundData['valid_scores'][$corner]['total'] +
                    $roundData['jatuhan'][$corner] +
                    $roundData['hukuman'][$corner];
            }

            $recap[] = $roundData;
        }

        return response()->json($recap);
    }

    // Route: GET /api/local-matches/tournaments
   public function getTournaments()
    {
        $tanding = \DB::table('local_matches')
            ->select('tournament_name');

        $seni = \DB::table('local_seni_matches')
            ->select('tournament_name');

        $tournaments = $tanding
            ->union($seni)
            ->distinct()
            ->pluck('tournament_name');

        return response()->json($tournaments);
    }


    // Route: GET /api/local-matches/arenas?tournament=Kejuaraan Nasional 2024
   public function getArenas(Request $request)
    {
        $tournament = $request->query('tournament');
        $type = $request->query('type'); // 'tanding' atau 'seni'

        if (!$tournament || !$type) {
            return response()->json([], 400);
        }

        if ($type === 'tanding') {
            $arenas = \DB::table('local_matches')
                ->where('tournament_name', $tournament)
                ->select('arena_name')
                ->distinct()
                ->pluck('arena_name');
        } elseif ($type === 'seni') {
            $arenas = \DB::table('local_seni_matches')
                ->where('tournament_name', $tournament)
                ->select('arena_name')
                ->distinct()
                ->pluck('arena_name');
        } else {
            return response()->json([], 400);
        }

        return response()->json($arenas);
    }


    public function requestVerification_(Request $request)
    {
        $data = $request->validate([
            'match_id' => 'required|integer',
            'round_id' => 'required|integer',
            'type' => 'required|in:jatuhan,hukuman',
            'corner' => 'required|in:blue,red',
        ]);

        // Kosongkan cache vote sebelumnya
        $cacheKey = "verification_votes_{$data['match_id']}_{$data['round_id']}";
        Cache::forget($cacheKey);
        Cache::put($cacheKey, [], now()->addMinutes(5));

        broadcast(new \App\Events\VerificationRequested(
            $data['match_id'],
            $data['round_id'],
            $data['type'],
            $data['corner']
        ))->toOthers();

        return response()->json(['message' => 'Verification request broadcasted']);
    }

    public function requestVerification(Request $request)
    {
        $data = $request->validate([
            'match_id' => 'required|integer',
            'round_id' => 'required|integer',
            'type' => 'required|in:jatuhan,hukuman',
            'corner' => 'required|in:blue,red',
        ]);

        $cacheKey = "verification_votes_{$data['match_id']}_{$data['round_id']}";

        // Simpan struktur lengkap ke cache
        Cache::put($cacheKey, [
            'type' => $data['type'],
            'corner' => $data['corner'],
            'votes' => [],
        ], now()->addMinutes(5));

        broadcast(new \App\Events\VerificationRequested(
            $data['match_id'],
            $data['round_id'],
            $data['type'],
            $data['corner']
        ))->toOthers();

        return response()->json(['message' => 'Verification request broadcasted']);
    }


    public function submitVerificationVote_(Request $request)
    {
        $data = $request->validate([
            'match_id' => 'required|integer',
            'round_id' => 'required|integer',
            'vote' => 'required|in:blue,red,invalid',
            'judge_name' => 'required|string',
        ]);

        $cacheKey = "verification_votes_{$data['match_id']}_{$data['round_id']}";

        $votes = Cache::get($cacheKey, []);

        $votes[] = [
            'judge' => $data['judge_name'],
            'vote' => $data['vote'],
        ];

        Cache::put($cacheKey, $votes, now()->addMinutes(5));

        // Kalau semua 3 juri sudah vote, broadcast hasil
        if (count($votes) >= 3) {
            broadcast(new VerificationResulted(
                $data['match_id'],
                $data['round_id'],
                $votes
            ))->toOthers();

            // Hapus cache
            Cache::forget($cacheKey);
        }

        return response()->json(['message' => 'Vote recorded']);
    }

    public function submitVerificationVote(Request $request)
    {
        $data = $request->validate([
            'match_id' => 'required|integer',
            'round_id' => 'required|integer',
            'vote' => 'required|in:blue,red,invalid',
            'judge_name' => 'required|string',
        ]);

        $cacheKey = "verification_votes_{$data['match_id']}_{$data['round_id']}";
        $cached = Cache::get($cacheKey);

        // ✅ Pastikan struktur cache valid
        if (!$cached || !isset($cached['votes'])) {
            return response()->json(['message' => 'No verification in progress'], 400);
        }

        $votes = collect($cached['votes']);

        // Hindari duplicate vote dari juri yang sama
        $votes = $votes->reject(fn ($v) => $v['judge'] === $data['judge_name'])->values();

        // Tambahkan vote baru
        $votes->push([
            'judge' => $data['judge_name'],
            'vote' => $data['vote'],
        ]);

        // Update cache dengan vote terbaru
        Cache::put($cacheKey, [
            'type' => $cached['type'],
            'corner' => $cached['corner'], // tetap simpan corner permintaan awal
            'votes' => $votes->toArray(),
        ], now()->addMinutes(5));

        // ✅ Jika sudah 3 vote, proses hasilnya
        if ($votes->count() >= 3) {
            // 🔎 Hitung mayoritas
            $blueVotes = $votes->where('vote', 'blue')->count();
            $redVotes = $votes->where('vote', 'red')->count();

            // 🧠 Tentukan sudut mayoritas
            $majorityCorner = $blueVotes > $redVotes ? 'blue' : 'red';

            // 🔊 Broadcast hasil verifikasi
            broadcast(new VerificationResulted(
                $data['match_id'],
                $data['round_id'],
                $votes->toArray(),
                $cached['type'],
                $majorityCorner // ✅ Kirim sudut hasil vote mayoritas
            ))->toOthers();

            // 🧹 Bersihkan cache
            Cache::forget($cacheKey);
        }

        return response()->json(['message' => 'Vote recorded']);
    }








}
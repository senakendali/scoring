<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Pertandingan Seni</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        h4, h5 { margin: 0; padding: 0; font-size:18px; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }

        .table { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        /* tipisin garis dasar supaya gak keliatan terlalu tebal saat ketemu separator */
        .table th, .table td { padding: 8px; font-size: 11px; border-bottom: 0.5pt solid #ddd; }
        .table thead th { font-weight: bold; }
        .table tbody tr:nth-child(even) { background-color: #f7f7f7; }

        .logo { width: 120px; }

        .soft-dark { background-color:#495057; color:#FFFFFF; height:50px; }
        .dark      { background-color:#343A40; color:#FFFFFF; }

        .blue-corner { background-color:#002FB9; color:#FFFFFF; }
        .red-corner  { background-color:#F80000; color:#FFFFFF; }

        .contingent { font-style: italic; font-size: 10px; opacity: .95; }
        .names { line-height: 1.2rem; }

        /* separator baris battle → lebih tipis & soft */
        .battle-sep td { border-top: 0.5pt solid #d9d9d9; }
    </style>
</head>
<body>

@php
    $upper = fn($s) => strtoupper((string)($s ?? ''));
    $titleGender = fn($g) => strtolower((string)$g) === 'male' ? 'PUTRA' : 'PUTRI';

    $joinNames = function(array $m): string {
        $t1 = trim((string)($m['team_member1']['name'] ?? $m['participant_1'] ?? ''));
        $t2 = trim((string)($m['team_member2']['name'] ?? $m['participant_2'] ?? ''));
        $t3 = trim((string)($m['team_member3']['name'] ?? $m['participant_3'] ?? ''));
        $parts = array_values(array_filter([$t1, $t2, $t3], fn($x)=>$x !== ''));
        return $parts ? implode(' / ', $parts) : '';
    };

    $safeScore = fn($v) => is_numeric($v) ? number_format((float)$v, 6, '.', '') : '-';
@endphp

@foreach ($data as $arenaName => $groups)

    {{-- HEADER per ARENA --}}
    <table style="width: 100%; margin-bottom: 10px;">
        <tr>
            <td style="width: 25%;">
               &nbsp;
            </td>
            <td style="width: 50%; text-align: center;">
                <h4 class="uppercase fw-bold">JADWAL {{ $upper($arenaName) }}</h4>
                @if(!empty($tournament))
                    <h4 class="uppercase fw-bold">{{ $tournament }}</h4>
                @endif
                <div class="uppercase fw-bold">
                    {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}
                </div>
            </td>
            <td style="width: 25%;"></td>
        </tr>
    </table>

    @php
        $battleRows = [];
        $seenBattleIds = [];
        $nonBattleTables = [];

        foreach ($groups as $group) {
            $catLabel = (string)($group['category'] ?? '-');
            $gender   = (string)($group['gender'] ?? '-');

            foreach ($group['age_categories'] ?? [] as $ageGroup) {
                $ageLabel = (string)($ageGroup['age_category'] ?? 'Tanpa Usia');

                foreach ($ageGroup['pools'] ?? [] as $pool) {
                    $poolName = (string)($pool['name'] ?? '-');
                    $rows     = $pool['matches'] ?? [];

                    // ====== BATTLE grouping per battle_group ======
                    $byBattle = [];
                    foreach ($rows as $m) {
                        $mode = strtolower((string)($m['mode'] ?? 'default'));
                        $bg   = $m['battle_group'] ?? null;
                        if ($mode === 'battle' && $bg !== null) {
                            $byBattle[$bg] ??= [];
                            $byBattle[$bg][] = $m;
                        }
                    }

                    foreach ($byBattle as $bg => $pair) {
                        $blue = collect($pair)->first(fn($x) => strtolower((string)($x['corner'] ?? '')) === 'blue');
                        $red  = collect($pair)->first(fn($x) => strtolower((string)($x['corner'] ?? '')) === 'red');

                        $minOrder = collect($pair)->pluck('match_order')->filter(fn($x)=>!is_null($x))->min();

                        // ⬇️ KELAS untuk battle: TANPA pool (sesuai request)
                        $classLabel = trim($catLabel.' '.$titleGender($gender).' '.strtoupper($ageLabel));

                        $blueNames = $blue ? $joinNames($blue) : '';
                        $blueCont  = $blue['contingent']['name'] ?? ($blue['contingent_name'] ?? null);
                        $blueScore = $blue['final_score'] ?? null;

                        $redNames = $red ? $joinNames($red) : '';
                        $redCont  = $red['contingent']['name'] ?? ($red['contingent_name'] ?? null);
                        $redScore = $red['final_score'] ?? null;

                        $battleRows[] = [
                            'order'       => $minOrder ?? '-',
                            'round_label' => $blue['round_label'] ?? ($red['round_label'] ?? null),
                            'class_label' => $classLabel,

                            'blue' => [
                                'names'     => $blueNames ?: null,
                                'contingent'=> $blueCont ?: null,
                                'score'     => $blueScore,
                                'time'      => $blue['match_time'] ?? ($blue['duration'] ?? null),
                            ],
                            'red'  => [
                                'names'     => $redNames ?: null,
                                'contingent'=> $redCont ?: null,
                                'score'     => $redScore,
                                'time'      => $red['match_time'] ?? ($red['duration'] ?? null),
                            ],
                        ];

                        foreach ($pair as $mm) { $seenBattleIds[$mm['id']] = true; }
                    }

                    // ====== NON-BATTLE per pool ======
                    $nonRows = [];
                    foreach ($rows as $m) {
                        if (!empty($seenBattleIds[$m['id'] ?? 0])) continue;
                        $nonRows[] = $m;
                    }
                    if (!empty($nonRows)) {
                        $nonBattleTables[] = [
                            'title' => [
                                'category' => $catLabel,
                                'gender'   => $gender,
                                'age'      => $ageLabel,
                                'pool'     => $poolName,
                            ],
                            'rows'  => $nonRows,
                        ];
                    }
                }
            }
        }

        usort($battleRows, function($a, $b) {
            $ao = is_numeric($a['order']) ? (int)$a['order'] : PHP_INT_MAX;
            $bo = is_numeric($b['order']) ? (int)$b['order'] : PHP_INT_MAX;
            return $ao <=> $bo;
        });
    @endphp

    {{-- ===================== BATTLE: SATU TABEL GABUNGAN ===================== --}}
    @if(!empty($battleRows))
        <table class="table">
            <thead>
                <tr>
                    <th class="soft-dark">PARTAI</th>
                    <th class="soft-dark">BABAK</th>
                    <th class="soft-dark">KELAS</th>
                    <th class="soft-dark text-center" colspan="2">PESERTA</th>
                    <th class="soft-dark text-center" colspan="2">WAKTU</th>
                    <th class="soft-dark text-center" colspan="2">SCORE</th>
                </tr>
            </thead>
            <tbody>
            @foreach($battleRows as $row)
                <tr class="battle-sep">
                    <td>{{ $row['order'] }}</td>
                    <td class="uppercase">{{ $row['round_label'] ?? '-' }}</td>
                    <td class="uppercase">{{ $row['class_label'] ?? '-' }}</td>

                    {{-- BLUE --}}
                    <td class="blue-corner" style="width: 25%;">
                        @php
                            $bn = $row['blue']['names'] ?? null;
                            $bc = $row['blue']['contingent'] ?? null;
                        @endphp
                        <div class="names">{{ $bn ?: '-' }}</div>
                        @if($bc)
                            <div class="contingent">{{ $bc }}</div>
                        @endif
                    </td>

                    {{-- RED --}}
                    <td class="red-corner" style="width: 25%;">
                        @php
                            $rn = $row['red']['names'] ?? null;
                            $rc = $row['red']['contingent'] ?? null;
                        @endphp
                        <div class="names">{{ $rn ?: '-' }}</div>
                        @if($rc)
                            <div class="contingent">{{ $rc }}</div>
                        @endif
                    </td>

                    {{-- waktu kiri/kanan --}}
                    <td class="text-center">-</td>
                    <td class="text-center">-</td>

                    {{-- score kiri/kanan --}}
                    <td class="text-center">-</td>
                    <td class="text-center">-</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    {{-- ===================== NON-BATTLE: PER POOL ===================== --}}
    @if(!empty($nonBattleTables))
        @foreach($nonBattleTables as $tb)
            <table class="table">
                <thead>
                    <tr>
                        <th class="soft-dark">PARTAI</th>
                        <th class="soft-dark">KONTINGEN</th>
                        <th class="soft-dark" colspan="3">NAMA ATLET</th>
                        <th class="soft-dark text-center">WAKTU</th>
                        <th class="soft-dark text-center">SCORE</th>
                    </tr>
                    <tr>
                        <th colspan="7" class="dark text-center fw-bold uppercase">
                            {{ $tb['title']['category'] }}
                            {{ $titleGender($tb['title']['gender'] ?? '') }}
                            {{ strtoupper($tb['title']['age'] ?? '-') }} - {{ $tb['title']['pool'] }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tb['rows'] as $m)
                        <tr>
                            <td>{{ $m['match_order'] ?? '-' }}</td>
                            <td>{{ $m['contingent']['name'] ?? '-' }}</td>

                            @php $type = $m['match_type'] ?? ''; @endphp
                            @if ($type === 'seni_tunggal' || $type === 'solo_kreatif')
                                <td colspan="3">{{ $m['team_member1']['name'] ?? '-' }}</td>
                            @elseif ($type === 'seni_ganda')
                                <td>{{ $m['team_member1']['name'] ?? '-' }}</td>
                                <td>{{ $m['team_member2']['name'] ?? '-' }}</td>
                                <td>-</td>
                            @elseif ($type === 'seni_regu')
                                <td>{{ $m['team_member1']['name'] ?? '-' }}</td>
                                <td>{{ $m['team_member2']['name'] ?? '-' }}</td>
                                <td>{{ $m['team_member3']['name'] ?? '-' }}</td>
                            @else
                                <td colspan="3">{{ $m['team_member1']['name'] ?? '-' }}</td>
                            @endif

                            <td class="text-center">{{ $m['match_time'] ?? ($m['duration'] ?? '-') }}</td>
                            <td class="text-center">{{ $safeScore($m['final_score'] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endif

@endforeach

</body>
</html>

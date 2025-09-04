<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Jadwal Pertandingan Seni</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
            color: #000;
        }

        h2, h3, h4 {
            margin-bottom: 0;
        }

        .arena-title {
            margin-top: 30px;
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .category-label {
            margin-top: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .age-label {
            font-style: italic;
            margin-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        th, td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }

        th{
            text-transform: uppercase;
        }

        th {
            background: #f0f0f0;
        }
    </style>
</head>
<body>

    <!--h2>Jadwal Pertandingan Seni</h2>
    <p><strong>Turnamen:</strong> {{ $tournament }}</p-->

    @foreach ($data as $arenaName => $groups)
        <div class="arena-title">Arena: {{ strtoupper($arenaName) }}</div>

        @foreach ($groups as $group)
            

            @foreach ($group['age_categories'] as $ageGroup)
                

                @foreach ($ageGroup['pools'] as $pool)
                    <table>
                        <thead>
                            <tr>
                                <th colspan="6">{{ $group['category'] }} - {{ $group['gender'] === 'male' ? 'PUTRA' : 'PUTRI' }} {{ strtoupper($ageGroup['age_category']) }} Pool: {{ $pool['name'] }}</th>
                            </tr>
                            <tr>
                                <th>Match</th>
                                <th>Kontingen</th>
                                <th colspan="3">Peserta</th>
                                <th>Score</th>
                                
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pool['matches'] as $match)
                                <tr>
                                    <td>{{ $match['match_order'] }}</td>
                                    <td>{{ $match['contingent']['name'] ?? '-' }}</td>

                                    @if (in_array($match['match_type'], ['seni_tunggal', 'solo_kreatif']))
                                        <td>{{ $match['team_member1']['name'] ?? '-' }}</td>
                                        <td colspan="3">-</td>
                                    @elseif ($match['match_type'] === 'seni_ganda')
                                        <td>{{ $match['team_member1']['name'] ?? '-' }}</td>
                                        <td>{{ $match['team_member2']['name'] ?? '-' }}</td>
                                        <td colspan="2">-</td>
                                    @elseif ($match['match_type'] === 'seni_regu')
                                        <td>{{ $match['team_member1']['name'] ?? '-' }}</td>
                                        <td>{{ $match['team_member2']['name'] ?? '-' }}</td>
                                        <td>{{ $match['team_member3']['name'] ?? '-' }}</td>
                                        <td></td>
                                    @else
                                        <td colspan="3">-</td>
                                    @endif

                                    
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            @endforeach
        @endforeach
    @endforeach

</body>
</html>

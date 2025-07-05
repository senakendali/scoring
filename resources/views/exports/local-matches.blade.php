<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rekap Pemenang Semua Usia</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; text-transform: uppercase; }
        th { background-color: #eee; }
    </style>
</head>
<body>
   <h2>DAFTAR PERTANDINGAN - {{ strtoupper($tournament) }}</h2>

    @foreach($grouped as $arena => $pools)
        <h3>Arena: {{ strtoupper($arena) }}</h3>

        @foreach($pools as $pool => $matches)
            
            <table>
                <thead>
                    <tr>
                        <th>Partai</th>
                        <th>Babak</th>
                        <th>Kelas</th>
                        <th style="background-color: #007bff; color: #fff;">Sudut Biru</th>
                        <th style="background-color: #007bff; color: #fff;">Kontingen</th>
                        <!--th>Skor</th-->
                        <th style="background-color: #dc3545; color: #fff;">Sudut Merah</th>
                        <th style="background-color: #dc3545; color: #fff;">Kontingen</th>
                        <th>Skor Biru</th>
                        <th>Skor Merah</th>
                        <!--th>Skor</th>
                        <th>Pemenang</th>
                        <th>Status</th-->
                    </tr>
                </thead>
                <tbody>
                    @foreach($matches as $match)
                        <tr>
                            <td>{{ $match->match_number }}</td>
                            <td>{{ $match->round_label }}</td>
                            <td>{{ $match->class_name }}</td>
                            <td>{{ $match->blue_name }}</td>
                            <td>{{ $match->blue_contingent }}</td>
                           
                            <td>{{ $match->red_name }}</td>
                            <td>{{ $match->red_contingent }}</td>
                            <td>-</td>
                            <td>-</td>
                            <!--td>{{ $match->participant_2_score ?? '-' }}</td>
                            <td>{{ $match->winner_name ?? '-' }}</td>
                            <td>{{ $match->status }}</td-->
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endforeach
</body>
</html>

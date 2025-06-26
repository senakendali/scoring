<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rekap Medali Semua</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        h3 { margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 5px; text-align: center; }
        th { background-color: #eee; }
    </style>
</head>
<body>
    <h2 style="text-align: center;">REKAPITULASI PEROLEHAN MEDALI</h2>

    @foreach($rekapPerUsia as $ageCategory => $rekap)
        <h3>{{ strtoupper($ageCategory) }}</h3>
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kontingen</th>
                    <th>Emas</th>
                    <th>Perak</th>
                    <th>Perunggu</th>
                    <th>Total</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rekap as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['kontingen'] }}</td>
                    <td>{{ $row['emas'] == 0 ? '-' : $row['emas'] }}</td>
                    <td>{{ $row['perak'] == 0 ? '-' : $row['perak'] }}</td>
                    <td>{{ $row['perunggu'] == 0 ? '-' : $row['perunggu'] }}</td>
                    <td>{{ $row['total'] == 0 ? '-' : $row['total'] }}</td>
                    <td>{{ $row['keterangan'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</body>
</html>

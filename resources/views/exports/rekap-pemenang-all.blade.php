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
    @foreach ($rekap as $ageCategory => $rows)
        <h2>DAFTAR NAMA PEMENANG {{ strtoupper($tournamentName) }} - KATEGORI {{ strtoupper($ageCategory) }}</h2>

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Atlet</th>
                    <th>Kontingen</th>
                    <th colspan="3">Sebagai</th>
                   
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $index => $row)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $row['nama'] }}</td>
                        <td>{{ $row['kontingen'] }}</td>
                        <td>{{ $row['medali'] }}</td>
                        <td class="text-uppercase">Kelas {{ $row['kelas'] }}</td>
                        <td class="text-uppercase">{{ $row['gender'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</body>
</html>

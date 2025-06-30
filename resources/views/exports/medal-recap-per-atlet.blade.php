<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rekap Pemenang - {{ $ageCategory }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; text-transform: uppercase; }
        th { background-color: #eee; }
    </style>
</head>
<body>
    <h3>DAFTAR NAMA PEMENANG {{ strtoupper($tournamentName) }}<br>KATEGORI {{ strtoupper($ageCategory) }}</h3>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;">No</th>
                <th>Nama Atlet</th>
                <th>Kontingen</th>
                <th colspan="3">Sebagai</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['nama'] }}</td>
                    <td>{{ $row['kontingen'] }}</td>
                    <td>{{ $row['medali'] }}</td>
                    <td>{{ strtoupper('Kelas ' . $row['kelas']) }}</td>
                    <td>{{ strtoupper($row['gender']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>

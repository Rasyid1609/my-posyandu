<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">

        <style>
            @page {
                margin: 24mm 14mm 18mm;
            }

            body {
                font-family: "DejaVu Sans", sans-serif;
                font-size: 9px;
                color: #24342c;
            }

            header {
                position: fixed;
                top: -16mm;
                left: 0;
                right: 0;
                border-bottom: 2px solid #397153;
                padding-bottom: 6px;
            }

            header strong {
                font-size: 17px;
            }

            footer {
                position: fixed;
                bottom: -10mm;
                color: #555;
                font-size: 8px;
            }

            h1 {
                font-size: 15px;
            }

            .metadata {
                width: 100%;
                margin-bottom: 12px;
            }

            .metadata td {
                padding: 3px 6px;
                vertical-align: top;
            }

            table.results {
                border-collapse: collapse;
                width: 100%;
                table-layout: fixed;
            }

            .results th {
                background: #315d49;
                color: white;
                text-align: left;
            }

            .results td,
            .results th {
                padding: 4px 8px;
                border: 1px solid #ccd7d0;
                overflow-wrap: break-word;
            }

            .results tr {
                page-break-inside: avoid;
            }

            .results tr:nth-child(even) td {
                background: #f4f7f4;
            }

            thead {
                display: table-header-group;
            }

            .number {
                text-align: right;
            }

            .note {
                color: #555;
                margin: 8px 0;
            }
        </style>
    </head>
    <body>
        <header><strong>POSYANDU JERUK</strong> — Rekap aplikasi / {{ $metadata['Jenis laporan'] }}</header>
        <footer>Konfigurasi sementara · Pengesahan administratif tidak berarti validasi klinis</footer>

        <h1>{{ $metadata['Jenis laporan'] }} — {{ $metadata['Periode'] }}</h1>

        <table class="metadata">
            @foreach ($metadata as $label => $value)
                <tr>
                    <td style="width:18%"><strong>{{ $label }}</strong></td>
                    <td>{{ $value }}</td>
                </tr>
            @endforeach
        </table>

        <p class="note">Angka hasil memakai warga unik dengan hasil petugas yang diketahui terakhir per indikator pada kunjungan final. Nilai kosong bukan nol/normal. Penyebut nol ditampilkan “Tidak berlaku”.</p>

        <table class="results">
            <thead>
                <tr>
                    <th style="width:58%">Indikator</th>
                    <th style="width:10%">Jumlah</th>
                    <th style="width:13%">Penyebut</th>
                    <th style="width:19%">Persentase</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="number">{{ $row['value'] ?? 'Belum diketahui' }}</td>
                        <td class="number">{{ $row['denominator'] ?? 'Tidak berlaku' }}</td>
                        <td class="number">{{ $row['percentage'] === null ? 'Tidak berlaku' : $row['percentage'].'%' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </body>
</html>

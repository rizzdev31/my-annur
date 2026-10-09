{{-- Daftar hadir untuk arsip. Tanda tangan dipasang sebagai data-URI karena
     dompdf tidak memuat berkas lewat URL. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Hadir — {{ $kegiatan->nama }}</title>
    <style>
        @page { margin: 22mm 15mm 18mm 15mm; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #111827; }
        .kop { border-bottom: 2.5px solid #2E3160; padding-bottom: 8px; margin-bottom: 14px; }
        .kop table { width: 100%; border-collapse: collapse; }
        .kop td { vertical-align: middle; }
        .kop .logo { width: 58px; }
        .kop .nama { font-size: 15px; font-weight: bold; color: #2E3160; letter-spacing: .3px; }
        .kop .sub { font-size: 9px; color: #4b5563; }
        h1 { font-size: 13px; text-align: center; margin: 0 0 2px; text-transform: uppercase; letter-spacing: .5px; }
        .meta { text-align: center; font-size: 9.5px; color: #4b5563; margin-bottom: 12px; }
        table.daftar { width: 100%; border-collapse: collapse; }
        table.daftar th, table.daftar td { border: 0.7px solid #9ca3af; padding: 5px 6px; }
        table.daftar th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; letter-spacing: .3px; }
        td.no { text-align: center; width: 26px; }
        td.ttd { width: 92px; text-align: center; }
        td.ttd img { height: 38px; }
        .kosong { text-align: center; padding: 24px; color: #6b7280; }
        .ttd-blok { margin-top: 26px; width: 100%; }
        .ttd-blok td { width: 50%; text-align: center; font-size: 10px; vertical-align: top; }
        .ruang { height: 58px; }
        .garis { text-decoration: underline; font-weight: bold; }
        .kaki { margin-top: 14px; font-size: 8px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>

<div class="kop">
    <table>
        <tr>
            @if ($logo)
                <td class="logo"><img src="{{ $logo }}" style="width:52px"></td>
            @endif
            <td>
                <div class="nama">{{ $kegiatan->penyelenggara ?: 'PONDOK PESANTREN AN-NUR' }}</div>
                <div class="sub">Daftar Hadir Kegiatan</div>
            </td>
        </tr>
    </table>
</div>

<h1>Daftar Hadir Tamu</h1>
<div class="meta">
    <strong>{{ $kegiatan->nama }}</strong><br>
    {{ $kegiatan->rentangLabel() }}@if ($kegiatan->lokasi) &middot; {{ $kegiatan->lokasi }}@endif<br>
    Jumlah tamu: {{ $tamu->count() }} orang
</div>

@if ($tamu->isEmpty())
    <p class="kosong">Belum ada tamu yang mengisi buku tamu kegiatan ini.</p>
@else
    <table class="daftar">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Lengkap</th>
                <th>Alamat / Instansi</th>
                <th>Pekerjaan / Jabatan</th>
                <th>Email</th>
                <th>Tanda Tangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tamu as $t)
                <tr>
                    <td class="no">{{ $t->nomor_urut }}</td>
                    <td>{{ $t->nama }}</td>
                    <td>{{ $t->asal }}</td>
                    <td>{{ $t->pekerjaan }}</td>
                    <td style="font-size:8.5px">{{ $t->email }}</td>
                    <td class="ttd">
                        @php($ttd = $t->tandaTanganDataUri())
                        @if ($ttd)
                            <img src="{{ $ttd }}" alt="">
                        @else
                            &mdash;
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<table class="ttd-blok">
    <tr>
        <td>&nbsp;</td>
        <td>
            Sidoarjo, {{ $dicetak }}<br>
            Penanggung Jawab Kegiatan
            <div class="ruang"></div>
            <span class="garis">(……………………………)</span>
        </td>
    </tr>
</table>

<div class="kaki">
    Dicetak oleh {{ $pencetak }} pada {{ $dicetak }} &middot; dokumen dihasilkan sistem An-Nur Smart System
</div>

</body>
</html>

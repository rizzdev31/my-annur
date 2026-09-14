{{--
    Berita Acara Tugas Tambahan — dokumen resmi yang bisa diunduh guru/admin
    untuk pelaporan. Gaya invoice: kop + blok nomor di kanan, tabel rapi,
    kotak ringkasan, lalu tanda tangan.

    Catatan dompdf: tidak mendukung flexbox/grid, jadi tata letak memakai
    <table> dan float. Warna & jarak sengaja sederhana agar hasil cetaknya
    konsisten di semua printer.
--}}
@php
    $navy  = '#2E3160';
    $emas  = '#B8860B';
    $abu   = '#6B7280';
    $garis = '#E5E7EB';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Berita Acara — {{ $tugas->judul }}</title>
    <style>
        @page { margin: 26px 32px 60px 32px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #111827; font-size: 10.5px; line-height: 1.45; }

        .kop { width: 100%; border-collapse: collapse; }
        .kop td { vertical-align: middle; }
        .kop .logo { width: 62px; }
        .kop .logo img { width: 58px; height: auto; }
        .nama-lembaga { font-size: 15px; font-weight: bold; color: {{ $navy }}; letter-spacing: .3px; }
        .sub-lembaga { font-size: 9.5px; color: {{ $abu }}; }
        .rule { height: 3px; background: {{ $navy }}; margin: 8px 0 0 0; }
        .rule-tipis { height: 1px; background: {{ $emas }}; margin-top: 2px; }

        .judul-dok { font-size: 17px; font-weight: bold; color: {{ $navy }}; letter-spacing: 1px; }
        .meta { border: 1px solid {{ $garis }}; border-radius: 4px; padding: 7px 10px; }
        .meta td { font-size: 9.5px; padding: 1px 0; }
        .meta .k { color: {{ $abu }}; padding-right: 8px; }

        h2.sec {
            font-size: 11px; color: {{ $navy }}; margin: 16px 0 6px 0;
            padding-bottom: 3px; border-bottom: 1px solid {{ $garis }};
            text-transform: uppercase; letter-spacing: .6px;
        }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th {
            background: {{ $navy }}; color: #fff; font-size: 9px; text-align: left;
            padding: 6px 7px; font-weight: bold; letter-spacing: .3px;
        }
        table.data td { padding: 5px 7px; border-bottom: 1px solid {{ $garis }}; font-size: 9.5px; vertical-align: top; }
        table.data tr.zebra td { background: #F9FAFB; }
        .tengah { text-align: center; }

        table.rincian { width: 100%; border-collapse: collapse; }
        table.rincian td { padding: 3px 0; font-size: 10px; vertical-align: top; }
        table.rincian td.k { color: {{ $abu }}; width: 130px; }
        table.rincian td.t { width: 10px; color: {{ $abu }}; }

        .pil { display: inline-block; padding: 1px 7px; border-radius: 8px; font-size: 8.5px; font-weight: bold; }
        .pil-ok  { background: #ECFDF5; color: #047857; }
        .pil-no  { background: #FEF2F2; color: #B91C1C; }
        .pil-net { background: #F3F4F6; color: #374151; }

        .ringkas { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .ringkas td { padding: 0; }
        .kotak { border: 1px solid {{ $garis }}; border-left: 3px solid {{ $emas }}; border-radius: 4px; padding: 9px 12px; }
        .kotak .angka { font-size: 16px; font-weight: bold; color: {{ $navy }}; }
        .kotak .label { font-size: 8.5px; color: {{ $abu }}; text-transform: uppercase; letter-spacing: .4px; }

        .ttd { width: 100%; border-collapse: collapse; margin-top: 26px; }
        .ttd td { width: 50%; text-align: center; font-size: 10px; vertical-align: top; }
        .ttd .ruang { height: 52px; }
        .ttd .nama { font-weight: bold; text-decoration: underline; }

        .kaki {
            position: fixed; bottom: -34px; left: 0; right: 0;
            font-size: 8px; color: {{ $abu }}; border-top: 1px solid {{ $garis }}; padding-top: 5px;
        }
        .kosong { color: {{ $abu }}; font-style: italic; }
    </style>
</head>
<body>

{{-- ── KOP ─────────────────────────────────────────────────────────── --}}
<table class="kop">
    <tr>
        @if ($logo)
            <td class="logo"><img src="{{ $logo }}" alt=""></td>
        @endif
        <td>
            <div class="nama-lembaga">{{ $lembaga['nama'] }}</div>
            <div class="sub-lembaga">{{ $lembaga['alamat'] }}</div>
            <div class="sub-lembaga">Telp. {{ $lembaga['telp'] }}</div>
        </td>
    </tr>
</table>
<div class="rule"></div>
<div class="rule-tipis"></div>

{{-- ── JUDUL + META (gaya invoice) ─────────────────────────────────── --}}
<table style="width:100%; border-collapse:collapse; margin-top:14px;">
    <tr>
        <td style="vertical-align:top;">
            <div class="judul-dok">BERITA ACARA</div>
            <div style="font-size:10px; color:{{ $abu }}; margin-top:2px;">Pelaksanaan Tugas Tambahan</div>
        </td>
        <td style="width:250px; vertical-align:top;">
            <table class="meta">
                <tr><td class="k">Nomor</td><td>: {{ $nomor }}</td></tr>
                <tr><td class="k">Tanggal Cetak</td><td>: {{ $dicetak }}</td></tr>
                <tr><td class="k">Status Tugas</td><td>: {{ ucfirst($tugas->status) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

{{-- ── RINCIAN KEGIATAN ────────────────────────────────────────────── --}}
<h2 class="sec">Rincian Kegiatan</h2>
<table class="rincian">
    <tr><td class="k">Nama Kegiatan</td><td class="t">:</td><td><b>{{ $tugas->judul }}</b></td></tr>
    <tr><td class="k">Jenis Pengerjaan</td><td class="t">:</td><td>{{ $tugas->tipe_pengerjaan_label ?? ucfirst(str_replace('_', ' ', $tugas->tipe_pengerjaan)) }}</td></tr>
    <tr><td class="k">Periode</td><td class="t">:</td><td>{{ $periode }}</td></tr>
    @if ($tugas->deskripsi)
        <tr><td class="k">Uraian</td><td class="t">:</td><td>{{ $tugas->deskripsi }}</td></tr>
    @endif
</table>

{{-- ── PENERIMA TUGAS ──────────────────────────────────────────────── --}}
<h2 class="sec">Penerima Tugas &amp; Penyelesaian</h2>
@if ($penugasan->isEmpty())
    <p class="kosong">Belum ada penerima tugas.</p>
@else
    <table class="data">
        <thead>
            <tr>
                <th style="width:22px;">No</th>
                <th>Nama</th>
                <th style="width:92px;">Jabatan</th>
                <th style="width:62px;" class="tengah">Status</th>
                <th style="width:66px;" class="tengah">Diselesaikan</th>
                <th>Laporan / Bukti</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($penugasan as $i => $p)
            <tr @class(['zebra' => $i % 2 === 1])>
                <td class="tengah">{{ $i + 1 }}</td>
                <td>
                    <b>{{ $p->tenagaPendidik?->user?->name ?? '—' }}</b>
                    @if ($p->tenagaPendidik?->nip)
                        <div style="font-size:8.5px; color:{{ $abu }};">NIP {{ $p->tenagaPendidik->nip }}</div>
                    @endif
                </td>
                <td>{{ $p->tenagaPendidik?->jabatan?->nama_jabatan ?? '—' }}</td>
                <td class="tengah">
                    <span class="pil {{ $p->status_pengerjaan === 'selesai' ? 'pil-ok' : 'pil-net' }}">
                        {{ ucfirst($p->status_pengerjaan) }}
                    </span>
                </td>
                <td class="tengah">{{ $p->dikerjakan_pada?->translatedFormat('d/m/Y') ?? '—' }}</td>
                <td>{{ $p->ringkas_bukti ?: '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

{{-- ── ABSENSI KEGIATAN ────────────────────────────────────────────── --}}
@if ($kegiatan->isNotEmpty())
    <h2 class="sec">Absensi Kegiatan</h2>
    @foreach ($kegiatan as $keg)
        <table class="rincian" style="margin-bottom:5px;">
            <tr>
                <td class="k">Sesi {{ $loop->iteration }}</td><td class="t">:</td>
                <td>
                    <b>{{ $keg->nama_kegiatan }}</b>
                    — {{ $keg->tanggal_kegiatan?->translatedFormat('d F Y') }}
                    @if ($keg->jam_mulai)
                        , {{ substr($keg->jam_mulai, 0, 5) }}@if ($keg->jam_selesai)–{{ substr($keg->jam_selesai, 0, 5) }}@endif
                    @endif
                    @if ($keg->lokasi) · {{ $keg->lokasi }} @endif
                </td>
            </tr>
        </table>

        @if ($keg->peserta->isEmpty())
            <p class="kosong" style="margin:0 0 10px 0;">Belum ada peserta tercatat pada sesi ini.</p>
        @else
            <table class="data" style="margin-bottom:12px;">
                <thead>
                    <tr>
                        <th style="width:22px;">No</th>
                        <th>Nama Peserta</th>
                        <th style="width:80px;" class="tengah">Kehadiran</th>
                        <th style="width:52px;" class="tengah">Jam</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($keg->peserta as $j => $ps)
                    <tr @class(['zebra' => $j % 2 === 1])>
                        <td class="tengah">{{ $j + 1 }}</td>
                        <td>{{ $ps->tenagaPendidik?->user?->name ?? '—' }}</td>
                        <td class="tengah">
                            <span class="pil {{ $ps->status_kehadiran === 'hadir' ? 'pil-ok' : 'pil-no' }}">
                                {{ ucfirst(str_replace('_', ' ', $ps->status_kehadiran)) }}
                            </span>
                        </td>
                        <td class="tengah">{{ $ps->jam_hadir ? substr($ps->jam_hadir, 0, 5) : '—' }}</td>
                        <td>{{ $ps->keterangan ?: '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
@endif

{{-- ── RINGKASAN (gaya total invoice) ──────────────────────────────── --}}
<table class="ringkas">
    <tr>
        <td style="width:25%; padding-right:6px;">
            <div class="kotak"><div class="angka">{{ $ringkas['penerima'] }}</div><div class="label">Penerima Tugas</div></div>
        </td>
        <td style="width:25%; padding-right:6px;">
            <div class="kotak"><div class="angka">{{ $ringkas['selesai'] }}</div><div class="label">Sudah Selesai</div></div>
        </td>
        <td style="width:25%; padding-right:6px;">
            <div class="kotak"><div class="angka">{{ $ringkas['sesi'] }}</div><div class="label">Sesi Kegiatan</div></div>
        </td>
        <td style="width:25%;">
            <div class="kotak"><div class="angka">{{ $ringkas['hadir'] }}/{{ $ringkas['peserta'] }}</div><div class="label">Kehadiran</div></div>
        </td>
    </tr>
</table>

{{-- ── TANDA TANGAN ────────────────────────────────────────────────── --}}
<table class="ttd">
    <tr>
        <td>
            Mengetahui,<br>Pimpinan Pondok
            <div class="ruang"></div>
            <div class="nama">............................................</div>
        </td>
        <td>
            {{ $lembaga['kota'] }}, {{ $dicetak }}<br>Dibuat oleh
            <div class="ruang"></div>
            <div class="nama">{{ $pencetak }}</div>
        </td>
    </tr>
</table>

<div class="kaki">
    Dokumen ini dicetak dari An-Nur Smart System pada {{ $dicetakLengkap }} oleh {{ $pencetak }}.
    Keabsahan data mengacu pada catatan sistem.
</div>

</body>
</html>

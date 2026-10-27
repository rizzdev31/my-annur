{{--
    Rekap log aktivitas kinerja satu periode.
    Dicetak lanskap karena satu baris memuat waktu, guru, pergerakan skor,
    sebab, alasan, dan pelakunya — dipotong jadi tidak ada gunanya untuk audit.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Log Kinerja {{ $label }}</title>
    <style>
        @page { margin: 18mm 12mm 16mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1f2937; }
        .kop { border-bottom: 2px solid #2E3160; padding-bottom: 8px; margin-bottom: 10px; }
        .kop h1 { margin: 0; font-size: 14px; color: #2E3160; }
        .kop .sub { font-size: 9px; color: #6b7280; margin-top: 2px; }
        .pita { background: #f3f4f6; padding: 6px 8px; margin-bottom: 10px; font-size: 9px; }
        .pita b { color: #2E3160; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #2E3160; color: #fff; text-align: left; padding: 5px 6px; font-size: 8px;
             text-transform: uppercase; letter-spacing: .3px; }
        td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        tr:nth-child(even) td { background: #fafafa; }
        .angka { text-align: right; white-space: nowrap; }
        .naik { color: #047857; font-weight: bold; }
        .turun { color: #b91c1c; font-weight: bold; }
        .netral { color: #6b7280; }
        h2 { font-size: 11px; color: #2E3160; margin: 14px 0 6px; }
        .kaki { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5px;
                color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 4px; }
        .kosong { padding: 14px; text-align: center; color: #6b7280; font-style: italic; }
    </style>
</head>
<body>

<div class="kop">
    <h1>Log Aktivitas Kinerja — {{ $label }}</h1>
    <div class="sub">
        Jendela penilaian {{ $dari }} s.d. {{ $sampai }}
        @if ($ikutGaji) (mengikuti periode penggajian, bukan bulan kalender) @endif
    </div>
</div>

<div class="pita">
    <b>{{ $riwayat->count() }}</b> pergerakan skor ·
    <b>{{ $naik }}</b> kenaikan ·
    <b>{{ $turun }}</b> penurunan ·
    <b>{{ $perGuru->count() }}</b> guru terdampak
</div>

<h2>A. Rekap per guru</h2>
<table>
    <thead>
        <tr>
            <th style="width:4%">No</th>
            <th style="width:34%">Guru</th>
            <th style="width:12%" class="angka">Skor awal</th>
            <th style="width:12%" class="angka">Skor akhir</th>
            <th style="width:12%" class="angka">Selisih</th>
            <th style="width:12%" class="angka">Jml perubahan</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($perGuru as $i => $g)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $g['nama'] }}</td>
            <td class="angka">{{ $g['dari'] !== null ? number_format($g['dari'], 2, ',', '.') : '—' }}</td>
            <td class="angka">{{ $g['ke'] !== null ? number_format($g['ke'], 2, ',', '.') : '—' }}</td>
            <td class="angka {{ $g['selisih'] === null ? 'netral' : ($g['selisih'] > 0 ? 'naik' : ($g['selisih'] < 0 ? 'turun' : 'netral')) }}">
                @if ($g['selisih'] === null) —
                @else {{ ($g['selisih'] > 0 ? '+' : '') . number_format($g['selisih'], 2, ',', '.') }}
                @endif
            </td>
            <td class="angka">{{ $g['perubahan'] }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="kosong">Tidak ada pergerakan skor pada periode ini.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>B. Rincian pergerakan (urut waktu)</h2>
<table>
    <thead>
        <tr>
            <th style="width:11%">Waktu</th>
            <th style="width:21%">Guru</th>
            <th style="width:8%" class="angka">Dari</th>
            <th style="width:8%" class="angka">Ke</th>
            <th style="width:8%" class="angka">Selisih</th>
            <th style="width:16%">Sebab</th>
            <th style="width:28%">Alasan / pelaku</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($riwayat as $r)
        @php $sel = $r->selisih; @endphp
        <tr>
            <td>{{ $r->created_at?->format('d/m/y H:i') }}</td>
            <td>{{ $r->tenagaPendidik?->user?->name ?? '—' }}</td>
            <td class="angka">{{ $r->skor_total !== null ? number_format((float) $r->skor_total, 2, ',', '.') : '—' }}</td>
            <td class="angka">{{ $r->skor_total_baru !== null ? number_format((float) $r->skor_total_baru, 2, ',', '.') : '—' }}</td>
            <td class="angka {{ $sel === null ? 'netral' : ($sel > 0 ? 'naik' : ($sel < 0 ? 'turun' : 'netral')) }}">
                @if ($sel === null) awal
                @else {{ ($sel > 0 ? '+' : '') . number_format($sel, 2, ',', '.') }}
                @endif
            </td>
            <td>{{ $r->label_sebab }}</td>
            <td>
                @if ($r->alasan){{ $r->alasan }}<br>@endif
                <span class="netral">{{ $r->pengubah?->name ?? 'Sistem (terjadwal)' }}</span>
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="kosong">Belum ada jejak perubahan pada periode ini.</td></tr>
    @endforelse
    </tbody>
</table>

<div class="kaki">
    Dicetak {{ $dicetak }} oleh {{ $pencetak }} · An-Nur Smart System ·
    Sumber: riwayat_rekap_kinerja (terekam otomatis pada setiap perubahan skor)
</div>

</body>
</html>

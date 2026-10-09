@extends('emails.layout')

@section('judul', 'Terima kasih atas kunjungan Anda')
@section('subkop', 'Buku Tamu Digital')
@section('pratinjau', 'Kehadiran Anda pada ' . $kegiatan->nama . ' sudah tercatat.')

@section('isi')
    <p style="margin:0 0 4px;font-size:14px;color:#334155;">
        Assalamu'alaikum warahmatullahi wabarakatuh,
    </p>
    <p style="margin:0 0 20px;font-size:14px;line-height:1.7;color:#334155;">
        Bapak/Ibu <strong style="color:#0f172a;">{{ $tamu->nama }}</strong>, terima kasih atas
        kunjungan Anda. Kehadiran Anda sudah tercatat dalam buku tamu kami.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background-color:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;margin:0 0 22px;">
        <tr>
            <td align="center" style="padding:18px;">
                <p style="margin:0;font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#0369a1;">
                    Nomor Urut Kunjungan
                </p>
                <p style="margin:4px 0 0;font-size:34px;font-weight:800;line-height:1;color:#0369a1;">
                    {{ $tamu->nomor_urut }}
                </p>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px;">
        <tr>
            <td style="font-size:12px;color:#64748b;padding:3px 10px 3px 0;white-space:nowrap;vertical-align:top;">Kegiatan</td>
            <td style="font-size:12px;color:#334155;padding:3px 0;font-weight:600;">{{ $kegiatan->nama }}</td>
        </tr>
        <tr>
            <td style="font-size:12px;color:#64748b;padding:3px 10px 3px 0;white-space:nowrap;vertical-align:top;">Waktu</td>
            <td style="font-size:12px;color:#334155;padding:3px 0;">{{ $kegiatan->rentangLabel() }}</td>
        </tr>
        @if ($kegiatan->lokasi)
            <tr>
                <td style="font-size:12px;color:#64748b;padding:3px 10px 3px 0;white-space:nowrap;vertical-align:top;">Tempat</td>
                <td style="font-size:12px;color:#334155;padding:3px 0;">{{ $kegiatan->lokasi }}</td>
            </tr>
        @endif
        <tr>
            <td style="font-size:12px;color:#64748b;padding:3px 10px 3px 0;white-space:nowrap;vertical-align:top;">Asal</td>
            <td style="font-size:12px;color:#334155;padding:3px 0;">{{ $tamu->asal }}</td>
        </tr>
        <tr>
            <td style="font-size:12px;color:#64748b;padding:3px 10px 3px 0;white-space:nowrap;vertical-align:top;">Jabatan</td>
            <td style="font-size:12px;color:#334155;padding:3px 0;">{{ $tamu->pekerjaan }}</td>
        </tr>
    </table>

    {{-- Inilah gunanya email konfirmasi: kalau alamatnya salah ketik, kami tahu
         sekarang — bukan berminggu kemudian saat notulensi gagal terkirim. --}}
    <p style="margin:0;padding:14px 16px;background-color:#f8fafc;border-radius:10px;font-size:12px;line-height:1.7;color:#64748b;">
        Notulensi/hasil kegiatan akan kami kirimkan ke alamat ini
        (<span style="color:#334155;font-weight:600;">{{ $tamu->email }}</span>) setelah kegiatan selesai.
        Bila alamat ini keliru, mohon sampaikan kepada petugas penerima tamu.
    </p>

    <p style="margin:20px 0 0;font-size:13px;line-height:1.7;color:#334155;">
        Wassalamu'alaikum warahmatullahi wabarakatuh.
    </p>
@endsection

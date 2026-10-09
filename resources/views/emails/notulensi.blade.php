@extends('emails.layout')

@section('judul', 'Notulensi: ' . $kegiatan->nama)
@section('subkop', 'Notulensi Kegiatan')
@section('pratinjau', 'Notulensi ' . $kegiatan->nama . ' — ' . $kegiatan->rentangLabel())

@section('isi')
    <p style="margin:0 0 4px;font-size:14px;color:#334155;">
        Assalamu'alaikum warahmatullahi wabarakatuh,
    </p>
    <p style="margin:0 0 20px;font-size:14px;line-height:1.7;color:#334155;">
        Bapak/Ibu <strong style="color:#0f172a;">{{ $tamu->nama }}</strong>, terima kasih atas
        kehadiran Anda pada kegiatan berikut. Bersama ini kami sampaikan notulensi/hasil kegiatannya.
    </p>

    {{-- Keterangan kegiatan: tabel label-nilai, bukan flex, agar aman di Outlook. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin:0 0 24px;">
        <tr>
            <td style="padding:16px 18px;">
                <p style="margin:0 0 10px;font-size:15px;font-weight:700;color:#0f172a;line-height:1.4;">
                    {{ $kegiatan->nama }}
                </p>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                    <tr>
                        <td style="font-size:12px;color:#64748b;padding:2px 10px 2px 0;white-space:nowrap;vertical-align:top;">Waktu</td>
                        <td style="font-size:12px;color:#334155;padding:2px 0;">{{ $kegiatan->rentangLabel() }}</td>
                    </tr>
                    @if ($kegiatan->lokasi)
                        <tr>
                            <td style="font-size:12px;color:#64748b;padding:2px 10px 2px 0;white-space:nowrap;vertical-align:top;">Tempat</td>
                            <td style="font-size:12px;color:#334155;padding:2px 0;">{{ $kegiatan->lokasi }}</td>
                        </tr>
                    @endif
                    @if ($kegiatan->penyelenggara)
                        <tr>
                            <td style="font-size:12px;color:#64748b;padding:2px 10px 2px 0;white-space:nowrap;vertical-align:top;">Penyelenggara</td>
                            <td style="font-size:12px;color:#334155;padding:2px 0;">{{ $kegiatan->penyelenggara }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="font-size:12px;color:#64748b;padding:2px 10px 2px 0;white-space:nowrap;vertical-align:top;">No. tamu</td>
                        <td style="font-size:12px;color:#334155;padding:2px 0;">{{ $tamu->nomor_urut }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 12px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#0369a1;">
        Notulensi Kegiatan
    </p>

    {!! $kegiatan->notulensiHtml() !!}

    {{-- Versi web: pengganti lampiran PDF. Tamu yang ingin mengarsipkan atau
         mencetak membuka tautan ini, jadi email tetap ringan. --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 0;">
        <tr>
            <td style="background-color:#0369a1;border-radius:10px;">
                <a href="{{ $tautanWeb }}"
                   style="display:inline-block;padding:12px 22px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;">
                    Buka versi web &amp; cetak
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:20px 0 0;font-size:13px;line-height:1.7;color:#334155;">
        Wassalamu'alaikum warahmatullahi wabarakatuh.
    </p>
@endsection

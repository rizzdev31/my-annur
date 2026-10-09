{{--
    Rangka email. Ditulis dengan tabel dan gaya INLINE, bukan kelas Tailwind:
    klien email (Gmail, Outlook) membuang <style> dan tidak mengenal flex/grid.
    Lebar dipatok 600px karena itu batas aman kolom pratinjau Outlook, dan
    seluruh warna dipilih terang agar tetap terbaca bila klien memaksa mode
    gelap tanpa mengubah warna teks.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>@yield('judul')</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;">

{{-- Pratinjau di daftar masuk: tampil sebelum email dibuka. --}}
<div style="display:none;font-size:1px;color:#f1f5f9;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
    @yield('pratinjau')
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9;">
    <tr>
        <td align="center" style="padding:24px 12px;">

            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                   style="width:100%;max-width:600px;background-color:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;">

                {{-- Kop --}}
                <tr>
                    <td style="background-color:#0f172a;padding:20px 28px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="font-size:16px;font-weight:700;color:#ffffff;line-height:1.3;">
                                    Pondok Pesantren An-Nur
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:12px;color:#94a3b8;padding-top:2px;">
                                    @yield('subkop', 'Mubalighin Muhammadiyah')
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Isi --}}
                <tr>
                    <td style="padding:28px;">
                        @yield('isi')
                    </td>
                </tr>

                {{-- Kaki --}}
                <tr>
                    <td style="background-color:#f8fafc;border-top:1px solid #e2e8f0;padding:18px 28px;">
                        <p style="margin:0;font-size:11px;line-height:1.6;color:#94a3b8;">
                            Email ini dikirim otomatis dari sistem buku tamu digital Pondok Pesantren An-Nur
                            karena Anda mencantumkan alamat ini saat mengisi buku tamu kegiatan.
                            Mohon tidak membalas email ini.
                        </p>
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>
</body>
</html>

<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\KegiatanTamu;
use App\Services\BukuTamuService;
use Illuminate\Http\Request;

/**
 * HALAMAN TAMU — publik, tanpa login, dibuka lewat tautan per kegiatan.
 *
 * Ini satu-satunya pintu sistem yang terbuka untuk umum, jadi pengamanannya
 * berlapis dan sengaja tidak mengandalkan captcha (jaringan di lokasi acara
 * sering lemah; captcha justru membuat tamu gagal mengisi):
 *   - token acak 40 karakter di URL, bukan id yang bisa diduga;
 *   - sakelar buka/tutup + kedaluwarsa otomatis;
 *   - pembatasan laju per IP (middleware throttle di rute);
 *   - honeypot + ambang waktu pengisian minimum;
 *   - IP & perangkat disimpan sebagai jejak.
 *
 * Daftar tamu TIDAK pernah ditampilkan di halaman publik — itu data pribadi
 * orang lain. Yang tampil hanya nomor urut si pengisi sendiri.
 */
class BukuTamuController extends Controller
{
    /** Minimal detik antara halaman terbuka dan terkirim (manusia tidak lebih cepat). */
    private const MIN_DETIK_ISI = 4;

    public function show(string $token)
    {
        $kegiatan = KegiatanTamu::where('token', $token)->firstOrFail();
        $alasan   = app(BukuTamuService::class)->alasanTertutup($kegiatan);

        return view('tamu.form', [
            'kegiatan' => $kegiatan,
            'tertutup' => $alasan,
            'jumlah'   => $kegiatan->tamu()->count(),
        ]);
    }

    public function store(Request $request, string $token)
    {
        $kegiatan = KegiatanTamu::where('token', $token)->firstOrFail();

        // Honeypot: kolom tersembunyi yang hanya diisi robot.
        if (filled($request->input('website'))) {
            return back()->withErrors(['nama' => 'Pengisian tidak dapat diproses.'])->withInput();
        }
        // Form yang terkirim dalam sekejap hampir pasti bukan manusia.
        $dibuka = (int) $request->input('dibuka_pada');
        if ($dibuka > 0 && (time() - $dibuka) < self::MIN_DETIK_ISI) {
            return back()->withErrors(['nama' => 'Mohon lengkapi data dengan teliti, lalu kirim ulang.'])->withInput();
        }

        $d = $request->validate([
            'nama'         => 'required|string|min:3|max:180',
            'asal'         => 'required|string|min:3|max:255',
            'pekerjaan'    => 'required|string|min:2|max:180',
            // Email WAJIB (keputusan 9 Okt 2026): notulensi dikirim lewat email.
            // Hanya format yang divalidasi di sini; keberadaan domainnya diperiksa
            // service agar bisa dilewati saat DNS sendiri sedang bermasalah.
            'email'        => 'required|email:rfc|max:180',
            'tanda_tangan' => 'required|string',
        ], [
            'tanda_tangan.required' => 'Tanda tangan belum diisi.',
        ]);

        $svc = app(BukuTamuService::class);
        if ($pesanEmail = $svc->alasanEmailDitolak($d['email'])) {
            return back()->withErrors(['email' => $pesanEmail])->withInput();
        }

        try {
            $tamu = $svc->simpanTamu($kegiatan, $d, $request);
        } catch (\DomainException $e) {
            return back()->withErrors(['nama' => $e->getMessage()])->withInput();
        }

        return redirect()->route('tamu.sukses', $kegiatan->token)
            ->with('tamu_nomor', $tamu->nomor_urut)
            ->with('tamu_nama', $tamu->nama)
            ->with('tamu_email', $tamu->email);
    }

    public function sukses(Request $request, string $token)
    {
        $kegiatan = KegiatanTamu::where('token', $token)->firstOrFail();

        // Tanpa data sesi berarti halaman ini dibuka langsung — kembalikan ke form.
        if (!$request->session()->has('tamu_nomor')) {
            return redirect()->route('tamu.form', $kegiatan->token);
        }

        return view('tamu.sukses', [
            'kegiatan' => $kegiatan,
            'nomor'    => $request->session()->get('tamu_nomor'),
            'nama'     => $request->session()->get('tamu_nama'),
            'email'    => $request->session()->get('tamu_email'),
        ]);
    }
}

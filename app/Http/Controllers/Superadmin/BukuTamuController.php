<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\KegiatanTamu;
use App\Services\BukuTamuService;
use App\Services\TimezoneHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * BUKU TAMU (superadmin) — membuat kegiatan, membagikan tautannya, dan
 * mengarsipkan daftar hadirnya.
 *
 * Pengisian tamu TIDAK dilakukan dari sini; halaman publik yang mengurusnya
 * (lihat Publik\BukuTamuController), supaya nomor urut dan jejaknya tunggal.
 */
class BukuTamuController extends Controller
{
    public function index(Request $request)
    {
        $tahun = (int) ($request->tahun ?: now()->year);
        $svc   = app(BukuTamuService::class);

        $daftar = KegiatanTamu::with('dibuatOleh:id,name')
            ->withCount('tamu')
            ->whereYear('tanggal', $tahun)
            ->orderByDesc('tanggal')->get()
            ->map(function ($k) use ($svc) {
                $r = $svc->ringkasan($k);
                return [
                    'id'             => $k->id,
                    'nama'           => $k->nama,
                    'deskripsi'      => $k->deskripsi,
                    'tanggal'        => $k->tanggal->toDateString(),
                    'tanggal_selesai'=> $k->tanggal_selesai?->toDateString(),
                    'rentang'        => $k->rentangLabel(),
                    'lokasi'         => $k->lokasi,
                    'penyelenggara'  => $k->penyelenggara,
                    'tautan'         => $k->tautanPublik(),
                    'token'          => $k->token,
                    'is_dibuka'      => $k->is_dibuka,
                    'dibuka_sampai'  => $k->dibuka_sampai?->format('Y-m-d H:i'),
                    'kedaluwarsa'    => $k->dibuka_sampai && TimezoneHelper::now()->gt($k->dibuka_sampai),
                    'jumlah_tamu'    => $k->tamu_count,
                    'notulensi_terisi' => filled($k->notulensi),
                    'notulensi_dikirim' => $k->notulensi_dikirim_pada?->format('d M Y H:i'),
                    'ringkasan'      => $r,
                    'dibuat_oleh'    => $k->dibuatOleh?->name,
                ];
            });

        return Inertia::render('Admin/BukuTamu/Index', [
            'kegiatan' => $daftar,
            'tahun'    => $tahun,
            'summary'  => [
                'kegiatan' => $daftar->count(),
                'tamu'     => $daftar->sum('jumlah_tamu'),
                'terbuka'  => $daftar->where('is_dibuka', true)->where('kedaluwarsa', false)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'nama'            => 'required|string|max:180',
            'deskripsi'       => 'nullable|string|max:1000',
            'tanggal'         => 'required|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal',
            'lokasi'          => 'nullable|string|max:180',
            'penyelenggara'   => 'nullable|string|max:180',
            'dibuka_sampai'   => 'nullable|date',
        ]);

        $kegiatan = app(BukuTamuService::class)->buatKegiatan($d, Auth::id());

        return back()->with('success', "Buku tamu \"{$kegiatan->nama}\" dibuat. "
            . 'Bagikan tautannya atau tempel QR-nya di meja penerima tamu.');
    }

    public function update(Request $request, KegiatanTamu $kegiatanTamu)
    {
        $d = $request->validate([
            'nama'            => 'required|string|max:180',
            'deskripsi'       => 'nullable|string|max:1000',
            'tanggal'         => 'required|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal',
            'lokasi'          => 'nullable|string|max:180',
            'penyelenggara'   => 'nullable|string|max:180',
            'dibuka_sampai'   => 'nullable|date',
        ]);

        $kegiatanTamu->update($d);

        return back()->with('success', 'Kegiatan diperbarui.');
    }

    /** Buka/tutup pengisian. Tautannya tetap sama, hanya ditolak saat tertutup. */
    public function toggle(KegiatanTamu $kegiatanTamu)
    {
        $kegiatanTamu->update(['is_dibuka' => !$kegiatanTamu->is_dibuka]);

        return back()->with('success', $kegiatanTamu->is_dibuka
            ? 'Buku tamu dibuka — tautannya sudah bisa diisi.'
            : 'Buku tamu ditutup — tautannya tidak lagi menerima isian.');
    }

    /** Detail + daftar tamu (tanda tangan ikut ditampilkan). */
    public function show(KegiatanTamu $kegiatanTamu)
    {
        $tamu = $kegiatanTamu->tamu()->get()->map(fn ($t) => [
            'id'           => $t->id,
            'nomor_urut'   => $t->nomor_urut,
            'nama'         => $t->nama,
            'asal'         => $t->asal,
            'pekerjaan'    => $t->pekerjaan,
            'email'        => $t->email,
            'tanda_tangan' => $t->tanda_tangan_url,
            'diisi_pada'   => $t->diisi_pada?->format('d M Y H:i'),
            'email_status' => $t->email_status,
            'email_error'  => $t->email_error,
        ]);

        return Inertia::render('Admin/BukuTamu/Show', [
            'kegiatan' => [
                'id'            => $kegiatanTamu->id,
                'nama'          => $kegiatanTamu->nama,
                'deskripsi'     => $kegiatanTamu->deskripsi,
                'rentang'       => $kegiatanTamu->rentangLabel(),
                'lokasi'        => $kegiatanTamu->lokasi,
                'penyelenggara' => $kegiatanTamu->penyelenggara,
                'tautan'        => $kegiatanTamu->tautanPublik(),
                'is_dibuka'     => $kegiatanTamu->is_dibuka,
                'dibuka_sampai' => $kegiatanTamu->dibuka_sampai?->format('Y-m-d H:i'),
                'notulensi'     => $kegiatanTamu->notulensi,
                'notulensi_dikirim' => $kegiatanTamu->notulensi_dikirim_pada?->format('d M Y H:i'),
            ],
            'tamu'      => $tamu,
            'ringkasan' => app(BukuTamuService::class)->ringkasan($kegiatanTamu),
        ]);
    }

    /** Daftar hadir siap arsip: PDF ber-kop dengan gambar tanda tangan. */
    public function cetak(KegiatanTamu $kegiatanTamu)
    {
        $tamu = $kegiatanTamu->tamu()->get();

        $pdf = Pdf::loadView('pdf.buku-tamu', [
            'kegiatan' => $kegiatanTamu,
            'tamu'     => $tamu,
            'logo'     => $this->logo('logo.png'),
            'dicetak'  => TimezoneHelper::now()->translatedFormat('d F Y H:i') . ' WIB',
            'pencetak' => Auth::user()?->name ?? '—',
        ])->setPaper('a4', 'portrait');

        $berkas = 'Daftar-Hadir-' . str($kegiatanTamu->nama)->slug() . '-'
            . $kegiatanTamu->tanggal->format('Ymd') . '.pdf';

        return $pdf->download($berkas);
    }

    private function logo(string $relatif): ?string
    {
        $path = public_path($relatif);
        if (!is_file($path)) return null;

        return 'data:image/png;base64,' . base64_encode((string) file_get_contents($path));
    }
}

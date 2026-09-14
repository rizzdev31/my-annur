<?php

namespace App\Services;

use App\Models\AbsensiKegiatan;
use App\Models\TugasTambahan;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Berita Acara Tugas Tambahan — dokumen resmi untuk pelaporan.
 *
 * Dipakai dua sisi dengan sumber data yang SAMA: admin mengunduh berkas
 * lengkap satu tugas, guru mengunduh versi yang hanya memuat dirinya. Karena
 * penyusunannya satu tempat, keduanya tidak mungkin berbeda isi.
 */
class BeritaAcaraTugasService
{
    private const LEMBAGA = [
        'nama'   => 'Pondok Pesantren Modern An-Nur',
        'alamat' => 'Jl. H. Ahmad Dahlan No.1, Desa Penatarsewu, Kec. Tanggulangin, Kab. Sidoarjo',
        'telp'   => '(031) 8052928',
        'kota'   => 'Sidoarjo',
    ];

    /**
     * Susun PDF berita acara.
     *
     * @param  int|null $hanyaTenagaPendidikId  Bila diisi, hanya penerima ini yang
     *                                          dimuat — dipakai guru yang mengunduh
     *                                          berita acara miliknya sendiri.
     */
    public function pdf(TugasTambahan $tugas, string $pencetak, ?int $hanyaTenagaPendidikId = null)
    {
        $tugas->loadMissing(['penugasan.tenagaPendidik.user', 'penugasan.tenagaPendidik.jabatan']);

        $penugasan = $tugas->penugasan
            ->when($hanyaTenagaPendidikId, fn($c) => $c->where('tenaga_pendidik_id', $hanyaTenagaPendidikId))
            ->sortBy(fn($p) => $p->tenagaPendidik?->user?->name ?? '')
            ->values();

        // Sesi absensi yang bersumber dari tugas ini. Bila guru mengunduh versi
        // miliknya, daftar peserta ikut disaring agar ia tidak membawa data
        // kehadiran rekan lain di dokumen pribadinya.
        $kegiatan = AbsensiKegiatan::with(['peserta.tenagaPendidik.user'])
            ->where('sumber_tipe', 'tugas_tambahan')->where('sumber_id', $tugas->id)
            ->orderBy('tanggal_kegiatan')->orderBy('jam_mulai')->get();

        if ($hanyaTenagaPendidikId) {
            $kegiatan = $kegiatan->each(function ($k) use ($hanyaTenagaPendidikId) {
                $k->setRelation('peserta',
                    $k->peserta->where('tenaga_pendidik_id', $hanyaTenagaPendidikId)->values());
            })->filter(fn($k) => $k->peserta->isNotEmpty())->values();
        }

        $pdf = Pdf::loadView('pdf.berita-acara-tugas', [
            'tugas'         => $tugas,
            'penugasan'     => $penugasan,
            'kegiatan'      => $kegiatan,
            'lembaga'       => self::LEMBAGA,
            'logo'          => $this->logo(),
            'nomor'         => $this->nomor($tugas),
            'periode'       => $this->periode($tugas),
            'dicetak'       => TimezoneHelper::now()->translatedFormat('d F Y'),
            'dicetakLengkap'=> TimezoneHelper::now()->translatedFormat('d F Y H:i') . ' WIB',
            'pencetak'      => $pencetak,
            'ringkas'       => [
                'penerima' => $penugasan->count(),
                'selesai'  => $penugasan->where('status_pengerjaan', 'selesai')->count(),
                'sesi'     => $kegiatan->count(),
                'peserta'  => $kegiatan->sum(fn($k) => $k->peserta->count()),
                'hadir'    => $kegiatan->sum(fn($k) => $k->peserta->where('status_kehadiran', 'hadir')->count()),
            ],
        ]);

        return $pdf->setPaper('a4', 'portrait');
    }

    /** Nama berkas unduhan — memuat judul tugas agar mudah dicari di folder. */
    public function namaBerkas(TugasTambahan $tugas, ?string $imbuhan = null): string
    {
        $judul = \Illuminate\Support\Str::slug(\Illuminate\Support\Str::limit($tugas->judul, 45, ''));

        return collect(['berita-acara', $judul, $imbuhan, $tugas->id])
            ->filter()->implode('-') . '.pdf';
    }

    /**
     * Logo di-embed sebagai data URI: dompdf tidak selalu bisa mengambil berkas
     * lewat URL (mis. saat berjalan di container tanpa akses ke dirinya sendiri).
     */
    private function logo(): ?string
    {
        $path = public_path('logo.png');
        if (!is_file($path)) return null;

        return 'data:image/png;base64,' . base64_encode((string) file_get_contents($path));
    }

    private function nomor(TugasTambahan $tugas): string
    {
        return sprintf('%03d/BA-TT/%s/%s',
            $tugas->id,
            $this->romawi((int) TimezoneHelper::now()->month),
            TimezoneHelper::now()->year
        );
    }

    private function periode(TugasTambahan $tugas): string
    {
        $mulai   = $tugas->tanggal_mulai?->translatedFormat('d F Y');
        $selesai = $tugas->tanggal_selesai?->translatedFormat('d F Y');

        if ($mulai && $selesai && $mulai !== $selesai) return "{$mulai} s/d {$selesai}";

        return $mulai ?? $selesai ?? '—';
    }

    private function romawi(int $bulan): string
    {
        return ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'][$bulan - 1] ?? (string) $bulan;
    }
}

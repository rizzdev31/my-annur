<?php

namespace App\Services;

use App\Models\KegiatanTamu;
use App\Models\Tamu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * BUKU TAMU — satu pintu untuk pengisian dari halaman publik.
 *
 * Halaman tamu adalah SATU-SATUNYA halaman sistem yang bisa diakses tanpa login,
 * jadi seluruh pemeriksaan ada di sini, bukan di view:
 *  - kegiatan harus dibuka & belum kedaluwarsa;
 *  - nomor urut diambil di dalam transaksi berkunci — di acara, puluhan tamu
 *    memindai QR dan menekan kirim hampir bersamaan;
 *  - tanda tangan divalidasi isinya, bukan hanya ada-tidaknya.
 */
class BukuTamuService
{
    /** Ukuran maksimal data tanda tangan yang diterima (base64), ±1,5 MB. */
    public const MAKS_TTD_BYTE = 1_500_000;

    /** Panjang minimal data PNG agar bukan kanvas kosong / satu titik. */
    private const MIN_TTD_BYTE = 1_500;

    // ══════════════════════════════════════════════════════════════════════
    // Kegiatan
    // ══════════════════════════════════════════════════════════════════════

    public function buatKegiatan(array $d, ?int $userId = null): KegiatanTamu
    {
        return KegiatanTamu::create([
            'nama'            => $d['nama'],
            'deskripsi'       => $d['deskripsi'] ?? null,
            'tanggal'         => $d['tanggal'],
            'tanggal_selesai' => $d['tanggal_selesai'] ?? null,
            'lokasi'          => $d['lokasi'] ?? null,
            'penyelenggara'   => $d['penyelenggara'] ?? null,
            'token'           => KegiatanTamu::tokenBaru(),
            'is_dibuka'       => true,
            // Tanpa batas waktu, tautan yang beredar di grup WA masih bisa diisi
            // berbulan-bulan kemudian. Bawaannya: tutup sehari setelah acara.
            'dibuka_sampai'   => $d['dibuka_sampai']
                ?? \Carbon\Carbon::parse($d['tanggal_selesai'] ?? $d['tanggal'])->endOfDay()->addDay(),
            'dibuat_oleh'     => $userId,
        ]);
    }

    /**
     * Mengapa buku tamu ini tidak bisa diisi (null = boleh diisi).
     * Pesannya langsung ditampilkan ke tamu, jadi ditulis untuk orang luar.
     */
    public function alasanTertutup(KegiatanTamu $kegiatan): ?string
    {
        if (!$kegiatan->is_dibuka) {
            return 'Buku tamu kegiatan ini sudah ditutup.';
        }
        if ($kegiatan->dibuka_sampai && TimezoneHelper::now()->gt($kegiatan->dibuka_sampai)) {
            return 'Masa pengisian buku tamu ini sudah berakhir pada '
                . $kegiatan->dibuka_sampai->locale('id')->isoFormat('D MMMM YYYY, HH:mm') . '.';
        }

        return null;
    }

    // ══════════════════════════════════════════════════════════════════════
    // Pengisian oleh tamu
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Simpan satu tamu. Nomor urut diberikan sistem.
     *
     * @param  array  $d  nama, asal, pekerjaan, email, tanda_tangan (data-URL PNG)
     * @throws \DomainException
     */
    public function simpanTamu(KegiatanTamu $kegiatan, array $d, ?Request $request = null): Tamu
    {
        if ($alasan = $this->alasanTertutup($kegiatan)) {
            throw new \DomainException($alasan);
        }

        $relatif = $this->simpanTandaTangan($d['tanda_tangan'], $kegiatan);

        try {
            return DB::transaction(function () use ($kegiatan, $d, $request, $relatif) {
                // Kunci baris kegiatannya, bukan tabel tamu: dua tamu yang menekan
                // kirim pada detik yang sama tidak boleh mendapat nomor yang sama.
                $terkunci = KegiatanTamu::whereKey($kegiatan->id)->lockForUpdate()->first();

                $nomor = (int) Tamu::where('kegiatan_tamu_id', $terkunci->id)->max('nomor_urut') + 1;

                return Tamu::create([
                    'kegiatan_tamu_id' => $terkunci->id,
                    'nomor_urut'       => $nomor,
                    'nama'             => $this->rapikan($d['nama']),
                    'asal'             => $this->rapikan($d['asal']),
                    'pekerjaan'        => $this->rapikan($d['pekerjaan']),
                    'email'            => Str::lower(trim($d['email'])),
                    'tanda_tangan'     => $relatif,
                    'ip'               => $request?->ip(),
                    'perangkat'        => Str::limit((string) $request?->userAgent(), 240, ''),
                    'diisi_pada'       => TimezoneHelper::now(),
                ]);
            });
        } catch (\Throwable $e) {
            // Gagal menyimpan baris → jangan tinggalkan berkas tanda tangan menggantung.
            Storage::disk('public')->delete($relatif);
            throw $e;
        }
    }

    /**
     * Simpan tanda tangan dari data-URL kanvas menjadi berkas PNG.
     *
     * @throws \DomainException bila bukan PNG, terlalu besar, atau nyaris kosong
     */
    public function simpanTandaTangan(string $dataUrl, KegiatanTamu $kegiatan): string
    {
        if (!preg_match('/^data:image\/png;base64,/', $dataUrl)) {
            throw new \DomainException('Tanda tangan tidak terbaca. Mohon tanda tangani ulang.');
        }
        if (strlen($dataUrl) > self::MAKS_TTD_BYTE) {
            throw new \DomainException('Tanda tangan terlalu besar. Mohon tanda tangani ulang.');
        }

        $biner = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);
        if ($biner === false || strlen($biner) < self::MIN_TTD_BYTE) {
            // Kanvas kosong atau satu titik menghasilkan PNG sangat kecil.
            throw new \DomainException('Tanda tangan belum terisi. Mohon tanda tangani pada kotak yang tersedia.');
        }

        $nama = 'buku-tamu/' . $kegiatan->id . '/' . Str::uuid() . '.png';
        Storage::disk('public')->put($nama, $biner);

        return $nama;
    }

    // ══════════════════════════════════════════════════════════════════════
    // Pembantu
    // ══════════════════════════════════════════════════════════════════════

    /** Ringkasan untuk kartu admin. */
    public function ringkasan(KegiatanTamu $kegiatan): array
    {
        $tamu = $kegiatan->tamu()->get(['id', 'email', 'email_status']);

        return [
            'tamu'      => $tamu->count(),
            'email'     => $tamu->pluck('email')->unique()->count(),
            'terkirim'  => $tamu->where('email_status', 'terkirim')->count(),
            'gagal'     => $tamu->where('email_status', 'gagal')->count(),
            'menunggu'  => $tamu->whereIn('email_status', ['belum', 'menunggu'])->count(),
        ];
    }

    /** Rapikan isian tamu: spasi ganda & huruf kapital asal-asalan. */
    private function rapikan(string $teks): string
    {
        return Str::limit(preg_replace('/\s+/u', ' ', trim($teks)), 175, '');
    }
}

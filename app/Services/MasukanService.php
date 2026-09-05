<?php

namespace App\Services;

use App\Models\Masukan;
use App\Models\MasukanPesan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Saran & Masukan — GERBANG TUNGGAL.
 *
 * Semua penulisan utas/pesan WAJIB lewat sini agar penanda belum-dibaca,
 * status utas, dan notifikasi selalu ikut terbarui. Jangan menulis
 * MasukanPesan langsung dari controller.
 *
 * CHATBOT (Gemini) nanti: cukup panggil `tambahPesan($m, 'bot', null, $teks,
 * [], ['model' => '...', 'token' => ...])`. Tidak ada jalur khusus yang perlu
 * dibuat — bot memakai pintu yang sama dengan manusia.
 */
class MasukanService
{
    /** Maksimal lampiran per pesan (menahan biaya penyimpanan). */
    public const MAKS_LAMPIRAN = 3;

    /** Buat utas baru + pesan pertama dari pelapor. */
    public function buat(
        User $pelapor, string $kategori, string $judul, string $isi,
        ?string $modul = null, array $foto = []
    ): Masukan {
        return DB::transaction(function () use ($pelapor, $kategori, $judul, $isi, $modul, $foto) {
            $m = Masukan::create([
                'user_id'   => $pelapor->id,
                'kategori'  => in_array($kategori, Masukan::KATEGORI, true) ? $kategori : 'saran',
                'judul'     => $judul,
                'modul'     => $modul,
                'status'    => 'baru',
                // Laporan bug lebih mendesak daripada usulan — bantu triase admin
                // sejak awal; admin tetap bisa menurunkan/menaikkan.
                'prioritas' => $kategori === 'bug' ? 'tinggi' : 'normal',
            ]);

            $this->tambahPesan($m, 'guru', $pelapor->id, $isi, $foto);

            NotifikasiService::event('masukan.baru', [
                'judul' => 'Masukan Baru: ' . Masukan::LABEL_KATEGORI[$m->kategori],
                'pesan' => $pelapor->name . ' — ' . $m->judul,
                'tipe'  => 'pengumuman',
                'data'  => ['route' => '/admin/masukan/' . $m->id],
            ]);

            return $m->fresh();
        });
    }

    /**
     * Tambah pesan ke utas. Satu-satunya cara menulis pesan.
     *
     * @param  'guru'|'admin'|'bot'  $tipe
     * @param  array<UploadedFile|string>  $foto  UploadedFile (diunggah) atau path siap pakai
     */
    public function tambahPesan(
        Masukan $m, string $tipe, ?int $userId, string $isi,
        array $foto = [], ?array $meta = null
    ): MasukanPesan {
        $paths = [];
        foreach (array_slice($foto, 0, self::MAKS_LAMPIRAN) as $f) {
            $paths[] = $f instanceof UploadedFile ? $f->store('masukan', 'public') : (string) $f;
        }

        $pesan = MasukanPesan::create([
            'masukan_id'    => $m->id,
            'pengirim_tipe' => $tipe,
            'user_id'       => $userId,
            'isi'           => $isi,
            'lampiran'      => $paths ?: null,
            'meta'          => $meta,
        ]);

        // Pesan dari satu sisi = belum dibaca oleh sisi lawan. Pesan bot dianggap
        // datang dari sisi admin (jawaban sistem untuk pelapor).
        //
        // Sengaja HANYA menyentuh kolom sisi yang berubah — jangan baca-lalu-tulis
        // kedua kolom: instance hasil create() belum memuat nilai default dari DB
        // (terbaca null → gagal simpan), dan menulis ulang nilai lama bisa
        // menghapus penanda yang baru diset permintaan lain.
        Masukan::whereKey($m->id)->update([
            $tipe === 'guru' ? 'belum_dibaca_admin' : 'belum_dibaca_user' => true,
            'pesan_terakhir_pada' => $pesan->created_at,
            'updated_at'          => now(),
        ]);
        $m->refresh();

        return $pesan;
    }

    /** Balasan admin (atau bot) — utas 'baru' otomatis naik jadi 'diproses'. */
    public function balasAdmin(Masukan $m, ?User $admin, string $isi, array $foto = [], bool $bot = false): MasukanPesan
    {
        return DB::transaction(function () use ($m, $admin, $isi, $foto, $bot) {
            $pesan = $this->tambahPesan($m, $bot ? 'bot' : 'admin', $bot ? null : $admin?->id, $isi, $foto);

            if ($m->status === 'baru') {
                $m->forceFill([
                    'status'         => 'diproses',
                    'ditangani_oleh' => $m->ditangani_oleh ?? $admin?->id,
                ])->save();
            }

            $this->beritahuPelapor($m, 'Balasan Masukan', 'Masukan Anda "' . $m->judul . '" dibalas.');

            return $pesan;
        });
    }

    /** Balasan dari pelapor sendiri. */
    public function balasPelapor(Masukan $m, User $pelapor, string $isi, array $foto = []): MasukanPesan
    {
        return $this->tambahPesan($m, 'guru', $pelapor->id, $isi, $foto);
    }

    /** Ubah status utas + catat penanganan. Menulis pesan sistem agar terlacak. */
    public function ubahStatus(Masukan $m, User $admin, string $status, ?string $catatan = null): Masukan
    {
        if (!in_array($status, Masukan::STATUS, true)) {
            throw new \DomainException('Status tidak dikenal.');
        }

        return DB::transaction(function () use ($m, $admin, $status, $catatan) {
            $lama = $m->status;

            $m->forceFill([
                'status'         => $status,
                'ditangani_oleh' => $m->ditangani_oleh ?? $admin->id,
                'catatan_admin'  => $catatan ?? $m->catatan_admin,
                'selesai_pada'   => in_array($status, ['selesai', 'ditolak'], true) ? now() : null,
            ])->save();

            if ($lama !== $status) {
                // Jejak perubahan status ikut masuk percakapan supaya pelapor
                // melihat riwayatnya, bukan hanya label yang tiba-tiba berubah.
                $this->tambahPesan($m, 'admin', $admin->id,
                    'Status diubah: ' . (Masukan::LABEL_STATUS[$lama] ?? $lama)
                        . ' → ' . (Masukan::LABEL_STATUS[$status] ?? $status)
                        . ($catatan ? "\n" . $catatan : ''),
                    [], ['sistem' => true]);

                $this->beritahuPelapor($m, 'Status Masukan Diperbarui',
                    '"' . $m->judul . '" kini berstatus ' . (Masukan::LABEL_STATUS[$status] ?? $status) . '.');
            }

            return $m->fresh();
        });
    }

    /** Tandai utas sudah dibaca oleh satu sisi. */
    public function tandaiDibaca(Masukan $m, bool $sisiAdmin): void
    {
        $kolom = $sisiAdmin ? 'belum_dibaca_admin' : 'belum_dibaca_user';
        if ($m->{$kolom}) $m->forceFill([$kolom => false])->save();
    }

    private function beritahuPelapor(Masukan $m, string $judul, string $pesan): void
    {
        if (!$m->user) return;

        NotifikasiService::event('masukan.balasan', [
            'judul' => $judul,
            'pesan' => $pesan,
            'tipe'  => 'pengumuman',
            'data'  => ['route' => '/masukan/' . $m->id],
        ], [$m->user]);
    }
}

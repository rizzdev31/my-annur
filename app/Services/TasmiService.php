<?php

namespace App\Services;

use App\Models\KoreksiPencapaian;
use App\Models\TugasTasmi;
use App\Models\TugasTambahan;
use App\Models\PenugasanTambahan;
use App\Models\HafalanJuz;
use App\Models\Santri;
use App\Models\SettingTahfidz;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Tasmi' sebagai tugas tambahan ber-vakasi.
 * Alur: pengampu menunjuk penguji (juz harus 'selesai') → penguji menilai →
 * juz tasmi_lulus (jika lulus) + penugasan selesai → vakasi mengalir ke payroll.
 */
class TasmiService
{
    /** Pengampu menunjuk guru penguji untuk tasmi' satu juz seorang santri. */
    public function tunjukPenguji(int $santriId, int $juz, int $pengampuId, int $pengujiId, ?int $userId): TugasTasmi
    {
        // Satu sumber kelayakan, sama dengan gerbang tombol tasmi' di PWA:
        // dulu di sini hanya `status === 'selesai'`, sehingga juz yang statusnya
        // penuh karena setoran terhitung ganda tetap bisa ditunjuk pengujinya.
        if ($alasan = app(TahfidzService::class)->alasanBelumBolehTasmi($santriId, $juz)) {
            throw new \DomainException($alasan);
        }
        if (TugasTasmi::where('santri_id', $santriId)->where('juz', $juz)->where('status', 'ditugaskan')->exists()) {
            throw new \DomainException("Sudah ada penugasan tasmi' aktif untuk juz $juz.");
        }
        if ($pengujiId === $pengampuId) {
            throw new \DomainException('Penguji tasmi harus guru lain (bukan pengampu sendiri).');
        }

        $santri = Santri::findOrFail($santriId);
        // Nominal vakasi tasmi: utamakan Setting Vakasi (tipe 'tasmi' aktif),
        // fallback ke SettingTahfidz.vakasi_tasmi (kompat lama).
        $nominal = (float) (
            \App\Models\SettingVakasi::where('tipe_aktivitas', 'tasmi')->where('is_aktif', true)->value('nominal')
            ?? SettingTahfidz::get()->vakasi_tasmi
            ?? 0
        );

        return DB::transaction(function () use ($santri, $juz, $pengampuId, $pengujiId, $userId, $nominal) {
            $tt = TugasTambahan::create([
                'judul'           => "Tasmi' Juz $juz — {$santri->nama_lengkap}",
                'deskripsi'       => "Menguji tasmi' juz $juz santri {$santri->nama_lengkap}.",
                'tanggal_mulai'   => Carbon::today()->toDateString(),
                'tipe'            => 'individu',
                'tipe_pengerjaan' => 'mandiri',
                'vakasi_override' => $nominal,
                'wajib_laporan'   => false,
                'status'          => 'aktif',
                'dibuat_oleh'     => $userId,
            ]);

            $pn = PenugasanTambahan::create([
                'tugas_tambahan_id'  => $tt->id,
                'tenaga_pendidik_id' => $pengujiId,
                'status_pengerjaan'  => 'belum',
                'disetujui'          => true, // ditunjuk pengampu → langsung sah
            ]);

            return TugasTasmi::create([
                'santri_id'         => $santri->id,
                'juz'               => $juz,
                'pengampu_id'       => $pengampuId,
                'penguji_id'        => $pengujiId,
                'tugas_tambahan_id' => $tt->id,
                'penugasan_id'      => $pn->id,
                'status'            => 'ditugaskan',
            ]);
        });
    }

    /**
     * BATALKAN penunjukan penguji — wewenang superadmin (keputusan 14 Okt 2026:
     * boleh membatalkan yang sudah dinilai, dengan alasan + jejak).
     *
     * Sebelumnya tidak ada jalan membatalkan sama sekali: `TasmiService` hanya
     * punya tunjuk/lihat/nilai, dan tidak satu pun rute superadmin. Akibatnya
     * penunjukan yang salah (salah penguji, salah santri, salah juz)
     * menggantung selamanya — terukur 5 tugas tasmi & 16 tugas tasnif berstatus
     * `ditugaskan` yang tak bisa disentuh siapa pun.
     *
     * Yang ikut dibereskan, karena penunjukan tasmi' MEMBUAT tugas tambahan
     * ber-vakasi: penugasannya ditandai tidak disetujui + tidak selesai supaya
     * vakasinya tidak mengalir ke payroll, dan tugas tambahannya dibatalkan.
     * Bila tasmi'-nya sudah dinilai, status juz dikembalikan dari `tasmi_lulus`
     * ke `selesai` sehingga tombol tasmi' muncul lagi.
     *
     * @throws \DomainException
     */
    public function batalkanTugas(int $tugasTasmiId, ?int $userId, string $alasan): TugasTasmi
    {
        $tt = TugasTasmi::findOrFail($tugasTasmiId);
        if ($tt->status === 'dibatalkan') {
            throw new \DomainException('Penunjukan ini sudah dibatalkan.');
        }
        if (trim($alasan) === '') {
            throw new \DomainException('Alasan pembatalan wajib diisi.');
        }

        $sudahDinilai = $tt->status === 'selesai';

        // Vakasi yang sudah terbit tidak boleh berubah diam-diam.
        if ($sudahDinilai && $tt->penugasan_id) {
            $dilaporkan = PenugasanTambahan::where('id', $tt->penugasan_id)->value('dilaporkan_pada');
            if ($dilaporkan && app(KoreksiPembelajaranService::class)
                ->periodeSudahBerslip(Carbon::parse($dilaporkan)->toDateString())) {
                throw new \DomainException(
                    'Vakasi tasmi ini sudah masuk periode penggajian yang berslip. '
                    . 'Batalkan lewat koreksi penggajian, bukan dari sini.'
                );
            }
        }

        return DB::transaction(function () use ($tt, $userId, $alasan, $sudahDinilai) {
            if ($sudahDinilai) {
                // Juz kembali wajib tasmi; hasil nilainya tetap tersimpan sebagai
                // riwayat, hanya tidak lagi mengunci statusnya.
                HafalanJuz::where('santri_id', $tt->santri_id)->where('juz', $tt->juz)
                    ->where('status', 'tasmi_lulus')
                    ->update(['status' => 'selesai']);
            }

            if ($tt->penugasan_id) {
                PenugasanTambahan::where('id', $tt->penugasan_id)->update([
                    'disetujui'         => false,   // payroll melewati yang tidak disetujui
                    'status_pengerjaan' => 'tidak_selesai',
                    'dilaporkan_pada'   => null,
                    'laporan'           => 'Dibatalkan superadmin — ' . $alasan,
                ]);
            }
            if ($tt->tugas_tambahan_id) {
                TugasTambahan::where('id', $tt->tugas_tambahan_id)->update(['status' => 'dibatalkan']);
            }

            $tt->update([
                'status'          => 'dibatalkan',
                'dibatalkan_oleh' => $userId,
                'dibatalkan_pada' => now(),
                'alasan_batal'    => $alasan,
            ]);

            KoreksiPencapaian::create([
                'santri_id'      => $tt->santri_id,
                'tipe'           => 'tahfidz',
                'juz'            => $tt->juz,
                'field'          => 'tugas_tasmi',
                'nilai_lama'     => $sudahDinilai ? 'selesai dinilai' : 'ditugaskan',
                'nilai_baru'     => 'dibatalkan',
                'alasan'         => $alasan,
                'dikoreksi_oleh' => $userId,
            ]);

            return $tt->fresh();
        });
    }

    /** Daftar tasmi' yang ditugaskan ke seorang penguji (belum dinilai). */
    public function tasmiSaya(int $pengujiId)
    {
        return TugasTasmi::where('penguji_id', $pengujiId)->where('status', 'ditugaskan')
            ->with(['santri:id,nama_lengkap,nip', 'pengampu.user:id,name'])
            ->orderBy('created_at')->get();
    }

    /** Penguji menilai tasmi' → catat setoran, juz tasmi_lulus (jika lulus), penugasan selesai (vakasi). */
    /**
     * Penguji menilai tasmi' via 4 rubrik (masing-masing 1-10):
     * Kelancaran, Makhorijul Huruf, Tajwid, Fashohah.
     * Nilai akhir = rata-rata 4 rubrik (desimal); lulus bila rata-rata >= 8 (ambang tasmi').
     *
     * @param array{kelancaran:float,makhorijul_huruf:float,tajwid:float,fashohah:float} $rubrik
     */
    public function nilaiTasmi(int $tugasTasmiId, int $pengujiId, array $rubrik, ?string $catatan): array
    {
        $tt = TugasTasmi::findOrFail($tugasTasmiId);
        if ($tt->penguji_id !== $pengujiId) {
            throw new \DomainException('Tugas tasmi ini bukan milik Anda.');
        }
        if ($tt->status === 'selesai') {
            throw new \DomainException('Tasmi ini sudah dinilai.');
        }

        $komponen = [
            (float) $rubrik['kelancaran'],
            (float) $rubrik['makhorijul_huruf'],
            (float) $rubrik['tajwid'],
            (float) $rubrik['fashohah'],
        ];
        $nilai = round(array_sum($komponen) / count($komponen), 2); // rata-rata desimal

        // Catat sebagai setoran tasmi (set juz tasmi_lulus bila lulus, ambang tasmi 8) — reuse logika tahfidz.
        $setoran = (new TahfidzService())->catatSetoran([
            'santri_id'          => $tt->santri_id,
            'tenaga_pendidik_id' => $pengujiId,
            'jenis'              => 'tasmi',
            'juz'                => $tt->juz,
            'nilai'              => $nilai,
            'catatan'            => $catatan,
        ]);
        $lulus = (bool) $setoran->lulus;

        DB::transaction(function () use ($tt, $nilai, $lulus, $catatan, $rubrik) {
            $tt->update([
                'nilai'                  => $nilai,
                'nilai_kelancaran'       => $rubrik['kelancaran'],
                'nilai_makhorijul_huruf' => $rubrik['makhorijul_huruf'],
                'nilai_tajwid'           => $rubrik['tajwid'],
                'nilai_fashohah'         => $rubrik['fashohah'],
                'lulus'                  => $lulus,
                'catatan'                => $catatan,
                'status'                 => 'selesai',
            ]);

            // Selesaikan penugasan → vakasi mengalir ke payroll bulan ini.
            if ($tt->penugasan_id) {
                PenugasanTambahan::where('id', $tt->penugasan_id)->update([
                    'status_pengerjaan' => 'selesai',
                    'dilaporkan_pada'   => now(),
                    'laporan'           => "Tasmi' juz {$tt->juz} — nilai {$nilai}" . ($lulus ? ' (lulus)' : ' (belum lulus)'),
                ]);
            }
        });

        return ['lulus' => $lulus, 'nilai' => $nilai, 'rubrik' => $rubrik, 'ambang' => TahfidzService::NILAI_LULUS_TASMI];
    }
}

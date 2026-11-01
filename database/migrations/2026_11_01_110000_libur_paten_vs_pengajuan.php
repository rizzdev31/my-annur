<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DUA MODE LIBUR GURU ASRAMA.
 *
 * Tidak semua guru asrama sama:
 *
 *  • LIBUR PATEN  — harinya tetap dan pasti (mis. selalu Jumat). Tidak diajukan,
 *    tidak berubah; kalau sesekali perlu bergeser, admin memakai tukar/pindah
 *    di layar Libur Individu.
 *  • LIBUR PENGAJUAN — fleksibel, dipilih sendiri tiap pekan antara Jumat atau
 *    Ahad lewat Pengajuan Izin.
 *
 * Karena itu `boleh_libur_pekanan` saja tidak cukup: ia hanya menjawab "berhak
 * atau tidak", bukan "lewat jalur mana". `hari_libur_paten` mengisi kekurangan
 * itu — terisi berarti PATEN (nomor hari ISO 1=Senin … 7=Ahad), null berarti
 * lewat pengajuan.
 *
 * Diisi awal dari kenyataan di data: 4 guru yang punya libur bertipe `rutin`
 * (Daffa, Thoriq, Abi Yasa, Miqdad) semuanya berhari Jumat.
 *
 * Sekaligus menambal lubang yang sudah menganga: generator libur rutin itu
 * MANUAL per bulan, dan terukur 1 Nov 2026 baris terakhirnya 30 Okt — November
 * kosong sama sekali. Libur "pasti" keempat guru itu lapse tanpa suara, dan
 * Jumat 6 Nov mereka akan dianggap masuk lalu dialfa. Penambalnya ada di
 * command `libur:paten-generate` yang dijadwalkan harian.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tenaga_pendidik', 'hari_libur_paten')) {
            Schema::table('tenaga_pendidik', function (Blueprint $t) {
                $t->unsignedTinyInteger('hari_libur_paten')->nullable()
                    ->after('boleh_libur_pekanan')
                    ->comment('Nomor hari ISO 1-7. Terisi = libur paten; null = lewat pengajuan');
            });
        }

        // Tetapkan mode PATEN bagi yang sudah nyata punya pola libur rutin.
        // Harinya diambil dari hari yang PALING SERING muncul pada baris
        // rutin-nya, bukan dari baris terakhir: satu kali tukar libur tidak
        // boleh mengubah kesimpulan tentang hari patennya.
        $rutin = DB::table('libur_tendik')->where('tipe', 'rutin')
            ->select('tenaga_pendidik_id', 'tanggal')->get()
            ->groupBy('tenaga_pendidik_id');

        foreach ($rutin as $tpId => $baris) {
            $hari = $baris
                ->map(fn ($b) => (int) \Carbon\Carbon::parse($b->tanggal)->dayOfWeekIso)
                ->countBy()->sortDesc()->keys()->first();

            if (! $hari) continue;

            DB::table('tenaga_pendidik')->where('id', $tpId)->update([
                'hari_libur_paten'    => $hari,
                // Tetap penerima hak libur asrama, hanya jalurnya berbeda.
                'boleh_libur_pekanan' => true,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenaga_pendidik', 'hari_libur_paten')) {
            Schema::table('tenaga_pendidik', function (Blueprint $t) {
                $t->dropColumn('hari_libur_paten');
            });
        }
    }
};

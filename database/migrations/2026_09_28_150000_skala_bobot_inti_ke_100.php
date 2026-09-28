<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menskalakan bobot 3 komponen inti agar berjumlah 100.
 *
 * Dulu jumlahnya 85 karena 15 "dipinjam" untuk bobot_piket yang ternyata tidak
 * pernah dipakai rumus. Skor selalu dinormalisasi ke jumlah bobot, jadi
 * penskalaan ini TIDAK mengubah skor siapa pun — hanya membuat angka di Setting
 * Kinerja terbaca sebagai persentase yang sebenarnya (mis. "Absensi 41%"),
 * bukan 35% yang diam-diam berarti 41%.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('setting_kinerja')->get() as $s) {
            $jml = (float) $s->bobot_absensi + (float) $s->bobot_tugas + (float) $s->bobot_administrasi;
            if ($jml <= 0 || abs($jml - 100) < 0.01) continue;   // sudah 100 → lewati

            // Dibulatkan 2 desimal, sisa pembulatan ditambahkan ke komponen
            // terbesar supaya jumlahnya tepat 100,00.
            $a = round((float) $s->bobot_absensi      / $jml * 100, 2);
            $b = round((float) $s->bobot_tugas        / $jml * 100, 2);
            $c = round(100 - $a - $b, 2);

            DB::table('setting_kinerja')->where('id', $s->id)->update([
                'bobot_absensi'      => $a,
                'bobot_tugas'        => $b,
                'bobot_administrasi' => $c,
                'updated_at'         => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Komposisi lama (35/30/20 + piket 15) — hanya untuk pemulihan manual.
        DB::table('setting_kinerja')->update([
            'bobot_absensi'      => 35,
            'bobot_tugas'        => 30,
            'bobot_administrasi' => 20,
            'updated_at'         => now(),
        ]);
    }
};

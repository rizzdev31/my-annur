<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix setting_jam_kerja:
 * 1. is_default = 0 → 1 (supaya getDefault() ketemu)
 * 2. hari_kerja double-encoded → proper JSON array
 */
return new class extends Migration
{
    public function up(): void
    {
        // Fix is_default: set id=1 jadi default
        DB::table('setting_jam_kerja')->where('id', 1)->update([
            'is_default' => true,
        ]);

        // Fix hari_kerja: double-encoded string → proper JSON array
        // DB saat ini: "["senin","selasa",...] " (string di dalam string)
        // Seharusnya:  ["senin","selasa",...] (pure JSON array)
        $rows = DB::table('setting_jam_kerja')->get();
        foreach ($rows as $row) {
            if (!$row->hari_kerja) continue;

            $val = $row->hari_kerja;

            // Decode pertama: lepas outer string
            $decoded = json_decode($val, true);

            // Jika hasil decode masih string (double-encoded), decode lagi
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            // Jika sekarang sudah array, simpan ulang sebagai clean JSON
            if (is_array($decoded)) {
                DB::table('setting_jam_kerja')
                    ->where('id', $row->id)
                    ->update(['hari_kerja' => json_encode($decoded)]);
            }
        }
    }

    public function down(): void
    {
        DB::table('setting_jam_kerja')->where('id', 1)->update([
            'is_default' => false,
        ]);
    }
};
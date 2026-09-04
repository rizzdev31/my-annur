<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sinkron kode variabel RamahAnak (Smart Habbit) tiap jam.
Schedule::job(new \App\Jobs\SyncVariabelJob)->hourly();

// Auto-Alfa Absensi Harian tiap 15 menit: tandai tendik aktif yang tidak absen
// sampai shift kerjanya berakhir (overnight-aware). Idempotent + lewati libur.
Schedule::command('absensi:auto-alfa')->everyFifteenMinutes()->withoutOverlapping();

// Pengingat & eskalasi notifikasi wajib (absensi + mengajar) tiap 15 menit.
Schedule::command('notifikasi:reminder')->everyFifteenMinutes()->withoutOverlapping();

// Sinkron berita CMS ke cache tiap jam (agar Beranda PWA instan, tak fetch eksternal saat request).
Schedule::command('berita:sync')->hourly()->withoutOverlapping()->runInBackground();

// Auto-selesai peminjaman inventaris yang jam pemakaiannya sudah lewat (tiap 15 menit).
Schedule::command('inventaris:auto-selesai')->everyFifteenMinutes()->withoutOverlapping();

// Auto-Alpha Smart Controlling tiap menit: begitu window + toleransi tutup
// (mis. subuh 05:00–05:15 toleransi 3' → tutup 05:18), yang belum scan langsung
// ditandai ALPHA (≤1 menit setelah tutup). Idempotent + lewati hari libur.
Schedule::command('controlling:auto-alpha')->everyMinute()->withoutOverlapping();

// Auto-isi absensi mengajar (reguler/tahfidz/tahsin) jadi 'libur' tiap hari libur.
// Dini hari agar siap sebelum jam mengajar; tidak bergantung guru membuka aplikasi.
Schedule::command('mengajar:isi-libur')->dailyAt('00:30');

// Eskalasi anomali ke pengawas/pimpinan. Tiap jam pada jam kerja; aman diulang
// karena tiap jenis di-dedup per hari (satu ringkasan per jenis per pengawas).
Schedule::command('eskalasi:pimpinan')->hourlyAt(5)->between('07:00', '21:00')
    ->withoutOverlapping()->runInBackground();

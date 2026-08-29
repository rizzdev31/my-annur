@echo off
REM ============================================================
REM  Scheduler An-Nur Smart System (lokal / development)
REM  Menjalankan tugas terjadwal, termasuk:
REM    - controlling:auto-alpha  (tiap menit: alfa otomatis saat window tutup)
REM    - sync kode variabel RamahAnak (tiap jam)
REM    - mengajar:isi-libur      (00:30 tiap hari)
REM  Biarkan jendela ini TERBUKA selama development.
REM  Tutup dengan Ctrl+C.
REM ============================================================
cd /d "%~dp0"
echo.
echo  Menjalankan Laravel scheduler (schedule:work)...
echo  Jangan tutup jendela ini selama pakai aplikasi.
echo.
php artisan schedule:work
pause

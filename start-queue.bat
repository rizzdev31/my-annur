@echo off
REM ============================================================
REM  Worker antrian An-Nur Smart System (lokal / development)
REM  Memproses outbox RamahAnak (Smart Eksekusi) + job lainnya.
REM  Biarkan jendela ini TERBUKA selama development.
REM  Tutup dengan Ctrl+C.
REM ============================================================
cd /d "%~dp0"
echo.
echo  Menjalankan queue worker (database)...
echo  Jangan tutup jendela ini selama pakai aplikasi.
echo.
php artisan queue:work --tries=5 --backoff=10 --timeout=60
pause

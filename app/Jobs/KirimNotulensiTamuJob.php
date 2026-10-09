<?php

namespace App\Jobs;

use App\Mail\NotulensiKegiatan;
use App\Models\Tamu;
use App\Services\TimezoneHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Kirim notulensi ke SATU tamu.
 *
 * Satu job satu tamu, bukan satu job untuk seluruh daftar: kalau satu alamat
 * ditolak server penerima, 49 tamu lain tidak boleh ikut gagal, dan kirim ulang
 * harus bisa menyasar yang gagal saja. Status ditulis per baris `tamu` supaya
 * superadmin melihat persis siapa yang belum menerima.
 */
class KirimNotulensiTamuJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** Jeda coba ulang panjang: penolakan SMTP biasanya karena batas laju. */
    public array $backoff = [60, 300];

    public function __construct(public int $tamuId) {}

    public function handle(): void
    {
        $tamu = Tamu::with('kegiatan')->find($this->tamuId);
        if (!$tamu || !$tamu->kegiatan) return;

        // Sudah terkirim (mis. job kembar dari klik ganda) → jangan kirim dua kali.
        if ($tamu->email_status === 'terkirim') return;

        if (blank($tamu->kegiatan->notulensi)) {
            $tamu->update([
                'email_status' => 'gagal',
                'email_error'  => 'Notulensi kegiatan kosong saat pengiriman dijalankan.',
            ]);
            return;
        }

        try {
            Mail::to($tamu->email)->send(new NotulensiKegiatan($tamu->kegiatan, $tamu));

            $tamu->update([
                'email_status'        => 'terkirim',
                'email_terkirim_pada' => TimezoneHelper::now(),
                'email_error'         => null,
            ]);
        } catch (\Throwable $e) {
            // Catat dulu, lalu lempar ulang supaya queue mencoba lagi. Pada
            // percobaan terakhir, `failed()` yang menyegel statusnya.
            $tamu->update([
                'email_status' => 'menunggu',
                'email_error'  => Str::limit($e->getMessage(), 240, ''),
            ]);

            throw $e;
        }
    }

    /** Percobaan habis — status harus berhenti di "gagal", bukan "menunggu" selamanya. */
    public function failed(?\Throwable $e): void
    {
        Tamu::whereKey($this->tamuId)->where('email_status', '!=', 'terkirim')->update([
            'email_status' => 'gagal',
            'email_error'  => Str::limit($e?->getMessage() ?: 'Pengiriman gagal.', 240, ''),
        ]);
    }
}

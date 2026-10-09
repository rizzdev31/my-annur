<?php

namespace App\Jobs;

use App\Mail\KonfirmasiTamu;
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
 * Email konfirmasi sesaat setelah tamu mengisi buku tamu.
 *
 * Dua percobaan saja dan tanpa menandai "gagal" di status notulensi: ini email
 * sopan-santun sekaligus pemeriksa alamat, bukan kiriman wajib. Kalau gagal,
 * yang penting pesan kesalahannya terekam supaya petugas tahu alamat itu
 * bermasalah SEBELUM notulensi dikirim — bukan membuat buku tamu ikut gagal.
 */
class KirimKonfirmasiTamuJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public array $backoff = [60];

    public function __construct(public int $tamuId) {}

    public function handle(): void
    {
        $tamu = Tamu::with('kegiatan')->find($this->tamuId);
        if (!$tamu || !$tamu->kegiatan) return;
        if ($tamu->konfirmasi_terkirim_pada) return;

        try {
            Mail::to($tamu->email)->send(new KonfirmasiTamu($tamu->kegiatan, $tamu));

            $tamu->update([
                'konfirmasi_terkirim_pada' => TimezoneHelper::now(),
                'konfirmasi_error'         => null,
            ]);
        } catch (\Throwable $e) {
            $tamu->update(['konfirmasi_error' => Str::limit($e->getMessage(), 240, '')]);
            throw $e;
        }
    }

    public function failed(?\Throwable $e): void
    {
        Tamu::whereKey($this->tamuId)->whereNull('konfirmasi_terkirim_pada')->update([
            'konfirmasi_error' => Str::limit($e?->getMessage() ?: 'Konfirmasi gagal terkirim.', 240, ''),
        ]);
    }
}

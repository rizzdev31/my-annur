<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Klien Fonnte (WhatsApp gateway). Token device dari config('fonnte.token').
 * Dipakai oleh KirimWaJob. Tidak menyimpan state.
 */
class FonnteClient
{
    /** Kirim 1 pesan WA (opsional dengan gambar/URL). Lempar bila jaringan gagal (ditangani job). */
    public function kirim(string $tujuan, string $pesan, ?string $mediaUrl = null): Response
    {
        $payload = ['target' => $tujuan, 'message' => $pesan];
        if ($mediaUrl) $payload['url'] = $mediaUrl; // Fonnte kirim gambar via 'url'

        return Http::asForm()
            ->withHeaders(['Authorization' => (string) config('fonnte.token')])
            ->timeout(25)
            ->post((string) config('fonnte.url'), $payload);
    }

    /**
     * Normalisasi nomor ke format internasional tanpa '+' (mis. 6281234...).
     * 08xxx → 62xxx; +62/62 dipertahankan; karakter non-digit dibuang.
     */
    public static function normalisasiNomor(?string $nomor): ?string
    {
        if ($nomor === null) return null;
        $n = preg_replace('/[^0-9]/', '', $nomor);
        if ($n === '' || $n === null) return null;
        $cc = (string) config('fonnte.country', '62');
        if (str_starts_with($n, '0')) {
            $n = $cc . substr($n, 1);
        } elseif (!str_starts_with($n, $cc)) {
            // nomor tanpa 0 & tanpa kode negara → prepend (mis. 81234 → 6281234)
            $n = $cc . $n;
        }
        return $n;
    }
}

<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Pengiriman Web Push ke perangkat guru (PWA).
 *
 * Dipakai lewat NotifikasiService (kanal 'push' per event), bukan dipanggil
 * langsung dari controller — agar aktif/nonaktif tetap dikendalikan katalog
 * `setting_notifikasi`.
 *
 * Catatan penting soal perangkat:
 *  - Android Chrome: jalan walau PWA hanya dibuka di tab.
 *  - iPhone/iPad: HANYA jalan pada iOS 16.4+ DAN setelah PWA di-"Add to Home
 *    Screen". Dibuka dari tab Safari tidak akan pernah menerima push.
 *  - Push tidak mengantre lama untuk perangkat mati; ini pengingat, bukan
 *    pengganti lonceng in-app (yang tetap jadi sumber kebenaran).
 */
class WebPushService
{
    /** Apakah VAPID sudah dikonfigurasi? Tanpa ini push dilewati diam-diam. */
    public static function siap(): bool
    {
        return (bool) (config('webpush.public_key') && config('webpush.private_key'));
    }

    public static function publicKey(): ?string
    {
        return config('webpush.public_key');
    }

    /**
     * Kirim satu notifikasi ke SEMUA perangkat milik daftar user.
     *
     * @param  array<int>  $userIds
     * @return int  jumlah perangkat yang berhasil dikirimi
     */
    public function kirim(array $userIds, string $judul, string $pesan, array $data = []): int
    {
        if (!self::siap() || empty($userIds)) return 0;

        $langganan = PushSubscription::whereIn('user_id', $userIds)->get();
        if ($langganan->isEmpty()) return 0;

        try {
            $webPush = new WebPush(['VAPID' => [
                'subject'    => config('webpush.subject'),
                'publicKey'  => config('webpush.public_key'),
                'privateKey' => config('webpush.private_key'),
            ]]);
            // Jangan menahan proses terlalu lama bila endpoint push lambat.
            $webPush->setReuseVAPIDHeaders(true);
        } catch (\Throwable $e) {
            Log::warning('WebPush gagal inisialisasi: ' . $e->getMessage());
            return 0;
        }

        $payload = json_encode([
            'judul' => $judul,
            'pesan' => $pesan,
            'route' => $data['route'] ?? '/notifikasi',
            'tag'   => $data['tag']   ?? null,
        ], JSON_UNESCAPED_UNICODE);

        foreach ($langganan as $s) {
            try {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint'  => $s->endpoint,
                        'publicKey' => $s->p256dh,
                        'authToken' => $s->auth,
                    ]),
                    $payload
                );
            } catch (\Throwable $e) {
                Log::warning("WebPush antre gagal (sub {$s->id}): " . $e->getMessage());
            }
        }

        $berhasil = 0;
        $matikan  = [];

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();

            if ($report->isSuccess()) {
                $berhasil++;
                continue;
            }

            // 404/410 = langganan sudah tidak berlaku (izin dicabut, aplikasi
            // dihapus, browser membuang endpoint). WAJIB dibuang, kalau tidak
            // tabel menumpuk sampah dan tiap kiriman memboroskan waktu.
            if ($report->isSubscriptionExpired()) {
                $matikan[] = PushSubscription::hash($endpoint);
            } else {
                Log::info('WebPush gagal: ' . $report->getReason());
            }
        }

        if ($matikan) {
            PushSubscription::whereIn('endpoint_hash', $matikan)->delete();
        }

        if ($berhasil) {
            PushSubscription::whereIn('user_id', $userIds)
                ->update(['terakhir_dipakai' => now()]);
        }

        return $berhasil;
    }
}

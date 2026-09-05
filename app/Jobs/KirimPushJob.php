<?php

namespace App\Jobs;

use App\Services\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Kirim notifikasi push ke perangkat sejumlah user, di luar siklus request.
 *
 * Sengaja TIDAK mengulang selamanya: notifikasi ini terikat waktu (pengingat
 * absen/mengajar), jadi kiriman yang gagal berkali-kali lebih baik gugur
 * daripada tiba jauh setelah momennya lewat. Lonceng in-app tetap ada sebagai
 * cadangan.
 */
class KirimPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(
        public array $userIds,
        public string $judul,
        public string $pesan,
        public array $data = [],
    ) {}

    public function handle(WebPushService $push): void
    {
        $push->kirim($this->userIds, $this->judul, $this->pesan, $this->data);
    }
}

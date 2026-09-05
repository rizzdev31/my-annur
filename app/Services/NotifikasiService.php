<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\SettingNotifikasi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Gerbang tunggal notifikasi. Dua cara pakai:
 *  1) kirim()/keSuperadmin() — kirim langsung ke user (dipakai kode lama).
 *  2) event() — berbasis katalog `setting_notifikasi`: cek aktif/penerima/kanal/
 *     kuota, lalu dispatch. Kanal in-app aktif; WA/push ditunda (stub).
 *
 * Lihat docs/PRD-Notifikasi.md.
 */
class NotifikasiService
{
    /** Kirim ke satu user (model atau id). */
    public static function kirim(
        User|int|null $user, string $judul, string $pesan,
        string $tipe = 'pengumuman', array $data = [],
        ?string $eventKode = null, string $prioritas = 'normal'
    ): ?Notifikasi {
        $userId = $user instanceof User ? $user->id : $user;
        if (!$userId) return null;

        return Notifikasi::create([
            'user_id'    => $userId,
            'judul'      => $judul,
            'pesan'      => $pesan,
            'tipe'       => $tipe,
            'event_kode' => $eventKode,
            'prioritas'  => $prioritas,
            'data'       => $data ?: null,
        ]);
    }

    /** Broadcast ke seluruh super admin. */
    public static function keSuperadmin(string $judul, string $pesan, string $tipe = 'pengumuman', array $data = []): void
    {
        User::where('role', 'super_admin')->pluck('id')->each(
            fn ($id) => self::kirim($id, $judul, $pesan, $tipe, $data)
        );
    }

    /**
     * Kirim berbasis EVENT katalog.
     *
     * @param string $kode  kode event (mis. 'mengajar.reminder')
     * @param array  $konteks {
     *     judul, pesan, data(array),
     *     user (User|int subjek 'guru'), penguji (User|int),
     *     dedup (string, cegah duplikat harian utk event ini),
     *     prioritas, tipe
     * }
     * @param array $penerimaExtra  User|int tambahan (mengalahkan katalog)
     * @return int jumlah notifikasi terkirim (in-app)
     */
    public static function event(string $kode, array $konteks = [], array $penerimaExtra = []): int
    {
        $cfg = SettingNotifikasi::untuk($kode);

        // Nonaktif & tidak wajib → jangan kirim.
        if ($cfg && !$cfg->aktif && !$cfg->wajib) return 0;

        $judul = $konteks['judul'] ?? ($cfg->nama ?? 'Notifikasi');
        $pesan = $konteks['pesan'] ?? ($cfg->deskripsi ?? '');
        $tipe  = $konteks['tipe']  ?? 'pengumuman';
        $data  = $konteks['data']  ?? [];
        $prio  = $konteks['prioritas'] ?? ($cfg && $cfg->wajib ? 'tinggi' : 'normal');

        // Resolusi penerima (id unik).
        $ids = self::resolvePenerima($cfg?->penerima ?? ['guru'], $konteks);
        foreach ($penerimaExtra as $p) {
            $ids[] = $p instanceof User ? $p->id : (int) $p;
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) return 0;

        // Kanal: in-app (lonceng) + push (notifikasi HP). WA masih ditunda.
        $inApp = !$cfg || $cfg->kanalAktif('in_app');
        $push  = $cfg && $cfg->kanalAktif('push');

        $dedup   = $konteks['dedup'] ?? null;
        $maksHari = $cfg?->maks_per_hari;
        $today   = now()->toDateString();
        $terkirim = 0;
        $idPush   = [];   // user yang lolos dedup/kuota → berhak dapat push

        foreach ($ids as $uid) {
            // Dedup: sudah pernah kirim event+dedup ke user ini hari ini?
            if ($dedup) {
                $ada = Notifikasi::where('user_id', $uid)
                    ->where('event_kode', $kode)
                    ->where('data->dedup', $dedup)
                    ->exists();
                if ($ada) continue;
            }
            // Kuota per hari per user per event.
            if ($maksHari) {
                $jml = Notifikasi::where('user_id', $uid)
                    ->where('event_kode', $kode)
                    ->whereDate('created_at', $today)
                    ->count();
                if ($jml >= $maksHari) continue;
            }

            if ($inApp) {
                $payload = $data;
                if ($dedup) $payload['dedup'] = $dedup;
                self::kirim($uid, $judul, $pesan, $tipe, $payload, $kode, $prio);
                $terkirim++;
            }

            // Push mengikuti dedup & kuota yang sama — supaya HP tidak berbunyi
            // untuk hal yang lonceng in-app sendiri sudah menolaknya.
            if ($push) $idPush[] = $uid;
            // TODO fase 3: kanal WA (WaService) — dicek dari $cfg->kanal.
        }

        // Dikerjakan di belakang layar: HTTP ke layanan push (FCM/Apple/Mozilla)
        // makan ratusan milidetik per perangkat — jangan sampai menahan request
        // guru yang sedang absen.
        if ($push && $idPush) {
            \App\Jobs\KirimPushJob::dispatch(
                array_values(array_unique($idPush)), $judul, $pesan,
                ['route' => $data['route'] ?? '/notifikasi', 'tag' => $kode]
            )->afterResponse();
        }

        return $terkirim;
    }

    /** Petakan token penerima ('guru','admin','pimpinan','penguji') → id user. */
    private static function resolvePenerima(array $tokens, array $konteks): array
    {
        $ids = [];
        foreach ($tokens as $t) {
            switch ($t) {
                case 'guru':
                    if (!empty($konteks['user'])) {
                        $u = $konteks['user'];
                        $ids[] = $u instanceof User ? $u->id : (int) $u;
                    }
                    break;
                case 'penguji':
                    if (!empty($konteks['penguji'])) {
                        $u = $konteks['penguji'];
                        $ids[] = $u instanceof User ? $u->id : (int) $u;
                    }
                    break;
                case 'admin':
                    $ids = array_merge($ids, User::where('role', 'super_admin')->pluck('id')->all());
                    break;
                case 'pimpinan':
                    $pimpinan = User::whereIn('id', function ($q) {
                        $q->select('user_id')->from('user_peran')
                          ->join('peran', 'peran.id', '=', 'user_peran.peran_id')
                          ->where('peran.kode', 'pimpinan');
                    })->pluck('id')->all();
                    $ids = array_merge($ids, $pimpinan);
                    break;
            }
        }
        return $ids;
    }
}

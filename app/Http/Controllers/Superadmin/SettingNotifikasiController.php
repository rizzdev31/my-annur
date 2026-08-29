<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Jabatan;
use App\Models\SettingNotifikasi;
use App\Models\TenagaPendidik;
use App\Services\NotifikasiService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingNotifikasiController extends Controller
{
    public function index()
    {
        $grup = SettingNotifikasi::orderBy('kategori')->orderBy('nama')->get()
            ->map(fn ($s) => [
                'id'            => $s->id,
                'event_kode'    => $s->event_kode,
                'nama'          => $s->nama,
                'kategori'      => $s->kategori,
                'deskripsi'     => $s->deskripsi,
                'wajib'         => $s->wajib,
                'aktif'         => $s->aktif,
                'penerima'      => $s->penerima ?? [],
                'kanal'         => array_merge(['in_app' => true, 'wa' => false, 'push' => false], $s->kanal ?? []),
                'reminder'      => $s->reminder,
                'eskalasi'      => $s->eskalasi,
                'maks_per_hari' => $s->maks_per_hari,
            ])
            ->groupBy('kategori');

        return Inertia::render('Admin/SmartPayroll/SettingNotifikasi/Index', [
            'grup'    => $grup,
            'jabatan' => Jabatan::where('is_aktif', true)->get(['id', 'nama_jabatan'])
                ->map(fn ($j) => ['id' => $j->id, 'nama' => $j->nama_jabatan]),
            'guru'    => TenagaPendidik::where('is_aktif', true)->with(['user', 'jabatan'])->get()
                ->filter(fn ($g) => $g->user)
                ->map(fn ($g) => [
                    'id'      => $g->id,
                    'nama'    => $g->user->name,
                    'jabatan' => $g->jabatan?->nama_jabatan ?? '—',
                ])->values(),
        ]);
    }

    public function update(Request $request, SettingNotifikasi $setting)
    {
        $data = $request->validate([
            'aktif'                   => 'boolean',
            'kanal'                   => 'nullable|array',
            'kanal.in_app'            => 'boolean',
            'kanal.wa'                => 'boolean',
            'kanal.push'              => 'boolean',
            'reminder'                => 'nullable|array',
            'reminder.sebelum_menit'  => 'nullable|integer|min:0|max:240',
            'reminder.ulang_menit'    => 'nullable|integer|min:0|max:240',
            'reminder.batas_menit'    => 'nullable|integer|min:0|max:480',
            'eskalasi'                => 'nullable|array',
            'eskalasi.setelah_menit'  => 'nullable|integer|min:0|max:1440',
            'maks_per_hari'           => 'nullable|integer|min:0|max:50',
        ]);

        // Event WAJIB tidak boleh dinonaktifkan (hal krusial harus tetap jalan).
        if ($setting->wajib) {
            $data['aktif'] = true;
        }

        $setting->update($data);

        return back()->with('success', "Notifikasi \"{$setting->nama}\" diperbarui.");
    }

    /** Broadcast pengumuman manual ke lonceng guru (target: semua/jabatan/individu). */
    public function broadcast(Request $request)
    {
        $data = $request->validate([
            'judul'                 => 'required|string|max:150',
            'pesan'                 => 'required|string|max:1000',
            'target'                => 'required|in:semua,jabatan,individu',
            'jabatan_ids'           => 'required_if:target,jabatan|array',
            'jabatan_ids.*'         => 'integer|exists:jabatan,id',
            'tenaga_pendidik_ids'   => 'required_if:target,individu|array',
            'tenaga_pendidik_ids.*' => 'integer|exists:tenaga_pendidik,id',
            'link'                  => 'nullable|string|max:200',
        ]);

        $q = TenagaPendidik::where('is_aktif', true)->with('user');
        if ($data['target'] === 'jabatan') {
            $ids = $data['jabatan_ids'] ?? [];
            $q->where(function ($w) use ($ids) {
                $w->whereIn('jabatan_id', $ids)
                  ->orWhereHas('jabatanGuru', fn ($jg) => $jg->whereIn('jabatan_id', $ids)->whereNull('berlaku_selesai'));
            });
        } elseif ($data['target'] === 'individu') {
            $q->whereIn('id', $data['tenaga_pendidik_ids'] ?? []);
        }

        $userIds = $q->get()->pluck('user.id')->filter()->unique()->values();
        if ($userIds->isEmpty()) {
            return back()->with('error', 'Tidak ada penerima yang cocok.');
        }

        // Manual & eksplisit → kirim langsung (tidak digating toggle), tandai event pengumuman.umum.
        $payload = ['type' => 'pengumuman'];
        if (!empty($data['link'])) $payload['route'] = $data['link'];
        foreach ($userIds as $uid) {
            NotifikasiService::kirim($uid, $data['judul'], $data['pesan'], 'pengumuman', $payload, 'pengumuman.umum', 'normal');
        }

        return back()->with('success', "Pengumuman terkirim ke {$userIds->count()} guru.");
    }
}

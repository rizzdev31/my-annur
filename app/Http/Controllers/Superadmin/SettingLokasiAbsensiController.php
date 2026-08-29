<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\SettingLokasiAbsensi;
use App\Models\TenagaPendidik;
use App\Services\LokasiAbsensiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SettingLokasiAbsensiController extends Controller
{
    public function __construct(
        private readonly LokasiAbsensiService $lokasiService
    ) {}

    // ══════════════════════════════════════════════════════════════════════════
    // INDEX — daftar semua setting + assign per-user
    // ══════════════════════════════════════════════════════════════════════════

    public function index()
    {
        $setting = SettingLokasiAbsensi::with(['dibuatOleh', 'tenagaPendidik.user'])
            ->orderByRaw("FIELD(lingkup, 'global', 'per_user')")
            ->orderBy('tipe')
            ->orderBy('nama')
            ->get()
            ->map(fn($s) => [
                'id'                  => $s->id,
                'nama'                => $s->nama,
                'tipe'                => $s->tipe,
                'tipe_label'          => $s->tipe_label,
                'lingkup'             => $s->lingkup,
                'lingkup_label'       => $s->lingkup_label,
                'latitude'            => $s->latitude,
                'longitude'           => $s->longitude,
                'radius_meter'        => $s->radius_meter,
                'wifi_ssid'           => $s->wifi_ssid,
                'wifi_bssid'          => $s->wifi_bssid,
                'is_aktif'            => $s->is_aktif,
                'izinkan_dinas_luar'  => $s->izinkan_dinas_luar,
                'izinkan_izin_aktif'  => $s->izinkan_izin_aktif,
                'keterangan'          => $s->keterangan,
                'dibuat_oleh'         => $s->dibuatOleh?->name,
                // Guru yang di-assign (hanya per_user)
                'guru_assigned' => $s->lingkup === 'per_user'
                    ? $s->tenagaPendidik->map(fn($g) => [
                        'id'               => $g->id,
                        'nama'             => $g->user->name,
                        'jabatan'          => $g->jabatan?->nama_jabatan ?? '—',
                        'konteks'          => $g->pivot->konteks,
                        'berlaku_mulai'    => $g->pivot->berlaku_mulai,
                        'berlaku_selesai'  => $g->pivot->berlaku_selesai,
                        'keterangan_pivot' => $g->pivot->keterangan,
                    ])->values()
                    : collect(),
            ]);

        $summary = [
            'total'     => $setting->count(),
            'global'    => $setting->where('lingkup', 'global')->count(),
            'per_user'  => $setting->where('lingkup', 'per_user')->count(),
            'koordinat' => $setting->where('tipe', 'koordinat')->count(),
            'wifi'      => $setting->where('tipe', 'wifi')->count(),
            'aktif'     => $setting->where('is_aktif', true)->count(),
        ];

        $guru = TenagaPendidik::aktif()
            ->with(['user', 'jabatan', 'lokasiAbsensi'])
            ->get()
            ->map(fn($g) => [
                'id'              => $g->id,
                'nama'            => $g->user->name,
                'jabatan'         => $g->jabatan?->nama_jabatan ?? '—',
                'nip'             => $g->nip,
                'lokasi_count'    => $g->lokasiAbsensi->count(),
                'lokasi_assigned' => $g->lokasiAbsensi->map(fn($l) => [
                    'id'              => $l->id,
                    'nama'            => $l->nama,
                    'tipe'            => $l->tipe,
                    'konteks'         => $l->pivot->konteks,
                    'berlaku_mulai'   => $l->pivot->berlaku_mulai,
                    'berlaku_selesai' => $l->pivot->berlaku_selesai,
                ])->values(),
            ]);

        return Inertia::render('Admin/SmartPayroll/SettingLokasi/Index', [
            'setting' => $setting,
            'summary' => $summary,
            'guru'    => $guru,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // STORE
    // ══════════════════════════════════════════════════════════════════════════

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'                => 'required|string|max:100',
            'tipe'                => 'required|in:koordinat,wifi',
            'lingkup'             => 'required|in:global,per_user',
            'latitude'            => 'required_if:tipe,koordinat|nullable|numeric|between:-90,90',
            'longitude'           => 'required_if:tipe,koordinat|nullable|numeric|between:-180,180',
            'radius_meter'        => 'required_if:tipe,koordinat|nullable|integer|min:10|max:5000',
            'wifi_ssid'           => 'required_if:tipe,wifi|nullable|string|max:100',
            'wifi_bssid'          => 'nullable|string|max:17',
            'izinkan_dinas_luar'  => 'boolean',
            'izinkan_izin_aktif'  => 'boolean',
            'keterangan'          => 'nullable|string|max:300',
        ]);

        SettingLokasiAbsensi::create(array_merge($data, [
            'is_aktif'    => true,
            'dibuat_oleh' => auth()->id(),
        ]));

        return back()->with('success', "Setting lokasi \"{$data['nama']}\" ditambahkan.");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // UPDATE
    // ══════════════════════════════════════════════════════════════════════════

    public function update(Request $request, SettingLokasiAbsensi $settingLokasi)
    {
        $data = $request->validate([
            'nama'               => 'required|string|max:100',
            'lingkup'            => 'required|in:global,per_user',
            'latitude'           => 'nullable|numeric|between:-90,90',
            'longitude'          => 'nullable|numeric|between:-180,180',
            'radius_meter'       => 'nullable|integer|min:10|max:5000',
            'wifi_ssid'          => 'nullable|string|max:100',
            'wifi_bssid'         => 'nullable|string|max:17',
            'izinkan_dinas_luar' => 'boolean',
            'izinkan_izin_aktif' => 'boolean',
            'keterangan'         => 'nullable|string|max:300',
        ]);

        $settingLokasi->update($data);

        // Jika diubah ke global, lepas semua assign user
        if ($data['lingkup'] === 'global') {
            $settingLokasi->tenagaPendidik()->detach();
        }

        return back()->with('success', 'Setting lokasi diperbarui.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ASSIGN GURU ke lokasi per_user
    // ══════════════════════════════════════════════════════════════════════════

    public function assignGuru(Request $request, SettingLokasiAbsensi $settingLokasi)
    {
        if ($settingLokasi->lingkup !== 'per_user') {
            return back()->with('error', 'Hanya lokasi per-user yang bisa di-assign ke guru.');
        }

        $data = $request->validate([
            'tenaga_pendidik_id'  => 'required|exists:tenaga_pendidik,id',
            'konteks'             => 'required|in:default,jadwal,sementara',
            'berlaku_mulai'       => 'nullable|date',
            'berlaku_selesai'     => 'nullable|date|after_or_equal:berlaku_mulai',
            'keterangan'          => 'nullable|string|max:200',
        ]);

        // Cek duplikat
        $existing = $settingLokasi->tenagaPendidik()
            ->wherePivot('tenaga_pendidik_id', $data['tenaga_pendidik_id'])
            ->exists();

        if ($existing) {
            // Update pivot yang sudah ada
            $settingLokasi->tenagaPendidik()->updateExistingPivot(
                $data['tenaga_pendidik_id'],
                [
                    'konteks'           => $data['konteks'],
                    'berlaku_mulai'     => $data['berlaku_mulai'] ?? null,
                    'berlaku_selesai'   => $data['berlaku_selesai'] ?? null,
                    'keterangan'        => $data['keterangan'] ?? null,
                    'ditambahkan_oleh'  => auth()->id(),
                ]
            );
            return back()->with('success', 'Assign guru diperbarui.');
        }

        $settingLokasi->tenagaPendidik()->attach($data['tenaga_pendidik_id'], [
            'konteks'          => $data['konteks'],
            'berlaku_mulai'    => $data['berlaku_mulai'] ?? null,
            'berlaku_selesai'  => $data['berlaku_selesai'] ?? null,
            'keterangan'       => $data['keterangan'] ?? null,
            'ditambahkan_oleh' => auth()->id(),
        ]);

        $nama = TenagaPendidik::find($data['tenaga_pendidik_id'])?->user?->name ?? 'Guru';
        return back()->with('success', "{$nama} berhasil di-assign ke {$settingLokasi->nama}.");
    }

    /**
     * Lepas guru dari lokasi.
     */
    public function unassignGuru(Request $request, SettingLokasiAbsensi $settingLokasi)
    {
        $request->validate([
            'tenaga_pendidik_id' => 'required|exists:tenaga_pendidik,id',
        ]);

        $settingLokasi->tenagaPendidik()->detach($request->tenaga_pendidik_id);

        return back()->with('success', 'Guru dilepas dari lokasi ini.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ASSIGN dari sisi GURU — set semua lokasi untuk guru tertentu
    // ══════════════════════════════════════════════════════════════════════════

    public function assignLokasiToGuru(Request $request, TenagaPendidik $guru)
    {
        $data = $request->validate([
            'lokasi'                  => 'required|array',
            'lokasi.*.setting_lokasi_id' => 'required|exists:setting_lokasi_absensi,id',
            'lokasi.*.konteks'           => 'required|in:default,jadwal,sementara',
            'lokasi.*.berlaku_mulai'     => 'nullable|date',
            'lokasi.*.berlaku_selesai'   => 'nullable|date',
            'lokasi.*.keterangan'        => 'nullable|string|max:200',
        ]);

        // Sync: replace semua lokasi per-user yang di-assign
        $syncData = [];
        foreach ($data['lokasi'] as $item) {
            // Pastikan setting yang dipilih adalah per_user
            $setting = SettingLokasiAbsensi::find($item['setting_lokasi_id']);
            if (!$setting || $setting->lingkup !== 'per_user') continue;

            $syncData[$item['setting_lokasi_id']] = [
                'konteks'          => $item['konteks'],
                'berlaku_mulai'    => $item['berlaku_mulai'] ?? null,
                'berlaku_selesai'  => $item['berlaku_selesai'] ?? null,
                'keterangan'       => $item['keterangan'] ?? null,
                'ditambahkan_oleh' => auth()->id(),
            ];
        }

        $guru->lokasiAbsensi()->sync($syncData);

        return back()->with('success', "Lokasi absensi {$guru->user->name} diperbarui ({$guru->lokasiAbsensi()->count()} lokasi).");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // TOGGLE AKTIF & DESTROY
    // ══════════════════════════════════════════════════════════════════════════

    public function toggleAktif(SettingLokasiAbsensi $settingLokasi)
    {
        $settingLokasi->update(['is_aktif' => !$settingLokasi->is_aktif]);
        $s = $settingLokasi->is_aktif ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "\"{$settingLokasi->nama}\" {$s}.");
    }

    public function destroy(SettingLokasiAbsensi $settingLokasi)
    {
        $settingLokasi->tenagaPendidik()->detach(); // hapus pivot dulu
        $settingLokasi->delete();
        return back()->with('success', 'Setting lokasi dihapus.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // TEST VALIDASI
    // ══════════════════════════════════════════════════════════════════════════

    public function testValidasi(Request $request)
    {
        $request->validate([
            'tenaga_pendidik_id' => 'nullable|exists:tenaga_pendidik,id',
            'latitude'           => 'nullable|numeric',
            'longitude'          => 'nullable|numeric',
            'wifi_ssid'          => 'nullable|string',
            'wifi_bssid'         => 'nullable|string',
            'status'             => 'nullable|string',
        ]);

        $guruId = $request->tenaga_pendidik_id ?? 0;
        $hasil  = $this->lokasiService->validasi($guruId, $request->all());

        return response()->json($hasil);
    }
}
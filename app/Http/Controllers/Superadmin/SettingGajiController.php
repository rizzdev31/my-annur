<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\SettingGajiPokok;
use App\Models\SettingVakasi;
use App\Models\SettingJamKerja;
use App\Models\Jabatan;
use App\Models\TenagaPendidik;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingGajiController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════
    // GAJI POKOK PER JABATAN
    // ══════════════════════════════════════════════════════════════════════

    public function index()
    {
        $settingGaji = SettingGajiPokok::with('jabatan')
            ->aktif()
            ->orderBy('jabatan_id')
            ->get()
            ->map(fn($s) => [
                'id'           => $s->id,
                'jabatan_id'   => $s->jabatan_id,
                'jabatan_nama' => $s->jabatan?->nama_jabatan ?? '—',
                'jabatan_kode' => $s->jabatan?->kode_jabatan ?? '',
                'jabatan_tipe' => $s->jabatan?->tipe ?? 'fungsional',
                'nominal'      => $s->nominal,
                'berlaku_mulai'=> $s->berlaku_mulai?->format('d M Y'),
                'keterangan'   => $s->keterangan,
            ]);

        return Inertia::render('Admin/SmartPayroll/SettingGaji/Pokok/Index', [
            'settingGaji' => $settingGaji,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/SmartPayroll/SettingGaji/Pokok/Form', [
            'jabatan' => Jabatan::aktif()->orderBy('tipe')->orderBy('nama_jabatan')
                ->get(['id', 'nama_jabatan', 'kode_jabatan', 'tipe']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'jabatan_id'    => 'required|exists:jabatan,id',
            'nominal'       => 'required|numeric|min:0',
            'berlaku_mulai' => 'required|date',
            'keterangan'    => 'nullable|string|max:500',
        ]);

        // Nonaktifkan setting lama jabatan yang sama
        SettingGajiPokok::where('jabatan_id', $data['jabatan_id'])
            ->where('is_aktif', true)
            ->update(['is_aktif' => false, 'berlaku_selesai' => now()]);

        SettingGajiPokok::create(array_merge($data, [
            'is_aktif'    => true,
            'dibuat_oleh' => auth()->id(),
        ]));

        return redirect()->route('admin.smart-payroll.setting-gaji.pokok.index')
            ->with('success', 'Setting gaji pokok berhasil disimpan.');
    }

    public function edit(SettingGajiPokok $pokoh)
    {
        return Inertia::render('Admin/SmartPayroll/SettingGaji/Pokok/Form', [
            'setting' => array_merge($pokoh->toArray(), [
                'berlaku_mulai' => $pokoh->berlaku_mulai?->format('Y-m-d'),
                'jabatan'       => $pokoh->jabatan,
            ]),
            'jabatan' => Jabatan::aktif()->orderBy('tipe')->orderBy('nama_jabatan')
                ->get(['id', 'nama_jabatan', 'kode_jabatan', 'tipe']),
        ]);
    }

    public function update(Request $request, SettingGajiPokok $pokoh)
    {
        $data = $request->validate([
            'nominal'       => 'required|numeric|min:0',
            'berlaku_mulai' => 'required|date',
            'keterangan'    => 'nullable|string|max:500',
        ]);

        $pokoh->update($data);

        return redirect()->route('admin.smart-payroll.setting-gaji.pokok.index')
            ->with('success', 'Setting gaji pokok diperbarui.');
    }

    public function destroy(SettingGajiPokok $pokoh)
    {
        $pokoh->update(['is_aktif' => false]);
        return back()->with('success', 'Setting gaji pokok dinonaktifkan.');
    }

    // ══════════════════════════════════════════════════════════════════════
    // VAKASI
    // ══════════════════════════════════════════════════════════════════════

    public function vakasiIndex()
    {
        $jabatan = Jabatan::aktif()->get(['id', 'nama_jabatan', 'kode_jabatan', 'tipe']);

        $vakasi = SettingVakasi::aktif()
            ->orderBy('tipe_aktivitas')
            ->orderBy('nama')
            ->get()
            ->map(fn($v) => [
                'id'                  => $v->id,
                'nama'                => $v->nama,
                'tipe_aktivitas'      => $v->tipe_aktivitas,
                'satuan'              => $v->satuan,
                'nominal'             => $v->nominal,
                'berlaku_untuk_semua' => $v->berlaku_untuk_semua,
                // FIXED: pastikan jabatan_ids selalu array, tidak pernah null atau string
                'jabatan_ids'         => is_array($v->jabatan_ids) ? $v->jabatan_ids : [],
                'berlaku_mulai'       => $v->berlaku_mulai?->format('d M Y'),
                'berlaku_selesai'     => $v->berlaku_selesai?->format('d M Y'),
            ]);

        return Inertia::render('Admin/SmartPayroll/SettingGaji/Vakasi/Index', [
            'vakasi'  => $vakasi,
            'jabatan' => $jabatan,
        ]);
    }

    public function vakasiCreate()
    {
        return Inertia::render('Admin/SmartPayroll/SettingGaji/Vakasi/Form', [
            'jabatan' => Jabatan::aktif()->get(['id', 'nama_jabatan', 'tipe']),
            'guru'    => \App\Models\TenagaPendidik::aktif()->with(['user','jabatan'])
                ->get()->map(fn($g) => [
                    'id'      => $g->id,
                    'nama'    => $g->user->name,
                    'jabatan' => $g->jabatan?->nama_jabatan ?? '—',
                ]),
        ]);
    }

    public function vakasiStore(Request $request)
    {
        $data = $request->validate([
            'nama'                   => 'required|string|max:100',
            'tipe_aktivitas'         => 'required|in:absen_harian,absen_mengajar,tugas_jabatan,tugas_tambahan,lembur,piket,tasmi,tasnif,ekstrakurikuler',
            'satuan'                 => 'required|in:per_hari,per_jp,per_tugas,per_jam,per_bulan,per_pertemuan',
            'nominal'                => 'required|numeric|min:0',
            // Khusus lembur:
            'min_durasi_menit'       => 'required_if:tipe_aktivitas,lembur|nullable|integer|min:1',
            'batas_grace_menit'      => 'nullable|integer|min:0',
            'lingkup'                => 'required|in:semua,per_jabatan,per_individu,custom',
            'berlaku_untuk_semua'    => 'boolean',
            'jabatan_ids'            => 'nullable|array',
            'jabatan_ids.*'          => 'integer|exists:jabatan,id',
            'tenaga_pendidik_ids'    => 'nullable|array',
            'tenaga_pendidik_ids.*'  => 'integer|exists:tenaga_pendidik,id',
            'berlaku_mulai'          => 'required|date',
        ]);

        // Lembur = tarif flat global (jam & penerima ditentukan saat input lembur)
        if ($data['tipe_aktivitas'] === 'lembur') {
            $data['lingkup'] = 'semua';
        }
        // Sinkronkan berlaku_untuk_semua dengan lingkup
        $data['berlaku_untuk_semua'] = ($data['lingkup'] === 'semua');

        // Cegah 1 guru masuk >1 kategori vakasi untuk aktivitas yang sama
        if (in_array($data['lingkup'], ['per_individu', 'custom'])) {
            $this->guardBentrokVakasiIndividu($data['tipe_aktivitas'], $data['tenaga_pendidik_ids'] ?? []);
        }

        SettingVakasi::create(array_merge($data, [
            'is_aktif'    => true,
            'dibuat_oleh' => auth()->id(),
        ]));

        return redirect()->route('admin.smart-payroll.setting-gaji.vakasi.index')
            ->with('success', 'Setting vakasi berhasil disimpan.');
    }

    public function vakasiEdit(SettingVakasi $vakasi)
    {
        return Inertia::render('Admin/SmartPayroll/SettingGaji/Vakasi/Form', [
            'vakasi'  => array_merge($vakasi->toArray(), [
                'berlaku_mulai'       => $vakasi->berlaku_mulai?->format('Y-m-d'),
                'jabatan_ids'         => is_array($vakasi->jabatan_ids) ? $vakasi->jabatan_ids : [],
                'tenaga_pendidik_ids' => is_array($vakasi->tenaga_pendidik_ids) ? $vakasi->tenaga_pendidik_ids : [],
                'lingkup'             => $vakasi->lingkup ?? 'semua',
            ]),
            'jabatan' => Jabatan::aktif()->get(['id', 'nama_jabatan', 'tipe']),
            'guru'    => \App\Models\TenagaPendidik::aktif()->with(['user','jabatan'])
                ->get()->map(fn($g) => [
                    'id'      => $g->id,
                    'nama'    => $g->user->name,
                    'jabatan' => $g->jabatan?->nama_jabatan ?? '—',
                ]),
        ]);
    }

    public function vakasiUpdate(Request $request, SettingVakasi $vakasi)
    {
        $data = $request->validate([
            'nama'                   => 'required|string|max:100',
            'satuan'                 => 'required|in:per_hari,per_jp,per_tugas,per_jam,per_bulan,per_pertemuan',
            'nominal'                => 'required|numeric|min:0',
            'min_durasi_menit'       => 'nullable|integer|min:1',
            'batas_grace_menit'      => 'nullable|integer|min:0',
            'lingkup'                => 'required|in:semua,per_jabatan,per_individu,custom',
            'berlaku_untuk_semua'    => 'boolean',
            'jabatan_ids'            => 'nullable|array',
            'jabatan_ids.*'          => 'integer|exists:jabatan,id',
            'tenaga_pendidik_ids'    => 'nullable|array',
            'tenaga_pendidik_ids.*'  => 'integer|exists:tenaga_pendidik,id',
            'berlaku_mulai'          => 'required|date',
        ]);

        $data['berlaku_untuk_semua'] = ($data['lingkup'] === 'semua');

        // Cegah 1 guru masuk >1 kategori vakasi untuk aktivitas yang sama (kecuali record ini sendiri)
        if (in_array($data['lingkup'], ['per_individu', 'custom'])) {
            $this->guardBentrokVakasiIndividu($vakasi->tipe_aktivitas, $data['tenaga_pendidik_ids'] ?? [], $vakasi->id);
        }

        $vakasi->update($data);

        return redirect()->route('admin.smart-payroll.setting-gaji.vakasi.index')
            ->with('success', 'Setting vakasi diperbarui.');
    }

    public function vakasiDestroy(SettingVakasi $vakasi)
    {
        $vakasi->update(['is_aktif' => false]);
        return back()->with('success', 'Setting vakasi dinonaktifkan.');
    }

    /**
     * Cegah seorang guru terdaftar di lebih dari satu kategori vakasi (per-individu)
     * untuk tipe aktivitas yang sama — agar tarif yang berlaku selalu tidak ambigu.
     * Berlaku umum, penting untuk kategori "vakasi absen harian" per jarak/kebutuhan.
     */
    private function guardBentrokVakasiIndividu(string $tipe, array $guruIds, ?int $exceptId = null): void
    {
        $guruIds = array_values(array_filter(array_map('intval', $guruIds)));
        if (empty($guruIds)) return;

        $lain = SettingVakasi::where('tipe_aktivitas', $tipe)
            ->where('is_aktif', true)
            ->whereIn('lingkup', ['per_individu', 'custom'])
            ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
            ->get();

        $bentrok = []; // guru_id => nama kategori
        foreach ($lain as $s) {
            $ids = is_array($s->tenaga_pendidik_ids) ? $s->tenaga_pendidik_ids : [];
            foreach (array_intersect($guruIds, array_map('intval', $ids)) as $gid) {
                $bentrok[$gid] = $s->nama;
            }
        }

        if (!empty($bentrok)) {
            $daftar = \App\Models\TenagaPendidik::with('user')
                ->whereIn('id', array_keys($bentrok))->get()
                ->map(fn($g) => ($g->user?->name ?? 'Guru #'.$g->id) . ' (sudah di kategori "' . $bentrok[$g->id] . '")')
                ->implode('; ');

            throw \Illuminate\Validation\ValidationException::withMessages([
                'tenaga_pendidik_ids' => "Guru berikut sudah masuk kategori vakasi lain untuk aktivitas yang sama: {$daftar}. Satu guru hanya boleh berada di satu kategori.",
            ]);
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // JAM KERJA
    // ══════════════════════════════════════════════════════════════════════

    public function jamKerjaIndex()
    {
        return Inertia::render('Admin/SmartPayroll/SettingGaji/JamKerja/Index', [
            // Hanya template (hasil generate per-guru disembunyikan).
            'settings' => SettingJamKerja::template()->orderByDesc('is_default')->orderBy('nama')->get(),
            // Guru untuk pemilih generate.
            'guruList' => TenagaPendidik::aktif()->with(['user:id,name', 'jabatan:id,nama_jabatan'])
                ->get()->map(fn ($g) => [
                    'id'      => $g->id,
                    'nama'    => $g->user?->name ?? ('Guru #' . $g->id),
                    'jabatan' => $g->jabatan?->nama_jabatan ?? '-',
                ])->sortBy('nama')->values(),
        ]);
    }

    /** POST jam-kerja/{jamKerja}/generate — generate jam kerja per-guru dari jadwal mengajar. */
    public function jamKerjaGenerate(Request $request, SettingJamKerja $jamKerja)
    {
        $data = $request->validate([
            'guru_ids'   => 'required|array|min:1',
            'guru_ids.*' => 'integer|exists:tenaga_pendidik,id',
            'mode'       => 'nullable|in:mengajar,libur',
            'paksa'      => 'nullable|boolean',
        ]);
        if (!$jamKerja->gunakan_jadwal_per_hari || empty($jamKerja->jadwal_per_hari)) {
            return response()->json(['success' => false, 'message' => 'Template harus memakai jadwal per-hari (isi jam Sen–Sab dulu).'], 422);
        }

        $hasil = (new \App\Services\GenerateJamKerjaService())
            ->generate($jamKerja, $data['guru_ids'], $data['mode'] ?? 'mengajar', (bool) ($data['paksa'] ?? false));

        $khusus = count($hasil['dilewati_khusus'] ?? []);
        return response()->json([
            'success' => true,
            'message' => count($hasil['generated']) . ' guru di-generate.'
                . (count($hasil['dilewati']) ? ' ' . count($hasil['dilewati']) . ' dilewati (tanpa jadwal mengajar).' : '')
                . ($khusus ? ' ' . $khusus . ' dilewati (shift khusus — pakai template asrama/satpam atau centang paksa).' : ''),
            'data'    => $hasil,
        ]);
    }

    public function jamKerjaCreate()
    {
        return Inertia::render('Admin/SmartPayroll/SettingGaji/JamKerja/Form');
    }

    public function jamKerjaStore(Request $request)
    {
        $data = $request->validate([
            'nama'                    => 'required|string|max:100',
            'gunakan_jadwal_per_hari' => 'boolean',
            // Global (wajib jika tidak per hari)
            'jam_masuk'               => 'required_if:gunakan_jadwal_per_hari,false|nullable|date_format:H:i,H:i:s',
            'jam_pulang'              => 'required_if:gunakan_jadwal_per_hari,false|nullable|date_format:H:i,H:i:s',
            'toleransi_terlambat'     => 'required|integer|min:0|max:120',
            'hari_kerja'              => 'required_if:gunakan_jadwal_per_hari,false|nullable|array',
            'total_jam_kerja_sehari'  => 'required|integer|min:0',
            // Per hari (JSON object)
            'jadwal_per_hari'         => 'required_if:gunakan_jadwal_per_hari,true|nullable|array',
            'jadwal_per_hari.*.aktif'      => 'boolean',
            'jadwal_per_hari.*.jam_masuk'  => 'nullable|date_format:H:i,H:i:s',
            'jadwal_per_hari.*.jam_pulang' => 'nullable|date_format:H:i,H:i:s',
            'jadwal_per_hari.*.toleransi'  => 'nullable|integer|min:0|max:120',
        ]);

        SettingJamKerja::create(array_merge($data, ['is_aktif' => true, 'is_template' => true]));

        return redirect()->route('admin.smart-payroll.setting-gaji.jam-kerja.index')
            ->with('success', 'Setting jam kerja berhasil disimpan.');
    }

    public function jamKerjaEdit(SettingJamKerja $jamKerja)
    {
        // Format jam ke H:i (kolom TIME menyimpan H:i:s) agar input time bersih &
        // lolos validasi date_format saat disimpan lagi.
        $fmt = fn($t) => $t ? substr($t, 0, 5) : $t;
        $jadwal = collect($jamKerja->jadwal_per_hari ?? [])->map(function ($d) use ($fmt) {
            if (is_array($d)) {
                $d['jam_masuk']  = $fmt($d['jam_masuk'] ?? null);
                $d['jam_pulang'] = $fmt($d['jam_pulang'] ?? null);
            }
            return $d;
        })->all();

        return Inertia::render('Admin/SmartPayroll/SettingGaji/JamKerja/Form', [
            'setting' => array_merge($jamKerja->toArray(), [
                'jam_masuk'       => $fmt($jamKerja->jam_masuk),
                'jam_pulang'      => $fmt($jamKerja->jam_pulang),
                'jadwal_per_hari' => $jadwal,
            ]),
        ]);
    }

    public function jamKerjaUpdate(Request $request, SettingJamKerja $jamKerja)
    {
        $data = $request->validate([
            'nama'                    => 'required|string|max:100',
            'gunakan_jadwal_per_hari' => 'boolean',
            'jam_masuk'               => 'required_if:gunakan_jadwal_per_hari,false|nullable|date_format:H:i,H:i:s',
            'jam_pulang'              => 'required_if:gunakan_jadwal_per_hari,false|nullable|date_format:H:i,H:i:s',
            'toleransi_terlambat'     => 'required|integer|min:0|max:120',
            'hari_kerja'              => 'required_if:gunakan_jadwal_per_hari,false|nullable|array',
            'total_jam_kerja_sehari'  => 'required|integer|min:0',
            'jadwal_per_hari'         => 'required_if:gunakan_jadwal_per_hari,true|nullable|array',
            'jadwal_per_hari.*.aktif'      => 'boolean',
            'jadwal_per_hari.*.jam_masuk'  => 'nullable|date_format:H:i,H:i:s',
            'jadwal_per_hari.*.jam_pulang' => 'nullable|date_format:H:i,H:i:s',
            'jadwal_per_hari.*.toleransi'  => 'nullable|integer|min:0|max:120',
        ]);

        $jamKerja->update($data);

        return redirect()->route('admin.smart-payroll.setting-gaji.jam-kerja.index')
            ->with('success', 'Setting jam kerja diperbarui.');
    }

    public function jamKerjaDestroy(SettingJamKerja $jamKerja)
    {
        if ($jamKerja->is_default) {
            return back()->with('error', 'Setting default tidak bisa dihapus.');
        }
        $jamKerja->delete();
        return back()->with('success', 'Setting jam kerja dihapus.');
    }

    public function setDefault(SettingJamKerja $jamKerja)
    {
        SettingJamKerja::where('is_default', true)->update(['is_default' => false]);
        $jamKerja->update(['is_default' => true]);
        return back()->with('success', "{$jamKerja->nama} dijadikan setting default.");
    }

    /** Duplikat cepat setting jam kerja (termasuk jadwal per hari). */
    public function jamKerjaDuplicate(SettingJamKerja $jamKerja)
    {
        $baru = $jamKerja->replicate();
        $baru->nama       = mb_substr($jamKerja->nama . ' (salinan)', 0, 100);
        $baru->is_default = false;
        $baru->is_aktif   = true;
        $baru->save();

        return back()->with('success', "Setting jam kerja diduplikat menjadi \"{$baru->nama}\".");
    }
}
<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\SettingKinerja;
use App\Models\TenagaPendidik;
use App\Services\KinerjaCalculationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingKinerjaController extends Controller
{
    public function __construct(
        private readonly KinerjaCalculationService $kinerjaService
    ) {}

    public function index()
    {
        $settings = SettingKinerja::with('dibuatOleh')
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($s) => [
                'id'                       => $s->id,
                'nama'                     => $s->nama,
                'is_aktif'                 => $s->is_aktif,
                'is_default'               => $s->is_default,
                'is_bobot_valid'           => $s->isBobotValid(),
                // Bobot komponen
                'bobot_absensi'            => $s->bobot_absensi,
                'bobot_tugas'              => $s->bobot_tugas,
                'bobot_administrasi'       => $s->bobot_administrasi,
                'bobot_piket'              => $s->bobot_piket,
                'skor_min_piket'           => $s->skor_min_piket ?? 50,
                // Sub-bobot absensi
                'bobot_absensi_harian'     => $s->bobot_absensi_harian,
                'bobot_absensi_mengajar'   => $s->bobot_absensi_mengajar,
                // Keterlambatan
                'hitung_keterlambatan'        => $s->hitung_keterlambatan,
                'toleransi_terlambat_menit'   => $s->toleransi_terlambat_menit,
                'potongan_per_terlambat'      => $s->potongan_per_terlambat,
                'max_potongan_terlambat'      => $s->max_potongan_terlambat,
                // Sub-bobot tugas
                'bobot_tugas_tambahan'     => $s->bobot_tugas_tambahan,
                'bobot_tugas_jabatan'      => $s->bobot_tugas_jabatan,
                'jika_tidak_ada_tugas'     => $s->jika_tidak_ada_tugas,
                // Sub-bobot admin
                'bobot_log_kerja'          => $s->bobot_log_kerja,
                'bobot_laporan_mengajar'   => $s->bobot_laporan_mengajar,
                'target_log_per_hari'      => $s->target_log_per_hari,
                // Grade
                'grade_a' => $s->grade_a, 'grade_b' => $s->grade_b,
                'grade_c' => $s->grade_c, 'grade_d' => $s->grade_d,
                'keterangan'   => $s->keterangan,
                'dibuat_oleh'  => $s->dibuatOleh?->name,
            ]);

        return Inertia::render('Admin/SmartPayroll/SettingKinerja/Index', [
            'settings' => $settings,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateSetting($request);

        // Validasi total bobot 3 komponen INTI = 100 (Piket = penyesuaian, tanpa bobot).
        $totalBobot = $data['bobot_absensi'] + $data['bobot_tugas'] + $data['bobot_administrasi'];
        if (abs($totalBobot - 100) > 0.01) {
            return back()->withErrors([
                'bobot' => "Total bobot Absensi+Tugas+Administrasi harus 100%. Saat ini: {$totalBobot}%"
            ]);
        }

        if (!empty($data['is_default'])) {
            SettingKinerja::where('is_default', true)->update(['is_default' => false]);
        }

        SettingKinerja::create(array_merge($data, [
            'dibuat_oleh' => auth()->id(),
        ]));

        return back()->with('success', "Setting kinerja \"{$data['nama']}\" ditambahkan.");
    }

    public function update(Request $request, SettingKinerja $settingKinerja)
    {
        $data = $this->validateSetting($request, $settingKinerja->id);

        $totalBobot = $data['bobot_absensi'] + $data['bobot_tugas'] + $data['bobot_administrasi'];
        if (abs($totalBobot - 100) > 0.01) {
            return back()->withErrors([
                'bobot' => "Total bobot Absensi+Tugas+Administrasi harus 100%. Saat ini: {$totalBobot}%"
            ]);
        }

        if (!empty($data['is_default'])) {
            SettingKinerja::where('id', '!=', $settingKinerja->id)
                ->where('is_default', true)->update(['is_default' => false]);
        }

        $settingKinerja->update($data);
        return back()->with('success', 'Setting kinerja diperbarui.');
    }

    public function setDefault(SettingKinerja $settingKinerja)
    {
        SettingKinerja::where('is_default', true)->update(['is_default' => false]);
        $settingKinerja->update(['is_default' => true, 'is_aktif' => true]);
        return back()->with('success', "\"{$settingKinerja->nama}\" dijadikan default.");
    }

    public function destroy(SettingKinerja $settingKinerja)
    {
        if ($settingKinerja->is_default) {
            return back()->with('error', 'Setting default tidak bisa dihapus.');
        }
        $settingKinerja->delete();
        return back()->with('success', 'Setting dihapus.');
    }

    /**
     * Preview skor satu guru dengan setting tertentu — real-time tanpa simpan.
     */
    public function preview(Request $request, SettingKinerja $settingKinerja)
    {
        $request->validate([
            'tenaga_pendidik_id' => 'required|exists:tenaga_pendidik,id',
            'bulan'              => 'required|integer|between:1,12',
            'tahun'              => 'required|integer|min:2020',
        ]);

        $guru   = TenagaPendidik::findOrFail($request->tenaga_pendidik_id);
        $result = $this->kinerjaService->preview(
            $guru, (int) $request->bulan, (int) $request->tahun, $settingKinerja
        );

        return response()->json($result);
    }

    private function validateSetting(Request $request, ?int $excludeId = null): array
    {
        return $request->validate([
            'nama'                       => 'required|string|max:100',
            'is_aktif'                   => 'boolean',
            'is_default'                 => 'boolean',
            // Absensi
            'bobot_absensi'              => 'required|numeric|min:0|max:100',
            'bobot_absensi_harian'       => 'required|numeric|min:0|max:100',
            'bobot_absensi_mengajar'     => 'required|numeric|min:0|max:100',
            // Nilai per status
            'nilai_hadir'                => 'required|numeric|min:0|max:100',
            'nilai_terlambat'            => 'required|numeric|min:0|max:100',
            'nilai_izin'                 => 'required|numeric|min:0|max:100',
            'nilai_sakit'                => 'required|numeric|min:0|max:100',
            'nilai_dinas_luar'           => 'required|numeric|min:0|max:100',
            'nilai_alfa'                 => 'required|numeric|min:0|max:100',
            // Penalty
            'hitung_penalty_terlambat'   => 'boolean',
            'toleransi_terlambat_menit'  => 'integer|min:0|max:120',
            'penalty_per_terlambat'      => 'numeric|min:0|max:50',
            'max_penalty_terlambat'      => 'numeric|min:0|max:100',
            // Tugas
            'bobot_tugas'                => 'required|numeric|min:0|max:100',
            'bobot_tugas_tambahan'       => 'required|numeric|min:0|max:100',
            'bobot_tugas_jabatan'        => 'required|numeric|min:0|max:100',
            'jika_tidak_ada_tugas'       => 'required|in:sempurna,nol,skip',
            // Administrasi
            'bobot_administrasi'         => 'required|numeric|min:0|max:100',
            'bobot_piket'                => 'nullable|numeric|min:0|max:100',
            'skor_min_piket'             => 'nullable|integer|min:0|max:100',
            'bobot_laporan_mengajar'     => 'required|numeric|min:0|max:100',
            'bobot_log_kerja'            => 'required|numeric|min:0|max:100',
            'target_log_per_hari'        => 'numeric|min:0.5|max:5',
            // Grade
            'grade_a'                    => 'required|numeric|min:50|max:100',
            'grade_b'                    => 'required|numeric|min:40|max:99',
            'grade_c'                    => 'required|numeric|min:30|max:90',
            'grade_d'                    => 'required|numeric|min:0|max:80',
            'keterangan'                 => 'nullable|string|max:500',
        ]);
    }
}
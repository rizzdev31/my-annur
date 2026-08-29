<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\SettingPotongan;
use App\Models\Jabatan;
use App\Models\TenagaPendidik;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingPotonganController extends Controller
{
    public function index()
    {
        $potongan = SettingPotongan::with('dibuatOleh')
            ->orderBy('kategori')
            ->orderBy('nama')
            ->get()
            ->map(fn($p) => [
                'id'                 => $p->id,
                'nama'               => $p->nama,
                'kode'               => $p->kode,
                'kategori'           => $p->kategori,
                'kategori_label'     => $p->kategori_label,
                'tipe_pemicu'        => $p->tipe_pemicu,
                'tipe_pemicu_label'  => $p->tipe_pemicu_label,
                'tipe_nominal'       => $p->tipe_nominal,
                'nominal'            => $p->nominal,
                'nominal_maksimal'   => $p->nominal_maksimal,
                'nominal_display'    => $p->nominal_display,
                'lingkup'            => $p->lingkup,
                'jabatan_ids'        => $p->jabatan_ids ?? [],
                'tenaga_pendidik_ids'=> $p->tenaga_pendidik_ids ?? [],
                'deskripsi'          => $p->deskripsi,
                'tampil_di_slip'     => $p->tampil_di_slip,
                'is_aktif'           => $p->is_aktif,
                'dibuat_oleh'        => $p->dibuatOleh?->name,
            ]);

        // Kelompokkan per kategori untuk tampilan
        $grouped = $potongan->groupBy('kategori');

        $summary = [
            'total'    => $potongan->count(),
            'aktif'    => $potongan->where('is_aktif', true)->count(),
            'nonaktif' => $potongan->where('is_aktif', false)->count(),
            'per_kategori' => $potongan->groupBy('kategori')->map->count(),
        ];

        return Inertia::render('Admin/SmartPayroll/SettingPotongan/Index', [
            'potongan' => $potongan,
            'grouped'  => $grouped,
            'summary'  => $summary,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/SmartPayroll/SettingPotongan/Form', [
            'jabatan' => Jabatan::aktif()->orderBy('tipe')->get(['id', 'nama_jabatan', 'kode_jabatan', 'tipe']),
            'guru'    => $this->getGuruList(),
            'kategoriOpts'   => self::kategoriOpts(),
            'pemicuOpts'     => self::pemicuOpts(),
            'lingkupOpts'    => self::lingkupOpts(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'                => 'required|string|max:100',
            'kode'                => 'required|string|max:20|unique:setting_potongan,kode',
            'kategori'            => 'required|in:absensi,wajib,simpanan,pinjaman,lainnya',
            'tipe_pemicu'         => 'required|in:per_keterlambatan,per_alfa,per_menit_keterlambatan,per_sesi_tidak_mengajar,per_bulan,persen_gaji,manual',
            'tipe_nominal'        => 'required|in:nominal,persen',
            'nominal'             => 'required|numeric|min:0',
            'nominal_maksimal'    => 'nullable|numeric|min:0',
            'lingkup'             => 'required|in:semua,per_jabatan,per_individu,custom',
            'jabatan_ids'         => 'nullable|array',
            'jabatan_ids.*'       => 'integer|exists:jabatan,id',
            'tenaga_pendidik_ids' => 'nullable|array',
            'tenaga_pendidik_ids.*' => 'integer|exists:tenaga_pendidik,id',
            'deskripsi'           => 'nullable|string|max:500',
            'tampil_di_slip'      => 'boolean',
        ]);

        SettingPotongan::create(array_merge($data, [
            'is_aktif'    => true,
            'dibuat_oleh' => auth()->id(),
        ]));

        return redirect()->route('admin.smart-payroll.setting-potongan.index')
            ->with('success', "Setting potongan \"{$data['nama']}\" berhasil ditambahkan.");
    }

    public function edit(SettingPotongan $settingPotongan)
    {
        return Inertia::render('Admin/SmartPayroll/SettingPotongan/Form', [
            'potongan'       => array_merge($settingPotongan->toArray(), [
                'jabatan_ids'         => $settingPotongan->jabatan_ids ?? [],
                'tenaga_pendidik_ids' => $settingPotongan->tenaga_pendidik_ids ?? [],
            ]),
            'jabatan'        => Jabatan::aktif()->orderBy('tipe')->get(['id', 'nama_jabatan', 'kode_jabatan', 'tipe']),
            'guru'           => $this->getGuruList(),
            'kategoriOpts'   => self::kategoriOpts(),
            'pemicuOpts'     => self::pemicuOpts(),
            'lingkupOpts'    => self::lingkupOpts(),
        ]);
    }

    public function update(Request $request, SettingPotongan $settingPotongan)
    {
        $data = $request->validate([
            'nama'                => 'required|string|max:100',
            'kode'                => 'required|string|max:20|unique:setting_potongan,kode,' . $settingPotongan->id,
            'kategori'            => 'required|in:absensi,wajib,simpanan,pinjaman,lainnya',
            'tipe_pemicu'         => 'required|in:per_keterlambatan,per_alfa,per_menit_keterlambatan,per_sesi_tidak_mengajar,per_bulan,persen_gaji,manual',
            'tipe_nominal'        => 'required|in:nominal,persen',
            'nominal'             => 'required|numeric|min:0',
            'nominal_maksimal'    => 'nullable|numeric|min:0',
            'lingkup'             => 'required|in:semua,per_jabatan,per_individu,custom',
            'jabatan_ids'         => 'nullable|array',
            'tenaga_pendidik_ids' => 'nullable|array',
            'deskripsi'           => 'nullable|string|max:500',
            'tampil_di_slip'      => 'boolean',
        ]);

        $settingPotongan->update($data);

        return redirect()->route('admin.smart-payroll.setting-potongan.index')
            ->with('success', 'Setting potongan diperbarui.');
    }

    public function toggleStatus(SettingPotongan $settingPotongan)
    {
        $settingPotongan->update(['is_aktif' => !$settingPotongan->is_aktif]);
        $status = $settingPotongan->is_aktif ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Potongan \"{$settingPotongan->nama}\" {$status}.");
    }

    public function destroy(SettingPotongan $settingPotongan)
    {
        $settingPotongan->delete();
        return back()->with('success', 'Setting potongan dihapus.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getGuruList()
    {
        return TenagaPendidik::aktif()->with(['user', 'jabatan'])
            ->get()->map(fn($g) => [
                'id'      => $g->id,
                'nama'    => $g->user->name,
                'jabatan' => $g->jabatan?->nama_jabatan ?? '—',
            ]);
    }

    public static function kategoriOpts(): array
    {
        return [
            ['value' => 'absensi',  'label' => 'Absensi',          'desc' => 'Otomatis dari keterlambatan/alfa',     'icon' => '⏰', 'color' => 'amber'],
            ['value' => 'wajib',    'label' => 'Potongan Wajib',    'desc' => 'BPJS, pajak, dan kewajiban tetap',     'icon' => '📋', 'color' => 'red'],
            ['value' => 'simpanan', 'label' => 'Simpanan',          'desc' => 'Koperasi, tabungan, arisan',           'icon' => '💰', 'color' => 'emerald'],
            ['value' => 'pinjaman', 'label' => 'Cicilan Pinjaman',  'desc' => 'Angsuran pinjaman dari lembaga',       'icon' => '🏦', 'color' => 'blue'],
            ['value' => 'lainnya',  'label' => 'Lainnya',           'desc' => 'Potongan custom / tidak terkategori',  'icon' => '📌', 'color' => 'gray'],
        ];
    }

    public static function pemicuOpts(): array
    {
        return [
            ['value' => 'per_keterlambatan',       'label' => 'Per Kejadian Terlambat', 'desc' => 'Dihitung × jumlah terlambat bulan ini'],
            ['value' => 'per_alfa',               'label' => 'Per Hari Alfa',          'desc' => 'Dihitung × jumlah hari tidak hadir'],
            ['value' => 'per_menit_keterlambatan', 'label' => 'Per Menit Keterlambatan','desc' => 'Dihitung × total menit terlambat kumulatif bulan ini'],
            ['value' => 'per_sesi_tidak_mengajar', 'label' => 'Per Sesi Tidak Mengajar','desc' => 'Flat × jumlah sesi mengajar yang digantikan / tidak terlaksana'],
            ['value' => 'per_bulan',              'label' => 'Flat Per Bulan',         'desc' => 'Nominal tetap setiap bulan'],
            ['value' => 'persen_gaji',       'label' => 'Persen dari Gaji Pokok', 'desc' => 'Dihitung % dari total gaji pokok'],
            ['value' => 'manual',            'label' => 'Input Manual',           'desc' => 'Tidak otomatis, diisi manual per guru'],
        ];
    }

    public static function lingkupOpts(): array
    {
        return [
            ['value' => 'semua',        'label' => 'Semua Guru'],
            ['value' => 'per_jabatan',  'label' => 'Per Jabatan'],
            ['value' => 'per_individu', 'label' => 'Per Individu'],
            ['value' => 'custom',       'label' => 'Jabatan & Individu'],
        ];
    }
}
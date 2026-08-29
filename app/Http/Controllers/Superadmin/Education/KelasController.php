<?php

namespace App\Http\Controllers\Superadmin\Education;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\TenagaPendidik;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KelasController extends Controller
{
    public function index(Request $request)
    {
        $kelas = Kelas::with(['tahunAjaran', 'waliKelas.user'])
            ->withCount(['santri', 'jadwalMengajar'])
            ->orderBy('jenis')->orderBy('nama')->get()
            ->map(fn($k) => [
                'id'             => $k->id,
                'nama'           => $k->nama,
                'nama_deskriptif' => $k->nama_deskriptif,
                'jenis'          => $k->jenis,
                'level_tahsin'   => $k->level_tahsin,
                'tingkat'        => $k->tingkat,
                'tahun_ajaran'   => $k->tahunAjaran?->nama,
                'tahun_ajaran_id' => $k->tahun_ajaran_id,
                'wali_kelas'     => $k->waliKelas?->user?->name,
                'wali_kelas_id'  => $k->wali_kelas_id,
                'jumlah_santri'  => $k->santri_count,
                'jumlah_jadwal'  => $k->jadwal_mengajar_count,
                'is_aktif'       => $k->is_aktif,
            ]);

        return Inertia::render('Admin/SmartEducation/Kelas/Index', [
            'kelas'   => $kelas,
            'tahunAjaran' => TahunAjaran::orderByDesc('id')->get(['id', 'nama']),
            'guru'    => TenagaPendidik::aktif()->with('user:id,name')->get()
                ->map(fn($g) => ['id' => $g->id, 'nama' => $g->user?->name ?? '—'])->values(),
            'summary' => [
                'total'   => Kelas::count(),
                'sekolah' => Kelas::sekolah()->count(),
                'tahfidz' => Kelas::tahfidz()->count(),
                'tahsin'  => Kelas::tahsin()->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        Kelas::create(array_merge($data, ['is_aktif' => true]));

        return back()->with('success', "Kelas {$data['nama']} berhasil ditambahkan.");
    }

    public function update(Request $request, Kelas $kelas)
    {
        $data = $this->validateData($request);
        $kelas->update($data);

        return back()->with('success', "Kelas {$kelas->nama} berhasil diperbarui.");
    }

    public function destroy(Kelas $kelas)
    {
        if ($kelas->jadwalMengajar()->count() > 0) {
            return back()->with('error',
                "Kelas {$kelas->nama} masih dipakai di {$kelas->jadwalMengajar()->count()} jadwal mengajar."
            );
        }

        $kelas->update(['is_aktif' => false]);
        return back()->with('success', "Kelas {$kelas->nama} dinonaktifkan.");
    }

    /** GET kelas/{kelas}/santri — daftar santri AKTIF di kelas (untuk UI kenaikan kelas). */
    public function santriKelas(Kelas $kelas)
    {
        $santri = $kelas->santri()->wherePivot('is_aktif', true)->orderBy('nama_lengkap')
            ->get(['santri.id', 'nip', 'nama_lengkap'])
            ->map(fn($s) => ['id' => $s->id, 'nip' => $s->nip, 'nama' => $s->nama_lengkap]);
        return response()->json(['success' => true, 'data' => $santri]);
    }

    /** POST kelas/{kelas}/naik-kelas — pindahkan santri aktif kelas ini ke kelas tujuan (sejenis). */
    public function naikKelas(Request $request, Kelas $kelas)
    {
        $d = $request->validate([
            'kelas_tujuan_id' => 'required|exists:kelas,id|different:kelas_id',
            'kecuali'         => 'nullable|array',
            'kecuali.*'       => 'integer|exists:santri,id',
            'tanggal'         => 'nullable|date_format:Y-m-d',
        ]);

        $tujuan = Kelas::findOrFail($d['kelas_tujuan_id']);
        try {
            $hasil = app(\App\Services\KenaikanKelasService::class)->naikKelasMassal(
                $kelas, $tujuan, $d['kecuali'] ?? [], $d['tanggal'] ?? null
            );
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success',
            "{$hasil['dipindah']} santri naik ke {$tujuan->nama}" .
            ($hasil['dilewati'] ? ", {$hasil['dilewati']} dikecualikan (tinggal kelas)" : '') . '.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'nama'            => 'required|string|max:100',
            'nama_deskriptif' => 'nullable|string|max:100',
            'jenis'           => 'required|in:sekolah,tahfidz,tahsin',
            'level_tahsin'    => 'nullable|integer|min:1|max:6',
            'tingkat'         => 'nullable|string|max:30',
            'tahun_ajaran_id' => 'nullable|exists:tahun_ajaran,id',
            'wali_kelas_id'   => 'nullable|exists:tenaga_pendidik,id',
        ]);
    }
}

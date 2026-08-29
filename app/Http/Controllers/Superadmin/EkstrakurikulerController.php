<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Ekstrakurikuler;
use App\Models\EkstrakurikulerAbsensi;
use App\Models\EkstrakurikulerPenilaian;
use App\Models\Santri;
use App\Models\SettingVakasi;
use App\Models\TahunAjaran;
use App\Models\TenagaPendidik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EkstrakurikulerController extends Controller
{
    private const HARI = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'ahad'];

    private function vakasiDefault(): float
    {
        return (float) (SettingVakasi::where('tipe_aktivitas', 'ekstrakurikuler')->where('is_aktif', true)->value('nominal') ?? 0);
    }

    public function index()
    {
        $default = $this->vakasiDefault();
        $list = Ekstrakurikuler::with(['pembina.user', 'tahunAjaran'])
            ->withCount(['anggota as anggota_count' => fn($q) => $q->where('is_aktif', true), 'pertemuan'])
            ->orderBy('nama')->get()
            ->map(fn($e) => [
                'id' => $e->id, 'nama' => $e->nama, 'deskripsi' => $e->deskripsi,
                'pembina' => $e->pembina?->user?->name ?? '—', 'pembina_id' => $e->pembina_id,
                'hari' => $e->hari, 'jam_mulai' => $e->jam_mulai ? substr($e->jam_mulai, 0, 5) : null,
                'jam_selesai' => $e->jam_selesai ? substr($e->jam_selesai, 0, 5) : null,
                'lokasi' => $e->lokasi, 'kuota' => $e->kuota,
                'tahun_ajaran' => $e->tahunAjaran?->nama, 'tahun_ajaran_id' => $e->tahun_ajaran_id,
                'nominal_vakasi' => $e->nominal_vakasi, 'vakasi_efektif' => $e->nominal_vakasi ?? $default,
                'batas_isi_hari' => $e->batas_isi_hari,
                'anggota' => $e->anggota_count, 'pertemuan' => $e->pertemuan_count, 'is_aktif' => $e->is_aktif,
            ]);

        return Inertia::render('Admin/Ekstrakurikuler/Index', [
            'list' => $list,
            'guru' => TenagaPendidik::aktif()->with('user:id,name')->get()->map(fn($g) => ['id' => $g->id, 'nama' => $g->user?->name ?? '—'])->values(),
            'tahunAjaran' => TahunAjaran::orderByDesc('id')->get(['id', 'nama']),
            'vakasiDefault' => $default,
            'hariOpsi' => self::HARI,
            'summary' => [
                'total' => Ekstrakurikuler::count(),
                'aktif' => Ekstrakurikuler::where('is_aktif', true)->count(),
                'anggota' => \App\Models\EkstrakurikulerSantri::where('is_aktif', true)->distinct('santri_id')->count('santri_id'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->validasi($request);
        Ekstrakurikuler::create($d + ['is_aktif' => true, 'dibuat_oleh' => auth()->id()]);
        return back()->with('success', "Ekstrakurikuler {$d['nama']} ditambahkan.");
    }

    public function update(Request $request, Ekstrakurikuler $ekstrakurikuler)
    {
        $ekstrakurikuler->update($this->validasi($request));
        return back()->with('success', "Ekstrakurikuler {$ekstrakurikuler->nama} diperbarui.");
    }

    public function destroy(Ekstrakurikuler $ekstrakurikuler)
    {
        $ekstrakurikuler->update(['is_aktif' => false]);
        return back()->with('success', "Ekstrakurikuler {$ekstrakurikuler->nama} dinonaktifkan.");
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama' => 'required|string|max:120',
            'deskripsi' => 'nullable|string|max:300',
            'pembina_id' => 'nullable|exists:tenaga_pendidik,id',
            'hari' => 'nullable|in:' . implode(',', self::HARI),
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i|after:jam_mulai',
            'lokasi' => 'nullable|string|max:100',
            'tahun_ajaran_id' => 'nullable|exists:tahun_ajaran,id',
            'kuota' => 'nullable|integer|min:1|max:1000',
            'nominal_vakasi' => 'nullable|numeric|min:0',
            'batas_isi_hari' => 'nullable|integer|min:0|max:30',
        ]);
    }

    // ── Anggota ───────────────────────────────────────────────────────────────
    /** GET ekskul/{id}/anggota — anggota aktif + kandidat santri. */
    public function anggota(Ekstrakurikuler $ekstrakurikuler): JsonResponse
    {
        $anggota = $ekstrakurikuler->santri()->wherePivot('is_aktif', true)->orderBy('nama_lengkap')
            ->get(['santri.id', 'nip', 'nama_lengkap'])
            ->map(fn($s) => ['id' => $s->id, 'nip' => $s->nip, 'nama' => $s->nama_lengkap]);
        $anggotaIds = $anggota->pluck('id');
        $kandidat = Santri::aktif()->whereNotIn('id', $anggotaIds)->orderBy('nama_lengkap')->limit(500)
            ->get(['id', 'nip', 'nama_lengkap'])
            ->map(fn($s) => ['id' => $s->id, 'nip' => $s->nip, 'nama' => $s->nama_lengkap]);
        return response()->json(['success' => true, 'data' => ['anggota' => $anggota, 'kandidat' => $kandidat]]);
    }

    /** POST ekskul/{id}/anggota — {tambah:[ids], keluarkan:[ids]}. */
    public function simpanAnggota(Request $request, Ekstrakurikuler $ekstrakurikuler)
    {
        $d = $request->validate([
            'tambah' => 'nullable|array', 'tambah.*' => 'integer|exists:santri,id',
            'keluarkan' => 'nullable|array', 'keluarkan.*' => 'integer|exists:santri,id',
        ]);
        foreach ($d['tambah'] ?? [] as $sid) {
            \App\Models\EkstrakurikulerSantri::updateOrCreate(
                ['ekstrakurikuler_id' => $ekstrakurikuler->id, 'santri_id' => $sid],
                ['is_aktif' => true, 'tanggal_masuk' => now()->toDateString()]);
        }
        if (!empty($d['keluarkan'])) {
            \App\Models\EkstrakurikulerSantri::where('ekstrakurikuler_id', $ekstrakurikuler->id)
                ->whereIn('santri_id', $d['keluarkan'])->update(['is_aktif' => false]); // histori tetap
        }
        return back()->with('success', 'Anggota diperbarui.');
    }

    // ── Monitoring ────────────────────────────────────────────────────────────
    /** GET ekskul/{id}/monitoring — rekap kehadiran + penilaian per santri. */
    public function monitoring(Ekstrakurikuler $ekstrakurikuler): JsonResponse
    {
        $pertemuanIds = $ekstrakurikuler->pertemuan()->pluck('id');
        $totalPertemuan = $pertemuanIds->count();

        $anggota = $ekstrakurikuler->santri()->wherePivot('is_aktif', true)->orderBy('nama_lengkap')
            ->get(['santri.id', 'nip', 'nama_lengkap']);
        $hadir = EkstrakurikulerAbsensi::whereIn('pertemuan_id', $pertemuanIds)
            ->whereIn('status', ['hadir'])->selectRaw('santri_id, COUNT(*) c')->groupBy('santri_id')->pluck('c', 'santri_id');
        $nilai = EkstrakurikulerPenilaian::where('ekstrakurikuler_id', $ekstrakurikuler->id)
            ->get()->keyBy('santri_id');

        $rows = $anggota->map(fn($s) => [
            'nama' => $s->nama_lengkap, 'nip' => $s->nip,
            'hadir' => (int) ($hadir[$s->id] ?? 0), 'total' => $totalPertemuan,
            'persen' => $totalPertemuan ? round(((int) ($hadir[$s->id] ?? 0)) / $totalPertemuan * 100) : 0,
            'keaktifan' => $nilai[$s->id]->keaktifan ?? null,
            'perkembangan' => $nilai[$s->id]->perkembangan ?? null,
            'catatan' => $nilai[$s->id]->catatan ?? null,
        ]);

        return response()->json(['success' => true, 'data' => [
            'nama' => $ekstrakurikuler->nama, 'total_pertemuan' => $totalPertemuan, 'rows' => $rows,
        ]]);
    }
}

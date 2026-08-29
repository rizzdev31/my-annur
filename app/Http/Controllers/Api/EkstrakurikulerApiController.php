<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ekstrakurikuler;
use App\Models\EkstrakurikulerAbsensi;
use App\Models\EkstrakurikulerPenilaian;
use App\Models\EkstrakurikulerPertemuan;
use App\Models\SettingVakasi;
use App\Models\TahunAjaran;
use App\Services\TimezoneHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ekstrakurikuler — sisi PEMBINA (guru). Absensi per pertemuan (→ vakasi flat) + penilaian A/B/C per semester.
 */
class EkstrakurikulerApiController extends Controller
{
    private function tp(Request $request) { return $request->user()->tenagaPendidik; }
    private function vakasi(Ekstrakurikuler $e): float
    {
        return (float) ($e->nominal_vakasi ?? SettingVakasi::where('tipe_aktivitas', 'ekstrakurikuler')->where('is_aktif', true)->value('nominal') ?? 0);
    }
    private function milikSaya(Request $request, Ekstrakurikuler $e): bool
    {
        return $e->pembina_id === $this->tp($request)?->id;
    }

    /** GET /ekstrakurikuler/saya — ekskul yang diampu. */
    public function saya(Request $request): JsonResponse
    {
        $tp = $this->tp($request);
        if (!$tp) return response()->json(['success' => false, 'message' => 'Tenaga pendidik tidak ditemukan.'], 404);

        $data = Ekstrakurikuler::where('pembina_id', $tp->id)->where('is_aktif', true)
            ->withCount(['anggota as anggota_count' => fn($q) => $q->where('is_aktif', true), 'pertemuan'])
            ->orderBy('nama')->get()
            ->map(fn($e) => [
                'id' => $e->id, 'nama' => $e->nama, 'hari' => $e->hari,
                'jam_mulai' => $e->jam_mulai ? substr($e->jam_mulai, 0, 5) : null,
                'jam_selesai' => $e->jam_selesai ? substr($e->jam_selesai, 0, 5) : null,
                'lokasi' => $e->lokasi, 'anggota' => $e->anggota_count, 'pertemuan' => $e->pertemuan_count,
                'vakasi' => $this->vakasi($e),
            ]);
        return response()->json(['success' => true, 'data' => $data]);
    }

    /** GET /ekstrakurikuler/{id} — detail: anggota + pertemuan. */
    public function detail(Request $request, $id): JsonResponse
    {
        $e = Ekstrakurikuler::findOrFail($id);
        if (!$this->milikSaya($request, $e)) return response()->json(['success' => false, 'message' => 'Bukan ekskul Anda.'], 403);

        $pertemuan = $e->pertemuan()->withCount(['absensi as hadir' => fn($q) => $q->where('status', 'hadir')])
            ->orderByDesc('tanggal')->orderByDesc('id')->limit(50)->get()
            ->map(fn($p) => [
                'id' => $p->id, 'tanggal' => optional($p->tanggal)->locale('id')->isoFormat('dd, D MMM YYYY'),
                'materi' => $p->materi, 'status' => $p->status, 'hadir' => $p->hadir,
            ]);
        return response()->json(['success' => true, 'data' => [
            'id' => $e->id, 'nama' => $e->nama, 'hari' => $e->hari,
            'jam' => $e->jam_mulai ? substr($e->jam_mulai, 0, 5) . '–' . substr($e->jam_selesai, 0, 5) : null,
            'lokasi' => $e->lokasi, 'vakasi' => $this->vakasi($e),
            'anggota_count' => $e->anggota()->where('is_aktif', true)->count(),
            'pertemuan' => $pertemuan,
        ]]);
    }

    /** POST /ekstrakurikuler/{id}/pertemuan — mulai pertemuan (buat + auto absensi hadir). */
    public function mulaiPertemuan(Request $request, $id): JsonResponse
    {
        $e = Ekstrakurikuler::findOrFail($id);
        if (!$this->milikSaya($request, $e)) return response()->json(['success' => false, 'message' => 'Bukan ekskul Anda.'], 403);
        $request->validate(['tanggal' => 'required|date_format:Y-m-d', 'materi' => 'nullable|string|max:300']);

        $tp = $this->tp($request);
        $anggotaIds = $e->anggota()->where('is_aktif', true)->pluck('santri_id');

        $pertemuan = DB::transaction(function () use ($e, $request, $tp, $anggotaIds) {
            $p = EkstrakurikulerPertemuan::create([
                'ekstrakurikuler_id' => $e->id, 'tanggal' => $request->tanggal,
                'jam_mulai_aktual' => TimezoneHelper::now()->format('H:i:s'),
                'materi' => $request->materi, 'status' => 'berlangsung', 'pembina_id' => $tp->id,
            ]);
            foreach ($anggotaIds as $sid) {
                EkstrakurikulerAbsensi::create(['pertemuan_id' => $p->id, 'santri_id' => $sid, 'status' => 'hadir']);
            }
            return $p;
        });
        return response()->json(['success' => true, 'message' => 'Pertemuan dimulai.', 'data' => ['id' => $pertemuan->id]]);
    }

    /** GET /ekstrakurikuler/pertemuan/{id} — roster absensi. */
    public function pertemuanDetail(Request $request, $id): JsonResponse
    {
        $p = EkstrakurikulerPertemuan::with(['ekstrakurikuler', 'absensi.santri:id,nama_lengkap,nip'])->findOrFail($id);
        if (!$this->milikSaya($request, $p->ekstrakurikuler)) return response()->json(['success' => false, 'message' => 'Bukan ekskul Anda.'], 403);

        return response()->json(['success' => true, 'data' => [
            'id' => $p->id, 'nama' => $p->ekstrakurikuler->nama,
            'tanggal' => optional($p->tanggal)->locale('id')->isoFormat('dd, D MMM YYYY'),
            'materi' => $p->materi, 'status' => $p->status,
            'santri' => $p->absensi->sortBy(fn($a) => $a->santri?->nama_lengkap)->map(fn($a) => [
                'id' => $a->id, 'santri_id' => $a->santri_id, 'nama' => $a->santri?->nama_lengkap ?? '—',
                'nip' => $a->santri?->nip, 'status' => $a->status,
            ])->values(),
        ]]);
    }

    /** POST /ekstrakurikuler/pertemuan/{id}/absensi — simpan absensi + selesaikan (→ vakasi). */
    public function simpanAbsensi(Request $request, $id): JsonResponse
    {
        $p = EkstrakurikulerPertemuan::with('ekstrakurikuler')->findOrFail($id);
        if (!$this->milikSaya($request, $p->ekstrakurikuler)) return response()->json(['success' => false, 'message' => 'Bukan ekskul Anda.'], 403);
        if ($p->status === 'selesai') return response()->json(['success' => false, 'message' => 'Pertemuan sudah dikunci.', 'code' => 'TERKUNCI'], 422);

        $request->validate([
            'absensi' => 'required|array|min:1',
            'absensi.*.id' => 'required|integer|exists:ekstrakurikuler_absensi,id',
            'absensi.*.status' => 'required|in:hadir,izin,sakit,alpha',
            'materi' => 'nullable|string|max:300',
        ]);

        $vakasi = $this->vakasi($p->ekstrakurikuler);
        DB::transaction(function () use ($request, $p, $vakasi) {
            foreach ($request->absensi as $row) {
                EkstrakurikulerAbsensi::where('id', $row['id'])->where('pertemuan_id', $p->id)
                    ->update(['status' => $row['status']]);
            }
            $p->update([
                'status' => 'selesai',
                'materi' => $request->filled('materi') ? $request->materi : $p->materi,
                'nominal_vakasi' => $vakasi, 'vakasi_diberikan' => true,
            ]);
        });
        return response()->json(['success' => true, 'message' => "Absensi tersimpan & dikunci. Vakasi pembina tercatat."]);
    }

    /** GET /ekstrakurikuler/{id}/penilaian — anggota + nilai semester berjalan. */
    public function penilaianList(Request $request, $id): JsonResponse
    {
        $e = Ekstrakurikuler::findOrFail($id);
        if (!$this->milikSaya($request, $e)) return response()->json(['success' => false, 'message' => 'Bukan ekskul Anda.'], 403);

        $ta = TahunAjaran::aktif();
        $nilai = EkstrakurikulerPenilaian::where('ekstrakurikuler_id', $e->id)
            ->where('tahun_ajaran_id', $ta?->id)->get()->keyBy('santri_id');
        $santri = $e->santri()->wherePivot('is_aktif', true)->orderBy('nama_lengkap')->get(['santri.id', 'nip', 'nama_lengkap'])
            ->map(fn($s) => [
                'santri_id' => $s->id, 'nama' => $s->nama_lengkap, 'nip' => $s->nip,
                'keaktifan' => $nilai[$s->id]->keaktifan ?? null,
                'perkembangan' => $nilai[$s->id]->perkembangan ?? null,
                'catatan' => $nilai[$s->id]->catatan ?? null,
            ]);
        return response()->json(['success' => true, 'data' => [
            'nama' => $e->nama, 'periode' => $ta?->nama ?? 'Tahun ajaran belum aktif', 'santri' => $santri,
        ]]);
    }

    /** POST /ekstrakurikuler/{id}/penilaian — upsert nilai semester berjalan. */
    public function simpanPenilaian(Request $request, $id): JsonResponse
    {
        $e = Ekstrakurikuler::findOrFail($id);
        if (!$this->milikSaya($request, $e)) return response()->json(['success' => false, 'message' => 'Bukan ekskul Anda.'], 403);
        $request->validate([
            'penilaian' => 'required|array|min:1',
            'penilaian.*.santri_id' => 'required|integer|exists:santri,id',
            'penilaian.*.keaktifan' => 'nullable|in:A,B,C',
            'penilaian.*.perkembangan' => 'nullable|in:A,B,C',
            'penilaian.*.catatan' => 'nullable|string|max:300',
        ]);

        $ta = TahunAjaran::aktif();
        if (!$ta) return response()->json(['success' => false, 'message' => 'Belum ada tahun ajaran aktif.'], 422);
        $tp = $this->tp($request);

        DB::transaction(function () use ($request, $e, $ta, $tp) {
            foreach ($request->penilaian as $row) {
                if (empty($row['keaktifan']) && empty($row['perkembangan']) && empty($row['catatan'])) continue;
                EkstrakurikulerPenilaian::updateOrCreate(
                    ['ekstrakurikuler_id' => $e->id, 'santri_id' => $row['santri_id'], 'tahun_ajaran_id' => $ta->id],
                    ['keaktifan' => $row['keaktifan'] ?? null, 'perkembangan' => $row['perkembangan'] ?? null,
                     'catatan' => $row['catatan'] ?? null, 'dinilai_oleh' => $tp?->id, 'tanggal' => now()->toDateString()]);
            }
        });
        return response()->json(['success' => true, 'message' => 'Penilaian tersimpan.']);
    }
}

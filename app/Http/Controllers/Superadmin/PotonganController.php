<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\JenisPotongan;
use App\Models\PotonganGuru;
use App\Models\TenagaPendidik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Potongan gaji MURNI per-guru (terpisah dari absensi/mengajar).
 * Item (jenis_potongan) dikelola admin; nominal diisi per guru.
 */
class PotonganController extends Controller
{
    public function index()
    {
        // Total potongan aktif per guru (untuk daftar).
        $total = PotonganGuru::where('is_aktif', true)
            ->whereHas('jenis', fn ($q) => $q->where('is_aktif', true))
            ->select('tenaga_pendidik_id', DB::raw('SUM(nominal) as total'))
            ->groupBy('tenaga_pendidik_id')->pluck('total', 'tenaga_pendidik_id');

        $guru = TenagaPendidik::aktif()->with(['user:id,name,foto', 'jabatan:id,nama_jabatan'])
            ->get()->map(fn ($g) => [
                'id'      => $g->id,
                'nama'    => $g->user?->name ?? ('Guru #' . $g->id),
                'foto'    => $g->user?->foto ? asset('storage/' . $g->user->foto) : null,
                'jabatan' => $g->jabatan?->nama_jabatan ?? '-',
                'total'   => (float) ($total[$g->id] ?? 0),
            ])->sortBy('nama')->values();

        return Inertia::render('Admin/SmartPayroll/Potongan/Index', [
            'jenis' => JenisPotongan::urut()->get()->map(fn ($j) => $this->formatJenis($j)),
            'guru'  => $guru,
        ]);
    }

    /** GET potongan/guru/{guru} — nominal guru per item (untuk form). */
    public function guru(TenagaPendidik $tenagaPendidik): JsonResponse
    {
        $nominal = PotonganGuru::where('tenaga_pendidik_id', $tenagaPendidik->id)
            ->pluck('nominal', 'jenis_potongan_id');

        $items = JenisPotongan::aktif()->urut()->get()->map(fn ($j) => [
            'jenis_id' => $j->id,
            'nama'     => $j->nama,
            'kategori' => $j->kategori,
            'nominal'  => (float) ($nominal[$j->id] ?? 0),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'guru'  => ['id' => $tenagaPendidik->id, 'nama' => $tenagaPendidik->user?->name],
                'items' => $items,
            ],
        ]);
    }

    /** POST potongan/guru/{guru} — simpan nominal per item (upsert). */
    public function simpanGuru(Request $request, TenagaPendidik $tenagaPendidik): JsonResponse
    {
        $data = $request->validate([
            'items'            => 'required|array',
            'items.*.jenis_id' => 'required|exists:jenis_potongan,id',
            'items.*.nominal'  => 'nullable|numeric|min:0|max:99999999',
        ]);

        foreach ($data['items'] as $it) {
            $nominal = (float) ($it['nominal'] ?? 0);
            if ($nominal > 0) {
                PotonganGuru::updateOrCreate(
                    ['tenaga_pendidik_id' => $tenagaPendidik->id, 'jenis_potongan_id' => $it['jenis_id']],
                    ['nominal' => $nominal, 'is_aktif' => true]
                );
            } else {
                // 0/kosong → hapus (guru tak kena potongan itu).
                PotonganGuru::where('tenaga_pendidik_id', $tenagaPendidik->id)
                    ->where('jenis_potongan_id', $it['jenis_id'])->delete();
            }
        }

        $total = PotonganGuru::where('tenaga_pendidik_id', $tenagaPendidik->id)->where('is_aktif', true)->sum('nominal');
        return response()->json(['success' => true, 'message' => 'Potongan tersimpan.', 'data' => ['total' => (float) $total]]);
    }

    // ── Kelola Jenis Potongan (item) ────────────────────────────────────────
    public function storeJenis(Request $request): JsonResponse
    {
        $data = $this->validateJenis($request);
        $jenis = JenisPotongan::create($data);
        return response()->json(['success' => true, 'message' => 'Jenis potongan ditambah.', 'data' => $this->formatJenis($jenis)]);
    }

    public function updateJenis(Request $request, JenisPotongan $jenisPotongan): JsonResponse
    {
        $jenisPotongan->update($this->validateJenis($request));
        return response()->json(['success' => true, 'message' => 'Jenis potongan diperbarui.', 'data' => $this->formatJenis($jenisPotongan)]);
    }

    public function toggleJenis(JenisPotongan $jenisPotongan): JsonResponse
    {
        $jenisPotongan->update(['is_aktif' => !$jenisPotongan->is_aktif]);
        return response()->json(['success' => true, 'data' => ['is_aktif' => $jenisPotongan->is_aktif]]);
    }

    public function destroyJenis(JenisPotongan $jenisPotongan): JsonResponse
    {
        $jenisPotongan->delete();   // cascade hapus potongan_guru terkait
        return response()->json(['success' => true, 'message' => 'Jenis potongan dihapus.']);
    }

    private function validateJenis(Request $request): array
    {
        return $request->validate([
            'nama'           => 'required|string|max:100',
            'kategori'       => 'required|in:wajib,simpanan,pinjaman,lainnya',
            'tampil_di_slip' => 'boolean',
            'urutan'         => 'nullable|integer|min:0|max:999',
            'is_aktif'       => 'boolean',
        ]);
    }

    private function formatJenis(JenisPotongan $j): array
    {
        return [
            'id'             => $j->id,
            'nama'           => $j->nama,
            'kategori'       => $j->kategori,
            'kategori_label' => $j->kategori_label,
            'tampil_di_slip' => $j->tampil_di_slip,
            'urutan'         => $j->urutan,
            'is_aktif'       => $j->is_aktif,
        ];
    }
}

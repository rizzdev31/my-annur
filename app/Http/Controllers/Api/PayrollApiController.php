<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DetailPenggajian;
use App\Models\Penggajian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollApiController extends Controller
{
    /**
     * GET /payroll/riwayat — daftar slip gaji guru ini (semua periode, hanya final/dibayar)
     */
    public function riwayat(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $list = Penggajian::with('periodePenggajian')
            ->where('tenaga_pendidik_id', $tp->id)
            ->whereIn('status', ['final', 'dibayar'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $list->map(fn($g) => $this->formatRingkasan($g))->values(),
            'total'   => $list->count(),
        ]);
    }

    /**
     * GET /payroll/terkini — slip gaji periode terbaru guru ini
     */
    public function terkini(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $g = Penggajian::with(['periodePenggajian', 'jabatan'])
            ->where('tenaga_pendidik_id', $tp->id)
            ->whereIn('status', ['final', 'dibayar'])
            ->latest('id')
            ->first();

        if (!$g) {
            return response()->json([
                'success' => true,
                'data'    => null,
                'message' => 'Belum ada data penggajian yang final.',
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatDetail($g),
        ]);
    }

    /**
     * GET /payroll/{periode} — penggajian guru ini untuk periode tertentu (by periode_penggajian_id)
     */
    public function detail(Request $request, $periodeId): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $g = Penggajian::with(['periodePenggajian', 'jabatan'])
            ->where('tenaga_pendidik_id', $tp->id)
            ->where('periode_penggajian_id', $periodeId)
            ->first();

        if (!$g) {
            return response()->json([
                'success' => false,
                'message' => 'Data penggajian tidak ditemukan untuk periode ini.',
                'code'    => 'NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatDetail($g),
        ]);
    }

    /**
     * GET /payroll/{penggajian}/slip — slip gaji lengkap beserta breakdown detail per komponen
     */
    public function slip(Request $request, $penggajianId): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return $this->notFound();

        $g = Penggajian::with(['periodePenggajian', 'jabatan', 'detailPenggajian'])
            ->where('tenaga_pendidik_id', $tp->id)
            ->find($penggajianId);

        if (!$g) {
            return response()->json([
                'success' => false,
                'message' => 'Slip gaji tidak ditemukan.',
                'code'    => 'NOT_FOUND',
            ], 404);
        }

        $detail = $g->detailPenggajian;

        return response()->json([
            'success' => true,
            'data'    => [
                ...$this->formatDetail($g),
                'breakdown_pendapatan' => $detail
                    ->filter(fn($d) => !$d->isPotongan())
                    ->map(fn($d) => $this->formatDetailItem($d))
                    ->values(),
                'breakdown_potongan' => $detail
                    ->filter(fn($d) => $d->isPotongan())
                    ->map(fn($d) => $this->formatDetailItem($d))
                    ->values(),
            ],
        ]);
    }

    // ─── Format Helpers ───────────────────────────────────────────────────────

    private function formatRingkasan(Penggajian $g): array
    {
        $p = $g->periodePenggajian;
        return [
            'id'               => $g->id,
            'periode_id'       => $g->periode_penggajian_id,
            'nama_periode'     => $p?->nama_bulan ?? '—',
            'bulan'            => $p?->bulan,
            'tahun'            => $p?->tahun,
            'gaji_bersih'      => $g->gaji_bersih,
            'total_pendapatan' => $g->total_pendapatan,
            'total_potongan'   => $g->total_potongan,
            'status'           => $g->status,
            'status_label'     => match ($g->status) {
                'final'   => 'Siap Bayar',
                'dibayar' => 'Sudah Dibayar',
                'draft'   => 'Draft',
                default   => ucfirst($g->status),
            },
            'dibayar_pada'     => $g->dibayar_pada?->format('d M Y'),
        ];
    }

    private function formatDetail(Penggajian $g): array
    {
        $p  = $g->periodePenggajian;
        $jp = $this->jpRincian($g);
        return [
            ...$this->formatRingkasan($g),
            'jabatan' => $g->jabatan?->nama_jabatan,

            // Komponen pendapatan
            'gaji_pokok'              => $g->gaji_pokok,
            'vakasi_absen_harian'     => $g->vakasi_absen_harian,
            'vakasi_mengajar'         => $g->vakasi_mengajar,
            'vakasi_tugas_jabatan'    => $g->vakasi_tugas_jabatan,
            'vakasi_tugas_tambahan'   => $g->vakasi_tugas_tambahan,
            'vakasi_peserta_kegiatan' => $g->vakasi_peserta_kegiatan,
            // Komponen yang dulu tidak terkirim ke aplikasi guru sehingga
            // rincian di slip tidak pernah pas dengan totalnya.
            'vakasi_piket'            => $g->vakasi_piket ?? 0,
            'vakasi_ekstrakurikuler'  => $g->vakasi_ekstrakurikuler ?? 0,
            'vakasi_lembur'           => $g->vakasi_lembur ?? 0,
            'tunjangan_lainnya'       => $g->tunjangan_lainnya,

            // Komponen potongan
            'potongan_keterlambatan'  => $g->potongan_keterlambatan,
            'potongan_alfa'           => $g->potongan_alfa,
            'potongan_tetap'          => $g->potongan_tetap,
            'potongan_lainnya'        => $g->potongan_lainnya,
            'potongan_liburan'        => $g->potongan_liburan,
            'keterangan_liburan'      => $g->keterangan_liburan,
            'potongan_guru'           => $g->potongan_guru ?? 0,
            // Potongan yang melebihi pendapatan bulan ini (belum terpotong).
            'potongan_tidak_terbayar' => $g->potongan_tidak_terbayar ?? 0,

            // Statistik absensi bulan ini
            'statistik' => [
                'hari_kerja'  => $g->total_hari_kerja,
                'hadir'       => $g->total_hadir,
                'izin'        => $g->total_izin,
                'sakit'       => $g->total_sakit,
                'alfa'        => $g->total_alfa,
                'terlambat'   => $g->total_terlambat,
                // JP total yang diampu (jadwal sendiri + pengganti + libur/izin),
                // bukan hanya JP yang dibayar vakasi — lihat jpRincian().
                'jp_mengajar'   => $jp['total'],
                'jp_sendiri'    => $jp['sendiri'],
                'jp_pengganti'  => $jp['pengganti'],
                'jp_libur_izin' => $jp['libur_izin'],
                'jp_dibayar'    => (int) $g->total_jp_mengajar,
            ],

            // Periode info
            'periode' => [
                'id'              => $p?->id,
                'nama'            => $p?->nama_bulan,
                'tanggal_mulai'   => $p?->tanggal_mulai?->toDateString(),
                'tanggal_selesai' => $p?->tanggal_selesai?->toDateString(),
            ],
        ];
    }

    /**
     * Rincian JP mengajar untuk ditampilkan di slip.
     *
     * Dipakai kolom hasil generate bila ada. Periode lama (kolomnya masih NULL)
     * dihitung langsung dari absensi mengajar dengan aturan yang sama seperti
     * PayrollCalculationService::hitungRekapMengajar, supaya guru tidak melihat
     * "0 JP" hanya karena periodenya belum di-generate ulang.
     */
    private function jpRincian(Penggajian $g): array
    {
        $sendiri   = $g->total_jp_sendiri;
        $pengganti = $g->total_jp_pengganti;
        $liburIzin = $g->total_jp_libur_izin;

        if ($sendiri === null || $pengganti === null || $liburIzin === null) {
            $periode = $g->periodePenggajian;
            $mulai   = $periode?->tanggal_mulai?->toDateString();
            $selesai = $periode?->tanggal_selesai?->toDateString();

            if (!$mulai || !$selesai) {
                return [
                    'sendiri' => 0, 'pengganti' => (int) $g->total_jp_mengajar,
                    'libur_izin' => 0, 'total' => (int) $g->total_jp_mengajar,
                ];
            }

            $milik = \App\Models\AbsensiMengajar::where('tenaga_pendidik_id', $g->tenaga_pendidik_id)
                ->whereBetween('tanggal', [$mulai, $selesai])
                ->whereIn('status', ['hadir', 'terlaksana', 'libur', 'izin'])
                ->get(['status', 'jp_terlaksana']);

            $sendiri   = (int) $milik->whereIn('status', ['hadir', 'terlaksana'])->sum('jp_terlaksana');
            $liburIzin = (int) $milik->whereIn('status', ['libur', 'izin'])->sum('jp_terlaksana');
            $pengganti = (int) \App\Models\AbsensiMengajar::where('digantikan_oleh', $g->tenaga_pendidik_id)
                ->whereBetween('tanggal', [$mulai, $selesai])
                ->where('status', 'pengganti')
                ->sum('jp_terlaksana');
        }

        return [
            'sendiri'    => (int) $sendiri,
            'pengganti'  => (int) $pengganti,
            'libur_izin' => (int) $liburIzin,
            'total'      => (int) $sendiri + (int) $pengganti + (int) $liburIzin,
        ];
    }

    private function formatDetailItem(DetailPenggajian $d): array
    {
        return [
            'tipe'             => $d->tipe,
            'label'            => $d->label_tipe,
            'keterangan'       => $d->keterangan,
            'jumlah_satuan'    => $d->jumlah_satuan,
            'satuan'           => $d->satuan,
            'nilai_per_satuan' => $d->nilai_per_satuan,
            'subtotal'         => $d->subtotal,
            'is_potongan'      => $d->isPotongan(),
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
    }
}

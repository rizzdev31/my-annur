<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\TenagaPendidik;
use App\Models\LiburTendik;
use App\Models\AbsensiHarian;
use App\Services\TimezoneHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Libur individu tenaga pendidik (khusus guru mukim).
 * CRUD manual + generator rolling + tukar/pindah libur, dengan exception handling
 * (tolak bila sudah hadir, bersihkan alfa basi, cegah duplikat).
 */
class LiburTendikController extends Controller
{
    private const HARI = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'ahad'];

    public function index(Request $request)
    {
        $tahun = (int) ($request->tahun ?? now()->year);

        $guruMukim = TenagaPendidik::mukim()->where('is_aktif', true)
            ->with(['user:id,name', 'jamKerja:id,nama'])
            ->get()
            ->map(fn($g) => [
                'id'        => $g->id,
                'nama'      => $g->user?->name ?? '—',
                'nip'       => $g->nip,
                'jam_kerja' => $g->jamKerjaAktif()?->nama ?? '—',
            ])->values();

        // Semua guru aktif (untuk "Kelola Guru Mukim" — tandai grup).
        $semuaGuru = TenagaPendidik::where('is_aktif', true)
            ->with('user:id,name')
            ->orderByDesc('is_mukim')
            ->get()
            ->map(fn($g) => [
                'id'         => $g->id,
                'nama'       => $g->user?->name ?? '—',
                'jenis_guru' => $g->jenis_guru,
                'is_mukim'   => (bool) $g->is_mukim,
            ])->values();

        $guruId = $request->guru_id ? (int) $request->guru_id : ($guruMukim->first()['id'] ?? null);

        $liburList = $guruId
            ? LiburTendik::with('ditukarDengan.user:id,name')
                ->where('tenaga_pendidik_id', $guruId)
                ->whereYear('tanggal', $tahun)
                ->orderBy('tanggal')
                ->get()
                ->map(fn($l) => [
                    'id'             => $l->id,
                    'tanggal'        => $l->tanggal->toDateString(),
                    'tanggal_label'  => $l->tanggal->locale('id')->isoFormat('ddd, D MMM YYYY'),
                    'tipe'           => $l->tipe,
                    'alasan'         => $l->alasan,
                    'ditukar_dengan' => $l->ditukarDengan?->user?->name,
                    'ditukar_tanggal'=> $l->ditukar_tanggal?->toDateString(),
                ])->values()
            : collect();

        return Inertia::render('Admin/SmartPayroll/LiburTendik/Index', [
            'guruMukim' => $guruMukim,
            'semuaGuru' => $semuaGuru,
            'guruId'    => $guruId,
            'tahun'     => $tahun,
            'liburList' => $liburList,
            'hariOpsi'  => self::HARI,
            'ringkasan' => [
                'total_guru' => $guruMukim->count(),
                'total_libur'=> $liburList->count(),
            ],
        ]);
    }

    /** Tambah libur manual (satu atau beberapa tanggal sekaligus). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'tenaga_pendidik_id' => 'required|exists:tenaga_pendidik,id',
            'tanggal'            => 'required|array|min:1',
            'tanggal.*'          => 'required|date_format:Y-m-d',
            'alasan'             => 'nullable|string|max:255',
        ]);

        $dibuat = 0; $gagal = [];
        DB::transaction(function () use ($data, &$dibuat, &$gagal) {
            foreach ($data['tanggal'] as $tgl) {
                try {
                    $this->pasangLibur((int) $data['tenaga_pendidik_id'], $tgl, 'manual', $data['alasan'] ?? null);
                    $dibuat++;
                } catch (\DomainException $e) {
                    $gagal[] = $e->getMessage();
                }
            }
        });

        return $this->hasil($dibuat, $gagal, 'Libur ditambahkan');
    }

    /**
     * Tandai GRUP guru mukim (libur individu rolling). Daftar yang dikirim = set
     * lengkap: yang tercantum → is_mukim true, sisanya (aktif) → false.
     */
    public function kelolaMukim(Request $request)
    {
        $data = $request->validate([
            'guru_ids'   => 'present|array',
            'guru_ids.*' => 'integer|exists:tenaga_pendidik,id',
        ]);
        $ids = $data['guru_ids'] ?? [];

        DB::transaction(function () use ($ids) {
            TenagaPendidik::where('is_aktif', true)->update(['is_mukim' => false]);
            if ($ids) TenagaPendidik::whereIn('id', $ids)->update(['is_mukim' => true]);
        });

        return back()->with('success', count($ids) . ' guru ditetapkan sebagai guru mukim (libur individu).');
    }

    /**
     * Generator rolling PER BULAN untuk satu/banyak guru sekaligus.
     * Urutan hari berputar tiap MINGGU dan BERLANJUT antar bulan (anchor: nomor
     * minggu ISO). Contoh hari=[jumat,ahad] → minggu ganjil Jumat, genap Ahad,
     * terus menyambung ke bulan berikutnya. Regenerasi bersih (libur 'rutin'
     * bulan itu ditimpa; manual/tukar dipertahankan).
     */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'guru_ids'   => 'required|array|min:1',
            'guru_ids.*' => 'integer|exists:tenaga_pendidik,id',
            'bulan'      => 'required|integer|min:1|max:12',
            'tahun'      => 'required|integer|min:2020|max:2100',
            'hari'       => 'required|array|min:1',
            'hari.*'     => 'in:' . implode(',', self::HARI),
            'alasan'     => 'nullable|string|max:255',
        ]);

        $hariList = array_values($data['hari']);
        $mulai = Carbon::create($data['tahun'], $data['bulan'], 1, 0, 0, 0, TimezoneHelper::TZ)->startOfMonth();
        $akhir = $mulai->copy()->endOfMonth();
        $dibuat = 0; $gagal = [];

        DB::transaction(function () use ($data, $hariList, $mulai, $akhir, &$dibuat, &$gagal) {
            foreach ($data['guru_ids'] as $tpId) {
                // Bersihkan libur 'rutin' bulan ini agar regenerasi tidak menumpuk.
                LiburTendik::where('tenaga_pendidik_id', $tpId)
                    ->where('tipe', 'rutin')
                    ->whereBetween('tanggal', [$mulai->toDateString(), $akhir->toDateString()])
                    ->delete();

                $c = $mulai->copy();
                while ($c->lte($akhir)) {
                    // Rotasi berlanjut antar bulan via paritas minggu ISO.
                    $target = $hariList[($c->isoWeek - 1) % count($hariList)];
                    if (TimezoneHelper::namaHariDB($c) === $target) {
                        try {
                            $this->pasangLibur((int) $tpId, $c->toDateString(), 'rutin', $data['alasan'] ?? null);
                            $dibuat++;
                        } catch (\DomainException $e) {
                            $gagal[] = $c->toDateString() . ': ' . $e->getMessage();
                        }
                    }
                    $c->addDay();
                }
            }
        });

        $namaBulan = $mulai->locale('id')->isoFormat('MMMM YYYY');
        return $this->hasil($dibuat, $gagal, "Generator {$namaBulan} selesai — {$dibuat} libur dibuat");
    }

    /** Pindahkan satu libur ke tanggal lain (mis. tukar mandiri). */
    public function pindah(Request $request, LiburTendik $liburTendik)
    {
        $data = $request->validate([
            'tanggal_baru' => 'required|date_format:Y-m-d',
            'alasan'       => 'nullable|string|max:255',
        ]);

        $tglLama = $liburTendik->tanggal->toDateString();
        if ($data['tanggal_baru'] === $tglLama) {
            return back()->with('error', 'Tanggal baru sama dengan tanggal lama.');
        }

        try {
            DB::transaction(function () use ($liburTendik, $data, $tglLama) {
                $tpId = $liburTendik->tenaga_pendidik_id;
                $liburTendik->delete();
                $this->pasangLibur($tpId, $data['tanggal_baru'], 'tukar',
                    $data['alasan'] ?? $liburTendik->alasan, null, $tglLama);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Libur dipindah ke {$data['tanggal_baru']}.");
    }

    /**
     * Tukar libur antar DUA guru: A (tanggal_a) ⇄ B (tanggal_b).
     * Setelah tukar: A libur di tanggal_b, B libur di tanggal_a.
     */
    public function tukar(Request $request)
    {
        $data = $request->validate([
            'tendik_a'  => 'required|exists:tenaga_pendidik,id',
            'tanggal_a' => 'required|date_format:Y-m-d',
            'tendik_b'  => 'required|exists:tenaga_pendidik,id|different:tendik_a',
            'tanggal_b' => 'required|date_format:Y-m-d',
            'alasan'    => 'nullable|string|max:255',
        ]);

        try {
            DB::transaction(function () use ($data) {
                $a = (int) $data['tendik_a']; $b = (int) $data['tendik_b'];
                // Lepas libur lama (bila ada) lalu pasang silang.
                LiburTendik::where('tenaga_pendidik_id', $a)->whereDate('tanggal', $data['tanggal_a'])->delete();
                LiburTendik::where('tenaga_pendidik_id', $b)->whereDate('tanggal', $data['tanggal_b'])->delete();
                $this->pasangLibur($a, $data['tanggal_b'], 'tukar', $data['alasan'] ?? 'Tukar libur', $b, $data['tanggal_a']);
                $this->pasangLibur($b, $data['tanggal_a'], 'tukar', $data['alasan'] ?? 'Tukar libur', $a, $data['tanggal_b']);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Libur kedua guru berhasil ditukar.');
    }

    public function destroy(LiburTendik $liburTendik)
    {
        $liburTendik->delete();
        return back()->with('success', 'Libur dihapus.');
    }

    // ─── Helper inti dengan exception handling ──────────────────────────────────

    /**
     * Pasang libur untuk (guru, tanggal). Exception:
     *   • tolak bila guru sudah tercatat HADIR (jam_masuk terisi) di tanggal itu,
     *   • bersihkan baris ALFA basi (auto-alfa terlanjur jalan) di tanggal itu,
     *   • idempotent (updateOrCreate, unik per guru+tanggal).
     */
    private function pasangLibur(
        int $tpId, string $tanggal, string $tipe, ?string $alasan,
        ?int $ditukarDenganId = null, ?string $ditukarTanggal = null
    ): LiburTendik {
        $sudahHadir = AbsensiHarian::where('tenaga_pendidik_id', $tpId)
            ->whereDate('tanggal', $tanggal)
            ->whereNotNull('jam_masuk')
            ->exists();
        if ($sudahHadir) {
            $nama = TenagaPendidik::find($tpId)?->user?->name ?? "guru #$tpId";
            throw new \DomainException("$nama sudah tercatat hadir pada $tanggal — koreksi absensi dulu sebelum dijadikan libur.");
        }

        // Bersihkan alfa otomatis yang terlanjur dibuat (libur ditetapkan belakangan).
        AbsensiHarian::where('tenaga_pendidik_id', $tpId)
            ->whereDate('tanggal', $tanggal)
            ->where('status', 'alfa')
            ->delete();

        return LiburTendik::updateOrCreate(
            ['tenaga_pendidik_id' => $tpId, 'tanggal' => $tanggal],
            [
                'tipe'              => $tipe,
                'alasan'            => $alasan,
                'ditukar_dengan_id' => $ditukarDenganId,
                'ditukar_tanggal'   => $ditukarTanggal,
                'dibuat_oleh'       => Auth::id(),
            ]
        );
    }

    private function hasil(int $dibuat, array $gagal, string $sukses)
    {
        if ($dibuat === 0 && $gagal) {
            return back()->with('error', 'Gagal: ' . implode(' | ', $gagal));
        }
        $msg = "{$sukses}." . ($gagal ? ' Beberapa dilewati: ' . implode(' | ', $gagal) : '');
        return back()->with('success', $msg);
    }
}

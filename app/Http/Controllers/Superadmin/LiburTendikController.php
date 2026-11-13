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

        // Daftar kiri: siapa pun yang punya libur asrama — penerima hak libur
        // pekanan ATAU penanda mukim lama. Dulu hanya `mukim()`, sehingga guru
        // yang berhak libur tapi tidak ditandai mukim tidak pernah muncul di
        // pengelolaan liburnya.
        $guruMukim = TenagaPendidik::where('is_aktif', true)
            ->where(fn ($q) => $q->where('is_mukim', true)->orWhere('boleh_libur_pekanan', true))
            ->with(['user:id,name', 'jamKerja:id,nama'])
            ->get()
            ->map(fn($g) => [
                'id'        => $g->id,
                'nama'      => $g->user?->name ?? '—',
                'nip'       => $g->nip,
                'jam_kerja' => $g->jamKerjaAktif()?->nama ?? '—',
                'mode'      => $g->modeLiburAsrama(),
                'hari_paten'=> $g->namaHariLiburPaten(),
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
                // Mode libur asrama: 'tidak' | 'paten' | 'pengajuan'.
                'mode'             => $g->modeLiburAsrama(),
                'hari_libur_paten' => $g->hari_libur_paten,
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
            'liburGanda' => $this->periksaLiburGanda(),
        ]);
    }

    /**
     * Guru yang punya DUA sumber hari libur yang saling berbeda.
     *
     * `hari_libur_paten` mudah disalahartikan: ia BUKAN "hari libur tetap",
     * melainkan "jatah libur asrama sudah dipatok, jadi tidak bisa diajukan".
     * Hari libur biasa tinggal di jam kerja guru. Kalau keduanya terisi dan
     * harinya berbeda, guru itu diam-diam libur dua hari sepekan DAN
     * pengajuan libur asramanya ikut terblokir.
     *
     * Terjadi beneran: migrasi 1 Nov 2026 mengisi `hari_libur_paten` dengan
     * menebak dari baris libur lama (hari yang paling sering muncul). Untuk
     * seorang guru tebakannya Jumat, padahal jam kerjanya libur Kamis —
     * dan baru ketahuan setelah gurunya mengeluh tidak bisa mengajukan.
     * Pemeriksaan ini supaya ketidakcocokan berikutnya terlihat di layar,
     * bukan lewat keluhan.
     *
     * Hanya menandai, tidak mengubah apa pun.
     */
    private function periksaLiburGanda(): array
    {
        return TenagaPendidik::where('is_aktif', true)
            ->whereNotNull('hari_libur_paten')
            ->with('user:id,name')
            ->get()
            ->map(function ($g) {
                // Hari yang jam kerjanya tandai TIDAK masuk.
                $liburJamKerja = collect($g->jamKerjaAktif()?->jadwal_per_hari ?? [])
                    ->reject(fn ($d) => $d['aktif'] ?? false)
                    ->keys()
                    ->map(fn ($h) => mb_strtolower((string) $h))
                    ->values();

                // Tidak punya hari libur dari jam kerja = memang murni
                // mengandalkan libur paten (pola guru asrama). Itu benar.
                if ($liburJamKerja->isEmpty()) return null;

                $hariPaten = mb_strtolower((string) (self::HARI[((int) $g->hari_libur_paten) - 1] ?? ''));
                if ($hariPaten === '' || $liburJamKerja->contains($hariPaten)) return null;

                return [
                    'id'              => $g->id,
                    'nama'            => $g->user?->name ?? '—',
                    'hari_paten'      => TimezoneHelper::namaHariIso((int) $g->hari_libur_paten),
                    'libur_jam_kerja' => $liburJamKerja->map(fn ($h) => ucfirst($h))->implode(', '),
                ];
            })
            ->filter()
            ->values()
            ->all();
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
     * Tetapkan MODE LIBUR ASRAMA per guru dalam satu kiriman.
     *
     *  'tidak'     → bukan penerima hak libur asrama.
     *  'paten'     → harinya tetap (wajib menyertakan nomor hari ISO 1-7);
     *                terbit otomatis oleh command libur:paten-generate.
     *  'pengajuan' → fleksibel, diajukan sendiri tiap pekan.
     *
     * Hanya guru yang DIKIRIM yang diubah — bukan "kosongkan semua lalu set
     * yang dicentang" seperti kelolaMukim. Pola menimpa-semua berbahaya di
     * sini: satu penyimpanan dari layar yang datanya basi akan mencabut hak
     * libur guru yang baru saja ditetapkan orang lain.
     */
    public function simpanModeLibur(Request $request)
    {
        $data = $request->validate([
            'guru'               => 'required|array|min:1',
            'guru.*.id'          => 'required|integer|exists:tenaga_pendidik,id',
            'guru.*.mode'        => 'required|in:tidak,paten,pengajuan',
            'guru.*.hari'        => 'nullable|integer|min:1|max:7',
        ]);

        $ubah = 0;
        $tanpaHari = [];

        DB::transaction(function () use ($data, &$ubah, &$tanpaHari) {
            foreach ($data['guru'] as $baris) {
                $guru = TenagaPendidik::find($baris['id']);
                if (! $guru) continue;

                $mode = $baris['mode'];
                $hari = $baris['hari'] ?? null;

                // Paten tanpa hari tidak bermakna: command tidak akan pernah
                // menerbitkan liburnya, dan gurunya juga tidak boleh mengajukan
                // — ia akan terjebak tanpa libur sama sekali.
                if ($mode === 'paten' && ! $hari) {
                    $tanpaHari[] = $guru->user?->name ?? ('Guru #' . $guru->id);
                    continue;
                }

                $guru->update([
                    'boleh_libur_pekanan' => $mode !== 'tidak',
                    'hari_libur_paten'    => $mode === 'paten' ? (int) $hari : null,
                ]);
                $ubah++;
            }
        });

        $pesan = "Mode libur {$ubah} guru disimpan.";
        if ($tanpaHari) {
            $pesan .= ' ' . count($tanpaHari) . ' dilewati karena mode paten tanpa hari ('
                . implode(', ', $tanpaHari) . ').';
        }

        return back()->with($tanpaHari ? 'warning' : 'success', $pesan);
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

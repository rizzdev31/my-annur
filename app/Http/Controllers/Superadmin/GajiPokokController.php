<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\KomponenGajiPokok;
use App\Models\Penggajian;
use App\Models\PeriodePenggajian;
use App\Models\TenagaPendidik;
use App\Models\VariabelGajiPokok;
use App\Services\KinerjaCalculationService;
use App\Services\TimezoneHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * GAJI POKOK PER GURU — penetapan komponen dan jumlah keseluruhannya.
 *
 * Satu layar untuk menjawab "gaji pokok orang ini terdiri dari apa saja":
 * jam mengajar, jabatan 1, tunjangan pendidikan, jabatan 2, jabatan 3, lalu
 * jumlah keseluruhannya. Di sampingnya ditampilkan kehadiran & JP pengganti
 * (inval) periode itu, karena itulah yang menambah di atas gaji pokok.
 *
 * Gaji pokok di sini TIDAK terpotong keterlambatan/alfa: tidak ada satu pun
 * setting potongan aktif, dan tak satu pun berbasis persen gaji pokok (terukur
 * 28 Okt 2026). Potongan per guru (simpanan, voucher, lazismu, pinjaman) tetap
 * mengurangi gaji bersih karena sifatnya titipan/angsuran, bukan sanksi.
 */
class GajiPokokController extends Controller
{
    /** Slot jabatan paling sedikit yang selalu tampil, walau kosong. */
    private const JABATAN_MINIMAL = 3;

    /** Batas waras; menahan kunci slot yang dikarang dari luar formulir. */
    private const JABATAN_MAKSIMAL = 12;

    /**
     * Urutan baku tiap slot. Nilainya TIDAK boleh digeser: 148 komponen yang
     * sudah tersimpan memakai urutan 1..5, dan pemetaan baliknya bersandar
     * pada sortBy('urutan'). Jabatan ke-4 dan seterusnya melanjutkan deret itu.
     */
    private static function urutanJabatan(int $ke): int
    {
        return $ke === 1 ? 2 : $ke + 2;     // 1→2, 2→4, 3→5, 4→6, 5→7 …
    }

    /**
     * Definisi slot untuk sejumlah jabatan tertentu. Jam mengajar dan tunjangan
     * pendidikan tetap satu, jabatannya sebanyak yang diperlukan.
     */
    private static function slotDefinisi(int $jumlahJabatan): array
    {
        $jumlahJabatan = max(self::JABATAN_MINIMAL, min(self::JABATAN_MAKSIMAL, $jumlahJabatan));

        $def = [
            ['kunci' => 'jam_mengajar', 'jenis' => 'jam_mengajar',
             'label' => 'Jam Mengajar', 'urutan' => 1, 'berlabel' => false],
            ['kunci' => 'jabatan_1', 'jenis' => 'jabatan',
             'label' => 'Jabatan 1', 'urutan' => self::urutanJabatan(1), 'berlabel' => true],
            ['kunci' => 'tunjangan_pendidikan', 'jenis' => 'tunjangan_pendidikan',
             'label' => 'Tunjangan Pendidikan', 'urutan' => 3, 'berlabel' => false],
        ];

        for ($ke = 2; $ke <= $jumlahJabatan; $ke++) {
            $def[] = ['kunci' => 'jabatan_' . $ke, 'jenis' => 'jabatan',
                'label' => 'Jabatan ' . $ke, 'urutan' => self::urutanJabatan($ke), 'berlabel' => true];
        }

        return $def;
    }

    /** Terjemahkan satu kunci slot kiriman formulir menjadi definisinya. */
    private static function definisiDariKunci(string $kunci): ?array
    {
        if ($kunci === 'jam_mengajar' || $kunci === 'tunjangan_pendidikan') {
            return collect(self::slotDefinisi(self::JABATAN_MINIMAL))->firstWhere('kunci', $kunci);
        }

        if (preg_match('/^jabatan_(\d{1,2})$/', $kunci, $m)) {
            $ke = (int) $m[1];
            if ($ke < 1 || $ke > self::JABATAN_MAKSIMAL) return null;

            return ['kunci' => $kunci, 'jenis' => 'jabatan', 'label' => 'Jabatan ' . $ke,
                'urutan' => self::urutanJabatan($ke), 'berlabel' => true];
        }

        return null;
    }

    /**
     * Petakan komponen yang ada ke slot, dengan jumlah slot jabatan yang bisa
     * tumbuh. Sebelumnya slotnya dipatok lima: guru dengan jabatan ke-4 tidak
     * pernah melihat komponen itu di layar DAN kehilangannya begitu tombol
     * simpan ditekan, karena penyimpanan hanya menulis slot yang terdefinisi.
     *
     * @param  int|null  $jumlahJabatan  dipaksa seragam untuk seluruh tabel,
     *         supaya kolomnya sejajar antar guru.
     */
    private function petakanSlot($komponen, ?int $jumlahJabatan = null): array
    {
        $jabatan = $komponen->where('jenis', 'jabatan')->sortBy('urutan')->values();
        $out = [];

        foreach (self::slotDefinisi($jumlahJabatan ?? $jabatan->count()) as $def) {
            $isi = $def['jenis'] === 'jabatan'
                ? $jabatan->get((int) filter_var($def['kunci'], FILTER_SANITIZE_NUMBER_INT) - 1)
                : $komponen->firstWhere('jenis', $def['jenis']);

            $out[] = [
                'kunci'    => $def['kunci'],
                'label'    => $def['label'],
                'berlabel' => $def['berlabel'],
                'nominal'  => $isi ? (float) $isi->nominal : 0.0,
                'isi_label'=> $isi?->label,
            ];
        }

        return $out;
    }

    public function index(Request $request)
    {
        [$bBerjalan, $tBerjalan] = KinerjaCalculationService::periodeBerjalan();
        $bulan = (int) ($request->bulan ?? $bBerjalan);
        $tahun = (int) ($request->tahun ?? $tBerjalan);

        $periode = PeriodePenggajian::untukLabel($bulan, $tahun);
        // Acuan tanggal = akhir periode, sama dengan yang dipakai payroll.
        $acuan = $periode?->tanggal_selesai?->toDateString()
            ?? TimezoneHelper::today()->toDateString();

        $cari = trim((string) $request->input('q', ''));
        $saring = (string) $request->input('saring', '');   // '' | perlu_ditinjau | belum_ada

        // Slip periode itu dipakai untuk kolom kehadiran & inval: angkanya
        // snapshot hasil generate, jadi tidak perlu menghitung ulang 52 guru
        // setiap halaman dibuka.
        $slip = $periode
            ? Penggajian::where('periode_penggajian_id', $periode->id)->get()->keyBy('tenaga_pendidik_id')
            : collect();

        $daftar = TenagaPendidik::aktif()->with(['user', 'jabatan', 'komponenGajiPokok'])->get();

        // Kolom tabel harus sejajar antar guru, jadi jumlah slot jabatannya
        // diambil dari yang TERBANYAK (minimal 3). Tanpa ini guru berjabatan 4
        // akan punya kolom lebih dan tabelnya melenceng.
        $maksJabatan = (int) $daftar
            ->map(fn ($g) => $g->komponenGajiPokokAktif($acuan)->where('jenis', 'jabatan')->count())
            ->max();

        $guru = $daftar
            ->map(function ($g) use ($acuan, $slip, $maksJabatan) {
                $komponen = $g->komponenGajiPokokAktif($acuan);
                $s = $slip->get($g->id);

                return [
                    'id'             => $g->id,
                    'nama'           => $g->user->name,
                    'jabatan_utama'  => $g->jabatan?->nama_jabatan ?? '—',
                    'slot'           => $this->petakanSlot($komponen, $maksJabatan),
                    'total'          => (float) $komponen->sum('nominal'),
                    // Bila komponen belum ada, gaji pokok masih dari cara lama.
                    'ada_komponen'   => $komponen->isNotEmpty(),
                    'gaji_pokok_berlaku' => (float) $g->getGajiPokokAktif($acuan),
                    'perlu_ditinjau' => $komponen->contains('perlu_ditinjau', true),
                    'catatan'        => $komponen->firstWhere('catatan', '!=', null)?->catatan,
                    // Yang menambah DI ATAS gaji pokok pada periode itu.
                    'hadir'          => $s?->total_hadir,
                    'hari_kerja'     => $s?->total_hari_kerja,
                    'alfa'           => $s?->total_alfa,
                    'terlambat'      => $s?->total_terlambat,
                    'jp_pengganti'   => $s?->total_jp_pengganti,
                    'vakasi_hadir'   => $s ? (float) $s->vakasi_absen_harian : null,
                    'vakasi_mengajar'=> $s ? (float) $s->vakasi_mengajar : null,
                    'gaji_bersih'    => $s ? (float) $s->gaji_bersih : null,
                    'slip_ada'       => $s !== null,
                ];
            })
            ->when($cari !== '', fn ($c) => $c->filter(
                fn ($x) => str_contains(mb_strtolower($x['nama']), mb_strtolower($cari))))
            ->when($saring === 'perlu_ditinjau', fn ($c) => $c->filter(fn ($x) => $x['perlu_ditinjau']))
            ->when($saring === 'belum_ada', fn ($c) => $c->filter(fn ($x) => !$x['ada_komponen']))
            ->sortBy('nama')->values();

        return Inertia::render('Admin/SmartPayroll/GajiPokok/Index', [
            'guru'    => $guru,
            'slotDef'          => self::slotDefinisi($maksJabatan),
            // Layar boleh menambah slot jabatan sampai batas ini.
            'jabatanMaksimal'  => self::JABATAN_MAKSIMAL,
            'periode' => [
                'bulan'     => $bulan,
                'tahun'     => $tahun,
                'label'     => $periode?->nama_bulan
                    ?? \Carbon\Carbon::create($tahun, $bulan, 1)->locale('id')->isoFormat('MMMM YYYY'),
                'dari'      => $periode?->tanggal_mulai?->format('d M Y'),
                'sampai'    => $periode?->tanggal_selesai?->format('d M Y'),
                'acuan'     => $acuan,
                'berjalan'  => $bulan === $bBerjalan && $tahun === $tBerjalan,
                'ada_slip'  => $slip->isNotEmpty(),
            ],
            'periodeOpsi' => PeriodePenggajian::orderByDesc('tahun')->orderByDesc('bulan')
                ->limit(12)->get()
                ->filter(fn ($p) => $p->tanggal_mulai && $p->tanggal_selesai)
                ->map(fn ($p) => [
                    'bulan' => (int) $p->bulan, 'tahun' => (int) $p->tahun,
                    'label' => $p->nama_bulan,
                    'dari'  => $p->tanggal_mulai->format('d M Y'),
                    'sampai'=> $p->tanggal_selesai->format('d M Y'),
                ])->values()->all(),
            'ringkasan' => [
                'guru'           => $guru->count(),
                'ada_komponen'   => $guru->where('ada_komponen', true)->count(),
                'perlu_ditinjau' => $guru->where('perlu_ditinjau', true)->count(),
                'total_gaji_pokok' => (float) $guru->sum('gaji_pokok_berlaku'),
            ],
            // Katalog nominal baku untuk mengisi formulir per guru.
            'variabel' => $this->katalogVariabel(),
            'filters' => ['bulan' => $bulan, 'tahun' => $tahun, 'q' => $cari, 'saring' => $saring],
        ]);
    }

    /**
     * Katalog variabel, dikelompokkan per jenis untuk dipakai formulir.
     *
     * Jam mengajar sengaja TIDAK berkatalog: 18 nilai berbeda karena mengikuti
     * JP masing-masing orang, jadi nominal baku tidak bermakna di sana.
     */
    private function katalogVariabel(): array
    {
        return VariabelGajiPokok::aktif()
            ->orderBy('jenis')->orderBy('urutan')->orderBy('nominal')
            ->get(['id', 'jenis', 'label', 'nominal'])
            ->groupBy('jenis')
            ->map(fn ($g) => $g->map(fn ($v) => [
                'id' => $v->id, 'label' => $v->label, 'nominal' => (float) $v->nominal,
            ])->values())
            ->toArray();
    }

    /** Tambah atau ubah satu variabel katalog. */
    public function variabelSimpan(Request $request)
    {
        $data = $request->validate([
            'id'      => 'nullable|integer|exists:variabel_gaji_pokok,id',
            'jenis'   => 'required|in:jabatan,tunjangan_pendidikan',
            'label'   => 'required|string|max:150',
            'nominal' => 'required|numeric|min:0|max:100000000',
            'urutan'  => 'nullable|integer|min:0|max:9999',
            'catatan' => 'nullable|string|max:300',
        ], [], ['label' => 'nama variabel']);

        $label = trim($data['label']);

        // Satu label per jenis: katalog ganda hanya membingungkan pengisi.
        $bentrok = VariabelGajiPokok::where('jenis', $data['jenis'])
            ->where('label', $label)
            ->when(!empty($data['id']), fn ($q) => $q->where('id', '!=', $data['id']))
            ->exists();
        if ($bentrok) {
            return back()->with('error', "Variabel \"{$label}\" sudah ada pada jenis itu.");
        }

        $isi = [
            'jenis'           => $data['jenis'],
            'label'           => $label,
            'nominal'         => (float) $data['nominal'],
            'urutan'          => (int) ($data['urutan'] ?? 0),
            'catatan'         => $data['catatan'] ?? null,
            'ditetapkan_oleh' => Auth::id(),
        ];

        if (!empty($data['id'])) {
            VariabelGajiPokok::findOrFail($data['id'])->update($isi);
            $pesan = "Variabel \"{$label}\" diperbarui.";
        } else {
            VariabelGajiPokok::create($isi + ['is_aktif' => true]);
            $pesan = "Variabel \"{$label}\" ditambahkan.";
        }

        // Ditegaskan supaya tidak ada yang menyangka gaji orang ikut bergeser.
        return back()->with('success', $pesan
            . ' Katalog ini hanya mengisi formulir — gaji pokok guru yang sudah ditetapkan tidak berubah.');
    }

    /** Nonaktifkan atau hapus satu variabel katalog. */
    public function variabelHapus(VariabelGajiPokok $variabel)
    {
        $label = $variabel->label;
        $variabel->delete();

        return back()->with('success', "Variabel \"{$label}\" dihapus dari katalog. "
            . 'Komponen guru yang memakai nominal itu tidak berubah.');
    }

    /**
     * Simpan lima slot sekaligus untuk SATU guru.
     *
     * Komponen lama ditutup masa berlakunya, bukan dihapus: riwayat nominal
     * harus tetap bisa dibaca saat slip periode lampau dihitung ulang.
     */
    public function simpan(TenagaPendidik $tenagaPendidik, Request $request)
    {
        $data = $request->validate([
            'berlaku_mulai'           => 'required|date',
            'slot'                    => 'required|array',
            // Kunci dibatasi pola: tanpa ini kiriman bisa mengarang slot.
            'slot.*.kunci'            => ['required', 'string',
                                          'regex:/^(jam_mengajar|tunjangan_pendidikan|jabatan_\d{1,2})$/'],
            'slot.*.nominal'          => 'nullable|numeric|min:0|max:100000000',
            'slot.*.label'            => 'nullable|string|max:150',
            'catatan'                 => 'nullable|string|max:300',
        ], [], ['slot.*.nominal' => 'nominal']);

        $mulai = \Carbon\Carbon::parse($data['berlaku_mulai'])->toDateString();

        // Periode yang slipnya sudah difinalisasi/dibayar tidak boleh digeser
        // dari sini: uang yang sudah keluar bukan urusan layar penetapan.
        $terkunci = Penggajian::whereHas('periodePenggajian', fn ($q) =>
                $q->whereDate('tanggal_selesai', '>=', $mulai))
            ->where(fn ($q) => $q->where('status', 'dibayar')
                ->orWhereNotNull('dibayar_pada')->orWhereNotNull('difinalisasi_oleh'))
            ->count();
        if ($terkunci > 0) {
            return back()->with('error',
                "Tidak bisa disimpan: ada {$terkunci} slip pada periode sejak {$mulai} yang sudah "
                . 'difinalisasi atau dibayar. Gaji yang sudah keluar tidak boleh berubah dari sini.');
        }

        $aktor = Auth::id();
        $sebelum = (float) $tenagaPendidik->getGajiPokokAktif($mulai);

        DB::transaction(function () use ($tenagaPendidik, $data, $mulai, $aktor) {
            $sebelumnya = \Carbon\Carbon::parse($mulai)->subDay()->toDateString();

            // Tutup yang masih berlaku pada/melewati tanggal itu.
            $tenagaPendidik->komponenGajiPokok()
                ->whereDate('berlaku_mulai', '<=', $mulai)
                ->where(fn ($q) => $q->whereNull('berlaku_selesai')
                    ->orWhereDate('berlaku_selesai', '>=', $mulai))
                ->each(function ($k) use ($mulai, $sebelumnya) {
                    // Baris yang mulainya SAMA dengan tanggal baru tidak punya masa
                    // hidup lagi, jadi dibuang; sisanya ditutup sehari sebelumnya.
                    $k->berlaku_mulai->toDateString() === $mulai
                        ? $k->delete()
                        : $k->update(['berlaku_selesai' => $sebelumnya]);
                });

            // Yang ditulis adalah slot yang DIKIRIM formulir, bukan daftar tetap,
            // supaya jabatan ke-4 dan seterusnya ikut tersimpan. Kunci kembar
            // dibuang agar satu slot tidak menghasilkan dua baris.
            $dikirim = collect($data['slot'])->unique('kunci')->values();

            foreach ($dikirim as $isi) {
                $def = self::definisiDariKunci((string) $isi['kunci']);
                if (! $def) continue;      // kunci di luar pola — abaikan

                $nominal = (float) ($isi['nominal'] ?? 0);
                $label = $def['berlabel'] ? trim((string) ($isi['label'] ?? '')) : null;

                // Slot kosong tidak disimpan: "tidak punya jabatan 3" lebih jujur
                // sebagai tidak ada barisnya daripada baris bernominal nol.
                if ($nominal <= 0 && $label === '') continue;
                if ($nominal <= 0 && !$def['berlabel']) continue;

                KomponenGajiPokok::create([
                    'tenaga_pendidik_id' => $tenagaPendidik->id,
                    'jenis'              => $def['jenis'],
                    'label'              => $def['berlabel'] ? ($label ?: $def['label']) : null,
                    'nominal'            => $nominal,
                    'urutan'             => $def['urutan'],
                    'berlaku_mulai'      => $mulai,
                    // Disimpan dari layar berarti sudah ditinjau orang.
                    'perlu_ditinjau'     => false,
                    'catatan'            => $data['catatan'] ?? null,
                    'ditetapkan_oleh'    => $aktor,
                ]);
            }
        });

        $sesudah = (float) $tenagaPendidik->fresh()->getGajiPokokAktif($mulai);
        $rp = fn ($n) => number_format($n, 0, ',', '.');

        $pesan = "Gaji pokok {$tenagaPendidik->user->name} ditetapkan: Rp {$rp($sesudah)}";
        if (abs($sesudah - $sebelum) >= 1) {
            $pesan .= ' (sebelumnya Rp ' . $rp($sebelum) . ')';
        }
        $pesan .= '. Berlaku sejak ' . \Carbon\Carbon::parse($mulai)->format('d M Y')
            . '. Slip periode itu perlu di-generate ulang agar angkanya ikut.';

        return back()->with('success', $pesan);
    }
}

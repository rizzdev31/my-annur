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
    /** Lima slot tetap, urut seperti berkas asalnya. */
    private const SLOT = [
        ['kunci' => 'jam_mengajar',         'jenis' => 'jam_mengajar',         'label' => 'Jam Mengajar',          'urutan' => 1, 'berlabel' => false],
        ['kunci' => 'jabatan_1',            'jenis' => 'jabatan',              'label' => 'Jabatan 1',             'urutan' => 2, 'berlabel' => true],
        ['kunci' => 'tunjangan_pendidikan', 'jenis' => 'tunjangan_pendidikan', 'label' => 'Tunjangan Pendidikan',  'urutan' => 3, 'berlabel' => false],
        ['kunci' => 'jabatan_2',            'jenis' => 'jabatan',              'label' => 'Jabatan 2',             'urutan' => 4, 'berlabel' => true],
        ['kunci' => 'jabatan_3',            'jenis' => 'jabatan',              'label' => 'Jabatan 3',             'urutan' => 5, 'berlabel' => true],
    ];

    /**
     * Petakan komponen yang ada ke LIMA slot tetap, supaya layar selalu punya
     * bentuk yang sama walau gurunya hanya punya satu komponen.
     */
    private function petakanSlot($komponen): array
    {
        $jabatan = $komponen->where('jenis', 'jabatan')->sortBy('urutan')->values();
        $out = [];

        foreach (self::SLOT as $def) {
            $isi = null;

            if ($def['jenis'] === 'jabatan') {
                // Slot jabatan 1/2/3 diisi urut dari jabatan yang ada.
                $ke = (int) filter_var($def['kunci'], FILTER_SANITIZE_NUMBER_INT) - 1;
                $isi = $jabatan->get($ke);
            } else {
                $isi = $komponen->firstWhere('jenis', $def['jenis']);
            }

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

        $guru = TenagaPendidik::aktif()
            ->with(['user', 'jabatan', 'komponenGajiPokok'])
            ->get()
            ->map(function ($g) use ($acuan, $slip) {
                $komponen = $g->komponenGajiPokokAktif($acuan);
                $s = $slip->get($g->id);

                return [
                    'id'             => $g->id,
                    'nama'           => $g->user->name,
                    'jabatan_utama'  => $g->jabatan?->nama_jabatan ?? '—',
                    'slot'           => $this->petakanSlot($komponen),
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
            'slotDef' => self::SLOT,
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
            'slot.*.kunci'            => 'required|string',
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

            foreach (self::SLOT as $def) {
                $isi = collect($data['slot'])->firstWhere('kunci', $def['kunci']);
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

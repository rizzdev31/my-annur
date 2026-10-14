<?php

namespace App\Http\Controllers\Superadmin\Education;

use App\Http\Controllers\Controller;
use App\Models\TugasTasmi;
use App\Models\TugasTasnif;
use App\Services\TahfidzService;
use App\Services\TahsinService;
use App\Services\TasmiService;
use App\Services\TasnifService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * HASIL UJIAN QUR'AN (tasmi' & tasnif) — pemantauan & kendali superadmin.
 *
 * Sebelumnya hasil ujian hanya terlihat di PWA guru (per penguji) dan di halaman
 * monitoring per santri. Superadmin tidak punya satu tempat untuk melihat
 * SELURUH hasil, menilai mutu penilaiannya, maupun menanggapi — padahal dialah
 * yang memantau. Halaman ini menyatukan keduanya dalam satu daftar, lengkap
 * dengan rincian rubrik, masukan pimpinan, pembatalan, dan tautan sertifikat.
 */
class HasilUjianQuranController extends Controller
{
    public function index(Request $request)
    {
        $tipe   = $request->tipe ?: 'semua';          // semua | tasmi | tasnif
        $status = $request->status ?: 'selesai';      // selesai | ditugaskan | dibatalkan | semua
        $hasil  = $request->hasil ?: 'semua';         // semua | lulus | belum
        $cari   = trim((string) $request->cari);

        $baris = collect();

        if ($tipe !== 'tasnif') {
            $baris = $baris->concat(
                TugasTasmi::with(['santri:id,nama_lengkap,nip', 'penguji.user:id,name',
                                  'pengampu.user:id,name', 'masukanOleh:id,name', 'dibatalkanOleh:id,name'])
                    ->when($status !== 'semua', fn ($q) => $q->where('status', $status))
                    ->when($hasil === 'lulus', fn ($q) => $q->where('lulus', true))
                    ->when($hasil === 'belum', fn ($q) => $q->where('lulus', false))
                    ->when($cari !== '', fn ($q) => $q->whereHas('santri',
                        fn ($s) => $s->where('nama_lengkap', 'like', "%$cari%")->orWhere('nip', 'like', "%$cari%")))
                    ->get()->map(fn ($t) => $this->baris($t, 'tasmi'))
            );
        }

        if ($tipe !== 'tasmi') {
            $baris = $baris->concat(
                TugasTasnif::with(['santri:id,nama_lengkap,nip', 'penguji.user:id,name',
                                   'pengampu.user:id,name', 'masukanOleh:id,name', 'dibatalkanOleh:id,name'])
                    ->when($status !== 'semua', fn ($q) => $q->where('status', $status))
                    ->when($hasil === 'lulus', fn ($q) => $q->where('lulus', true))
                    ->when($hasil === 'belum', fn ($q) => $q->where('lulus', false))
                    ->when($cari !== '', fn ($q) => $q->whereHas('santri',
                        fn ($s) => $s->where('nama_lengkap', 'like', "%$cari%")->orWhere('nip', 'like', "%$cari%")))
                    ->get()->map(fn ($t) => $this->baris($t, 'tasnif'))
            );
        }

        $baris = $baris->sortByDesc('waktu_urut')->values();

        return Inertia::render('Admin/SmartEducation/HasilUjianQuran/Index', [
            'hasilUjian' => $baris,
            'filter'     => ['tipe' => $tipe, 'status' => $status, 'hasil' => $hasil, 'cari' => $cari],
            'summary'    => [
                'total'      => $baris->count(),
                'lulus'      => $baris->where('status', 'selesai')->where('lulus', true)->count(),
                'belum_lulus'=> $baris->where('status', 'selesai')->where('lulus', false)->count(),
                'menunggu'   => $baris->where('status', 'ditugaskan')->count(),
                'dibatalkan' => $baris->where('status', 'dibatalkan')->count(),
                // Yang paling dicari superadmin: hasil yang belum ditanggapi.
                'tanpa_masukan' => $baris->where('status', 'selesai')->whereNull('masukan')->count(),
            ],
        ]);
    }

    /** Satu baris daftar, bentuknya sama untuk tasmi' & tasnif. */
    private function baris($t, string $jenis): array
    {
        $nilai = $t->nilai !== null ? (float) $t->nilai : null;
        $ambang = $jenis === 'tasmi' ? TahfidzService::NILAI_LULUS_TASMI : TasnifService::NILAI_LULUS;

        $rubrik = $jenis === 'tasmi'
            ? [
                ['label' => 'Kelancaran',       'nilai' => $t->nilai_kelancaran],
                ['label' => 'Makhorijul Huruf', 'nilai' => $t->nilai_makhorijul_huruf],
                ['label' => 'Tajwid',           'nilai' => $t->nilai_tajwid],
                ['label' => 'Fashohah',         'nilai' => $t->nilai_fashohah],
            ]
            : [
                ['label' => 'Pemahaman Materi', 'nilai' => $t->nilai_pemahaman_materi],
                ['label' => 'Kelancaran',       'nilai' => $t->nilai_kelancaran],
                ['label' => 'Fashohah',         'nilai' => $t->nilai_fashohah],
                ['label' => 'Makhorijul Huruf', 'nilai' => $t->nilai_makhorijul_huruf],
            ];

        return [
            'id'        => $t->id,
            'jenis'     => $jenis,
            'santri_id' => $t->santri_id,
            'santri'    => $t->santri?->nama_lengkap ?? '—',
            'nip'       => $t->santri?->nip,
            'capaian'   => $jenis === 'tasmi' ? "Juz {$t->juz}" : TahsinService::levelLabel((int) $t->level),
            'juz'       => $jenis === 'tasmi' ? $t->juz : null,
            'level'     => $jenis === 'tasnif' ? $t->level : null,
            'status'    => $t->status,
            'nilai'     => $nilai,
            'lulus'     => $t->lulus === null ? null : (bool) $t->lulus,
            'ambang'    => $ambang,
            // Penanda mutu: lulus tetapi ada komponen di bawah ambang — pola yang
            // tidak terlihat bila hanya melihat rata-rata.
            'ada_rubrik_rendah' => collect($rubrik)->contains(
                fn ($r) => $r['nilai'] !== null && (float) $r['nilai'] < $ambang),
            'rubrik'    => $rubrik,
            'rubrik_ada'=> $t->nilai_kelancaran !== null,
            'penguji'   => $t->penguji?->user?->name ?? '—',
            'pengampu'  => $t->pengampu?->user?->name ?? '—',
            'catatan'   => $t->catatan,
            'masukan'   => $t->masukan_pimpinan,
            'masukan_oleh' => $t->masukanOleh?->name,
            'masukan_pada' => $t->masukan_pada?->format('d M Y H:i'),
            'alasan_batal' => $t->alasan_batal,
            'dibatalkan_oleh' => $t->dibatalkanOleh?->name,
            'tanggal'   => ($t->updated_at ?? $t->created_at)?->format('d M Y'),
            'waktu_urut'=> ($t->updated_at ?? $t->created_at)?->timestamp ?? 0,
            'boleh_sertifikat' => $t->status === 'selesai' && (bool) $t->lulus,
        ];
    }

    /** Masukan pimpinan atas satu hasil ujian. */
    public function beriMasukan(Request $request, string $jenis, int $id)
    {
        $d = $request->validate([
            'masukan' => 'nullable|string|max:1000',
        ], ['masukan.max' => 'Masukan terlalu panjang (maks 1.000 karakter).']);

        try {
            $jenis === 'tasnif'
                ? app(TasnifService::class)->beriMasukan($id, Auth::id(), $d['masukan'] ?? null)
                : app(TasmiService::class)->beriMasukan($id, Auth::id(), $d['masukan'] ?? null);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', filled($d['masukan'] ?? null)
            ? 'Masukan pimpinan disimpan dan akan tampil di halaman nilai sertifikat.'
            : 'Masukan pimpinan dihapus.');
    }

    /** Batalkan penunjukan/hasil — memakai gerbang yang sama dengan halaman monitoring. */
    public function batalkan(Request $request, string $jenis, int $id)
    {
        $d = $request->validate(
            ['alasan' => 'required|string|max:300'],
            ['alasan.required' => 'Alasan pembatalan wajib diisi.']
        );

        try {
            $jenis === 'tasnif'
                ? app(TasnifService::class)->batalkanTugas($id, Auth::id(), $d['alasan'])
                : app(TasmiService::class)->batalkanTugas($id, Auth::id(), $d['alasan']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Penunjukan/hasil dibatalkan. Vakasi pengujinya tidak akan terbayar.');
    }

    /** Sertifikat 2 halaman (depan: nama, belakang: nilai) untuk dicetak admin. */
    public function sertifikat(string $jenis, int $id)
    {
        $t = $jenis === 'tasnif'
            ? TugasTasnif::with(['santri:id,nama_lengkap,nip', 'penguji.user:id,name',
                                 'pengampu.user:id,name', 'masukanOleh:id,name'])->findOrFail($id)
            : TugasTasmi::with(['santri:id,nama_lengkap,nip', 'penguji.user:id,name',
                                'pengampu.user:id,name', 'masukanOleh:id,name'])->findOrFail($id);

        if ($t->status !== 'selesai' || !$t->lulus) {
            return back()->with('error', 'Sertifikat hanya untuk hasil yang sudah dinilai dan LULUS.');
        }

        $nilai = (float) $t->nilai;
        $predikat = $nilai >= 9.5 ? 'Mumtaz (Istimewa)'
            : ($nilai >= 9 ? 'Jayyid Jiddan (Sangat Baik)'
            : ($nilai >= 8 ? 'Jayyid (Baik)' : 'Maqbul'));
        $tgl = $t->updated_at ?? now();

        $data = [
            'nomor' => $jenis === 'tasnif'
                ? sprintf('%03d/TASNIF-L%d/AN-NUR/%s', $t->id, $t->level, $tgl->format('Y'))
                : sprintf('%03d/TASMI-%d/AN-NUR/%s', $t->id, $t->juz, $tgl->format('Y')),
            'santri'   => ['nama' => $t->santri?->nama_lengkap ?? '—', 'nip' => $t->santri?->nip],
            'nilai'    => $nilai,
            'predikat' => $predikat,
            'rubrik'   => $this->baris($t, $jenis)['rubrik'],
            'rubrik_ada' => $t->nilai_kelancaran !== null,
            'penguji'  => $t->penguji?->user?->name ?? '—',
            'pengampu' => $t->pengampu?->user?->name ?? '—',
            'catatan'  => $t->catatan,
            'masukan'      => $t->masukan_pimpinan,
            'masukan_oleh' => $t->masukanOleh?->name,
            'ambang'   => $jenis === 'tasnif' ? TasnifService::NILAI_LULUS : TahfidzService::NILAI_LULUS_TASMI,
            'tanggal'  => $tgl->locale('id')->isoFormat('D MMMM YYYY'),
            'lembaga'  => 'Pondok Pesantren An Nur Sidoarjo',
            'program'  => $jenis === 'tasnif' ? "Tahsinul Qur'an" : "Tahfizhul Qur'an",
            'alamat'   => 'Jalan KH Ahmad Dahlan, Penatarsewu, Tanggulangin, Sidoarjo, Jawa Timur',
        ];

        if ($jenis === 'tasnif') {
            $data['level'] = $t->level;
            $data['level_label'] = TahsinService::levelLabel((int) $t->level);
        } else {
            $data['juz'] = $t->juz;
        }

        return Inertia::render('Admin/SmartEducation/HasilUjianQuran/Sertifikat', [
            'sertifikat' => $data,
            'jenis'      => $jenis,
        ]);
    }
}

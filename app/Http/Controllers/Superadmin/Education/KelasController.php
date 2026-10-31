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
            // Hitung anggota yang MASIH aktif — baris riwayat tidak boleh ikut.
            ->withCount(['santri as santri_count' => fn ($q) => $q->where('kelas_santri.is_aktif', true), 'jadwalMengajar'])
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
                'total'     => Kelas::count(),
                'sekolah'   => Kelas::sekolah()->count(),
                'pesantren' => Kelas::pesantren()->count(),
                'tahfidz'   => Kelas::tahfidz()->count(),
                'tahsin'    => Kelas::tahsin()->count(),
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

    /** Aktifkan kembali kelas yang dinonaktifkan. */
    public function aktifkan(Kelas $kelas)
    {
        $kelas->update(['is_aktif' => true]);
        return back()->with('success', "Kelas {$kelas->nama} diaktifkan.");
    }

    /** GET kelas/{kelas}/santri — daftar santri AKTIF di kelas (untuk UI kenaikan kelas). */
    public function santriKelas(Kelas $kelas)
    {
        $santri = $kelas->santri()->wherePivot('is_aktif', true)->orderBy('nama_lengkap')
            ->get(['santri.id', 'nip', 'nama_lengkap'])
            ->map(fn($s) => ['id' => $s->id, 'nip' => $s->nip, 'nama' => $s->nama_lengkap]);
        return response()->json(['success' => true, 'data' => $santri]);
    }

    /**
     * GET kelas/{kelas}/atur-santri — data panel centang.
     * Semua santri aktif + status keanggotaan di kelas ini + kelas mereka saat ini
     * di slot yang sama (agar admin tahu siapa yang akan dipindah).
     */
    public function aturSantriData(Kelas $kelas)
    {
        $slot = $kelas->jenisSeslot();

        $kelasSlot = \Illuminate\Support\Facades\DB::table('kelas_santri')
            ->join('kelas', 'kelas.id', '=', 'kelas_santri.kelas_id')
            ->where('kelas_santri.is_aktif', true)->whereIn('kelas.jenis', $slot)
            // jenis & tingkat ikut: panel perlu tahu apakah mencentang santri ini
            // berarti NAIK/TURUN kelas, bukan sekadar pindah.
            ->get(['kelas_santri.santri_id', 'kelas.id', 'kelas.nama', 'kelas.jenis', 'kelas.tingkat'])
            ->keyBy('santri_id');

        // Pencapaian yang DIBAWA santri — ditampilkan di konfirmasi agar admin
        // melihat bahwa pindah kelas tidak mengubahnya.
        $hafalan = \App\Models\HafalanSantri::pluck('total_ayat', 'santri_id');
        $progresTahsin = \App\Models\TahsinPenilaian::distinct()->pluck('santri_id')->flip();

        $santri = \App\Models\Santri::aktif()->orderBy('nama_lengkap')
            ->get(['id', 'nip', 'nama_lengkap', 'jenis_kelamin', 'tahsin_level'])
            ->map(function ($s) use ($kelasSlot, $kelas, $hafalan, $progresTahsin) {
                $k = $kelasSlot->get($s->id);
                return [
                    'id'            => $s->id,
                    'nip'           => $s->nip,
                    'nama'          => $s->nama_lengkap,
                    'jenis_kelamin' => $s->jenis_kelamin,
                    'anggota'       => $k && (int) $k->id === $kelas->id,
                    // Kelas lain di slot yang sama → akan DIPINDAH bila dicentang.
                    'kelas_lain'         => $k && (int) $k->id !== $kelas->id ? $k->nama : null,
                    'kelas_lain_jenis'   => $k && (int) $k->id !== $kelas->id ? $k->jenis : null,
                    'kelas_lain_tingkat' => $k && (int) $k->id !== $kelas->id ? $k->tingkat : null,
                    'hafalan_ayat'  => (int) ($hafalan[$s->id] ?? 0),
                    'tahsin_level'  => $s->tahsin_level,
                    'progres_tahsin'=> $progresTahsin->has($s->id),
                ];
            })->values();

        // Kelas Putra/Putri → saran filter jenis kelamin.
        $namaKecil = mb_strtolower($kelas->nama);
        $saranJk = str_contains($namaKecil, 'putri') ? 'P' : (str_contains($namaKecil, 'putra') ? 'L' : null);

        return response()->json(['success' => true, 'data' => [
            'kelas' => [
                'id' => $kelas->id, 'nama' => $kelas->nama, 'jenis' => $kelas->jenis,
                'tingkat' => $kelas->tingkat,
                'level_tahsin' => $kelas->level_tahsin,
                'slot_label' => $kelas->slotLabel(),
            ],
            'saran_jk' => $saranJk,
            'santri'   => $santri,
        ]]);
    }

    /** POST kelas/{kelas}/atur-santri — simpan anggota kelas hasil centang. */
    public function aturSantri(Request $request, Kelas $kelas)
    {
        $d = $request->validate([
            'santri_ids'   => 'present|array',
            'santri_ids.*' => 'integer|exists:santri,id',
            'tanggal'      => 'nullable|date_format:Y-m-d',
        ]);

        if (!$kelas->is_aktif) {
            return back()->with('error', "Kelas {$kelas->nama} nonaktif — aktifkan dulu sebelum mengatur santri.");
        }

        $keanggotaan = app(\App\Services\KenaikanKelasService::class);
        $hasil = $keanggotaan
            ->aturAnggota($kelas, $d['santri_ids'], $d['tanggal'] ?? null);

        $masuk   = count($hasil['masuk']);
        $pindah  = collect($hasil['masuk'])->whereNotNull('dari')->count();
        $keluar  = count($hasil['keluar']);

        if (!$masuk && !$keluar) {
            return back()->with('success', "Tidak ada perubahan santri di {$kelas->nama}.");
        }

        $pesan = "Santri {$kelas->nama} diperbarui: {$masuk} masuk"
            . ($pindah ? " ({$pindah} dipindah dari kelas lain)" : '')
            . ", {$keluar} keluar."
            . (in_array($kelas->jenis, \App\Services\KenaikanKelasService::JENIS_DIKABARI, true) ? ' Pengampu sudah dikabari.' : '');

        // Kenaikan level tahsin mengubah MATERI yang akan dinilai, jadi tidak
        // boleh terjadi tanpa diberitahukan; yang tertahan disebut namanya.
        if ($lvl = $keanggotaan->ringkasanLevelTahsin()) $pesan .= ' ' . $lvl;

        return back()->with('success', $pesan);
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
            $keanggotaan = app(\App\Services\KenaikanKelasService::class);
            $hasil = $keanggotaan->naikKelasMassal(
                $kelas, $tujuan, $d['kecuali'] ?? [], $d['tanggal'] ?? null
            );
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $pesan = "{$hasil['dipindah']} santri naik ke {$tujuan->nama}"
            . ($hasil['dilewati'] ? ", {$hasil['dilewati']} dikecualikan (tinggal kelas)" : '') . '.';
        if ($lvl = $keanggotaan->ringkasanLevelTahsin()) $pesan .= ' ' . $lvl;

        return back()->with('success', $pesan);
    }

    /**
     * GET kelas/perombakan — papan perombakan halaqoh.
     *
     * Satu layar memuat SELURUH santri pada satu kelompok kelas (tahfidz/tahsin,
     * sekolah, atau pesantren) beserta halaqoh/kelasnya saat ini, sehingga admin
     * bisa menetapkan tujuan santri satu per satu lalu menyimpannya sekali.
     *
     * Pencapaian tahfidz sengaja ikut ditampilkan: itulah dasar admin membagi
     * halaqoh, dan sekaligus bukti bahwa perpindahan tidak mengubahnya.
     */
    public function perombakan(Request $request)
    {
        $slotDipilih = in_array($request->slot, ['quran', 'sekolah', 'pesantren'], true)
            ? $request->slot : 'quran';
        $jenis = $slotDipilih === 'quran' ? ['tahfidz', 'tahsin'] : [$slotDipilih];

        $kelas = Kelas::whereIn('jenis', $jenis)
            ->withCount(['santri as santri_count' => fn ($q) => $q->where('kelas_santri.is_aktif', true)])
            ->orderBy('jenis')->orderBy('nama')->get()
            ->map(fn ($k) => [
                'id' => $k->id, 'nama' => $k->nama, 'jenis' => $k->jenis,
                'tingkat' => $k->tingkat,
                'level_tahsin' => $k->level_tahsin, 'is_aktif' => (bool) $k->is_aktif,
                'jumlah_santri' => $k->santri_count,
                'pengampu' => \App\Models\JadwalMengajar::where('kelas_id', $k->id)
                    ->where('is_aktif', true)->with('tenagaPendidik.user:id,name')->get()
                    ->map(fn ($j) => $j->tenagaPendidik?->user?->name)->filter()->unique()->values()->all(),
            ])->values();

        // Keanggotaan AKTIF pada slot ini → kelas santri sekarang.
        $anggota = \Illuminate\Support\Facades\DB::table('kelas_santri')
            ->join('kelas', 'kelas.id', '=', 'kelas_santri.kelas_id')
            ->where('kelas_santri.is_aktif', true)->whereIn('kelas.jenis', $jenis)
            // `jenis` & `tingkat` WAJIB ikut: papan perombakan memakainya untuk
            // membatasi tujuan ke kelas SEJAJAR. Lintas program (tahsin →
            // tahfidz) dan lintas tingkat (VII → VIII) keduanya naik kelas,
            // bukan pindah, dan ditolak servicenya.
            ->get(['kelas_santri.santri_id', 'kelas.id as kelas_id', 'kelas.nama',
                   'kelas.jenis', 'kelas.tingkat'])
            ->keyBy('santri_id');

        $hafalan   = \App\Models\HafalanSantri::pluck('total_ayat', 'santri_id');
        $juzSelesai = \App\Models\HafalanJuz::whereIn('status', ['selesai', 'tasmi_lulus'])
            ->selectRaw('santri_id, COUNT(*) n')->groupBy('santri_id')->pluck('n', 'santri_id');

        $santri = \App\Models\Santri::aktif()->orderBy('nama_lengkap')
            ->get(['id', 'nip', 'nama_lengkap', 'jenis_kelamin', 'tahsin_level'])
            ->map(function ($s) use ($anggota, $hafalan, $juzSelesai) {
                $a = $anggota->get($s->id);

                return [
                    'id' => $s->id, 'nip' => $s->nip, 'nama' => $s->nama_lengkap,
                    'jenis_kelamin' => $s->jenis_kelamin,
                    'kelas_id'    => $a ? (int) $a->kelas_id : null,
                    'kelas_nama'  => $a->nama ?? null,
                    'kelas_jenis' => $a->jenis ?? null,
                    'kelas_tingkat' => $a->tingkat ?? null,
                    'hafalan_ayat' => (int) ($hafalan[$s->id] ?? 0),
                    'juz_selesai'  => (int) ($juzSelesai[$s->id] ?? 0),
                    'tahsin_level' => $s->tahsin_level,
                ];
            })->values();

        return Inertia::render('Admin/SmartEducation/Kelas/Perombakan', [
            'slot'   => $slotDipilih,
            'kelas'  => $kelas,
            'santri' => $santri,
        ]);
    }

    /**
     * POST kelas/perombakan — simpan perombakan.
     *
     * Hanya santri yang disebut yang berpindah. Tidak ada yang dikeluarkan —
     * beda dengan panel centang per kelas, yang mengeluarkan siapa pun yang
     * tidak tercentang.
     */
    public function simpanPerombakan(Request $request)
    {
        $d = $request->validate([
            'pindah'              => 'required|array|min:1',
            'pindah.*.santri_id'  => 'required|integer|exists:santri,id',
            'pindah.*.kelas_id'   => 'required|integer|exists:kelas,id',
            'tanggal'             => 'nullable|date_format:Y-m-d',
            'keterangan'          => 'nullable|string|max:150',
        ], [
            'pindah.required' => 'Belum ada santri yang ditetapkan kelas tujuannya.',
        ]);

        $peta = collect($d['pindah'])->mapWithKeys(
            fn ($r) => [(int) $r['santri_id'] => (int) $r['kelas_id']]
        )->all();

        try {
            $keanggotaan = app(\App\Services\KenaikanKelasService::class);
            $hasil = $keanggotaan
                ->rombak($peta, $d['tanggal'] ?? null, $d['keterangan'] ?? null);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $n = count($hasil['dipindah']);
        $pesan = $n
            ? "$n santri dipindah." . (count($hasil['tetap']) ? ' ' . count($hasil['tetap']) . ' sudah di kelas tujuannya.' : '')
            : 'Tidak ada perpindahan — semua santri sudah berada di kelas tujuannya.';
        if ($hasil['gagal']) {
            $pesan .= ' ' . count($hasil['gagal']) . ' dilewati (santri tidak aktif).';
        }
        if ($n) {
            $pesan .= ' Pencapaian hafalan tidak berubah; pengampu sudah dikabari.';
        }
        if ($lvl = $keanggotaan->ringkasanLevelTahsin()) $pesan .= ' ' . $lvl;

        return back()->with($n || !$hasil['gagal'] ? 'success' : 'error', $pesan);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'nama'            => 'required|string|max:100',
            'nama_deskriptif' => 'nullable|string|max:100',
            'jenis'           => 'required|in:sekolah,pesantren,tahfidz,tahsin',
            'level_tahsin'    => 'nullable|integer|min:1|max:6',
            'tingkat'         => 'nullable|string|max:30',
            'tahun_ajaran_id' => 'nullable|exists:tahun_ajaran,id',
            'wali_kelas_id'   => 'nullable|exists:tenaga_pendidik,id',
        ]);
    }
}

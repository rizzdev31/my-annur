<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\JadwalMengajar;
use App\Models\Kelas;
use App\Models\TenagaPendidik;
use App\Models\MataPelajaran;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JadwalMengajarController extends Controller
{
    /**
     * Index utama — ditampilkan per guru, per hari, multi-slot.
     * Guru bisa punya banyak slot di hari yang sama.
     */
    public function index(Request $request)
    {
        // FIXED: TahunAjaran::aktif() adalah static method, return ?self langsung
        $tahunAjaran = TahunAjaran::aktif();

        // Ambil semua guru aktif yang punya jadwal
        $guruList = TenagaPendidik::aktif()
            ->with(['user', 'jabatan'])
            ->whereHas('jadwalMengajar', fn($q) => $q->aktif()
                ->when($tahunAjaran, fn($q2) => $q2->where('tahun_ajaran_id', $tahunAjaran->id))
            )
            ->orderBy('id')
            ->get()
            ->map(fn($g) => [
                'id'      => $g->id,
                'nama'    => $g->user->name,
                'jabatan' => $g->jabatan?->nama_jabatan ?? '—',
                'foto'    => $g->user->foto ? asset('storage/'.$g->user->foto) : null,
            ]);

        // Ambil semua jadwal, diformat untuk Vue
        $hariOrder = ['senin','selasa','rabu','kamis','jumat','sabtu','ahad'];

        $semuaJadwal = JadwalMengajar::bukanUjian()->with([
                'tenagaPendidik.user',
                'mataPelajaran',
                'tahunAjaran',
            ])
            ->aktif()
            ->when($tahunAjaran, fn($q) => $q->where('tahun_ajaran_id', $tahunAjaran->id))
            ->orderBy('tenaga_pendidik_id')
            ->orderByRaw("FIELD(hari, 'senin','selasa','rabu','kamis','jumat','sabtu','ahad')")
            ->orderBy('jam_mulai')
            ->get()
            ->map(fn($j) => [
                'id'              => $j->id,
                'guru_id'         => $j->tenaga_pendidik_id,
                'guru_nama'       => $j->tenagaPendidik->user->name,
                'mapel_id'        => $j->mata_pelajaran_id,
                'mapel_nama'      => $j->mataPelajaran?->nama ?? '—',
                'mapel_kode'      => $j->mataPelajaran?->kode ?? '',
                'mapel_kategori'  => $j->mataPelajaran?->kategori ?? '',
                'hari'            => $j->hari,
                'jam_mulai'       => $j->jam_mulai,
                'jam_selesai'     => $j->jam_selesai,
                'jumlah_jp'       => $j->jumlah_jp,
                'kelas'           => $j->kelas,
                'kelas_id'        => $j->kelas_id,
                'ruangan'         => $j->ruangan,
                'tahun_ajaran_id' => $j->tahun_ajaran_id,
            ]);

        // Statistik JP per guru per bulan (estimasi dari jumlah_jp × 4 minggu)
        $jpStats = $semuaJadwal
            ->groupBy('guru_id')
            ->map(fn($slots) => [
                'total_jp_minggu' => $slots->sum('jumlah_jp'),
                'total_jp_bulan'  => $slots->sum('jumlah_jp') * 4, // estimasi
                'total_slot'      => $slots->count(),
            ]);

        return Inertia::render('Admin/Master/JadwalMengajar/Index', [
            'jadwal'      => $semuaJadwal,
            'guruList'    => $guruList,
            'jpStats'     => $jpStats,
            'tahunAjaran' => $tahunAjaran ? [
                'id'    => $tahunAjaran->id,
                'nama'  => $tahunAjaran->nama,
                'label' => $tahunAjaran->label,
            ] : null,
            'semua_tahun' => TahunAjaran::orderByDesc('id')->get(['id', 'nama', 'semester']),
            // Hanya mapel REGULER di form ini. Tahfidz & Tahsin dijadwalkan lewat
            // generator Smart Tahfidz/Tahsin (kelas khusus), agar tak salah pasang ke kelas sekolah.
            'mapel'       => MataPelajaran::aktif()
                ->where(fn($q) => $q->where('tipe', 'reguler')->orWhereNull('tipe'))
                ->orderBy('tingkat')->orderBy('nama')
                ->get(['id', 'nama', 'kode', 'kategori', 'tingkat']),
            'guru'        => TenagaPendidik::aktif()->with(['user', 'jabatan'])
                ->get()->map(fn($g) => [
                    'id'      => $g->id,
                    'nama'    => $g->user->name,
                    'jabatan' => $g->jabatan?->nama_jabatan,
                ]),
            // Kelas Smart Education (sekolah & pesantren) untuk pemilihan kelas jadwal.
            'kelasList'   => Kelas::aktif()->reguler()->orderBy('nama')
                ->get(['id', 'nama', 'tahun_ajaran_id']),
        ]);
    }

    /**
     * Store slot jadwal baru — bisa multi-slot sehari.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'tahun_ajaran_id'    => 'required|exists:tahun_ajaran,id',
            'tenaga_pendidik_id' => 'required|exists:tenaga_pendidik,id',
            'mata_pelajaran_id'  => 'required|exists:mata_pelajaran,id',
            'hari'               => 'required|in:senin,selasa,rabu,kamis,jumat,sabtu,ahad',
            'jam_mulai'          => 'required|date_format:H:i',
            'jam_selesai'        => 'required|date_format:H:i|after:jam_mulai',
            'jumlah_jp'          => 'required|integer|min:1|max:20',
            'kelas_id'           => 'required|exists:kelas,id',
            'ruangan'            => 'nullable|string|max:50',
        ]);

        if ($err = $this->tolakProgramKhusus($data['mata_pelajaran_id'], $data['kelas_id'])) {
            return back()->with('error', $err);
        }

        if ($err = $this->alasanBentrok($data)) {
            return back()->with('error', $err);
        }

        // kelas_id (master Smart Education) = sumber kebenaran. String `kelas`
        // disinkronkan dari nama master untuk tampilan & kompatibilitas lama.
        $data['kelas'] = Kelas::find($data['kelas_id'])?->nama ?? '';

        JadwalMengajar::create(array_merge($data, ['is_aktif' => true]));

        return back()->with('success', 'Slot jadwal berhasil ditambahkan.');
    }

    /**
     * Update 1 slot jadwal.
     */
    public function update(Request $request, JadwalMengajar $jadwalMengajar)
    {
        $data = $request->validate([
            'tahun_ajaran_id'    => 'required|exists:tahun_ajaran,id',
            'tenaga_pendidik_id' => 'required|exists:tenaga_pendidik,id',
            'mata_pelajaran_id'  => 'required|exists:mata_pelajaran,id',
            'hari'               => 'required|in:senin,selasa,rabu,kamis,jumat,sabtu,ahad',
            'jam_mulai'          => 'required|date_format:H:i',
            'jam_selesai'        => 'required|date_format:H:i|after:jam_mulai',
            'jumlah_jp'          => 'required|integer|min:1|max:20',
            'kelas_id'           => 'required|exists:kelas,id',
            'ruangan'            => 'nullable|string|max:50',
        ]);

        if ($err = $this->tolakProgramKhusus($data['mata_pelajaran_id'], $data['kelas_id'])) {
            return back()->with('error', $err);
        }

        // Dulu update() TIDAK memeriksa bentrok sama sekali, padahal store()
        // memeriksa — penjaganya bisa dilewati hanya dengan membuat slot bersih
        // lalu menggeser jamnya lewat edit.
        if ($err = $this->alasanBentrok($data, $jadwalMengajar->id)) {
            return back()->with('error', $err);
        }

        // kelas_id = sumber kebenaran; sinkronkan string kelas untuk tampilan.
        $data['kelas'] = Kelas::find($data['kelas_id'])?->nama ?? $jadwalMengajar->kelas;

        $jadwalMengajar->update($data);

        return back()->with('success', 'Jadwal berhasil diperbarui.');
    }

    /**
     * Mengapa slot ini bentrok dengan jadwal guru yang sama (null = boleh).
     *
     * Overlap STRICT (interval setengah-terbuka): dua sesi bentrok HANYA bila
     *   jam_mulai < jam_selesai_baru  DAN  jam_selesai > jam_mulai_baru.
     * Sesi BERURUTAN (mis. 10:20–11:30 lalu 11:30–12:40) TIDAK bentrok, karena
     * batas yang bersinggungan tidak dihitung tumpang tindih.
     *
     * Form ini hanya memasang mapel REGULER (tahfidz & tahsin lewat generator
     * Smart Tahfidz/Tahsin), dan untuk kelas sekolah/pesantren guru benar-benar
     * harus berada di satu ruang — jadi tumpang tindih di sini ditolak mutlak.
     *
     * Halaqoh Qur'an berbeda: satu pengampu memang bisa membimbing beberapa
     * kelompok kecil berbeda level sekaligus dalam satu majelis (terbukti di
     * produksi 9 Okt 2026). Pola itu dibuat lewat generator tahfidz/tahsin yang
     * memang tidak memeriksa tumpang tindih — dan itu benar, jangan ditambahi
     * penjaga di sana.
     */
    private function alasanBentrok(array $d, ?int $kecualikanId = null): ?string
    {
        $query = JadwalMengajar::query()
            ->where('tenaga_pendidik_id', $d['tenaga_pendidik_id'])
            ->where('tahun_ajaran_id', $d['tahun_ajaran_id'])
            ->where('hari', $d['hari'])
            ->where('is_aktif', true)
            ->bukanUjian()
            ->where('jam_mulai', '<', $d['jam_selesai'])
            ->where('jam_selesai', '>', $d['jam_mulai']);

        if ($kecualikanId) {
            $query->whereKeyNot($kecualikanId);
        }

        $tabrakan = $query->get();
        if ($tabrakan->isEmpty()) {
            return null;
        }

        // Sebutkan kelas & jamnya: pesan lama hanya berkata "bentrok", sehingga
        // admin harus menelusuri sendiri jadwal mana yang menghalangi.
        $daftar = $tabrakan
            ->map(fn ($j) => ($j->kelas ?: 'kelas lain')
                . ' (' . substr((string) $j->jam_mulai, 0, 5) . '–' . substr((string) $j->jam_selesai, 0, 5) . ')')
            ->implode(', ');

        return "Jadwal bentrok! Guru ini sudah mengajar $daftar pada hari {$d['hari']} di jam tersebut. "
            . 'Satu guru tidak dapat berada di dua kelas sekaligus.';
    }

    /**
     * Nonaktifkan 1 slot.
     */
    public function destroy(JadwalMengajar $jadwalMengajar)
    {
        $jadwalMengajar->update(['is_aktif' => false]);
        return back()->with('success', 'Slot jadwal dihapus.');
    }

    public function export()
    {
        return back()->with('info', 'Fitur export segera tersedia.');
    }

    /**
     * Penjaga konsistensi: mapel Tahfidz/Tahsin TIDAK boleh dijadwalkan di sini
     * (harus lewat generator Smart Tahfidz/Tahsin dengan kelas khusus). Mencegah
     * jadwal "nyasar" seperti mapel Tahfidz dipasang ke kelas sekolah (mis. 9A).
     * Return pesan error bila ditolak, atau null bila lolos.
     */
    private function tolakProgramKhusus(int $mapelId, int $kelasId): ?string
    {
        $tipe  = MataPelajaran::find($mapelId)?->tipe;
        if (in_array($tipe, ['tahfidz', 'tahsin'], true)) {
            return 'Mapel ' . ucfirst($tipe) . ' dijadwalkan lewat menu Smart ' . ucfirst($tipe)
                . ' (Generate Jadwal), bukan di Jadwal Mengajar umum.';
        }
        // Mapel reguler tidak boleh dipasang ke kelas tahfidz/tahsin.
        $jenis = Kelas::find($kelasId)?->jenis;
        if (in_array($jenis, ['tahfidz', 'tahsin'], true)) {
            return 'Kelas ' . ucfirst($jenis) . ' hanya untuk mapel ' . ucfirst($jenis)
                . '. Gunakan menu Smart ' . ucfirst($jenis) . '.';
        }
        return null;
    }
}
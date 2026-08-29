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

        $semuaJadwal = JadwalMengajar::with([
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
            // Kelas Smart Education (jenis sekolah) untuk pemilihan kelas jadwal.
            'kelasList'   => Kelas::aktif()->sekolah()->orderBy('nama')
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
            'jam_selesai'        => 'required|date_format:H:i',
            'jumlah_jp'          => 'required|integer|min:1|max:20',
            'kelas_id'           => 'required|exists:kelas,id',
            'ruangan'            => 'nullable|string|max:50',
        ]);

        if ($err = $this->tolakProgramKhusus($data['mata_pelajaran_id'], $data['kelas_id'])) {
            return back()->with('error', $err);
        }

        // Cek bentrok jam (guru yang sama, hari yang sama, waktu tumpang tindih)
        $bentrok = JadwalMengajar::where('tenaga_pendidik_id', $data['tenaga_pendidik_id'])
            ->where('tahun_ajaran_id', $data['tahun_ajaran_id'])
            ->where('hari', $data['hari'])
            ->where('is_aktif', true)
            ->where(fn($q) =>
                $q->whereBetween('jam_mulai', [$data['jam_mulai'], $data['jam_selesai']])
                  ->orWhereBetween('jam_selesai', [$data['jam_mulai'], $data['jam_selesai']])
                  ->orWhere(fn($q2) =>
                      $q2->where('jam_mulai', '<=', $data['jam_mulai'])
                         ->where('jam_selesai', '>=', $data['jam_selesai'])
                  )
            )
            ->exists();

        if ($bentrok) {
            return back()->with('error',
                "Jadwal bentrok! Guru ini sudah memiliki jadwal di jam yang sama pada hari {$data['hari']}."
            );
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
            'jam_selesai'        => 'required|date_format:H:i',
            'jumlah_jp'          => 'required|integer|min:1|max:20',
            'kelas_id'           => 'required|exists:kelas,id',
            'ruangan'            => 'nullable|string|max:50',
        ]);

        if ($err = $this->tolakProgramKhusus($data['mata_pelajaran_id'], $data['kelas_id'])) {
            return back()->with('error', $err);
        }

        // kelas_id = sumber kebenaran; sinkronkan string kelas untuk tampilan.
        $data['kelas'] = Kelas::find($data['kelas_id'])?->nama ?? $jadwalMengajar->kelas;

        $jadwalMengajar->update($data);

        return back()->with('success', 'Jadwal berhasil diperbarui.');
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
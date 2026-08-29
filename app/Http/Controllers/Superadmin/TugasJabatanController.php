<?php
// ══════════════════════════════════════════════════════
// TugasJabatanController.php
// ══════════════════════════════════════════════════════
namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\TugasJabatan;
use App\Models\RealisasiTugasJabatan;
use App\Models\AbsensiKegiatan;
use App\Models\TenagaPendidik;
use App\Models\Jabatan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TugasJabatanController extends Controller
{
    public function index()
    {
        $bulan = (int) now()->month;
        $tahun = (int) now()->year;

        $tugas = TugasJabatan::with(['jabatan'])
            ->withCount([
                'realisasi',
                // Realisasi bulan berjalan + yang masih perlu diverifikasi admin.
                'realisasi as realisasi_bulan' => fn($q) => $q->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun),
                'realisasi as verif_pending' => fn($q) => $q->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun)->where('disetujui', false),
            ])
            ->aktif()
            ->get()
            ->map(fn($t) => [
                'id'              => $t->id,
                'jabatan_id'      => $t->jabatan_id,
                'nama_tugas'      => $t->nama_tugas,
                'deskripsi'       => $t->deskripsi,
                'frekuensi'       => $t->frekuensi,
                'frekuensi_label' => $t->frekuensi_label,
                'tipe_pengerjaan' => $t->tipe_pengerjaan ?? 'mandiri',
                'wajib_laporan'   => (bool) ($t->perlu_verifikasi ?? false), // kolom boolean model
                'is_aktif'        => $t->is_aktif,
                'jabatan'         => $t->jabatan?->nama_jabatan,
                'kode_jabatan'    => $t->jabatan?->kode_jabatan,
                'total_realisasi'   => $t->realisasi_count,
                'realisasi_bulan'   => $t->realisasi_bulan,
                'perlu_verifikasi'  => $t->verif_pending, // jumlah realisasi belum disetujui (bulan ini)
            ])->values();

        return Inertia::render('Admin/SmartPayroll/TugasJabatan/Index', [
            'tugas'   => $tugas,
            'bulanLabel' => \Carbon\Carbon::create($tahun, $bulan, 1)->locale('id')->isoFormat('MMMM Y'),
            'summary' => [
                'total'          => $tugas->count(),
                'mandiri'        => $tugas->where('tipe_pengerjaan', 'mandiri')->count(),
                'absen_kegiatan' => $tugas->where('tipe_pengerjaan', 'absen_kegiatan')->count(),
                'perlu_verifikasi' => $tugas->sum('perlu_verifikasi'),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/SmartPayroll/TugasJabatan/Form', [
            'jabatan' => Jabatan::aktif()->get(['id', 'nama_jabatan']),
            // setting_vakasi_id tidak lagi dipakai — vakasi dikonfigurasi via SettingVakasi per jabatan
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'jabatan_id'            => 'required|exists:jabatan,id',
            'nama_tugas'            => 'required|string|max:150',
            'deskripsi'             => 'nullable|string',
            'frekuensi'             => 'required|in:harian,mingguan,bulanan,insidental',
            'tipe_pengerjaan'       => 'required|in:mandiri,absen_kegiatan',
            'perlu_verifikasi'      => 'boolean',
            'icon'                  => 'nullable|string|max:50',
            'estimasi_durasi_menit' => 'nullable|integer|min:1',
            'urutan'                => 'nullable|integer|min:0',
        ]);

        TugasJabatan::create(array_merge($data, ['is_aktif' => true]));

        return redirect()->route('admin.smart-payroll.tugas-jabatan.index')
            ->with('success', 'Tugas jabatan berhasil ditambahkan.');
    }

    public function show(TugasJabatan $tugasJabatan)
    {
        // Realisasi bulan ini
        $bulan = request('bulan', now()->month);
        $tahun = request('tahun', now()->year);

        $realisasi = RealisasiTugasJabatan::with([
                'tenagaPendidik.user',
                'tenagaPendidik.jabatan',
                'diverifikasiOleh',
            ])
            ->where('tugas_jabatan_id', $tugasJabatan->id)
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun)
            ->orderByDesc('tanggal')
            ->get()
            ->map(fn($r) => [
                'id'              => $r->id,
                'guru_id'         => $r->tenaga_pendidik_id,
                'guru_nama'       => $r->tenagaPendidik->user->name,
                'guru_foto'       => $r->tenagaPendidik->user->foto
                    ? asset('storage/'.$r->tenagaPendidik->user->foto) : null,
                'guru_jabatan'    => $r->tenagaPendidik->jabatan?->nama_jabatan ?? '—',
                'tanggal'         => $r->tanggal->format('d M Y'),
                'keterangan'      => $r->keterangan,
                'bukti_tipe'      => $r->bukti_tipe,
                'link_bukti'      => $r->link_bukti,
                'teks_bukti'      => $r->teks_bukti,
                'file_bukti_url'  => $r->file_bukti ? asset('storage/'.$r->file_bukti) : null,
                'disetujui'       => $r->disetujui,
                'diverifikasi_oleh'=> $r->diverifikasiOleh?->name,
            ]);

        // Kegiatan absensi terkait (jika tipe absen_kegiatan)
        $kegiatan = [];
        if (($tugasJabatan->tipe_pengerjaan ?? 'mandiri') === 'absen_kegiatan') {
            $kegiatan = AbsensiKegiatan::with([
                    'peserta.tenagaPendidik.user',
                    'pengabsen.user',
                ])
                ->where('sumber_tipe', 'tugas_jabatan')
                ->where('sumber_id', $tugasJabatan->id)
                ->orderByDesc('tanggal_kegiatan')
                ->get()
                ->map(fn($k) => [
                    'id'               => $k->id,
                    'nama_kegiatan'    => $k->nama_kegiatan,
                    'tanggal_kegiatan' => $k->tanggal_kegiatan->format('d M Y'),
                    'jam_mulai'        => $k->jam_mulai,
                    'jam_selesai'      => $k->jam_selesai,
                    'lokasi'           => $k->lokasi,
                    'status'           => $k->status,
                    'pengabsen'        => $k->pengabsen?->user?->name ?? '—',
                    'total_peserta'    => $k->peserta->count(),
                    'hadir'            => $k->peserta->whereIn('status_kehadiran', ['hadir','terlambat'])->count(),
                    'vakasi_per_peserta'=> $k->vakasi_per_peserta,
                ])->toArray();
        }

        // FIX Gap 2: cari guru penerima via jabatan_guru pivot (support rangkap jabatan),
        // dengan fallback ke jabatan_id lama jika pivot belum terisi.
        $guruPenerima = TenagaPendidik::aktif()
            ->with(['user', 'jabatan'])
            ->where(function ($q) use ($tugasJabatan) {
                // Rangkap jabatan: cek pivot jabatan_guru
                $q->whereHas('jabatanAktif', fn($q2) =>
                    $q2->where('jabatan_id', $tugasJabatan->jabatan_id)
                )
                // Fallback: jabatan_id langsung di tabel tenaga_pendidik
                ->orWhere('jabatan_id', $tugasJabatan->jabatan_id);
            })
            ->get()
            ->unique('id')   // cegah duplikat jika match di keduanya
            ->map(fn($g) => [
                'id'        => $g->id,
                'nama'      => $g->user->name,
                'foto'      => $g->user->foto ? asset('storage/'.$g->user->foto) : null,
                'sudah_realisasi' => $realisasi->where('guru_id', $g->id)->count() > 0,
            ])->values();

        return Inertia::render('Admin/SmartPayroll/TugasJabatan/Show', [
            'tugas' => [
                'id'                    => $tugasJabatan->id,
                'nama_tugas'            => $tugasJabatan->nama_tugas,
                'deskripsi'             => $tugasJabatan->deskripsi,
                'frekuensi'             => $tugasJabatan->frekuensi,
                'frekuensi_label'       => $tugasJabatan->frekuensi_label,
                'tipe_pengerjaan'       => $tugasJabatan->tipe_pengerjaan ?? 'mandiri',
                'jabatan'               => $tugasJabatan->jabatan?->nama_jabatan,
                'jabatan_id'            => $tugasJabatan->jabatan_id,
                'icon'                  => $tugasJabatan->icon,
                'estimasi_durasi_menit' => $tugasJabatan->estimasi_durasi_menit,
                'is_aktif'              => $tugasJabatan->is_aktif,
                // Catatan: vakasi tugas jabatan kini dikonfigurasi via SettingVakasi per jabatan/guru
            ],
            'realisasi'    => $realisasi->values(),
            'kegiatan'     => $kegiatan,
            'guru_penerima'=> $guruPenerima,
            'bulan'        => (int)$bulan,
            'tahun'        => (int)$tahun,
            'stats'        => [
                'total_guru'      => $guruPenerima->count(),
                'sudah_realisasi' => $realisasi->pluck('guru_id')->unique()->count(),
                'disetujui'       => $realisasi->where('disetujui', true)->count(),
                'pending'         => $realisasi->whereNull('disetujui')->count(),
            ],
        ]);
    }

    public function verifikasi(RealisasiTugasJabatan $realisasi, Request $request)
    {
        $data = $request->validate([
            'disetujui'  => 'required|boolean',
            'keterangan' => 'nullable|string|max:500',
        ]);

        $realisasi->update([
            'disetujui'          => $data['disetujui'],
            'keterangan'         => $data['keterangan'] ?? $realisasi->keterangan,
            'diverifikasi_oleh'  => auth()->id(),
        ]);

        return back()->with('success', $data['disetujui'] ? 'Realisasi disetujui.' : 'Realisasi ditolak.');
    }

    public function edit(TugasJabatan $tugasJabatan)
    {
        return Inertia::render('Admin/SmartPayroll/TugasJabatan/Form', [
            'tugas'   => $tugasJabatan->load('jabatan'),
            'jabatan' => Jabatan::aktif()->get(['id', 'nama_jabatan']),
        ]);
    }

    public function update(Request $request, TugasJabatan $tugasJabatan)
    {
        $data = $request->validate([
            'jabatan_id'            => 'required|exists:jabatan,id',
            'nama_tugas'            => 'required|string|max:150',
            'deskripsi'             => 'nullable|string',
            'frekuensi'             => 'required|in:harian,mingguan,bulanan,insidental',
            'tipe_pengerjaan'       => 'required|in:mandiri,absen_kegiatan',
            'perlu_verifikasi'      => 'boolean',
            'icon'                  => 'nullable|string|max:50',
            'estimasi_durasi_menit' => 'nullable|integer|min:1',
            'urutan'                => 'nullable|integer|min:0',
        ]);

        $tugasJabatan->update($data);

        return redirect()->route('admin.smart-payroll.tugas-jabatan.index')
            ->with('success', 'Tugas jabatan berhasil diperbarui.');
    }

    public function destroy(TugasJabatan $tugasJabatan)
    {
        $tugasJabatan->update(['is_aktif' => false]);

        return back()->with('success', 'Tugas jabatan dinonaktifkan.');
    }
}
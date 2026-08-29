<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AbsensiKegiatan;
use App\Models\AbsensiKegiatanPeserta;
use App\Models\HariLibur;
use App\Models\TenagaPendidik;
use App\Models\AbsensiHarian;
use App\Models\RealisasiTugasJabatan;
use App\Models\PenugasanTambahan;
use App\Models\SettingVakasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class AbsensiKegiatanController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════════
    // INDEX — daftar semua kegiatan
    // ══════════════════════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        $query = AbsensiKegiatan::with([
                'pengabsen.user', 'peserta.tenagaPendidik.user',
            ])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->tanggal, fn($q) => $q->whereDate('tanggal_kegiatan', $request->tanggal))
            ->orderByDesc('tanggal_kegiatan');

        $kegiatan = $query->get()->map(fn($k) => $this->formatKegiatan($k));

        return Inertia::render('Admin/SmartPayroll/AbsensiKegiatan/Index', [
            'kegiatan' => $kegiatan,
            'filters'  => $request->only(['status', 'tanggal']),
            'stats'    => [
                'total'       => $kegiatan->count(),
                'berlangsung' => $kegiatan->where('status', 'berlangsung')->count(),
                'selesai'     => $kegiatan->where('status', 'selesai')->count(),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SHOW — detail kegiatan + daftar absensi peserta
    // ══════════════════════════════════════════════════════════════════════════

    public function show(AbsensiKegiatan $absensiKegiatan)
    {
        $kegiatan = $absensiKegiatan->load([
            'pengabsen.user', 'pengabsen.jabatan',
            'peserta.tenagaPendidik.user',
            'peserta.tenagaPendidik.jabatan',
            'settingVakasi',
        ]);

        // Semua guru aktif (untuk tambah peserta)
        $semuaGuru = TenagaPendidik::aktif()
            ->with(['user', 'jabatan'])
            ->get()
            ->map(fn($g) => [
                'id'      => $g->id,
                'nama'    => $g->user->name,
                'jabatan' => $g->jabatan?->nama_jabatan ?? '—',
                'foto'    => $g->user->foto ? asset('storage/'.$g->user->foto) : null,
            ]);

        $pesertaIds = $kegiatan->peserta->pluck('tenaga_pendidik_id');

        // Ambil info tugas sumber (untuk tampil di Vue)
        $tugasSumber = null;
        if ($kegiatan->sumber_tipe === 'tugas_jabatan') {
            $ts = \App\Models\TugasJabatan::with('jabatan')->find($kegiatan->sumber_id);
            $tugasSumber = $ts ? [
                'tipe'            => 'tugas_jabatan',
                'nama'            => $ts->nama_tugas,
                'tipe_pengerjaan' => $ts->tipe_pengerjaan ?? 'absen_kegiatan',
                'jabatan'         => $ts->jabatan?->nama_jabatan,
                'url'             => route('admin.smart-payroll.tugas-jabatan.edit', $ts->id),
            ] : null;
        } elseif ($kegiatan->sumber_tipe === 'tugas_tambahan') {
            $ts = \App\Models\TugasTambahan::find($kegiatan->sumber_id);
            $tugasSumber = $ts ? [
                'tipe'            => 'tugas_tambahan',
                'nama'            => $ts->judul,
                'tipe_pengerjaan' => $ts->tipe_pengerjaan ?? 'absen_kegiatan',
                'url'             => route('admin.smart-payroll.tugas-tambahan.show', $ts->id),
            ] : null;
        }

        return Inertia::render('Admin/SmartPayroll/AbsensiKegiatan/Show', [
            'tugas_sumber' => $tugasSumber,
            'kegiatan'     => $this->formatKegiatan($kegiatan),
            'peserta'  => $kegiatan->peserta->map(fn($p) => [
                'id'               => $p->id,
                'nama'             => $p->tenagaPendidik->user->name,
                'jabatan'          => $p->tenagaPendidik->jabatan?->nama_jabatan ?? '—',
                'foto'             => $p->tenagaPendidik->user->foto
                    ? asset('storage/'.$p->tenagaPendidik->user->foto) : null,
                'status_kehadiran' => $p->status_kehadiran,
                'jam_hadir'        => $p->jam_hadir,
                'keterangan'       => $p->keterangan,
                'vakasi_diberikan' => $p->vakasi_diberikan,
                'nominal_vakasi'   => $p->nominal_vakasi,
                'status_label'     => $p->statusLabel(),
                'status_color'     => $p->statusColor(),
            ])->values(),
            'guru_tersedia' => $semuaGuru->whereNotIn('id', $pesertaIds)->values(),
            'vakasi'        => SettingVakasi::aktif()->get(['id', 'nama', 'nominal']),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // STORE — buat kegiatan baru (dari tugas jabatan atau tambahan)
    // ══════════════════════════════════════════════════════════════════════════

    public function store(Request $request)
    {
        $data = $request->validate([
            'sumber_tipe'       => 'required|in:tugas_jabatan,tugas_tambahan',
            'sumber_id'         => 'required|integer',
            'realisasi_id'      => 'nullable|exists:realisasi_tugas_jabatan,id',
            'penugasan_id'      => 'nullable|exists:penugasan_tambahan,id',
            // pengabsen_id: nullable karena bisa auto-resolve dari auth user
            'pengabsen_id'      => 'nullable|exists:tenaga_pendidik,id',
            'nama_kegiatan'     => 'required|string|max:200',
            'tanggal_kegiatan'  => 'required|date',
            'jam_mulai'         => 'nullable|date_format:H:i',
            'jam_selesai'       => 'nullable|date_format:H:i',
            'deskripsi'         => 'nullable|string',
            'lokasi'            => 'nullable|string|max:200',
            'setting_vakasi_id' => 'nullable|exists:setting_vakasi,id',
            'vakasi_per_peserta'=> 'nullable|numeric|min:0',
            // Mode peserta: semua guru atau pilih manual
            'semua_guru'        => 'nullable|boolean',
            'peserta_ids'       => 'nullable|array',
            'peserta_ids.*'     => 'exists:tenaga_pendidik,id',
        ]);

        $kegiatanBaru = null;
        DB::transaction(function () use ($data, $request, &$kegiatanBaru) {
            // Auto-set vakasi_per_peserta dari tugas jika belum diset
            $vakasiPeserta = $data['vakasi_per_peserta'] ?? null;
            if (!$vakasiPeserta && isset($data['sumber_tipe'], $data['sumber_id'])) {
                $vakasiPeserta = $this->getVakasiDariTugas(
                    $data['sumber_tipe'], $data['sumber_id']
                );
            }

            $tanggal = $data['tanggal_kegiatan'];

            // ── Resolve pengabsen_id ─────────────────────────────────────────
            // Prioritas 1: explicit dari request (dikirim frontend)
            $pengabsenId = $data['pengabsen_id'] ?? null;

            // Prioritas 2: resolve dari penugasan_id yang dikirim
            // (superadmin buat kegiatan untuk guru tertentu via admin panel)
            $penugasanId = $data['penugasan_id'] ?? null;
            if (!$pengabsenId && $penugasanId) {
                $pengabsenId = \App\Models\PenugasanTambahan::find($penugasanId)
                    ?->tenaga_pendidik_id;
            }

            // Prioritas 3: dari sumber tugas_tambahan + penugasan yang ada
            // (saat sumber_tipe=tugas_tambahan tapi penugasan_id belum dikirim)
            if (!$pengabsenId && ($data['sumber_tipe'] ?? '') === 'tugas_tambahan') {
                // Cari satu-satunya penugasan aktif untuk tugas ini, atau pilih pertama
                $firstPenugasan = \App\Models\PenugasanTambahan::where('tugas_tambahan_id', $data['sumber_id'])
                    ->first();
                if ($firstPenugasan) {
                    $pengabsenId = $firstPenugasan->tenaga_pendidik_id;
                    $penugasanId = $penugasanId ?? $firstPenugasan->id;
                }
            }

            // Prioritas 4: dari user login (guru buat kegiatan sendiri via mobile)
            if (!$pengabsenId) {
                $pengabsenId = \App\Models\TenagaPendidik::where('user_id', Auth::id())->value('id');
            }

            // Jika penugasan_id belum ada, cari dari pengabsen + tugas (untuk tugas_tambahan)
            if (!$penugasanId && $pengabsenId && ($data['sumber_tipe'] ?? '') === 'tugas_tambahan') {
                $penugasanId = \App\Models\PenugasanTambahan::where('tugas_tambahan_id', $data['sumber_id'])
                    ->where('tenaga_pendidik_id', $pengabsenId)
                    ->value('id');
            }

            // Guard: harus ada pengabsen
            if (!$pengabsenId) {
                throw new \Exception('Pengabsen tidak ditemukan. Pastikan ada guru yang di-assign ke tugas ini sebelum membuat kegiatan.');
            }

            $kegiatan = AbsensiKegiatan::create([
                'sumber_tipe'        => $data['sumber_tipe'],
                'sumber_id'          => $data['sumber_id'],
                'realisasi_id'       => $data['realisasi_id'] ?? null,
                'penugasan_id'       => $penugasanId,
                'pengabsen_id'       => $pengabsenId,
                'nama_kegiatan'      => $data['nama_kegiatan'],
                'tanggal_kegiatan'   => $data['tanggal_kegiatan'],
                'jam_mulai'          => $data['jam_mulai'] ?? null,
                'jam_selesai'        => $data['jam_selesai'] ?? null,
                'deskripsi'          => $data['deskripsi'] ?? null,
                'lokasi'             => $data['lokasi'] ?? null,
                'setting_vakasi_id'  => $data['setting_vakasi_id'] ?? null,
                'vakasi_per_peserta' => $vakasiPeserta,
                'status'             => 'berlangsung',
                'dibuat_oleh'        => Auth::id(),
            ]);

            // ── Tentukan peserta ──────────────────────────────────────────────
            if ($request->boolean('semua_guru')) {
                // Semua guru aktif KECUALI:
                // 1. Hari libur nasional/pesantren
                // 2. Guru yang izin/sakit/alfa hari itu
                // 3. Guru yang tidak punya jam kerja hari itu (misal: part-time)
                $isHariLibur = HariLibur::isLibur($tanggal);

                if ($isHariLibur) {
                    // Hari libur: kegiatan dibuat tanpa peserta otomatis
                    // Admin bisa tambah manual jika kegiatan tetap ada saat libur
                } else {
                    $namaHari = strtolower(
                        \Carbon\Carbon::parse($tanggal)->locale('id')->dayName
                    );
                    // Map nama hari Bahasa Indonesia → format DB
                    $namaHariMap = [
                        'senin' => 'senin', 'selasa' => 'selasa',
                        'rabu' => 'rabu', 'kamis' => 'kamis',
                        'jumat' => 'jumat', 'sabtu' => 'sabtu', 'minggu' => 'ahad',
                    ];
                    $hariDb = $namaHariMap[$namaHari] ?? $namaHari;

                    // Ambil setting jam kerja default
                    $jamKerja = \App\Models\SettingJamKerja::getDefault();

                    // Guru yang tidak hadir (izin/sakit/alfa) hari ini
                    $guruTidakHadir = AbsensiHarian::whereDate('tanggal', $tanggal)
                        ->whereIn('status', ['izin', 'sakit', 'alfa', 'libur', 'dinas_luar'])
                        ->pluck('tenaga_pendidik_id')
                        ->toArray();

                    // Guru yang tidak punya jam kerja hari itu
                    $guruTanpaJamKerja = [];
                    if ($jamKerja && $jamKerja->gunakan_jadwal_per_hari) {
                        $jadwalHari = $jamKerja->getJamUntukHari($hariDb);
                        if (!$jadwalHari) {
                            // Seluruh guru tidak kerja hari itu — kegiatan tetap bisa dibuat
                            // tapi peserta otomatis kosong
                            $guruTanpaJamKerja = TenagaPendidik::aktif()->pluck('id')->toArray();
                        }
                    } elseif ($jamKerja) {
                        // Setting global: cek hari_kerja
                        $hariKerja = $jamKerja->hari_kerja ?? [];
                        if (!in_array($hariDb, $hariKerja)) {
                            $guruTanpaJamKerja = TenagaPendidik::aktif()->pluck('id')->toArray();
                        }
                    }

                    // Exclude pengabsen dari peserta — pengabsen adalah penanggung jawab kegiatan,
                    // bukan peserta biasa. Vakasi pengabsen disalurkan via PenugasanTambahan
                    // (slip gaji kolom vakasi_tugas_tambahan), bukan vakasi_peserta_kegiatan.
                    // Memasukkan pengabsen ke peserta akan menyebabkan duplikat entry + double vakasi.
                    $exclude = array_unique(array_merge($guruTidakHadir, $guruTanpaJamKerja, [$pengabsenId]));

                    // Ambil semua guru aktif kecuali yang dikecualikan
                    $semuaGuruIds = TenagaPendidik::aktif()
                        ->whereNotIn('id', $exclude)
                        ->pluck('id');

                    // status 'belum' = daftar hadir belum diisi oleh guru pengabsen via Flutter
                    // firstOrCreate: hindari duplicate entry jika peserta sudah terdaftar sebelumnya
                    foreach ($semuaGuruIds as $gId) {
                        AbsensiKegiatanPeserta::firstOrCreate(
                            [
                                'absensi_kegiatan_id' => $kegiatan->id,
                                'tenaga_pendidik_id'  => $gId,
                            ],
                            [
                                'status_kehadiran' => 'belum',
                                'dicatat_oleh'     => Auth::id(),
                            ]
                        );
                    }
                }
            } else {
                // Pilih manual: filter pengabsen agar tidak masuk peserta
                $pesertaIds = array_filter(
                    $data['peserta_ids'] ?? [],
                    fn($id) => $id != $pengabsenId
                );
                foreach ($pesertaIds as $gId) {
                    AbsensiKegiatanPeserta::firstOrCreate(
                        [
                            'absensi_kegiatan_id' => $kegiatan->id,
                            'tenaga_pendidik_id'  => $gId,
                        ],
                        [
                            'status_kehadiran' => 'belum',
                            'dicatat_oleh'     => Auth::id(),
                        ]
                    );
                }
            }

            // Simpan ke variable luar agar bisa dipakai setelah transaction
            $kegiatanBaru = $kegiatan;
        });

        return redirect()
            ->route('admin.smart-payroll.absensi-kegiatan.show', $kegiatanBaru->id)
            ->with('success', 'Kegiatan absensi berhasil dibuat. Tambahkan peserta dan mulai absensi.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // TAMBAH PESERTA
    // ══════════════════════════════════════════════════════════════════════════

    public function tambahPeserta(AbsensiKegiatan $absensiKegiatan, Request $request)
    {
        $request->validate([
            'semua_guru'            => 'nullable|boolean',
            'tenaga_pendidik_ids'   => 'nullable|array',
            'tenaga_pendidik_ids.*' => 'exists:tenaga_pendidik,id',
        ]);

        if ($request->boolean('semua_guru')) {
            // Ambil peserta yang sudah ada
            $sudahAdaIds = AbsensiKegiatanPeserta::where('absensi_kegiatan_id', $absensiKegiatan->id)
                ->pluck('tenaga_pendidik_id')->toArray();

            $tanggal    = $absensiKegiatan->tanggal_kegiatan->toDateString();
            $isHariLibur = HariLibur::isLibur($tanggal);

            if ($isHariLibur) {
                return back()->with('info', 'Hari ini adalah hari libur. Peserta tidak otomatis ditambahkan.');
            }

            $namaHari = strtolower(\Carbon\Carbon::parse($tanggal)->locale('id')->dayName);
            $namaHariMap = [
                'senin' => 'senin', 'selasa' => 'selasa', 'rabu' => 'rabu',
                'kamis' => 'kamis', 'jumat' => 'jumat',
                'sabtu' => 'sabtu', 'minggu' => 'ahad',
            ];
            $hariDb = $namaHariMap[$namaHari] ?? $namaHari;

            // Filter tidak hadir
            $guruTidakHadir = AbsensiHarian::whereDate('tanggal', $tanggal)
                ->whereIn('status', ['izin', 'sakit', 'alfa', 'libur', 'dinas_luar'])
                ->pluck('tenaga_pendidik_id')->toArray();

            // Filter tidak ada jam kerja
            $guruTanpaJamKerja = [];
            $jamKerja = \App\Models\SettingJamKerja::getDefault();
            if ($jamKerja) {
                if ($jamKerja->gunakan_jadwal_per_hari) {
                    if (!$jamKerja->getJamUntukHari($hariDb)) {
                        $guruTanpaJamKerja = TenagaPendidik::aktif()->pluck('id')->toArray();
                    }
                } else {
                    $hariKerja = $jamKerja->hari_kerja ?? [];
                    if (!in_array($hariDb, $hariKerja)) {
                        $guruTanpaJamKerja = TenagaPendidik::aktif()->pluck('id')->toArray();
                    }
                }
            }

            // Exclude pengabsen — penanggung jawab kegiatan bukan peserta biasa
            $exclude   = array_unique(array_merge(
                $sudahAdaIds, $guruTidakHadir, $guruTanpaJamKerja,
                [$absensiKegiatan->pengabsen_id]
            ));
            $guruIds   = TenagaPendidik::aktif()->whereNotIn('id', $exclude)->pluck('id');

            // status 'belum' — guru pengabsen yang akan tandai kehadiran via Flutter
            // firstOrCreate: hindari duplicate entry jika peserta sudah terdaftar sebelumnya
            $ditambahkan = 0;
            foreach ($guruIds as $id) {
                $peserta = AbsensiKegiatanPeserta::firstOrCreate(
                    [
                        'absensi_kegiatan_id' => $absensiKegiatan->id,
                        'tenaga_pendidik_id'  => $id,
                    ],
                    [
                        'status_kehadiran' => 'belum',
                        'dicatat_oleh'     => Auth::id(),
                    ]
                );
                if ($peserta->wasRecentlyCreated) $ditambahkan++;
            }

            return back()->with('success', "{$ditambahkan} guru ditambahkan ke daftar. Guru pengabsen akan menandai kehadiran via Flutter.");
        }

        // Mode manual — filter pengabsen agar tidak masuk peserta
        $ids = array_filter(
            $request->tenaga_pendidik_ids ?? [],
            fn($id) => $id != $absensiKegiatan->pengabsen_id
        );
        $ditambahkan = 0;
        foreach ($ids as $id) {
            $exists = AbsensiKegiatanPeserta::where('absensi_kegiatan_id', $absensiKegiatan->id)
                ->where('tenaga_pendidik_id', $id)->exists();
            if (!$exists) {
                AbsensiKegiatanPeserta::create([
                    'absensi_kegiatan_id' => $absensiKegiatan->id,
                    'tenaga_pendidik_id'  => $id,
                    'status_kehadiran'    => 'belum',
                    'dicatat_oleh'        => Auth::id(),
                ]);
                $ditambahkan++;
            }
        }

        return back()->with('success', "{$ditambahkan} guru ditambahkan ke daftar peserta.");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // EDIT — redirect ke show (edit lewat modal di Show.vue)
    // ══════════════════════════════════════════════════════════════════════════

    public function edit(AbsensiKegiatan $absensiKegiatan)
    {
        return redirect()->route('admin.smart-payroll.absensi-kegiatan.show', $absensiKegiatan->id);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // UPDATE — perbarui data kegiatan (nama, tanggal, lokasi, jam, vakasi)
    // ══════════════════════════════════════════════════════════════════════════

    public function update(Request $request, AbsensiKegiatan $absensiKegiatan)
    {
        // Sanitize empty strings → null
        foreach (['jam_mulai', 'jam_selesai', 'lokasi', 'deskripsi', 'setting_vakasi_id', 'vakasi_per_peserta'] as $field) {
            if ($request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }

        $data = $request->validate([
            'nama_kegiatan'     => 'required|string|max:200',
            'tanggal_kegiatan'  => 'required|date',
            'jam_mulai'         => 'nullable|date_format:H:i',
            'jam_selesai'       => 'nullable|date_format:H:i',
            'lokasi'            => 'nullable|string|max:200',
            'deskripsi'         => 'nullable|string',
            'vakasi_per_peserta'=> 'nullable|numeric|min:0',
            'setting_vakasi_id' => 'nullable|exists:setting_vakasi,id',
        ]);

        $absensiKegiatan->update($data);

        return redirect()
            ->route('admin.smart-payroll.absensi-kegiatan.show', $absensiKegiatan->id)
            ->with('success', 'Data kegiatan berhasil diperbarui.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DESTROY — hapus kegiatan (hanya yang belum selesai)
    // ══════════════════════════════════════════════════════════════════════════

    public function destroy(AbsensiKegiatan $absensiKegiatan)
    {
        if ($absensiKegiatan->status === 'selesai') {
            return redirect()
                ->route('admin.smart-payroll.absensi-kegiatan.show', $absensiKegiatan->id)
                ->with('error', 'Kegiatan yang sudah selesai tidak dapat dihapus.');
        }

        DB::transaction(function () use ($absensiKegiatan) {
            $absensiKegiatan->peserta()->delete();
            $absensiKegiatan->delete();
        });

        return redirect()
            ->route('admin.smart-payroll.absensi-kegiatan.index')
            ->with('success', 'Kegiatan berhasil dihapus.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HAPUS PESERTA — keluarkan satu peserta dari daftar kegiatan
    // ══════════════════════════════════════════════════════════════════════════

    public function hapusPeserta(AbsensiKegiatanPeserta $peserta)
    {
        $kegiatanId = $peserta->absensi_kegiatan_id;
        $peserta->delete();

        return redirect()
            ->route('admin.smart-payroll.absensi-kegiatan.show', $kegiatanId)
            ->with('success', 'Peserta berhasil dihapus dari kegiatan.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // UPDATE STATUS KEHADIRAN PESERTA
    // ══════════════════════════════════════════════════════════════════════════

    public function updatePeserta(AbsensiKegiatanPeserta $peserta, Request $request)
    {
        $data = $request->validate([
            'status_kehadiran' => 'required|in:hadir,terlambat,izin,alfa',
            'jam_hadir'        => 'nullable|date_format:H:i',
            'keterangan'       => 'nullable|string|max:300',
        ]);

        $peserta->update($data);
        return back()->with('success', 'Status kehadiran diperbarui.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SELESAIKAN KEGIATAN + DISTRIBUSI VAKASI
    // ══════════════════════════════════════════════════════════════════════════

    public function selesaikan(AbsensiKegiatan $absensiKegiatan, Request $request)
    {
        $request->validate([
            'catatan' => 'nullable|string|max:500',
        ]);

        if ($absensiKegiatan->status !== 'berlangsung') {
            return back()->with('error', 'Kegiatan sudah selesai atau dibatalkan.');
        }

        DB::transaction(function () use ($absensiKegiatan) {
            // ── 1. Tandai kegiatan selesai ─────────────────────────────────
            $absensiKegiatan->update(['status' => 'selesai']);

            // ── 2. Ambil nominal vakasi dari sumber tugas ──────────────────
            // Prioritas: vakasi_per_peserta kegiatan → sumber tugas → settingVakasi
            $nominalVakasi = $absensiKegiatan->vakasi_per_peserta ?? 0;

            if (!$nominalVakasi) {
                // Ambil dari tugas tambahan/jabatan sumber
                $nominalVakasi = $this->getVakasiDariTugas(
                    $absensiKegiatan->sumber_tipe,
                    $absensiKegiatan->sumber_id
                ) ?? $absensiKegiatan->settingVakasi?->nominal ?? 0;
            }

            // ── 3. Distribusi vakasi ke PESERTA yang hadir ─────────────────
            // Exclude pengabsen sebagai safety net — seharusnya pengabsen sudah
            // tidak masuk ke tabel peserta, tapi ini melindungi data lama.
            // Vakasi pengabsen disalurkan via PenugasanTambahan (langkah 4).
            if ($nominalVakasi > 0) {
                $absensiKegiatan->peserta()
                    ->whereIn('status_kehadiran', ['hadir', 'terlambat'])
                    ->where('tenaga_pendidik_id', '!=', $absensiKegiatan->pengabsen_id)
                    ->update([
                        'vakasi_diberikan' => true,
                        'nominal_vakasi'   => $nominalVakasi,
                    ]);
            }

            // ── 4. Update PenugasanTambahan (pengabsen) → selesai ─────────
            // Sertakan dikerjakan_pada agar tidak terjadi constraint "belum dimulai"
            // pada penugasan yang status-nya masih 'belum' (belum dikerjakan sebelumnya).
            if ($absensiKegiatan->penugasan_id) {
                $penugasan = PenugasanTambahan::find($absensiKegiatan->penugasan_id);
                if ($penugasan && $penugasan->status_pengerjaan !== 'selesai') {
                    $penugasan->update([
                        'status_pengerjaan' => 'selesai',
                        'dikerjakan_pada'   => $penugasan->dikerjakan_pada ?? now(),
                        'dilaporkan_pada'   => now(),
                        'disetujui'         => true,
                        'catatan_verifikasi'=> 'Otomatis disetujui saat kegiatan selesai.',
                    ]);
                }
            }

            // ── 5. Update RealisasiTugasJabatan (Tugas Jabatan) → selesai ─
            if ($absensiKegiatan->realisasi_id) {
                RealisasiTugasJabatan::find($absensiKegiatan->realisasi_id)
                    ?->update(['disetujui' => true]);
            }
        });

        $hadir = $absensiKegiatan->peserta()
            ->whereIn('status_kehadiran', ['hadir','terlambat'])->count();

        return back()->with('success',
            "Kegiatan selesai! {$hadir} peserta mendapat vakasi. Tugas pengabsen otomatis disetujui.");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // UPDATE ABSENSI BULK (dari Vue — satu request untuk update banyak status)
    // ══════════════════════════════════════════════════════════════════════════

    public function updateBulk(AbsensiKegiatan $absensiKegiatan, Request $request)
    {
        $data = $request->validate([
            'absensi'             => 'required|array',
            'absensi.*.id'        => 'required|exists:absensi_kegiatan_peserta,id',
            'absensi.*.status_kehadiran' => 'required|in:hadir,terlambat,izin,alfa,belum',
            'absensi.*.jam_hadir'        => 'nullable|date_format:H:i',
            'absensi.*.keterangan'       => 'nullable|string|max:300',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['absensi'] as $item) {
                AbsensiKegiatanPeserta::find($item['id'])?->update([
                    'status_kehadiran' => $item['status_kehadiran'],
                    'jam_hadir'        => $item['jam_hadir'] ?? null,
                    'keterangan'       => $item['keterangan'] ?? null,
                    'dicatat_oleh'     => Auth::id(),
                ]);
            }
        });

        return back()->with('success', 'Absensi kegiatan berhasil disimpan.');
    }

    // ── Format Helper ─────────────────────────────────────────────────────────

    /**
     * Ambil nominal vakasi dari tugas sumber (jabatan atau tambahan).
     * Vakasi ini berlaku untuk peserta yang hadir — SAMA dengan pengabsen.
     */
    private function getVakasiDariTugas(string $sumberTipe, int $sumberId): ?float
    {
        // Catatan: tugas_jabatan.setting_vakasi_id sudah di-DROP dari skema.
        // Vakasi untuk tugas jabatan kini dikonfigurasi via SettingVakasi lingkup jabatan,
        // BUKAN di-embed ke tugas. Untuk kegiatan, nominal ditetapkan saat buat kegiatan
        // (field vakasi_per_peserta atau setting_vakasi_id di absensi_kegiatan).

        if ($sumberTipe === 'tugas_jabatan') {
            // Tidak ada nominal di model TugasJabatan lagi — return null supaya
            // caller jatuh ke fallback settingVakasi kegiatan atau 0.
            return null;
        }

        if ($sumberTipe === 'tugas_tambahan') {
            $tugas = \App\Models\TugasTambahan::with('settingVakasi')->find($sumberId);
            return $tugas?->vakasi_override
                ?? $tugas?->settingVakasi?->nominal
                ?? null;
        }

        return null;
    }

    private function formatKegiatan(AbsensiKegiatan $k): array
    {
        return [
            'id'               => $k->id,
            'sumber_tipe'      => $k->sumber_tipe,
            'sumber_id'        => $k->sumber_id,
            'nama_kegiatan'    => $k->nama_kegiatan,
            'tanggal_kegiatan' => $k->tanggal_kegiatan?->format('Y-m-d'),
            'tanggal_label'    => $k->tanggal_kegiatan?->locale('id')->isoFormat('dddd, D MMMM Y'),
            'jam_mulai'        => $k->jam_mulai,
            'jam_selesai'      => $k->jam_selesai,
            'lokasi'           => $k->lokasi,
            'deskripsi'        => $k->deskripsi,
            'status'           => $k->status,
            'vakasi_per_peserta'=> $k->vakasi_per_peserta,
            'pengabsen' => [
                'id'      => $k->pengabsen?->id,
                'nama'    => $k->pengabsen?->user?->name ?? '—',
                'jabatan' => $k->pengabsen?->jabatan?->nama_jabatan ?? '—',
            ],
            'stats' => [
                'total_peserta' => $k->peserta->count(),
                'hadir'         => $k->peserta->whereIn('status_kehadiran', ['hadir','terlambat'])->count(),
                'izin'          => $k->peserta->where('status_kehadiran', 'izin')->count(),
                'alfa'          => $k->peserta->where('status_kehadiran', 'alfa')->count(),
            ],
        ];
    }
}
<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\TugasTambahan;
use App\Models\PenugasanTambahan;
use App\Models\TenagaPendidik;
use App\Models\Jabatan;
use App\Models\SettingVakasi;
use App\Models\JabatanGuru;
use App\Services\TimezoneHelper;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TugasTambahanController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) $request->input('status', '');
        $q      = trim((string) $request->input('q', ''));

        $tugas = TugasTambahan::with(['dibuatOleh', 'settingVakasi'])
            ->withCount([
                'penugasan',
                'penugasan as selesai_count' => fn($q) => $q->where('status_pengerjaan', 'selesai'),
                'penugasan as disetujui_count' => fn($q) => $q->where('disetujui', true),
                'penugasan as menunggu_count' => fn($q) => $q->where('status_pengerjaan', 'selesai')->whereNull('disetujui'),
            ])
            // Filter status (atau "perlu_verifikasi": tugas yang punya penugasan menunggu).
            ->when(in_array($status, ['draft', 'aktif', 'selesai', 'dibatalkan'], true),
                fn($qq) => $qq->where('status', $status))
            ->when($status === 'perlu_verifikasi',
                fn($qq) => $qq->whereHas('penugasan', fn($p) => $p->menungguVerifikasi()))
            ->when($q !== '', fn($qq) => $qq->where('judul', 'like', "%{$q}%"))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn($t) => [
                'id'               => $t->id,
                'judul'            => $t->judul,
                'deskripsi'        => $t->deskripsi,
                'tipe'             => $t->tipe,
                'status'           => $t->status,
                'tanggal_mulai'    => $t->tanggal_mulai?->format('d M Y'),
                'tanggal_selesai'  => $t->tanggal_selesai?->format('d M Y'),
                'nominal_vakasi'   => $t->getNominalVakasi(),
                'sumber_vakasi'    => $t->setting_vakasi_id ? 'setting' : ($t->vakasi_override !== null ? 'override' : 'tidak_ada'),
                'setting_vakasi'   => $t->settingVakasi?->nama,
                'total_penerima'   => $t->penugasan_count,
                'selesai'          => $t->selesai_count,
                'disetujui'        => $t->disetujui_count,
                'menunggu'         => $t->menunggu_count,
                'dibuat_oleh'      => $t->dibuatOleh?->name,
                'dibuat_pada'      => $t->created_at?->format('d M Y'),
            ]);

        // Estimasi vakasi = Σ (nominal vakasi × penerima disetujui) seluruh tugas.
        $vakasiTotal = TugasTambahan::with('settingVakasi')
            ->withCount(['penugasan as disetujui_count' => fn($q) => $q->where('disetujui', true)])
            ->get()->sum(fn($t) => (float) $t->getNominalVakasi() * $t->disetujui_count);

        return Inertia::render('Admin/SmartPayroll/TugasTambahan/Index', [
            'tugas'   => $tugas,
            'filters' => ['status' => $status, 'q' => $q],
            'summary' => [
                'total'    => TugasTambahan::count(),
                'aktif'    => TugasTambahan::where('status', 'aktif')->count(),
                'selesai'  => TugasTambahan::where('status', 'selesai')->count(),
                'menunggu' => PenugasanTambahan::menungguVerifikasi()->count(),
                'vakasi'   => $vakasiTotal,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/SmartPayroll/TugasTambahan/Form', [
            'guru'    => $this->getGuruList(),
            'jabatan' => Jabatan::aktif()->orderBy('tipe')->get(['id', 'nama_jabatan', 'kode_jabatan', 'tipe']),
            'vakasi'  => $this->getVakasiList(),
        ]);
    }

    public function store(Request $request)
    {
        // Sanitasi: empty string dari form → null agar FK dan cast tidak error
        $request->merge([
            'setting_vakasi_id' => $request->input('setting_vakasi_id') !== '' ? $request->input('setting_vakasi_id') : null,
            'vakasi_override'   => $request->input('vakasi_override') !== '' ? $request->input('vakasi_override') : null,
            'tanggal_selesai'   => $request->input('tanggal_selesai') !== '' ? $request->input('tanggal_selesai') : null,
        ]);

        $data = $request->validate([
            'judul'               => 'required|string|max:200',
            'deskripsi'           => 'nullable|string',
            'tanggal_mulai'       => 'required|date',
            'tanggal_selesai'     => 'nullable|date|after_or_equal:tanggal_mulai',
            'tipe'                => 'required|in:individu,jabatan,semua',
            'tipe_pengerjaan'     => 'required|in:mandiri,absen_kegiatan',
            'setting_vakasi_id'   => 'nullable|exists:setting_vakasi,id',
            'vakasi_override'     => 'nullable|numeric|min:0',
            'wajib_laporan'       => 'boolean',
            // Penerima
            'penerima_ids'        => 'nullable|array',
            'penerima_ids.*'      => 'integer',
            // Vakasi override per penerima (opsional)
            'vakasi_per_penerima' => 'nullable|array',
        ]);

        $tugas = TugasTambahan::create([
            'judul'             => $data['judul'],
            'deskripsi'         => $data['deskripsi'] ?? null,
            'tanggal_mulai'     => $data['tanggal_mulai'],
            'tanggal_selesai'   => $data['tanggal_selesai'] ?? null,
            'tipe'              => $data['tipe'],
            'tipe_pengerjaan'   => $data['tipe_pengerjaan'] ?? 'mandiri',
            'setting_vakasi_id' => $data['setting_vakasi_id'] ?? null,
            'vakasi_override'   => $data['vakasi_override'] ?? null,
            'wajib_laporan'     => $data['wajib_laporan'] ?? false,
            'status'            => 'aktif',
            'dibuat_oleh'       => auth()->id(),
        ]);

        // Tentukan penerima berdasarkan tipe
        $guruIds = match ($data['tipe']) {
            'semua'    => TenagaPendidik::aktif()->pluck('id'),
            'jabatan'  => $this->getGuruIdsByJabatan($data['penerima_ids'] ?? []),
            'individu' => collect($data['penerima_ids'] ?? []),
        };

        // Buat penugasan per penerima
        $vakasiPerPenerima = $request->vakasi_per_penerima ?? [];
        foreach ($guruIds as $guruId) {
            $vakasiInfo = $vakasiPerPenerima[$guruId] ?? [];
            PenugasanTambahan::create([
                'tugas_tambahan_id'  => $tugas->id,
                'tenaga_pendidik_id' => $guruId,
                'status_pengerjaan'  => 'belum',
                'setting_vakasi_id'  => $vakasiInfo['setting_vakasi_id'] ?? null,
                'vakasi_override'    => $vakasiInfo['vakasi_override'] ?? null,
            ]);
        }

        // Notifikasi 'tugas.baru' ke tiap penerima (menghormati toggle Setting Notifikasi).
        $penerima = TenagaPendidik::with('user')->whereIn('id', $guruIds)->get();
        foreach ($penerima as $tp) {
            if (!$tp->user) continue;
            \App\Services\NotifikasiService::event('tugas.baru', [
                'user'  => $tp->user,
                'judul' => 'Tugas Baru',
                'pesan' => 'Anda mendapat tugas: ' . $tugas->judul
                    . ($tugas->tanggal_selesai ? ' (deadline ' . \Carbon\Carbon::parse($tugas->tanggal_selesai)->format('d/m/Y') . ')' : ''),
                'tipe'  => 'tugas_baru',
                'data'  => ['type' => 'tugas', 'route' => '/tugas', 'tugas_id' => $tugas->id],
                'dedup' => "tugas-{$tugas->id}",
            ]);
        }

        return redirect()->route('admin.smart-payroll.tugas-tambahan.index')
            ->with('success', "Tugas dibuat dan dikirim ke {$guruIds->count()} orang.");
    }

    public function show(TugasTambahan $tugasTambahan)
    {
        $penugasan = $tugasTambahan->penugasan()
            ->with([
                'tenagaPendidik.user',
                'tenagaPendidik.jabatan',
                'settingVakasi',
                'diverifikasiOleh',
                'kegiatanAbsensi',
            ])
            ->orderBy('status_pengerjaan')
            ->get()
            ->map(fn($p) => [
                'id'                  => $p->id,
                'guru_id'             => $p->tenaga_pendidik_id,
                'guru_nama'           => $p->tenagaPendidik->user->name,
                'guru_foto'           => $p->tenagaPendidik->user->foto
                    ? asset('storage/'.$p->tenagaPendidik->user->foto) : null,
                'guru_jabatan'        => $p->tenagaPendidik->jabatan?->nama_jabatan ?? '—',
                'status_pengerjaan'   => $p->status_pengerjaan,
                'disetujui'           => $p->disetujui,
                'laporan'             => $p->laporan,
                'dikerjakan_pada'     => $p->dikerjakan_pada?->format('d M Y H:i'),
                'catatan_verifikasi'  => $p->catatan_verifikasi,
                // Vakasi per penerima
                'nominal_vakasi'      => $p->getNominalVakasi(),
                'sumber_vakasi'       => $p->sumber_vakasi,
                'vakasi_override'     => $p->vakasi_override,
                'setting_vakasi_nama' => $p->settingVakasi?->nama,
                'setting_vakasi_id'   => $p->setting_vakasi_id,
                // Bukti pengerjaan
                'bukti_tipe'          => $p->bukti_tipe,
                'link_bukti'          => $p->link_bukti,
                'teks_bukti'          => $p->teks_bukti,
                'file_laporan_url'    => $p->file_laporan_url,
                // Absensi kegiatan (jika tipe_pengerjaan=absen_kegiatan)
                // CATATAN: null-safe ?-> hanya melindungi 1 level; method berikutnya
                // (whereIn, count) harus dibungkus dengan pemeriksaan eksplisit agar
                // tidak memanggil method pada null (TypeError) untuk tugas mandiri.
                'kegiatan_id'         => $p->kegiatanAbsensi?->id,
                'kegiatan_status'     => $p->kegiatanAbsensi?->status,
                'kegiatan_nama'       => $p->kegiatanAbsensi?->nama_kegiatan,
                'kegiatan_hadir'      => $p->kegiatanAbsensi
                    ? $p->kegiatanAbsensi->peserta()
                        ->whereIn('status_kehadiran', ['hadir', 'terlambat'])->count()
                    : null,
                'kegiatan_total'      => $p->kegiatanAbsensi
                    ? $p->kegiatanAbsensi->peserta()->count()
                    : null,
            ]);

        return Inertia::render('Admin/SmartPayroll/TugasTambahan/Show', [
            'tugas' => [
                'id'               => $tugasTambahan->id,
                'judul'            => $tugasTambahan->judul,
                'deskripsi'        => $tugasTambahan->deskripsi,
                'tipe'             => $tugasTambahan->tipe,
                'tipe_pengerjaan'  => $tugasTambahan->tipe_pengerjaan ?? 'mandiri',
                'status'           => $tugasTambahan->status,
                'tanggal_mulai'    => $tugasTambahan->tanggal_mulai?->format('d M Y'),
                'tanggal_selesai'  => $tugasTambahan->tanggal_selesai?->format('d M Y'),
                'nominal_vakasi'        => $tugasTambahan->getNominalVakasi(),
                'wajib_laporan'         => $tugasTambahan->wajib_laporan,
                'dibuat_oleh'           => $tugasTambahan->dibuatOleh?->name,
                // Skema vakasi: untuk absen_kegiatan, peserta juga dapat vakasi yg sama
                'vakasi_peserta_nominal'=> $tugasTambahan->tipe_pengerjaan === 'absen_kegiatan'
                    ? $tugasTambahan->getNominalVakasi()
                    : null,
            ],
            'penugasan'  => $penugasan,
            'guru'       => $this->getGuruList(),
            'vakasi'     => $this->getVakasiList(),
        ]);
    }

    public function edit(TugasTambahan $tugasTambahan)
    {
        // Kirim field eksplisit — JANGAN kirim full model agar:
        // 1. tanggal_mulai_raw / tanggal_selesai_raw tersedia dalam format Y-m-d
        //    (Carbon yang diserialisasi Inertia menjadi ISO string, bukan format input[date])
        // 2. setting_vakasi_id dan vakasi_override konsisten dengan yang dikirim form
        return Inertia::render('Admin/SmartPayroll/TugasTambahan/Form', [
            'tugas'   => [
                'id'                  => $tugasTambahan->id,
                'judul'               => $tugasTambahan->judul,
                'deskripsi'           => $tugasTambahan->deskripsi,
                'tanggal_mulai'       => $tugasTambahan->tanggal_mulai?->format('d M Y'),
                'tanggal_mulai_raw'   => $tugasTambahan->tanggal_mulai?->format('Y-m-d'),
                'tanggal_selesai'     => $tugasTambahan->tanggal_selesai?->format('d M Y'),
                'tanggal_selesai_raw' => $tugasTambahan->tanggal_selesai?->format('Y-m-d'),
                'tipe'                => $tugasTambahan->tipe,
                'tipe_pengerjaan'     => $tugasTambahan->tipe_pengerjaan ?? 'mandiri',
                'setting_vakasi_id'   => $tugasTambahan->setting_vakasi_id,   // null jika tidak ada
                'vakasi_override'     => $tugasTambahan->vakasi_override,     // null jika tidak ada
                'wajib_laporan'       => $tugasTambahan->wajib_laporan,
                'status'              => $tugasTambahan->status,
            ],
            'guru'    => $this->getGuruList(),
            'jabatan' => Jabatan::aktif()->get(['id', 'nama_jabatan', 'kode_jabatan', 'tipe']),
            'vakasi'  => $this->getVakasiList(),
        ]);
    }

    public function update(Request $request, TugasTambahan $tugasTambahan)
    {
        // ── Sanitasi: empty-string → null ──────────────────────────────────────
        // Dilakukan SEBELUM validate() agar cast Eloquent tidak error:
        //   setting_vakasi_id: '' → QueryException (FK integer)
        //   vakasi_override: ''   → 0.0 (float cast, korupsi data diam-diam)
        //   tanggal_selesai: ''   → InvalidFormatException (date cast Carbon::parse(''))
        //   deskripsi: ''         → simpan null, bukan string kosong
        $nullableStrings = ['setting_vakasi_id', 'vakasi_override', 'tanggal_selesai', 'deskripsi'];
        foreach ($nullableStrings as $field) {
            $val = $request->input($field);
            if ($val === '' || $val === null) {
                $request->merge([$field => null]);
            }
        }

        // ── Logic absen_kegiatan vs mandiri ────────────────────────────────────
        // "wajib_laporan" hanya berlaku untuk tugas mandiri.
        // Untuk absen_kegiatan, guru tidak upload bukti — tugas selesai lewat absensi kegiatan.
        // Paksa false agar tidak ada inkonsistensi data.
        if ($tugasTambahan->tipe_pengerjaan === 'absen_kegiatan') {
            $request->merge(['wajib_laporan' => false]);
        }

        $data = $request->validate([
            'judul'             => 'required|string|max:200',
            'deskripsi'         => 'nullable|string',
            'tanggal_selesai'   => 'nullable|date|after_or_equal:' . ($tugasTambahan->tanggal_mulai?->format('Y-m-d') ?? '2000-01-01'),
            'setting_vakasi_id' => 'nullable|exists:setting_vakasi,id',
            'vakasi_override'   => 'nullable|numeric|min:0',
            'wajib_laporan'     => 'boolean',
            'status'            => 'required|in:draft,aktif,selesai,dibatalkan',
        ]);

        $tugasTambahan->update($data);

        return redirect()
            ->route('admin.smart-payroll.tugas-tambahan.show', $tugasTambahan->id)
            ->with('success', 'Tugas tambahan diperbarui.');
    }

    public function destroy(TugasTambahan $tugasTambahan)
    {
        $tugasTambahan->update(['status' => 'dibatalkan']);
        return redirect()
            ->route('admin.smart-payroll.tugas-tambahan.index')
            ->with('success', 'Tugas dibatalkan.');
    }

    /**
     * Assign guru tambahan ke tugas yang sudah berjalan.
     */
    public function assign(TugasTambahan $tugasTambahan, Request $request)
    {
        $data = $request->validate([
            'tenaga_pendidik_ids'   => 'required|array',
            'tenaga_pendidik_ids.*' => 'exists:tenaga_pendidik,id',
            'setting_vakasi_id'     => 'nullable|exists:setting_vakasi,id',
            'vakasi_override'       => 'nullable|numeric|min:0',
        ]);

        $ditambahkan = 0;
        foreach ($data['tenaga_pendidik_ids'] as $id) {
            // Untuk absen_kegiatan: satu guru boleh menjadi pengabsen lebih dari sekali —
            // setiap baris penugasan mewakili satu sesi kegiatan yang berbeda.
            // Tidak ada batasan duplikasi; tiap assign = baris PenugasanTambahan baru.
            PenugasanTambahan::create([
                'tugas_tambahan_id'  => $tugasTambahan->id,
                'tenaga_pendidik_id' => $id,
                'status_pengerjaan'  => 'belum',
                'setting_vakasi_id'  => $data['setting_vakasi_id'] ?? null,
                'vakasi_override'    => $data['vakasi_override'] ?? null,
            ]);
            $ditambahkan++;
        }

        return redirect()
            ->route('admin.smart-payroll.tugas-tambahan.show', $tugasTambahan->id)
            ->with('success', "{$ditambahkan} penugasan pengabsen berhasil ditambahkan.");
    }

    /**
     * Update vakasi untuk penugasan tertentu (per penerima).
     */
    public function updateVakasiPenerima(PenugasanTambahan $penugasan, Request $request)
    {
        $data = $request->validate([
            'setting_vakasi_id' => 'nullable|exists:setting_vakasi,id',
            'vakasi_override'   => 'nullable|numeric|min:0',
        ]);

        $penugasan->update($data);

        return redirect()
            ->route('admin.smart-payroll.tugas-tambahan.show', $penugasan->tugas_tambahan_id)
            ->with('success', 'Vakasi penerima diperbarui.');
    }

    /**
     * Verifikasi pengerjaan tugas.
     */
    public function verifikasi(PenugasanTambahan $penugasan, Request $request)
    {
        $data = $request->validate([
            'disetujui'          => 'required|boolean',
            'catatan_verifikasi' => 'nullable|string|max:500',
        ]);

        $penugasan->update([
            'disetujui'          => $data['disetujui'],
            'catatan_verifikasi' => $data['catatan_verifikasi'] ?? null,
            'diverifikasi_oleh'  => auth()->id(),
        ]);

        // BUG FIX: Saat disetujui, pastikan status_pengerjaan = 'selesai' DAN
        // dilaporkan_pada terisi — karena hitungVakasiTugasTambahan() menyaring
        // WHERE status_pengerjaan = 'selesai' AND dilaporkan_pada IS NOT NULL.
        // Tanpa ini, vakasi guru tidak pernah masuk ke slip gaji meski sudah disetujui.
        if ($data['disetujui']) {
            $penugasan->update([
                'status_pengerjaan' => 'selesai',
                'dilaporkan_pada'   => $penugasan->dilaporkan_pada ?? TimezoneHelper::now(),
            ]);
        }

        $msg = $data['disetujui'] ? 'Tugas disetujui. Vakasi akan masuk ke slip gaji bulan ini.' : 'Tugas ditolak.';

        return redirect()
            ->route('admin.smart-payroll.tugas-tambahan.show', $penugasan->tugas_tambahan_id)
            ->with('success', $msg);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function getGuruList(): \Illuminate\Support\Collection
    {
        return TenagaPendidik::aktif()
            ->with(['user', 'jabatan', 'jabatanGuru.jabatan'])
            ->get()
            ->map(function ($g) {
                // Jabatan aktif dari pivot (support rangkap)
                $jabatanAktif = $g->jabatanGuru
                    ->filter(fn($jg) => !$jg->berlaku_selesai || $jg->berlaku_selesai->isFuture())
                    ->map(fn($jg) => $jg->jabatan?->nama_jabatan)
                    ->filter()->values();

                return [
                    'id'      => $g->id,
                    'nama'    => $g->user->name,
                    'jabatan' => $jabatanAktif->isNotEmpty()
                        ? $jabatanAktif->join(', ')
                        : ($g->jabatan?->nama_jabatan ?? '—'),
                    'jabatan_ids' => $g->jabatanGuru
                        ->filter(fn($jg) => !$jg->berlaku_selesai || $jg->berlaku_selesai->isFuture())
                        ->pluck('jabatan_id')
                        ->toArray() ?: [$g->jabatan_id],
                    'foto'    => $g->user->foto ? asset('storage/'.$g->user->foto) : null,
                ];
            });
    }

    private function getVakasiList(): \Illuminate\Support\Collection
    {
        return SettingVakasi::aktif()
            ->byTipe('tugas_tambahan')
            ->orderBy('lingkup')
            ->orderBy('nama')
            ->get()
            ->map(fn($v) => [
                'id'           => $v->id,
                'nama'         => $v->nama,
                'nominal'      => $v->nominal,
                'satuan'       => $v->satuan,
                'lingkup'      => $v->lingkup ?? 'semua',
                'lingkup_label'=> $v->lingkup_label,
                'jabatan_ids'  => is_array($v->jabatan_ids) ? $v->jabatan_ids : [],
                'tenaga_pendidik_ids' => is_array($v->tenaga_pendidik_ids) ? $v->tenaga_pendidik_ids : [],
            ]);
    }

    private function getGuruIdsByJabatan(array $jabatanIds): \Illuminate\Support\Collection
    {
        if (empty($jabatanIds)) return collect();

        // Cari via pivot jabatan_guru dulu
        $viaJabatanGuru = JabatanGuru::whereIn('jabatan_id', $jabatanIds)
            ->whereNull('berlaku_selesai')
            ->pluck('tenaga_pendidik_id');

        // Fallback: via kolom jabatan_id lama
        $viaLama = TenagaPendidik::aktif()
            ->whereIn('jabatan_id', $jabatanIds)
            ->pluck('id');

        return $viaJabatanGuru->merge($viaLama)->unique()->values();
    }
}
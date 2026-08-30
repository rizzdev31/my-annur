<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\TenagaPendidik;
use App\Models\Jabatan;
use App\Models\User;
use App\Models\AbsensiHarian;
use App\Models\SettingJamKerja;
use App\Services\StatusKepegawaianService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class TenagaPendidikController extends Controller
{
    public function __construct(
        private readonly StatusKepegawaianService $statusService
    ) {}

    public function index(Request $request)
    {
        // Load jabatanGuru pivot agar bisa tampilkan multi jabatan di kartu
        $query = TenagaPendidik::with(['user', 'jabatan', 'jabatanGuru.jabatan'])
            ->when($request->search, function ($q) use ($request) {
                $q->whereHas('user', fn($u) =>
                    $u->where('name', 'like', "%{$request->search}%")
                      ->orWhere('email', 'like', "%{$request->search}%")
                )->orWhere('nip', 'like', "%{$request->search}%");
            })
            ->when($request->jabatan_id, fn($q) =>
                // Filter support multi jabatan — cek di pivot dulu, fallback ke jabatan_id
                $q->where(fn($q2) =>
                    $q2->where('jabatan_id', $request->jabatan_id)
                       ->orWhereHas('jabatanGuru', fn($q3) =>
                           $q3->where('jabatan_id', $request->jabatan_id)
                              ->whereNull('berlaku_selesai')
                       )
                )
            )
            ->when($request->jenis_guru, fn($q) => $q->where('jenis_guru', $request->jenis_guru))
            ->when($request->status_kepegawaian, fn($q) =>
                $q->where('status_kepegawaian', $request->status_kepegawaian)
            )
            ->orderBy('created_at', 'desc');

        $tenagaPendidik = $query->paginate(12)->withQueryString();

        $tenagaPendidik->through(function ($g) {
            // Jabatan aktif dari pivot, fallback ke jabatan_id lama
            $jabatanAktif = $g->jabatanGuru
                ->filter(fn($jg) => !$jg->berlaku_selesai || $jg->berlaku_selesai->isFuture())
                ->sortByDesc('adalah_utama')
                ->values();

            $jabatanLabel = $jabatanAktif->isNotEmpty()
                ? $jabatanAktif->map(fn($jg) => $jg->jabatan?->nama_jabatan)->filter()->join(', ')
                : ($g->jabatan?->nama_jabatan ?? '—');

            return [
                'id'                   => $g->id,
                'nama'                 => $g->user->name,
                'email'                => $g->user->email,
                'username'             => $g->user->username,
                'foto'                 => $g->user->foto ? asset('storage/' . $g->user->foto) : null,
                'nip'                  => $g->nip,
                // Jabatan utama untuk backward compat
                'jabatan'              => $g->jabatan?->nama_jabatan ?? '—',
                'jabatan_id'           => $g->jabatan_id,
                // Multi jabatan untuk display badge
                'jabatan_aktif'        => $jabatanAktif->map(fn($jg) => [
                    'pivot_id'     => $jg->id,
                    'jabatan_id'   => $jg->jabatan_id,
                    'nama_jabatan' => $jg->jabatan?->nama_jabatan ?? '—',
                    'kode_jabatan' => $jg->jabatan?->kode_jabatan ?? '',
                    'tipe'         => $jg->jabatan?->tipe ?? '',
                    'adalah_utama' => $jg->adalah_utama,
                ])->values(),
                'jabatan_label'        => $jabatanLabel,
                'is_rangkap'           => $jabatanAktif->count() > 1,
                'jenis_kelamin'        => $g->jenis_kelamin,
                'jenis_guru'           => $g->jenis_guru,
                'no_hp'                => $g->no_hp,
                'tanggal_masuk'        => $g->tanggal_masuk?->format('d M Y'),
                'is_aktif'             => $g->is_aktif,
                'status_kepegawaian'   => $g->status_kepegawaian ?? 'aktif',
                'status_badge'         => $g->status_badge,
                'bisa_aktif_kembali'   => $g->bisaAktifKembali(),
                'alasan_nonaktif'      => $g->alasan_nonaktif,
            ];
        });

        return Inertia::render('Admin/Master/TenagaPendidik/Index', [
            'tenagaPendidik' => $tenagaPendidik,
            'jabatan'        => Jabatan::aktif()->get(['id', 'nama_jabatan']),
            'filters'        => $request->only(['search', 'jabatan_id', 'jenis_guru', 'status_kepegawaian']),
            'summary'        => [
                'total'              => TenagaPendidik::count(),
                'aktif'              => TenagaPendidik::where('status_kepegawaian', 'aktif')->count(),
                'cuti'               => TenagaPendidik::whereIn('status_kepegawaian', ['cuti', 'cuti_sakit', 'nonaktif_sementara'])->count(),
                'resign_pensiun'     => TenagaPendidik::whereIn('status_kepegawaian', ['resign', 'pensiun', 'meninggal'])->count(),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Master/TenagaPendidik/Form', [
            'jabatan'  => Jabatan::aktif()->get(['id', 'nama_jabatan', 'kode_jabatan', 'tipe']),
            'jamKerja' => SettingJamKerja::where('is_aktif', true)->get(['id', 'nama', 'jam_masuk', 'jam_pulang']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:100',
            'email'               => 'required|email|unique:users,email',
            'username'            => 'required|string|max:50|unique:users,username|alpha_dash',
            'password'            => 'required|string|min:8|confirmed',
            'jabatan_id'          => 'required|exists:jabatan,id',
            'setting_jam_kerja_id'=> 'nullable|exists:setting_jam_kerja,id',
            'is_mukim'            => 'boolean',
            'hari_libur'          => 'nullable|array',
            'hari_libur.*'        => 'in:senin,selasa,rabu,kamis,jumat,sabtu,ahad',
            'nip'                 => 'required|string|max:30|unique:tenaga_pendidik,nip',
            'jenis_kelamin'       => 'required|in:L,P',
            'tanggal_masuk'       => 'required|date',
            'jenis_guru'          => 'required|in:mukim,non_mukim',
            'nik'                 => 'nullable|string|max:16',
            'tempat_lahir'        => 'nullable|string|max:100',
            'tanggal_lahir'       => 'nullable|date|before:today',
            'pendidikan_terakhir' => 'nullable|in:SMA,D3,S1,S2,S3,Pesantren',
            'jurusan'             => 'nullable|string|max:100',
            'no_hp'               => 'nullable|string|max:20',
            'alamat'              => 'nullable|string|max:500',
            'no_rekening'         => 'nullable|string|max:30',
            'nama_bank'           => 'nullable|string|max:50',
            'nama_rekening'       => 'nullable|string|max:100',
            'foto'                => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        DB::transaction(function () use ($request, $validated) {
            $fotoPath = $request->hasFile('foto')
                ? $request->file('foto')->store('foto-profil', 'public')
                : null;

            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'username' => $validated['username'],
                'password' => Hash::make($validated['password']),
                'role'     => 'tenaga_pendidik',
                'status'   => 'aktif',
                'foto'     => $fotoPath,
            ]);

            $guru = TenagaPendidik::create(array_merge(
                collect($validated)->except(['name', 'email', 'username', 'password', 'password_confirmation', 'foto'])->toArray(),
                [
                    'user_id'            => $user->id,
                    'is_aktif'           => true,
                    'status_kepegawaian' => 'aktif',
                ]
            ));

            // Insert jabatan awal ke pivot jabatan_guru (sebagai jabatan utama)
            \App\Models\JabatanGuru::create([
                'tenaga_pendidik_id' => $guru->id,
                'jabatan_id'         => $validated['jabatan_id'],
                'adalah_utama'       => true,
                'berlaku_mulai'      => $validated['tanggal_masuk'],
                'berlaku_selesai'    => null,
                'keterangan'         => 'Jabatan awal saat pendaftaran',
                'ditetapkan_oleh'    => auth()->id(),
            ]);
        });

        return redirect()->route('admin.master.tenaga-pendidik.index')
            ->with('success', "{$request->name} berhasil ditambahkan.");
    }

    public function show(TenagaPendidik $tenagaPendidik)
    {
        $tenagaPendidik->load([
            'user',
            'jabatan',
            'jabatanGuru.jabatan',   // semua jabatan via pivot
        ]);

        $rekapBulanIni = AbsensiHarian::where('tenaga_pendidik_id', $tenagaPendidik->id)
            ->byBulan(now()->month, now()->year)->get();

        // Jabatan aktif dari pivot (support rangkap)
        $jabatanAktif = $tenagaPendidik->jabatanGuru
            ->filter(fn($jg) => !$jg->berlaku_selesai || $jg->berlaku_selesai->isFuture())
            ->sortByDesc('adalah_utama')
            ->map(fn($jg) => [
                'pivot_id'       => $jg->id,
                'jabatan_id'     => $jg->jabatan_id,
                'nama_jabatan'   => $jg->jabatan?->nama_jabatan ?? '—',
                'kode_jabatan'   => $jg->jabatan?->kode_jabatan ?? '',
                'tipe'           => $jg->jabatan?->tipe ?? '',
                'adalah_utama'   => $jg->adalah_utama,
                'berlaku_mulai'  => $jg->berlaku_mulai?->format('d M Y'),
                'berlaku_selesai'=> $jg->berlaku_selesai?->format('d M Y'),
                'keterangan'     => $jg->keterangan,
            ])->values();

        // Riwayat jabatan (sudah berakhir)
        $riwayatJabatan = $tenagaPendidik->jabatanGuru
            ->filter(fn($jg) => $jg->berlaku_selesai && $jg->berlaku_selesai->isPast())
            ->sortByDesc('berlaku_selesai')
            ->map(fn($jg) => [
                'pivot_id'       => $jg->id,
                'nama_jabatan'   => $jg->jabatan?->nama_jabatan ?? '—',
                'adalah_utama'   => $jg->adalah_utama,
                'berlaku_mulai'  => $jg->berlaku_mulai?->format('d M Y'),
                'berlaku_selesai'=> $jg->berlaku_selesai?->format('d M Y'),
                'keterangan'     => $jg->keterangan,
            ])->values();

        // Gaji pokok detail per jabatan — safe jika tabel belum lengkap
        try {
            $detailGajiPokok = $tenagaPendidik->getDetailGajiPokokPerJabatan()
                ->map(fn($d) => [
                    'jabatan_id'   => $d['jabatan_id'],
                    'nama_jabatan' => $d['nama_jabatan'],
                    'nominal'      => $d['nominal'],
                    'sumber'       => $d['sumber'],
                ]);
        } catch (\Exception $e) {
            $detailGajiPokok = collect();
        }

        return Inertia::render('Admin/Master/TenagaPendidik/Show', [
            'guru' => [
                'id'                  => $tenagaPendidik->id,
                'nama'                => $tenagaPendidik->user->name,
                'email'               => $tenagaPendidik->user->email,
                'username'            => $tenagaPendidik->user->username,
                'foto'                => $tenagaPendidik->user->foto ? asset('storage/' . $tenagaPendidik->user->foto) : null,
                'nip'                 => $tenagaPendidik->nip,
                'nik'                 => $tenagaPendidik->nik,
                // Jabatan utama (backward compat)
                'jabatan'             => $tenagaPendidik->jabatan?->nama_jabatan ?? '—',
                'jabatan_tipe'        => $tenagaPendidik->jabatan?->tipe ?? '',
                // Multi jabatan
                'jabatan_aktif'       => $jabatanAktif,
                'riwayat_jabatan'     => $riwayatJabatan,
                'detail_gaji_pokok'   => $detailGajiPokok,
                'total_gaji_pokok'    => $detailGajiPokok->sum('nominal'),
                'jenis_kelamin'       => $tenagaPendidik->jenis_kelamin,
                'jenis_guru'          => $tenagaPendidik->jenis_guru,
                'tempat_lahir'        => $tenagaPendidik->tempat_lahir,
                'tanggal_lahir'       => $tenagaPendidik->tanggal_lahir?->format('d M Y'),
                'pendidikan_terakhir' => $tenagaPendidik->pendidikan_terakhir,
                'jurusan'             => $tenagaPendidik->jurusan,
                'no_hp'               => $tenagaPendidik->no_hp,
                'alamat'              => $tenagaPendidik->alamat,
                'tanggal_masuk'       => $tenagaPendidik->tanggal_masuk?->format('d M Y'),
                'tanggal_keluar'      => $tenagaPendidik->tanggal_keluar?->format('d M Y'),
                'no_rekening'         => $tenagaPendidik->no_rekening,
                'nama_bank'           => $tenagaPendidik->nama_bank,
                'nama_rekening'       => $tenagaPendidik->nama_rekening,
                'is_aktif'            => $tenagaPendidik->is_aktif,
                'status_kepegawaian'  => $tenagaPendidik->status_kepegawaian ?? 'aktif',
                'status_badge'        => $tenagaPendidik->status_badge,
                'alasan_nonaktif'     => $tenagaPendidik->alasan_nonaktif,
                'tanggal_nonaktif'    => $tenagaPendidik->tanggal_nonaktif?->format('d M Y'),
                'bisa_aktif_kembali'  => $tenagaPendidik->bisaAktifKembali(),
            ],
            // Data untuk assign jabatan
            'jabatanList' => \App\Models\Jabatan::aktif()
                ->orderBy('tipe')->orderBy('nama_jabatan')
                ->get(['id', 'nama_jabatan', 'kode_jabatan', 'tipe']),
            'rekapBulanIni' => [
                'hadir'     => $rekapBulanIni->whereIn('status', ['hadir', 'terlambat'])->count(),
                'terlambat' => $rekapBulanIni->where('status', 'terlambat')->count(),
                'izin'      => $rekapBulanIni->where('status', 'izin')->count(),
                'sakit'     => $rekapBulanIni->where('status', 'sakit')->count(),
                'alfa'      => $rekapBulanIni->where('status', 'alfa')->count(),
            ],
            'riwayatStatus' => $this->statusService->getRiwayat($tenagaPendidik),
        ]);
    }

    public function edit(TenagaPendidik $tenagaPendidik)
    {
        return Inertia::render('Admin/Master/TenagaPendidik/Form', [
            'guru' => [
                'id'                  => $tenagaPendidik->id,
                'name'                => $tenagaPendidik->user->name,
                'email'               => $tenagaPendidik->user->email,
                'username'            => $tenagaPendidik->user->username,
                'foto'                => $tenagaPendidik->user->foto ? asset('storage/' . $tenagaPendidik->user->foto) : null,
                'nip'                 => $tenagaPendidik->nip,
                'nik'                 => $tenagaPendidik->nik,
                'jabatan_id'          => $tenagaPendidik->jabatan_id,
                'setting_jam_kerja_id'=> $tenagaPendidik->setting_jam_kerja_id,
                'is_mukim'            => $tenagaPendidik->is_mukim,
                'hari_libur'          => $tenagaPendidik->hari_libur ?? [],
                'hari_libur_diajukan' => $tenagaPendidik->hari_libur_diajukan,
                'jenis_kelamin'       => $tenagaPendidik->jenis_kelamin,
                'jenis_guru'          => $tenagaPendidik->jenis_guru,
                'tempat_lahir'        => $tenagaPendidik->tempat_lahir,
                'tanggal_lahir'       => $tenagaPendidik->tanggal_lahir?->format('Y-m-d'),
                'pendidikan_terakhir' => $tenagaPendidik->pendidikan_terakhir,
                'jurusan'             => $tenagaPendidik->jurusan,
                'no_hp'               => $tenagaPendidik->no_hp,
                'alamat'              => $tenagaPendidik->alamat,
                'tanggal_masuk'       => $tenagaPendidik->tanggal_masuk?->format('Y-m-d'),
                'no_rekening'         => $tenagaPendidik->no_rekening,
                'nama_bank'           => $tenagaPendidik->nama_bank,
                'nama_rekening'       => $tenagaPendidik->nama_rekening,
            ],
            'jabatan'  => Jabatan::aktif()->get(['id', 'nama_jabatan', 'kode_jabatan', 'tipe']),
            'jamKerja' => SettingJamKerja::where('is_aktif', true)->get(['id', 'nama', 'jam_masuk', 'jam_pulang']),
        ]);
    }

    public function update(Request $request, TenagaPendidik $tenagaPendidik)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:100',
            'email'               => 'required|email|unique:users,email,' . $tenagaPendidik->user_id,
            'username'            => 'required|string|max:50|unique:users,username,' . $tenagaPendidik->user_id . '|alpha_dash',
            'password'            => 'nullable|string|min:8|confirmed',
            'jabatan_id'          => 'required|exists:jabatan,id',
            'setting_jam_kerja_id'=> 'nullable|exists:setting_jam_kerja,id',
            'is_mukim'            => 'boolean',
            'hari_libur'          => 'nullable|array',
            'hari_libur.*'        => 'in:senin,selasa,rabu,kamis,jumat,sabtu,ahad',
            'nip'                 => 'required|string|max:30|unique:tenaga_pendidik,nip,' . $tenagaPendidik->id,
            'nik'                 => 'nullable|string|max:16',
            'jenis_kelamin'       => 'required|in:L,P',
            'tanggal_masuk'       => 'required|date',
            'jenis_guru'          => 'required|in:mukim,non_mukim',
            'tempat_lahir'        => 'nullable|string|max:100',
            'tanggal_lahir'       => 'nullable|date|before:today',
            'pendidikan_terakhir' => 'nullable|in:SMA,D3,S1,S2,S3,Pesantren',
            'jurusan'             => 'nullable|string|max:100',
            'no_hp'               => 'nullable|string|max:20',
            'alamat'              => 'nullable|string|max:500',
            'no_rekening'         => 'nullable|string|max:30',
            'nama_bank'           => 'nullable|string|max:50',
            'nama_rekening'       => 'nullable|string|max:100',
            'foto'                => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        DB::transaction(function () use ($request, $validated, $tenagaPendidik) {
            $userUpdate = [
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'username' => $validated['username'],
            ];

            if (!empty($validated['password'])) {
                $userUpdate['password'] = Hash::make($validated['password']);
            }

            if ($request->hasFile('foto')) {
                if ($tenagaPendidik->user->foto) {
                    Storage::disk('public')->delete($tenagaPendidik->user->foto);
                }
                $userUpdate['foto'] = $request->file('foto')->store('foto-profil', 'public');
            }

            $tenagaPendidik->user->update($userUpdate);
            $tenagaPendidik->update(
                collect($validated)->except(['name', 'email', 'username', 'password', 'password_confirmation', 'foto'])->toArray()
            );

            // Sinkronkan jabatan UTAMA di pivot jabatan_guru (sumber tampilan) dengan
            // jabatan_id terpilih — tanpa ini, ubah jabatan lewat form edit tak terlihat
            // di kolom karena tampilan membaca dari pivot.
            $utama = $tenagaPendidik->jabatanGuru()->where('adalah_utama', true)->first();
            if ($utama) {
                if ((int) $utama->jabatan_id !== (int) $validated['jabatan_id']) {
                    $utama->update(['jabatan_id' => $validated['jabatan_id']]);
                }
            } else {
                \App\Models\JabatanGuru::create([
                    'tenaga_pendidik_id' => $tenagaPendidik->id,
                    'jabatan_id'         => $validated['jabatan_id'],
                    'adalah_utama'       => true,
                    'berlaku_mulai'      => $validated['tanggal_masuk'],
                    'berlaku_selesai'    => null,
                    'keterangan'         => 'Sinkron dari edit data pegawai',
                    'ditetapkan_oleh'    => auth()->id(),
                ]);
            }
        });

        return redirect()->route('admin.master.tenaga-pendidik.index')
            ->with('success', "Data {$request->name} berhasil diperbarui.");
    }

    /** Setujui usulan hari libur guru → salin ke hari_libur (berlaku setelah generate). */
    public function setujuiLibur(TenagaPendidik $tenagaPendidik): \Illuminate\Http\JsonResponse
    {
        $diajukan = $tenagaPendidik->hari_libur_diajukan;
        if ($diajukan === null) {
            return response()->json(['success' => false, 'message' => 'Tidak ada pengajuan libur.'], 422);
        }
        $tenagaPendidik->update(['hari_libur' => $diajukan, 'hari_libur_diajukan' => null]);

        if ($tenagaPendidik->user) {
            \App\Services\NotifikasiService::kirim(
                $tenagaPendidik->user->id, 'Hari Libur Disetujui',
                'Hari libur Anda: ' . (empty($diajukan) ? '(tidak ada)' : implode(', ', $diajukan))
                    . '. Berlaku setelah admin men-generate jam kerja.',
                'izin', ['type' => 'libur', 'route' => '/profil']
            );
        }
        return response()->json(['success' => true, 'message' => 'Pengajuan libur disetujui.', 'data' => ['hari_libur' => $tenagaPendidik->hari_libur]]);
    }

    /** Tolak usulan hari libur guru (hapus pengajuan, hari_libur tak berubah). */
    public function tolakLibur(TenagaPendidik $tenagaPendidik): \Illuminate\Http\JsonResponse
    {
        $tenagaPendidik->update(['hari_libur_diajukan' => null]);
        if ($tenagaPendidik->user) {
            \App\Services\NotifikasiService::kirim(
                $tenagaPendidik->user->id, 'Pengajuan Hari Libur Ditolak',
                'Usulan hari libur Anda belum disetujui admin. Silakan koordinasi.',
                'izin', ['type' => 'libur', 'route' => '/profil']
            );
        }
        return response()->json(['success' => true, 'message' => 'Pengajuan libur ditolak.']);
    }

    public function destroy(TenagaPendidik $tenagaPendidik)
    {
        // Cegah hapus jika masih punya data penggajian
        if ($tenagaPendidik->penggajian()->count() > 0) {
            return back()->with('error',
                "{$tenagaPendidik->user->name} tidak bisa dihapus karena memiliki riwayat penggajian. " .
                "Gunakan fitur Resign atau Nonaktif."
            );
        }

        DB::transaction(function () use ($tenagaPendidik) {
            if ($tenagaPendidik->user->foto) {
                Storage::disk('public')->delete($tenagaPendidik->user->foto);
            }
            $tenagaPendidik->delete();
            $tenagaPendidik->user->delete();
        });

        return redirect()->route('admin.master.tenaga-pendidik.index')
            ->with('success', 'Tenaga pendidik berhasil dihapus.');
    }

    // ── Status Kepegawaian ────────────────────────────────────────────────────

    /**
     * Ubah status kepegawaian (cuti, resign, nonaktif, dll).
     * Endpoint: POST /admin/master/tenaga-pendidik/{id}/ubah-status
     */
    public function ubahStatus(Request $request, TenagaPendidik $tenagaPendidik)
    {
        $request->validate([
            'status_baru'     => 'required|in:aktif,cuti,cuti_sakit,nonaktif_sementara,resign,pensiun,meninggal',
            'tanggal_efektif' => 'required|date',
            'tanggal_kembali' => 'nullable|date|after:tanggal_efektif',
            'alasan'          => 'required|string|max:500',
            'dokumen_file'    => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'alasan.required' => 'Alasan perubahan status wajib diisi.',
        ]);

        try {
            $riwayat = $this->statusService->ubahStatus(
                $tenagaPendidik,
                $request->status_baru,
                $request->only(['tanggal_efektif', 'tanggal_kembali', 'alasan', 'dokumen_file'])
            );

            $statusLabel = \App\Models\RiwayatStatusKepegawaian::labelStatus($request->status_baru);

            return back()->with('success',
                "Status {$tenagaPendidik->user->name} berhasil diubah menjadi {$statusLabel}."
            );

        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Aktifkan kembali guru yang cuti/nonaktif sementara.
     * Endpoint: PATCH /admin/master/tenaga-pendidik/{id}/aktifkan
     */
    public function aktifkanKembali(Request $request, TenagaPendidik $tenagaPendidik)
    {
        $request->validate([
            'alasan' => 'required|string|max:500',
        ]);

        try {
            $this->statusService->aktifkanKembali($tenagaPendidik, $request->alasan);

            return back()->with('success',
                "{$tenagaPendidik->user->name} berhasil diaktifkan kembali."
            );

        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Riwayat perubahan status kepegawaian.
     */
    public function riwayatStatus(TenagaPendidik $tenagaPendidik)
    {
        return response()->json([
            'success' => true,
            'data'    => $this->statusService->getRiwayat($tenagaPendidik),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // MANAJEMEN JABATAN GURU (RANGKAP JABATAN)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Assign jabatan baru ke tenaga pendidik.
     * Satu guru bisa punya banyak jabatan (rangkap).
     */
    public function assignJabatan(Request $request, TenagaPendidik $tenagaPendidik)
    {
        $data = $request->validate([
            'jabatan_id'    => 'required|exists:jabatan,id',
            'berlaku_mulai' => 'required|date',
            'adalah_utama'  => 'boolean',
            'keterangan'    => 'nullable|string|max:500',
        ]);

        // Cek apakah jabatan ini sudah aktif untuk guru ini
        $sudahAda = \App\Models\JabatanGuru::where('tenaga_pendidik_id', $tenagaPendidik->id)
            ->where('jabatan_id', $data['jabatan_id'])
            ->whereNull('berlaku_selesai')
            ->exists();

        if ($sudahAda) {
            return back()->with('error', 'Jabatan ini sudah aktif untuk tenaga pendidik tersebut.');
        }

        // Jika dijadikan utama, lepas flag utama dari yang lain
        if ($data['adalah_utama'] ?? false) {
            \App\Models\JabatanGuru::where('tenaga_pendidik_id', $tenagaPendidik->id)
                ->update(['adalah_utama' => false]);

            // Update jabatan_id di kolom lama juga (backward compat)
            $tenagaPendidik->update(['jabatan_id' => $data['jabatan_id']]);
        }

        \App\Models\JabatanGuru::create([
            'tenaga_pendidik_id' => $tenagaPendidik->id,
            'jabatan_id'         => $data['jabatan_id'],
            'adalah_utama'       => $data['adalah_utama'] ?? false,
            'berlaku_mulai'      => $data['berlaku_mulai'],
            'berlaku_selesai'    => null,
            'keterangan'         => $data['keterangan'] ?? null,
            'ditetapkan_oleh'    => auth()->id(),
        ]);

        $jabatan = \App\Models\Jabatan::find($data['jabatan_id']);
        return back()->with('success',
            "Jabatan {$jabatan->nama_jabatan} berhasil ditambahkan untuk {$tenagaPendidik->user->name}."
        );
    }

    /**
     * Lepas jabatan dari tenaga pendidik (set berlaku_selesai = hari ini).
     */
    public function lepasJabatan(Request $request, TenagaPendidik $tenagaPendidik, \App\Models\JabatanGuru $jabatanGuru)
    {
        if ($jabatanGuru->tenaga_pendidik_id !== $tenagaPendidik->id) {
            return back()->with('error', 'Data tidak valid.');
        }

        // Tidak boleh melepas jika ini satu-satunya jabatan aktif
        $totalAktif = \App\Models\JabatanGuru::where('tenaga_pendidik_id', $tenagaPendidik->id)
            ->whereNull('berlaku_selesai')
            ->count();

        if ($totalAktif <= 1) {
            return back()->with('error',
                'Tidak bisa melepas jabatan — guru harus memiliki minimal 1 jabatan aktif.'
            );
        }

        $jabatanGuru->update([
            'berlaku_selesai' => now()->toDateString(),
        ]);

        // Jika jabatan yang dilepas adalah utama, set jabatan pertama yang tersisa sebagai utama
        if ($jabatanGuru->adalah_utama) {
            $jabatanBaru = \App\Models\JabatanGuru::where('tenaga_pendidik_id', $tenagaPendidik->id)
                ->whereNull('berlaku_selesai')
                ->first();

            if ($jabatanBaru) {
                $jabatanBaru->update(['adalah_utama' => true]);
                $tenagaPendidik->update(['jabatan_id' => $jabatanBaru->jabatan_id]);
            }
        }

        return back()->with('success', "Jabatan berhasil dilepas.");
    }

    /**
     * Jadikan jabatan sebagai jabatan utama.
     */
    public function setJabatanUtama(TenagaPendidik $tenagaPendidik, \App\Models\JabatanGuru $jabatanGuru)
    {
        if ($jabatanGuru->tenaga_pendidik_id !== $tenagaPendidik->id) {
            return back()->with('error', 'Data tidak valid.');
        }

        // Reset semua jadi bukan utama
        \App\Models\JabatanGuru::where('tenaga_pendidik_id', $tenagaPendidik->id)
            ->update(['adalah_utama' => false]);

        // Set yang dipilih sebagai utama
        $jabatanGuru->update(['adalah_utama' => true]);

        // Sync jabatan_id lama
        $tenagaPendidik->update(['jabatan_id' => $jabatanGuru->jabatan_id]);

        return back()->with('success',
            "{$jabatanGuru->jabatan->nama_jabatan} dijadikan jabatan utama."
        );
    }

    /** Download template Excel import tenaga pendidik. */
    public function templateImport()
    {
        $headings = ['name', 'email', 'username', 'password', 'jabatan', 'nip', 'jenis_kelamin', 'tanggal_masuk', 'jenis_guru', 'nik', 'tempat_lahir', 'tanggal_lahir', 'pendidikan_terakhir', 'jurusan', 'no_hp', 'alamat', 'no_rekening', 'nama_bank', 'nama_rekening'];
        $contoh   = [[
            'Ustadz Fulan', 'ustadz.fulan@contoh.com', 'ustadzfulan', '', 'Guru', '198701012010',
            'L', '2020-07-01', 'non_mukim', '3515010101870001', 'Surabaya', '1987-01-01', 'S1', 'PAI',
            '081234567890', 'Jl. Contoh No. 1', '1234567890', 'BSI', 'Ustadz Fulan',
        ]];
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\TemplateExport($headings, $contoh), 'template-import-tenaga-pendidik.xlsx');
    }

    /** Import tenaga pendidik dari Excel (buat User + jabatan). */
    public function import(\Illuminate\Http\Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);
        $imp = new \App\Imports\TenagaPendidikImport();
        try {
            \Maatwebsite\Excel\Facades\Excel::import($imp, $request->file('file'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal membaca file: ' . $e->getMessage());
        }
        $msg = "Import guru: {$imp->berhasil} berhasil, {$imp->gagal} gagal. (password kosong = NIP)"
            . (!empty($imp->errors) ? ' — ' . implode(' | ', array_slice($imp->errors, 0, 3)) : '');
        return back()->with($imp->berhasil > 0 ? 'success' : 'error', $msg);
    }

}
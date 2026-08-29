<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\HariLibur;
use App\Models\AbsensiHarian;
use App\Models\TenagaPendidik;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class HariLiburController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════════
    // INDEX
    // ══════════════════════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        $tahun = (int) ($request->tahun ?? now()->year);

        // Ambil semua hari libur tahun ini + group per sumber
        $semua = HariLibur::with('dibuatOleh')
            ->whereYear('tanggal', $tahun)
            ->orderBy('tanggal')
            ->get()
            ->map(fn($h) => [
                'id'                => $h->id,
                'nama'              => $h->nama,
                'tanggal'           => $h->tanggal->format('d M Y'),
                'tanggal_raw'           => $h->tanggal->format('Y-m-d'),
                'tanggal_selesai'        => $h->tanggal_selesai?->format('d M Y'),
                'tanggal_selesai_raw'    => $h->tanggal_selesai?->format('Y-m-d'),
                'durasi_hari'       => $h->durasi_hari,
                'sumber'            => $h->sumber,
                'sumber_label'      => $h->sumber_label,
                'tipe'              => $h->tipe,
                'is_aktif'          => $h->is_aktif,
                'is_darurat'        => $h->is_darurat,
                'is_dibatalkan'     => $h->is_dibatalkan,
                'pengaruh_gaji'     => $h->pengaruh_gaji,
                'keterangan'        => $h->keterangan,
                'absensi_terdampak' => $h->absensi_terdampak,
                'alasan_pembatalan' => $h->alasan_pembatalan,
                'dibuat_oleh'       => $h->dibuatOleh?->name,
                'dibuat_pada'       => $h->created_at?->format('d M Y H:i'),
            ]);

        $summary = [
            'total'     => $semua->count(),
            'nasional'  => $semua->where('sumber', 'nasional')->count(),
            'pesantren' => $semua->where('sumber', 'pesantren')->count(),
            'darurat'   => $semua->where('sumber', 'darurat')->count(),
            'aktif'     => $semua->where('is_aktif', true)->where('is_dibatalkan', false)->count(),
            'nonaktif'  => $semua->where('is_aktif', false)->count(),
        ];

        return Inertia::render('Admin/SmartPayroll/HariLibur/Index', [
            'hari_libur' => $semua,
            'grouped'    => $semua->groupBy('sumber'),
            'summary'    => $summary,
            'tahun'      => $tahun,
            'nasional_tersedia' => HariLibur::daftarNasional($tahun),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // STORE — Tambah hari libur pesantren / nasional manual
    // ══════════════════════════════════════════════════════════════════════════

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'            => 'required|string|max:150',
            'tanggal'         => 'required|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal',
            'sumber'          => 'required|in:nasional,pesantren',
            'keterangan'      => 'nullable|string|max:500',
            'pengaruh_gaji'   => 'boolean',
        ]);

        $hariLibur = HariLibur::create(array_merge($data, [
            'tipe'        => $data['sumber'], // backward compat
            'is_aktif'    => true,
            'is_darurat'  => false,
            'dibuat_oleh' => Auth::id(),
        ]));

        // Auto-isi absensi mengajar (reguler/tahfidz/tahsin) jadi 'libur' utk tanggal s/d hari ini.
        app(\App\Services\LiburMengajarService::class)->isiUntukLibur($hariLibur);

        return back()->with('success', "Hari libur \"{$data['nama']}\" berhasil ditambahkan.");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // UPDATE
    // ══════════════════════════════════════════════════════════════════════════

    public function update(Request $request, HariLibur $hariLibur)
    {
        $data = $request->validate([
            'nama'            => 'required|string|max:150',
            'tanggal'         => 'required|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal',
            'keterangan'      => 'nullable|string|max:500',
            'pengaruh_gaji'   => 'boolean',
        ]);

        $hariLibur->update($data);

        return back()->with('success', 'Hari libur diperbarui.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // TOGGLE AKTIF — Aktifkan/nonaktifkan (terutama untuk hari libur nasional)
    // ══════════════════════════════════════════════════════════════════════════

    public function toggleAktif(HariLibur $hariLibur)
    {
        if ($hariLibur->is_darurat && !$hariLibur->is_aktif) {
            return back()->with('error', 'Libur darurat yang sudah dibatalkan tidak bisa diaktifkan kembali.');
        }

        $hariLibur->update(['is_aktif' => !$hariLibur->is_aktif]);

        $status = $hariLibur->is_aktif ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "\"{$hariLibur->nama}\" {$status}.");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // IMPORT NASIONAL — Import batch hari libur nasional dari daftar preset
    // ══════════════════════════════════════════════════════════════════════════

    public function importNasional(Request $request)
    {
        $data = $request->validate([
            'items'             => 'required|array|min:1',
            'items.*.nama'      => 'required|string|max:150',
            'items.*.tanggal'   => 'required|date',
            'items.*.tanggal_selesai' => 'nullable|date',
            'pengaruh_gaji'     => 'boolean',
        ]);

        $berhasil = 0;
        $duplikat = 0;

        DB::transaction(function () use ($data, &$berhasil, &$duplikat) {
            foreach ($data['items'] as $item) {
                if (empty($item['tanggal'])) continue; // skip yang tanggalnya kosong

                $exists = HariLibur::where('tanggal', $item['tanggal'])
                    ->where('sumber', 'nasional')->exists();

                if ($exists) { $duplikat++; continue; }

                HariLibur::create([
                    'nama'            => $item['nama'],
                    'tanggal'         => $item['tanggal'],
                    'tanggal_selesai' => $item['tanggal_selesai'] ?? null,
                    'sumber'          => 'nasional',
                    'tipe'            => 'nasional',
                    'is_aktif'        => true,
                    'is_darurat'      => false,
                    'pengaruh_gaji'   => $data['pengaruh_gaji'] ?? true,
                    'dibuat_oleh'     => Auth::id(),
                ]);
                $berhasil++;
            }
        });

        return back()->with('success', "{$berhasil} hari libur nasional diimport. {$duplikat} sudah ada.");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // LIBUR DARURAT — Exception handling: tambah + auto-update absensi
    // ══════════════════════════════════════════════════════════════════════════

    public function storeDarurat(Request $request)
    {
        $data = $request->validate([
            'nama'            => 'required|string|max:150',
            'tanggal'         => 'required|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal',
            'keterangan'      => 'required|string|max:500',
            'pengaruh_gaji'   => 'boolean',
        ]);

        try {
            $hasil = DB::transaction(function () use ($data) {
                // 1. Simpan hari libur darurat
                $hariLibur = HariLibur::create([
                    'nama'            => $data['nama'],
                    'tanggal'         => $data['tanggal'],
                    'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
                    'sumber'          => 'darurat',
                    'tipe'            => 'darurat',
                    'is_aktif'        => true,
                    'is_darurat'      => true,
                    'keterangan'      => $data['keterangan'],
                    'pengaruh_gaji'   => $data['pengaruh_gaji'] ?? true,
                    'dibuat_oleh'     => Auth::id(),
                ]);

                // 2. Rentang tanggal terdampak
                $mulai   = Carbon::parse($data['tanggal']);
                $selesai = Carbon::parse($data['tanggal_selesai'] ?? $data['tanggal']);
                $tanggalRange = $this->getRangeTanggal($mulai, $selesai);

                // 3. Auto-update absensi yang sudah ada → jadi 'libur'
                $jumlahDiupdate = AbsensiHarian::whereIn('tanggal', $tanggalRange)
                    ->whereIn('status', ['alfa', 'hadir', 'terlambat'])
                    ->update([
                        'status'         => 'libur',
                        'keterangan'     => "Auto: {$hariLibur->nama}",
                        'is_koreksi'     => true,
                        'dikoreksi_oleh' => Auth::id(),
                    ]);

                // 4. Buat record absensi 'libur' untuk guru yang BELUM ada record
                $guruIds = TenagaPendidik::aktif()->pluck('id');
                $dibuatBaru = 0;

                foreach ($tanggalRange as $tgl) {
                    $sudahAda = AbsensiHarian::where('tanggal', $tgl)
                        ->pluck('tenaga_pendidik_id')->toArray();

                    foreach ($guruIds as $guruId) {
                        if (!in_array($guruId, $sudahAda)) {
                            AbsensiHarian::create([
                                'tenaga_pendidik_id' => $guruId,
                                'tanggal'            => $tgl,
                                'status'             => 'libur',
                                'keterangan'         => "Auto: {$hariLibur->nama}",
                                'is_koreksi'         => true,
                                'dikoreksi_oleh'     => Auth::id(),
                            ]);
                            $dibuatBaru++;
                        }
                    }
                }

                // 5. Simpan jumlah terdampak ke record hari libur
                $hariLibur->update([
                    'absensi_terdampak' => $jumlahDiupdate + $dibuatBaru,
                ]);

                return [
                    'hari_libur'         => $hariLibur,
                    'absensi_diupdate'   => $jumlahDiupdate,
                    'absensi_dibuat'     => $dibuatBaru,
                    'tanggal_terdampak'  => $tanggalRange,
                ];
            });

            // Auto-isi absensi MENGAJAR (reguler/tahfidz/tahsin) jadi 'libur' utk rentang s/d hari ini.
            app(\App\Services\LiburMengajarService::class)->isiUntukLibur($hasil['hari_libur']);

            $total = $hasil['absensi_diupdate'] + $hasil['absensi_dibuat'];
            return back()->with('success',
                "🚨 Libur darurat \"{$data['nama']}\" ditambahkan. {$total} record absensi diperbarui otomatis."
            );

        } catch (\Throwable $e) {
            Log::error('Libur darurat gagal: ' . $e->getMessage());
            return back()->with('error', 'Gagal menambahkan libur darurat: ' . $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // BATALKAN DARURAT — Rollback libur darurat + absensi
    // ══════════════════════════════════════════════════════════════════════════

    public function batalkanDarurat(Request $request, HariLibur $hariLibur)
    {
        if (!$hariLibur->is_darurat) {
            return back()->with('error', 'Hanya libur darurat yang bisa dibatalkan via fitur ini.');
        }

        if ($hariLibur->is_dibatalkan) {
            return back()->with('error', 'Libur darurat ini sudah pernah dibatalkan.');
        }

        $data = $request->validate([
            'alasan' => 'required|string|max:500',
        ]);

        try {
            DB::transaction(function () use ($hariLibur, $data) {
                $mulai   = $hariLibur->tanggal;
                $selesai = $hariLibur->tanggal_selesai ?? $hariLibur->tanggal;
                $tanggalRange = $this->getRangeTanggal($mulai, $selesai);

                // Rollback: kembalikan absensi yang diubah otomatis ke 'alfa'
                $dikembalikan = AbsensiHarian::whereIn('tanggal', $tanggalRange)
                    ->where('status', 'libur')
                    ->where('is_koreksi', true)
                    ->where('dikoreksi_oleh', $hariLibur->dibuat_oleh)
                    ->update([
                        'status'     => 'alfa',
                        'keterangan' => "Libur \"{$hariLibur->nama}\" dibatalkan. {$data['alasan']}",
                    ]);

                // Tandai hari libur sebagai dibatalkan
                $hariLibur->update([
                    'is_aktif'           => false,
                    'dibatalkan_pada'    => now(),
                    'alasan_pembatalan'  => $data['alasan'],
                    'dibatalkan_oleh'    => Auth::id(),
                ]);
            });

            return back()->with('success',
                "Libur darurat \"{$hariLibur->nama}\" dibatalkan. Absensi dikembalikan ke status alfa."
            );

        } catch (\Throwable $e) {
            Log::error('Batalkan darurat gagal: ' . $e->getMessage());
            return back()->with('error', 'Gagal membatalkan: ' . $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DELETE — Hapus hari libur (bukan darurat)
    // ══════════════════════════════════════════════════════════════════════════

    public function destroy(HariLibur $hariLibur)
    {
        if ($hariLibur->is_darurat && !$hariLibur->is_dibatalkan) {
            return back()->with('error', 'Batalkan libur darurat dulu sebelum menghapus.');
        }

        $hariLibur->delete();
        return back()->with('success', 'Hari libur dihapus.');
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function getRangeTanggal(Carbon $mulai, Carbon $selesai): array
    {
        $tanggal = [];
        $current = $mulai->copy();
        while ($current->lte($selesai)) {
            $tanggal[] = $current->toDateString();
            $current->addDay();
        }
        return $tanggal;
    }
}
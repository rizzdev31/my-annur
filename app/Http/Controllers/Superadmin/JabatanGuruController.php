<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\JabatanGuru;
use App\Models\TenagaPendidik;
use App\Models\Jabatan;
use App\Models\SettingGajiPokok;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JabatanGuruController extends Controller
{
    /**
     * Halaman khusus setting multi jabatan satu guru.
     */
    public function index(TenagaPendidik $tenagaPendidik)
    {
        $tenagaPendidik->load(['user', 'jabatan', 'jabatanGuru.jabatan', 'jabatanGuru.ditetapkanOleh']);

        // Jabatan aktif (berlaku_selesai null atau masa depan)
        $jabatanAktif = $tenagaPendidik->jabatanGuru
            ->filter(fn($jg) => !$jg->berlaku_selesai || $jg->berlaku_selesai->isFuture())
            ->sortByDesc('adalah_utama')
            ->map(fn($jg) => self::formatPivot($jg))
            ->values();

        // Riwayat jabatan (sudah berakhir)
        $riwayatJabatan = $tenagaPendidik->jabatanGuru
            ->filter(fn($jg) => $jg->berlaku_selesai && $jg->berlaku_selesai->isPast())
            ->sortByDesc('berlaku_selesai')
            ->map(fn($jg) => self::formatPivot($jg))
            ->values();

        // Semua jabatan aktif (untuk dropdown assign)
        $jabatanList = Jabatan::aktif()
            ->orderBy('tipe')->orderBy('nama_jabatan')
            ->get(['id', 'nama_jabatan', 'kode_jabatan', 'tipe'])
            ->map(fn($j) => [
                'id'           => $j->id,
                'nama_jabatan' => $j->nama_jabatan,
                'kode_jabatan' => $j->kode_jabatan,
                'tipe'         => $j->tipe,
                'sudah_aktif'  => $jabatanAktif->contains('jabatan_id', $j->id),
                // Gaji pokok jabatan ini (untuk preview)
                'gaji_pokok'   => SettingGajiPokok::where('jabatan_id', $j->id)
                    ->where('is_aktif', true)
                    ->latest('berlaku_mulai')
                    ->value('nominal') ?? 0,
            ]);

        // Ringkasan gaji pokok total
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

        return Inertia::render('Admin/Master/TenagaPendidik/Jabatan/Index', [
            'guru' => [
                'id'      => $tenagaPendidik->id,
                'nama'    => $tenagaPendidik->user->name,
                'nip'     => $tenagaPendidik->nip,
                'foto'    => $tenagaPendidik->user->foto
                    ? asset('storage/'.$tenagaPendidik->user->foto) : null,
                'jabatan' => $tenagaPendidik->jabatan?->nama_jabatan ?? '—',
                'is_aktif'=> $tenagaPendidik->is_aktif,
                'status_kepegawaian' => $tenagaPendidik->status_kepegawaian ?? 'aktif',
            ],
            'jabatanAktif'   => $jabatanAktif,
            'riwayatJabatan' => $riwayatJabatan,
            'jabatanList'    => $jabatanList,
            'detailGajiPokok'=> $detailGajiPokok,
            'totalGajiPokok' => $detailGajiPokok->sum('nominal'),
        ]);
    }

    /**
     * Assign jabatan baru ke guru.
     */
    public function store(Request $request, TenagaPendidik $tenagaPendidik)
    {
        $data = $request->validate([
            'jabatan_id'    => 'required|exists:jabatan,id',
            'berlaku_mulai' => 'required|date',
            'adalah_utama'  => 'boolean',
            'keterangan'    => 'nullable|string|max:500',
        ]);

        // Cek duplikasi jabatan aktif
        $sudahAda = JabatanGuru::where('tenaga_pendidik_id', $tenagaPendidik->id)
            ->where('jabatan_id', $data['jabatan_id'])
            ->whereNull('berlaku_selesai')
            ->exists();

        if ($sudahAda) {
            return back()->with('error', 'Jabatan ini sudah aktif untuk guru ini.');
        }

        // Jika dijadikan utama, lepas flag utama dari yang lain
        if (!empty($data['adalah_utama'])) {
            JabatanGuru::where('tenaga_pendidik_id', $tenagaPendidik->id)
                ->update(['adalah_utama' => false]);

            $tenagaPendidik->update(['jabatan_id' => $data['jabatan_id']]);
        }

        JabatanGuru::create([
            'tenaga_pendidik_id' => $tenagaPendidik->id,
            'jabatan_id'         => $data['jabatan_id'],
            'adalah_utama'       => $data['adalah_utama'] ?? false,
            'berlaku_mulai'      => $data['berlaku_mulai'],
            'berlaku_selesai'    => null,
            'keterangan'         => $data['keterangan'] ?? null,
            'ditetapkan_oleh'    => auth()->id(),
        ]);

        $jabatan = Jabatan::find($data['jabatan_id']);
        return back()->with('success', "Jabatan {$jabatan->nama_jabatan} berhasil ditambahkan.");
    }

    /**
     * Update data jabatan (keterangan, berlaku_mulai).
     */
    public function update(Request $request, TenagaPendidik $tenagaPendidik, JabatanGuru $jabatanGuru)
    {
        $this->authorize_ownership($jabatanGuru, $tenagaPendidik);

        $data = $request->validate([
            'berlaku_mulai'  => 'required|date',
            'berlaku_selesai'=> 'nullable|date|after:berlaku_mulai',
            'keterangan'     => 'nullable|string|max:500',
        ]);

        $jabatanGuru->update($data);

        return back()->with('success', 'Data jabatan diperbarui.');
    }

    /**
     * Jadikan jabatan sebagai utama.
     */
    public function setUtama(TenagaPendidik $tenagaPendidik, JabatanGuru $jabatanGuru)
    {
        $this->authorize_ownership($jabatanGuru, $tenagaPendidik);

        // Reset semua utama
        JabatanGuru::where('tenaga_pendidik_id', $tenagaPendidik->id)
            ->update(['adalah_utama' => false]);

        $jabatanGuru->update(['adalah_utama' => true]);

        // Sync kolom lama
        $tenagaPendidik->update(['jabatan_id' => $jabatanGuru->jabatan_id]);

        return back()->with('success', "{$jabatanGuru->jabatan->nama_jabatan} dijadikan jabatan utama.");
    }

    /**
     * Lepas jabatan (set berlaku_selesai hari ini).
     */
    public function destroy(TenagaPendidik $tenagaPendidik, JabatanGuru $jabatanGuru)
    {
        $this->authorize_ownership($jabatanGuru, $tenagaPendidik);

        // Minimal harus ada 1 jabatan aktif
        $totalAktif = JabatanGuru::where('tenaga_pendidik_id', $tenagaPendidik->id)
            ->whereNull('berlaku_selesai')->count();

        if ($totalAktif <= 1) {
            return back()->with('error', 'Tidak bisa melepas jabatan — minimal 1 jabatan aktif harus ada.');
        }

        $jabatanGuru->update(['berlaku_selesai' => now()->toDateString()]);

        // Jika yang dilepas adalah utama, promote yang pertama
        if ($jabatanGuru->adalah_utama) {
            $gantiUtama = JabatanGuru::where('tenaga_pendidik_id', $tenagaPendidik->id)
                ->whereNull('berlaku_selesai')
                ->first();

            if ($gantiUtama) {
                $gantiUtama->update(['adalah_utama' => true]);
                $tenagaPendidik->update(['jabatan_id' => $gantiUtama->jabatan_id]);
            }
        }

        return back()->with('success', 'Jabatan berhasil dilepas.');
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function authorize_ownership(JabatanGuru $jg, TenagaPendidik $guru): void
    {
        abort_if($jg->tenaga_pendidik_id !== $guru->id, 403, 'Data tidak valid.');
    }

    private static function formatPivot(JabatanGuru $jg): array
    {
        return [
            'pivot_id'        => $jg->id,
            'jabatan_id'      => $jg->jabatan_id,
            'nama_jabatan'    => $jg->jabatan?->nama_jabatan ?? '—',
            'kode_jabatan'    => $jg->jabatan?->kode_jabatan ?? '',
            'tipe'            => $jg->jabatan?->tipe ?? '',
            'adalah_utama'    => $jg->adalah_utama,
            'berlaku_mulai'   => $jg->berlaku_mulai?->format('d M Y'),
            'berlaku_mulai_raw'=> $jg->berlaku_mulai?->format('Y-m-d'),
            'berlaku_selesai' => $jg->berlaku_selesai?->format('d M Y'),
            'berlaku_selesai_raw' => $jg->berlaku_selesai?->format('Y-m-d'),
            'keterangan'      => $jg->keterangan,
            'ditetapkan_oleh' => $jg->ditetapkanOleh?->name,
        ];
    }
}
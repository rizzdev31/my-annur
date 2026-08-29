<?php

namespace App\Http\Controllers\Superadmin\SmartHabbit;

use App\Http\Controllers\Controller;
use App\Models\ControllingPeriode;
use App\Models\ControllingKegiatan;
use App\Models\ControllingJadwal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Setting Smart Controlling: periode (1 bulan), master kegiatan, jadwal per periode.
 * Jadwal inilah yang dipakai endpoint scan untuk menentukan Hadir/Telat/Alpha.
 */
class ControllingSettingController extends Controller
{
    /** Nama bulan Indonesia (index 1-12) — dipakai untuk nama periode & dropdown. */
    private const NAMA_BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function index(Request $request)
    {
        $periodeAktif = ControllingPeriode::aktif();
        $periodeId    = $request->periode_id ? (int) $request->periode_id : $periodeAktif?->id;

        return Inertia::render('Admin/SmartHabbit/Controlling/Index', [
            'periodeList'   => ControllingPeriode::orderByDesc('tahun')->orderByDesc('bulan')->get()
                ->map(fn($p) => [
                    'id' => $p->id, 'nama' => $p->nama, 'bulan' => $p->bulan, 'tahun' => $p->tahun,
                    'is_aktif' => $p->is_aktif,
                    'bulan_label' => (self::NAMA_BULAN[$p->bulan] ?? $p->bulan) . ' ' . $p->tahun,
                    'jumlah_jadwal' => ControllingJadwal::where('periode_id', $p->id)->count(),
                ]),
            'periodeAktif'  => $periodeAktif,
            'periodeId'     => $periodeId,
            'bulanOpsi'     => collect(self::NAMA_BULAN)->map(fn($nama, $v) => ['v' => $v, 'label' => $nama])->values(),
            // Peringatan pergantian bulan: apakah periode untuk bulan sistem sudah dibuat.
            'bulanIni'          => ['ada' => ControllingPeriode::adaBulanIni(), 'label' => self::NAMA_BULAN[(int) now()->format('n')] . ' ' . now()->format('Y')],
            'kegiatanList'  => ControllingKegiatan::orderBy('jenis')->orderBy('nama')->get(),
            'jadwalList'    => $periodeId
                ? ControllingJadwal::with('kegiatan')
                    ->where('periode_id', $periodeId)
                    ->orderByRaw("FIELD(hari,'senin','selasa','rabu','kamis','jumat','sabtu','ahad')")
                    ->orderBy('jam_mulai')->get()
                    ->map(fn($j) => [
                        'id' => $j->id, 'kegiatan_id' => $j->kegiatan_id,
                        'kegiatan' => $j->kegiatan?->nama ?? '—', 'jenis' => $j->kegiatan?->jenis,
                        'hari' => $j->hari, 'jam_mulai' => substr($j->jam_mulai, 0, 5),
                        'jam_selesai' => substr($j->jam_selesai, 0, 5),
                        'ambang_telat_menit' => $j->ambang_telat_menit, 'is_aktif' => $j->is_aktif,
                    ])
                : [],
            'hariOpsi' => ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'ahad'],
        ]);
    }

    // ── Periode ───────────────────────────────────────────────────────────
    public function periodeStore(Request $request)
    {
        $d = $request->validate([
            'bulan'      => 'required|integer|min:1|max:12',
            'tahun'      => 'required|integer|min:2024|max:2100',
            // Opsional: salin seluruh jadwal dari periode lain → kegiatan berjalan
            // di bulan ini juga (carry-over), lalu boleh ditambah/dikurangi (override).
            'salin_dari' => 'nullable|integer|exists:controlling_periode,id',
        ]);

        // Cegah duplikat bulan+tahun (1 periode per bulan).
        if (ControllingPeriode::where('bulan', $d['bulan'])->where('tahun', $d['tahun'])->exists()) {
            return back()->withErrors(['bulan' => 'Periode untuk bulan tersebut sudah ada.']);
        }

        $nama = self::NAMA_BULAN[$d['bulan']] . ' ' . $d['tahun'];
        DB::transaction(function () use ($d, $nama) {
            $periode = ControllingPeriode::create([
                'nama' => $nama, 'bulan' => $d['bulan'], 'tahun' => $d['tahun'], 'is_aktif' => false,
            ]);
            if (!empty($d['salin_dari'])) {
                $this->salinJadwal((int) $d['salin_dari'], $periode->id);
            }
        });

        $extra = !empty($d['salin_dari']) ? ' (jadwal disalin dari periode lain)' : '';
        return back()->with('success', "Periode {$nama} ditambahkan{$extra}.");
    }

    /** Buat periode bulan BERIKUTNYA dengan menyalin seluruh jadwal periode ini. */
    public function periodeDuplikat(ControllingPeriode $periode)
    {
        $bulan = $periode->bulan + 1;
        $tahun = $periode->tahun;
        if ($bulan > 12) { $bulan = 1; $tahun++; }

        if (ControllingPeriode::where('bulan', $bulan)->where('tahun', $tahun)->exists()) {
            return back()->withErrors(['bulan' => 'Periode bulan berikutnya sudah ada.']);
        }

        $nama = self::NAMA_BULAN[$bulan] . ' ' . $tahun;
        DB::transaction(function () use ($periode, $bulan, $tahun, $nama) {
            $baru = ControllingPeriode::create([
                'nama' => $nama, 'bulan' => $bulan, 'tahun' => $tahun, 'is_aktif' => false,
            ]);
            $this->salinJadwal($periode->id, $baru->id);
        });

        return back()->with('success', "Periode {$nama} dibuat dari salinan {$periode->nama}.");
    }

    /** Salin semua baris jadwal dari satu periode ke periode lain. */
    private function salinJadwal(int $fromId, int $toId): int
    {
        $rows = ControllingJadwal::where('periode_id', $fromId)->get();
        foreach ($rows as $r) {
            ControllingJadwal::create([
                'periode_id'         => $toId,
                'kegiatan_id'        => $r->kegiatan_id,
                'hari'               => $r->hari,
                'jam_mulai'          => $r->jam_mulai,
                'jam_selesai'        => $r->jam_selesai,
                'ambang_telat_menit' => $r->ambang_telat_menit,
                'is_aktif'           => $r->is_aktif,
            ]);
        }
        return $rows->count();
    }

    public function periodeActivate(ControllingPeriode $periode)
    {
        DB::transaction(function () use ($periode) {
            ControllingPeriode::where('is_aktif', true)->update(['is_aktif' => false]);
            $periode->update(['is_aktif' => true]);
        });
        return back()->with('success', "Periode {$periode->nama} diaktifkan.");
    }

    public function periodeDestroy(ControllingPeriode $periode)
    {
        $periode->delete(); // cascade jadwal & absensi
        return back()->with('success', 'Periode dihapus.');
    }

    // ── Kegiatan (master) ───────────────────────────────────────────────────
    public function kegiatanStore(Request $request)
    {
        $d = $request->validate([
            'nama'       => 'required|string|max:100',
            'jenis'      => 'required|in:harian,kajian',
            'keterangan' => 'nullable|string|max:255',
        ]);
        ControllingKegiatan::create($d + ['is_aktif' => true]);
        return back()->with('success', 'Kegiatan ditambahkan.');
    }

    public function kegiatanUpdate(Request $request, ControllingKegiatan $kegiatan)
    {
        $d = $request->validate([
            'nama'       => 'required|string|max:100',
            'jenis'      => 'required|in:harian,kajian',
            'keterangan' => 'nullable|string|max:255',
            'is_aktif'   => 'boolean',
        ]);
        $kegiatan->update($d);
        return back()->with('success', 'Kegiatan diperbarui.');
    }

    public function kegiatanDestroy(ControllingKegiatan $kegiatan)
    {
        $kegiatan->delete();
        return back()->with('success', 'Kegiatan dihapus.');
    }

    // ── Jadwal (per periode) ──────────────────────────────────────────────
    public function jadwalStore(Request $request)
    {
        // 'hari' berupa ARRAY → 1 kegiatan bisa dijadwalkan ke banyak hari sekaligus
        // (mis. Senin-Ahad untuk kegiatan harian, atau cukup Senin untuk kajian mingguan).
        $d = $request->validate([
            'periode_id'         => 'required|exists:controlling_periode,id',
            'kegiatan_id'        => 'required|exists:controlling_kegiatan,id',
            'hari'               => 'required|array|min:1',
            'hari.*'             => 'in:senin,selasa,rabu,kamis,jumat,sabtu,ahad',
            'jam_mulai'          => 'required|date_format:H:i',
            'jam_selesai'        => 'required|date_format:H:i|after:jam_mulai',
            'ambang_telat_menit' => 'nullable|integer|min:0|max:120',
        ]);

        $dibuat = 0; $lewat = 0;
        foreach (array_unique($d['hari']) as $hari) {
            // Hindari duplikat: kegiatan + hari + jam_mulai yang sama pada periode ini.
            $ada = ControllingJadwal::where('periode_id', $d['periode_id'])
                ->where('kegiatan_id', $d['kegiatan_id'])->where('hari', $hari)
                ->where('jam_mulai', $d['jam_mulai'])->exists();
            if ($ada) { $lewat++; continue; }
            ControllingJadwal::create([
                'periode_id'         => $d['periode_id'],
                'kegiatan_id'        => $d['kegiatan_id'],
                'hari'               => $hari,
                'jam_mulai'          => $d['jam_mulai'],
                'jam_selesai'        => $d['jam_selesai'],
                'ambang_telat_menit' => $d['ambang_telat_menit'] ?? 0,
                'is_aktif'           => true,
            ]);
            $dibuat++;
        }
        $msg = "{$dibuat} jadwal ditambahkan" . ($lewat ? ", {$lewat} dilewati (sudah ada)" : '') . '.';
        return back()->with('success', $msg);
    }

    public function jadwalDestroy(ControllingJadwal $jadwal)
    {
        $jadwal->delete();
        return back()->with('success', 'Jadwal dihapus.');
    }

    // ── Kiosk Scan (web admin) ──────────────────────────────────────────────
    public function scanPage()
    {
        return Inertia::render('Admin/SmartHabbit/Controlling/Scan', [
            'periodeAktif' => ControllingPeriode::aktif()?->only(['id', 'nama']),
        ]);
    }

    // ── Kartu santri ber-barcode (NIP) ──────────────────────────────────────
    public function kartu(Request $request)
    {
        $q = trim((string) $request->q);
        return Inertia::render('Admin/SmartHabbit/Controlling/Kartu', [
            'santri' => \App\Models\Santri::where('is_aktif', true)
                ->when($q !== '', fn($x) => $x->where(fn($w) =>
                    $w->where('nama_lengkap', 'like', "%{$q}%")->orWhere('nip', 'like', "%{$q}%")))
                ->orderBy('nama_lengkap')->limit(500)
                ->get(['id', 'nip', 'nama_lengkap'])
                ->map(fn($s) => ['id' => $s->id, 'nip' => $s->nip, 'nama' => $s->nama_lengkap]),
            'filter' => ['q' => $q],
        ]);
    }

    /** SVG barcode Code128 untuk satu NIP (dipakai <img> di kartu). */
    public function barcode(string $nip)
    {
        $svg = app(\App\Services\BarcodeService::class)->code128Svg($nip);
        return response($svg, 200)->header('Content-Type', 'image/svg+xml');
    }

    /** JSON (dipanggil via fetch dari halaman kiosk). Admin terautentikasi → tanpa device token. */
    public function scanSubmit(Request $request)
    {
        $request->validate(['nip' => 'required|string|max:50', 'kegiatan_id' => 'nullable|integer']);
        try {
            $hasil = app(\App\Services\ControllingService::class)->scan(
                trim($request->nip),
                $request->kegiatan_id ? (int) $request->kegiatan_id : null,
                'kiosk:' . (auth()->user()->name ?? 'admin')
            );
        } catch (\DomainException $e) {
            $code = in_array($e->getCode(), [404, 422], true) ? $e->getCode() : 422;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $code);
        }
        return response()->json(['success' => true, 'data' => $hasil]);
    }
}


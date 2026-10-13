<?php

namespace App\Http\Controllers\Superadmin\Education;

use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Models\Kelas;
use App\Models\Surah;
use App\Models\HafalanSantri;
use App\Models\HafalanJuz;
use App\Models\SetoranTahfidz;
use App\Services\TahfidzService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

class MonitoringTahfidzController extends Controller
{
    /** Daftar santri tahfidz per kelas + ringkasan progres. */
    public function index(Request $request)
    {
        $kelasId   = $request->kelas_id ? (int) $request->kelas_id : null;
        $kelas     = $kelasId ? Kelas::find($kelasId) : null;
        $totalQ    = (int) Surah::sum('jumlah_ayat');
        $santriRow = collect();

        if ($kelas) {
            $santri = Santri::aktif()
                ->anggotaKelas($kelas->id)
                ->orderBy('nama_lengkap')->get(['id', 'nip', 'nama_lengkap']);

            $ids     = $santri->pluck('id');
            $hafalan = HafalanSantri::whereIn('santri_id', $ids)->get()->keyBy('santri_id');
            $juz     = HafalanJuz::whereIn('santri_id', $ids)->get()->groupBy('santri_id');
            $last    = SetoranTahfidz::whereIn('santri_id', $ids)
                ->selectRaw('santri_id, MAX(tanggal) as last_tgl')->groupBy('santri_id')
                ->pluck('last_tgl', 'santri_id');

            $santriRow = $santri->map(function ($s) use ($hafalan, $juz, $last, $totalQ) {
                $h  = $hafalan->get($s->id);
                $jz = $juz->get($s->id) ?? collect();
                $total = $h?->total_ayat ?? 0;
                return [
                    'id'             => $s->id,
                    'nip'            => $s->nip,
                    'nama'           => $s->nama_lengkap,
                    'total_ayat'     => $total,
                    'persen'         => $totalQ > 0 ? round($total / $totalQ * 100, 2) : 0,
                    'juz_selesai'    => $jz->whereIn('status', ['selesai', 'tasmi_lulus'])->count(),
                    'perlu_tasmi'    => $jz->where('status', 'selesai')->pluck('juz')->values(),
                    'perlu_murojaah' => (bool) ($h?->perlu_murojaah ?? false),
                    'last_setoran'   => $last->get($s->id)
                        ? Carbon::parse($last->get($s->id))->locale('id')->isoFormat('D MMM YYYY') : null,
                ];
            })->values();
        }

        return Inertia::render('Admin/SmartEducation/MonitoringTahfidz/Index', [
            'kelas'     => $kelas ? ['id' => $kelas->id, 'nama' => $kelas->nama] : null,
            'kelasOpsi' => Kelas::aktif()->tahfidz()->orderBy('nama')->get(['id', 'nama']),
            'santri'    => $santriRow,
            'summary'   => [
                'jumlah_santri' => $santriRow->count(),
                'total_juz_selesai' => $santriRow->sum('juz_selesai'),
                'perlu_tasmi'   => $santriRow->filter(fn($s) => count($s['perlu_tasmi']) > 0)->count(),
            ],
        ]);
    }

    /** Detail tracking 1 santri: ringkasan + grid 30 juz + riwayat setoran. */
    public function detail(Request $request, Santri $santri)
    {
        $status   = (new TahfidzService())->statusSantri($santri->id);
        $namaSurah = Surah::pluck('nama', 'nomor');

        // Grid 30 juz + CAKUPAN NYATA per juz. Cakupan dihitung dari himpunan
        // ayat (riwayat setoran + baseline), bukan dari penghitung
        // `ayat_terkumpul` yang bisa terisi ganda — inilah yang menjelaskan
        // kenapa tombol tasmi' kadang muncul/tidak muncul tidak sesuai kenyataan.
        $svc    = new TahfidzService();
        $juzMap = HafalanJuz::where('santri_id', $santri->id)->get()->keyBy('juz');
        $juzGrid = collect(range(1, 30))->map(function ($n) use ($juzMap, $santri, $svc) {
            $hj = $juzMap->get($n);
            $c  = $hj ? $svc->cakupanJuz($santri->id, $n) : null;

            return [
                'juz'       => $n,
                'status'    => $hj?->status ?? 'belum',
                'ayat'      => $hj?->ayat_terkumpul ?? 0,
                'total'     => $hj?->jumlah_ayat_juz ?? 0,
                'dikunci'   => (bool) ($hj?->status_dikunci ?? false),
                'alasan'    => $hj?->alasan_koreksi,
                // Rincian otomatis: surat & ayat yang sudah / belum dihafal.
                'nyata'     => $c['nyata'] ?? 0,
                'kurang'    => $c['kurang'] ?? 0,
                'penuh'     => $c['penuh'] ?? false,
                'sudah_hafal' => collect($c['hafal'] ?? [])->pluck('teks')->all(),
                'belum_hafal' => collect($c['belum'] ?? [])->pluck('teks')->all(),
                // Gerbang tasmi' tidak sejalan dengan cakupan → perlu dibenarkan.
                'perlu_koreksi' => $hj && !($hj->status_dikunci)
                    && ($c['penuh'] ?? false) !== in_array($hj->status, ['selesai', 'tasmi_lulus'], true),
                'boleh_tasmi'  => $hj ? $svc->alasanBelumBolehTasmi($santri->id, $n) === null : false,
                'alasan_tasmi' => $hj ? $svc->alasanBelumBolehTasmi($santri->id, $n) : null,
            ];
        });

        // Penunjukan penguji tasmi' — termasuk yang menggantung, agar superadmin
        // bisa membatalkan/menunjuk ulang. Sebelumnya tidak ada tempat sama
        // sekali untuk melihat, apalagi membatalkan, penunjukan ini.
        $tugasTasmi = \App\Models\TugasTasmi::where('santri_id', $santri->id)
            ->with(['penguji.user:id,name', 'pengampu.user:id,name'])
            ->orderByDesc('id')->get()
            ->map(fn ($t) => [
                'id'       => $t->id,
                'juz'      => $t->juz,
                'status'   => $t->status,
                'penguji'  => $t->penguji?->user?->name ?? '—',
                'pengampu' => $t->pengampu?->user?->name ?? '—',
                'nilai'    => $t->nilai,
                'lulus'    => $t->lulus,
                'alasan_batal' => $t->alasan_batal,
                'dibuat'   => $t->created_at?->format('d M Y'),
            ]);

        $jejak = \App\Models\KoreksiPencapaian::where('santri_id', $santri->id)
            ->with('dikoreksiOleh:id,name')->orderByDesc('id')->limit(20)->get()
            ->map(fn ($k) => [
                'tipe'   => $k->tipe,
                'juz'    => $k->juz,
                'level'  => $k->level,
                'field'  => $k->field,
                'lama'   => $k->nilai_lama,
                'baru'   => $k->nilai_baru,
                'alasan' => $k->alasan,
                'oleh'   => $k->dikoreksiOleh?->name ?? '—',
                'waktu'  => $k->created_at?->format('d M Y H:i'),
            ]);

        $riwayat = SetoranTahfidz::where('santri_id', $santri->id)
            ->with('tenagaPendidik.user')
            ->orderByDesc('tanggal')->orderByDesc('id')->limit(50)->get()
            ->map(fn($r) => [
                'id'        => $r->id,
                'tanggal'   => Carbon::parse($r->tanggal)->locale('id')->isoFormat('dd, D MMM YYYY'),
                'jenis'     => $r->jenis,
                'rentang'   => ($namaSurah[$r->surah_mulai] ?? '?') . ' ' . $r->ayat_mulai
                    . ' – ' . ($namaSurah[$r->surah_selesai] ?? '?') . ' ' . $r->ayat_selesai,
                'jumlah_ayat' => $r->jumlah_ayat,
                'juz'       => $r->juz_mulai == $r->juz_selesai ? $r->juz_mulai : "{$r->juz_mulai}–{$r->juz_selesai}",
                'nilai'     => $r->nilai,
                'lulus'     => $r->lulus,
                'guru'      => $r->tenagaPendidik?->user?->name ?? '—',
                'catatan'   => $r->catatan,
            ]);

        return Inertia::render('Admin/SmartEducation/MonitoringTahfidz/Detail', [
            'santri'  => ['id' => $santri->id, 'nama' => $santri->nama_lengkap, 'nip' => $santri->nip,
                          'tahsin_level' => $santri->tahsin_level],
            'status'  => $status,
            'juzGrid' => $juzGrid,
            'riwayat' => $riwayat,
            'tugasTasmi' => $tugasTasmi,
            'jejak'      => $jejak,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // OVERRIDE SUPERADMIN — pembenaran saat data pencapaian tidak sesuai
    // kenyataan. Semua berjejak di `koreksi_pencapaian`.
    // ══════════════════════════════════════════════════════════════════════

    /** Hitung ulang pencapaian dari riwayat setoran (membereskan hitungan ganda). */
    public function hitungUlang(Request $request, Santri $santri)
    {
        $h = (new TahfidzService())->hitungUlangSantri($santri->id);

        $berubah = collect($h['juz'])->filter(fn ($x) =>
            $x['sebelum']['ayat_terkumpul'] !== $x['sesudah']['ayat_terkumpul']
            || $x['sebelum']['status'] !== $x['sesudah']['status'])->count();

        return back()->with('success', "Pencapaian {$santri->nama_lengkap} dihitung ulang dari riwayat setoran: "
            . count($h['juz']) . " juz diperiksa, {$berubah} berubah, total {$h['total_ayat']} ayat.");
    }

    /** Tetapkan status satu juz secara manual (naikkan / turunkan / kunci). */
    public function setStatusJuz(Request $request, Santri $santri)
    {
        $d = $request->validate([
            'juz'    => 'required|integer|min:1|max:30',
            'status' => 'required|in:berjalan,selesai,tasmi_lulus',
            'alasan' => 'required|string|max:300',
            'kunci'  => 'nullable|boolean',
        ], [
            'alasan.required' => 'Alasan koreksi wajib diisi.',
            'status.in'       => 'Status juz tidak dikenal.',
        ]);

        try {
            (new TahfidzService())->setStatusJuz(
                $santri->id, (int) $d['juz'], $d['status'], $d['alasan'],
                Auth::id(), (bool) ($d['kunci'] ?? true)
            );
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Juz {$d['juz']} {$santri->nama_lengkap} ditetapkan \"{$d['status']}\".");
    }

    /** Lepas kunci agar juz kembali mengikuti hitungan otomatis. */
    public function lepasKunciJuz(Request $request, Santri $santri)
    {
        $d = $request->validate([
            'juz'    => 'required|integer|min:1|max:30',
            'alasan' => 'nullable|string|max:300',
        ]);

        try {
            (new TahfidzService())->lepasKunciJuz($santri->id, (int) $d['juz'], $d['alasan'] ?? '', Auth::id());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Kunci juz {$d['juz']} dilepas; statusnya kembali mengikuti hitungan.");
    }

    /** Batalkan penunjukan penguji tasmi' (termasuk tugas tambahan & vakasinya). */
    public function batalkanTasmi(Request $request, int $tugasTasmi)
    {
        $d = $request->validate(
            ['alasan' => 'required|string|max:300'],
            ['alasan.required' => 'Alasan pembatalan wajib diisi.']
        );

        try {
            app(\App\Services\TasmiService::class)->batalkanTugas($tugasTasmi, Auth::id(), $d['alasan']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Penunjukan tasmi' dibatalkan. Vakasi pengujinya tidak akan terbayar.");
    }
}

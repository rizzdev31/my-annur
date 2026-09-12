<?php

namespace App\Http\Controllers\Superadmin\Education;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Models\Kelas;
use App\Models\Santri;
use App\Models\JadwalMengajar;
use App\Models\TenagaPendidik;
use App\Models\TahunAjaran;
use App\Models\SettingTahfidz;
use App\Models\SetoranTahfidz;
use App\Models\HafalanJuz;
use App\Models\Surah;
use App\Services\TahfidzService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TahfidzController extends Controller
{
    /** Hub Smart Tahfidz: alur, status setup, setting penilaian, generator jadwal. */
    public function index()
    {
        $mapelTahfidz = MataPelajaran::whereIn('tipe', ['tahfidz', 'tahsin'])
            ->orderBy('tipe')->orderBy('nama')->get(['id', 'nama', 'kode', 'tipe']);
        $kelasTahfidz = Kelas::tahfidz()->count();
        $santriAktif  = Santri::aktif()->count();
        $jadwalTahfidz = JadwalMengajar::whereHas('mataPelajaran',
            fn($q) => $q->whereIn('tipe', ['tahfidz', 'tahsin']))->where('is_aktif', true)->count();

        return Inertia::render('Admin/SmartEducation/Tahfidz/Index', [
            'setting'      => SettingTahfidz::get(),
            'mapelTahfidz' => $mapelTahfidz,
            'guruOpsi'     => TenagaPendidik::aktif()->with('user:id,name')->get()
                ->map(fn($g) => ['id' => $g->id, 'nama' => $g->user?->name ?? '—'])->values(),
            'kelasTahfidzOpsi' => Kelas::aktif()->tahfidz()->orderBy('nama')->get(['id', 'nama']),
            'tahunAjaranAktif' => TahunAjaran::aktif()?->nama,
            // Sinkronisasi pencapaian awal: opsi santri (tandai yg sudah ada data) + daftar surah.
            'santriSyncOpsi'   => $this->santriSyncOpsi(),
            'surahOpsi'        => Surah::orderBy('nomor')->get(['nomor', 'nama', 'jumlah_ayat']),
            'stat' => [
                'mapel_tahfidz'  => $mapelTahfidz->where('tipe', 'tahfidz')->count(),
                'mapel_tahsin'   => $mapelTahfidz->where('tipe', 'tahsin')->count(),
                'kelas_tahfidz'  => $kelasTahfidz,
                'santri'         => $santriAktif,
                'jadwal_tahfidz' => $jadwalTahfidz,
            ],
            'setup' => [
                'mapel'  => $mapelTahfidz->isNotEmpty(),
                'kelas'  => $kelasTahfidz > 0,
                'santri' => $santriAktif > 0,
                'jadwal' => $jadwalTahfidz > 0,
            ],
        ]);
    }

    /** Simpan setting penilaian + pola sesi/jam. */
    public function updateSetting(Request $request)
    {
        $data = $request->validate([
            'nilai_min'        => 'required|integer|min:0|max:100',
            'nilai_maks'       => 'required|integer|gt:nilai_min|max:100',
            'nilai_lulus'      => 'required|numeric|min:0|max:100',
            'jam_pagi_mulai'   => 'required|date_format:H:i',
            'jam_pagi_selesai' => 'required|date_format:H:i',
            'jam_sore_mulai'   => 'required|date_format:H:i',
            'jam_sore_selesai' => 'required|date_format:H:i',
            'jp_per_sesi'      => 'required|integer|min:1|max:5',
            'pola_jadwal'      => 'nullable|array',
            'vakasi_tasmi'     => 'nullable|numeric|min:0',
        ]);

        SettingTahfidz::get()->update($data);

        return back()->with('success', 'Setting tahfidz & pola jadwal diperbarui.');
    }

    /** Opsi santri untuk sinkronisasi + kondisi pencapaiannya saat ini. */
    private function santriSyncOpsi()
    {
        $adaData = SetoranTahfidz::distinct()->pluck('santri_id')
            ->merge(HafalanJuz::distinct()->pluck('santri_id'))->unique()->flip();

        // Santri yang sudah punya ziyadah lulus TIDAK bisa disinkron ulang lewat
        // jalur biasa (anti hitung ganda) — untuk mereka dipakai Koreksi Admin
        // yang membangun ulang dari baseline + riwayat setoran.
        $adaZiyadah = SetoranTahfidz::where('jenis', 'ziyadah')->where('lulus', true)
            ->distinct()->pluck('santri_id')->flip();

        $haf   = \App\Models\HafalanSantri::get()->keyBy('santri_id');
        $juz   = HafalanJuz::get()->groupBy('santri_id');
        $total = (int) Surah::sum('jumlah_ayat');

        return Santri::aktif()->orderBy('nama_lengkap')->get(['id', 'nip', 'nama_lengkap'])
            ->map(function ($s) use ($adaData, $adaZiyadah, $haf, $juz, $total) {
                $ayat = (int) ($haf->get($s->id)?->total_ayat ?? 0);

                return [
                    'id'             => $s->id,
                    'nip'            => $s->nip,
                    'nama'           => $s->nama_lengkap,
                    'sudah_ada_data' => $adaData->has($s->id),
                    'ada_ziyadah'    => $adaZiyadah->has($s->id),
                    'total_ayat'     => $ayat,
                    'persen'         => $total > 0 ? round($ayat / $total * 100, 1) : 0,
                    'juz'            => $juz->get($s->id)?->pluck('juz')->sort()->values() ?? [],
                ];
            })->values();
    }

    /**
     * KOREKSI ADMIN — perbaiki pencapaian awal walau santri sudah setoran.
     * Mode pratinjau (`simulasi`) menampilkan sebelum→sesudah tanpa menyimpan.
     */
    public function koreksiPencapaian(Request $request)
    {
        $d = $request->validate([
            'santri_id'   => 'required|integer|exists:santri,id',
            'juz_lulus'   => 'nullable|array',
            'juz_lulus.*' => 'integer|min:1|max:30',
            'last_surah'  => 'nullable|integer|min:1|max:114',
            'last_ayat'   => 'nullable|integer|min:1',
            'pola'        => 'nullable|in:' . implode(',', TahfidzService::POLA),
            'simulasi'    => 'nullable|boolean',
        ]);

        try {
            $res = app(TahfidzService::class)->bangunUlangPencapaian(
                (int) $d['santri_id'], $d['juz_lulus'] ?? [],
                $d['last_surah'] ?? null, $d['last_ayat'] ?? null,
                $d['pola'] ?? null, (bool) ($d['simulasi'] ?? false),
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $nama = Santri::find($d['santri_id'])?->nama_lengkap ?? 'Santri';
        $ringkas = $nama . ': ' . $res['sebelum']['total_ayat'] . ' → ' . $res['sesudah']['total_ayat']
            . ' ayat (' . count($res['sesudah']['juz']) . ' juz, ' . $res['setoran_diputar'] . ' setoran diputar ulang)';

        return back()->with(
            $res['simulasi'] ? 'info' : 'success',
            ($res['simulasi'] ? 'PRATINJAU (belum disimpan) — ' : 'Pencapaian diperbarui. ') . $ringkas
        );
    }

    /** Sinkronisasi pencapaian awal santri (juz lulus + posisi tengah). Nilai lulus otomatis. */
    public function sinkronisasi(Request $request)
    {
        $d = $request->validate([
            'santri_id'   => 'required|integer|exists:santri,id',
            'juz_lulus'   => 'nullable|array',
            'juz_lulus.*' => 'integer|min:1|max:30',
            'last_surah'  => 'nullable|integer|min:1|max:114',
            'last_ayat'   => 'nullable|integer|min:1',
        ]);

        try {
            $res = app(TahfidzService::class)->seedPencapaian(
                (int) $d['santri_id'],
                $d['juz_lulus'] ?? [],
                $d['last_surah'] ?? null,
                $d['last_ayat'] ?? null,
            );
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $posisi = $res['partial_juz'] ? " + posisi tengah (juz {$res['partial_juz']})" : '';
        return back()->with('success',
            "Sinkronisasi tersimpan: {$res['juz_lulus']} juz lulus{$posisi} · total {$res['total_ayat']} ayat.");
    }

    /** Generator jadwal tahfidz standar (1 klik bikin slot mingguan). */
    public function generateJadwal(Request $request)
    {
        $data = $request->validate([
            'tenaga_pendidik_id' => 'required|exists:tenaga_pendidik,id',
            'kelas_id'           => 'required|exists:kelas,id',
            'mata_pelajaran_id'  => 'required|exists:mata_pelajaran,id',
        ]);

        $ta = TahunAjaran::aktif();
        if (!$ta) return back()->with('error', 'Belum ada tahun ajaran aktif.');

        $kelas   = Kelas::findOrFail($data['kelas_id']);
        $setting = SettingTahfidz::get();
        $pola    = $setting->pola_jadwal ?: SettingTahfidz::POLA_DEFAULT;

        $dibuat = 0; $lewat = 0;
        foreach ($pola as $hari => $sesiList) {
            foreach ((array) $sesiList as $sesi) {
                [$mulai, $selesai] = $setting->jamSesi($sesi);

                $ada = JadwalMengajar::where('tenaga_pendidik_id', $data['tenaga_pendidik_id'])
                    ->where('tahun_ajaran_id', $ta->id)->where('hari', $hari)
                    ->where('jam_mulai', $mulai)->where('kelas_id', $kelas->id)
                    ->where('is_aktif', true)->exists();
                if ($ada) { $lewat++; continue; }

                JadwalMengajar::create([
                    'tahun_ajaran_id'    => $ta->id,
                    'tenaga_pendidik_id' => $data['tenaga_pendidik_id'],
                    'mata_pelajaran_id'  => $data['mata_pelajaran_id'],
                    'kelas_id'           => $kelas->id,
                    'hari'               => $hari,
                    'jam_mulai'          => $mulai,
                    'jam_selesai'        => $selesai,
                    'jumlah_jp'          => $setting->jp_per_sesi ?? 1,
                    'kelas'              => $kelas->nama,
                    'is_aktif'           => true,
                ]);
                $dibuat++;
            }
        }

        return back()->with('success',
            "Jadwal tahfidz: {$dibuat} slot dibuat" . ($lewat ? ", {$lewat} sudah ada (dilewati)." : "."));
    }
}

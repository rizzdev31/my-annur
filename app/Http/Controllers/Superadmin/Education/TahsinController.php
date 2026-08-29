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
use App\Services\TahsinService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Hub Smart Tahsin: status setup + GENERATOR JADWAL (seperti tahfidz).
 * Jam tahsin = sama dengan tahfidz → memakai pola jam dari SettingTahfidz.
 */
class TahsinController extends Controller
{
    public function index()
    {
        $mapelTahsin   = MataPelajaran::where('tipe', 'tahsin')->orderBy('nama')->get(['id', 'nama', 'kode', 'tipe']);
        $kelasTahsin   = Kelas::aktif()->tahsin()->count();
        $santriTahsin  = Santri::aktif()->where('program_quran', 'tahsin')->count();
        $jadwalTahsin  = JadwalMengajar::whereHas('mataPelajaran', fn($q) => $q->where('tipe', 'tahsin'))
            ->where('is_aktif', true)->count();

        $setting = SettingTahfidz::get();

        return Inertia::render('Admin/SmartEducation/Tahsin/Index', [
            'guruOpsi' => TenagaPendidik::aktif()->with('user:id,name')->get()
                ->map(fn($g) => ['id' => $g->id, 'nama' => $g->user?->name ?? '—'])->values(),
            'kelasTahsinOpsi' => Kelas::aktif()->tahsin()->orderBy('nama')->get(['id', 'nama', 'level_tahsin'])
                ->map(fn($k) => [
                    'id'   => $k->id,
                    'nama' => $k->nama,
                    'label' => $k->nama . ' · ' . TahsinService::levelLabel((int) ($k->level_tahsin ?? 1)),
                ])->values(),
            'mapelTahsin'      => $mapelTahsin,
            'tahunAjaranAktif' => TahunAjaran::aktif()?->nama,
            'polaJadwal'       => $setting->pola_jadwal ?: SettingTahfidz::POLA_DEFAULT,
            'jamSesiInfo'      => [
                'pagi' => [$setting->jam_pagi_mulai ?? '05:00', $setting->jam_pagi_selesai ?? '05:45'],
                'sore' => [$setting->jam_sore_mulai ?? '15:30', $setting->jam_sore_selesai ?? '16:15'],
            ],
            'jpPerSesi' => $setting->jp_per_sesi ?? 1,
            'stat' => [
                'mapel_tahsin'  => $mapelTahsin->count(),
                'kelas_tahsin'  => $kelasTahsin,
                'santri'        => $santriTahsin,
                'jadwal_tahsin' => $jadwalTahsin,
            ],
            'setup' => [
                'mapel'  => $mapelTahsin->isNotEmpty(),
                'kelas'  => $kelasTahsin > 0,
                'jadwal' => $jadwalTahsin > 0,
            ],
        ]);
    }

    /** Generator jadwal tahsin (1 klik bikin slot mingguan, jam mengikuti pola tahfidz). */
    public function generateJadwal(Request $request)
    {
        $data = $request->validate([
            'tenaga_pendidik_id' => 'required|exists:tenaga_pendidik,id',
            'kelas_id'           => 'required|exists:kelas,id',
            'mata_pelajaran_id'  => 'required|exists:mata_pelajaran,id',
        ]);

        $ta = TahunAjaran::aktif();
        if (!$ta) return back()->with('error', 'Belum ada tahun ajaran aktif.');

        // Penjaga konsistensi: kelas harus tahsin & mapel harus tipe tahsin.
        $kelas = Kelas::findOrFail($data['kelas_id']);
        if ($kelas->jenis !== 'tahsin') {
            return back()->with('error', 'Kelas yang dipilih bukan kelas Tahsin.');
        }
        if (MataPelajaran::find($data['mata_pelajaran_id'])?->tipe !== 'tahsin') {
            return back()->with('error', 'Mata pelajaran yang dipilih bukan tipe Tahsin.');
        }

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
            "Jadwal tahsin: {$dibuat} slot dibuat" . ($lewat ? ", {$lewat} sudah ada (dilewati)." : "."));
    }
}

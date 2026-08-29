<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\JadwalShift;
use App\Models\SettingJamKerja;
use App\Models\TenagaPendidik;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JadwalShiftController extends Controller
{
    /** Guru berjabatan keamanan/satpam. */
    private function satpamQuery()
    {
        return TenagaPendidik::where('is_aktif', true)
            ->whereHas('jabatan', fn ($q) => $q->where('nama_jabatan', 'like', '%keamanan%')
                ->orWhere('nama_jabatan', 'like', '%satpam%'))
            ->with(['user', 'jabatan']);
    }

    public function index()
    {
        $today = now()->toDateString();

        $satpam = $this->satpamQuery()->get()->map(function ($g) use ($today) {
            $shifts = JadwalShift::where('tenaga_pendidik_id', $g->id)
                ->whereDate('tanggal_selesai', '>=', $today)
                ->with('jamKerja')->orderBy('tanggal_mulai')->get()
                ->map(fn ($s) => [
                    'id'      => $s->id,
                    'shift'   => $s->jamKerja?->nama,
                    'jam'     => $s->jamKerja ? substr((string) $s->jamKerja->jam_masuk, 0, 5) . '–' . substr((string) $s->jamKerja->jam_pulang, 0, 5) : '',
                    'mulai'   => $s->tanggal_mulai?->format('d/m/Y'),
                    'selesai' => $s->tanggal_selesai?->format('d/m/Y'),
                ]);
            $aktif = $g->jamKerjaAktif($today);
            return [
                'id'           => $g->id,
                'nama'         => $g->user?->name ?? ('Guru #' . $g->id),
                'jabatan'      => $g->jabatan?->nama_jabatan ?? '—',
                'shift_hari_ini' => $aktif?->nama,
                'shifts'       => $shifts,
            ];
        })->values();

        return Inertia::render('Admin/SmartPayroll/JadwalShift/Index', [
            'satpam'      => $satpam,
            'shiftOptions'=> SettingJamKerja::where('is_aktif', true)->get()
                ->map(fn ($s) => [
                    'id'   => $s->id,
                    'nama' => $s->nama,
                    'jam'  => substr((string) $s->jam_masuk, 0, 5) . '–' . substr((string) $s->jam_pulang, 0, 5),
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tenaga_pendidik_id'   => 'required|integer|exists:tenaga_pendidik,id',
            'setting_jam_kerja_id' => 'required|integer|exists:setting_jam_kerja,id',
            'tanggal_mulai'        => 'required|date',
            'tanggal_selesai'      => 'required|date|after_or_equal:tanggal_mulai',
            'keterangan'           => 'nullable|string|max:150',
        ]);

        JadwalShift::create($data + ['dibuat_oleh' => auth()->id()]);
        return back()->with('success', 'Shift ditetapkan.');
    }

    public function destroy(JadwalShift $jadwalShift)
    {
        $jadwalShift->delete();
        return back()->with('success', 'Shift dihapus.');
    }

    /**
     * Rotasi cepat: bagi shift bergilir ke daftar satpam untuk beberapa periode.
     * Periode p, satpam ke-i → shift[(i + p) % jumlah_shift].
     */
    public function rotasi(Request $request)
    {
        $data = $request->validate([
            'tenaga_pendidik_ids'   => 'required|array|min:1',
            'tenaga_pendidik_ids.*' => 'integer|exists:tenaga_pendidik,id',
            'setting_jam_kerja_ids'   => 'required|array|min:1',
            'setting_jam_kerja_ids.*' => 'integer|exists:setting_jam_kerja,id',
            'tanggal_mulai'         => 'required|date',
            'interval'              => 'required|in:mingguan,bulanan',
            'jumlah_periode'        => 'required|integer|min:1|max:24',
        ]);

        $guru   = $data['tenaga_pendidik_ids'];
        $shifts = $data['setting_jam_kerja_ids'];
        $nShift = count($shifts);
        $mulai  = Carbon::parse($data['tanggal_mulai']);
        $dibuat = 0;

        for ($p = 0; $p < $data['jumlah_periode']; $p++) {
            if ($data['interval'] === 'mingguan') {
                $ps = $mulai->copy()->addWeeks($p)->startOfDay();
                $pe = $ps->copy()->addDays(6);
            } else {
                $ps = $mulai->copy()->addMonthsNoOverflow($p)->startOfDay();
                $pe = $ps->copy()->addMonthNoOverflow()->subDay();
            }
            foreach ($guru as $i => $tpId) {
                JadwalShift::create([
                    'tenaga_pendidik_id'   => $tpId,
                    'setting_jam_kerja_id' => $shifts[($i + $p) % $nShift],
                    'tanggal_mulai'        => $ps->toDateString(),
                    'tanggal_selesai'      => $pe->toDateString(),
                    'keterangan'           => 'Rotasi ' . $data['interval'] . ' otomatis',
                    'dibuat_oleh'          => auth()->id(),
                ]);
                $dibuat++;
            }
        }

        return back()->with('success', "Rotasi dibuat: {$dibuat} jadwal shift.");
    }
}

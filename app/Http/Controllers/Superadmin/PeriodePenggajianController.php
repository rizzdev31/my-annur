<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\PeriodePenggajian;
use App\Models\Penggajian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class PeriodePenggajianController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/SmartPayroll/Periode/Index', [
            'periodes' => PeriodePenggajian::withCount('penggajian')
                ->orderByDesc('tahun')->orderByDesc('bulan')
                ->paginate(12),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/SmartPayroll/Periode/Form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'            => 'required|string|max:100',
            'bulan'           => 'required|integer|between:1,12',
            'tahun'           => 'required|integer|min:2020',
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
        ]);

        PeriodePenggajian::create(array_merge($data, [
            'status'      => 'draft',
            'dibuat_oleh' => auth()->id(),
        ]));

        return redirect()->route('admin.smart-payroll.periode.index')
            ->with('success', 'Periode penggajian berhasil dibuat.');
    }

    public function edit(PeriodePenggajian $periode)
    {
        return Inertia::render('Admin/SmartPayroll/Periode/Form', [
            'periode' => $periode,
        ]);
    }

    public function update(Request $request, PeriodePenggajian $periode)
    {
        if ($periode->status === 'dibayar') {
            return back()->with('error', 'Periode yang sudah dibayar tidak bisa diubah.');
        }

        $data = $request->validate([
            'nama'            => 'required|string|max:100',
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
        ]);

        $periode->update($data);

        return redirect()->route('admin.smart-payroll.periode.index')
            ->with('success', 'Periode diperbarui.');
    }

    public function destroy(PeriodePenggajian $periode)
    {
        if ($periode->status !== 'draft') {
            return back()->with('error', 'Hanya periode berstatus draft yang bisa dihapus.');
        }

        $periode->delete();

        return redirect()->route('admin.smart-payroll.periode.index')
            ->with('success', 'Periode dihapus.');
    }

    /**
     * Tandai periode SUDAH DIBAYAR + kunci.
     *
     * PENTING: cascade ke seluruh record penggajian agar status guru ikut
     * berubah menjadi 'dibayar' (sinkron dengan slip gaji di aplikasi Flutter).
     * Sebelumnya hanya status periode yang berubah → slip guru tetap "Siap Bayar".
     *
     * Guard: wajib semua penggajian sudah difinalisasi (tidak ada draft).
     */
    public function kunci(PeriodePenggajian $periode)
    {
        if ($periode->status === 'dibayar') {
            return back()->with('error', 'Periode ini sudah ditandai dibayar.');
        }

        $total = Penggajian::where('periode_penggajian_id', $periode->id)->count();
        if ($total === 0) {
            return back()->with('error',
                'Belum ada data penggajian pada periode ini. Generate gaji terlebih dahulu.');
        }

        $draftCount = Penggajian::where('periode_penggajian_id', $periode->id)
            ->where('status', 'draft')->count();
        if ($draftCount > 0) {
            return back()->with('error',
                "Masih ada {$draftCount} penggajian berstatus draft. "
                . "Finalisasi semua terlebih dahulu sebelum menandai dibayar.");
        }

        DB::transaction(function () use ($periode) {
            // Cascade: semua penggajian final → dibayar (+ stempel waktu bayar)
            Penggajian::where('periode_penggajian_id', $periode->id)
                ->where('status', 'final')
                ->update(['status' => 'dibayar', 'dibayar_pada' => now()]);

            $periode->update(['status' => 'dibayar', 'dikunci_pada' => now()]);
        });

        return back()->with('success',
            'Periode ditandai sudah dibayar. Seluruh slip gaji guru diperbarui ke status "Dibayar".');
    }
}
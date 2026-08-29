<?php

namespace App\Http\Controllers\Superadmin\Education;

use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Models\Kelas;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SantriController extends Controller
{
    public function __construct(private \App\Services\SantriSyncService $sync) {}

    public function index(Request $request)
    {
        $santri = Santri::with('kelas:id,nama,jenis')
            ->orderBy('nama_lengkap')->get()
            ->map(fn($s) => [
                'id'             => $s->id,
                'nip'            => $s->nip,
                'nama_lengkap'   => $s->nama_lengkap,
                'nama_panggilan' => $s->nama_panggilan,
                'email'          => $s->email,
                'jenis_kelamin'  => $s->jenis_kelamin,
                'tempat_lahir'   => $s->tempat_lahir,
                'tanggal_lahir'  => $s->tanggal_lahir?->format('Y-m-d'),
                'no_whatsapp'    => $s->no_whatsapp,
                'tahsin_level'   => $s->tahsin_level,
                'program_quran'  => $s->program_quran,
                'is_aktif'       => $s->is_aktif,
                'punya_password' => $s->password !== null,   // status aktivasi Portal Santri
                'kelas'          => $s->kelas->map(fn($k) => ['id' => $k->id, 'nama' => $k->nama, 'jenis' => $k->jenis]),
                'kelas_ids'      => $s->kelas->pluck('id'),
            ]);

        return Inertia::render('Admin/SmartEducation/Santri/Index', [
            'santri' => $santri,
            'kelas'  => Kelas::aktif()->orderBy('jenis')->orderBy('nama')
                ->get(['id', 'nama', 'jenis']),
            'summary' => [
                'total' => Santri::count(),
                'aktif' => Santri::aktif()->count(),
                'putra' => Santri::where('jenis_kelamin', 'L')->count(),
                'putri' => Santri::where('jenis_kelamin', 'P')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $kelasIds = $data['kelas_ids'] ?? [];
        unset($data['kelas_ids']);

        $santri = Santri::create(array_merge($data, ['is_aktif' => true]));
        $santri->kelas()->sync($kelasIds);
        $this->sync->sync($santri);

        return back()->with('success', "Santri {$data['nama_lengkap']} berhasil ditambahkan.");
    }

    public function update(Request $request, Santri $santri)
    {
        $data = $this->validateData($request, $santri->id);
        $kelasIds = $data['kelas_ids'] ?? [];
        unset($data['kelas_ids']);

        $santri->update($data);
        $santri->kelas()->sync($kelasIds);
        $this->sync->sync($santri->fresh());

        return back()->with('success', "Santri {$santri->nama_lengkap} berhasil diperbarui.");
    }

    public function destroy(Santri $santri)
    {
        $santri->update(['is_aktif' => false]);
        $this->sync->sync($santri);
        return back()->with('success', "Santri {$santri->nama_lengkap} dinonaktifkan.");
    }

    public function aktifkan(Santri $santri)
    {
        $santri->update(['is_aktif' => true]);
        $this->sync->sync($santri);
        return back()->with('success', "Santri {$santri->nama_lengkap} diaktifkan kembali.");
    }

    /** Reset password Portal Santri → kosong (wali wajib aktivasi ulang via tanggal lahir/OTP). */
    public function resetPasswordPortal(Santri $santri)
    {
        $santri->forceFill(['password' => null])->save();
        $santri->tokens()->delete(); // cabut semua sesi login portal
        return back()->with('success', "Password Portal {$santri->nama_lengkap} direset. Wali dapat aktivasi ulang.");
    }

    /** Set password Portal Santri langsung (mis. diserahkan ke wali yang kesulitan aktivasi). */
    public function setPasswordPortal(Request $request, Santri $santri)
    {
        $request->validate(['password' => 'required|string|min:6|max:100']);
        $santri->password = $request->password; // cast 'hashed'
        $santri->save();
        $santri->tokens()->delete();
        return back()->with('success', "Password Portal {$santri->nama_lengkap} diperbarui.");
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'nip'            => 'required|string|max:30|unique:santri,nip' . ($ignoreId ? ",$ignoreId" : ''),
            'nama_lengkap'   => 'required|string|max:150',
            'nama_panggilan' => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:150',
            'jenis_kelamin'  => 'required|in:L,P',
            'tempat_lahir'   => 'nullable|string|max:100',
            'tanggal_lahir'  => 'nullable|date',
            'no_whatsapp'    => 'nullable|string|max:20',
            'tahsin_level'   => 'nullable|integer|min:1|max:6',
            'program_quran'  => 'nullable|in:tahsin,tahfidz',
            'kelas_ids'      => 'nullable|array',
            'kelas_ids.*'    => 'integer|exists:kelas,id',
        ]);
    }

    /** Download template Excel import santri. */
    public function templateImport()
    {
        $headings = ['nip', 'nama_lengkap', 'nama_panggilan', 'email', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'no_whatsapp', 'tahsin_level', 'program_quran'];
        $contoh   = [['2024001', 'Ahmad Fulan', 'Ahmad', 'ahmad@contoh.com', 'L', 'Sidoarjo', '2010-05-17', '081234567890', '1', 'tahfidz']];
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\TemplateExport($headings, $contoh), 'template-import-santri.xlsx');
    }

    /** Import santri dari Excel. */
    public function import(\Illuminate\Http\Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);
        $imp = new \App\Imports\SantriImport();
        try {
            \Maatwebsite\Excel\Facades\Excel::import($imp, $request->file('file'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal membaca file: ' . $e->getMessage());
        }
        $msg = "Import santri: {$imp->berhasil} berhasil, {$imp->gagal} gagal."
            . (!empty($imp->errors) ? ' — ' . implode(' | ', array_slice($imp->errors, 0, 3)) : '');
        return back()->with($imp->berhasil > 0 ? 'success' : 'error', $msg);
    }
}

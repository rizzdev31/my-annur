<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Kelola Pengumuman/Pamflet pop-up aplikasi Flutter — superadmin only
 * (fail-safe via middleware 'akses', route tak dipetakan ke modul RBAC).
 *
 * Kebijakan "1 informasi saja": hanya SATU pengumuman aktif pada satu waktu.
 * Mengaktifkan satu pamflet otomatis menonaktifkan yang lain.
 */
class PengumumanController extends Controller
{
    public function index()
    {
        $list = Pengumuman::orderByDesc('aktif')->terurut()->get()
            ->map(fn($p) => [
                'id'         => $p->id,
                'judul'      => $p->judul,
                'tipe'       => $p->tipe ?? 'gambar',
                'gambar_url' => $p->gambar_url,
                'file_url'   => $p->file_url,
                'nama_file'  => $p->nama_file,
                'isi'        => $p->isi,
                'link_url'   => $p->link_url,
                'aktif'      => $p->aktif,
                'urutan'     => $p->urutan,
                'sumber_tipe'=> $p->sumber_tipe,
                'sumber_id'  => $p->sumber_id,
                'updated_at' => $p->updated_at?->format('d M Y H:i'),
            ]);

        return Inertia::render('Admin/Pengaturan/Pengumuman/Index', [
            'pengumuman' => $list,
        ]);
    }

    public function store(Request $request)
    {
        // Pengumuman kini boleh berupa pamflet GAMBAR atau berkas PDF (mis. notulensi
        // rapat), dan boleh AKTIF bersamaan dengan pengumuman lain — dulu mengaktifkan
        // satu berarti menonaktifkan semua yang lain.
        $d = $request->validate([
            'judul'    => 'nullable|string|max:150',
            'tipe'     => 'required|in:gambar,pdf',
            'gambar'   => 'required_if:tipe,gambar|nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'file'     => 'required_if:tipe,pdf|nullable|file|mimes:pdf|max:8192',
            'isi'      => 'nullable|string|max:1000',
            'link_url' => 'nullable|url|max:255',
            'aktif'    => 'boolean',
            'urutan'   => 'nullable|integer|min:0|max:999',
        ]);

        Pengumuman::create([
            'judul'     => $d['judul'] ?? null,
            'tipe'      => $d['tipe'],
            'gambar'    => $request->hasFile('gambar') ? $this->simpanGambar($request->file('gambar')) : null,
            'file'      => $request->hasFile('file') ? $request->file('file')->store('pengumuman-berkas', 'public') : null,
            'nama_file' => $request->hasFile('file') ? $request->file('file')->getClientOriginalName() : null,
            'isi'       => $d['isi'] ?? null,
            'link_url'  => $d['link_url'] ?? null,
            'aktif'     => $request->boolean('aktif'),
            'urutan'    => (int) ($d['urutan'] ?? 0),
        ]);

        return back()->with('success', 'Pengumuman berhasil dibuat.');
    }

    public function update(Request $request, Pengumuman $pengumuman)
    {
        $d = $request->validate([
            'judul'    => 'nullable|string|max:150',
            'tipe'     => 'nullable|in:gambar,pdf',
            'gambar'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'file'     => 'nullable|file|mimes:pdf|max:8192',
            'isi'      => 'nullable|string|max:1000',
            'link_url' => 'nullable|url|max:255',
            'aktif'    => 'boolean',
            'urutan'   => 'nullable|integer|min:0|max:999',
        ]);

        $update = [
            'judul'    => $d['judul'] ?? null,
            'tipe'     => $d['tipe'] ?? $pengumuman->tipe ?? 'gambar',
            'isi'      => $d['isi'] ?? null,
            'link_url' => $d['link_url'] ?? null,
            'aktif'    => $request->boolean('aktif'),
            'urutan'   => (int) ($d['urutan'] ?? $pengumuman->urutan ?? 0),
        ];

        if ($request->hasFile('gambar')) {
            if ($pengumuman->gambar) Storage::disk('public')->delete($pengumuman->gambar);
            $update['gambar'] = $this->simpanGambar($request->file('gambar'));
        }
        if ($request->hasFile('file')) {
            if ($pengumuman->file) Storage::disk('public')->delete($pengumuman->file);
            $update['file']      = $request->file('file')->store('pengumuman-berkas', 'public');
            $update['nama_file'] = $request->file('file')->getClientOriginalName();
        }

        $pengumuman->update($update);

        return back()->with('success', 'Pengumuman diperbarui.');
    }

    /** Aktif/nonaktifkan cepat. Beberapa pengumuman boleh aktif bersamaan. */
    public function toggle(Pengumuman $pengumuman)
    {
        $baru = !$pengumuman->aktif;
        $pengumuman->update(['aktif' => $baru]);

        $jumlahAktif = Pengumuman::aktif()->count();

        return back()->with('success', $baru
            ? "Pengumuman diaktifkan — kini ada {$jumlahAktif} pengumuman aktif."
            : 'Pengumuman dinonaktifkan.');
    }

    public function destroy(Pengumuman $pengumuman)
    {
        if ($pengumuman->gambar) Storage::disk('public')->delete($pengumuman->gambar);
        if ($pengumuman->file) Storage::disk('public')->delete($pengumuman->file);
        // Lepas tautan dari kegiatan agar notulensinya bisa diganti/dipublikasikan lagi.
        \App\Models\AbsensiKegiatan::where('pengumuman_id', $pengumuman->id)->update(['pengumuman_id' => null]);
        $pengumuman->delete();

        return back()->with('success', 'Pengumuman dihapus.');
    }

    /**
     * Simpan gambar pamflet TERKOMPRES (GD): resize maks lebar 1080px + JPEG q80.
     * Ini menekan ukuran dari beberapa MB → ratusan KB, agar terkirim penuh
     * meski server dev (php artisan serve) single-thread di Windows. Bila GD gagal,
     * fallback simpan apa adanya.
     *
     * @return string path relatif di disk 'public'
     */
    private function simpanGambar(\Illuminate\Http\UploadedFile $file): string
    {
        $src = $file->getRealPath();
        $info = @getimagesize($src);
        // function_exists: cegah fatal "Call to undefined function" bila GD di
        // server tak mendukung format tertentu (mis. WebP tanpa --with-webp).
        // Bila tak didukung → biarkan fallback simpan apa adanya di bawah.
        $img  = match ($info['mime'] ?? null) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($src) : false,
            'image/png'  => function_exists('imagecreatefrompng')  ? @imagecreatefrompng($src)  : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
            default      => false,
        };

        // Fallback bila GD tak bisa memproses.
        if (!$img) {
            return $file->store('pengumuman', 'public');
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $maxW = 1080;
        $nw = $w > $maxW ? $maxW : $w;
        $nh = (int) round($h * $nw / $w);

        // Kanvas putih (meratakan transparansi PNG saat dikonversi ke JPEG).
        $out = imagecreatetruecolor($nw, $nh);
        $white = imagecolorallocate($out, 255, 255, 255);
        imagefilledrectangle($out, 0, 0, $nw, $nh, $white);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);

        Storage::disk('public')->makeDirectory('pengumuman');
        $path = 'pengumuman/' . \Illuminate\Support\Str::random(40) . '.jpg';
        imagejpeg($out, Storage::disk('public')->path($path), 80);
        imagedestroy($out);

        return $path;
    }
}

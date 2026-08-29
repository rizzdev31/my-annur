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
        $list = Pengumuman::orderByDesc('aktif')->orderByDesc('updated_at')->get()
            ->map(fn($p) => [
                'id'         => $p->id,
                'judul'      => $p->judul,
                'gambar_url' => $p->gambar_url,
                'link_url'   => $p->link_url,
                'aktif'      => $p->aktif,
                'updated_at' => $p->updated_at?->format('d M Y H:i'),
            ]);

        return Inertia::render('Admin/Pengaturan/Pengumuman/Index', [
            'pengumuman' => $list,
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'judul'    => 'nullable|string|max:150',
            'gambar'   => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
            'link_url' => 'nullable|url|max:255',
            'aktif'    => 'boolean',
        ]);

        $path  = $this->simpanGambar($request->file('gambar'));
        $aktif = $request->boolean('aktif');

        if ($aktif) {
            Pengumuman::where('aktif', true)->update(['aktif' => false]);
        }

        Pengumuman::create([
            'judul'    => $d['judul'] ?? null,
            'gambar'   => $path,
            'link_url' => $d['link_url'] ?? null,
            'aktif'    => $aktif,
        ]);

        return back()->with('success', 'Pengumuman berhasil dibuat.');
    }

    public function update(Request $request, Pengumuman $pengumuman)
    {
        $d = $request->validate([
            'judul'    => 'nullable|string|max:150',
            'gambar'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'link_url' => 'nullable|url|max:255',
            'aktif'    => 'boolean',
        ]);

        $aktif = $request->boolean('aktif');
        if ($aktif) {
            Pengumuman::where('aktif', true)->where('id', '!=', $pengumuman->id)
                ->update(['aktif' => false]);
        }

        $update = [
            'judul'    => $d['judul'] ?? null,
            'link_url' => $d['link_url'] ?? null,
            'aktif'    => $aktif,
        ];

        if ($request->hasFile('gambar')) {
            if ($pengumuman->gambar) Storage::disk('public')->delete($pengumuman->gambar);
            $update['gambar'] = $this->simpanGambar($request->file('gambar'));
        }

        $pengumuman->update($update);

        return back()->with('success', 'Pengumuman diperbarui.');
    }

    /** Aktif/nonaktifkan cepat; mengaktifkan satu → nonaktifkan lainnya. */
    public function toggle(Pengumuman $pengumuman)
    {
        $baru = !$pengumuman->aktif;
        if ($baru) {
            Pengumuman::where('aktif', true)->where('id', '!=', $pengumuman->id)
                ->update(['aktif' => false]);
        }
        $pengumuman->update(['aktif' => $baru]);

        return back()->with('success', $baru
            ? 'Pengumuman diaktifkan (yang lain dinonaktifkan).'
            : 'Pengumuman dinonaktifkan.');
    }

    public function destroy(Pengumuman $pengumuman)
    {
        if ($pengumuman->gambar) Storage::disk('public')->delete($pengumuman->gambar);
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
        $img  = match ($info['mime'] ?? null) {
            'image/jpeg' => @imagecreatefromjpeg($src),
            'image/png'  => @imagecreatefrompng($src),
            'image/webp' => @imagecreatefromwebp($src),
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

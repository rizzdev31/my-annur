<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\SettingWa;
use App\Services\FonnteClient;
use App\Services\WaTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Pengaturan template Bot WhatsApp (Fonnte) untuk superadmin.
 * Edit nama bot, salam, footer, dan body template per jenis + uji kirim.
 */
class SettingWaController extends Controller
{
    public function index()
    {
        $s = SettingWa::get();

        return Inertia::render('Admin/SettingWa/Index', [
            'setting' => $s->only([
                'nama_bot', 'pakai_salam', 'footer',
                'tpl_controlling', 'tpl_mengajar', 'tpl_pelanggaran', 'tpl_apresiasi', 'tpl_konselor',
            ]),
            'preview' => [
                'controlling' => WaTemplate::preview('controlling', $s->tpl_controlling ?: SettingWa::DEF_CONTROLLING),
                'mengajar'    => WaTemplate::preview('mengajar', $s->tpl_mengajar ?: SettingWa::DEF_MENGAJAR),
                'pelanggaran' => WaTemplate::preview('pelanggaran', $s->tpl_pelanggaran ?: SettingWa::DEF_PELANGGARAN),
                'apresiasi'   => WaTemplate::preview('apresiasi', $s->tpl_apresiasi ?: SettingWa::DEF_APRESIASI),
                'konselor'    => WaTemplate::preview('konselor', $s->tpl_konselor ?: SettingWa::DEF_KONSELOR),
            ],
            'fonnte' => [
                'aktif'      => (bool) config('fonnte.enabled'),
                'token_ada'  => (bool) config('fonnte.token'),
            ],
            'placeholder' => [
                'umum'        => ['{nama}', '{tanggal}', '{status}'],
                'controlling' => ['{kegiatan}', '{jam}'],
                'mengajar'    => ['{pembelajaran}'],
                'eksekusi'    => ['{label}', '{poin}', '{catatan}'],
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'nama_bot'        => 'required|string|max:100',
            'pakai_salam'     => 'boolean',
            'footer'          => 'nullable|string|max:500',
            'tpl_controlling' => 'required|string|max:2000',
            'tpl_mengajar'    => 'required|string|max:2000',
            'tpl_pelanggaran' => 'required|string|max:2000',
            'tpl_apresiasi'   => 'required|string|max:2000',
            'tpl_konselor'    => 'required|string|max:2000',
        ]);

        SettingWa::get()->update($data);
        return back()->with('success', 'Template WhatsApp diperbarui.');
    }

    /** Kirim WA percobaan ke nomor tertentu (verifikasi token & device Fonnte). */
    public function test(Request $request, FonnteClient $client)
    {
        $request->validate(['nomor' => 'required|string|max:20']);

        if (!config('fonnte.enabled') || !config('fonnte.token')) {
            return back()->with('error', 'Fonnte belum aktif / token kosong (cek .env).');
        }

        $tujuan = FonnteClient::normalisasiNomor($request->nomor);
        if (!$tujuan) return back()->with('error', 'Nomor tidak valid.');

        $pesan = WaTemplate::preview('controlling', SettingWa::get()->tpl_controlling ?: SettingWa::DEF_CONTROLLING);

        try {
            $resp = $client->kirim($tujuan, "🧪 *UJI KIRIM*\n\n" . $pesan);
            $body = $resp->json() ?? [];
            if ($resp->successful() && ($body['status'] ?? false) === true) {
                return back()->with('success', "Pesan uji terkirim ke {$tujuan}.");
            }
            $reason = $body['reason'] ?? ('HTTP ' . $resp->status());
            // Arahan bila token bermasalah (kesalahan umum: pakai token akun, bukan token DEVICE).
            if (stripos($reason, 'token') !== false || stripos($reason, 'invalid') !== false) {
                $reason .= ' — pastikan pakai TOKEN DEVICE Fonnte (Dashboard → Device → Token), bukan token akun. '
                    . 'Setelah ganti: perbarui FONNTE_TOKEN di .env lalu `php artisan config:clear`.';
            }
            return back()->with('error', 'Gagal: ' . $reason);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menghubungi Fonnte: ' . $e->getMessage());
        }
    }
}

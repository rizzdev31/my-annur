<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbsensiHarian;
use App\Models\SettingJamKerja;
use App\Services\AbsensiKalkulasiService;
use App\Services\TimezoneHelper;
use App\Services\LokasiAbsensiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AbsensiApiController extends Controller
{
    public function __construct(
        private readonly LokasiAbsensiService $lokasiService
    ) {}

    // ══════════════════════════════════════════════════════════════════════════
    // STATUS ABSENSI HARI INI
    // ══════════════════════════════════════════════════════════════════════════

    public function hariIni(Request $request): JsonResponse
    {
        $user = $request->user();
        $tp   = $user->tenagaPendidik;

        if (!$tp) {
            return response()->json([
                'success' => false,
                'message' => 'Data tenaga pendidik tidak ditemukan.',
            ], 404);
        }

        // Tanggal & waktu acuan (WIB). device_date dari Flutter untuk konsistensi TZ.
        $today = TimezoneHelper::tanggalDariRequest($request->device_date);
        $now   = TimezoneHelper::now();

        // Setting jam kerja per individu tendik (fallback default).
        $jamKerja = $tp->jamKerjaAktif();

        // Tanggal KERJA efektif (overnight-aware): bila shift lintas-hari kemarin masih
        // berjalan & shift hari ini belum buka → pakai tanggal kemarin (mis. dini hari).
        $kerjaDate = $this->resolveTanggalKerja($tp, $jamKerja, $today, $now);
        $namaHari  = TimezoneHelper::namaHariDB($kerjaDate);

        // Record absensi untuk tanggal kerja efektif.
        $absensi = AbsensiHarian::where('tenaga_pendidik_id', $tp->id)
            ->whereDate('tanggal', $kerjaDate)->first();

        $jadwal = $jamKerja ? TimezoneHelper::getJadwalHariIni($jamKerja, $kerjaDate) : null;

        // ── Waktu absolut (mendukung lintas hari) ───────────────────────────
        $checkinOpenDt = null; $jamPulangDt = null;
        $bisaCheckInMulai = null; $bisaCheckoutMulai = null; $jamPulangJadwal = null;
        if ($jadwal && isset($jadwal['jam_masuk'], $jadwal['jam_pulang'])) {
            $masukDt       = Carbon::parse($kerjaDate->toDateString() . ' ' . $jadwal['jam_masuk'], TimezoneHelper::TZ);
            $checkinOpenDt = $masukDt->copy()->subMinutes(30);
            $jamPulangDt   = Carbon::parse($kerjaDate->toDateString() . ' ' . $jadwal['jam_pulang'], TimezoneHelper::TZ);
            if ($jadwal['lintas_hari'] ?? false) $jamPulangDt->addDay();

            $bisaCheckInMulai  = $checkinOpenDt->format('H:i');
            $bisaCheckoutMulai = $jamPulangDt->format('H:i');
            $jamPulangJadwal   = substr((string) $jadwal['jam_pulang'], 0, 5);
        }

        // ── Izin/cuti & libur (acuan tanggal kerja) ─────────────────────────
        $izinAktif   = $this->deteksiIzinAktif($tp->id, $kerjaDate->toDateString());
        $isDinasLuar = $izinAktif && $izinAktif['status_absensi'] === 'dinas_luar';

        $hariLiburAktif = \App\Models\HariLibur::where('is_aktif', true)
            ->whereNull('dibatalkan_pada')
            ->where('tanggal', '<=', $kerjaDate->toDateString())
            ->where(fn($q) =>
                $q->whereNull('tanggal_selesai')
                  ->orWhere('tanggal_selesai', '>=', $kerjaDate->toDateString())
            )
            ->select('id', 'nama', 'sumber', 'tipe')
            ->first();
        $liburMingguan = $jamKerja && $jamKerja->isHariLibur($namaHari);
        $isLibur       = $hariLiburAktif !== null || $liburMingguan;
        // Libur individu guru mukim: TIDAK menonaktifkan check-in (opsional) —
        // hanya info + tidak dialfa + tidak dihitung hari kerja (lihat auto-alfa & payroll).
        $liburIndividu = \App\Models\LiburTendik::isLibur($tp->id, $kerjaDate->toDateString());

        $sudahCheckin  = $absensi?->jam_masuk  !== null;
        $sudahCheckout = $absensi?->jam_pulang !== null;

        // ── Eligibility dihitung SERVER (otoritatif & overnight-aware) ──────
        // Flutter cukup memakai boleh_checkin/boleh_checkout ini (tanpa hitung ulang).
        $bolehCheckin = false; $menitMenungguCheckin = 0;
        $bolehCheckout = false; $menitMenungguCheckout = 0;

        if ($isDinasLuar) {
            $bolehCheckin  = !$sudahCheckin;
            $bolehCheckout = $sudahCheckin && !$sudahCheckout;
            $bisaCheckInMulai = null; $bisaCheckoutMulai = null;
        } elseif (!$isLibur && $izinAktif === null) {
            // Check-in: dari window buka s/d jam pulang (telat tetap boleh).
            if (!$sudahCheckin) {
                if (!$checkinOpenDt) {
                    $bolehCheckin = true; // tak ada setting → bebas
                } elseif ($now->gte($checkinOpenDt) && $jamPulangDt && $now->lte($jamPulangDt)) {
                    $bolehCheckin = true;
                } elseif ($now->lt($checkinOpenDt)) {
                    $menitMenungguCheckin = (int) ceil($now->diffInMinutes($checkinOpenDt, false));
                }
            }
            // Check-out: setelah check-in, mulai jam pulang.
            if ($sudahCheckin && !$sudahCheckout) {
                if (!$jamPulangDt || $now->gte($jamPulangDt)) {
                    $bolehCheckout = true;
                } else {
                    $menitMenungguCheckout = (int) ceil($now->diffInMinutes($jamPulangDt, false));
                }
            }
        }

        // Shift kemarin yang masih berjalan (tanggal kerja ≠ tanggal kalender).
        $overnightCarry = $kerjaDate->toDateString() !== $today->toDateString();

        return response()->json([
            'success' => true,
            'data'    => [
                // tanggal = TANGGAL KERJA efektif (bisa = kemarin saat shift malam berjalan)
                'tanggal'        => $kerjaDate->toDateString(),
                'hari'           => ucfirst($kerjaDate->locale('id')->isoFormat('dddd')),
                'jadwal_masuk'   => $isDinasLuar ? null : (isset($jadwal['jam_masuk'])  ? substr((string)$jadwal['jam_masuk'], 0, 5)  : null),
                'jadwal_pulang'  => $isDinasLuar ? null : (isset($jadwal['jam_pulang']) ? substr((string)$jadwal['jam_pulang'], 0, 5) : null),
                'toleransi'      => $jadwal['toleransi']  ?? 15,
                // Window check-in: 30 menit sebelum jadwal masuk (null = bebas)
                'bisa_checkin_mulai'  => $bisaCheckInMulai,
                'absensi'        => $absensi ? $this->formatAbsensi($absensi) : null,
                'sudah_checkin'       => $sudahCheckin,
                'sudah_checkout'      => $sudahCheckout,
                // ── Eligibility OTORITATIF dari server (overnight-aware) ──────
                'boleh_checkin'           => $bolehCheckin,
                'boleh_checkout'          => $bolehCheckout,
                'menit_menunggu_checkin'  => $menitMenungguCheckin,
                'menit_menunggu_checkout' => $menitMenungguCheckout,
                // true = shift malam dari kemarin masih berjalan (waktunya check out pagi ini)
                'overnight_carry'     => $overnightCarry,
                // Checkout: tepat jam pulang (null = bebas)
                'bisa_checkout_mulai' => $bisaCheckoutMulai,
                'jam_pulang_jadwal'   => $isDinasLuar ? null : $jamPulangJadwal,
                // INFO IZIN/CUTI AKTIF — untuk Flutter menampilkan banner
                'izin_aktif'     => $izinAktif ?? ['ada' => false],
                // INFO HARI LIBUR — untuk Flutter menampilkan banner & disable tombol
                // Prioritas: libur nasional/pesantren (HariLibur) → libur mingguan (jam kerja).
                'hari_libur'     => $hariLiburAktif ? [
                    'ada'     => true,
                    'nama'    => $hariLiburAktif->nama,
                    'sumber'  => $hariLiburAktif->sumber,
                    'opsional'=> false,
                ] : ($liburMingguan ? [
                    'ada'     => true,
                    'nama'    => 'Libur Mingguan',
                    'sumber'  => 'jam_kerja',
                    'opsional'=> false,
                ] : ($liburIndividu ? [
                    // Libur jadwal individu (mukim) → check-in tetap boleh (opsional).
                    'ada'     => true,
                    'nama'    => 'Libur (jadwal Anda)',
                    'sumber'  => 'libur_individu',
                    'opsional'=> true,
                ] : ['ada' => false])),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CHECK IN
    // ══════════════════════════════════════════════════════════════════════════

    public function checkIn(Request $request): JsonResponse
    {
        $request->validate([
            'latitude'   => 'nullable|numeric',
            'longitude'  => 'nullable|numeric',
            'wifi_ssid'  => 'nullable|string|max:100',
            'wifi_bssid' => 'nullable|string|max:100',
            'foto'       => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
            'device_date'=> 'nullable|date_format:Y-m-d',
        ]);

        $user = $request->user();
        $tp   = $user->tenagaPendidik;

        if (!$tp) {
            return response()->json([
                'success' => false,
                'message' => 'Data tenaga pendidik tidak ditemukan.',
            ], 404);
        }

        // Gunakan device_date dari Flutter agar konsisten dengan hariIni() — handle timezone WIB.
        // Lalu sesuaikan ke TANGGAL KERJA efektif (shift lintas-hari yang dimulai kemarin).
        $today    = TimezoneHelper::tanggalDariRequest($request->device_date);
        $jamKerja = $tp->jamKerjaAktif();
        $today    = $this->resolveTanggalKerja($tp, $jamKerja, $today, TimezoneHelper::now());

        // Cek sudah check in belum
        $existing = AbsensiHarian::where('tenaga_pendidik_id', $tp->id)
            ->whereDate('tanggal', $today)
            ->first();

        if ($existing && $existing->jam_masuk) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah check in hari ini pukul '
                    . Carbon::parse($existing->jam_masuk)->format('H:i') . '.',
                'data'    => $this->formatAbsensi($existing),
            ], 422);
        }

        $jamSekarang = TimezoneHelper::now();

        // ── Deteksi izin/cuti aktif ──────────────────────────────────────────
        // Priority: dinas_luar → bypass lokasi & window; izin lain → bypass lokasi saja
        $izinAktif   = $this->deteksiIzinAktif($tp->id, $today->toDateString());
        $isDinasLuar = $izinAktif && $izinAktif['status_absensi'] === 'dinas_luar';

        // ── Cek hari libur aktif (kecuali dinas luar) ───────────────────────
        if (!$isDinasLuar && \App\Models\HariLibur::isLibur($today->toDateString())) {
            return response()->json([
                'success' => false,
                'message' => 'Hari ini adalah hari libur. Check in tidak diperlukan.',
                'code'    => 'HARI_LIBUR',
            ], 422);
        }

        // ── Izin / Sakit / Cuti aktif (NON-dinas) → tidak perlu & tidak boleh check in ──
        // Absensi sudah otomatis tercatat saat pengajuan izin disetujui
        // (PengajuanIzinService::generateAbsensi men-set status izin/sakit).
        // Diblokir agar status izin tidak tertimpa menjadi hadir/terlambat.
        // Dinas luar DIKECUALIKAN — wajib tetap check in (di bawah).
        if ($izinAktif && !$isDinasLuar) {
            return response()->json([
                'success' => false,
                'message' => "Anda sedang {$izinAktif['jenis']}. Absensi hari ini sudah "
                    . "tercatat otomatis — tidak perlu check in.",
                'code'    => 'IZIN_AKTIF',
                'data'    => [
                    'jenis'          => $izinAktif['jenis'],
                    'status_absensi' => $izinAktif['status_absensi'],
                ],
            ], 422);
        }

        if ($isDinasLuar) {
            // ── Dinas Luar: bypass lokasi + window, status langsung dinas_luar ─
            $status         = 'dinas_luar';
            $menitTerlambat = 0;
            $validasi = [
                'valid'             => true,
                'tipe_validasi'     => 'valid_dinas_luar',
                'setting_lokasi_id' => null,
                'jarak_meter'       => null,
                'nama_wifi'         => null,
                'bssid_wifi'        => null,
                'pesan'             => "Izin dinas luar aktif: {$izinAktif['jenis']}.",
            ];
            \Log::info('[CHECKIN] Dinas luar aktif — bypass lokasi & window', [
                'tp_id' => $tp->id, 'izin' => $izinAktif['jenis'],
            ]);
        } else {
            // ── Normal flow: lokasi WAJIB untuk CHECK-IN ─────────────────────
            // Kebijakan: check-in harus di dalam area yang diizinkan. Selalu
            // validasi (walau koordinat kosong → ditolak) agar GPS yang ditolak
            // tidak bisa dipakai menembus geofence. Izin (cuti/sakit) & dinas
            // luar sudah dikembalikan valid oleh validasi() (valid_izin/dinas).
            // (Check-out tetap fleksibel — pakai alur alasan pulang di checkOut.)
            $validasi = $this->lokasiService->validasi($tp->id, [
                'latitude'   => $request->latitude,
                'longitude'  => $request->longitude,
                'wifi_ssid'  => $request->wifi_ssid,
                'wifi_bssid' => $request->wifi_bssid,
                'status'     => 'hadir',
                'tanggal'    => $today->toDateString(),
            ]);

            if (!$validasi['valid']) {
                $tanpaKoordinat = $request->latitude === null && $request->wifi_bssid === null;
                return response()->json([
                    'success' => false,
                    'message' => $tanpaKoordinat
                        ? 'Aktifkan izin lokasi (GPS) untuk check-in. Lokasi wajib saat masuk.'
                        : ($validasi['pesan'] ?? 'Lokasi tidak valid untuk check-in.'),
                    'code'    => 'LOKASI_WAJIB_CHECKIN',
                    'data'    => [
                        'tipe_validasi' => $validasi['tipe_validasi'],
                        'jarak_meter'   => $validasi['jarak_meter'],
                    ],
                ], 422);
            }

            // ── Hitung status hadir/terlambat ────────────────────────────────
            $hasil          = AbsensiKalkulasiService::hitungStatus(
                $jamSekarang->format('H:i:s'),
                $today->toDateString(),
                $tp
            );
            $status         = $hasil['status'];
            // Clamp ≥ 0 — cegah menit terlambat negatif (mis. check-in lebih awal).
            $menitTerlambat = max(0, (int) $hasil['menit_terlambat']);
            $jadwal         = $hasil['jadwal'];

            // ── Validasi window check-in ──────────────────────────────────────
            // Aturan:
            //   - Terlalu awal   : > 30 menit SEBELUM jam masuk → TOLAK
            //   - Tepat waktu    : dalam toleransi → hadir
            //   - Terlambat      : melewati toleransi → terlambat (tetap boleh)
            //   - Lewat jam kerja: setelah jam pulang → TOLAK
            if ($jadwal && isset($jadwal['jam_masuk'])) {
                $jamMasukJadwal   = Carbon::parse(
                    $today->toDateString() . ' ' . $jadwal['jam_masuk']
                )->setTimezone(TimezoneHelper::TZ);
                $batasAwalCheckin = $jamMasukJadwal->copy()->subMinutes(30);

                // Terlalu awal
                if ($jamSekarang->lt($batasAwalCheckin)) {
                    $menitLagi = (int) $jamSekarang->diffInMinutes($batasAwalCheckin);
                    return response()->json([
                        'success'            => false,
                        'message'            => 'Check in terlalu awal. Bisa check in mulai '
                            . $batasAwalCheckin->format('H:i')
                            . ' (' . $menitLagi . ' menit lagi).',
                        'code'               => 'TOO_EARLY_CHECKIN',
                        'bisa_checkin_mulai' => $batasAwalCheckin->format('H:i'),
                        'jam_masuk_jadwal'   => $jamMasukJadwal->format('H:i'),
                        'toleransi_menit'    => $jadwal['toleransi'] ?? 15,
                        'sisa_menit'         => $menitLagi,
                    ], 422);
                }

                // Sudah lewat jam pulang
                if (isset($jadwal['jam_pulang'])) {
                    $jamPulangJadwal = Carbon::parse(
                        $today->toDateString() . ' ' . $jadwal['jam_pulang']
                    )->setTimezone(TimezoneHelper::TZ);

                    if ($jamSekarang->gt($jamPulangJadwal)) {
                        return response()->json([
                            'success'           => false,
                            'message'           => 'Jam kerja telah berakhir pukul '
                                . $jamPulangJadwal->format('H:i')
                                . '. Check in tidak dapat dilakukan. Hubungi admin untuk koreksi.',
                            'code'              => 'TOO_LATE_CHECKIN',
                            'jam_pulang_jadwal' => $jamPulangJadwal->format('H:i'),
                        ], 422);
                    }
                }

                \Log::info('[CHECKIN] jam='.$jamSekarang->format('H:i')
                    .' jadwal='.$jamMasukJadwal->format('H:i')
                    .' batasAwal='.$batasAwalCheckin->format('H:i')
                    .' toleransi='.($jadwal['toleransi'] ?? 15).'mnt'
                    .' status='.$status);
            }
        }

        // Upload foto selfie jika ada
        $fotoPath = null;
        if ($request->hasFile('foto') && $request->file('foto')->isValid()) {
            $fotoPath = $request->file('foto')
                ->store("foto-absensi/{$tp->id}", 'public');
        }

        // Simpan ke absensi_harian
        $absensi = AbsensiHarian::updateOrCreate(
            [
                'tenaga_pendidik_id' => $tp->id,
                'tanggal'            => $today->toDateString(),
            ],
            [
                'jam_masuk'          => $jamSekarang->format('H:i:s'),
                'foto_masuk'         => $fotoPath,
                'lat_masuk'          => $request->latitude,
                'lng_masuk'          => $request->longitude,
                'status'             => $status,
                'menit_terlambat'    => $menitTerlambat,
                'validasi_lokasi'    => $validasi['tipe_validasi'] ?? 'tidak_diperiksa',
                'nama_wifi'          => $validasi['nama_wifi']         ?? null,
                'bssid_wifi'         => $validasi['bssid_wifi']        ?? null,
                'jarak_meter'        => $validasi['jarak_meter']       ?? null,
                'setting_lokasi_id'  => $validasi['setting_lokasi_id'] ?? null,
            ]
        );

        $labelTerlambat = AbsensiKalkulasiService::labelTerlambat($menitTerlambat);
        $pesan = $status === 'terlambat'
            ? "Check in berhasil. Terlambat {$labelTerlambat}."
            : 'Check in berhasil. Tepat waktu! Jam masuk: '.$jamSekarang->format('H:i').'.';

        return response()->json([
            'success' => true,
            'message' => $pesan,
            'data'    => $this->formatAbsensi($absensi->fresh()),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CHECK OUT
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Resolusi record absensi yang relevan untuk check-out / status:
     *  - record HARI INI (apa pun shift-nya), ATAU
     *  - bila tak ada record hari ini → record KEMARIN yang masih terbuka (sudah masuk,
     *    belum pulang) DAN shift kemarin bersifat lintas hari (overnight) → dipakai untuk
     *    check-out pagi berikutnya.
     *
     * @return array{0: ?AbsensiHarian, 1: Carbon, 2: bool}  [record, tanggalShift, overnightCarry]
     */
    private function resolveAbsensiAktif($tp, Carbon $today, ?SettingJamKerja $jamKerja): array
    {
        $today = $today->copy()->startOfDay();

        $rec = AbsensiHarian::where('tenaga_pendidik_id', $tp->id)
            ->whereDate('tanggal', $today)->first();
        if ($rec) return [$rec, $today, false];

        // Tidak ada record hari ini → cek shift lintas-hari kemarin yang belum check out.
        $yesterday = $today->copy()->subDay();
        $yRec = AbsensiHarian::where('tenaga_pendidik_id', $tp->id)
            ->whereDate('tanggal', $yesterday)
            ->whereNotNull('jam_masuk')->whereNull('jam_pulang')->first();

        if ($yRec && $jamKerja) {
            $yJadwal = $jamKerja->getJamUntukHari(TimezoneHelper::namaHariDB($yesterday));
            if ($yJadwal && ($yJadwal['lintas_hari'] ?? false)) {
                return [$yRec, $yesterday, true]; // shift malam masih berjalan
            }
        }

        return [null, $today, false];
    }

    /**
     * Tanggal kerja efektif (mendukung shift lintas hari).
     * Jika BELUM ada record hari ini, shift KEMARIN bersifat lintas-hari & masih
     * berjalan (now di antara [jam_masuk−30m kemarin, jam_pulang+1hari]), DAN shift
     * hari ini belum buka window → tanggal kerja = KEMARIN (mis. dini hari 01:17 saat
     * shift 15:15→07:00 yang dimulai kemarin masih berlangsung).
     */
    private function resolveTanggalKerja($tp, ?SettingJamKerja $jamKerja, Carbon $today, Carbon $now): Carbon
    {
        // Delegasi ke sumber tunggal (dipakai bersama Beranda) agar tak drift.
        return \App\Services\AbsensiWindowService::resolveTanggalKerja($tp, $jamKerja, $today, $now);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $request->validate([
            'latitude'      => 'nullable|numeric',
            'longitude'     => 'nullable|numeric',
            'wifi_ssid'     => 'nullable|string|max:100',
            'wifi_bssid'    => 'nullable|string|max:100',
            'foto'          => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
            'device_date'   => 'nullable|date_format:Y-m-d',
            'alasan_pulang' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $tp   = $user->tenagaPendidik;

        if (!$tp) {
            return response()->json([
                'success' => false,
                'message' => 'Data tenaga pendidik tidak ditemukan.',
            ], 404);
        }

        $today    = TimezoneHelper::tanggalDariRequest($request->device_date);
        $jamKerja = $tp->jamKerjaAktif();

        // ── Izin/Sakit/Cuti aktif (NON-dinas) → tidak perlu check out ──────────
        // Absensi sudah final (status izin). Dinas luar dikecualikan (wajib check out).
        $izinCO = $this->deteksiIzinAktif($tp->id, $today->toDateString());
        if ($izinCO && $izinCO['status_absensi'] !== 'dinas_luar') {
            return response()->json([
                'success' => false,
                'message' => "Anda sedang {$izinCO['jenis']}. Absensi sudah tercatat otomatis "
                    . "— tidak perlu check out.",
                'code'    => 'IZIN_AKTIF',
            ], 422);
        }

        // Record hari ini, ATAU shift lintas-hari kemarin yang masih terbuka (overnight).
        // $shiftDate = tanggal mulai shift (dipakai untuk validasi jam pulang & durasi).
        [$absensi, $shiftDate, $overnightCarry] = $this->resolveAbsensiAktif($tp, $today, $jamKerja);

        // Wajib sudah check in
        if (!$absensi || !$absensi->jam_masuk) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum melakukan check in hari ini.',
                'code'    => 'NOT_CHECKED_IN',
            ], 422);
        }

        // Cegah double check out
        if ($absensi->jam_pulang) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah check out pukul '
                    . Carbon::parse($absensi->jam_pulang)->format('H:i') . '.',
                'code'    => 'ALREADY_CHECKED_OUT',
                'data'    => $this->formatAbsensi($absensi),
            ], 422);
        }

        // ── Validasi waktu checkout (berbasis tanggal shift, bukan tanggal kalender) ──
        $jamSekarang = TimezoneHelper::now();

        // Resolusi jam pulang — multi-layer robust fallback
        $jamPulangStr = null;
        $isLintas     = false;
        $toleransiMenit = 0; // toleransi menit setelah jam pulang (boleh checkout)

        if ($jamKerja) {
            // Layer 1: jadwal per hari berdasarkan tanggal MULAI shift
            $namaHari = TimezoneHelper::namaHariDB($shiftDate);
            $jadwal   = $jamKerja->getJamUntukHari($namaHari);

            if ($jadwal && isset($jadwal['jam_pulang'])) {
                $jamPulangStr   = $jadwal['jam_pulang'];
                $isLintas       = $jadwal['lintas_hari'] ?? false;
                $toleransiMenit = $jadwal['toleransi']   ?? 15;
            }

            // Layer 2: jam global dari model (jika per-hari kosong)
            if (!$jamPulangStr && $jamKerja->jam_pulang) {
                $jamPulangStr   = $jamKerja->jam_pulang;
                $toleransiMenit = $jamKerja->toleransi_terlambat ?? 15;
            }

            // Layer 3: dari AbsensiKalkulasiService sebagai terakhir
            if (!$jamPulangStr) {
                $hasilKalkulasi = AbsensiKalkulasiService::hitungStatus(
                    $jamSekarang->format('H:i:s'),
                    $shiftDate->toDateString(),
                    $tp
                );
                if (isset($hasilKalkulasi['jadwal']['jam_pulang'])) {
                    $jamPulangStr = $hasilKalkulasi['jadwal']['jam_pulang'];
                }
            }
        }

        \Log::info('[CHECKOUT] namaHari='.TimezoneHelper::namaHariDB($today)
            .' jamPulangStr='.$jamPulangStr
            .' jam='.$jamSekarang->format('H:i')
            .' gunakan_per_hari='.($jamKerja?->gunakan_jadwal_per_hari ? 'true':'false'));

        // Validasi: checkout hanya boleh saat atau setelah jam pulang
        if ($jamPulangStr) {
            $jamPulangJadwal = Carbon::parse(
                $shiftDate->toDateString() . ' ' . $jamPulangStr
            )->setTimezone(TimezoneHelper::TZ);

            if ($isLintas) $jamPulangJadwal->addDay();

            // Checkout boleh dilakukan tepat pada jam pulang (tidak ada toleransi sebelum jam pulang)
            if ($jamSekarang->lt($jamPulangJadwal)) {
                $selisihMenit = (int) $jamSekarang->diffInMinutes($jamPulangJadwal);
                $j = (int) floor($selisihMenit / 60);
                $m = $selisihMenit % 60;
                $pesanWaktu = $j > 0 ? "{$j} jam {$m} menit" : "{$selisihMenit} menit";

                return response()->json([
                    'success'             => false,
                    'message'             => 'Belum saatnya check out. Jam pulang '
                        . $jamPulangJadwal->format('H:i')
                        . '. Sisa ' . $pesanWaktu . ' lagi.',
                    'code'                => 'TOO_EARLY',
                    'jam_pulang_jadwal'   => $jamPulangJadwal->format('H:i'),
                    'bisa_checkout_mulai' => $jamPulangJadwal->format('H:i'),
                    'sisa_menit'          => $selisihMenit,
                ], 422);
            }
        } else {
            // Tidak ada setting jam kerja — log dan izinkan checkout
            \Log::warning('[CHECKOUT] Tidak ada setting jam kerja aktif, checkout diizinkan.');
        }

        // ── Validasi lokasi CHECK-OUT (longgar, dengan penanda + alasan wajib) ──
        // Antisipasi lupa/di-luar-lokasi saat pulang. Check-IN tetap ketat; check-out
        // boleh di luar lokasi TAPI wajib alasan & ditandai agar auditable.
        $isDinasLuarCheckout = $izinCO && $izinCO['status_absensi'] === 'dinas_luar';
        $pulangLuarLokasi = false;
        $jarakPulang      = null;
        $alasanPulang     = null;

        $adaLokasiData = $request->latitude !== null || $request->longitude !== null
            || $request->wifi_ssid !== null || $request->wifi_bssid !== null;

        if (!$isDinasLuarCheckout && $adaLokasiData) {
            $valPulang = $this->lokasiService->validasi($tp->id, [
                'latitude'   => $request->latitude,
                'longitude'  => $request->longitude,
                'wifi_ssid'  => $request->wifi_ssid,
                'wifi_bssid' => $request->wifi_bssid,
                'status'     => 'hadir',
                'tanggal'    => $shiftDate->toDateString(),
            ]);

            if (!($valPulang['valid'] ?? false)) {
                $izinkanLuar = \App\Models\SettingLokasiAbsensi::aktif()
                    ->where('izinkan_checkout_luar_lokasi', true)->exists();

                if (!$izinkanLuar) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Check out harus dilakukan di lokasi. ' . ($valPulang['pesan'] ?? ''),
                        'code'    => 'HARUS_DI_LOKASI',
                        'data'    => ['jarak_meter' => $valPulang['jarak_meter'] ?? null],
                    ], 422);
                }

                $alasanPulang = trim((string) $request->alasan_pulang);
                if ($alasanPulang === '') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda di luar lokasi pesantren. Wajib mengisi alasan untuk check out.',
                        'code'    => 'BUTUH_ALASAN_PULANG',
                        'data'    => ['jarak_meter' => $valPulang['jarak_meter'] ?? null],
                    ], 422);
                }

                $pulangLuarLokasi = true;
                $jarakPulang      = $valPulang['jarak_meter'] ?? null;
            }
        }

        // Upload foto pulang
        $fotoPulangPath = null;
        if ($request->hasFile('foto') && $request->file('foto')->isValid()) {
            $fotoPulangPath = $request->file('foto')
                ->store("foto-absensi/{$tp->id}", 'public');
        }

        $jamPulang = TimezoneHelper::now(); // WIB, bukan UTC
        $absensi->update([
            'jam_pulang'         => $jamPulang->format('H:i:s'),
            'foto_pulang'        => $fotoPulangPath,
            'lat_pulang'         => $request->latitude,
            'lng_pulang'         => $request->longitude,
            'pulang_luar_lokasi' => $pulangLuarLokasi,
            'alasan_pulang'      => $pulangLuarLokasi ? $alasanPulang : null,
            'jarak_pulang_meter' => $jarakPulang,
        ]);

        // Hitung durasi kerja (dari tanggal & jam masuk shift → jam pulang aktual)
        $masuk      = Carbon::parse($shiftDate->toDateString() . ' ' . $absensi->jam_masuk);
        $totalMenit = (int) $masuk->diffInMinutes($jamPulang);
        $jam        = floor($totalMenit / 60);
        $menit      = $totalMenit % 60;

        return response()->json([
            'success' => true,
            'message' => "Check out berhasil. Durasi kerja: {$jam} jam {$menit} menit.",
            'data'    => $this->formatAbsensi($absensi->fresh()),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // RIWAYAT & REKAP
    // ══════════════════════════════════════════════════════════════════════════

    public function riwayat(Request $request): JsonResponse
    {
        $user = $request->user();
        $tp   = $user->tenagaPendidik;

        if (!$tp) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan.'], 404);
        }

        $bulan = (int) ($request->bulan ?? Carbon::now()->month);
        $tahun = (int) ($request->tahun ?? Carbon::now()->year);
        $limit = min((int) ($request->limit ?? 31), 60);

        $list = AbsensiHarian::where('tenaga_pendidik_id', $tp->id)
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun)
            ->orderByDesc('tanggal')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $list->map(fn($a) => $this->formatAbsensi($a))->values(),
        ]);
    }

    public function rekap(Request $request, int $bulan, int $tahun): JsonResponse
    {
        $user = $request->user();
        $tp   = $user->tenagaPendidik;

        if (!$tp) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan.'], 404);
        }

        $mulai   = Carbon::create($tahun, $bulan, 1)->startOfMonth()->setTimezone(TimezoneHelper::TZ);
        $selesai = $mulai->copy()->endOfMonth();

        $list = AbsensiHarian::where('tenaga_pendidik_id', $tp->id)
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun)
            ->orderBy('tanggal')
            ->get();

        // Hitung hari kerja efektif (kurangi hari libur nasional/pesantren)
        $hariLiburCount = \App\Models\HariLibur::aktif()
            ->where('tanggal', '<=', $selesai)
            ->where(fn($q) => $q->whereNull('tanggal_selesai')
                ->orWhere('tanggal_selesai', '>=', $mulai))
            ->count();
        // Estimasi hari kerja: total hari - weekend - hari libur
        $totalHariKerja = max(0, $mulai->diffInWeekdays($selesai) + 1 - $hariLiburCount);

        $hadir          = $list->whereIn('status', ['hadir', 'terlambat', 'dinas_luar'])->count();
        $terlambat      = $list->where('status', 'terlambat')->count();
        $izin           = $list->whereIn('status', ['izin', 'izin_sakit'])->count();
        $sakit          = $list->where('status', 'sakit')->count();
        $alfa           = $list->where('status', 'alfa')->count();
        $dinasLuar      = $list->where('status', 'dinas_luar')->count();
        $totalMenitTerlambat = (int) $list->sum('menit_terlambat');

        // Persen kehadiran
        $persenHadir = $totalHariKerja > 0
            ? round($hadir / $totalHariKerja * 100, 1)
            : 0;

        // Total keterlambatan
        $jamTerlambat   = (int) floor($totalMenitTerlambat / 60);
        $menitSisa      = $totalMenitTerlambat % 60;
        $labelTerlambat = $jamTerlambat > 0
            ? "{$jamTerlambat} jam {$menitSisa} menit"
            : "{$totalMenitTerlambat} menit";

        // Dampak pada kinerja (estimasi skor absensi)
        // Basis: hadir=100, terlambat=75, izin=60, sakit=70, alfa=0
        $nilaiTotal = ($hadir    * 100)
                    + ($terlambat * 75)
                    + ($izin      * 60)
                    + ($sakit     * 70)
                    + ($dinasLuar * 100)
                    + ($alfa      * 0);
        $skorAbsensi = $totalHariKerja > 0
            ? min(100, round($nilaiTotal / ($totalHariKerja * 100) * 100, 1))
            : 100;

        return response()->json([
            'success' => true,
            'data'    => [
                'bulan'                  => $bulan,
                'tahun'                  => $tahun,
                'total_hari_kerja'       => $totalHariKerja,
                'total_hadir'            => $hadir,
                'total_terlambat'        => $terlambat,
                'total_izin'             => $izin,
                'total_sakit'            => $sakit,
                'total_alfa'             => $alfa,
                'total_dinas_luar'       => $dinasLuar,
                'total_menit_terlambat'  => $totalMenitTerlambat,
                'label_terlambat'        => $labelTerlambat,
                'persen_hadir'           => $persenHadir,
                'skor_absensi'           => $skorAbsensi,
                // Detail per hari (untuk chart riwayat)
                'detail_harian'          => $list->map(fn($a) => [
                    'tanggal'          => $a->tanggal?->toDateString(),
                    'status'           => $a->status,
                    'jam_masuk'        => $a->jam_masuk,
                    'jam_pulang'       => $a->jam_pulang,
                    'menit_terlambat'  => $a->menit_terlambat ?? 0,
                ])->values(),
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ABSENSI MENGAJAR — stub (next phase)
    // ══════════════════════════════════════════════════════════════════════════

    // ══════════════════════════════════════════════════════════════════════════
    // ABSENSI MENGAJAR
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /absensi/mengajar/hari-ini
     * Jadwal mengajar hari ini + status absen masing-masing.
     */
    public function jadwalMengajarHariIni(Request $request): JsonResponse
    {
        $user = $request->user();
        $tp   = $user->tenagaPendidik;

        if (!$tp) {
            return response()->json(['success' => false, 'message' => 'Data tenaga pendidik tidak ditemukan.'], 404);
        }

        $today    = TimezoneHelper::tanggalDariRequest($request->device_date);
        $namaHari = TimezoneHelper::namaHariDB($today);
        $sekarang = TimezoneHelper::now();

        // ── 1. Cek konteks hari ini ───────────────────────────────────────────
        // A. Hari libur aktif?
        $hariLibur = \App\Models\HariLibur::where('is_aktif', true)
            ->whereNull('dibatalkan_pada')
            ->where('tanggal', '<=', $today->toDateString())
            ->where(fn($q) =>
                $q->whereNull('tanggal_selesai')
                  ->orWhere('tanggal_selesai', '>=', $today->toDateString())
            )
            ->first();
        $isHariLibur = $hariLibur !== null;

        // B. Guru punya pengajuan izin yang disetujui hari ini?
        $izinAktif = \App\Models\PengajuanIzin::where('tenaga_pendidik_id', $tp->id)
            ->where('status', 'disetujui')
            ->where('tanggal_mulai', '<=', $today->toDateString())
            ->where('tanggal_selesai', '>=', $today->toDateString())
            ->with('jenisPengajuan')
            ->first();
        $isIzinGuru = $izinAktif !== null;

        // ── 2. Ambil jadwal hari ini ─────────────────────────────────────────
        $jadwalList = \App\Models\JadwalMengajar::with(['mataPelajaran', 'tahunAjaran'])
            ->where('tenaga_pendidik_id', $tp->id)
            ->where('hari', $namaHari)
            ->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn($q) => $q->where('is_aktif', true))
            // Tahfidz & Tahsin punya alur sendiri (halaman terpisah) — kecualikan di sini.
            ->whereHas('mataPelajaran', fn($q) => $q->where('tipe', 'reguler')->orWhereNull('tipe'))
            ->orderBy('jam_mulai')
            ->get();

        // ── 3. Auto-mark otomatis per jadwal yang sudah melewati jam selesai ─
        // Prioritas:
        //   a) Hari libur  → 'libur', jp_full (gaji tetap)
        //   b) Izin guru   → 'izin', jp_full (gaji tetap, asumsikan konfirmasi tugas)
        //   c) Tanpa alasan → 'tidak_terlaksana', jp=0 (tidak ada gaji JP)
        foreach ($jadwalList as $jdwl) {
            $jamSelesaiC = Carbon::parse(
                $today->toDateString().' '.$jdwl->jam_selesai, TimezoneHelper::TZ
            );

            if ($isHariLibur) {
                // Hari libur: SEMUA jadwal langsung di-mark 'libur' (tidak perlu tunggu jam selesai)
                \App\Models\AbsensiMengajar::firstOrCreate(
                    [
                        'jadwal_mengajar_id' => $jdwl->id,
                        'tenaga_pendidik_id' => $tp->id,
                        'tanggal'            => $today->toDateString(),
                    ],
                    [
                        'jp_terlaksana'  => $jdwl->jumlah_jp, // JP tetap diberikan saat libur
                        'status'         => 'libur',
                        'sudah_buka_jurnal' => false,
                        'keterangan'     => 'Libur '.ucfirst($hariLibur->sumber ?: $hariLibur->tipe ?: 'pesantren').': '.($hariLibur->nama ?? 'Libur'),
                    ]
                );
            } elseif ($sekarang->gt($jamSelesaiC)) {
                // Jam mengajar sudah selesai — auto-mark berdasarkan kondisi guru
                if ($isIzinGuru) {
                    // Guru izin resmi + jam sudah selesai → auto-mark izin, jp_full
                    // (guru seharusnya mengkonfirmasi tugas sebelum jam selesai,
                    //  tapi jika belum, tetap jp_full karena izin sudah disetujui admin)
                    // Kebijakan: izin TANPA pengganti → JP hangus (jp=0, tidak dibayar).
                    // Guru semestinya menunjuk pengganti agar JP mengalir ke pengganti.
                    \App\Models\AbsensiMengajar::firstOrCreate(
                        [
                            'jadwal_mengajar_id' => $jdwl->id,
                            'tenaga_pendidik_id' => $tp->id,
                            'tanggal'            => $today->toDateString(),
                        ],
                        [
                            'jp_terlaksana'  => 0, // izin tanpa pengganti → JP hangus
                            'status'         => 'izin',
                            'sudah_buka_jurnal' => false,
                            'keterangan'     => 'Otomatis: guru izin ('
                                .($izinAktif->jenisPengajuan?->nama ?? 'Izin')
                                .') tanpa pengganti — JP tidak dibayar.',
                        ]
                    );
                } else {
                    // Tidak ada alasan resmi → tidak terlaksana, jp=0
                    \App\Models\AbsensiMengajar::firstOrCreate(
                        [
                            'jadwal_mengajar_id' => $jdwl->id,
                            'tenaga_pendidik_id' => $tp->id,
                            'tanggal'            => $today->toDateString(),
                        ],
                        [
                            'jp_terlaksana'  => 0,
                            'status'         => 'tidak_terlaksana',
                            'sudah_buka_jurnal' => false,
                            'keterangan'     => 'Otomatis: tidak hadir tanpa keterangan.',
                        ]
                    );
                }
            }
        }

        // ── 4. Load absensi setelah auto-mark ────────────────────────────────
        $absensiAda = \App\Models\AbsensiMengajar::whereDate('tanggal', $today)
            ->where('tenaga_pendidik_id', $tp->id)
            ->with('digantikanOleh.user:id,name')
            ->get()
            ->keyBy('jadwal_mengajar_id');

        $sudahAbsenIds = $absensiAda->keys()->toArray();

        // ── 5. Map jadwal → response ──────────────────────────────────────────
        $data = $jadwalList->map(function ($jadwal)
            use ($absensiAda, $today, $sekarang, $jadwalList, $sudahAbsenIds,
                 $isHariLibur, $isIzinGuru, $hariLibur, $izinAktif)
        {
            $absensi  = $absensiAda->get($jadwal->id);
            $durMenit = $jadwal->jumlah_jp * 45;

            $jamMulai   = Carbon::parse($today->toDateString().' '.$jadwal->jam_mulai, TimezoneHelper::TZ);
            $jamSelesai = Carbon::parse($today->toDateString().' '.$jadwal->jam_selesai, TimezoneHelper::TZ);

            $dalamWindow = $sekarang->between($jamMulai->copy()->subMinutes(15), $jamSelesai);

            $jadwalLebihAwal = $jadwalList->filter(fn($j) => $j->jam_mulai < $jadwal->jam_mulai);
            $semuaLebihAwalSudahAbsen = $jadwalLebihAwal->every(
                fn($j) => in_array($j->id, $sudahAbsenIds)
            );

            // boleh_absen: hanya untuk kondisi normal (bukan libur, bukan izin)
            $bolehAbsen = !$isHariLibur && !$isIzinGuru
                && $dalamWindow && $semuaLebihAwalSudahAbsen && $absensi === null;

            // boleh_konfirmasi_izin: saat guru punya izin resmi & jadwal belum dikonfirmasi
            // (berlaku selama jam mengajar belum selesai, atau sudah selesai tapi record masih izin-auto)
            $bolehKonfirmasiIzin = $isIzinGuru && $absensi === null
                && $semuaLebihAwalSudahAbsen && !$isHariLibur;

            // Pesan blokir untuk kondisi normal
            $pesanBlokir = null;
            if (!$isHariLibur && !$isIzinGuru && $absensi === null && !$bolehAbsen) {
                if (!$semuaLebihAwalSudahAbsen) {
                    $belum = $jadwalLebihAwal->filter(fn($j) => !in_array($j->id, $sudahAbsenIds))->first();
                    $pesanBlokir = 'Selesaikan absensi '.($belum?->mataPelajaran?->nama ?? 'jadwal sebelumnya')
                        .' ('.$belum?->jam_mulai.') terlebih dahulu.';
                } elseif (!$dalamWindow) {
                    $pesanBlokir = $sekarang->lt($jamMulai->copy()->subMinutes(15))
                        ? 'Belum waktunya. Absen mulai pukul '.$jamMulai->copy()->subMinutes(15)->format('H:i').'.'
                        : 'Waktu mengajar sudah selesai.';
                }
            }

            return [
                'jadwal_id'      => $jadwal->id,
                'mata_pelajaran' => $jadwal->mataPelajaran?->nama ?? '—',
                'tipe'           => $jadwal->mataPelajaran?->tipe ?? 'reguler', // reguler|tahfidz|tahsin
                'kelas'          => $jadwal->kelas,
                'ruangan'        => $jadwal->ruangan ?? '—',
                'jam_mulai'      => $jadwal->jam_mulai,
                'jam_selesai'    => $jadwal->jam_selesai,
                'jumlah_jp'      => $jadwal->jumlah_jp,
                'durasi_menit'   => $durMenit,

                // ── Flags konteks hari ini ───────────────────────────────────
                'is_hari_libur'  => $isHariLibur,
                'nama_libur'     => $isHariLibur ? ($hariLibur->nama ?? 'Hari Libur') : null,
                'is_izin_guru'   => $isIzinGuru,
                'info_izin'      => $isIzinGuru ? ($izinAktif->jenisPengajuan?->nama ?? 'Izin') : null,

                // ── Aksi yang diizinkan ──────────────────────────────────────
                'boleh_absen'          => $bolehAbsen,
                'boleh_konfirmasi_izin'=> $bolehKonfirmasiIzin,
                'pesan_blokir'         => $pesanBlokir,

                // ── Status absensi ────────────────────────────────────────────
                'sudah_absen'       => $absensi !== null,
                'absensi_id'        => $absensi?->id,
                'status'            => $absensi?->status,
                'jp_terlaksana'     => $absensi?->jp_terlaksana,
                'jam_mulai_aktual'  => $absensi?->jam_mulai_aktual
                    ? Carbon::parse($absensi->jam_mulai_aktual)->format('H:i') : null,
                'materi'            => $absensi?->materi,
                'keterangan'        => $absensi?->keterangan,
                'foto_url'          => $absensi?->foto_mengajar
                    ? asset('storage/'.$absensi->foto_mengajar) : null,
                'sudah_buka_jurnal' => $absensi?->sudah_buka_jurnal ?? false,

                // ── Info pengganti (saat guru asli menunjuk) ─────────────────
                'digantikan_oleh' => $absensi?->digantikan_oleh,
                'pengganti_nama'  => $absensi?->digantikanOleh?->user?->name,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'tanggal'       => $today->toDateString(),
                'hari'          => $today->locale('id')->isoFormat('dddd'),
                'is_hari_libur' => $isHariLibur,
                'nama_libur'    => $hariLibur?->nama,
                'is_izin_guru'  => $isIzinGuru,
                'info_izin'     => $isIzinGuru
                    ? ($izinAktif->jenisPengajuan?->nama ?? 'Izin') : null,
                'jadwal'        => $data->values(),
                'total'         => $data->count(),
                'sudah_absen'   => $data->where('sudah_absen', true)->count(),
            ],
        ]);
    }

    /**
     * POST /absensi/mengajar/konfirmasi-izin
     * Guru yang sedang izin/cuti mengkonfirmasi tugas pengganti.
     *
     * Syarat:
     *  - Guru punya pengajuan izin disetujui hari ini
     *  - Jadwal belum dikonfirmasi (sudah_absen = false)
     *  - Tidak perlu foto & jurnal — hanya keterangan tugas yang diberikan
     *  - JP tetap diberikan penuh (guru izin resmi)
     */
    public function konfirmasiIzin(Request $request): JsonResponse
    {
        $request->validate([
            'jadwal_mengajar_id' => 'required|exists:jadwal_mengajar,id',
            'keterangan'         => 'required|string|min:5|max:500',
        ]);

        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);

        $today  = TimezoneHelper::today();
        $jadwal = \App\Models\JadwalMengajar::with('mataPelajaran')->findOrFail($request->jadwal_mengajar_id);

        if ($jadwal->tenaga_pendidik_id !== $tp->id) {
            return response()->json(['success' => false, 'message' => 'Jadwal ini bukan milik Anda.'], 403);
        }

        // Validasi hari
        $namaHari = TimezoneHelper::namaHariDB($today);
        if (strtolower($jadwal->hari) !== $namaHari) {
            return response()->json([
                'success' => false, 'message' => 'Jadwal ini tidak berlangsung hari ini.',
                'code' => 'WRONG_DAY',
            ], 422);
        }

        // Validasi harus punya izin yang disetujui hari ini
        $izinAktif = \App\Models\PengajuanIzin::where('tenaga_pendidik_id', $tp->id)
            ->where('status', 'disetujui')
            ->where('tanggal_mulai', '<=', $today->toDateString())
            ->where('tanggal_selesai', '>=', $today->toDateString())
            ->with('jenisPengajuan')
            ->first();

        if (!$izinAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki pengajuan izin yang disetujui untuk hari ini.',
                'code'    => 'TIDAK_ADA_IZIN',
            ], 422);
        }

        // Cek sudah dikonfirmasi sebelumnya
        $existing = \App\Models\AbsensiMengajar::where('jadwal_mengajar_id', $jadwal->id)
            ->whereDate('tanggal', $today)
            ->first();

        if ($existing && $existing->status !== 'izin') {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal ini sudah tercatat dengan status "'.$existing->status.'". Tidak perlu konfirmasi izin.',
                'code'    => 'ALREADY_RECORDED',
            ], 422);
        }

        // Buat/update tanda izin TANPA pengganti → JP hangus (kebijakan baru).
        $absensi = \App\Models\AbsensiMengajar::updateOrCreate(
            [
                'jadwal_mengajar_id' => $jadwal->id,
                'tenaga_pendidik_id' => $tp->id,
                'tanggal'            => $today->toDateString(),
            ],
            [
                'jp_terlaksana'     => 0, // izin tanpa pengganti → JP tidak dibayar
                'status'            => 'izin',
                'materi'            => null,
                'keterangan'        => 'Izin tanpa pengganti ('
                    .($izinAktif->jenisPengajuan?->nama ?? 'Izin').') — '
                    .$request->keterangan,
                'sudah_buka_jurnal' => false,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Ditandai izin. JP tidak dibayar. Tunjuk pengganti bila ingin JP dialihkan ke guru lain.',
            'data'    => [
                'absensi_id'    => $absensi->id,
                'status'        => $absensi->status,
                'jp_terlaksana' => $absensi->jp_terlaksana,
                'keterangan'    => $absensi->keterangan,
            ],
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // GURU PENGGANTI (saat izin) — tunjuk, lihat tugas, absen pengganti
    // ════════════════════════════════════════════════════════════════════════

    /** GET /absensi/mengajar/pengganti-opsi — daftar guru calon pengganti. */
    public function penggantiOpsi(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);

        $guru = \App\Models\TenagaPendidik::aktif()->where('id', '!=', $tp->id)
            ->with('user:id,name')->get()
            ->map(fn($g) => ['id' => $g->id, 'nama' => $g->user?->name ?? '—'])->values();
        return response()->json(['success' => true, 'data' => $guru]);
    }

    /** POST /absensi/mengajar/tunjuk-pengganti — guru izin menunjuk pengganti. */
    public function tunjukPengganti(Request $request): JsonResponse
    {
        $request->validate([
            'jadwal_mengajar_id' => 'required|exists:jadwal_mengajar,id',
            'pengganti_id'       => 'required|exists:tenaga_pendidik,id',
            'tanggal'            => 'nullable|date',
            'keterangan'         => 'nullable|string|max:500',
        ]);
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);

        try {
            $tgl = $request->filled('tanggal') ? Carbon::parse($request->tanggal) : null;
            $absensi = (new \App\Services\PenggantiMengajarService())->tunjukPengganti(
                (int) $request->jadwal_mengajar_id, $tp->id, (int) $request->pengganti_id, $tgl, $request->keterangan
            );
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => 'PENGGANTI'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengganti ditunjuk. JP akan dibayarkan ke pengganti setelah ia absen mengajar.',
            'data'    => ['absensi_id' => $absensi->id, 'status' => $absensi->status],
        ]);
    }

    /** GET /absensi/mengajar/pengganti-saya — kelas pengganti untuk guru ini hari ini. */
    public function penggantiSaya(Request $request): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);

        $today = TimezoneHelper::tanggalDariRequest($request->device_date);
        $list = (new \App\Services\PenggantiMengajarService())->penggantiSaya($tp->id, $today)
            ->map(fn($a) => [
                'absensi_id'     => $a->id,
                'jadwal_id'      => $a->jadwal_mengajar_id,
                'mata_pelajaran' => $a->jadwalMengajar?->mataPelajaran?->nama ?? '—',
                'kelas'          => $a->jadwalMengajar?->kelas ?? '—',
                'jam_mulai'      => $a->jadwalMengajar?->jam_mulai,
                'jam_selesai'    => $a->jadwalMengajar?->jam_selesai,
                'jumlah_jp'      => $a->jadwalMengajar?->jumlah_jp ?? 0,
                'guru_asli'      => $a->tenagaPendidik?->user?->name ?? '—',
                'keterangan'     => $a->keterangan,
                'tanggal'        => $a->tanggal?->toDateString(),
                'is_hari_ini'    => $a->tanggal?->toDateString() === $today->toDateString(),
                // "sudah diajar" = sudah absen (jam_selesai_aktual terisi), bukan jp>0
                // (kasus telat: jp=0 tapi sesi sudah diabsen).
                'sudah_diajar'   => !is_null($a->jam_selesai_aktual),
            ])->values();

        return response()->json(['success' => true, 'data' => [
            'tanggal' => $today->toDateString(),
            'kelas'   => $list,
            'total'   => $list->count(),
        ]]);
    }

    /** POST /absensi/mengajar/absen-pengganti — pengganti absen + bukti (JP penuh). */
    public function absenPengganti(Request $request): JsonResponse
    {
        // Multipart mengirim boolean sebagai string ("true"/"false") yang ditolak aturan
        // `boolean`. Normalisasi dulu ke boolean asli agar validasi lolos apa pun encoding klien.
        if ($request->has('sudah_buka_jurnal')) {
            $request->merge(['sudah_buka_jurnal' => $request->boolean('sudah_buka_jurnal')]);
        }

        $request->validate([
            'absensi_mengajar_id' => 'required|exists:absensi_mengajar,id',
            'foto'                => 'required|image|mimes:jpeg,jpg,png|max:3072',
            'materi'              => 'nullable|string|max:500',
            'keterangan'          => 'nullable|string|max:300',
            'sudah_buka_jurnal'   => 'required|boolean',
            'absensi_json'        => 'nullable|string', // [{santri_id,status}] (multipart-safe)
        ]);
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);

        // Decode kehadiran santri (opsional) — disaring agar hanya status valid.
        $santri = [];
        if ($request->filled('absensi_json')) {
            $decoded = json_decode($request->absensi_json, true);
            if (is_array($decoded)) {
                foreach ($decoded as $row) {
                    if (!isset($row['santri_id'], $row['status'])) continue;
                    if (!in_array($row['status'], ['hadir', 'telat', 'alpha'], true)) continue;
                    $santri[] = ['santri_id' => (int) $row['santri_id'], 'status' => $row['status']];
                }
            }
        }

        $fotoPath = $request->file('foto')->store('absensi-mengajar', 'public');

        try {
            $absensi = (new \App\Services\PenggantiMengajarService())->absenPengganti(
                (int) $request->absensi_mengajar_id, $tp->id, $fotoPath,
                $request->materi, $request->keterangan, $request->boolean('sudah_buka_jurnal'),
                $santri, $request->user()->name
            );
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => 'PENGGANTI'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Absen pengganti tersimpan. JP ' . $absensi->jp_terlaksana . ' diberikan ke Anda.',
            'data'    => ['absensi_id' => $absensi->id, 'jp_terlaksana' => $absensi->jp_terlaksana],
        ]);
    }

    /** POST /absensi/mengajar/batal-pengganti — guru asli batalkan penunjukan pengganti. */
    public function batalPengganti(Request $request): JsonResponse
    {
        $request->validate(['absensi_mengajar_id' => 'required|exists:absensi_mengajar,id']);
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);

        try {
            $absensi = (new \App\Services\PenggantiMengajarService())->batalkanPengganti(
                (int) $request->absensi_mengajar_id, $tp->id
            );
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => 'PENGGANTI'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Penunjukan pengganti dibatalkan. Sesi kembali ke status izin (JP tidak dibayar).',
            'data'    => ['absensi_id' => $absensi->id, 'status' => $absensi->status],
        ]);
    }

    /**
     * POST /absensi/mengajar/absen
     * Submit absensi mengajar (satu kali per jadwal per hari).
     * 
     * Body: jadwal_mengajar_id, foto (file), materi, sudah_buka_jurnal (bool)
     */
    public function absenMengajar(Request $request): JsonResponse
    {
        $request->validate([
            'jadwal_mengajar_id' => 'required|exists:jadwal_mengajar,id',
            'foto'               => 'required|image|mimes:jpeg,jpg,png|max:3072',
            'materi'             => 'nullable|string|max:500',
            'keterangan'         => 'nullable|string|max:300',
        ]);

        $user = $request->user();
        $tp   = $user->tenagaPendidik;

        if (!$tp) {
            return response()->json(['success' => false, 'message' => 'Data tenaga pendidik tidak ditemukan.'], 404);
        }

        $today  = TimezoneHelper::today();
        $jadwal = \App\Models\JadwalMengajar::with('mataPelajaran')
            ->findOrFail($request->jadwal_mengajar_id);

        // Validasi jadwal milik guru ini
        if ($jadwal->tenaga_pendidik_id !== $tp->id) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal ini bukan milik Anda.',
            ], 403);
        }

        // Validasi jadwal hari ini
        $namaHari = TimezoneHelper::namaHariDB($today);
        if (strtolower($jadwal->hari) !== $namaHari) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal ini tidak berlangsung hari ini.',
                'code'    => 'WRONG_DAY',
            ], 422);
        }

        // Cek sudah absen
        $existing = \App\Models\AbsensiMengajar::where('jadwal_mengajar_id', $jadwal->id)
            ->whereDate('tanggal', $today)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah melakukan absen mengajar untuk jadwal ini hari ini.',
                'code'    => 'ALREADY_ABSEN',
                'data'    => $this->formatAbsensiMengajar($existing, $jadwal),
            ], 422);
        }

        // ── Validasi urutan JP — jadwal lebih awal wajib diabsen dulu ──────────
        // Ambil semua jadwal aktif guru hari ini dengan jam_mulai < jadwal ini
        $jadwalLebihAwal = \App\Models\JadwalMengajar::with('mataPelajaran')
            ->where('tenaga_pendidik_id', $tp->id)
            ->where('hari', $namaHari)
            ->where('is_aktif', true)
            ->whereHas('tahunAjaran', fn($q) => $q->where('is_aktif', true))
            // KONSISTEN dgn jadwalMengajarHariIni: Tahfidz & Tahsin punya alur absen
            // sendiri (modul Education), JANGAN dijadikan syarat urutan di sini —
            // kalau tidak, absen reguler bisa deadlock oleh sesi tahfidz yang tak tampil.
            ->whereHas('mataPelajaran', fn($q) => $q->where('tipe', 'reguler')->orWhereNull('tipe'))
            ->where('jam_mulai', '<', $jadwal->jam_mulai)
            ->orderBy('jam_mulai')
            ->get();

        foreach ($jadwalLebihAwal as $jadwalSebelum) {
            $sudahAbsenSebelum = \App\Models\AbsensiMengajar::where('jadwal_mengajar_id', $jadwalSebelum->id)
                ->whereDate('tanggal', $today)
                ->exists();

            if (!$sudahAbsenSebelum) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selesaikan absensi "'
                        .($jadwalSebelum->mataPelajaran?->nama ?? 'Jadwal')
                        .'" (pukul '.$jadwalSebelum->jam_mulai
                        .') terlebih dahulu sebelum absen jadwal ini.',
                    'code'    => 'JP_ORDER_REQUIRED',
                    'jadwal_sebelum' => [
                        'id'            => $jadwalSebelum->id,
                        'mata_pelajaran'=> $jadwalSebelum->mataPelajaran?->nama ?? '—',
                        'jam_mulai'     => $jadwalSebelum->jam_mulai,
                    ],
                ], 422);
            }
        }

        // ── Validasi window waktu mengajar ────────────────────────────────────────
        // Guru hanya bisa absen mulai 15 menit sebelum jam_mulai s.d. jam_selesai.
        // Di luar window ini, absensi TIDAK diproses.
        $sekarang    = TimezoneHelper::now();
        $jamMulaiC   = Carbon::parse($today->toDateString().' '.$jadwal->jam_mulai, TimezoneHelper::TZ);
        $jamSelesaiC = Carbon::parse($today->toDateString().' '.$jadwal->jam_selesai, TimezoneHelper::TZ);
        $batasAwal   = $jamMulaiC->copy()->subMinutes(15);

        if ($sekarang->lt($batasAwal)) {
            $menitLagi = (int) $sekarang->diffInMinutes($batasAwal);
            return response()->json([
                'success'     => false,
                'message'     => 'Belum waktunya absen. Absensi dibuka pukul '
                    .$batasAwal->format('H:i').' ('.$menitLagi.' menit lagi).',
                'code'        => 'TOO_EARLY',
                'batas_awal'  => $batasAwal->format('H:i'),
                'sisa_menit'  => $menitLagi,
            ], 422);
        }

        // Lewat jam_selesai → sesi tetap dibuat sebagai "tidak hadir" (vakasi hangus),
        // agar absensi santri tetap bisa diisi. (Handoff ke guru piket = pengembangan berikutnya.)
        $telat  = $sekarang->gt($jamSelesaiC);
        $status = $telat ? 'tidak_terlaksana' : 'terlaksana';
        $jp     = $telat ? 0 : $jadwal->jumlah_jp;

        // Upload foto mengajar
        $fotoPath = $request->file('foto')->store("foto-mengajar/{$tp->id}", 'public');

        $absensi = \App\Models\AbsensiMengajar::create([
            'jadwal_mengajar_id' => $jadwal->id,
            'tenaga_pendidik_id' => $tp->id,
            'tanggal'            => $today->toDateString(),
            'jam_mulai_aktual'   => $sekarang->format('H:i:s'), // reuse $sekarang WIB
            'jp_terlaksana'      => $jp,
            'status'             => $status,
            'materi'             => $request->materi,
            'keterangan'         => $request->keterangan,
            'foto_mengajar'      => $fotoPath,
            'sudah_buka_jurnal'  => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => $telat
                ? "Sudah lewat jam selesai ({$jamSelesaiC->format('H:i')}) → ditandai TIDAK HADIR, vakasi tidak diberikan. Absensi santri tetap dapat diisi."
                : "Absen mengajar berhasil! {$jadwal->mataPelajaran?->nama} — {$jadwal->jumlah_jp} JP.",
            'telat'   => $telat,
            'data'    => $this->formatAbsensiMengajar($absensi->load('jadwalMengajar.mataPelajaran'), $jadwal),
        ]);
    }

    /**
     * GET /absensi/mengajar/riwayat
     */
    public function riwayatMengajar(Request $request): JsonResponse
    {
        $user = $request->user();
        $tp   = $user->tenagaPendidik;

        if (!$tp) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan.'], 404);
        }

        $bulan = (int) ($request->bulan ?? now()->month);
        $tahun = (int) ($request->tahun ?? now()->year);

        $list = \App\Models\AbsensiMengajar::with('jadwalMengajar.mataPelajaran')
            ->where('tenaga_pendidik_id', $tp->id)
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun)
            ->orderByDesc('tanggal')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'bulan'       => $bulan,
                'tahun'       => $tahun,
                'riwayat'     => $list->map(fn($a) => $this->formatAbsensiMengajar(
                    $a, $a->jadwalMengajar
                ))->values(),
                'total_jp'    => (int) $list->sum('jp_terlaksana'),
                'total_sesi'  => $list->count(),
            ],
        ]);
    }

    /**
     * mulaiMengajar — alias ke absenMengajar untuk backward compat
     */
    public function mulaiMengajar(Request $request): JsonResponse
    {
        return $this->absenMengajar($request);
    }

    public function selesaiMengajar(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Absensi mengajar sudah selesai dicatat saat mulai.']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SMART EDUCATION — ABSENSI SANTRI (Jurnal Mengajar Sekolah)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /absensi/mengajar/{jadwalId}/santri
     * Daftar santri pada kelas jadwal ini + status absensi yang sudah terisi
     * (jika guru sudah absen mengajar hari ini). Default status = 'hadir'.
     */
    public function santriKelasJadwal(Request $request, $jadwalId): JsonResponse
    {
        $tp = $request->user()->tenagaPendidik;
        if (!$tp) {
            return response()->json(['success' => false, 'message' => 'Data tenaga pendidik tidak ditemukan.'], 404);
        }

        $jadwal = \App\Models\JadwalMengajar::with(['mataPelajaran', 'kelasRel'])->findOrFail($jadwalId);

        // Boleh diakses pemilik jadwal ATAU guru pengganti yang ditunjuk untuk sesi hari ini.
        $isOwner = $jadwal->tenaga_pendidik_id === $tp->id;
        $isPengganti = !$isOwner && \App\Models\AbsensiMengajar::where('jadwal_mengajar_id', $jadwal->id)
            ->whereDate('tanggal', TimezoneHelper::today())
            ->where('status', 'pengganti')->where('digantikan_oleh', $tp->id)->exists();
        if (!$isOwner && !$isPengganti) {
            return response()->json(['success' => false, 'message' => 'Jadwal ini bukan milik Anda.'], 403);
        }

        if (!$jadwal->kelas_id) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas untuk jadwal ini belum tersinkron. Hubungi admin untuk melengkapi data kelas.',
                'code'    => 'KELAS_BELUM_SINKRON',
            ], 422);
        }

        $today = TimezoneHelper::today();

        // Record absen mengajar hari ini (kepala sesi) — bisa belum ada.
        $absensi = \App\Models\AbsensiMengajar::where('jadwal_mengajar_id', $jadwal->id)
            ->whereDate('tanggal', $today)->first();

        $terisi = $absensi
            ? \App\Models\AbsensiSantri::where('absensi_mengajar_id', $absensi->id)
                ->pluck('status', 'santri_id')
            : collect();

        $santri = \App\Models\Santri::aktif()
            ->whereHas('kelas', fn($q) => $q->where('kelas.id', $jadwal->kelas_id))
            ->orderBy('nama_lengkap')->get()
            ->map(fn($s) => [
                'santri_id' => $s->id,
                'nip'       => $s->nip,
                'nama'      => $s->nama_lengkap,
                'status'    => $terisi[$s->id] ?? 'hadir',
            ])->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'jadwal_id'           => $jadwal->id,
                'kelas'               => $jadwal->kelasRel?->nama ?? $jadwal->kelas,
                'mata_pelajaran'      => $jadwal->mataPelajaran?->nama ?? '—',
                'absensi_mengajar_id' => $absensi?->id, // null jika guru belum absen mengajar
                'sudah_isi_santri'    => $terisi->isNotEmpty(),
                'total_santri'        => $santri->count(),
                'santri'              => $santri,
            ],
        ]);
    }

    /**
     * POST /absensi/mengajar/absen-santri
     * Simpan absensi santri untuk satu sesi mengajar (absensi_mengajar_id) +
     * opsi memperbarui materi/jurnal. Idempoten (updateOrCreate per santri).
     */
    public function absenSantri(Request $request): JsonResponse
    {
        $request->validate([
            'absensi_mengajar_id'  => 'required|exists:absensi_mengajar,id',
            'absensi'              => 'required|array|min:1',
            'absensi.*.santri_id'  => 'required|integer|exists:santri,id',
            'absensi.*.status'     => 'required|in:hadir,telat,alpha',
            'materi'               => 'nullable|string|max:500',
        ]);

        $tp = $request->user()->tenagaPendidik;
        if (!$tp) {
            return response()->json(['success' => false, 'message' => 'Data tenaga pendidik tidak ditemukan.'], 404);
        }

        $absensi = \App\Models\AbsensiMengajar::findOrFail($request->absensi_mengajar_id);
        if ($absensi->tenaga_pendidik_id !== $tp->id) {
            return response()->json(['success' => false, 'message' => 'Sesi mengajar ini bukan milik Anda.'], 403);
        }

        // Kunci: absensi santri hanya boleh disimpan SEKALI. Setelah tersimpan,
        // tidak dapat diperbarui lagi (final) demi integritas data kehadiran.
        if (\App\Models\AbsensiSantri::where('absensi_mengajar_id', $absensi->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Absensi santri sesi ini sudah dikunci dan tidak dapat diubah lagi.',
                'code'    => 'ABSENSI_TERKUNCI',
            ], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $absensi) {
            foreach ($request->absensi as $row) {
                \App\Models\AbsensiSantri::create([
                    'absensi_mengajar_id' => $absensi->id,
                    'santri_id'           => $row['santri_id'],
                    'status'              => $row['status'],
                ]);
            }
            if ($request->filled('materi')) {
                $absensi->update(['materi' => $request->materi]);
            }
        });

        // Fase A — kirim santri "telat" ke RamahAnak (batch, idempotent). Aman bila gagal.
        app(\App\Services\EducationTelatSync::class)->pushSesi(
            $absensi,
            $absensi->jadwalMengajar?->mataPelajaran?->nama ?? 'KBM',
            "{$request->user()->name} (NIP {$tp->nip})",
        );

        // Notifikasi WA wali per santri (hadir/telat/alfa).
        $pembelajaran = $absensi->jadwalMengajar?->mataPelajaran?->nama ?? 'KBM';
        $tglAjr = $absensi->tanggal instanceof \Carbon\Carbon
            ? $absensi->tanggal->toDateString() : (string) $absensi->tanggal;
        foreach (\App\Models\AbsensiSantri::where('absensi_mengajar_id', $absensi->id)->get() as $as) {
            app(\App\Services\WaService::class)->absenMengajar(
                $as->santri_id, $as->status, $pembelajaran, $tglAjr, $as->id);
        }

        $rekap = \App\Models\AbsensiSantri::where('absensi_mengajar_id', $absensi->id)
            ->selectRaw('status, COUNT(*) as jml')->groupBy('status')->pluck('jml', 'status');

        return response()->json([
            'success' => true,
            'message' => 'Absensi santri tersimpan.',
            'data'    => [
                'absensi_mengajar_id' => $absensi->id,
                'hadir' => (int) ($rekap['hadir'] ?? 0),
                'telat' => (int) ($rekap['telat'] ?? 0),
                'alpha' => (int) ($rekap['alpha'] ?? 0),
                'total' => (int) $rekap->sum(),
            ],
        ]);
    }

    private function formatAbsensiMengajar(\App\Models\AbsensiMengajar $a, $jadwal): array
    {
        return [
            'id'               => $a->id,
            'jadwal_id'        => $a->jadwal_mengajar_id,
            'tanggal'          => $a->tanggal?->toDateString(),
            'mata_pelajaran'   => $jadwal?->mataPelajaran?->nama ?? '—',
            'kelas'            => $jadwal?->kelas ?? '—',
            'jam_mulai'        => $jadwal?->jam_mulai,
            'jam_selesai'      => $jadwal?->jam_selesai,
            'jumlah_jp'        => $jadwal?->jumlah_jp,
            'jam_mulai_aktual' => $a->jam_mulai_aktual
                ? Carbon::parse($a->jam_mulai_aktual)->format('H:i') : null,
            'jp_terlaksana'    => $a->jp_terlaksana,
            'status'           => $a->status,
            'materi'           => $a->materi,
            'foto_url'         => $a->foto_mengajar
                ? asset('storage/'.$a->foto_mengajar) : null,
            'sudah_buka_jurnal'=> $a->sudah_buka_jurnal ?? false,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HELPER
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Deteksi izin/cuti yang sudah disetujui dan aktif pada tanggal tertentu.
     * Return array info izin, atau null jika tidak ada.
     *
     * status_absensi: 'dinas_luar' | 'sakit' | 'izin' (dari PengajuanIzin::getStatusAbsensi())
     */
    private function deteksiIzinAktif(int $tpId, string $tanggal): ?array
    {
        $izin = \App\Models\PengajuanIzin::where('tenaga_pendidik_id', $tpId)
            ->where('status', 'disetujui')
            ->where('tanggal_mulai', '<=', $tanggal)
            ->where('tanggal_selesai', '>=', $tanggal)
            ->with('jenisPengajuan')
            ->first();

        if (!$izin) return null;

        return [
            'ada'            => true,
            'jenis'          => $izin->jenisPengajuan?->nama ?? 'Izin',
            'kategori'       => $izin->jenisPengajuan?->kategori ?? 'izin',
            'status_absensi' => $izin->getStatusAbsensi(),
            'tanggal_mulai'  => $izin->tanggal_mulai?->toDateString(),
            'tanggal_selesai'=> $izin->tanggal_selesai?->toDateString(),
        ];
    }

    private function formatAbsensi(AbsensiHarian $a): array
    {
        $menit          = (int) ($a->menit_terlambat ?? 0);
        $jam            = (int) floor($menit / 60);
        $menitSisa      = $menit % 60;
        $labelTerlambat = $menit === 0
            ? 'Tepat waktu'
            : ($jam > 0 ? "{$jam} jam {$menitSisa} menit" : "{$menit} menit");

        // Hitung durasi kerja jika sudah checkout
        $durasiMenit = null;
        $durasiLabel = null;
        if ($a->jam_masuk && $a->jam_pulang) {
            $masuk       = Carbon::parse($a->tanggal?->toDateString() . ' ' . $a->jam_masuk);
            $pulang      = Carbon::parse($a->tanggal?->toDateString() . ' ' . $a->jam_pulang);
            $durasiMenit = (int) $masuk->diffInMinutes($pulang);
            $dJam        = (int) floor($durasiMenit / 60);
            $dMenit      = $durasiMenit % 60;
            $durasiLabel = "{$dJam} jam {$dMenit} menit";
        }

        return [
            'id'               => $a->id,
            'tanggal'          => $a->tanggal?->toDateString(),
            'jam_masuk'        => $a->jam_masuk
                ? Carbon::parse($a->jam_masuk)->format('H:i') : null,
            'jam_pulang'       => $a->jam_pulang
                ? Carbon::parse($a->jam_pulang)->format('H:i') : null,
            'status'           => $a->status,
            'menit_terlambat'  => $menit,
            'label_terlambat'  => $labelTerlambat,
            'durasi_menit'     => $durasiMenit,
            'durasi_label'     => $durasiLabel,
            'keterangan'       => $a->keterangan,
            'is_koreksi'       => $a->is_koreksi ?? false,
            // Foto
            'foto_masuk_url'   => $a->foto_masuk
                ? asset('storage/' . $a->foto_masuk)  : null,
            'foto_pulang_url'  => $a->foto_pulang
                ? asset('storage/' . $a->foto_pulang) : null,
            // Koordinat
            'lat_masuk'        => $a->lat_masuk,
            'lng_masuk'        => $a->lng_masuk,
            'lat_pulang'       => $a->lat_pulang,
            'lng_pulang'       => $a->lng_pulang,
            // Checkout luar lokasi (penanda + alasan, untuk audit/monitoring)
            'pulang_luar_lokasi' => (bool) ($a->pulang_luar_lokasi ?? false),
            'alasan_pulang'      => $a->alasan_pulang,
            'jarak_pulang_meter' => $a->jarak_pulang_meter,
            // Validasi lokasi
            'validasi_lokasi'  => $a->validasi_lokasi,
            'jarak_meter'      => $a->jarak_meter,
            'nama_wifi'        => $a->nama_wifi,
        ];
    }
}
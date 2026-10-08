<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalMengajar extends Model
{
    protected $table = 'jadwal_mengajar';

    protected $fillable = [
        'tahun_ajaran_id',
        'tenaga_pendidik_id',
        'mata_pelajaran_id',
        'kelas_id',
        'hari',
        'jam_mulai',
        'jam_selesai',
        'jumlah_jp',
        'kelas',
        'ruangan',
        'is_aktif',
        'berlaku_mulai',    // masa berlaku; dipakai jadwal sekali-pakai (sesi ujian)
        'berlaku_selesai',
        'ujian_sesi_id',    // terisi = jadwal ini sesi UJIAN, bukan pembelajaran reguler
    ];

    protected function casts(): array
    {
        return [
            'is_aktif'        => 'boolean',
            'jumlah_jp'       => 'integer',
            'berlaku_mulai'   => 'date',
            'berlaku_selesai' => 'date',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function kelasRel()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function absensiMengajar()
    {
        return $this->hasMany(AbsensiMengajar::class);
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }

    public function scopeHariIni($query)
    {
        $hariMap = [
            'Monday'    => 'senin',
            'Tuesday'   => 'selasa',
            'Wednesday' => 'rabu',
            'Thursday'  => 'kamis',
            'Friday'    => 'jumat',
            'Saturday'  => 'sabtu',
            'Sunday'    => 'ahad',
        ];

        return $query->where('hari', $hariMap[now()->format('l')])
            ->berlakuPada(now()->toDateString());
    }

    /**
     * Jadwal yang BERLAKU pada tanggal tsb.
     *
     * Wajib dipakai setiap kali menanyakan "jadwal pada tanggal X". Tanpa ini,
     * jadwal sekali-pakai (sesi ujian) akan muncul lagi setiap pekan di hari yang
     * sama dan memicu "sesi tidak terlaksana" palsu, sementara jadwal yang sudah
     * berakhir tetap ditagih.
     *
     * `created_at` tetap ikut dijaga: jadwal yang baru dibuat tidak boleh
     * menghukum hari-hari sebelum ia ada (perilaku lama, dipertahankan).
     */
    public function scopeBerlakuPada($query, string|\Carbon\Carbon|\DateTimeInterface $tanggal)
    {
        // Sengaja menerima string maupun Carbon: pemanggilnya belasan tempat dan
        // separuhnya memegang Carbon — memaksa satu tipe hanya melahirkan TypeError.
        $tanggal = $tanggal instanceof \DateTimeInterface
            ? $tanggal->format('Y-m-d') : (string) $tanggal;

        return $query
            // Masa berlaku EKSPLISIT menang atas penjaga created_at. Tanpa ini,
            // sesi ujian yang dicatat untuk tanggal yang sudah lewat (mis. panitia
            // merapikan data kemarin) tidak akan pernah terlihat pada tanggalnya.
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->whereNotNull('berlaku_mulai')->whereDate('berlaku_mulai', '<=', $tanggal))
                ->orWhere(fn ($w) => $w->whereNull('berlaku_mulai')->whereDate('created_at', '<=', $tanggal)))
            ->where(fn ($q) => $q->whereNull('berlaku_selesai')->orWhereDate('berlaku_selesai', '>=', $tanggal));
    }

    /** Hanya pembelajaran reguler — sesi ujian dikecualikan. */
    public function scopeBukanUjian($query)
    {
        return $query->whereNull('ujian_sesi_id');
    }

    /** Hanya sesi ujian. */
    public function scopeUjian($query)
    {
        return $query->whereNotNull('ujian_sesi_id');
    }

    public function ujianSesi()
    {
        return $this->belongsTo(UjianSesi::class, 'ujian_sesi_id');
    }

    /** Sesi ujian? (dipakai UI & aturan vakasi) */
    public function isUjian(): bool
    {
        return $this->ujian_sesi_id !== null;
    }
}
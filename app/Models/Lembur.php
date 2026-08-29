<?php

namespace App\Models;

use App\Services\TimezoneHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Lembur — event/batch lembur per jabatan.
 * Tarif flat dari SettingVakasi tipe 'lembur' (tanpa override).
 * jam_selesai = jam_mulai + durasi_menit (opsi A, dihitung otomatis).
 */
class Lembur extends Model
{
    protected $table = 'lembur';

    protected $fillable = [
        'jabatan_id',
        'judul',
        'deskripsi',
        'tanggal',
        'jam_mulai',
        'durasi_menit',
        'jam_selesai',
        'setting_vakasi_id',
        'nominal_vakasi',
        'min_durasi_menit',
        'batas_grace_menit',
        'sumber_input',     // mandiri | kolektif
        'status',           // diajukan | disetujui | ditolak | dibatalkan
        'diajukan_oleh',
        'disetujui_oleh',
        'disetujui_pada',
        'catatan',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'           => 'date',
            'durasi_menit'      => 'integer',
            'nominal_vakasi'    => 'float',
            'min_durasi_menit'  => 'integer',
            'batas_grace_menit' => 'integer',
            'disetujui_pada'    => 'datetime',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function jabatan()
    {
        return $this->belongsTo(Jabatan::class);
    }

    public function settingVakasi()
    {
        return $this->belongsTo(SettingVakasi::class);
    }

    public function peserta()
    {
        return $this->hasMany(LemburPeserta::class);
    }

    public function diajukanOleh()
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function disetujuiOleh()
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    // ─── Helper waktu (opsi A + lintas-hari) ──────────────────────────────────

    /**
     * Hitung jam selesai dari jam_mulai + durasi (format H:i:s).
     * Dipakai service saat approve agar jam_selesai konsisten.
     */
    public function hitungJamSelesai(): ?string
    {
        if (!$this->jam_mulai || !$this->durasi_menit) return null;
        return Carbon::parse($this->jam_mulai, TimezoneHelper::TZ)
            ->addMinutes((int) $this->durasi_menit)
            ->format('H:i:s');
    }

    /**
     * Datetime batas selesai lembur (tanggal + jam_selesai).
     * Menangani lintas-hari: jika jam_selesai <= jam_mulai → +1 hari.
     */
    public function waktuSelesai(): ?Carbon
    {
        if (!$this->jam_selesai) return null;
        $tgl = $this->tanggal->toDateString();
        $selesai = TimezoneHelper::parse("{$tgl} {$this->jam_selesai}");
        if ($this->jam_mulai && $this->jam_selesai <= $this->jam_mulai) {
            $selesai->addDay(); // lembur melewati tengah malam
        }
        return $selesai;
    }

    /** Batas akhir user boleh upload bukti = waktuSelesai + grace. */
    public function batasAkhirUpload(): ?Carbon
    {
        $selesai = $this->waktuSelesai();
        return $selesai?->copy()->addMinutes((int) ($this->batas_grace_menit ?? 60));
    }

    /** Apakah sekarang berada dalam jendela upload [selesai, selesai+grace]. */
    public function dalamJendelaUpload(?Carbon $now = null): bool
    {
        $now     = $now ?? TimezoneHelper::now();
        $selesai = $this->waktuSelesai();
        $batas   = $this->batasAkhirUpload();
        if (!$selesai || !$batas) return false;
        return $now->gte($selesai) && $now->lte($batas);
    }

    /** Lewat tenggang & belum ditangani (untuk antrian "Perlu Tindakan" admin). */
    public function sudahTerlewat(?Carbon $now = null): bool
    {
        $now   = $now ?? TimezoneHelper::now();
        $batas = $this->batasAkhirUpload();
        return $this->status === 'disetujui' && $batas && $now->gt($batas);
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeByStatus($q, string $status) { return $q->where('status', $status); }
    public function scopeDisetujui($q) { return $q->where('status', 'disetujui'); }
    public function scopeByJabatan($q, int $id) { return $q->where('jabatan_id', $id); }
}

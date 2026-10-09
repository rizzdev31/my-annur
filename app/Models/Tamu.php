<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris buku tamu. Dibuat dari halaman publik, bukan dari admin.
 */
class Tamu extends Model
{
    protected $table = 'tamu';

    protected $fillable = [
        'kegiatan_tamu_id', 'nomor_urut', 'nama', 'asal', 'pekerjaan', 'email', 'telepon',
        'tanda_tangan', 'ip', 'perangkat', 'diisi_pada',
        'email_status', 'email_terkirim_pada', 'email_error',
        'konfirmasi_terkirim_pada', 'konfirmasi_error',
    ];

    protected function casts(): array
    {
        return [
            'nomor_urut'               => 'integer',
            'diisi_pada'               => 'datetime',
            'email_terkirim_pada'      => 'datetime',
            'konfirmasi_terkirim_pada' => 'datetime',
        ];
    }

    /** Label status pengiriman notulensi untuk tampilan admin. */
    public const LABEL_EMAIL = [
        'belum'    => 'Belum dikirim',
        'menunggu' => 'Dalam antrean',
        'terkirim' => 'Terkirim',
        'gagal'    => 'Gagal',
        'duplikat' => 'Alamat ganda',
    ];

    public function kegiatan()
    {
        return $this->belongsTo(KegiatanTamu::class, 'kegiatan_tamu_id');
    }

    /**
     * Nomor tersimpan ternormalkan (`6281…`); tampilkan dalam bentuk yang
     * dikenali orang Indonesia agar mudah dicocokkan dengan kontak di ponsel.
     */
    public function getTeleponTampilAttribute(): ?string
    {
        if (blank($this->telepon)) return null;

        if (str_starts_with($this->telepon, '62')) {
            return '0' . substr($this->telepon, 2);
        }

        return '+' . $this->telepon;
    }

    /** Tautan chat WhatsApp. Null bila nomor tidak ada. */
    public function getWaUrlAttribute(): ?string
    {
        return blank($this->telepon) ? null : 'https://wa.me/' . $this->telepon;
    }

    public function getTandaTanganUrlAttribute(): ?string
    {
        return $this->tanda_tangan ? asset('storage/' . $this->tanda_tangan) : null;
    }

    /** Tanda tangan sebagai data-URI — dompdf tidak memuat berkas lewat URL. */
    public function tandaTanganDataUri(): ?string
    {
        if (!$this->tanda_tangan) return null;

        $path = storage_path('app/public/' . $this->tanda_tangan);
        if (!is_file($path)) return null;

        return 'data:image/png;base64,' . base64_encode((string) file_get_contents($path));
    }
}

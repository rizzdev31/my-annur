<?php // ══════════════════════════════════════════
// app/Models/Jabatan.php
// ══════════════════════════════════════════

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Jabatan extends Model
{
    use SoftDeletes;
    protected $table = 'jabatan';
    protected $fillable = ['nama_jabatan', 'kode_jabatan', 'deskripsi', 'tipe', 'is_aktif', 'wajib_kegiatan'];
    protected function casts(): array { return ['is_aktif' => 'boolean', 'wajib_kegiatan' => 'boolean']; }

    public function tenagaPendidik() { return $this->hasMany(TenagaPendidik::class); }
    public function tugasJabatan()   { return $this->hasMany(TugasJabatan::class); }
    public function settingGajiPokok() { return $this->hasMany(SettingGajiPokok::class); }
    public function lembur()         { return $this->hasMany(Lembur::class); }

    public function scopeAktif($q) { return $q->where('is_aktif', true); }
}
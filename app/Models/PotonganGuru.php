<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PotonganGuru extends Model
{
    protected $table = 'potongan_guru';

    protected $fillable = ['jenis_potongan_id', 'tenaga_pendidik_id', 'nominal', 'is_aktif'];

    protected function casts(): array
    {
        return [
            'nominal'  => 'float',
            'is_aktif' => 'boolean',
        ];
    }

    public function jenis()
    {
        return $this->belongsTo(JenisPotongan::class, 'jenis_potongan_id');
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaInbox extends Model
{
    protected $table = 'wa_inbox';

    protected $fillable = [
        'device', 'pengirim', 'nama', 'santri_id', 'pesan',
        'media_url', 'raw', 'dibaca', 'diterima_pada',
    ];

    protected function casts(): array
    {
        return [
            'raw'           => 'array',
            'dibaca'        => 'boolean',
            'diterima_pada' => 'datetime',
        ];
    }

    public function santri()
    {
        return $this->belongsTo(Santri::class);
    }
}

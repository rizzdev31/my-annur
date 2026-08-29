<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaOutbox extends Model
{
    protected $table = 'wa_outbox';

    protected $fillable = [
        'ref_id', 'tujuan', 'santri_id', 'jenis', 'pesan', 'media_url',
        'status', 'provider_response', 'attempts', 'error', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_response' => 'array',
            'attempts'          => 'integer',
            'sent_at'           => 'datetime',
        ];
    }

    public function santri()
    {
        return $this->belongsTo(Santri::class);
    }
}

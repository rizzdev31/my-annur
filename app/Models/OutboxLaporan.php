<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxLaporan extends Model
{
    protected $table = 'outbox_laporan';

    protected $fillable = [
        'jenis', 'payload', 'ref_id', 'status', 'attempts',
        'response', 'error', 'ramahanak_laporan_id', 'actor', 'sent_at',
    ];

    protected $casts = [
        'payload'  => 'array',
        'response' => 'array',
        'sent_at'  => 'datetime',
    ];
}

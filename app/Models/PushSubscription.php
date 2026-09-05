<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu perangkat yang berlangganan notifikasi push. */
class PushSubscription extends Model
{
    protected $fillable = [
        'user_id', 'endpoint', 'endpoint_hash', 'p256dh', 'auth',
        'perangkat', 'terakhir_dipakai',
    ];

    protected function casts(): array
    {
        return ['terakhir_dipakai' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Kunci pencarian endpoint (endpoint terlalu panjang untuk diindeks utuh). */
    public static function hash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}

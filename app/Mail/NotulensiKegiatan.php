<?php

namespace App\Mail;

use App\Models\KegiatanTamu;
use App\Models\Tamu;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notulensi kegiatan untuk SATU tamu.
 *
 * Isinya dikirim sebagai HTML di BADAN email, bukan lampiran PDF. Keputusan ini
 * sengaja: lampiran menambah ratusan KB per penerima (50 tamu bisa belasan MB
 * keluar sekali kirim, memakan kuota Hostinger), menaikkan skor spam, dan
 * memaksa penerima mengunduh sebelum bisa membaca — padahal mayoritas tamu
 * membukanya dari ponsel. Yang ingin mengarsipkan cukup membuka tautan versi
 * web di akhir email, dan dari sana bisa mencetak sendiri.
 */
class NotulensiKegiatan extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public KegiatanTamu $kegiatan,
        public Tamu $tamu,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Notulensi: ' . $this->kegiatan->nama,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notulensi',
            with: [
                'kegiatan'  => $this->kegiatan,
                'tamu'      => $this->tamu,
                'tautanWeb' => url('/tamu/' . $this->kegiatan->token . '/notulensi'),
            ],
        );
    }
}

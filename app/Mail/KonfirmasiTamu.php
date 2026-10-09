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
 * Konfirmasi begitu tamu selesai mengisi buku tamu.
 *
 * Bukan sekadar sopan: inilah yang MEMVERIFIKASI alamat emailnya benar jauh
 * sebelum notulensi dikirim. Tanpa ini, salah ketik ("gmial.com") baru ketahuan
 * berminggu kemudian saat pengiriman notulensi gagal — dan tiap kiriman gagal
 * tetap memakan kuota kirim.
 */
class KonfirmasiTamu extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public KegiatanTamu $kegiatan,
        public Tamu $tamu,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Terima kasih atas kunjungan Anda — ' . $this->kegiatan->nama,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.konfirmasi-tamu',
            with: ['kegiatan' => $this->kegiatan, 'tamu' => $this->tamu],
        );
    }
}

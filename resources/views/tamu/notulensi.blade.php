{{--
    Versi web notulensi. Inilah pengganti lampiran PDF: email tetap ringan,
    dan yang ingin mengarsipkan mencetak dari sini (Ctrl+P / "Bagikan → Cetak"
    di ponsel sudah menghasilkan PDF sendiri).

    Aturan cetak disiapkan di @media print agar hasilnya bersih tanpa tombol.
--}}
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Notulensi — {{ $kegiatan->nama }}</title>
    <link rel="icon" href="{{ asset('logo.png') }}">
    @vite(['resources/css/tamu.css'])
    <style>
        @media print {
            .tanpa-cetak { display: none !important; }
            body { background: #fff !important; }
            .lembar { box-shadow: none !important; border: 0 !important; border-radius: 0 !important; }
        }
    </style>
</head>
<body class="min-h-full bg-slate-100 antialiased">
<div class="mx-auto w-full max-w-3xl px-4 py-8 sm:py-12">

    <div class="lembar overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">

        {{-- Kop --}}
        <div class="border-b border-slate-200 bg-slate-900 px-6 py-5 sm:px-8">
            <p class="text-base font-bold text-white">Pondok Pesantren An-Nur</p>
            <p class="mt-0.5 text-xs text-slate-400">Notulensi Kegiatan</p>
        </div>

        <div class="px-6 py-7 sm:px-8">
            <h1 class="text-xl font-bold leading-snug text-slate-900 sm:text-2xl">{{ $kegiatan->nama }}</h1>

            <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                <div class="flex gap-2">
                    <dt class="w-24 shrink-0 text-slate-500">Waktu</dt>
                    <dd class="font-medium text-slate-800">{{ $kegiatan->rentangLabel() }}</dd>
                </div>
                @if ($kegiatan->lokasi)
                    <div class="flex gap-2">
                        <dt class="w-24 shrink-0 text-slate-500">Tempat</dt>
                        <dd class="font-medium text-slate-800">{{ $kegiatan->lokasi }}</dd>
                    </div>
                @endif
                @if ($kegiatan->penyelenggara)
                    <div class="flex gap-2">
                        <dt class="w-24 shrink-0 text-slate-500">Penyelenggara</dt>
                        <dd class="font-medium text-slate-800">{{ $kegiatan->penyelenggara }}</dd>
                    </div>
                @endif
                <div class="flex gap-2">
                    <dt class="w-24 shrink-0 text-slate-500">Peserta</dt>
                    <dd class="font-medium text-slate-800">{{ $kegiatan->tamu()->count() }} tamu tercatat</dd>
                </div>
            </dl>

            @if ($kegiatan->deskripsi)
                <p class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm leading-relaxed text-slate-600">
                    {{ $kegiatan->deskripsi }}
                </p>
            @endif

            <hr class="my-6 border-slate-200">

            <div class="prose-sm max-w-none">
                {!! $kegiatan->notulensiHtml() !!}
            </div>

            <p class="mt-8 border-t border-slate-200 pt-4 text-xs text-slate-400">
                Diterbitkan {{ $kegiatan->notulensi_dikirim_pada->locale('id')->isoFormat('D MMMM YYYY, HH:mm') }} WIB
                &middot; {{ $kegiatan->penyelenggara ?: 'Pondok Pesantren An-Nur' }}
            </p>
        </div>
    </div>

    <div class="tanpa-cetak mt-5 flex flex-wrap items-center justify-center gap-3">
        <button type="button" onclick="window.print()"
                class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white">
            Cetak / Simpan PDF
        </button>
    </div>

    <p class="tanpa-cetak mt-6 text-center text-[11px] text-slate-400">
        Buku Tamu Digital &middot; Pondok Pesantren An-Nur
    </p>
</div>
</body>
</html>

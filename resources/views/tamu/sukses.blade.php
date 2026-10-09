{{--
    Layar sukses. Nomor urut ditonjolkan karena itulah yang tamu cari sebagai
    bukti kehadirannya, dan menjelaskan apa yang akan terjadi dengan emailnya.
--}}
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Terima kasih — {{ $kegiatan->nama }}</title>
    <link rel="icon" href="{{ asset('logo.png') }}">
    @vite(['resources/css/tamu.css'])
</head>
<body class="min-h-full bg-slate-100 antialiased">
<div class="mx-auto w-full max-w-lg px-4 py-10">

    <div class="rounded-2xl bg-white p-7 text-center shadow-sm ring-1 ring-slate-200">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50">
            <svg class="h-9 w-9 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <h1 class="mt-4 text-xl font-bold text-slate-900">Terima kasih, {{ $nama }}</h1>
        <p class="mt-1 text-sm text-slate-500">Kehadiran Anda sudah tercatat.</p>

        <div class="my-6 rounded-2xl bg-sky-50 px-4 py-5">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-sky-700">Nomor Urut Kunjungan</p>
            <p class="mt-1 text-5xl font-black leading-none text-sky-700">{{ $nomor }}</p>
        </div>

        <div class="space-y-1 text-sm">
            <p class="font-semibold text-slate-800">{{ $kegiatan->nama }}</p>
            <p class="text-slate-500">{{ $kegiatan->rentangLabel() }}</p>
            @if ($kegiatan->lokasi)
                <p class="text-slate-500">{{ $kegiatan->lokasi }}</p>
            @endif
        </div>

        <p class="mt-6 rounded-xl bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600">
            Notulensi/hasil kegiatan akan kami kirimkan ke
            <span class="font-semibold text-slate-800">{{ $email }}</span>.
            Mohon periksa folder <span class="font-semibold">Spam</span> bila belum terlihat.
        </p>
    </div>

    <div class="mt-4 text-center">
        <a href="{{ route('tamu.form', $kegiatan->token) }}"
           class="inline-block rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600">
            Isi untuk tamu berikutnya
        </a>
    </div>

    <p class="mt-6 text-center text-[11px] text-slate-400">
        {{ $kegiatan->penyelenggara ?: 'Pondok Pesantren An-Nur' }} &middot; Buku Tamu Digital
    </p>
</div>
</body>
</html>

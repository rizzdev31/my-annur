{{--
    HALAMAN TAMU — publik, dibuka lewat tautan, mayoritas dari ponsel.

    Sengaja Blade mandiri (bukan PWA/Inertia): satu berkas ringan yang terbuka
    cepat di jaringan lokasi acara, tanpa memuat seluruh aplikasi. Tidak ada
    menu atau navigasi lain di halaman ini — tamu hanya perlu satu hal.
--}}
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    {{-- Zoom TIDAK dimatikan: tamu lanjut usia perlu memperbesar. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Buku Tamu — {{ $kegiatan->nama }}</title>
    <link rel="icon" href="{{ asset('logo.png') }}">
    @vite(['resources/css/tamu.css'])
    <style>
        /* Kanvas tanda tangan: cegah halaman tergeser saat menggambar. */
        #ttd { touch-action: none; }
    </style>
</head>
<body class="min-h-full bg-slate-100 antialiased">
<div class="mx-auto w-full max-w-lg px-4 py-6 sm:py-10">

    {{-- Identitas penyelenggara --}}
    <div class="text-center mb-5">
        <img src="{{ asset('logo.png') }}" alt="" class="mx-auto h-14 w-14 object-contain">
        <p class="mt-2 text-[11px] font-semibold uppercase tracking-widest text-slate-500">
            {{ $kegiatan->penyelenggara ?: 'Pondok Pesantren An-Nur' }}
        </p>
        <h1 class="mt-1 text-xl font-bold leading-snug text-slate-900">{{ $kegiatan->nama }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $kegiatan->rentangLabel() }}</p>
        @if ($kegiatan->lokasi)
            <p class="text-sm text-slate-500">{{ $kegiatan->lokasi }}</p>
        @endif
    </div>

    @if ($tertutup)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center">
            <p class="text-base font-bold text-amber-900">Buku Tamu Tertutup</p>
            <p class="mt-2 text-sm leading-relaxed text-amber-800">{{ $tertutup }}</p>
            <p class="mt-3 text-xs text-amber-700">
                Silakan hubungi petugas penerima tamu bila Anda masih perlu mencatatkan kehadiran.
            </p>
        </div>
    @else
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-6">
            <div class="mb-5 flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Buku Tamu</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Mohon isi data berikut. Tanda <span class="text-rose-500">*</span> wajib.
                    </p>
                </div>
                @if ($jumlah > 0)
                    <span class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold text-slate-600">
                        Tamu ke-{{ $jumlah + 1 }}
                    </span>
                @endif
            </div>

            {{-- isset(): view ini juga dirender di luar middleware web (uji & pratinjau). --}}
            @if (isset($errors) && $errors->any())
                <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3">
                    <p class="text-sm font-semibold text-rose-800">Mohon periksa kembali:</p>
                    <ul class="mt-1 list-inside list-disc text-xs leading-relaxed text-rose-700">
                        @foreach ($errors->all() as $pesan)
                            <li>{{ $pesan }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('tamu.simpan', $kegiatan->token) }}" id="form-tamu" novalidate>
                @csrf
                {{-- Anti-robot: kolom tersembunyi + penanda waktu buka halaman. --}}
                <input type="text" name="website" tabindex="-1" autocomplete="off"
                       class="hidden" aria-hidden="true">
                <input type="hidden" name="dibuka_pada" value="{{ time() }}">
                <input type="hidden" name="tanda_tangan" id="tanda_tangan" value="">

                <div class="space-y-4">
                    <div>
                        <label for="nama" class="mb-1.5 block text-sm font-semibold text-slate-700">
                            Nama Lengkap <span class="text-rose-500">*</span>
                        </label>
                        {{-- text-base (16px) agar iOS tidak auto-zoom saat fokus. --}}
                        <input id="nama" name="nama" type="text" required autocomplete="name"
                               value="{{ old('nama') }}" placeholder="cth: Ahmad Fauzi"
                               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-base text-slate-900 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                    </div>

                    <div>
                        <label for="asal" class="mb-1.5 block text-sm font-semibold text-slate-700">
                            Alamat / Instansi Asal <span class="text-rose-500">*</span>
                        </label>
                        <input id="asal" name="asal" type="text" required autocomplete="organization"
                               value="{{ old('asal') }}" placeholder="Alamat rumah atau nama instansi/perusahaan"
                               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-base text-slate-900 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                    </div>

                    <div>
                        <label for="pekerjaan" class="mb-1.5 block text-sm font-semibold text-slate-700">
                            Pekerjaan / Jabatan <span class="text-rose-500">*</span>
                        </label>
                        <input id="pekerjaan" name="pekerjaan" type="text" required
                               autocomplete="organization-title"
                               value="{{ old('pekerjaan') }}" placeholder="cth: Guru, Kepala Sekolah, Wali Santri"
                               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-base text-slate-900 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                    </div>

                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700">
                            Email Aktif <span class="text-rose-500">*</span>
                        </label>
                        <input id="email" name="email" type="email" required inputmode="email"
                               autocomplete="email" autocapitalize="off" spellcheck="false"
                               value="{{ old('email') }}" placeholder="nama@email.com"
                               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-base text-slate-900 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                        <p class="mt-1.5 text-xs leading-relaxed text-slate-500">
                            Notulensi/hasil kegiatan akan kami kirimkan ke alamat ini.
                        </p>
                    </div>

                    <div>
                        <label for="telepon" class="mb-1.5 block text-sm font-semibold text-slate-700">
                            Nomor HP / WhatsApp
                            <span class="ml-1 font-normal text-slate-400">(tidak wajib)</span>
                        </label>
                        {{-- inputmode tel: papan tuts angka langsung terbuka di ponsel. --}}
                        <input id="telepon" name="telepon" type="tel" inputmode="tel"
                               autocomplete="tel" value="{{ old('telepon') }}"
                               placeholder="cth: 081234567890"
                               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-base text-slate-900 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                        <p class="mt-1.5 text-xs leading-relaxed text-slate-500">
                            Hanya dipakai bila panitia perlu menghubungi Anda terkait kegiatan ini.
                        </p>
                    </div>

                    {{-- Tanda tangan --}}
                    <div>
                        <div class="mb-1.5 flex items-end justify-between">
                            <label class="block text-sm font-semibold text-slate-700">
                                Tanda Tangan <span class="text-rose-500">*</span>
                            </label>
                            <button type="button" id="ttd-ulangi"
                                    class="text-xs font-bold text-sky-600 active:scale-95">Ulangi</button>
                        </div>
                        <div class="relative overflow-hidden rounded-xl border-2 border-dashed border-slate-300 bg-slate-50">
                            <canvas id="ttd" class="block h-[180px] w-full"></canvas>
                            <p id="ttd-petunjuk"
                               class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-slate-400">
                                Tanda tangani di sini
                            </p>
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500">Gunakan jari atau stylus pada kotak di atas.</p>
                    </div>
                </div>

                {{--
                    Pernyataan perlindungan data. Ditulis rinci karena halaman ini
                    mengumpulkan email, nomor HP, dan TANDA TANGAN — tamu berhak tahu
                    persis dipakai untuk apa sebelum menekan kirim, bukan setelahnya.
                --}}
                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3.5">
                    <p class="flex items-center gap-1.5 text-xs font-bold text-slate-700">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Data Anda Aman
                    </p>
                    <p class="mt-1.5 text-[11px] leading-relaxed text-slate-600">
                        Data yang Anda isikan disimpan secara aman pada sistem resmi
                        {{ $kegiatan->penyelenggara ?: 'Pondok Pesantren An-Nur' }} dan digunakan
                        sesuai ketentuan yang berlaku, yaitu: pencatatan daftar hadir kegiatan,
                        pengiriman notulensi/hasil kegiatan melalui email, dan keperluan
                        komunikasi panitia terkait kegiatan ini.
                    </p>
                    <p class="mt-1.5 text-[11px] leading-relaxed text-slate-600">
                        Data <span class="font-semibold">tidak diperjualbelikan</span> dan
                        <span class="font-semibold">tidak dibagikan kepada pihak lain</span>
                        di luar keperluan di atas. Daftar tamu tidak ditampilkan pada halaman
                        ini, sehingga data Anda tidak dapat dilihat tamu lain.
                    </p>
                    <p class="mt-1.5 text-[11px] leading-relaxed text-slate-500">
                        Dengan menekan tombol di bawah, Anda menyetujui ketentuan tersebut.
                    </p>
                </div>

                <button type="submit" id="tombol-kirim"
                        class="mt-4 w-full rounded-2xl bg-sky-600 px-4 py-4 text-base font-bold text-white shadow-sm transition active:scale-[0.99] disabled:opacity-60">
                    Kirim &amp; Catatkan Kehadiran
                </button>
            </form>
        </div>
    @endif

    <p class="mt-6 text-center text-[11px] text-slate-400">
        {{ $kegiatan->penyelenggara ?: 'Pondok Pesantren An-Nur' }} &middot; Buku Tamu Digital
    </p>
</div>

<script>
(function () {
    const kanvas = document.getElementById('ttd');
    if (!kanvas) return;

    const petunjuk = document.getElementById('ttd-petunjuk');
    const ladang   = document.getElementById('tanda_tangan');
    const form     = document.getElementById('form-tamu');
    const tombol   = document.getElementById('tombol-kirim');
    const ctx      = kanvas.getContext('2d');

    let menggambar = false, adaCoretan = false, titik = 0;

    // Kanvas harus mengikuti lebar layar DAN kerapatan piksel, kalau tidak
    // tanda tangan tampak kabur di ponsel ber-DPI tinggi.
    function siapkanUkuran() {
        const rasio = Math.max(window.devicePixelRatio || 1, 1);
        const kotak = kanvas.getBoundingClientRect();
        const data  = adaCoretan ? kanvas.toDataURL() : null;

        kanvas.width  = Math.round(kotak.width * rasio);
        kanvas.height = Math.round(kotak.height * rasio);
        ctx.setTransform(rasio, 0, 0, rasio, 0, 0);
        ctx.lineWidth = 2.4;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#0f172a';

        if (data) {
            const gbr = new Image();
            gbr.onload = () => ctx.drawImage(gbr, 0, 0, kotak.width, kotak.height);
            gbr.src = data;
        }
    }
    siapkanUkuran();
    window.addEventListener('resize', siapkanUkuran);
    window.addEventListener('orientationchange', siapkanUkuran);

    const posisi = (e) => {
        const k = kanvas.getBoundingClientRect();
        return { x: e.clientX - k.left, y: e.clientY - k.top };
    };

    // Pointer events: satu jalur untuk sentuh, stylus, dan tetikus.
    kanvas.addEventListener('pointerdown', (e) => {
        menggambar = true;
        kanvas.setPointerCapture(e.pointerId);
        const p = posisi(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        if (petunjuk) petunjuk.classList.add('hidden');
    });
    kanvas.addEventListener('pointermove', (e) => {
        if (!menggambar) return;
        e.preventDefault();
        const p = posisi(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        adaCoretan = true;
        titik++;
    });
    const selesai = () => { menggambar = false; };
    kanvas.addEventListener('pointerup', selesai);
    kanvas.addEventListener('pointerleave', selesai);
    kanvas.addEventListener('pointercancel', selesai);

    document.getElementById('ttd-ulangi').addEventListener('click', () => {
        ctx.clearRect(0, 0, kanvas.width, kanvas.height);
        adaCoretan = false; titik = 0;
        if (petunjuk) petunjuk.classList.remove('hidden');
    });

    // Kanvas mengikuti devicePixelRatio supaya tidak kabur di layar, tetapi
    // menyimpan apa adanya membuat berkasnya besar: 50 tanda tangan beresolusi
    // tinggi membengkakkan PDF daftar hadir sampai belasan MB. Jadi ekspornya
    // dinormalkan ke ukuran cetak yang wajar.
    const LEBAR_EKSPOR = 560, TINGGI_EKSPOR = 240;
    function ekspor() {
        const kecil = document.createElement('canvas');
        kecil.width = LEBAR_EKSPOR;
        kecil.height = TINGGI_EKSPOR;
        const k = kecil.getContext('2d');
        // Latar putih: PNG transparan tampak hitam di beberapa pembaca PDF.
        k.fillStyle = '#ffffff';
        k.fillRect(0, 0, LEBAR_EKSPOR, TINGGI_EKSPOR);
        k.drawImage(kanvas, 0, 0, LEBAR_EKSPOR, TINGGI_EKSPOR);
        return kecil.toDataURL('image/png');
    }

    form.addEventListener('submit', (e) => {
        // Satu titik atau kotak kosong bukan tanda tangan — tolak di sini supaya
        // tamu tidak perlu menunggu bolak-balik ke server.
        if (!adaCoretan || titik < 8) {
            e.preventDefault();
            alert('Mohon tanda tangani pada kotak yang tersedia.');
            return;
        }
        ladang.value = ekspor();
        tombol.disabled = true;
        tombol.textContent = 'Menyimpan…';
    });
})();
</script>
</body>
</html>

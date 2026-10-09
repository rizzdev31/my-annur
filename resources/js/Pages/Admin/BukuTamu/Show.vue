<template>
    <AdminLayout title="Buku Tamu" subtitle="Administrasi">

        <Head :title="'Buku Tamu — ' + kegiatan.nama" />

        <div class="mb-5">
            <Link :href="route('admin.smart-payroll.buku-tamu.index')"
                class="text-xs font-semibold text-gray-400 hover:text-gray-600">← Semua kegiatan</Link>
            <h2 class="text-xl font-semibold text-gray-900 mt-1">{{ kegiatan.nama }}</h2>
            <p class="text-sm text-gray-400 mt-0.5">
                {{ kegiatan.rentang }}<span v-if="kegiatan.lokasi"> · {{ kegiatan.lokasi }}</span>
            </p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
            <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3">
                <p class="text-xs text-gray-400">Tamu</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">{{ ringkasan.tamu ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3">
                <p class="text-xs text-gray-400">Email Unik</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">{{ ringkasan.email ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3">
                <p class="text-xs text-gray-400">Notulensi Terkirim</p>
                <p class="text-2xl font-bold text-emerald-700 mt-0.5">{{ ringkasan.terkirim ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-rose-100 bg-rose-50 px-4 py-3">
                <p class="text-xs text-gray-400">Gagal Kirim</p>
                <p class="text-2xl font-bold text-rose-700 mt-0.5">{{ ringkasan.gagal ?? 0 }}</p>
            </div>
        </div>

        <!-- Tautan + QR untuk ditempel di meja penerima tamu -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-800">Tautan Pengisian Tamu</p>
                    <p class="text-xs text-gray-400 mt-0.5 mb-2">
                        Bagikan tautan ini, atau cetak QR-nya dan tempel di meja penerima tamu.
                        {{ kegiatan.is_dibuka ? '' : 'Saat ini pengisian DITUTUP.' }}
                    </p>
                    <div class="flex items-center gap-2 rounded-xl bg-gray-50 px-3 py-2">
                        <code class="text-[11px] text-gray-600 truncate">{{ kegiatan.tautan }}</code>
                        <button @click="salin" class="ml-auto shrink-0 text-[11px] font-bold text-sky-600">
                            {{ tersalin ? 'Tersalin ✓' : 'Salin' }}
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-3">
                        <a :href="kegiatan.tautan" target="_blank" rel="noopener"
                            class="px-3 py-1.5 rounded-lg bg-sky-50 text-sky-700 text-xs font-semibold">Buka Halaman Tamu</a>
                        <a :href="route('admin.smart-payroll.buku-tamu.cetak', kegiatan.id)"
                            class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 text-xs font-semibold">Daftar Hadir PDF</a>
                        <button @click="cetakQr" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 text-xs font-semibold">
                            Cetak QR
                        </button>
                    </div>
                </div>
                <div class="shrink-0 text-center">
                    <div ref="qrKotak" class="rounded-xl border border-gray-200 bg-white p-2"></div>
                    <p class="text-[10px] text-gray-400 mt-1">Pindai untuk mengisi</p>
                </div>
            </div>
        </div>

        <!-- ══ NOTULENSI ══════════════════════════════════════════════════ -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-5">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Notulensi Kegiatan</p>
                    <p class="text-xs text-gray-400 mt-0.5">
                        Dikirim sebagai isi email (bukan lampiran) ke alamat yang diisi tamu, disertai tautan versi web.
                    </p>
                </div>
                <a v-if="kegiatan.tautan_notulensi" :href="kegiatan.tautan_notulensi" target="_blank" rel="noopener"
                    class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 text-xs font-semibold shrink-0">
                    Lihat versi web
                </a>
            </div>

            <!-- Server tanpa SMTP = Mail::send "berhasil" tanpa email keluar.
                 Harus terlihat SEBELUM superadmin menekan kirim. -->
            <div v-if="!emailSiap" class="mb-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3">
                <p class="text-xs font-bold text-amber-800">Pengiriman email belum aktif</p>
                <p class="text-[11px] text-amber-700 mt-0.5 leading-relaxed">{{ emailAlasan }}</p>
                <p class="text-[11px] text-amber-700 mt-1.5">
                    Naskah notulensi tetap bisa ditulis dan disimpan sekarang; tombol kirim aktif
                    setelah pengaturan email di server diisi.
                </p>
            </div>

            <div v-if="pesanGalat" class="mb-3 rounded-xl bg-rose-50 border border-rose-200 px-4 py-2.5">
                <p class="text-xs text-rose-700">{{ pesanGalat }}</p>
            </div>

            <textarea v-model="formNotulensi.notulensi" rows="12"
                placeholder="Tulis notulensi kegiatan di sini.&#10;&#10;Pisahkan antar-bagian dengan satu baris kosong — tiap bagian menjadi satu paragraf di email."
                class="w-full px-3.5 py-3 rounded-xl border border-gray-200 text-sm leading-relaxed focus:outline-none focus:border-indigo-500 font-mono"></textarea>

            <div class="flex flex-wrap items-center gap-2 mt-3">
                <button @click="simpanNotulensi" :disabled="formNotulensi.processing"
                    class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-semibold disabled:opacity-50">
                    {{ formNotulensi.processing ? 'Menyimpan…' : 'Simpan Naskah' }}
                </button>

                <span v-if="berubah" class="text-[11px] text-amber-600 font-semibold">Ada perubahan belum disimpan</span>
                <span v-else-if="kegiatan.notulensi_dikirim" class="text-[11px] text-gray-400">
                    Terakhir dikirim {{ kegiatan.notulensi_dikirim }}
                    <template v-if="kegiatan.notulensi_pengirim"> oleh {{ kegiatan.notulensi_pengirim }}</template>
                </span>
            </div>

            <!-- Pengiriman -->
            <div class="mt-5 pt-4 border-t border-gray-100">
                <p class="text-xs font-semibold text-gray-700 mb-2">Pengiriman ke Tamu</p>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
                    <div class="rounded-xl bg-gray-50 px-3 py-2">
                        <p class="text-[10px] text-gray-400">Belum dikirim</p>
                        <p class="text-lg font-bold text-gray-700">{{ ringkasan.belum ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-amber-50 px-3 py-2">
                        <p class="text-[10px] text-gray-400">Dalam antrean</p>
                        <p class="text-lg font-bold text-amber-700">{{ ringkasan.menunggu ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 px-3 py-2">
                        <p class="text-[10px] text-gray-400">Terkirim</p>
                        <p class="text-lg font-bold text-emerald-700">{{ ringkasan.terkirim ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-rose-50 px-3 py-2">
                        <p class="text-[10px] text-gray-400">Gagal</p>
                        <p class="text-lg font-bold text-rose-700">{{ ringkasan.gagal ?? 0 }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button @click="tanya('belum')" :disabled="!bolehKirim"
                        class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-semibold disabled:opacity-40">
                        Kirim ke {{ ringkasan.email ?? 0 }} Alamat Tamu
                    </button>
                    <button v-if="(ringkasan.gagal ?? 0) > 0" @click="tanya('gagal')" :disabled="!bolehKirim"
                        class="px-4 py-2 rounded-xl bg-rose-50 text-rose-700 text-xs font-semibold disabled:opacity-40">
                        Kirim Ulang {{ ringkasan.gagal }} yang Gagal
                    </button>
                    <button v-if="(ringkasan.terkirim ?? 0) > 0" @click="tanya('semua')" :disabled="!bolehKirim"
                        class="px-4 py-2 rounded-xl bg-gray-100 text-gray-600 text-xs font-semibold disabled:opacity-40">
                        Kirim Ulang ke Semua
                    </button>
                </div>

                <!-- Uji kirim: satu email ke diri sendiri sebelum menyasar puluhan tamu. -->
                <div class="mt-4 rounded-xl bg-gray-50 px-3.5 py-3">
                    <p class="text-[11px] font-semibold text-gray-600 mb-1.5">Uji kirim dulu (sangat disarankan)</p>
                    <div class="flex flex-wrap gap-2">
                        <input v-model="formUji.email" type="email" placeholder="email.anda@contoh.com"
                            class="flex-1 min-w-[200px] px-3 py-2 rounded-lg border border-gray-200 text-xs focus:outline-none focus:border-indigo-500" />
                        <button @click="kirimUji" :disabled="!emailSiap || formUji.processing || !formUji.email"
                            class="px-4 py-2 rounded-lg bg-white border border-gray-300 text-gray-700 text-xs font-semibold disabled:opacity-40">
                            {{ formUji.processing ? 'Mengirim…' : 'Kirim Uji' }}
                        </button>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1.5">
                        Dikirim langsung (tanpa antrean) supaya kesalahan pengaturan email langsung terlihat di sini.
                    </p>
                </div>
            </div>
        </div>

        <!-- Konfirmasi kirim: email ke pihak luar tidak bisa ditarik kembali. -->
        <div v-if="modeTanya" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50"
            @click="modeTanya = null">
            <div class="bg-white rounded-2xl p-5 max-w-sm w-full" @click.stop>
                <p class="text-sm font-bold text-gray-900">{{ judulTanya }}</p>
                <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">{{ pesanTanya }}</p>
                <p class="text-[11px] text-gray-400 mt-2">
                    Email yang sudah terkirim tidak dapat ditarik kembali. Pengiriman bertahap
                    ±{{ jedaKirim }} detik per email agar tidak ditolak server email.
                </p>
                <div class="flex gap-2 mt-4">
                    <button @click="modeTanya = null"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold">Batal</button>
                    <button @click="kirim" :disabled="formKirim.processing"
                        class="flex-1 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold disabled:opacity-50">
                        {{ formKirim.processing ? 'Mengantre…' : 'Kirim Sekarang' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Daftar tamu -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-gray-800">Daftar Tamu</p>
                <input v-model="cari" type="text" placeholder="Cari nama / instansi / email…"
                    class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs w-56 focus:outline-none focus:border-indigo-500" />
            </div>
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-400 uppercase w-12">No</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Nama</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">Alamat / Instansi</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-400 uppercase hidden lg:table-cell">Jabatan</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Email</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold text-gray-400 uppercase">Tanda Tangan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="t in tamuTampil" :key="t.id" class="hover:bg-gray-50/40">
                        <td class="px-4 py-3 text-center text-sm font-bold text-gray-500 tabular-nums">{{ t.nomor_urut }}</td>
                        <td class="px-3 py-3">
                            <p class="text-sm font-medium text-gray-800">{{ t.nama }}</p>
                            <p class="text-[11px] text-gray-400 md:hidden">{{ t.asal }}</p>
                        </td>
                        <td class="px-3 py-3 text-sm text-gray-600 hidden md:table-cell">{{ t.asal }}</td>
                        <td class="px-3 py-3 text-sm text-gray-600 hidden lg:table-cell">{{ t.pekerjaan }}</td>
                        <td class="px-3 py-3">
                            <p class="text-xs text-gray-600 break-all">{{ t.email }}</p>
                            <!-- Nomor langsung dapat diklik ke WhatsApp: panitia biasanya
                                 perlu menghubungi tamu, bukan menyalin nomornya. -->
                            <a v-if="t.wa_url" :href="t.wa_url" target="_blank" rel="noopener"
                                class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 hover:underline">
                                {{ t.telepon }}
                                <span class="text-[9px] font-normal text-gray-400">WhatsApp ↗</span>
                            </a>
                            <span v-if="t.email_status !== 'belum'"
                                :class="['inline-block mt-1 px-1.5 py-0.5 rounded text-[10px] font-semibold', badge(t.email_status)]">
                                {{ t.email_label }}<template v-if="t.email_terkirim"> · {{ t.email_terkirim }}</template>
                            </span>
                            <p v-if="t.email_error" class="text-[10px] text-rose-500 mt-0.5">{{ t.email_error }}</p>
                            <!-- Konfirmasi adalah bukti alamatnya hidup; tanpa itu,
                                 notulensi berisiko memantul. -->
                            <p v-if="t.konfirmasi" class="text-[10px] text-gray-400 mt-0.5">Konfirmasi terkirim ✓</p>
                            <p v-else-if="t.konfirmasi_error" class="text-[10px] text-amber-600 mt-0.5">
                                Konfirmasi gagal — alamat mungkin keliru
                            </p>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <img v-if="t.tanda_tangan" :src="t.tanda_tangan" alt="Tanda tangan"
                                class="h-10 mx-auto cursor-zoom-in" @click="ttdBesar = t" />
                            <span v-else class="text-xs text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-400 hidden md:table-cell whitespace-nowrap">{{ t.diisi_pada }}</td>
                    </tr>
                    <tr v-if="!tamuTampil.length">
                        <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">
                            {{ tamu.length ? 'Tidak ada tamu yang cocok.' : 'Belum ada tamu yang mengisi.' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Tanda tangan diperbesar -->
        <div v-if="ttdBesar" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/60"
            @click="ttdBesar = null">
            <div class="bg-white rounded-2xl p-5 max-w-sm w-full text-center" @click.stop>
                <p class="text-sm font-semibold text-gray-800">{{ ttdBesar.nama }}</p>
                <p class="text-xs text-gray-400 mb-3">{{ ttdBesar.asal }}</p>
                <img :src="ttdBesar.tanda_tangan" alt="Tanda tangan" class="w-full rounded-xl border border-gray-200" />
                <button @click="ttdBesar = null" class="mt-4 w-full py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold">Tutup</button>
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import QRCode from 'qrcode'

const props = defineProps({
    kegiatan: { type: Object, required: true },
    tamu: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
    email_siap: { type: Boolean, default: false },
    email_alasan: { type: String, default: null },
    jeda_kirim: { type: Number, default: 6 },
})

const emailSiap = computed(() => props.email_siap)
const emailAlasan = computed(() => props.email_alasan)
const jedaKirim = computed(() => props.jeda_kirim)

// ── Notulensi ───────────────────────────────────────────────────────────────
const formNotulensi = useForm({ notulensi: props.kegiatan.notulensi || '' })
const formKirim = useForm({ mode: 'belum' })
const formUji = useForm({ email: '' })
const modeTanya = ref(null)

const page = usePage()
const pesanGalat = computed(() => {
    const e = page.props.errors || {}
    return e.notulensi || e.uji_email || null
})

/** Naskah di layar berbeda dari yang tersimpan → jangan kirim versi lama. */
const berubah = computed(() =>
    (formNotulensi.notulensi || '').trim() !== (props.kegiatan.notulensi || '').trim())

const bolehKirim = computed(() =>
    emailSiap.value && !berubah.value && !!(props.kegiatan.notulensi || '').trim() && !formKirim.processing)

const judulTanya = computed(() => ({
    belum: 'Kirim notulensi ke tamu?',
    gagal: 'Kirim ulang yang gagal?',
    semua: 'Kirim ulang ke SEMUA tamu?',
}[modeTanya.value] || ''))

const pesanTanya = computed(() => ({
    belum: `Notulensi akan dikirim ke ${props.ringkasan.email ?? 0} alamat email tamu yang belum menerimanya.`,
    gagal: `Pengiriman diulang hanya untuk ${props.ringkasan.gagal ?? 0} tamu yang sebelumnya gagal.`,
    semua: `Seluruh tamu (${props.ringkasan.email ?? 0} alamat) akan menerima notulensi LAGI, termasuk yang sudah menerima. Gunakan ini hanya bila naskahnya direvisi.`,
}[modeTanya.value] || ''))

function simpanNotulensi() {
    formNotulensi.put(route('admin.smart-payroll.buku-tamu.notulensi', props.kegiatan.id), {
        preserveScroll: true,
    })
}

function tanya(mode) {
    modeTanya.value = mode
}

function kirim() {
    formKirim.mode = modeTanya.value
    formKirim.post(route('admin.smart-payroll.buku-tamu.notulensi.kirim', props.kegiatan.id), {
        preserveScroll: true,
        onFinish: () => modeTanya.value = null,
    })
}

function kirimUji() {
    formUji.post(route('admin.smart-payroll.buku-tamu.notulensi.uji', props.kegiatan.id), {
        preserveScroll: true,
    })
}

const cari = ref('')
const tersalin = ref(false)
const ttdBesar = ref(null)
const qrKotak = ref(null)
const qrDataUrl = ref('')

const tamuTampil = computed(() => {
    const q = cari.value.trim().toLowerCase()
    if (!q) return props.tamu
    return props.tamu.filter(t =>
        t.nama.toLowerCase().includes(q)
        || (t.asal || '').toLowerCase().includes(q)
        || (t.email || '').toLowerCase().includes(q))
})

const badge = (s) => ({
    terkirim: 'bg-emerald-50 text-emerald-700',
    gagal: 'bg-rose-50 text-rose-700',
    menunggu: 'bg-amber-50 text-amber-700',
    duplikat: 'bg-violet-50 text-violet-700',
}[s] ?? 'bg-gray-100 text-gray-600')

// QR dibuat di peramban — tidak perlu dependensi di server.
onMounted(async () => {
    try {
        qrDataUrl.value = await QRCode.toDataURL(props.kegiatan.tautan, { width: 320, margin: 1 })
        if (qrKotak.value) {
            const img = document.createElement('img')
            img.src = qrDataUrl.value
            img.alt = 'QR buku tamu'
            img.className = 'h-28 w-28'
            qrKotak.value.appendChild(img)
        }
    } catch (_) { /* QR gagal dibuat — tautan tetap bisa dibagikan manual */ }
})

async function salin() {
    try {
        await navigator.clipboard.writeText(props.kegiatan.tautan)
        tersalin.value = true
        setTimeout(() => tersalin.value = false, 2000)
    } catch (_) {
        window.prompt('Salin tautan ini:', props.kegiatan.tautan)
    }
}

/** Halaman cetak QR sederhana: judul kegiatan + QR besar + tautan. */
function cetakQr() {
    const w = window.open('', '_blank')
    if (!w) return
    w.document.write(`
        <html><head><title>QR Buku Tamu</title>
        <style>
            body{font-family:system-ui,sans-serif;text-align:center;padding:40px}
            h1{font-size:22px;margin:0 0 4px}
            p{color:#555;margin:2px 0}
            img{width:320px;height:320px;margin:24px 0}
            code{font-size:12px;color:#444}
        </style></head><body>
        <p style="letter-spacing:2px;font-size:11px;color:#777">${props.kegiatan.penyelenggara || 'BUKU TAMU'}</p>
        <h1>${props.kegiatan.nama}</h1>
        <p>${props.kegiatan.rentang}</p>
        <img src="${qrDataUrl.value}" alt="QR">
        <p><b>Pindai untuk mengisi buku tamu</b></p>
        <code>${props.kegiatan.tautan}</code>
        </body></html>`)
    w.document.close()
    w.focus()
    setTimeout(() => w.print(), 400)
}
</script>

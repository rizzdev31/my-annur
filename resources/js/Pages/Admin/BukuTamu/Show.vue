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
                            <span v-if="t.email_status !== 'belum'"
                                :class="['inline-block mt-1 px-1.5 py-0.5 rounded text-[10px] font-semibold', badge(t.email_status)]">
                                {{ labelEmail(t.email_status) }}
                            </span>
                            <p v-if="t.email_error" class="text-[10px] text-rose-500 mt-0.5">{{ t.email_error }}</p>
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
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import QRCode from 'qrcode'

const props = defineProps({
    kegiatan: { type: Object, required: true },
    tamu: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
})

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

const labelEmail = (s) => ({ menunggu: 'Menunggu kirim', terkirim: 'Terkirim', gagal: 'Gagal' }[s] ?? s)
const badge = (s) => ({
    terkirim: 'bg-emerald-50 text-emerald-700',
    gagal: 'bg-rose-50 text-rose-700',
    menunggu: 'bg-amber-50 text-amber-700',
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

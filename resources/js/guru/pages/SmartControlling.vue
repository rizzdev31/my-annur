<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import api from '../api'
import PageHeader from '../components/PageHeader.vue'

const info = ref(null)
const loading = ref(true)
const error = ref('')
const kegiatanSel = ref(null)
const nip = ref('')
const scanning = ref(false)
const msg = ref(null)
const recent = ref([])   // riwayat scan sesi ini

const kegiatan = computed(() => info.value?.kegiatan ?? [])
// Kamera tersedia bila getUserMedia ada (secure context). Pembaca QR: BarcodeDetector
// (Android/Chrome) ATAU jsQR (fallback iOS/Firefox, dimuat lazy saat kamera dibuka).
const bisaKamera = typeof window !== 'undefined' && !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)
const statusColor = (s) => ({ hadir: 'text-emerald-600 bg-emerald-50', telat: 'text-amber-600 bg-amber-50', alpha: 'text-red-500 bg-red-50' }[s] || 'text-gray-500 bg-gray-100')

function pilihDefault(list) {
    const keep = list.find((k) => k.jadwal_id === kegiatanSel.value?.jadwal_id)
    kegiatanSel.value = keep || list.find((k) => k.fase === 'hadir') || list[0] || null
}

async function load() {
    loading.value = true; error.value = ''
    try {
        const res = await api.get('/smart-habbit/controlling/aktif')
        info.value = res.data.data ?? res.data
        pilihDefault(kegiatan.value)
    } catch (e) { error.value = e.response?.data?.message || 'Gagal memuat kegiatan.' }
    finally { loading.value = false }
}
// Refresh senyap berkala: window kegiatan berubah seiring waktu (mulai→telat→tutup),
// jadi panel disegarkan otomatis agar petugas bisa scan satu sesi penuh. Meniru Flutter.
async function refreshAktif() {
    try {
        const res = await api.get('/smart-habbit/controlling/aktif')
        info.value = res.data.data ?? res.data
        pilihDefault(kegiatan.value)
    } catch (_) {/* diamkan */}
}
let refreshTimer = null
onMounted(() => { load(); refreshTimer = setInterval(refreshAktif, 30000) })

async function catat(nipVal, fromCam = false) {
    const n = (nipVal ?? nip.value).trim()
    if (!n) { msg.value = { ok: false, text: 'Masukkan / scan NIP santri.' }; return }
    scanning.value = true
    if (!fromCam) msg.value = null
    try {
        const res = await api.post('/smart-habbit/controlling/scan', {
            nip: n, kegiatan_id: kegiatanSel.value?.kegiatan_id ?? null,
        })
        const dt = res.data.data || {}
        const nama = dt.santri?.nama || dt.nama || n
        const entry = { nip: n, status: dt.status, nama, sudah: dt.sudah_absen, waktu: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }
        recent.value.unshift(entry)
        lastScan.value = { ...entry, message: res.data.message, ok: true }
        if (!fromCam) { msg.value = { ok: true, text: res.data.message || 'Tercatat.' }; nip.value = '' }
    } catch (e) {
        const text = e.response?.data?.message || 'Gagal mencatat.'
        if (fromCam) lastScan.value = { status: 'error', message: text, ok: false }
        else msg.value = { ok: false, text }
    } finally { scanning.value = false }
}

// ── Kamera QR/barcode (progressive enhancement, scan beruntun) ────────────────
const showCam = ref(false)
const videoEl = ref(null)
const canvasEl = ref(null)
const lastScan = ref(null)          // hasil scan terakhir (overlay kamera)
let stream = null, detector = null, jsQRfn = null, raf = null
let lastVal = null, lastValTime = 0 // dedupe NIP sama dalam jeda singkat

async function bukaKamera() {
    showCam.value = true; msg.value = null; lastScan.value = null
    try {
        if ('BarcodeDetector' in window) {
            // eslint-disable-next-line no-undef
            detector = new BarcodeDetector({ formats: ['qr_code', 'code_128', 'ean_13', 'code_39'] })
        } else {
            jsQRfn = (await import('jsqr')).default   // hanya dimuat saat kamera dibuka
        }
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        await new Promise((r) => setTimeout(r, 60))
        if (videoEl.value) { videoEl.value.srcObject = stream; await videoEl.value.play() }
        loopDetect()
    } catch (e) {
        tutupKamera()
        msg.value = { ok: false, text: 'Kamera tidak bisa dibuka. Gunakan input manual.' }
    }
}
async function loopDetect() {
    if (!showCam.value || !videoEl.value) return
    let val = null
    try {
        if (detector) {
            const codes = await detector.detect(videoEl.value)
            if (codes && codes.length) val = (codes[0].rawValue || '').trim()
        } else if (jsQRfn && canvasEl.value && videoEl.value.videoWidth) {
            const v = videoEl.value, c = canvasEl.value
            c.width = v.videoWidth; c.height = v.videoHeight
            const ctx = c.getContext('2d', { willReadFrequently: true })
            ctx.drawImage(v, 0, 0, c.width, c.height)
            const img = ctx.getImageData(0, 0, c.width, c.height)
            const code = jsQRfn(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' })
            if (code) val = (code.data || '').trim()
        }
    } catch (_) {/* frame belum siap */}
    if (val) {
        const t = Date.now()
        // Kamera tetap terbuka: scan beruntun, tapi abaikan NIP sama dalam 2.5 detik.
        if (!(val === lastVal && t - lastValTime < 2500) && !scanning.value) {
            lastVal = val; lastValTime = t
            await catat(val, true)
        }
    }
    raf = requestAnimationFrame(loopDetect)
}
function tutupKamera() {
    showCam.value = false
    if (raf) { cancelAnimationFrame(raf); raf = null }
    detector = null; jsQRfn = null; lastVal = null
    if (stream) { stream.getTracks().forEach((t) => t.stop()); stream = null }
}
onUnmounted(() => { tutupKamera(); if (refreshTimer) clearInterval(refreshTimer) })
</script>

<template>
    <div>
        <PageHeader title="Smart Controlling" />

        <div v-if="loading" class="pt-10 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>
        <div v-else-if="error" class="pt-8 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button @click="load" class="mt-3 px-4 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold">Coba lagi</button>
        </div>

        <template v-else>
            <p v-if="msg" :class="msg.ok ? 'text-emerald-700 bg-emerald-50' : 'text-red-600 bg-red-50'" class="text-sm rounded-xl px-3 py-2 mb-3">{{ msg.text }}</p>

            <div v-if="!kegiatan.length" class="pt-12 text-center text-sm text-gray-400">Tidak ada kegiatan controlling aktif saat ini.</div>

            <template v-else>
                <p class="text-[11px] text-gray-400 mb-2">{{ info.periode }} · {{ info.tanggal }} — pilih kegiatan lalu scan/masukkan NIP santri.</p>

                <!-- Pilih kegiatan -->
                <div class="flex flex-wrap gap-2 mb-4">
                    <button v-for="k in kegiatan" :key="k.jadwal_id" @click="kegiatanSel = k"
                        class="text-left px-3 py-2 rounded-xl border transition" :class="kegiatanSel?.jadwal_id === k.jadwal_id ? 'border-[#0C78FF] bg-[#0C78FF]/5' : 'border-gray-200 bg-white'">
                        <p class="text-[13px] font-bold text-gray-800">{{ k.nama }}</p>
                        <p class="text-[10px] text-gray-400">{{ k.jam_mulai }}–{{ k.jam_selesai }} · <span :class="k.fase === 'hadir' ? 'text-emerald-600' : 'text-amber-600'">{{ k.fase === 'hadir' ? 'fase hadir' : 'fase telat' }}</span> · tutup {{ k.jam_tutup }}</p>
                    </button>
                </div>

                <!-- Peringatan fase telat -->
                <div v-if="kegiatanSel?.fase === 'telat'" class="rounded-xl bg-amber-50 border border-amber-100 text-amber-700 text-[12px] px-3 py-2 mb-3">
                    ⚠ Masa toleransi — scan sekarang akan tercatat <b>TELAT</b> (tutup {{ kegiatanSel.jam_tutup }}).
                </div>

                <!-- Input NIP + kamera -->
                <div class="rounded-2xl bg-white border border-gray-100 p-4 mb-4">
                    <label class="block text-[11px] font-medium text-gray-600 mb-1">NIP Santri</label>
                    <div class="flex gap-2">
                        <input v-model="nip" type="text" inputmode="numeric" placeholder="ketik / scan NIP" @keyup.enter="catat()"
                            class="flex-1 px-3 py-3 rounded-xl border border-gray-200 text-sm outline-none focus:border-[#0C78FF]" />
                        <button v-if="bisaKamera" @click="bukaKamera" class="px-3 rounded-xl bg-gray-100 text-gray-600" title="Scan kamera">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7V5a1 1 0 011-1h2m0 16H5a1 1 0 01-1-1v-2m16 0v2a1 1 0 01-1 1h-2M17 4h2a1 1 0 011 1v2M8 12h8"/></svg>
                        </button>
                    </div>
                    <button @click="catat()" :disabled="scanning" class="w-full mt-3 py-3 rounded-xl bg-[#0C78FF] text-white font-bold text-sm disabled:opacity-60">
                        {{ scanning ? 'Mencatat…' : 'Catat Kehadiran' }}
                    </button>
                    <p v-if="!bisaKamera" class="text-[10px] text-gray-400 mt-2 text-center">Scan kamera tak didukung browser ini — gunakan input manual.</p>
                </div>

                <!-- Riwayat scan sesi ini -->
                <div v-if="recent.length">
                    <h2 class="text-sm font-bold text-gray-800 mb-2">Baru Dicatat ({{ recent.length }})</h2>
                    <ul class="space-y-2">
                        <li v-for="(r, i) in recent" :key="i" class="flex items-center justify-between bg-white rounded-xl border border-gray-100 px-3 py-2">
                            <div class="min-w-0"><p class="text-sm font-semibold text-gray-800 truncate">{{ r.nama || r.nip }}</p><p class="text-[10px] text-gray-400">{{ r.nip }} · {{ r.waktu }}<span v-if="r.sudah"> · sudah absen</span></p></div>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full capitalize" :class="statusColor(r.status)">{{ r.status }}</span>
                        </li>
                    </ul>
                </div>
            </template>
        </template>

        <!-- Modal kamera (scan beruntun) -->
        <Transition name="pop">
            <div v-if="showCam" class="fixed inset-0 z-[80] bg-black flex flex-col">
                <div class="safe-t flex items-center justify-between px-4 h-14 text-white">
                    <span class="font-bold text-sm">{{ kegiatanSel?.nama || 'Scan santri' }}</span>
                    <button @click="tutupKamera" class="w-9 h-9 rounded-full bg-white/15 grid place-items-center"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg></button>
                </div>
                <div class="flex-1 relative">
                    <video ref="videoEl" playsinline muted autoplay class="absolute inset-0 w-full h-full object-cover"></video>
                    <canvas ref="canvasEl" class="hidden"></canvas>
                    <div class="absolute inset-0 grid place-items-center pointer-events-none">
                        <div class="w-56 h-56 border-4 border-white/80 rounded-3xl"></div>
                    </div>

                    <!-- Overlay hasil scan terakhir -->
                    <div v-if="lastScan" class="absolute bottom-6 inset-x-4">
                        <div class="rounded-2xl px-4 py-3 flex items-center gap-3 shadow-lg" :class="lastScan.ok ? 'bg-white' : 'bg-red-50'">
                            <div class="w-9 h-9 rounded-full grid place-items-center shrink-0" :class="lastScan.ok ? statusColor(lastScan.status) : 'text-red-500 bg-red-100'">
                                <svg v-if="lastScan.ok" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-gray-800 truncate">{{ lastScan.ok ? (lastScan.nama || lastScan.nip) : 'Gagal' }}</p>
                                <p class="text-[11px] text-gray-500 truncate">{{ lastScan.message }}</p>
                            </div>
                            <span v-if="lastScan.ok" class="text-[10px] font-bold px-2 py-0.5 rounded-full capitalize shrink-0" :class="statusColor(lastScan.status)">{{ lastScan.status }}</span>
                        </div>
                    </div>
                    <p v-else class="absolute bottom-6 inset-x-0 text-center text-white/70 text-xs">Menyorot QR/barcode NIP santri… (scan beruntun)</p>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.pop-enter-active, .pop-leave-active { transition: opacity .2s ease; }
.pop-enter-from, .pop-leave-to { opacity: 0; }
</style>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import api from '../api'

const d = ref(null)
const loading = ref(true)
const busy = ref('')          // 'masuk' | 'pulang'
const msg = ref(null)
const now = ref(new Date())
let timer = null

import { tanggalLokal } from '../tanggal'
const todayStr = () => tanggalLokal()
const jamStr = computed(() => now.value.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }))

// ── State turunan (pakai field otoritatif dari server) ──────────────────────
const izin  = computed(() => d.value?.izin_aktif?.ada ? d.value.izin_aktif : null)
const libur = computed(() => d.value?.hari_libur?.ada ? d.value.hari_libur : null)
const rec   = computed(() => d.value?.absensi || {})
const sudahCheckin  = computed(() => !!d.value?.sudah_checkin)
const sudahCheckout = computed(() => !!d.value?.sudah_checkout)
const bolehCheckin  = computed(() => !!d.value?.boleh_checkin)
const bolehCheckout = computed(() => !!d.value?.boleh_checkout)

// Status ringkas untuk hero
const hero = computed(() => {
    if (!d.value) return { text: '—', tone: 'default' }
    if (sudahCheckout.value) return { text: 'Absensi hari ini selesai', tone: 'done' }
    if (izin.value)  return { text: `Sedang ${izin.value.jenis}`, tone: 'izin' }
    if (libur.value && !libur.value.opsional) return { text: `Libur — ${libur.value.nama}`, tone: 'libur' }
    if (sudahCheckin.value) return { text: 'Sudah masuk, menunggu jam pulang', tone: 'in' }
    if (bolehCheckin.value) return { text: 'Belum absen masuk', tone: 'pending' }
    if (d.value.menit_menunggu_checkin > 0) return { text: `Absen dibuka pukul ${d.value.bisa_checkin_mulai || '—'}`, tone: 'wait' }
    return { text: 'Absen belum tersedia', tone: 'default' }
})

async function load() {
    loading.value = true
    try {
        const res = await api.get('/absensi/hari-ini', { params: { device_date: todayStr() } })
        d.value = res.data.data ?? res.data
    } catch (e) {
        msg.value = { ok: false, text: e.response?.data?.message || 'Gagal memuat absensi.' }
    } finally { loading.value = false }
}
onMounted(() => { load(); timer = setInterval(() => (now.value = new Date()), 1000) })
onUnmounted(() => clearInterval(timer))

// ── Aksi absen (sheet konfirmasi) ───────────────────────────────────────────
const sheet = ref(null)        // 'masuk' | 'pulang'
const foto = ref(null)
const fotoPreview = ref(null)
const gps = ref('idle')        // idle | getting | ok | fail
const pos = ref(null)
const alasanPulang = ref('')
const perluAlasan = ref(false)   // true bila server minta alasan (di luar lokasi)
const sheetErr = ref('')

function bukaSheet(tipe) {
    sheet.value = tipe; foto.value = null; fotoPreview.value = null; pos.value = null; gps.value = 'getting'; msg.value = null
    alasanPulang.value = ''; perluAlasan.value = false; sheetErr.value = ''
    mintaLokasi()
}
function mintaLokasi() {
    if (!navigator.geolocation) { gps.value = 'fail'; return }
    gps.value = 'getting'
    navigator.geolocation.getCurrentPosition(
        (p) => { pos.value = { lat: p.coords.latitude, lng: p.coords.longitude }; gps.value = 'ok' },
        () => { gps.value = 'fail' },
        { enableHighAccuracy: true, timeout: 8000 },
    )
}
function pilihFoto(e) { const f = e.target.files?.[0]; foto.value = f || null; fotoPreview.value = f ? URL.createObjectURL(f) : null }

async function konfirmasi() {
    const tipe = sheet.value
    busy.value = tipe
    sheetErr.value = ''
    try {
        const fd = new FormData()
        fd.append('device_date', todayStr())
        if (pos.value) { fd.append('latitude', pos.value.lat); fd.append('longitude', pos.value.lng) }
        if (foto.value) fd.append('foto', foto.value)
        if (tipe === 'pulang' && alasanPulang.value.trim()) fd.append('alasan_pulang', alasanPulang.value.trim())
        const url = tipe === 'masuk' ? '/absensi/check-in' : '/absensi/check-out'
        const res = await api.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
        sheet.value = null
        msg.value = { ok: true, text: res.data.message || 'Absensi tersimpan.' }
        await load()
    } catch (e) {
        const code = e.response?.data?.code
        const text = e.response?.data?.message || 'Gagal menyimpan absensi.'
        // Di luar lokasi saat pulang → minta alasan, JANGAN tutup sheet
        if (code === 'BUTUH_ALASAN_PULANG') {
            perluAlasan.value = true
            sheetErr.value = text
        } else if (code === 'LOKASI_WAJIB_CHECKIN' || code === 'TOO_EARLY_CHECKIN') {
            // Lokasi wajib / terlalu awal → biarkan sheet terbuka agar bisa perbaiki
            sheetErr.value = text
        } else {
            msg.value = { ok: false, text }
            sheet.value = null
        }
    } finally { busy.value = '' }
}
</script>

<template>
    <div>
        <h1 class="text-xl font-extrabold text-gray-900 mb-4">Absensi Harian</h1>

        <div v-if="loading" class="pt-16 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>

        <template v-else-if="d">
            <!-- HERO: jam live + status -->
            <div class="relative overflow-hidden rounded-[28px] p-6 text-white text-center"
                :class="{
                    'bg-gradient-to-br from-[#06346B] to-[#0C78FF]': ['pending','in','default','wait'].includes(hero.tone),
                    'bg-gradient-to-br from-emerald-500 to-emerald-700': hero.tone === 'done',
                    'bg-gradient-to-br from-amber-500 to-orange-600': hero.tone === 'izin',
                    'bg-gradient-to-br from-slate-500 to-slate-700': hero.tone === 'libur',
                }">
                <div class="absolute -right-8 -top-8 w-32 h-32 rounded-full bg-white/10"></div>
                <div class="absolute -left-6 -bottom-10 w-28 h-28 rounded-full bg-white/10"></div>
                <p class="relative text-xs opacity-80">{{ d.hari }}, {{ d.tanggal }}</p>
                <p class="relative text-5xl font-extrabold tracking-tight mt-2 tabular-nums">{{ jamStr }}</p>
                <p class="relative inline-block mt-3 px-4 py-1.5 rounded-full bg-white/20 text-sm font-semibold">{{ hero.text }}</p>
            </div>

            <p v-if="msg" :class="msg.ok ? 'text-emerald-700 bg-emerald-50' : 'text-red-600 bg-red-50'"
                class="text-sm rounded-xl px-3 py-2 mt-4">{{ msg.text }}</p>

            <!-- Banner izin -->
            <div v-if="izin" class="mt-4 rounded-2xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3">
                <div class="w-9 h-9 rounded-xl bg-amber-100 grid place-items-center shrink-0">
                    <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M5 21h14a2 2 0 002-2V7l-5-4H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-amber-800">Anda sedang {{ izin.jenis }}</p>
                    <p class="text-xs text-amber-600 mt-0.5">{{ izin.tanggal_mulai }} — {{ izin.tanggal_selesai }}</p>
                    <p class="text-[11px] text-amber-500 mt-1">Tidak perlu absen selama periode {{ izin.kategori }}.</p>
                </div>
            </div>

            <!-- Banner libur -->
            <div v-else-if="libur && !libur.opsional" class="mt-4 rounded-2xl bg-slate-50 border border-slate-200 p-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-slate-100 grid place-items-center shrink-0">
                    <svg class="w-5 h-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-700">Hari Libur</p>
                    <p class="text-xs text-slate-500">{{ libur.nama }}</p>
                </div>
            </div>

            <!-- Kartu jam masuk/pulang -->
            <div class="grid grid-cols-2 gap-3 mt-4">
                <div class="rounded-2xl bg-white border border-gray-100 p-4">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <p class="text-[11px] text-gray-400 font-medium">Masuk</p>
                    </div>
                    <p class="text-2xl font-extrabold text-gray-800 tabular-nums">{{ rec.jam_masuk || '--:--' }}</p>
                    <p v-if="d.jadwal_masuk" class="text-[10px] text-gray-300 mt-0.5">Jadwal {{ d.jadwal_masuk }}</p>
                </div>
                <div class="rounded-2xl bg-white border border-gray-100 p-4">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-2 h-2 rounded-full bg-[#0C78FF]"></span>
                        <p class="text-[11px] text-gray-400 font-medium">Pulang</p>
                    </div>
                    <p class="text-2xl font-extrabold text-gray-800 tabular-nums">{{ rec.jam_pulang || '--:--' }}</p>
                    <p v-if="d.jadwal_pulang" class="text-[10px] text-gray-300 mt-0.5">Jadwal {{ d.jadwal_pulang }}</p>
                </div>
            </div>

            <!-- AKSI -->
            <div class="mt-5">
                <button v-if="bolehCheckin" @click="bukaSheet('masuk')"
                    class="w-full py-4 rounded-2xl bg-emerald-600 text-white font-bold active:scale-[0.99] transition shadow-lg shadow-emerald-600/20">
                    Absen Masuk
                </button>
                <button v-else-if="sudahCheckin && bolehCheckout" @click="bukaSheet('pulang')"
                    class="w-full py-4 rounded-2xl bg-[#0C78FF] text-white font-bold active:scale-[0.99] transition shadow-lg shadow-[#0C78FF]/20">
                    Absen Pulang
                </button>
                <div v-else-if="sudahCheckout" class="w-full py-4 rounded-2xl bg-emerald-50 text-emerald-700 font-bold text-center">
                    ✓ Absensi hari ini selesai
                </div>
                <div v-else-if="d.menit_menunggu_checkin > 0" class="w-full py-4 rounded-2xl bg-gray-100 text-gray-500 font-semibold text-center text-sm">
                    Absen masuk dibuka pukul {{ d.bisa_checkin_mulai }}
                </div>
                <div v-else-if="sudahCheckin && d.menit_menunggu_checkout > 0" class="w-full py-4 rounded-2xl bg-gray-100 text-gray-500 font-semibold text-center text-sm">
                    Absen pulang dibuka pukul {{ d.bisa_checkout_mulai }}
                </div>
                <div v-else-if="izin" class="w-full py-4 rounded-2xl bg-amber-50 text-amber-700 font-semibold text-center text-sm">
                    Sedang {{ izin.jenis }} — tidak perlu absen
                </div>
                <div v-else class="w-full py-4 rounded-2xl bg-gray-100 text-gray-400 font-semibold text-center text-sm">
                    Absen belum tersedia
                </div>

                <p class="text-[11px] text-gray-400 text-center mt-3 px-6">
                    Saat absen, izinkan <b>lokasi</b> (& kamera bila ingin foto). Foto bersifat opsional.
                </p>
            </div>
        </template>

        <!-- Sheet konfirmasi -->
        <Transition name="pop">
            <div v-if="sheet" class="fixed inset-0 z-[70] flex items-end justify-center" style="background: rgba(0,0,0,0.55)">
                <div class="w-full max-w-md bg-white rounded-t-3xl p-5 pb-8 safe-b">
                    <div class="w-10 h-1 bg-gray-200 rounded-full mx-auto mb-4"></div>
                    <h3 class="text-base font-extrabold text-gray-900">Absen {{ sheet === 'masuk' ? 'Masuk' : 'Pulang' }}</h3>
                    <p class="text-3xl font-extrabold text-[#06346B] tabular-nums mt-1">{{ jamStr }}</p>

                    <!-- Status lokasi -->
                    <div class="mt-4 flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4" :class="gps === 'ok' ? 'text-emerald-500' : gps === 'fail' ? 'text-red-400' : 'text-gray-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span :class="gps === 'fail' && sheet === 'masuk' ? 'text-red-500 font-medium' : 'text-gray-500'">
                            {{ gps === 'getting' ? 'Mengambil lokasi…'
                                : gps === 'ok' ? 'Lokasi terkunci'
                                : sheet === 'masuk' ? 'Lokasi wajib untuk masuk — aktifkan GPS'
                                : 'Lokasi tidak tersedia (tetap bisa lanjut)' }}
                        </span>
                        <button v-if="gps === 'fail'" @click="mintaLokasi" type="button"
                            class="ml-auto text-xs font-semibold text-[#0C78FF] underline">Coba lagi</button>
                    </div>
                    <p v-if="gps === 'fail' && sheet === 'masuk'" class="mt-1 text-[11px] text-gray-400 leading-snug">
                        Jika prompt lokasi tidak muncul, aktifkan izin lokasi untuk situs ini di pengaturan browser, lalu ketuk "Coba lagi".
                    </p>

                    <!-- Foto opsional -->
                    <div v-if="fotoPreview" class="mt-3"><img :src="fotoPreview" class="w-full h-40 object-cover rounded-xl" /></div>
                    <input type="file" accept="image/*" capture="user" @change="pilihFoto"
                        class="mt-3 block w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-[#0C78FF]/10 file:text-[#0C78FF] file:text-xs file:font-semibold" />

                    <!-- Alasan (khusus pulang; wajib bila di luar lokasi) -->
                    <div v-if="sheet === 'pulang'" class="mt-3">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">
                            Alasan <span class="text-gray-400 font-normal">(wajib bila di luar lokasi pesantren)</span>
                        </label>
                        <textarea v-model="alasanPulang" rows="2" placeholder="cth: sudah pulang duluan, lupa absen"
                            :class="['w-full rounded-xl border px-3 py-2 text-sm focus:outline-none focus:ring-2',
                                perluAlasan ? 'border-red-300 focus:ring-red-100' : 'border-gray-200 focus:ring-[#0C78FF]/20']"></textarea>
                        <p v-if="sheetErr" class="text-xs text-red-500 mt-1">{{ sheetErr }}</p>
                    </div>

                    <!-- Error check-in (mis. di luar lokasi / GPS ditolak) -->
                    <p v-if="sheet === 'masuk' && sheetErr" class="mt-3 text-xs text-red-500">{{ sheetErr }}</p>

                    <div class="flex gap-3 mt-5">
                        <button @click="sheet = null" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-600 font-semibold text-sm">Batal</button>
                        <button @click="konfirmasi" :disabled="busy || gps === 'getting' || (sheet === 'masuk' && gps !== 'ok')"
                            class="flex-1 py-3 rounded-xl text-white font-bold text-sm disabled:opacity-60"
                            :class="sheet === 'masuk' ? 'bg-emerald-600' : 'bg-[#0C78FF]'">
                            {{ busy ? 'Menyimpan…' : (sheet === 'masuk' && gps === 'fail') ? 'Menunggu lokasi…' : 'Konfirmasi' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.pop-enter-active, .pop-leave-active { transition: opacity .2s ease; }
.pop-enter-from, .pop-leave-to { opacity: 0; }
</style>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
import { auth } from '../store/auth'
import BottomSheet from '../components/BottomSheet.vue'

const router = useRouter()
const profil = ref(null)
const loading = ref(true)
const konfirmKeluar = ref(false)
const keluarBusy = ref(false)

// Hari libur mingguan (ajukan ke admin)
const hariOpsi = [
    { key: 'senin', label: 'Sen' }, { key: 'selasa', label: 'Sel' }, { key: 'rabu', label: 'Rab' },
    { key: 'kamis', label: 'Kam' }, { key: 'jumat', label: 'Jum' }, { key: 'sabtu', label: 'Sab' }, { key: 'ahad', label: 'Ahd' },
]
const labelHari = (k) => hariOpsi.find(h => h.key === k)?.label ?? k
const liburSel = ref([])
const liburSaving = ref(false)
const liburMsg = ref(null)

function toggleLibur(k) {
    const i = liburSel.value.indexOf(k)
    if (i === -1) liburSel.value.push(k); else liburSel.value.splice(i, 1)
}
async function ajukanLibur() {
    liburSaving.value = true; liburMsg.value = null
    try {
        await api.post('/profile/hari-libur', { hari_libur: liburSel.value })
        liburMsg.value = { ok: true, text: 'Usulan terkirim. Menunggu persetujuan admin.' }
        await load()
    } catch (e) {
        liburMsg.value = { ok: false, text: e.response?.data?.message || 'Gagal mengirim usulan.' }
    } finally { liburSaving.value = false }
}

async function load() {
    loading.value = true
    try {
        const res = await api.get('/profile')
        profil.value = res.data.data ?? res.data
        auth.user = profil.value
        liburSel.value = [...(profil.value.hari_libur_diajukan ?? profil.value.hari_libur ?? [])]
    } catch (_) {/* diamkan */} finally {
        loading.value = false
    }
}
onMounted(load)

async function keluar() {
    keluarBusy.value = true
    await auth.logout()
    router.replace({ name: 'login' })
}

const initials = computed(() => (profil.value?.name || 'AN').split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase())
const gender = (g) => ({ L: 'Laki-laki', P: 'Perempuan' }[g] || null)
const tgl = (d) => {
    if (!d) return null
    try { return new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }
    catch { return d }
}
const statusBadge = computed(() => {
    const s = (profil.value?.status_kepegawaian || 'aktif').toLowerCase()
    return s === 'aktif' ? 'bg-emerald-400/20 text-emerald-100 border-emerald-300/30' : 'bg-white/15 text-white/80 border-white/20'
})

// Ikon per baris (path SVG stroke)
const I = {
    email: 'M3 8l7.89 4.26a2 2 0 001.94 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    phone: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.95.68l1.5 4.5a1 1 0 01-.5 1.21l-2.26 1.13a11 11 0 005.52 5.52l1.13-2.26a1 1 0 011.21-.5l4.5 1.5a1 1 0 01.68.95V19a2 2 0 01-2 2h-1C9.72 21 3 14.28 3 6V5z',
    home: 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3v-6h6v6h3a1 1 0 001-1V10',
    id: 'M3 5h18M3 5a2 2 0 00-2 2v10a2 2 0 002 2h18a2 2 0 002-2V7a2 2 0 00-2-2M7 9h4m-4 4h6',
    cake: 'M12 3v4m0 0a2 2 0 100 0zM4 21v-6a2 2 0 012-2h12a2 2 0 012 2v6M4 15c2 0 2 1.5 4 1.5s2-1.5 4-1.5 2 1.5 4 1.5 2-1.5 4-1.5',
    user: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    edu: 'M12 14l9-5-9-5-9 5 9 5zm0 0v7m6-9v4.5c0 1-2.7 2.5-6 2.5s-6-1.5-6-2.5V10',
    badge: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    briefcase: 'M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2m-9 0h14a1 1 0 011 1v10a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1z',
    calendar: 'M8 7V3m8 4V3M4 11h16M5 7h14a1 1 0 011 1v11a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1z',
    bank: 'M4 10h16M4 10l8-6 8 6M6 10v8m4-8v8m4-8v8m4-8v8M4 20h16',
    card: 'M3 10h18M3 7a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7z',
}

const sections = computed(() => {
    const p = profil.value || {}
    const g = (icon, label, value) => ({ icon, label, value })
    return [
        { title: 'Kontak', items: [g('email', 'Email', p.email), g('phone', 'No. HP', p.no_hp), g('home', 'Alamat', p.alamat)].filter(x => x.value) },
        {
            title: 'Data Diri', items: [
                g('id', 'NIK', p.nik),
                g('cake', 'Tempat, Tgl Lahir', [p.tempat_lahir, tgl(p.tanggal_lahir)].filter(Boolean).join(', ') || null),
                g('user', 'Jenis Kelamin', gender(p.jenis_kelamin)),
                g('edu', 'Pendidikan', [p.pendidikan_terakhir, p.jurusan].filter(Boolean).join(' · ') || null),
            ].filter(x => x.value)
        },
        {
            title: 'Kepegawaian', items: [
                g('briefcase', 'Jenis Guru', p.jenis_guru),
                g('badge', 'Status', p.status_kepegawaian),
                g('calendar', 'Tanggal Masuk', tgl(p.tanggal_masuk)),
            ].filter(x => x.value)
        },
        {
            title: 'Rekening', items: [
                g('bank', 'Bank', p.nama_bank),
                g('card', 'No. Rekening', p.no_rekening),
                g('user', 'Atas Nama', p.nama_rekening),
            ].filter(x => x.value)
        },
    ].filter(s => s.items.length)
})
</script>

<template>
    <div class="pb-4">
        <div v-if="loading" class="pt-24 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>

        <template v-else>
            <!-- ══ HERO ══ -->
            <div class="relative overflow-hidden rounded-[28px] bg-gradient-to-br from-[#06346B] via-[#0A5AC0] to-[#0C78FF] text-white p-6 pt-7 shadow-lg shadow-blue-500/20">
                <div class="absolute -top-10 -right-8 w-40 h-40 rounded-full bg-white/10 blur-2xl"></div>
                <div class="absolute -bottom-12 -left-10 w-44 h-44 rounded-full bg-cyan-300/10 blur-2xl"></div>

                <button @click="router.push({ name: 'profil-edit' })"
                    class="absolute top-4 right-4 z-10 inline-flex items-center gap-1.5 text-[12px] font-bold px-3 py-1.5 rounded-full bg-white/15 border border-white/25 backdrop-blur active:scale-95 transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5l4 4M4 20h4l10.5-10.5a2.83 2.83 0 10-4-4L4 16v4z"/></svg>
                    Edit
                </button>

                <div class="relative flex flex-col items-center text-center">
                    <div class="w-24 h-24 rounded-full bg-white/15 ring-4 ring-white/30 overflow-hidden grid place-items-center shadow-xl">
                        <img v-if="profil?.foto" :src="profil.foto" class="w-full h-full object-cover" @error="profil.foto = null" />
                        <span v-else class="text-3xl font-extrabold tracking-tight">{{ initials }}</span>
                    </div>
                    <h1 class="mt-3 text-xl font-extrabold leading-tight">{{ profil?.name || '—' }}</h1>
                    <p class="text-white/75 text-sm">{{ profil?.jabatan_display || profil?.jabatan || '—' }}</p>

                    <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                        <span v-if="profil?.nip" class="text-[11px] font-bold px-3 py-1 rounded-full bg-white/15 border border-white/20">NIP {{ profil.nip }}</span>
                        <span v-if="profil?.status_kepegawaian" class="text-[11px] font-bold px-3 py-1 rounded-full border capitalize" :class="statusBadge">{{ profil.status_kepegawaian }}</span>
                    </div>
                </div>
            </div>

            <!-- ══ SECTIONS ══ -->
            <div class="mt-5 space-y-5">
                <section v-for="sec in sections" :key="sec.title">
                    <p class="text-[11px] font-extrabold uppercase tracking-wider text-gray-400 px-1 mb-2">{{ sec.title }}</p>
                    <div class="rounded-2xl bg-white border border-gray-100 divide-y divide-gray-50 overflow-hidden">
                        <div v-for="it in sec.items" :key="it.label" class="flex items-start gap-3 px-4 py-3.5">
                            <div class="w-9 h-9 rounded-xl bg-[#0C78FF]/10 grid place-items-center shrink-0 mt-0.5">
                                <svg class="w-[18px] h-[18px] text-[#0C78FF]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" :d="I[it.icon]" /></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[11px] text-gray-400">{{ it.label }}</p>
                                <p class="text-sm font-semibold text-gray-800 break-words">{{ it.value }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- ══ HARI LIBUR MINGGUAN ══ -->
            <section class="mt-4 bg-white rounded-2xl border border-gray-100 p-4">
                <p class="text-sm font-bold text-gray-800">Hari Libur Mingguan</p>

                <!-- 1) Menunggu persetujuan → terkunci -->
                <template v-if="profil?.hari_libur_diajukan">
                    <div class="mt-2 rounded-xl bg-amber-50 border border-amber-200 px-3 py-3 text-xs text-amber-700 leading-relaxed">
                        ⏳ <b>Menunggu persetujuan admin.</b><br>
                        Diajukan: <b>{{ profil.hari_libur_diajukan.length ? profil.hari_libur_diajukan.map(labelHari).join(', ') : 'tidak ada libur' }}</b>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1.5">🔒 Terkunci sampai admin memproses. Jika ditolak, kamu bisa mengajukan ulang.</p>
                </template>

                <!-- 2) Sudah disetujui → terkunci (hanya admin yang bisa ubah) -->
                <template v-else-if="profil?.hari_libur?.length">
                    <div class="mt-2 rounded-xl bg-emerald-50 border border-emerald-200 px-3 py-3 text-xs text-emerald-700">
                        ✓ <b>Hari libur sudah ditetapkan:</b> {{ profil.hari_libur.map(labelHari).join(', ') }}
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1.5">🔒 Terkunci. Untuk mengubah, hubungi admin.</p>
                </template>

                <!-- 3) Belum mengajukan / ditolak → form (sekali ajukan) -->
                <template v-else>
                    <p class="text-[11px] text-gray-400 mt-0.5 mb-2">Ajukan hari libur tetapmu (sekali). Berlaku setelah disetujui admin.</p>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="h in hariOpsi" :key="h.key" type="button" @click="toggleLibur(h.key)"
                            :class="['px-3 py-2 rounded-xl border text-xs font-medium transition-colors',
                                liburSel.includes(h.key) ? 'border-amber-400 bg-amber-50 text-amber-700' : 'border-gray-200 text-gray-500']">
                            {{ h.label }}
                        </button>
                    </div>
                    <p v-if="liburMsg" :class="liburMsg.ok ? 'text-emerald-600' : 'text-red-500'" class="text-xs mt-2">{{ liburMsg.text }}</p>
                    <button @click="ajukanLibur" :disabled="liburSaving"
                        class="mt-3 w-full py-2.5 rounded-xl bg-amber-500 text-white font-bold text-sm disabled:opacity-60">
                        {{ liburSaving ? 'Mengirim…' : 'Ajukan Hari Libur' }}
                    </button>
                </template>
            </section>

            <!-- ══ LOGOUT ══ -->
            <button @click="konfirmKeluar = true"
                class="mt-6 w-full py-3.5 rounded-2xl bg-red-50 text-red-600 font-bold text-sm flex items-center justify-center gap-2 active:scale-[0.99] transition">
                <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                Keluar
            </button>

            <p class="mt-4 text-[11px] text-gray-300 text-center">An-Nur Smart System · Web v1.0</p>
        </template>

        <!-- Konfirmasi keluar -->
        <BottomSheet v-model="konfirmKeluar" title="Keluar dari aplikasi?" subtitle="Anda harus login kembali untuk masuk.">
            <div class="flex gap-2">
                <button @click="konfirmKeluar = false" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-600 font-bold text-sm">Batal</button>
                <button @click="keluar" :disabled="keluarBusy" class="flex-1 py-3 rounded-xl bg-red-600 text-white font-bold text-sm disabled:opacity-60">{{ keluarBusy ? 'Keluar…' : 'Ya, Keluar' }}</button>
            </div>
        </BottomSheet>
    </div>
</template>

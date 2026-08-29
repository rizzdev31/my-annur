<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
import { auth } from '../store/auth'
import PengumumanPopup from '../components/PengumumanPopup.vue'

const router = useRouter()
const pengRef = ref(null)
const hasPeng = ref(false)
const d = ref(null)
const loading = ref(true)
const initials = (n) => (n || '?').split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase()

async function load() {
    try { d.value = (await api.get('/beranda')).data.data; if (d.value?.santri) auth.santri = { ...auth.santri, ...d.value.santri } }
    catch (_) {/* */ } finally { loading.value = false }
}
onMounted(load)

// donut dasharray helper (r=15.915 → keliling 100)
const dash = (p) => `${Math.max(0, Math.min(100, p))} ${100 - Math.max(0, Math.min(100, p))}`
const trenMax = computed(() => Math.max(1, ...((d.value?.tren || []).map((t) => t.total))))
</script>

<template>
    <div v-if="loading" class="pt-16 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>
    <div v-else-if="d" class="space-y-5">
        <PengumumanPopup ref="pengRef" @loaded="hasPeng = $event" />

        <!-- Banner pengumuman (buka ulang popup) -->
        <button v-if="hasPeng" @click="pengRef.open()"
            class="w-full flex items-center gap-3 rounded-2xl bg-[#0C78FF]/10 border border-[#0C78FF]/20 px-4 py-3 text-left active:scale-[0.99] transition">
            <div class="w-9 h-9 rounded-xl bg-[#0C78FF] grid place-items-center shrink-0">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" /></svg>
            </div>
            <div class="flex-1 min-w-0"><p class="text-[13px] font-bold text-[#06346B]">Ada Pengumuman</p><p class="text-[11px] text-gray-500">Ketuk untuk melihat</p></div>
            <svg class="w-5 h-5 text-[#0C78FF]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        </button>

        <!-- Hero santri -->
        <div class="rounded-3xl bg-gradient-to-br from-[#0C78FF] to-[#06346B] text-white p-5 flex items-center gap-4">
            <div class="w-16 h-16 rounded-2xl bg-white/15 ring-2 ring-white/30 overflow-hidden grid place-items-center text-lg font-extrabold shrink-0">
                <img v-if="d.santri.foto" :src="d.santri.foto" class="w-full h-full object-cover" @error="d.santri.foto = null" />
                <span v-else>{{ initials(d.santri.nama) }}</span>
            </div>
            <div class="min-w-0">
                <p class="text-lg font-extrabold truncate">{{ d.santri.nama }}</p>
                <p class="text-white/70 text-[12px]">NIS {{ d.santri.nis }} · {{ d.santri.kelas || '—' }}</p>
                <p class="text-white/70 text-[11px] mt-0.5">{{ d.santri.program || '—' }} · {{ d.santri.tahsin_label }}</p>
            </div>
        </div>

        <p class="text-[11px] font-bold text-gray-400 -mb-2">STATISTIK {{ (d.bulan || '').toUpperCase() }}</p>

        <!-- KBM: donut + legend -->
        <div class="rounded-2xl bg-white border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-1">
                <p class="text-sm font-bold text-gray-800">Kehadiran Sekolah (KBM)</p>
                <button @click="router.push({ name: 'absensi' })" class="text-[11px] font-bold text-[#0C78FF]">Detail ›</button>
            </div>
            <div class="flex items-center gap-4">
                <svg viewBox="0 0 36 36" class="w-24 h-24 shrink-0">
                    <circle cx="18" cy="18" r="15.915" fill="none" stroke="#f1f5f4" stroke-width="3.6" />
                    <circle cx="18" cy="18" r="15.915" fill="none" stroke="#0C78FF" stroke-width="3.6" stroke-linecap="round" :stroke-dasharray="dash(d.kbm.persen_hadir)" stroke-dashoffset="25" transform="rotate(-90 18 18)" />
                    <text x="18" y="17" text-anchor="middle" font-size="7" font-weight="800" fill="#06346B">{{ d.kbm.persen_hadir }}%</text>
                    <text x="18" y="23" text-anchor="middle" font-size="3.2" fill="#94a3b8">hadir</text>
                </svg>
                <div class="flex-1 space-y-1.5">
                    <div v-for="[k, lbl, c] in [['hadir','Hadir','bg-emerald-500'],['telat','Telat','bg-amber-500'],['alpha','Alpha','bg-red-500']]" :key="k" class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full" :class="c"></span>
                        <span class="text-[12px] text-gray-500 flex-1">{{ lbl }}</span>
                        <span class="text-sm font-extrabold text-gray-800">{{ d.kbm[k] }}</span>
                    </div>
                    <div class="flex items-center gap-2 pt-1 border-t border-gray-50">
                        <span class="text-[12px] text-gray-400 flex-1">Total sesi</span>
                        <span class="text-sm font-bold text-gray-600">{{ d.kbm.total }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tren 6 bulan -->
        <div class="rounded-2xl bg-white border border-gray-100 p-4">
            <p class="text-sm font-bold text-gray-800 mb-3">Tren Kehadiran 6 Bulan</p>
            <div class="flex items-end justify-between gap-1.5 h-28">
                <div v-for="(t, i) in d.tren" :key="i" class="flex-1 flex flex-col items-center gap-1">
                    <div class="w-full rounded-t-md bg-blue-100 relative flex items-end justify-center" :style="{ height: (18 + (t.total / trenMax) * 82) + '%' }">
                        <div class="absolute bottom-0 inset-x-0 rounded-t-md bg-[#0C78FF]" :style="{ height: t.total ? (t.persen) + '%' : '0%' }"></div>
                        <span class="relative text-[9px] font-bold text-gray-600 mb-0.5">{{ t.total || '' }}</span>
                    </div>
                    <span class="text-[9px] text-gray-400">{{ t.bulan }}</span>
                </div>
            </div>
            <p class="text-[10px] text-gray-400 mt-2 text-center">Batang = total sesi · isi biru = proporsi hadir</p>
        </div>

        <!-- Tahfidz + Tahsin progress -->
        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-2xl bg-white border border-gray-100 p-4" @click="router.push({ name: 'tahfidz' })">
                <p class="text-[11px] font-bold text-gray-400">TAHFIDZ</p>
                <p class="text-2xl font-extrabold text-emerald-600 mt-1">{{ d.tahfidz.persen }}%</p>
                <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden my-1.5"><div class="h-full rounded-full bg-emerald-500" :style="{ width: Math.min(100, d.tahfidz.persen) + '%' }"></div></div>
                <p class="text-[11px] text-gray-500">{{ d.tahfidz.juz_selesai }} juz · {{ d.tahfidz.total_ayat }} ayat</p>
                <p class="text-[11px] text-emerald-600 font-semibold mt-0.5">{{ d.tahfidz.tasmi_lulus }} Tasmi' lulus</p>
            </div>
            <div class="rounded-2xl bg-white border border-gray-100 p-4" @click="router.push({ name: 'tahsin' })">
                <p class="text-[11px] font-bold text-gray-400">TAHSIN</p>
                <p class="text-lg font-extrabold text-violet-600 mt-1 leading-tight">{{ d.tahsin.label }}</p>
                <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden my-1.5"><div class="h-full rounded-full bg-violet-500" :style="{ width: d.tahsin.persen + '%' }"></div></div>
                <p class="text-[11px] text-gray-500">{{ d.tahsin.materi_lulus }}/{{ d.tahsin.materi_total }} materi lulus</p>
                <p class="text-[11px] text-violet-600 font-semibold mt-0.5">{{ d.tahsin.tasnif_lulus }} Tasnif lulus</p>
            </div>
        </div>

        <!-- Controlling + tabel ringkas -->
        <div class="rounded-2xl bg-white border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-bold text-gray-800">Kegiatan (Smart Controlling)</p>
                <button @click="router.push({ name: 'controlling' })" class="text-[11px] font-bold text-[#0C78FF]">Detail ›</button>
            </div>
            <div class="h-2 rounded-full overflow-hidden flex bg-gray-100 mb-2">
                <div class="bg-emerald-500" :style="{ width: (d.controlling.total ? d.controlling.hadir / d.controlling.total * 100 : 0) + '%' }"></div>
                <div class="bg-amber-500" :style="{ width: (d.controlling.total ? d.controlling.telat / d.controlling.total * 100 : 0) + '%' }"></div>
                <div class="bg-red-500" :style="{ width: (d.controlling.total ? d.controlling.alpha / d.controlling.total * 100 : 0) + '%' }"></div>
            </div>
            <div class="grid grid-cols-3 text-center">
                <div><p class="text-base font-extrabold text-emerald-600">{{ d.controlling.hadir }}</p><p class="text-[10px] text-gray-400">Hadir</p></div>
                <div><p class="text-base font-extrabold text-amber-600">{{ d.controlling.telat }}</p><p class="text-[10px] text-gray-400">Telat</p></div>
                <div><p class="text-base font-extrabold text-red-500">{{ d.controlling.alpha }}</p><p class="text-[10px] text-gray-400">Alpha</p></div>
            </div>
        </div>

        <!-- Tabel ringkas lainnya -->
        <div class="rounded-2xl bg-white border border-gray-100 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-50">
                <span class="text-[13px] text-gray-600">Izin (disetujui / total)</span>
                <span class="text-sm font-bold text-gray-800">{{ d.lainnya.izin_disetujui }} / {{ d.lainnya.izin_total }}</span>
            </div>
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-[13px] text-gray-600">Laporan kesehatan aktif</span>
                <span class="text-sm font-bold" :class="d.lainnya.kesehatan_aktif ? 'text-red-500' : 'text-gray-800'">{{ d.lainnya.kesehatan_aktif }}</span>
            </div>
        </div>
    </div>
</template>

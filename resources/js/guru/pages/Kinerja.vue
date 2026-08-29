<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../api'
import PageHeader from '../components/PageHeader.vue'

const d = ref(null)
const loading = ref(true)
const error = ref('')

async function load() {
    loading.value = true; error.value = ''
    try {
        const res = await api.get('/kinerja/bulan-ini')
        d.value = res.data.data ?? res.data
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat kinerja.'
    } finally { loading.value = false }
}
onMounted(load)

const komponen = computed(() => {
    if (!d.value) return []
    const k = d.value.komponen ?? d.value
    return [
        { label: 'Absensi', c: k.absensi, color: 'bg-emerald-500' },
        { label: 'Tugas', c: k.tugas, color: 'bg-[#0C78FF]' },
        { label: 'Administrasi', c: k.administrasi, color: 'bg-violet-500' },
    ].filter((x) => x.c)
})

const gradeColor = (g) => ({
    A: 'text-emerald-600', B: 'text-[#0C78FF]', C: 'text-amber-500', D: 'text-orange-500', E: 'text-red-500',
}[(g || '').toString().charAt(0)] || 'text-gray-700')
</script>

<template>
    <div>
        <PageHeader title="Kinerja Saya" />

        <div v-if="loading" class="pt-10 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div v-else-if="error" class="pt-8 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button @click="load" class="mt-3 px-4 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold">Coba lagi</button>
        </div>

        <template v-else>
            <!-- Skor -->
            <div class="rounded-3xl bg-white border border-gray-100 p-6 text-center">
                <p class="text-xs text-gray-400">{{ d.nama_bulan }}</p>
                <p class="mt-2 text-5xl font-extrabold" :class="gradeColor(d.grade)">{{ Math.round(d.skor_total ?? 0) }}</p>
                <p class="text-sm font-bold mt-1" :class="gradeColor(d.grade)">Grade {{ d.grade }} · {{ d.label_grade }}</p>
                <span v-if="d.is_preview" class="inline-block mt-2 text-[10px] text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">Preview berjalan</span>
                <span v-else-if="d.sudah_dikunci" class="inline-block mt-2 text-[10px] text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">Terkunci</span>
            </div>

            <!-- Komponen -->
            <div class="rounded-2xl bg-white border border-gray-100 p-4 mt-4 space-y-4">
                <h2 class="text-sm font-bold text-gray-800">Komponen Penilaian</h2>
                <div v-for="k in komponen" :key="k.label">
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-gray-600 font-medium">{{ k.label }}</span>
                        <span class="text-gray-400">skor {{ Math.round(k.c.skor ?? 0) }} · +{{ Math.round(k.c.kontribusi ?? 0) }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full rounded-full" :class="k.color" :style="{ width: Math.min(100, k.c.skor ?? 0) + '%' }"></div>
                    </div>
                </div>
            </div>

            <!-- Penyesuaian Guru Piket (penunjang +/−) -->
            <div v-if="d.piket" class="rounded-2xl bg-white border border-gray-100 p-4 mt-4">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-sm font-bold text-gray-800">Penilaian Guru Piket</h2>
                    <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full"
                        :class="d.piket.penyesuaian >= 0 ? 'text-emerald-600 bg-emerald-50' : 'text-red-500 bg-red-50'">
                        {{ d.piket.penyesuaian > 0 ? '+' : '' }}{{ d.piket.penyesuaian }} poin
                    </span>
                </div>
                <div class="flex items-center gap-2 text-[11px] text-gray-500 flex-wrap">
                    <span>Skor dasar <b class="text-gray-700">{{ Math.round(d.skor_dasar ?? d.skor_total) }}</b></span>
                    <span>→</span>
                    <span :class="d.piket.penyesuaian >= 0 ? 'text-emerald-600 font-semibold' : 'text-red-500 font-semibold'">{{ d.piket.penyesuaian > 0 ? '+' : '' }}{{ d.piket.penyesuaian }} piket</span>
                    <span>→</span>
                    <span>Total <b class="text-gray-800">{{ Math.round(d.skor_total) }}</b></span>
                </div>
                <div class="flex gap-2 mt-3">
                    <div class="flex-1 rounded-xl bg-emerald-50 p-2 text-center">
                        <p class="text-lg font-extrabold text-emerald-600">{{ d.piket.apresiasi }}</p>
                        <p class="text-[10px] text-gray-400">Apresiasi <span v-if="d.piket.poin_apresiasi">(+{{ d.piket.poin_apresiasi }})</span></p>
                    </div>
                    <div class="flex-1 rounded-xl bg-red-50 p-2 text-center">
                        <p class="text-lg font-extrabold text-red-500">{{ d.piket.catatan }}</p>
                        <p class="text-[10px] text-gray-400">Catatan <span v-if="d.piket.poin_catatan">(−{{ d.piket.poin_catatan }})</span></p>
                    </div>
                </div>
                <p class="text-[10px] text-gray-400 mt-2">Guru piket menambah (apresiasi) / mengurangi (catatan) kinerja di luar 3 komponen inti.</p>
            </div>
        </template>
    </div>
</template>

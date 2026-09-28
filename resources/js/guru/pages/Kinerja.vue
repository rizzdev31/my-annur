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

            <!-- Penyebab skor belum 100 — pertanyaan pertama guru: "kenapa turun?" -->
            <div v-if="d.faktor?.length" class="rounded-2xl bg-white border border-gray-100 p-4 mt-4">
                <div class="flex items-baseline justify-between mb-1">
                    <h2 class="text-sm font-bold text-gray-800">Kenapa skor belum 100</h2>
                    <span class="text-[11px] font-bold text-red-500">−{{ Math.round(d.skor_hilang ?? 0) }} poin</span>
                </div>
                <p class="text-[10px] text-gray-400 mb-3">Diurut dari yang paling menurunkan skor.</p>
                <div class="space-y-2.5">
                    <div v-for="(f, i) in d.faktor" :key="'f'+i" class="rounded-xl bg-gray-50 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[13px] font-semibold text-gray-800">{{ f.komponen }}</p>
                                <p class="text-[11px] text-gray-600 mt-0.5">{{ f.sebab }}</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">{{ f.angka }}</p>
                            </div>
                            <span class="shrink-0 text-[11px] font-bold text-red-500 tabular-nums">−{{ f.dampak }}</span>
                        </div>
                        <p class="text-[10px] text-[#0C78FF] mt-1.5">{{ f.saran }}</p>
                    </div>
                </div>
            </div>
            <div v-else class="rounded-2xl bg-emerald-50 border border-emerald-100 p-4 mt-4">
                <p class="text-[12px] text-emerald-700 font-semibold">Tidak ada catatan penurunan bulan ini.</p>
                <p class="text-[11px] text-emerald-600 mt-0.5">Semua komponen bernilai penuh sejauh data yang tercatat.</p>
            </div>

            <!-- Jejak revisi: guru berhak tahu skornya pernah diubah admin -->
            <div v-if="d.revisi?.jumlah" class="rounded-2xl bg-white border border-amber-200 p-4 mt-4">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-sm font-bold text-gray-800">Riwayat Revisi Skor</h2>
                    <span class="text-[10px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full">
                        {{ d.revisi.jumlah }}× direvisi
                    </span>
                </div>
                <div class="space-y-2">
                    <div v-for="(r, i) in d.revisi.daftar" :key="'r'+i" class="text-[11px] border-l-2 border-amber-200 pl-2.5">
                        <p class="text-gray-700 font-semibold">
                            {{ r.label_sebab }}
                            <span v-if="r.skor_lama !== null" class="font-normal text-gray-500">
                                · {{ Math.round(r.skor_lama) }} → {{ Math.round(r.skor_baru) }}
                                <span :class="r.selisih >= 0 ? 'text-emerald-600' : 'text-red-500'">
                                    ({{ r.selisih > 0 ? '+' : '' }}{{ r.selisih }})
                                </span>
                            </span>
                        </p>
                        <p v-if="r.alasan" class="text-gray-500">Alasan: {{ r.alasan }}</p>
                        <p class="text-gray-400">{{ r.oleh }} · {{ r.waktu }}</p>
                    </div>
                </div>
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

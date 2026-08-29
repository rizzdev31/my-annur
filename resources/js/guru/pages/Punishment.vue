<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'
import PageHeader from '../components/PageHeader.vue'

const list = ref([])
const meta = ref(null)
const loading = ref(true)
const error = ref('')

const rp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID')

const jenisStyle = (j) => ({
    evaluasi:    { c: 'text-[#0C78FF] bg-blue-50',   ic: 'M11 5h10M11 9h7M11 13h10M3 5h.01M3 9h.01M3 13h.01' },
    peringatan:  { c: 'text-amber-600 bg-amber-50',  ic: 'M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z' },
    potongan:    { c: 'text-red-500 bg-red-50',      ic: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V6m9 6a9 9 0 11-18 0 9 9 0 0118 0z' },
    pencopotan:  { c: 'text-red-600 bg-red-50',      ic: 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636' },
}[j] || { c: 'text-gray-500 bg-gray-100', ic: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' })

const gradeColor = (g) => ({
    A: 'text-emerald-600', B: 'text-[#0C78FF]', C: 'text-amber-500', D: 'text-orange-500', E: 'text-red-500',
}[(g || '').toString().charAt(0)] || 'text-gray-600')

async function load() {
    loading.value = true; error.value = ''
    try {
        const res = await api.get('/kinerja/punishment')
        list.value = res.data.data ?? []
        meta.value = res.data.meta ?? null
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat data.'
    } finally { loading.value = false }
}
onMounted(load)
</script>

<template>
    <div>
        <PageHeader title="Evaluasi & Teguran" />

        <div v-if="loading" class="pt-10 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>
        <div v-else-if="error" class="pt-8 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button @click="load" class="mt-3 px-4 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold">Coba lagi</button>
        </div>

        <template v-else>
            <!-- Ringkasan -->
            <div v-if="meta" class="grid grid-cols-2 gap-3 mb-4">
                <div class="rounded-2xl bg-white border border-gray-100 p-4">
                    <p class="text-[11px] text-gray-400">Kinerja Terbaru</p>
                    <p class="text-2xl font-extrabold mt-0.5" :class="gradeColor(meta.grade_terbaru)">
                        {{ meta.skor_terbaru != null ? Math.round(meta.skor_terbaru) : '–' }}
                        <span class="text-sm">{{ meta.grade_terbaru ? '· ' + meta.grade_terbaru : '' }}</span>
                    </p>
                    <p class="text-[10px] text-gray-400">{{ meta.label_grade_terbaru || '' }}</p>
                </div>
                <div class="rounded-2xl bg-white border border-gray-100 p-4">
                    <p class="text-[11px] text-gray-400">Total Potongan</p>
                    <p class="text-2xl font-extrabold text-red-500 mt-0.5">{{ rp(meta.total_potongan) }}</p>
                    <p class="text-[10px] text-gray-400">{{ meta.total }} catatan</p>
                </div>
            </div>

            <div v-if="!list.length" class="pt-12 text-center">
                <div class="w-14 h-14 rounded-2xl bg-emerald-50 grid place-items-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-sm text-gray-500 font-semibold">Tidak ada teguran</p>
                <p class="text-xs text-gray-400">Pertahankan kinerja Anda 👍</p>
            </div>

            <ul v-else class="space-y-3">
                <li v-for="p in list" :key="p.id" class="rounded-2xl bg-white border border-gray-100 p-4">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl grid place-items-center shrink-0" :class="jenisStyle(p.jenis).c">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" :d="jenisStyle(p.jenis).ic"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-bold text-gray-800">{{ p.jenis_label || p.jenis }}</p>
                                <span v-if="p.nominal > 0" class="text-[12px] font-extrabold text-red-500 shrink-0">− {{ rp(p.nominal) }}</span>
                            </div>
                            <p class="text-[11px] text-gray-400">{{ p.periode }}<span v-if="p.jabatan"> · {{ p.jabatan }}</span></p>
                            <p v-if="p.catatan" class="text-[12px] text-gray-600 mt-1.5">{{ p.catatan }}</p>
                            <p v-if="p.skor_kinerja != null" class="text-[11px] mt-1.5">
                                Skor bulan itu: <b :class="gradeColor(p.grade)">{{ Math.round(p.skor_kinerja) }}{{ p.grade ? ' · ' + p.grade : '' }}</b>
                            </p>
                        </div>
                    </div>
                </li>
            </ul>

            <p class="text-[11px] text-gray-400 text-center px-6 mt-4">Teguran/evaluasi diberikan admin berdasarkan kinerja bulanan.</p>
        </template>
    </div>
</template>

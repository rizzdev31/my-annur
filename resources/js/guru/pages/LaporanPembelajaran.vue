<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'
import PageHeader from '../components/PageHeader.vue'

const d = ref(null)
const loading = ref(true)
const error = ref('')
const kelasId = ref('')
const dari = ref('')
const sampai = ref('')
const expand = ref(null)   // no baris yang dibuka

async function load() {
    loading.value = true; error.value = ''
    try {
        const params = {}
        if (kelasId.value) params.kelas_id = kelasId.value
        if (dari.value) params.dari = dari.value
        if (sampai.value) params.sampai = sampai.value
        const res = await api.get('/education/laporan/pembelajaran', { params })
        d.value = res.data.data ?? res.data
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat laporan.'
    } finally { loading.value = false }
}
onMounted(load)
</script>

<template>
    <div>
        <PageHeader title="Laporan Pembelajaran" />

        <!-- Filter -->
        <div class="rounded-2xl bg-white border border-gray-100 p-4 mb-4 space-y-3">
            <div>
                <label class="block text-[11px] font-medium text-gray-600 mb-1">Kelas</label>
                <select v-model="kelasId" @change="load" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none">
                    <option value="">— pilih kelas —</option>
                    <option v-for="k in (d?.kelas_opsi || [])" :key="k.id" :value="k.id">{{ k.nama }}</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-[11px] font-medium text-gray-600 mb-1">Dari</label>
                    <input v-model="dari" type="date" @change="load" class="w-full px-2 py-2.5 rounded-xl border border-gray-200 text-sm outline-none" /></div>
                <div><label class="block text-[11px] font-medium text-gray-600 mb-1">Sampai</label>
                    <input v-model="sampai" type="date" @change="load" class="w-full px-2 py-2.5 rounded-xl border border-gray-200 text-sm outline-none" /></div>
            </div>
            <p v-if="d?.periode_label" class="text-[11px] text-gray-400">Periode: {{ d.periode_label }}</p>
        </div>

        <div v-if="loading" class="pt-6 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div v-else-if="error" class="pt-8 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button @click="load" class="mt-3 px-4 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold">Coba lagi</button>
        </div>

        <template v-else>
            <div v-if="!d.kelas" class="pt-10 text-center text-sm text-gray-400">Pilih kelas untuk melihat jurnal pembelajaran.</div>
            <div v-else-if="!d.rows?.length" class="pt-10 text-center text-sm text-gray-400">Tidak ada jurnal pada periode ini.</div>

            <ul v-else class="space-y-2.5">
                <li v-for="r in d.rows" :key="r.no" class="rounded-2xl bg-white border border-gray-100 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-800">{{ r.mapel }}</p>
                            <p class="text-[11px] text-gray-400">{{ r.tanggal }} · {{ r.guru }}</p>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">H {{ r.kehadiran.hadir }}</span>
                            <span class="text-[10px] font-bold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded">T {{ r.kehadiran.telat }}</span>
                            <span class="text-[10px] font-bold text-red-500 bg-red-50 px-1.5 py-0.5 rounded">A {{ r.kehadiran.alpha }}</span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-600 mt-2">{{ r.deskripsi }}</p>

                    <!-- Jurnal absensi per santri -->
                    <template v-if="r.kehadiran.terisi && r.kehadiran.santri?.length">
                        <button @click="expand = expand === r.no ? null : r.no"
                            class="mt-2 inline-flex items-center gap-1 text-[11px] font-semibold text-[#0C78FF]">
                            {{ expand === r.no ? 'Sembunyikan absensi santri' : `Lihat absensi santri (${r.kehadiran.total})` }}
                            <svg class="w-3.5 h-3.5 transition-transform" :class="expand === r.no ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <ul v-if="expand === r.no" class="mt-2 rounded-xl bg-gray-50 divide-y divide-gray-100 overflow-hidden">
                            <li v-for="(s, i) in r.kehadiran.santri" :key="i" class="flex items-center justify-between px-3 py-2">
                                <span class="text-[12px] text-gray-700 truncate">{{ i + 1 }}. {{ s.nama }}</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full capitalize shrink-0"
                                    :class="{ 'text-emerald-600 bg-emerald-100': s.status === 'hadir', 'text-amber-600 bg-amber-100': s.status === 'telat', 'text-red-500 bg-red-100': s.status === 'alpha' }">
                                    {{ s.status }}
                                </span>
                            </li>
                        </ul>
                    </template>
                    <p v-else class="mt-2 text-[11px] text-gray-400 italic">Absensi santri belum diisi untuk sesi ini.</p>
                </li>
            </ul>
        </template>
    </div>
</template>

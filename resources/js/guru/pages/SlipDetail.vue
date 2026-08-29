<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '../api'
import { auth } from '../store/auth'
import PageHeader from '../components/PageHeader.vue'

const route = useRoute()
const d = ref(null)
const loading = ref(true)
const error = ref('')

const rp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID')

const statusColor = (s) => ({
    dibayar: 'bg-emerald-500', final: 'bg-[#0041c8]', draft: 'bg-gray-400',
}[s] || 'bg-gray-400')

async function load() {
    loading.value = true; error.value = ''
    try {
        const res = await api.get(`/payroll/${route.params.id}/slip`)
        d.value = res.data.data ?? res.data
    } catch (e) {
        error.value = e.response?.data?.message || 'Slip gaji tidak ditemukan.'
    } finally { loading.value = false }
}
onMounted(load)
</script>

<template>
    <div>
        <PageHeader title="Slip Gaji" />

        <div v-if="loading" class="pt-10 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0041c8] border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div v-else-if="error" class="pt-8 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button @click="load" class="mt-3 px-4 py-2 rounded-xl bg-[#0041c8] text-white text-sm font-semibold">Coba lagi</button>
        </div>

        <div v-else class="rounded-3xl overflow-hidden border border-gray-200 bg-white" style="font-family: 'Plus Jakarta Sans', sans-serif">
            <!-- Header invoice -->
            <div class="bg-[#0041c8] text-white p-5">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-2">
                        <img src="/logo.png" class="w-8 h-8 object-contain bg-white/90 rounded-lg p-1" />
                        <div>
                            <p class="text-[13px] font-extrabold leading-tight">An-Nur Smart</p>
                            <p class="text-[10px] text-white/70">Slip Gaji Tenaga Pendidik</p>
                        </div>
                    </div>
                    <span class="text-[10px] font-bold px-2.5 py-1 rounded-full" :class="statusColor(d.status)">{{ d.status_label }}</span>
                </div>
                <div class="mt-4">
                    <p class="text-[11px] text-white/60">Periode</p>
                    <p class="text-lg font-extrabold">{{ d.periode?.nama || d.nama_periode }}</p>
                    <p v-if="d.periode?.tanggal_mulai" class="text-[10px] text-white/60">{{ d.periode.tanggal_mulai }} — {{ d.periode.tanggal_selesai }}</p>
                </div>
            </div>

            <div class="p-5">
                <!-- Penerima -->
                <div class="mb-4">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Penerima</p>
                    <p class="text-sm font-bold text-gray-800">{{ auth.user?.name || '—' }}</p>
                    <p v-if="d.jabatan" class="text-[11px] text-gray-400">{{ d.jabatan }}</p>
                </div>

                <!-- Pendapatan -->
                <p class="text-[11px] font-bold text-[#0041c8] uppercase tracking-wide mb-1">Pendapatan</p>
                <div class="divide-y divide-gray-50">
                    <div v-for="(it, i) in d.breakdown_pendapatan" :key="'p'+i" class="py-2 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[13px] text-gray-700">{{ it.label || it.keterangan }}</p>
                            <p v-if="it.jumlah_satuan" class="text-[10px] text-gray-400">{{ it.jumlah_satuan }} {{ it.satuan }} × {{ rp(it.nilai_per_satuan) }}</p>
                        </div>
                        <p class="text-[13px] font-semibold text-gray-800 tabular-nums shrink-0">{{ rp(it.subtotal) }}</p>
                    </div>
                    <div v-if="!d.breakdown_pendapatan?.length && d.gaji_pokok" class="py-2 flex justify-between">
                        <span class="text-[13px] text-gray-700">Gaji Pokok</span><span class="text-[13px] font-semibold tabular-nums">{{ rp(d.gaji_pokok) }}</span>
                    </div>
                </div>
                <div class="flex justify-between py-2 mt-1 border-t-2 border-gray-100">
                    <span class="text-[12px] font-bold text-gray-500">Total Pendapatan</span>
                    <span class="text-[13px] font-extrabold text-emerald-600 tabular-nums">{{ rp(d.total_pendapatan) }}</span>
                </div>

                <!-- Potongan -->
                <template v-if="d.breakdown_potongan?.length || d.total_potongan">
                    <p class="text-[11px] font-bold text-red-500 uppercase tracking-wide mb-1 mt-4">Potongan</p>
                    <div class="divide-y divide-gray-50">
                        <div v-for="(it, i) in d.breakdown_potongan" :key="'x'+i" class="py-2 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[13px] text-gray-700">{{ it.label || it.keterangan }}</p>
                                <p v-if="it.jumlah_satuan" class="text-[10px] text-gray-400">{{ it.jumlah_satuan }} {{ it.satuan }} × {{ rp(it.nilai_per_satuan) }}</p>
                            </div>
                            <p class="text-[13px] font-semibold text-red-500 tabular-nums shrink-0">− {{ rp(it.subtotal) }}</p>
                        </div>
                        <div v-if="!d.breakdown_potongan?.length" class="py-2 flex justify-between">
                            <span class="text-[13px] text-gray-500">Potongan</span><span class="text-[13px] font-semibold text-red-500 tabular-nums">− {{ rp(d.total_potongan) }}</span>
                        </div>
                    </div>
                    <div class="flex justify-between py-2 mt-1 border-t-2 border-gray-100">
                        <span class="text-[12px] font-bold text-gray-500">Total Potongan</span>
                        <span class="text-[13px] font-extrabold text-red-500 tabular-nums">− {{ rp(d.total_potongan) }}</span>
                    </div>
                </template>

                <!-- Gaji diterima -->
                <div class="mt-5 rounded-2xl bg-[#0041c8] text-white p-4 flex items-center justify-between">
                    <span class="text-sm font-bold">Gaji Diterima</span>
                    <span class="text-xl font-extrabold tabular-nums">{{ rp(d.gaji_bersih) }}</span>
                </div>
                <p v-if="d.dibayar_pada" class="text-[10px] text-gray-400 text-center mt-2">Dibayar pada {{ d.dibayar_pada }}</p>

                <!-- Statistik -->
                <div v-if="d.statistik" class="mt-5">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide mb-2">Rekap Absensi</p>
                    <div class="grid grid-cols-4 gap-2 text-center">
                        <div class="rounded-xl bg-gray-50 py-2"><p class="text-base font-extrabold text-gray-700">{{ d.statistik.hadir }}</p><p class="text-[9px] text-gray-400">Hadir</p></div>
                        <div class="rounded-xl bg-gray-50 py-2"><p class="text-base font-extrabold text-amber-500">{{ d.statistik.izin + d.statistik.sakit }}</p><p class="text-[9px] text-gray-400">Izin/Sakit</p></div>
                        <div class="rounded-xl bg-gray-50 py-2"><p class="text-base font-extrabold text-red-500">{{ d.statistik.alfa }}</p><p class="text-[9px] text-gray-400">Alfa</p></div>
                        <div class="rounded-xl bg-gray-50 py-2"><p class="text-base font-extrabold text-[#0041c8]">{{ d.statistik.jp_mengajar }}</p><p class="text-[9px] text-gray-400">JP</p></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

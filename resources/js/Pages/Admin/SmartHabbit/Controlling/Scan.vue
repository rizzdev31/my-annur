<template>
    <AdminLayout title="Scan Kiosk" subtitle="Smart Controlling">

        <Head title="Scan Absensi Controlling" />

        <div class="max-w-xl mx-auto">
            <div class="text-center mb-6">
                <h2 class="text-xl font-semibold text-gray-900">Scan Absensi Santri</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    Periode: <b>{{ periodeAktif?.nama || '— belum ada periode aktif' }}</b> · arahkan barcode kartu santri (NIP).
                </p>
            </div>

            <!-- Hasil scan terakhir -->
            <div v-if="hasil" :class="['rounded-2xl border-2 p-6 mb-5 text-center transition-all', kartuCls]">
                <p class="text-5xl font-black tracking-tight">{{ statusLabel }}</p>
                <p class="text-lg font-bold text-gray-800 mt-2">{{ hasil.santri?.nama }}</p>
                <p class="text-sm text-gray-500">{{ hasil.kegiatan }} · {{ hasil.jam_scan }}{{ hasil.sudah_absen ? ' · sudah absen' : '' }}</p>
            </div>
            <div v-else-if="err" class="rounded-2xl border-2 border-red-300 bg-red-50 p-6 mb-5 text-center">
                <p class="text-2xl font-black text-red-600">DITOLAK</p>
                <p class="text-sm text-red-700 mt-1">{{ err }}</p>
            </div>

            <!-- Input scan -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <label class="block text-xs font-medium text-gray-500 mb-1.5">NIP (scan barcode / ketik lalu Enter)</label>
                <input ref="nipInput" v-model="nip" @keyup.enter="scan" :disabled="loading"
                    autofocus inputmode="numeric"
                    class="w-full px-4 py-4 rounded-xl border-2 border-indigo-200 text-2xl font-mono tracking-widest text-center focus:outline-none focus:border-indigo-500"
                    placeholder="________" />
                <button @click="scan" :disabled="loading || !nip"
                    class="w-full mt-3 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold disabled:opacity-50">
                    {{ loading ? 'Memproses...' : 'Absen' }}
                </button>
            </div>

            <!-- Riwayat scan sesi ini -->
            <div v-if="riwayat.length" class="mt-5 bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs font-semibold text-gray-500 mb-2">Scan terakhir ({{ riwayat.length }})</p>
                <div class="space-y-1 max-h-60 overflow-y-auto">
                    <div v-for="(r, i) in riwayat" :key="i" class="flex items-center justify-between text-sm py-1">
                        <span class="text-gray-700">{{ r.nama }} <span class="text-gray-400">· {{ r.kegiatan }}</span></span>
                        <span :class="['text-xs font-bold px-2 py-0.5 rounded', badge(r.status)]">{{ r.status?.toUpperCase() }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, nextTick, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ periodeAktif: { type: Object, default: null } })

const nip = ref('')
const loading = ref(false)
const hasil = ref(null)
const err = ref('')
const riwayat = ref([])
const nipInput = ref(null)

onMounted(() => nipInput.value?.focus())

function csrf() {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
    return m ? decodeURIComponent(m[1]) : ''
}

async function scan() {
    const val = nip.value.trim()
    if (!val || loading.value) return
    loading.value = true; err.value = ''; hasil.value = null
    try {
        const res = await fetch(route('admin.smart-habbit.controlling.scan.submit'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': csrf() },
            body: JSON.stringify({ nip: val }),
        })
        const body = await res.json()
        if (res.ok && body.success) {
            hasil.value = body.data
            riwayat.value.unshift({ nama: body.data.santri?.nama, kegiatan: body.data.kegiatan, status: body.data.status })
            if (riwayat.value.length > 20) riwayat.value.pop()
        } else {
            err.value = body.message || 'Gagal.'
        }
    } catch (e) {
        err.value = 'Koneksi gagal.'
    } finally {
        loading.value = false
        nip.value = ''
        await nextTick(); nipInput.value?.focus()
    }
}

const statusLabel = computed(() => ({ hadir: 'HADIR', telat: 'TELAT', alpha: 'ALPHA' }[hasil.value?.status] ?? '—'))
const kartuCls = computed(() => ({
    hadir: 'border-emerald-300 bg-emerald-50 text-emerald-600',
    telat: 'border-amber-300 bg-amber-50 text-amber-600',
    alpha: 'border-red-300 bg-red-50 text-red-600',
}[hasil.value?.status] ?? 'border-gray-200 bg-gray-50 text-gray-600'))
const badge = (s) => ({ hadir: 'bg-emerald-100 text-emerald-700', telat: 'bg-amber-100 text-amber-700', alpha: 'bg-red-100 text-red-600' }[s] ?? 'bg-gray-100')
</script>

<template>
    <AdminLayout title="Monitor Outbox" subtitle="Smart Habbit">

        <Head title="Monitor Outbox" />

        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Monitor Outbox</h2>
                <p class="text-sm text-gray-400 mt-0.5">Status kiriman laporan & absensi telat ke RamahAnak. Sukses = "menunggu Guru BK".</p>
            </div>
            <button @click="sinkron" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">Tarik Kode Variabel</button>
        </div>

        <div v-if="!enabled" class="mb-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-2.5 text-sm text-amber-800">
            Integrasi RamahAnak <b>nonaktif</b> — kiriman menumpuk sebagai <b>pending</b> sampai diaktifkan & queue berjalan.
        </div>

        <!-- Ringkasan -->
        <div class="grid grid-cols-4 gap-3 mb-5">
            <div v-for="s in statKeys" :key="s.k" class="bg-white rounded-xl border border-gray-200 px-3 py-2.5 text-center">
                <p :class="['text-2xl font-bold', s.color]">{{ ringkasan[s.k] ?? 0 }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ s.label }}</p>
            </div>
        </div>

        <!-- Filter -->
        <div class="flex gap-2 mb-3">
            <select v-model="fStatus" @change="applyFilter" :class="inp + ' w-40'">
                <option value="">Semua status</option>
                <option value="pending">Pending</option>
                <option value="sent">Terkirim</option>
                <option value="duplicate">Duplicate</option>
                <option value="failed">Gagal</option>
            </select>
            <select v-model="fJenis" @change="applyFilter" :class="inp + ' w-40'">
                <option value="">Semua jenis</option>
                <option value="pelanggaran">Pelanggaran</option>
                <option value="apresiasi">Apresiasi</option>
                <option value="konselor">Konselor</option>
                <option value="telat">Telat</option>
            </select>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50/60 border-b border-gray-100 text-xs text-gray-400 uppercase">
                    <th class="px-4 py-3 text-left">Jenis</th>
                    <th class="px-4 py-3 text-left">Ringkas</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Ref / Error</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="o in rows" :key="o.id" class="hover:bg-gray-50/40">
                        <td class="px-4 py-3 capitalize font-semibold text-gray-700">{{ o.jenis }}</td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ o.payload?.kode ? o.payload.kode + ' · ' : '' }}{{ o.payload?.kegiatan || '' }}
                            <span class="text-xs text-gray-400">{{ o.payload?.tanggal }} · {{ o.actor }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="['text-xs font-semibold px-2.5 py-1 rounded-lg', cls(o.status)]">{{ label(o.status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            <p class="font-mono">{{ o.ref_id }}</p>
                            <p v-if="o.error" class="text-red-500">{{ o.error }}</p>
                            <p v-else-if="o.ramahanak_laporan_id" class="text-emerald-600">RA#{{ o.ramahanak_laporan_id }} · {{ o.sent_at }}</p>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button v-if="['failed','pending'].includes(o.status)" @click="retry(o)"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium border border-indigo-200 text-indigo-600 hover:bg-indigo-50">Kirim ulang</button>
                        </td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="5" class="py-12 text-center text-sm text-gray-400">Belum ada kiriman.</td></tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    rows: { type: Array, default: () => [] },
    filter: { type: Object, default: () => ({}) },
    ringkasan: { type: Object, default: () => ({}) },
    enabled: { type: Boolean, default: false },
})

const statKeys = [
    { k: 'pending', label: 'Pending', color: 'text-gray-500' },
    { k: 'sent', label: 'Terkirim', color: 'text-emerald-600' },
    { k: 'duplicate', label: 'Duplicate', color: 'text-sky-600' },
    { k: 'failed', label: 'Gagal', color: 'text-red-600' },
]
const fStatus = ref(props.filter.status ?? '')
const fJenis = ref(props.filter.jenis ?? '')

const label = (s) => ({ pending: 'Pending', sent: 'Terkirim (tunggu BK)', duplicate: 'Duplicate', failed: 'Gagal' }[s] ?? s)
const cls = (s) => ({ pending: 'bg-gray-100 text-gray-600', sent: 'bg-emerald-50 text-emerald-700', duplicate: 'bg-sky-50 text-sky-700', failed: 'bg-red-50 text-red-600' }[s] ?? 'bg-gray-100')

const applyFilter = () => router.get(route('admin.smart-habbit.outbox.index'), { status: fStatus.value, jenis: fJenis.value }, { preserveState: true, preserveScroll: true })
const retry = (o) => router.post(route('admin.smart-habbit.outbox.retry', o.id), {}, { preserveScroll: true })
const sinkron = () => router.post(route('admin.smart-habbit.sync-kode'), {}, { preserveScroll: true })

const inp = 'px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white'
</script>

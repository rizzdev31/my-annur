<template>
    <AdminLayout title="Periode Penggajian" subtitle="Smart Payroll">

        <Head title="Periode Penggajian" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Periode Penggajian</h2>
                <p class="text-sm text-gray-400 mt-0.5">Manajemen periode dan proses penggajian bulanan</p>
            </div>
            <Link :href="route('admin.smart-payroll.periode.create')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Buat Periode
            </Link>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Periode</th>
                        <th
                            class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">
                            Tanggal</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Guru</th>
                        <th
                            class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">
                            Total Gaji</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Status</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="p in periodes.data" :key="p.id" class="hover:bg-gray-50/40 transition-colors">

                        <td class="px-5 py-4">
                            <p class="text-sm font-semibold text-gray-800">{{ p.nama }}</p>
                            <p class="text-xs text-gray-400">{{ p.nama_bulan }} {{ p.tahun }}</p>
                        </td>

                        <td class="px-5 py-4 hidden md:table-cell">
                            <p class="text-xs text-gray-600">{{ p.tanggal_mulai }}</p>
                            <p class="text-xs text-gray-400">s/d {{ p.tanggal_selesai }}</p>
                        </td>

                        <td class="px-5 py-4 text-center">
                            <p class="text-sm font-semibold text-gray-700">{{ p.jumlah_guru }}</p>
                        </td>

                        <td class="px-5 py-4 text-right hidden lg:table-cell">
                            <p v-if="p.total_gaji > 0" class="text-sm font-bold text-indigo-700">
                                {{ formatRp(p.total_gaji) }}
                            </p>
                            <p v-else class="text-xs text-gray-400">Belum digenerate</p>
                        </td>

                        <td class="px-5 py-4">
                            <span
                                :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium', badgeStatus(p.status).class]">
                                <span :class="['w-1.5 h-1.5 rounded-full', badgeStatus(p.status).dot]"></span>
                                {{ badgeStatus(p.status).label }}
                            </span>
                        </td>

                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-1">
                                <Link :href="route('admin.smart-payroll.penggajian.detail', p.id)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                                    title="Lihat data gaji">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </Link>
                                <Link v-if="p.status === 'draft'"
                                    :href="route('admin.smart-payroll.periode.edit', p.id)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors"
                                    title="Edit">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </Link>
                                <button v-if="p.status !== 'dibayar'" @click="kunci(p)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors"
                                    title="Kunci & tandai dibayar">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </button>
                                <button v-if="p.status === 'draft' && p.jumlah_guru === 0" @click="hapus(p)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors"
                                    title="Hapus">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!periodes.data?.length">
                        <td colspan="6" class="py-14 text-center text-sm text-gray-400">
                            Belum ada periode penggajian.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Konfirmasi: Tandai Dibayar -->
        <ConfirmDialog
            :show="showBayar"
            variant="success"
            title="Tandai Periode Sudah Dibayar?"
            :message="target ? `Periode &quot;${target.nama}&quot; akan dikunci dan ditandai sudah dibayar.` : ''"
            :details="[
                'Seluruh slip gaji guru pada periode ini berubah status menjadi DIBAYAR',
                'Status &quot;Dibayar&quot; langsung tampil di aplikasi guru (Flutter)',
                'Periode dikunci — data gaji tidak bisa di-generate atau diubah lagi',
            ]"
            confirm-label="Ya, Tandai Dibayar"
            irreversible
            :loading="processing"
            @confirm="confirmBayar"
            @cancel="showBayar = false" />

        <!-- Konfirmasi: Hapus Periode -->
        <ConfirmDialog
            :show="showHapus"
            variant="danger"
            title="Hapus Periode Penggajian?"
            :message="hapusTarget ? `Periode &quot;${hapusTarget.nama}&quot; akan dihapus permanen.` : ''"
            confirm-label="Ya, Hapus"
            irreversible
            :loading="deleting"
            @confirm="confirmHapus"
            @cancel="showHapus = false" />
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'

defineProps({
    periodes: { type: Object, default: () => ({ data: [] }) },
})

// State konfirmasi "tandai dibayar"
const showBayar  = ref(false)
const target     = ref(null)
const processing = ref(false)

// State konfirmasi "hapus periode"
const showHapus   = ref(false)
const hapusTarget = ref(null)
const deleting    = ref(false)

function badgeStatus(s) {
    return {
        draft: { label: 'Draft', class: 'bg-gray-100 text-gray-600', dot: 'bg-gray-400' },
        selesai: { label: 'Selesai', class: 'bg-amber-50 text-amber-700', dot: 'bg-amber-500' },
        dibayar: { label: 'Dibayar', class: 'bg-emerald-50 text-emerald-700', dot: 'bg-emerald-500' },
        proses: { label: 'Proses', class: 'bg-blue-50 text-blue-700', dot: 'bg-blue-500' },
    }[s] ?? { label: s, class: 'bg-gray-100 text-gray-600', dot: 'bg-gray-400' }
}

function formatRp(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }

function kunci(p) {
    target.value = p
    showBayar.value = true
}
function confirmBayar() {
    if (!target.value) return
    router.patch(route('admin.smart-payroll.periode.kunci', target.value.id), {}, {
        onStart:  () => { processing.value = true },
        onFinish: () => { processing.value = false; showBayar.value = false },
    })
}
function hapus(p) {
    hapusTarget.value = p
    showHapus.value = true
}
function confirmHapus() {
    if (!hapusTarget.value) return
    router.delete(route('admin.smart-payroll.periode.destroy', hapusTarget.value.id), {
        onStart:  () => { deleting.value = true },
        onFinish: () => { deleting.value = false; showHapus.value = false },
    })
}
</script>
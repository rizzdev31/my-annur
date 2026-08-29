<template>
    <AdminLayout title="Setting Potongan" subtitle="Smart Payroll">

        <Head title="Setting Potongan" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Setting Potongan</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    Kelola semua jenis potongan gaji — absensi, wajib, simpanan, pinjaman, dan lainnya
                </p>
            </div>
            <Link :href="route('admin.smart-payroll.setting-potongan.create')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Potongan
            </Link>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center text-lg shrink-0">📋</div>
                <div>
                    <p class="text-xl font-bold text-gray-900">{{ summary.total }}</p>
                    <p class="text-xs text-gray-400">Total Setting</p>
                </div>
            </div>
            <div class="bg-emerald-50 rounded-xl border border-emerald-100 px-4 py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-100 flex items-center justify-center text-lg shrink-0">✅</div>
                <div>
                    <p class="text-xl font-bold text-emerald-700">{{ summary.aktif }}</p>
                    <p class="text-xs text-emerald-500">Aktif</p>
                </div>
            </div>
            <div v-for="(jumlah, kat) in summary.per_kategori" :key="kat"
                class="bg-white rounded-xl border border-gray-200 px-4 py-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg shrink-0" :class="katBg[kat]">
                    {{ katIcon[kat] }}
                </div>
                <div>
                    <p class="text-xl font-bold text-gray-900">{{ jumlah }}</p>
                    <p class="text-xs text-gray-400">{{ katLabel[kat] }}</p>
                </div>
            </div>
        </div>

        <!-- Info box -->
        <div class="mb-5 p-4 bg-blue-50 border border-blue-100 rounded-2xl flex gap-3">
            <svg class="w-5 h-5 text-blue-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="text-xs text-blue-700 space-y-1">
                <p><strong>Absensi</strong> — otomatis dihitung saat generate gaji (per terlambat/per alfa).</p>
                <p><strong>Wajib/Simpanan/Pinjaman</strong> — dipotong flat setiap bulan sesuai nominal/persen.</p>
                <p><strong>Manual</strong> — tidak otomatis, harus di-input per guru via override penggajian.</p>
            </div>
        </div>

        <!-- Tabel per kategori -->
        <div v-for="(items, kategori) in grouped" :key="kategori" class="mb-5">
            <!-- Kategori header -->
            <div class="flex items-center gap-3 mb-3">
                <span class="text-xl">{{ katIcon[kategori] }}</span>
                <p class="text-sm font-semibold text-gray-700">{{ katLabel[kategori] }}</p>
                <span class="text-xs text-gray-400">{{ items.length }} setting</span>
                <div :class="['w-2 h-2 rounded-full ml-auto', katDot[kategori]]"></div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50/50 border-b border-gray-100">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Nama / Kode
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">
                                Pemicu</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-400 uppercase">Nominal</th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase hidden lg:table-cell">
                                Lingkup</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-400 uppercase">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-400 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="p in items" :key="p.id"
                            :class="['hover:bg-gray-50/40 transition-colors', !p.is_aktif ? 'opacity-50' : '']">
                            <td class="px-4 py-3.5">
                                <p class="text-sm font-semibold text-gray-800">{{ p.nama }}</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-xs font-mono text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">
                                        {{ p.kode }}
                                    </span>
                                    <span v-if="!p.tampil_di_slip" class="text-xs text-gray-400 italic">
                                        · Tidak di slip
                                    </span>
                                </div>
                                <p v-if="p.deskripsi" class="text-xs text-gray-400 mt-1 truncate max-w-xs">
                                    {{ p.deskripsi }}
                                </p>
                            </td>
                            <td class="px-4 py-3.5 hidden md:table-cell">
                                <span :class="['text-xs px-2 py-1 rounded-lg font-medium', pemicuBadge(p.tipe_pemicu)]">
                                    {{ p.tipe_pemicu_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <p class="text-sm font-bold text-gray-800">{{ p.nominal_display }}</p>
                                <p v-if="p.nominal_maksimal" class="text-xs text-gray-400">
                                    maks {{ formatRp(p.nominal_maksimal) }}
                                </p>
                            </td>
                            <td class="px-4 py-3.5 hidden lg:table-cell">
                                <span :class="['text-xs px-2 py-1 rounded-lg', lingkupBadge(p.lingkup)]">
                                    {{ lingkupLabel[p.lingkup] }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <button @click="toggleStatus(p)" :class="[`relative w-9 h-5 rounded-full transition-colors`,
                                    p.is_aktif ? `bg-indigo-500` : `bg-gray-300`]">
                                    <span :class="[`absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform`,
                                        p.is_aktif ? `translate-x-4` : `translate-x-0.5`]" />
                                </button>
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <div class="flex justify-end gap-1">
                                    <Link :href="route('admin.smart-payroll.setting-potongan.edit', p.id)"
                                        class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </Link>
                                    <button @click="hapus(p)"
                                        class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="!potongan.length" class="bg-white rounded-2xl border border-gray-200 py-14 text-center">
            <p class="text-3xl mb-3">📋</p>
            <p class="text-sm font-semibold text-gray-700 mb-1">Belum ada setting potongan</p>
            <p class="text-xs text-gray-400 mb-4">Tambahkan jenis potongan untuk digunakan saat generate gaji</p>
            <Link :href="route('admin.smart-payroll.setting-potongan.create')"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl">
                Tambah Setting Pertama
            </Link>
        </div>
        <AppConfirm ref="confirm" />
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppConfirm from '@/Components/AppConfirm.vue'

const confirm = ref(null)

const props = defineProps({
    potongan: { type: Array, default: () => [] },
    grouped: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
})

const katLabel = { absensi: 'Absensi', wajib: 'Wajib', simpanan: 'Simpanan', pinjaman: 'Pinjaman', lainnya: 'Lainnya' }
const katIcon = { absensi: '⏰', wajib: '📋', simpanan: '💰', pinjaman: '🏦', lainnya: '📌' }
const katBg = { absensi: 'bg-amber-100', wajib: 'bg-red-100', simpanan: 'bg-emerald-100', pinjaman: 'bg-blue-100', lainnya: 'bg-gray-100' }
const katDot = { absensi: 'bg-amber-400', wajib: 'bg-red-400', simpanan: 'bg-emerald-400', pinjaman: 'bg-blue-400', lainnya: 'bg-gray-400' }
const lingkupLabel = { semua: 'Semua Guru', per_jabatan: 'Per Jabatan', per_individu: 'Per Individu', custom: 'Jabatan & Individu' }

function pemicuBadge(t) {
    return {
        per_keterlambatan: 'bg-amber-50 text-amber-700', per_alfa: 'bg-red-50 text-red-600',
        per_menit_keterlambatan: 'bg-orange-50 text-orange-700',
        per_bulan: 'bg-gray-100 text-gray-600', persen_gaji: 'bg-indigo-50 text-indigo-600',
        manual: 'bg-violet-50 text-violet-600'
    }[t] ?? 'bg-gray-100 text-gray-600'
}

function lingkupBadge(l) {
    return {
        semua: 'bg-gray-100 text-gray-600', per_jabatan: 'bg-indigo-50 text-indigo-600',
        per_individu: 'bg-teal-50 text-teal-600', custom: 'bg-violet-50 text-violet-600'
    }[l] ?? 'bg-gray-100 text-gray-600'
}

function toggleStatus(p) {
    router.patch(route('admin.smart-payroll.setting-potongan.toggle', p.id), {}, { preserveScroll: true })
}

function hapus(p) {
    confirm.value.ask(
        { title: 'Hapus Setting Potongan?', message: `Setting potongan "${p.nama}" akan dihapus permanen.`,
          variant: 'danger', confirmLabel: 'Ya, Hapus', irreversible: true },
        (done) => router.delete(route('admin.smart-payroll.setting-potongan.destroy', p.id),
            { preserveScroll: true, onFinish: done }),
    )
}

function formatRp(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }
</script>
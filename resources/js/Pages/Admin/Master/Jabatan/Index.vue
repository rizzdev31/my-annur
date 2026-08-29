<template>
    <AdminLayout title="Jabatan" subtitle="Master Data">

        <Head title="Jabatan" />

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Jabatan</h2>
                <p class="text-sm text-gray-400 mt-0.5">Kelola jabatan, template tugas, dan struktur pendapatan</p>
            </div>
            <div class="flex items-center gap-2">
                <!-- Link ke Multi Jabatan -->
                <Link :href="route('admin.master.jabatan.multi')"
                    class="inline-flex items-center gap-2 px-4 py-2.5 border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Multi Jabatan Guru
                </Link>
                <Link :href="route('admin.master.jabatan.create')"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Jabatan
                </Link>
            </div>
        </div>

        <!-- Banner: terkunci saat penggajian diproses -->
        <div v-if="penggajianProses"
            class="flex items-start gap-3 p-4 mb-5 rounded-2xl bg-amber-50 border border-amber-200">
            <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
                <p class="text-sm font-semibold text-amber-800">Penggajian sedang diproses</p>
                <p class="text-xs text-amber-700 mt-0.5">
                    Ubah &amp; hapus jabatan dikunci sementara karena berkaitan dengan gaji.
                    Bisa dilakukan lagi setelah gaji bulan ini selesai <b>dibayar</b>.
                </p>
            </div>
        </div>

        <!-- Info box: penjelasan gaji pokok vs vakasi jabatan -->
        <div class="bg-indigo-50 border border-indigo-100 rounded-2xl px-5 py-4 mb-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-indigo-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-indigo-800">Gaji Pokok</p>
                        <p class="text-xs text-indigo-600 mt-0.5 leading-relaxed">
                            Dari setting <strong>Gaji Pokok</strong> per jabatan. Bisa di-override per individu.
                            Masuk ke slip gaji sebagai komponen tetap.
                        </p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-violet-100 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-violet-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-violet-800">Vakasi Jabatan</p>
                        <p class="text-xs text-violet-600 mt-0.5 leading-relaxed">
                            Tunjangan tetap bulanan <em>khusus jabatan ini</em> (misal Rp 450.000/bln untuk Tim
                            Branding).
                            Setting di <strong>Setting Gaji → Vakasi</strong> dengan tipe <code>tugas_jabatan</code>.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary strip -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div v-for="s in summaryCards" :key="s.label"
                class="bg-white rounded-xl border border-gray-200 px-4 py-3 flex items-center gap-3">
                <div :class="['w-9 h-9 rounded-lg flex items-center justify-center shrink-0 text-base', s.bg]">
                    {{ s.icon }}
                </div>
                <div>
                    <p class="text-xl font-bold text-gray-900 leading-none">{{ s.value }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ s.label }}</p>
                </div>
            </div>
        </div>

        <!-- Jabatan grouped by tipe -->
        <div v-for="(group, tipe) in jabatanByTipe" :key="tipe" class="mb-6">
            <div class="flex items-center gap-3 mb-3">
                <span :class="['w-2 h-2 rounded-full', dotTipe[tipe]]"></span>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest">
                    {{ labelTipe[tipe] }}
                </p>
                <span class="text-xs text-gray-300">{{ group.length }} jabatan</span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                <div v-for="j in group" :key="j.id" :class="[
                    'bg-white rounded-2xl border overflow-hidden transition-all duration-150',
                    !j.is_aktif ? 'opacity-60 border-gray-200' : 'border-gray-200 hover:border-indigo-200 hover:shadow-sm'
                ]">

                    <!-- Card Header -->
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="flex items-center gap-3">
                                <div
                                    :class="['w-10 h-10 rounded-xl flex items-center justify-center shrink-0', bgTipe[tipe]]">
                                    <span class="text-xs font-bold" :class="textTipe[tipe]">
                                        {{ j.kode_jabatan }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ j.nama_jabatan }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ j.jumlah_guru }} guru aktif</p>
                                </div>
                            </div>
                            <span :class="[
                                'shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-medium',
                                j.is_aktif ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'
                            ]">
                                <span
                                    :class="['w-1.5 h-1.5 rounded-full', j.is_aktif ? 'bg-emerald-500' : 'bg-gray-400']"></span>
                                {{ j.is_aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>

                        <!-- Gaji Pokok + Vakasi Jabatan — 2 komponen terpisah -->
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <div class="bg-indigo-50 rounded-xl px-3 py-2.5">
                                <p class="text-xs text-indigo-500 font-medium mb-0.5">Gaji Pokok</p>
                                <p v-if="j.gaji_pokok > 0" class="text-sm font-bold text-indigo-700">
                                    {{ formatRupiah(j.gaji_pokok) }}
                                </p>
                                <p v-else class="text-xs text-indigo-400 italic">Belum di-setting</p>
                                <p class="text-xs text-indigo-400 mt-0.5">per bulan</p>
                            </div>
                            <div class="bg-violet-50 rounded-xl px-3 py-2.5">
                                <p class="text-xs text-violet-500 font-medium mb-0.5">Vakasi Jabatan</p>
                                <p v-if="j.vakasi_jabatan > 0" class="text-sm font-bold text-violet-700">
                                    {{ formatRupiah(j.vakasi_jabatan) }}
                                </p>
                                <p v-else class="text-xs text-violet-400 italic">Belum di-setting</p>
                                <p class="text-xs text-violet-400 mt-0.5">per bulan</p>
                            </div>
                        </div>

                        <!-- Total tetap per bulan -->
                        <div v-if="j.total_tetap_per_bulan > 0"
                            class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-xl mb-3">
                            <p class="text-xs text-gray-500">Total tetap/bulan</p>
                            <p class="text-sm font-bold text-gray-800">{{ formatRupiah(j.total_tetap_per_bulan) }}</p>
                        </div>
                    </div>

                    <!-- Template Tugas -->
                    <div class="border-t border-gray-50">
                        <div class="px-4 pt-3 pb-1">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">
                                Template Tugas
                                <span class="text-gray-300 font-normal ml-1">(referensi log kerja)</span>
                            </p>
                        </div>

                        <div v-if="j.tugas.length" class="px-4 pb-3 space-y-1.5">
                            <div v-for="t in j.tugas.slice(0, 4)" :key="t.id"
                                class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span :class="[
                                        'w-1.5 h-1.5 rounded-full shrink-0',
                                        t.wajib_laporan ? 'bg-orange-400' : 'bg-gray-300'
                                    ]"></span>
                                    <span class="text-xs text-gray-700 truncate">{{ t.nama_tugas }}</span>
                                    <span
                                        class="text-xs text-gray-400 shrink-0 font-mono bg-gray-100 px-1.5 py-0.5 rounded">
                                        {{ frekuensiShort[t.frekuensi] }}
                                    </span>
                                </div>
                                <span v-if="t.vakasi_nominal > 0" class="text-xs font-medium text-teal-600 shrink-0">
                                    +{{ formatRupiah(t.vakasi_nominal) }}
                                </span>
                            </div>
                            <p v-if="j.tugas.length > 4" class="text-xs text-gray-400 pl-3.5">
                                +{{ j.tugas.length - 4 }} tugas lainnya...
                            </p>
                        </div>

                        <div v-else class="px-4 pb-3">
                            <button @click="tambahTugas(j)"
                                class="flex items-center gap-1.5 text-xs text-indigo-500 hover:text-indigo-700 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                Tambah template tugas
                            </button>
                        </div>
                    </div>

                    <!-- Warnings -->
                    <div class="px-4 pb-3 space-y-2">
                        <!-- Warning: ada guru tapi gaji pokok & vakasi belum di-setting -->
                        <div v-if="j.jumlah_guru > 0 && j.gaji_pokok === 0 && j.vakasi_jabatan === 0"
                            class="flex items-start gap-2 p-2.5 bg-amber-50 border border-amber-200 rounded-xl">
                            <svg class="w-3.5 h-3.5 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <p class="text-xs text-amber-700">
                                {{ j.jumlah_guru }} guru aktif tapi <strong>gaji pokok dan vakasi jabatan belum
                                    di-setting</strong>.
                                Penggajian akan menghasilkan Rp 0.
                            </p>
                        </div>

                        <!-- Warning: gaji pokok ada tapi vakasi jabatan kosong -->
                        <div v-else-if="j.jumlah_guru > 0 && j.gaji_pokok > 0 && j.vakasi_jabatan === 0"
                            class="flex items-start gap-2 p-2.5 bg-blue-50 border border-blue-100 rounded-xl">
                            <svg class="w-3.5 h-3.5 text-blue-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-xs text-blue-600">
                                Vakasi jabatan belum di-setting. Jika jabatan ini tidak perlu vakasi jabatan, abaikan.
                            </p>
                        </div>

                        <!-- Warning: ada guru tapi tidak ada template tugas -->
                        <div v-if="j.jumlah_guru > 0 && j.jumlah_tugas === 0"
                            class="flex items-start gap-2 p-2.5 bg-gray-50 border border-gray-200 rounded-xl">
                            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <p class="text-xs text-gray-500">
                                Belum ada template tugas. Guru tidak punya referensi saat input log kerja.
                            </p>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="px-4 py-3 border-t border-gray-50 flex items-center justify-between">
                        <div class="text-xs text-gray-400">
                            <span v-if="j.total_vakasi_tugas > 0">
                                Potensi vakasi tugas:
                                <span class="font-semibold text-teal-600">{{ formatRupiah(j.total_vakasi_tugas)
                                    }}</span>
                            </span>
                            <span v-else class="italic">Tidak ada vakasi per-tugas</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <button @click="tambahTugas(j)"
                                class="p-2 rounded-lg text-gray-400 hover:text-teal-600 hover:bg-teal-50 transition-colors"
                                title="Tambah Template Tugas">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </button>
                            <Link :href="route('admin.master.jabatan.edit', j.id)"
                                class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors"
                                title="Edit">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </Link>
                            <button @click="confirmHapus(j)"
                                class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors"
                                title="Hapus">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="flex items-center gap-6 mt-2 px-1">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                <p class="text-xs text-gray-400">Tugas wajib laporan (ada di realisasi)</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-gray-300"></span>
                <p class="text-xs text-gray-400">Template tugas opsional</p>
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

const props = defineProps({
    jabatan: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    penggajianProses: { type: Boolean, default: false },
})

const summaryCards = computed(() => [
    { label: 'Total Jabatan', value: props.summary.total ?? 0, icon: '🏷️', bg: 'bg-indigo-50' },
    { label: 'Aktif', value: props.summary.aktif ?? 0, icon: '✅', bg: 'bg-emerald-50' },
    { label: 'Template Tugas', value: props.summary.total_tugas ?? 0, icon: '📋', bg: 'bg-teal-50' },
    { label: 'Tugas Wajib', value: props.summary.tugas_wajib ?? 0, icon: '⚠️', bg: 'bg-amber-50' },
])

const jabatanByTipe = computed(() => {
    const order = ['struktural', 'fungsional', 'mengajar']
    const groups = {}
    order.forEach(t => { groups[t] = [] })
    props.jabatan.forEach(j => {
        const t = j.tipe ?? 'struktural'
        if (!groups[t]) groups[t] = []
        groups[t].push(j)
    })
    return Object.fromEntries(Object.entries(groups).filter(([, v]) => v.length > 0))
})

const labelTipe = { struktural: 'Struktural', fungsional: 'Fungsional', mengajar: 'Mengajar' }
const dotTipe = { struktural: 'bg-indigo-500', fungsional: 'bg-violet-500', mengajar: 'bg-teal-500' }
const bgTipe = { struktural: 'bg-indigo-50', fungsional: 'bg-violet-50', mengajar: 'bg-teal-50' }
const textTipe = { struktural: 'text-indigo-700', fungsional: 'text-violet-700', mengajar: 'text-teal-700' }
const frekuensiShort = { harian: 'H', mingguan: 'M', bulanan: 'B', insidental: 'I' }

function formatRupiah(n) {
    if (!n) return 'Rp 0'
    return 'Rp ' + Number(n).toLocaleString('id-ID')
}

function tambahTugas(jabatan) {
    router.visit(route('admin.smart-payroll.tugas-jabatan.create') + '?jabatan_id=' + jabatan.id)
}

async function confirmHapus(j) {
    const details = []
    if (j.jumlah_guru > 0) details.push(`Masih dipakai ${j.jumlah_guru} tenaga pendidik — penghapusan akan ditolak sistem.`)
    if (j.jumlah_tugas > 0) details.push(`${j.jumlah_tugas} template tugas pada jabatan ini akan ikut terhapus.`)
    if (!(await confirm({
        title: `Hapus jabatan "${j.nama_jabatan}"?`,
        details,
        variant: 'danger',
        irreversible: true,
        confirmLabel: 'Ya, Hapus',
    }))) return
    // Backend yang menentukan (blokir bila dipakai / penggajian diproses) → hasil muncul sebagai toast.
    router.delete(route('admin.master.jabatan.destroy', j.id), { preserveScroll: true })
}
</script>

<style scoped>
.modal-enter-active {
    transition: all 0.2s ease;
}

.modal-leave-active {
    transition: all 0.15s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
<template>
    <AdminLayout title="Setting Jam Kerja" subtitle="Pengaturan">

        <Head title="Setting Jam Kerja" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Setting Jam Kerja</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    Jam kerja bisa berbeda tiap hari, termasuk shift overnight (lintas tengah malam)
                </p>
            </div>
            <Link :href="route('admin.smart-payroll.setting-gaji.jam-kerja.create')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Setting
            </Link>
        </div>

        <div class="space-y-4">
            <div v-for="s in settings" :key="s.id" :class="[
                'bg-white rounded-2xl border overflow-hidden transition-all',
                s.is_default ? 'border-indigo-300 ring-2 ring-indigo-100' : 'border-gray-200 hover:border-gray-300'
            ]">

                <!-- Header -->
                <div class="px-5 py-4 flex items-start justify-between border-b border-gray-50">
                    <div class="flex items-center gap-3">
                        <div :class="['w-10 h-10 rounded-xl flex items-center justify-center shrink-0',
                            s.is_default ? 'bg-indigo-100' : 'bg-gray-100']">
                            <svg class="w-5 h-5" :class="s.is_default ? 'text-indigo-600' : 'text-gray-500'" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-semibold text-gray-800">{{ s.nama }}</p>
                                <span v-if="s.is_default"
                                    class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">
                                    Default
                                </span>
                                <span v-if="s.gunakan_jadwal_per_hari"
                                    class="px-2 py-0.5 rounded-full bg-violet-100 text-violet-700 text-xs font-medium">
                                    Fleksibel per hari
                                </span>
                            </div>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ hariAktifCount(s) }} hari kerja ·
                                {{ formatDurasi(totalMenitMinggu(s)) }}/minggu
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1">
                        <button v-if="!s.is_default" @click="setDefault(s)"
                            class="p-2 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                            title="Jadikan default">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                            </svg>
                        </button>
                        <Link :href="route('admin.smart-payroll.setting-gaji.jam-kerja.edit', s.id)"
                            class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </Link>
                        <button @click="duplikat(s)"
                            class="p-2 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                            title="Duplikat setting">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </button>
                        <button v-if="!s.is_default" @click="hapus(s)"
                            class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Grid jadwal per hari -->
                <div class="px-5 py-4">
                    <div class="grid grid-cols-7 gap-2">
                        <div v-for="h in hariAll" :key="h.key"
                            :class="['rounded-xl p-2.5 text-center transition-colors',
                                isHariAktif(s, h.key) ? 'bg-gray-50 border border-gray-200' : 'bg-gray-50/40 opacity-40']">
                            <p class="text-xs font-semibold text-gray-500 mb-1">{{ h.short }}</p>
                            <template v-if="isHariAktif(s, h.key)">
                                <p class="text-xs font-bold text-gray-800">{{ getJam(s, h.key, 'masuk') }}</p>
                                <div class="w-full h-px bg-gray-300 my-1"></div>
                                <div class="flex items-center justify-center gap-1">
                                    <p class="text-xs font-bold text-gray-800">{{ getJam(s, h.key, 'pulang') }}</p>
                                    <span v-if="isOvernightHari(s, h.key)"
                                        class="text-amber-500 text-xs font-bold">+1</span>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">{{ getDurasi(s, h.key) }}</p>
                            </template>
                            <template v-else>
                                <p class="text-xs text-gray-300 mt-2">Libur</p>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!settings.length"
                class="bg-white rounded-2xl border border-gray-200 py-14 text-center text-sm text-gray-400">
                Belum ada setting jam kerja.
                <Link :href="route('admin.smart-payroll.setting-gaji.jam-kerja.create')"
                    class="text-indigo-500 underline ml-1">Buat
                    sekarang</Link>
            </div>
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

defineProps({
    settings: { type: Array, default: () => [] },
})

const hariAll = [
    { key: 'senin', short: 'Sen' }, { key: 'selasa', short: 'Sel' },
    { key: 'rabu', short: 'Rab' }, { key: 'kamis', short: 'Kam' },
    { key: 'jumat', short: "Jum" }, { key: 'sabtu', short: 'Sab' },
    { key: 'ahad', short: 'Ahd' },
]

function parseToMenit(jamStr) {
    if (!jamStr) return 0
    const [h, m] = jamStr.split(':').map(Number)
    return h * 60 + m
}

function hitungDurasiMenit(masuk, pulang) {
    const mMasuk = parseToMenit(masuk)
    const mPulang = parseToMenit(pulang)
    return mPulang <= mMasuk ? (mPulang + 1440) - mMasuk : mPulang - mMasuk
}

function formatDurasi(menit) {
    if (!menit) return '—'
    const jam = Math.floor(menit / 60)
    const min = menit % 60
    if (jam === 0) return `${min}m`
    return min === 0 ? `${jam}j` : `${jam}j ${min}m`
}

function isHariAktif(s, hari) {
    if (s.gunakan_jadwal_per_hari && s.jadwal_per_hari) {
        return s.jadwal_per_hari[hari]?.aktif ?? false
    }
    return s.hari_kerja?.includes(hari) ?? false
}

function getJam(s, hari, tipe) {
    if (s.gunakan_jadwal_per_hari && s.jadwal_per_hari) {
        const j = s.jadwal_per_hari[hari]
        return tipe === 'masuk' ? j?.jam_masuk : j?.jam_pulang
    }
    return tipe === 'masuk' ? s.jam_masuk : s.jam_pulang
}

function isOvernightHari(s, hari) {
    const masuk = getJam(s, hari, 'masuk')
    const pulang = getJam(s, hari, 'pulang')
    if (!masuk || !pulang) return false
    return parseToMenit(pulang) <= parseToMenit(masuk)
}

function getDurasi(s, hari) {
    const masuk = getJam(s, hari, 'masuk')
    const pulang = getJam(s, hari, 'pulang')
    if (!masuk || !pulang) return '—'
    return formatDurasi(hitungDurasiMenit(masuk, pulang))
}

function hariAktifCount(s) {
    return hariAll.filter(h => isHariAktif(s, h.key)).length
}

function totalMenitMinggu(s) {
    return hariAll.reduce((total, h) => {
        if (!isHariAktif(s, h.key)) return total
        const masuk = getJam(s, h.key, 'masuk')
        const pulang = getJam(s, h.key, 'pulang')
        return total + (masuk && pulang ? hitungDurasiMenit(masuk, pulang) : 0)
    }, 0)
}

function duplikat(s) {
    router.post(route('admin.smart-payroll.setting-gaji.jam-kerja.duplicate', s.id), {}, { preserveScroll: true })
}

function setDefault(s) {
    router.patch(route('admin.smart-payroll.setting-gaji.jam-kerja.set-default', s.id), {}, { preserveScroll: true })
}

function hapus(s) {
    confirm.value.ask(
        { title: 'Hapus Setting Jam Kerja?', message: `Setting "${s.nama}" akan dihapus permanen.`,
          variant: 'danger', confirmLabel: 'Ya, Hapus', irreversible: true },
        (done) => router.delete(route('admin.smart-payroll.setting-gaji.jam-kerja.destroy', s.id),
            { preserveScroll: true, onFinish: done }),
    )
}
</script>
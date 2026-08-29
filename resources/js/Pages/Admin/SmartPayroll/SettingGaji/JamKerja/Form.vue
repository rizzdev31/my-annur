<template>
    <AdminLayout :title="isEdit ? 'Edit Jam Kerja' : 'Tambah Setting Jam Kerja'" subtitle="Pengaturan">

        <Head :title="isEdit ? 'Edit Jam Kerja' : 'Tambah Setting Jam Kerja'" />

        <div class="flex items-center gap-4 mb-6">
            <Link :href="route('admin.smart-payroll.setting-gaji.jam-kerja.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div>
                <h2 class="text-xl font-semibold text-gray-900">
                    {{ isEdit ? 'Edit Setting Jam Kerja' : 'Tambah Setting Jam Kerja' }}
                </h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    Setting jam kerja bisa berbeda setiap hari, termasuk shift overnight (lintas tengah malam)
                </p>
            </div>
        </div>

        <div class="max-w-3xl">
            <form @submit.prevent="submit" class="space-y-4">

                <!-- Nama & Mode -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Nama Setting <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.nama" type="text" placeholder="cth: Jam Kerja Standar, Shift Malam Pondok"
                            :class="inputCls(form.errors.nama)" />
                        <p v-if="form.errors.nama" class="mt-1 text-xs text-red-500">{{ form.errors.nama }}</p>
                    </div>

                    <!-- Mode: global vs per hari -->
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" @click="form.gunakan_jadwal_per_hari = false" :class="[
                            'flex flex-col gap-1 px-4 py-3.5 rounded-xl border-2 text-left transition-all',
                            !form.gunakan_jadwal_per_hari
                                ? 'border-indigo-500 bg-indigo-50'
                                : 'border-gray-200 hover:border-gray-300'
                        ]">
                            <span class="text-sm font-semibold"
                                :class="!form.gunakan_jadwal_per_hari ? 'text-indigo-700' : 'text-gray-700'">
                                Jam Kerja Global
                            </span>
                            <span class="text-xs"
                                :class="!form.gunakan_jadwal_per_hari ? 'text-indigo-500' : 'text-gray-400'">
                                Jam masuk/pulang sama setiap hari
                            </span>
                        </button>
                        <button type="button" @click="form.gunakan_jadwal_per_hari = true" :class="[
                            'flex flex-col gap-1 px-4 py-3.5 rounded-xl border-2 text-left transition-all',
                            form.gunakan_jadwal_per_hari
                                ? 'border-indigo-500 bg-indigo-50'
                                : 'border-gray-200 hover:border-gray-300'
                        ]">
                            <span class="text-sm font-semibold"
                                :class="form.gunakan_jadwal_per_hari ? 'text-indigo-700' : 'text-gray-700'">
                                Jadwal Per Hari
                            </span>
                            <span class="text-xs"
                                :class="form.gunakan_jadwal_per_hari ? 'text-indigo-500' : 'text-gray-400'">
                                Setiap hari bisa jam berbeda + overnight
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Mode Global -->
                <div v-if="!form.gunakan_jadwal_per_hari"
                    class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-800">Konfigurasi Jam Kerja Global</h3>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Jam Masuk <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.jam_masuk" type="time" :class="inputCls()" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Jam Pulang <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.jam_pulang" type="time" :class="inputCls()" />
                            <p v-if="isOvernightGlobal" class="text-xs text-amber-600 mt-1">
                                ⏱ Overnight — pulang hari berikutnya
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Toleransi Terlambat
                            </label>
                            <div class="relative">
                                <input v-model.number="form.toleransi_terlambat" type="number" min="0" max="120"
                                    :class="inputCls()" />
                                <span
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">menit</span>
                            </div>
                        </div>
                    </div>

                    <!-- Durasi -->
                    <div v-if="durasiGlobalMenit > 0"
                        class="px-4 py-2.5 bg-gray-50 rounded-xl text-sm text-gray-600 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Durasi: <strong class="text-indigo-700 ml-1">{{ formatDurasi(durasiGlobalMenit) }}</strong>
                        <span v-if="isOvernightGlobal" class="text-amber-600 text-xs">(lintas tengah malam)</span>
                    </div>

                    <!-- Hari kerja global -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Hari Kerja</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="h in hariAll" :key="h.key" type="button" @click="toggleHariGlobal(h.key)"
                                :class="[
                                    'px-4 py-2 rounded-xl text-sm font-medium border-2 transition-all',
                                    form.hari_kerja.includes(h.key)
                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                        : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                ]">
                                {{ h.label }}
                            </button>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">
                            {{ form.hari_kerja.length }} hari kerja · estimasi
                            {{ formatDurasi(durasiGlobalMenit * form.hari_kerja.length) }}/minggu
                        </p>
                    </div>
                </div>

                <!-- Mode Per Hari -->
                <div v-if="form.gunakan_jadwal_per_hari"
                    class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-800">Jadwal Per Hari</h3>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Aktifkan hari kerja dan set jam masing-masing. Jam pulang lebih kecil dari jam masuk = shift
                            overnight (lintas tengah malam).
                        </p>
                    </div>

                    <div class="divide-y divide-gray-50">
                        <div v-for="h in hariAll" :key="h.key" class="px-5 py-4">
                            <div class="flex items-start gap-4">
                                <!-- Toggle hari aktif -->
                                <div class="flex items-center gap-3 w-28 shrink-0 pt-0.5">
                                    <button type="button" @click="toggleHariPerHari(h.key)" :class="[
                                        'relative rounded-full transition-colors flex-shrink-0',
                                        form.jadwal_per_hari[h.key]?.aktif ? 'bg-indigo-600' : 'bg-gray-300'
                                    ]" style="height:22px;width:40px;">
                                        <span :class="[
                                            'absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200',
                                            form.jadwal_per_hari[h.key]?.aktif ? 'translate-x-5' : 'translate-x-0.5'
                                        ]"></span>
                                    </button>
                                    <span :class="[
                                        'text-sm font-semibold',
                                        form.jadwal_per_hari[h.key]?.aktif ? 'text-gray-800' : 'text-gray-400'
                                    ]">{{ h.label }}</span>
                                </div>

                                <!-- Config hari ini -->
                                <Transition name="slide">
                                    <div v-if="form.jadwal_per_hari[h.key]?.aktif"
                                        class="flex-1 grid grid-cols-3 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1">Jam
                                                Masuk</label>
                                            <input v-model="form.jadwal_per_hari[h.key].jam_masuk" type="time"
                                                class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 bg-white" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1">
                                                Jam Pulang
                                                <span v-if="isOvernightHari(h.key)" class="text-amber-500 ml-1">+1
                                                    hari</span>
                                            </label>
                                            <input v-model="form.jadwal_per_hari[h.key].jam_pulang" type="time" :class="[
                                                'w-full px-3 py-2 rounded-xl border text-sm focus:outline-none focus:ring-2 bg-white',
                                                isOvernightHari(h.key)
                                                    ? 'border-amber-300 focus:border-amber-500 focus:ring-amber-100'
                                                    : 'border-gray-200 focus:border-indigo-500 focus:ring-indigo-100'
                                            ]" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1">Toleransi
                                                (menit)</label>
                                            <input v-model.number="form.jadwal_per_hari[h.key].toleransi" type="number"
                                                min="0" max="120"
                                                class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 bg-white" />
                                        </div>

                                        <!-- Info durasi -->
                                        <div class="col-span-3">
                                            <div :class="[
                                                'inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs',
                                                isOvernightHari(h.key) ? 'bg-amber-50 text-amber-700' : 'bg-gray-50 text-gray-600'
                                            ]">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                {{ formatDurasi(durasiHari(h.key)) }}
                                                <span v-if="isOvernightHari(h.key)">· Shift overnight</span>
                                            </div>
                                        </div>
                                    </div>
                                </Transition>

                                <!-- Libur label -->
                                <div v-if="!form.jadwal_per_hari[h.key]?.aktif" class="flex-1 flex items-center h-9">
                                    <span class="text-sm text-gray-400 italic">Libur / tidak bekerja</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ringkasan total -->
                    <div class="px-5 py-4 border-t border-gray-100 bg-gray-50/30">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500">
                                {{ hariAktifCount }} hari kerja aktif
                            </span>
                            <span class="font-semibold text-indigo-700">
                                Total: {{ formatDurasi(totalMenitPerMinggu) }} / minggu
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Tombol aksi -->
                <div class="flex gap-3">
                    <Link :href="route('admin.smart-payroll.setting-gaji.jam-kerja.index')"
                        class="flex-1 py-2.5 text-center rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Batal
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60 transition-colors">
                        {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Buat Setting') }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed, reactive } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    setting: { type: Object, default: null },
})

const isEdit = computed(() => !!props.setting)

const hariAll = [
    { key: 'senin', label: 'Senin' },
    { key: 'selasa', label: 'Selasa' },
    { key: 'rabu', label: 'Rabu' },
    { key: 'kamis', label: 'Kamis' },
    { key: 'jumat', label: "Jum'at" },
    { key: 'sabtu', label: 'Sabtu' },
    { key: 'ahad', label: 'Ahad' },
]

// Default jadwal per hari
function defaultJadwalPerHari(existing = null) {
    const result = {}
    hariAll.forEach(h => {
        const ex = existing?.[h.key]
        result[h.key] = {
            aktif: ex?.aktif ?? (h.key !== 'ahad'),
            jam_masuk: ex?.jam_masuk ?? '07:30',
            jam_pulang: ex?.jam_pulang ?? '14:30',
            toleransi: ex?.toleransi ?? 15,
        }
    })
    return result
}

const form = useForm({
    nama: props.setting?.nama ?? '',
    gunakan_jadwal_per_hari: props.setting?.gunakan_jadwal_per_hari ?? false,
    // Global
    jam_masuk: props.setting?.jam_masuk ?? '07:30',
    jam_pulang: props.setting?.jam_pulang ?? '14:30',
    toleransi_terlambat: props.setting?.toleransi_terlambat ?? 15,
    hari_kerja: props.setting?.hari_kerja ?? ['senin', 'selasa', 'rabu', 'kamis', 'jumat'],
    total_jam_kerja_sehari: props.setting?.total_jam_kerja_sehari ?? 420,
    // Per hari
    jadwal_per_hari: defaultJadwalPerHari(props.setting?.jadwal_per_hari),
})

// ── Helpers ───────────────────────────────────────────────────────────────────

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

function isOvernightTime(masuk, pulang) {
    return parseToMenit(pulang) <= parseToMenit(masuk)
}

function formatDurasi(menit) {
    if (!menit) return '0 menit'
    const jam = Math.floor(menit / 60)
    const min = menit % 60
    if (jam === 0) return `${min} menit`
    if (min === 0) return `${jam} jam`
    return `${jam}j ${min}m`
}

// ── Computed ──────────────────────────────────────────────────────────────────

const durasiGlobalMenit = computed(() =>
    form.jam_masuk && form.jam_pulang
        ? hitungDurasiMenit(form.jam_masuk, form.jam_pulang)
        : 0
)

const isOvernightGlobal = computed(() =>
    form.jam_masuk && form.jam_pulang
        ? isOvernightTime(form.jam_masuk, form.jam_pulang)
        : false
)

function isOvernightHari(hari) {
    const j = form.jadwal_per_hari[hari]
    if (!j?.aktif || !j.jam_masuk || !j.jam_pulang) return false
    return isOvernightTime(j.jam_masuk, j.jam_pulang)
}

function durasiHari(hari) {
    const j = form.jadwal_per_hari[hari]
    if (!j?.aktif || !j.jam_masuk || !j.jam_pulang) return 0
    return hitungDurasiMenit(j.jam_masuk, j.jam_pulang)
}

const hariAktifCount = computed(() =>
    Object.values(form.jadwal_per_hari).filter(j => j.aktif).length
)

const totalMenitPerMinggu = computed(() => {
    if (!form.gunakan_jadwal_per_hari) {
        return durasiGlobalMenit.value * form.hari_kerja.length
    }
    return hariAll.reduce((total, h) => total + durasiHari(h.key), 0)
})

// ── Actions ───────────────────────────────────────────────────────────────────

function toggleHariGlobal(hari) {
    const idx = form.hari_kerja.indexOf(hari)
    idx >= 0 ? form.hari_kerja.splice(idx, 1) : form.hari_kerja.push(hari)
}

function toggleHariPerHari(hari) {
    if (!form.jadwal_per_hari[hari]) {
        form.jadwal_per_hari[hari] = { aktif: true, jam_masuk: '07:30', jam_pulang: '14:30', toleransi: 15 }
    } else {
        form.jadwal_per_hari[hari].aktif = !form.jadwal_per_hari[hari].aktif
    }
}

function inputCls(e) {
    const b = 'w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 transition-all bg-white'
    return e ? `${b} border-red-300 focus:ring-red-100` : `${b} border-gray-200 focus:border-indigo-500 focus:ring-indigo-100`
}

function submit() {
    // Update total_jam_kerja_sehari sebelum submit
    if (!form.gunakan_jadwal_per_hari) {
        form.total_jam_kerja_sehari = durasiGlobalMenit.value
    }

    isEdit.value
        ? form.put(route('admin.smart-payroll.setting-gaji.jam-kerja.update', props.setting.id))
        : form.post(route('admin.smart-payroll.setting-gaji.jam-kerja.store'))
}
</script>

<style scoped>
.slide-enter-active,
.slide-leave-active {
    transition: all 0.2s ease;
    overflow: hidden;
}

.slide-enter-from,
.slide-leave-to {
    opacity: 0;
    max-height: 0;
}

.slide-enter-to,
.slide-leave-from {
    max-height: 200px;
}
</style>
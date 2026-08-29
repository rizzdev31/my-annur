<template>
    <AdminLayout title="Monitoring Kinerja" subtitle="Smart Payroll">

        <Head title="Monitoring Kinerja" />

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Monitoring Kinerja</h2>
                <p class="text-sm text-gray-400 mt-0.5">Log kerja harian & skor efisiensi tenaga pendidik</p>
            </div>
            <div class="flex items-center gap-2">
                <!-- Filter bulan/tahun -->
                <select v-model="filterBulan" @change="doFilter"
                    class="px-3 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:border-indigo-500 bg-white">
                    <option v-for="b in bulanList" :key="b.value" :value="b.value">{{ b.label }}</option>
                </select>
                <select v-model="filterTahun" @change="doFilter"
                    class="px-3 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:border-indigo-500 bg-white">
                    <option v-for="t in tahunList" :key="t" :value="t">{{ t }}</option>
                </select>
                <button @click="hitungRekap" :disabled="menghitung"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors disabled:opacity-60">
                    <svg class="w-4 h-4" :class="menghitung ? `animate-spin` : ``" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    {{ menghitung ? 'Menghitung...' : 'Hitung Rekap' }}
                </button>
                <button @click="resetSemua" :disabled="mereset"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-semibold transition-colors disabled:opacity-60">
                    {{ mereset ? 'Mereset…' : 'Reset Semua' }}
                </button>
            </div>
        </div>

        <!-- Summary strip -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                <p class="text-2xl font-bold text-gray-900">{{ ringkasan.total_log_bulan_ini ?? 0 }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Log kerja bulan ini</p>
            </div>
            <div class="bg-white rounded-xl border border-amber-200 bg-amber-50/30 px-4 py-3">
                <p class="text-2xl font-bold text-amber-600">{{ ringkasan.log_pending_verifikasi ?? 0 }}</p>
                <p class="text-xs text-amber-500 mt-0.5">Pending verifikasi</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                <p class="text-2xl font-bold text-indigo-600">{{ Math.round(ringkasan.rata_skor_total ?? 0) }}%</p>
                <p class="text-xs text-gray-400 mt-0.5">Rata-rata skor kinerja</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-2xl font-bold text-emerald-600">{{ ringkasan.guru_sangat_baik ?? 0 }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Sangat baik</p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold text-red-500">{{ ringkasan.guru_perlu_perhatian ?? 0 }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Perlu perhatian</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-5 gap-5">

            <!-- ── Kolom Kiri: Log Pending Verifikasi ─────────────────────── -->
            <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Log Pending Verifikasi</h3>
                        <p class="text-xs text-gray-400 mt-0.5">{{ logPending.length }} log menunggu review</p>
                    </div>
                    <button v-if="logPending.length > 0" @click="verifikasiBulk"
                        class="px-3 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors">
                        ✓ Setujui Semua
                    </button>
                </div>

                <div class="divide-y divide-gray-50 max-h-[500px] overflow-y-auto">
                    <div v-for="log in logPending" :key="log.id" class="p-4 hover:bg-gray-50/50 transition-colors">
                        <!-- Header log -->
                        <div class="flex items-start gap-3 mb-2.5">
                            <img v-if="log.foto_guru" :src="log.foto_guru"
                                class="w-8 h-8 rounded-full object-cover shrink-0 mt-0.5" />
                            <div v-else
                                class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center shrink-0 mt-0.5">
                                <span class="text-xs font-bold text-indigo-700">{{ log.nama_guru?.charAt(0) }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-gray-800 truncate">{{ log.nama_guru }}</p>
                                    <p class="text-xs text-gray-400 shrink-0">{{ log.tanggal }}</p>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5">{{ log.jabatan }}</p>
                            </div>
                        </div>

                        <!-- Konten log -->
                        <div class="bg-gray-50 rounded-xl px-3 py-2.5 mb-3">
                            <p class="text-sm font-medium text-gray-800">{{ log.judul }}</p>
                            <div class="flex items-center gap-3 mt-1">
                                <span class="text-xs text-gray-400">{{ log.kategori }}</span>
                                <span v-if="log.durasi_format" class="text-xs text-indigo-600">⏱ {{ log.durasi_format
                                    }}</span>
                            </div>
                            <p v-if="log.deskripsi" class="text-xs text-gray-500 mt-1 line-clamp-2">{{ log.deskripsi }}
                            </p>
                        </div>

                        <!-- Aksi -->
                        <div class="flex gap-2">
                            <button @click="bukaModalVerifikasi(log)"
                                class="flex-1 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-semibold transition-colors">
                                ✓ Verifikasi
                            </button>
                            <button @click="bukaModalTolak(log)"
                                class="flex-1 py-2 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold transition-colors">
                                ✕ Tolak
                            </button>
                        </div>
                    </div>

                    <div v-if="!logPending.length" class="py-12 text-center">
                        <p class="text-3xl mb-2">✅</p>
                        <p class="text-sm text-gray-500">Semua log sudah diverifikasi</p>
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-gray-100">
                    <Link :href="route('admin.smart-payroll.kinerja.log-kerja')"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                        Lihat semua log kerja →
                    </Link>
                </div>
            </div>

            <!-- ── Kolom Kanan: Rekap Kinerja Semua Guru ─────────────────── -->
            <div class="xl:col-span-3 bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800">Rekap Kinerja Bulanan</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Skor tidak mempengaruhi gaji — hanya untuk evaluasi</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/50">
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                    Pendidik</th>
                                <th
                                    class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                    Log</th>
                                <th
                                    class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">
                                    Keaktifan</th>
                                <th
                                    class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">
                                    Penugasan</th>
                                <th
                                    class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                    Skor</th>
                                <th
                                    class="px-5 py-3 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                    Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="r in rekapList" :key="r.id" class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <img v-if="r.foto" :src="r.foto"
                                            class="w-7 h-7 rounded-full object-cover shrink-0" />
                                        <div v-else
                                            class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center shrink-0">
                                            <span class="text-xs font-bold text-indigo-700">{{ r.nama?.charAt(0)
                                                }}</span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-800">{{ r.nama }}</p>
                                            <p class="text-xs text-gray-400">{{ r.jabatan }}</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-5 py-3.5 text-center">
                                    <p class="text-sm font-semibold text-gray-700">{{ r.total_log }}</p>
                                    <p class="text-xs text-gray-400">{{ r.total_durasi_jam }}j</p>
                                </td>

                                <td class="px-5 py-3.5 hidden lg:table-cell">
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="text-sm font-semibold text-gray-700">{{ r.skor_keaktifan }}%</span>
                                        <div class="w-16 bg-gray-100 rounded-full h-1.5">
                                            <div class="h-1.5 rounded-full bg-indigo-400 transition-all"
                                                :style="{ width: r.skor_keaktifan + '%' }"></div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-5 py-3.5 hidden lg:table-cell">
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="text-sm font-semibold text-gray-700">{{ r.skor_penugasan }}%</span>
                                        <div class="w-16 bg-gray-100 rounded-full h-1.5">
                                            <div class="h-1.5 rounded-full bg-teal-400 transition-all"
                                                :style="{ width: r.skor_penugasan + '%' }"></div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-5 py-3.5 text-center">
                                    <span
                                        :class="['inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold', r.badge_skor.class]">
                                        {{ r.skor_total }}%
                                    </span>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ r.label_skor }}</p>
                                </td>

                                <td class="px-5 py-3.5 text-right">
                                    <Link :href="route('admin.smart-payroll.kinerja.detail-guru', r.guru_id)"
                                        class="p-2 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors inline-block">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </Link>
                                </td>
                            </tr>

                            <tr v-if="!rekapList.length">
                                <td colspan="6" class="py-12 text-center">
                                    <p class="text-sm text-gray-400">Belum ada rekap. Klik "Hitung Rekap" untuk
                                        kalkulasi.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── Modal Verifikasi ─────────────────────────────────────────────── -->
        <Transition name="modal">
            <div v-if="showModalVerifikasi" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showModalVerifikasi = false" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <h3 class="font-semibold text-gray-900 mb-1">Verifikasi Log Kerja</h3>
                    <p class="text-sm text-gray-500 mb-4">{{ aksiTarget?.judul }}</p>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Catatan (opsional)</label>
                    <textarea v-model="catatanAksi" rows="2" placeholder="Catatan verifikasi..."
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 resize-none mb-4">
                    </textarea>
                    <div class="flex gap-3">
                        <button @click="showModalVerifikasi = false"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600">Batal</button>
                        <button @click="doVerifikasi" :disabled="loading"
                            class="flex-1 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold disabled:opacity-60">
                            {{ loading ? 'Menyimpan...' : 'Verifikasi' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- ── Modal Tolak ──────────────────────────────────────────────────── -->
        <Transition name="modal">
            <div v-if="showModalTolak" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showModalTolak = false" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <h3 class="font-semibold text-gray-900 mb-1">Tolak Log Kerja</h3>
                    <p class="text-sm text-gray-500 mb-4">{{ aksiTarget?.judul }}</p>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Alasan Penolakan <span class="text-red-500">*</span>
                    </label>
                    <textarea v-model="catatanAksi" rows="3" placeholder="Tulis alasan penolakan..."
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100 resize-none mb-4">
                    </textarea>
                    <div class="flex gap-3">
                        <button @click="showModalTolak = false"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600">Batal</button>
                        <button @click="doTolak" :disabled="!catatanAksi || loading"
                            class="flex-1 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-semibold disabled:opacity-60">
                            {{ loading ? 'Menyimpan...' : 'Tolak' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    logPending: { type: Array, default: () => [] },
    rekapList: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
    bulan: { type: Number, default: () => new Date().getMonth() + 1 },
    tahun: { type: Number, default: () => new Date().getFullYear() },
    filters: { type: Object, default: () => ({}) },
})

// ── Filter ────────────────────────────────────────────────────────────────────
const filterBulan = ref(props.bulan)
const filterTahun = ref(props.tahun)

const tahunList = computed(() => {
    const y = new Date().getFullYear(); return [y, y - 1, y - 2]
})

const bulanList = [
    { value: 1, label: 'Januari' }, { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' }, { value: 4, label: 'April' },
    { value: 5, label: 'Mei' }, { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' }, { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' }, { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' }, { value: 12, label: 'Desember' },
]

function doFilter() {
    router.get(route('admin.smart-payroll.kinerja.index'), {
        bulan: filterBulan.value,
        tahun: filterTahun.value,
    }, { preserveState: true, replace: true })
}

// ── Hitung rekap ──────────────────────────────────────────────────────────────
const menghitung = ref(false)
function hitungRekap() {
    menghitung.value = true
    router.post(route('admin.smart-payroll.kinerja.hitung-rekap'), {
        bulan: filterBulan.value,
        tahun: filterTahun.value,
    }, {
        onFinish: () => menghitung.value = false,
    })
}

const mereset = ref(false)
function resetSemua() {
    if (!window.confirm('Reset kinerja SEMUA guru untuk periode ini? Skor dihitung ulang dari data (override manual dihapus).')) return
    mereset.value = true
    router.post(route('admin.smart-payroll.kinerja.reset-semua'), {
        bulan: filterBulan.value,
        tahun: filterTahun.value,
    }, {
        onFinish: () => mereset.value = false,
    })
}

// ── Verifikasi bulk ───────────────────────────────────────────────────────────
function verifikasiBulk() {
    router.post(route('admin.smart-payroll.kinerja.verifikasi-bulk'), {
        bulan: filterBulan.value,
        tahun: filterTahun.value,
    })
}

// ── Modal verifikasi / tolak ──────────────────────────────────────────────────
const showModalVerifikasi = ref(false)
const showModalTolak = ref(false)
const aksiTarget = ref(null)
const catatanAksi = ref('')
const loading = ref(false)

function bukaModalVerifikasi(log) {
    aksiTarget.value = log
    catatanAksi.value = ''
    showModalVerifikasi.value = true
}

function bukaModalTolak(log) {
    aksiTarget.value = log
    catatanAksi.value = ''
    showModalTolak.value = true
}

function doVerifikasi() {
    loading.value = true
    router.post(route('admin.smart-payroll.kinerja.log.verifikasi', aksiTarget.value.id), {
        catatan: catatanAksi.value
    }, {
        onSuccess: () => showModalVerifikasi.value = false,
        onFinish: () => loading.value = false,
    })
}

function doTolak() {
    if (!catatanAksi.value) return
    loading.value = true
    router.post(route('admin.smart-payroll.kinerja.log.tolak', aksiTarget.value.id), {
        catatan: catatanAksi.value
    }, {
        onSuccess: () => showModalTolak.value = false,
        onFinish: () => loading.value = false,
    })
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
<template>
    <AdminLayout :title="`Kinerja — ${guru.nama}`" subtitle="Smart Payroll">
        <Head :title="`Kinerja ${guru.nama}`" />

        <!-- Header -->
        <div class="flex items-center gap-3 mb-5">
            <Link :href="route('admin.smart-payroll.kinerja.index', { bulan, tahun })"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </Link>
            <img v-if="guru.foto" :src="guru.foto" class="w-11 h-11 rounded-full object-cover" />
            <div v-else class="w-11 h-11 rounded-full bg-indigo-100 flex items-center justify-center font-bold text-indigo-600">
                {{ guru.nama?.charAt(0) }}
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-lg font-bold text-gray-800 truncate">{{ guru.nama }}</h1>
                <p class="text-xs text-gray-400">{{ guru.jabatan }} · {{ namaBulan }} {{ tahun }}</p>
            </div>
            <span class="px-3 py-1.5 rounded-xl text-sm font-bold border" :class="gradeCls(rekap.grade)">
                {{ rekap.skor_total }} · {{ rekap.label_grade }}
            </span>
            <Link :href="route('admin.smart-payroll.kinerja.raport', { guru: guru.id, bulan, tahun })"
                class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gray-900 text-white text-sm font-semibold hover:bg-black">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                Cetak Raport
            </Link>
        </div>

        <!-- Skor komponen -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            <div class="bg-indigo-600 rounded-2xl p-4 text-white">
                <div class="text-[11px] uppercase tracking-wide text-indigo-200">Skor Total</div>
                <div class="text-2xl font-extrabold mt-1">{{ rekap.skor_total }}</div>
            </div>
            <div v-for="k in komponenList" :key="k.label" class="bg-white rounded-2xl border border-gray-100 p-4">
                <div class="text-[11px] uppercase tracking-wide text-gray-400">{{ k.label }} · {{ k.bobot }}%</div>
                <div class="text-xl font-bold text-gray-800 mt-1">{{ k.skor }}</div>
                <div class="text-[11px] text-gray-400">kontribusi {{ k.kontribusi }}</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 p-4">
                <div class="text-[11px] uppercase tracking-wide text-gray-400">Penyesuaian Piket</div>
                <div class="text-xl font-bold mt-1" :class="piketAdj >= 0 ? 'text-emerald-600' : 'text-red-500'">{{ piketAdj > 0 ? '+' : '' }}{{ piketAdj }}</div>
                <div class="text-[11px] text-gray-400">apresiasi (+) / catatan (−)</div>
            </div>
        </div>

        <!-- Rincian tugas jabatan -->
        <div class="bg-white rounded-2xl border border-gray-100 p-4 mb-5 text-sm">
            <p class="font-semibold text-gray-700 mb-2">Rincian Tugas</p>
            <div class="grid grid-cols-2 gap-y-1 text-gray-500">
                <span>Tugas jabatan disetujui</span>
                <span class="text-right text-gray-800 font-medium">{{ rekap.komponen.tugas.jabatan_disetujui }} / {{ rekap.komponen.tugas.jabatan_total }}</span>
                <span>Penugasan tambahan selesai</span>
                <span class="text-right text-gray-800 font-medium">{{ rekap.komponen.tugas.penugasan_selesai }} / {{ rekap.komponen.tugas.penugasan_total }}</span>
                <span>Hari kerja · hadir</span>
                <span class="text-right text-gray-800 font-medium">{{ rekap.komponen.absensi.hari_kerja }} · {{ rekap.komponen.absensi.hadir }}</span>
            </div>
        </div>

        <!-- Banner perlu perhatian -->
        <div v-if="kinerjaBuruk"
            class="bg-red-50 border border-red-200 rounded-2xl p-4 mb-5 flex items-start gap-3">
            <span class="text-red-500 text-lg shrink-0">⚠️</span>
            <p class="text-sm text-red-700">
                Skor kinerja <b>{{ rekap.skor_total }}</b> di bawah ambang
                ({{ setting_aktif.grade_c }}). Pertimbangkan memberi punishment / evaluasi.
            </p>
        </div>

        <!-- KONTROL SKOR (override / reset — koreksi manual & demo) -->
        <div class="bg-white rounded-2xl border border-indigo-100 p-5 mb-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-1">Kontrol Skor Kinerja</h3>
            <p class="text-xs text-gray-400 mb-4">Override skor manual (dikunci agar tak tertimpa hitung ulang), atau reset ke skor terhitung dari data.</p>
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-[11px] text-gray-500 mb-1">Skor (0–100)</label>
                    <input v-model.number="overrideForm.skor_total" type="number" min="0" max="100" step="0.5"
                        class="w-28 rounded-lg border-gray-200 text-sm" />
                </div>
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-[11px] text-gray-500 mb-1">Catatan (opsional)</label>
                    <input v-model="overrideForm.catatan" type="text" placeholder="Alasan override…"
                        class="w-full rounded-lg border-gray-200 text-sm" />
                </div>
                <button @click="override" :disabled="overrideForm.processing"
                    class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
                    Override &amp; Kunci
                </button>
                <button @click="resetKinerja" :disabled="overrideForm.processing"
                    class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200">
                    Reset (hitung ulang)
                </button>
            </div>
        </div>

        <!-- PUNISHMENT -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-4">Punishment Kinerja — {{ namaBulan }} {{ tahun }}</h3>

            <!-- Daftar existing -->
            <div v-if="punishments.length" class="space-y-2 mb-4">
                <div v-for="p in punishments" :key="p.id"
                    class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 bg-gray-50/50">
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold border" :class="jenisCls(p.jenis)">
                        {{ p.jenis_label }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-700 truncate">{{ p.catatan }}</p>
                        <p class="text-[11px] text-gray-400">
                            {{ p.oleh }} · {{ p.tanggal }}
                            <span v-if="p.nominal"> · {{ rupiah(p.nominal) }}</span>
                            <span v-if="p.jabatan"> · {{ p.jabatan }}</span>
                        </p>
                    </div>
                    <button @click="hapus(p)" class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                </div>
            </div>
            <p v-else class="text-xs text-gray-400 mb-4">Belum ada punishment pada periode ini.</p>

            <!-- Form beri punishment -->
            <div class="border-t border-gray-100 pt-4 space-y-3">
                <div class="grid grid-cols-2 gap-2">
                    <button v-for="j in jenisOpts" :key="j.value" type="button" @click="form.jenis = j.value"
                        class="px-3 py-2.5 rounded-xl border-2 text-left text-sm font-medium transition-all"
                        :class="form.jenis === j.value ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600'">
                        {{ j.label }}
                    </button>
                </div>

                <div v-if="form.jenis === 'potongan'">
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Nominal Potongan</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">Rp</span>
                        <input v-model.number="form.nominal" type="number" min="0" step="1000"
                            class="w-full pl-8 pr-3 py-2 rounded-xl border border-gray-200 text-sm" />
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">Dipotong otomatis di penggajian periode {{ namaBulan }} {{ tahun }}.</p>
                </div>

                <div v-if="form.jenis === 'pencopotan'">
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Jabatan yang Dicabut</label>
                    <select v-model.number="form.jabatan_id" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm">
                        <option :value="null" disabled>Pilih jabatan</option>
                        <option v-for="j in jabatan_diemban" :key="j.jabatan_id" :value="j.jabatan_id">
                            {{ j.nama }}{{ j.utama ? ' (utama)' : '' }}
                        </option>
                    </select>
                    <p class="text-[11px] text-red-500 mt-1">Jabatan akan diakhiri (berlaku selesai hari ini).</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Catatan / Alasan *</label>
                    <textarea v-model="form.catatan" rows="2" placeholder="Alasan punishment / kalimat evaluasi…"
                        class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm"></textarea>
                </div>

                <button @click="simpan" :disabled="!valid || form.processing"
                    class="px-5 py-2 rounded-xl text-sm font-semibold text-white bg-red-600 hover:bg-red-700 disabled:opacity-50">
                    {{ form.processing ? 'Menyimpan…' : 'Beri Punishment' }}
                </button>
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

const props = defineProps({
    guru: { type: Object, default: () => ({}) },
    rekap: { type: Object, default: () => ({ komponen: { absensi: {}, tugas: {}, administrasi: {}, piket: {} } }) },
    logs: { type: Array, default: () => [] },
    riwayat: { type: Array, default: () => [] },
    setting_aktif: { type: Object, default: () => ({}) },
    punishments: { type: Array, default: () => [] },
    jabatan_diemban: { type: Array, default: () => [] },
    bulan: { type: Number, default: 1 },
    tahun: { type: Number, default: 2026 },
})

const bulanNama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
const namaBulan = computed(() => bulanNama[props.bulan] ?? '')

const komponenList = computed(() => [
    { label: 'Absensi', ...props.rekap.komponen.absensi },
    { label: 'Tugas', ...props.rekap.komponen.tugas },
    { label: 'Administrasi', ...props.rekap.komponen.administrasi },
])
// Piket = penyesuaian (+/−), bukan komponen berbobot.
const piketAdj = computed(() => Number(props.rekap.komponen?.piket?.penyesuaian ?? 0))

const kinerjaBuruk = computed(() =>
    Number(props.rekap.skor_total) < Number(props.setting_aktif.grade_c ?? 0))

const jenisOpts = [
    { value: 'evaluasi', label: 'Catatan Evaluasi' },
    { value: 'peringatan', label: 'Surat Peringatan' },
    { value: 'potongan', label: 'Potongan Gaji' },
    { value: 'pencopotan', label: 'Pencopotan Jabatan' },
]

const form = useForm({
    bulan: props.bulan,
    tahun: props.tahun,
    jenis: 'evaluasi',
    nominal: null,
    jabatan_id: null,
    catatan: '',
    skor_kinerja: props.rekap.skor_total,
})

const valid = computed(() => {
    if (!form.catatan?.trim()) return false
    if (form.jenis === 'potongan' && !(form.nominal > 0)) return false
    if (form.jenis === 'pencopotan' && !form.jabatan_id) return false
    return true
})

function simpan() {
    form.post(route('admin.smart-payroll.kinerja.punishment.store', props.guru.id), {
        preserveScroll: true,
        onSuccess: () => { form.reset('nominal', 'jabatan_id', 'catatan'); form.jenis = 'evaluasi' },
    })
}

async function hapus(p) {
    if (!(await confirm({ title: 'Hapus punishment ini?', variant: 'danger', confirmLabel: 'Ya, Hapus' }))) return
    router.delete(route('admin.smart-payroll.kinerja.punishment.destroy', p.id), { preserveScroll: true })
}

const overrideForm = useForm({
    bulan: props.bulan,
    tahun: props.tahun,
    skor_total: props.rekap.skor_total,
    catatan: '',
})
function override() {
    overrideForm.post(route('admin.smart-payroll.kinerja.override', props.guru.id), { preserveScroll: true })
}
async function resetKinerja() {
    if (!(await confirm({ title: 'Reset kinerja guru ini?', message: 'Skor dihitung ulang dari data (override dihapus).', confirmLabel: 'Ya, Reset' }))) return
    router.post(route('admin.smart-payroll.kinerja.reset', props.guru.id), { bulan: props.bulan, tahun: props.tahun }, { preserveScroll: true })
}

function rupiah(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }

function gradeCls(g) {
    return { A: 'bg-emerald-50 text-emerald-700 border-emerald-200', B: 'bg-blue-50 text-blue-700 border-blue-200',
        C: 'bg-amber-50 text-amber-700 border-amber-200', D: 'bg-orange-50 text-orange-700 border-orange-200',
        E: 'bg-red-50 text-red-700 border-red-200' }[g] ?? 'bg-gray-50 text-gray-600 border-gray-200'
}
function jenisCls(j) {
    return { potongan: 'bg-red-50 text-red-700 border-red-200', evaluasi: 'bg-blue-50 text-blue-700 border-blue-200',
        peringatan: 'bg-amber-50 text-amber-700 border-amber-200', pencopotan: 'bg-gray-800 text-white border-gray-800' }[j]
        ?? 'bg-gray-50 text-gray-600 border-gray-200'
}
</script>

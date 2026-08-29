<template>
    <AdminLayout title="Raport Kinerja" subtitle="Kinerja">
        <Head :title="`Raport Kinerja — ${guru.nama}`" />

        <!-- Toolbar (tak ikut cetak) -->
        <div class="no-print flex items-center justify-between mb-4">
            <Link :href="route('admin.smart-payroll.kinerja.detail-guru', { guru: guru.id, bulan: periode.bulan, tahun: periode.tahun })"
                class="text-sm text-gray-500 hover:text-gray-700">← Kembali</Link>
            <button @click="cetak" class="px-4 py-2 rounded-xl bg-gray-900 text-white text-sm font-semibold hover:bg-black">
                Cetak / Simpan PDF
            </button>
        </div>

        <!-- Lembar raport -->
        <div id="raport" class="mx-auto max-w-[820px] bg-white rounded-2xl border border-gray-200 print:border-0 print:rounded-none overflow-hidden">
            <!-- Header -->
            <div class="px-8 pt-8 pb-6 flex items-start justify-between gap-6 border-b border-gray-100">
                <div>
                    <p class="text-[11px] font-semibold tracking-[0.2em] text-gray-400 uppercase">{{ lembaga }}</p>
                    <h1 class="text-2xl font-bold text-gray-900 mt-1">Raport Kinerja</h1>
                    <p class="text-sm text-gray-500 mt-0.5 capitalize">Periode {{ periode.label }}</p>
                </div>
                <div class="text-right shrink-0">
                    <div class="flex items-center gap-3 justify-end">
                        <div>
                            <p class="font-bold text-gray-900 leading-tight">{{ guru.nama }}</p>
                            <p class="text-xs text-gray-500">{{ guru.jabatan }}</p>
                            <p v-if="guru.nip" class="text-[11px] text-gray-400">NIP. {{ guru.nip }}</p>
                        </div>
                        <img v-if="guru.foto" :src="guru.foto" class="w-12 h-12 rounded-full object-cover ring-1 ring-gray-200" />
                        <div v-else class="w-12 h-12 rounded-full bg-gray-900 grid place-items-center text-white font-bold">{{ guru.nama?.charAt(0) }}</div>
                    </div>
                </div>
            </div>

            <!-- Skor utama -->
            <div class="px-8 py-7 flex items-center gap-8 border-b border-gray-100">
                <div class="text-center">
                    <div class="text-6xl font-extrabold tabular-nums" :class="gradeText(d.grade)">{{ d.skor_total }}</div>
                    <div class="text-xs text-gray-400 mt-1">dari 100</div>
                </div>
                <div class="flex-1">
                    <span class="inline-block px-3 py-1 rounded-full text-sm font-bold border" :class="gradeCls(d.grade)">
                        Grade {{ d.grade }} · {{ d.label_grade }}
                    </span>
                    <div class="mt-3 h-2.5 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full rounded-full" :class="gradeBar(d.grade)" :style="{ width: d.skor_total + '%' }"></div>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-2">Ambang: A ≥ {{ ambang.grade_a }} · B ≥ {{ ambang.grade_b }} · C ≥ {{ ambang.grade_c }}</p>
                </div>
            </div>

            <!-- Komponen -->
            <div class="px-8 py-6 border-b border-gray-100">
                <h2 class="text-xs font-bold uppercase tracking-wide text-gray-400 mb-4">Rincian Komponen</h2>
                <div class="space-y-4">
                    <div v-for="k in komponen" :key="k.nama">
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-semibold text-gray-700">{{ k.nama }} <span class="text-gray-400 font-normal">· bobot {{ k.bobot }}%</span></span>
                            <span class="tabular-nums text-gray-800 font-semibold">{{ k.skor }} <span class="text-xs text-gray-400">→ +{{ k.kontribusi }}</span></span>
                        </div>
                        <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full bg-gray-800" :style="{ width: k.skor + '%' }"></div>
                        </div>
                    </div>
                    <!-- Piket penyesuaian -->
                    <div class="flex items-center justify-between text-sm pt-1">
                        <span class="font-semibold text-gray-700">Penyesuaian Piket</span>
                        <span class="tabular-nums font-semibold" :class="piket >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ piket >= 0 ? '+' : '' }}{{ piket }}</span>
                    </div>
                </div>
            </div>

            <!-- Metrik detail -->
            <div class="px-8 py-6 grid grid-cols-2 sm:grid-cols-4 gap-4 border-b border-gray-100">
                <div v-for="m in metrik" :key="m.label" class="text-center">
                    <div class="text-2xl font-bold text-gray-800 tabular-nums">{{ m.val }}</div>
                    <div class="text-[11px] text-gray-400 mt-0.5">{{ m.label }}</div>
                </div>
            </div>

            <!-- Catatan + TTD -->
            <div class="px-8 py-6 flex items-end justify-between gap-8">
                <div class="flex-1 text-xs text-gray-500">
                    <p v-if="d.catatan"><span class="font-semibold text-gray-600">Catatan:</span> {{ d.catatan }}</p>
                    <p class="text-gray-300 mt-2">Dicetak {{ dicetak }}</p>
                </div>
                <div class="text-center text-xs text-gray-500">
                    <p>Mengetahui,</p>
                    <div class="h-14"></div>
                    <p class="font-semibold text-gray-700 border-t border-gray-300 pt-1 px-4">Pimpinan</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    lembaga: { type: String, default: '' },
    guru: { type: Object, default: () => ({}) },
    periode: { type: Object, default: () => ({}) },
    detail: { type: Object, default: () => ({ komponen: {} }) },
    ambang: { type: Object, default: () => ({}) },
    dicetak: { type: String, default: '' },
})

const d = computed(() => props.detail)
const piket = computed(() => Number(d.value.komponen?.piket?.penyesuaian ?? 0))

const komponen = computed(() => {
    const c = d.value.komponen || {}
    return [
        { nama: 'Absensi', ...c.absensi },
        { nama: 'Tugas', ...c.tugas },
        { nama: 'Administrasi', ...c.administrasi },
    ]
})

const metrik = computed(() => {
    const a = d.value.komponen?.absensi || {}, ad = d.value.komponen?.administrasi || {}
    return [
        { label: 'Hadir', val: a.hadir ?? 0 },
        { label: 'Terlambat', val: a.terlambat ?? 0 },
        { label: 'Alfa', val: a.alfa ?? 0 },
        { label: 'Izin/Sakit', val: (a.izin ?? 0) + (a.sakit ?? 0) },
        { label: 'JP Terlaksana', val: a.jp_terlaksana ?? 0 },
        { label: 'Sesi Dilaporkan', val: ad.sesi_dilaporkan ?? 0 },
        { label: 'Log Diverifikasi', val: ad.log_diverifikasi ?? 0 },
        { label: 'Durasi (jam)', val: ad.durasi_jam ?? 0 },
    ]
})

function cetak() { window.print() }

function gradeText(g) { return { A: 'text-emerald-600', B: 'text-blue-600', C: 'text-amber-500', D: 'text-orange-500', E: 'text-red-500' }[g] ?? 'text-gray-800' }
function gradeBar(g) { return { A: 'bg-emerald-500', B: 'bg-blue-500', C: 'bg-amber-400', D: 'bg-orange-400', E: 'bg-red-500' }[g] ?? 'bg-gray-800' }
function gradeCls(g) {
    return { A: 'bg-emerald-50 text-emerald-700 border-emerald-200', B: 'bg-blue-50 text-blue-700 border-blue-200',
        C: 'bg-amber-50 text-amber-700 border-amber-200', D: 'bg-orange-50 text-orange-700 border-orange-200',
        E: 'bg-red-50 text-red-700 border-red-200' }[g] ?? 'bg-gray-50 text-gray-600 border-gray-200'
}
</script>

<style scoped>
@media print {
    .no-print { display: none !important; }
    #raport { box-shadow: none; }
    :deep(nav), :deep(aside), :deep(header) { display: none !important; }
}
</style>

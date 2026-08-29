<template>
    <AdminLayout title="Libur Individu" subtitle="Smart Payroll">

        <Head title="Libur Individu Tenaga Pendidik" />

        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Libur Individu (Guru Mukim)</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    Penetapan libur per tanggal yang rolling per bulan — tanpa mengubah jam kerja. Tidak memotong gaji.
                </p>
            </div>
            <input v-model.number="filterTahun" type="number" @change="reload"
                class="w-24 px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-indigo-500" />
        </div>

        <!-- Kelola Guru Mukim (tandai grup) -->
        <div class="bg-white border border-gray-200 rounded-2xl p-5 mb-5">
            <div class="flex items-center justify-between mb-1">
                <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                    <span class="text-indigo-500">👥</span> Kelola Guru Mukim
                </h3>
                <button @click="simpanMukim"
                    class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition-colors">
                    Simpan Grup
                </button>
            </div>
            <p class="text-xs text-gray-400 mb-3">
                Centang guru yang bekerja shift &amp; punya libur rolling (mis. satpam/penjaga asrama). Hanya mereka yang muncul di pengelolaan libur.
            </p>
            <div class="flex flex-wrap gap-2">
                <label v-for="g in semuaGuru" :key="g.id"
                    :class="['flex items-center gap-2 px-3 py-1.5 rounded-xl border text-xs cursor-pointer transition-colors',
                        kelolaIds.includes(g.id) ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600 hover:border-gray-300']">
                    <input type="checkbox" :value="g.id" v-model="kelolaIds" class="w-3.5 h-3.5 rounded text-indigo-600" />
                    {{ g.nama }}
                    <span class="text-[10px] text-gray-400">({{ g.jenis_guru }})</span>
                </label>
            </div>
        </div>

        <!-- Belum ada guru mukim -->
        <div v-if="!guruMukim.length"
            class="p-6 text-center bg-amber-50 border border-amber-100 rounded-2xl">
            <p class="text-sm font-semibold text-amber-700">Belum ada guru mukim ditandai</p>
            <p class="text-xs text-amber-600 mt-1">Centang minimal satu guru di "Kelola Guru Mukim" lalu Simpan Grup.</p>
        </div>

        <template v-else>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <!-- Generator per bulan (multi-guru) -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                        <span class="text-indigo-500">⟳</span> Generator Libur per Bulan
                    </h3>
                    <p class="text-xs text-gray-400 mt-1 mb-3">
                        Rotasi hari libur berputar tiap minggu &amp; <b>berlanjut antar bulan</b>. Bisa untuk banyak guru sekaligus.
                    </p>

                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <label class="text-xs text-gray-500">Bulan
                            <select v-model.number="gen.bulan" class="mt-1 w-full px-2 py-2 rounded-lg border border-gray-200 text-sm">
                                <option v-for="b in bulanOpsi" :key="b.v" :value="b.v">{{ b.label }}</option>
                            </select>
                        </label>
                        <label class="text-xs text-gray-500">Tahun
                            <input v-model.number="gen.tahun" type="number" class="mt-1 w-full px-2.5 py-2 rounded-lg border border-gray-200 text-sm" />
                        </label>
                    </div>

                    <p class="text-xs text-gray-500 mb-1.5">Urutan hari libur (rotasi):</p>
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        <button v-for="h in hariOpsi" :key="h" @click="toggleHari(h)"
                            :class="['px-2.5 py-1 rounded-lg text-xs font-semibold border capitalize transition-colors',
                                gen.hari.includes(h) ? 'bg-indigo-100 border-indigo-300 text-indigo-700'
                                                     : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300']">
                            <span v-if="gen.hari.includes(h)" class="mr-1 text-indigo-400">{{ gen.hari.indexOf(h) + 1 }}.</span>{{ h }}
                        </button>
                    </div>
                    <p v-if="gen.hari.length" class="text-xs text-gray-500 mb-3">
                        Rotasi: <span class="font-semibold capitalize">{{ gen.hari.join(' → ') }}</span> · minggu berikutnya bergeser, lanjut tiap bulan
                    </p>

                    <p class="text-xs text-gray-500 mb-1.5">Terapkan untuk guru:</p>
                    <div class="flex flex-wrap gap-1.5 mb-3">
                        <button @click="toggleSemuaTarget"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold border border-gray-200 text-gray-600 hover:border-indigo-300">
                            {{ gen.guru_ids.length === guruMukim.length ? 'Kosongkan' : 'Semua' }}
                        </button>
                        <button v-for="g in guruMukim" :key="g.id" @click="toggleTarget(g.id)"
                            :class="['px-2.5 py-1 rounded-lg text-xs font-semibold border transition-colors',
                                gen.guru_ids.includes(g.id) ? 'bg-indigo-100 border-indigo-300 text-indigo-700'
                                                            : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300']">
                            {{ g.nama }}
                        </button>
                    </div>

                    <input v-model="gen.alasan" type="text" placeholder="Alasan (opsional)"
                        class="w-full px-2.5 py-2 rounded-lg border border-gray-200 text-sm mb-3" />
                    <button @click="submitGenerate" :disabled="!gen.hari.length || !gen.guru_ids.length"
                        class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white text-sm font-semibold transition-colors">
                        Generate Libur Bulan Ini
                    </button>
                    <p class="text-[11px] text-gray-400 mt-2 leading-snug">
                        Untuk membagi grup (sebagian libur Jumat, sebagian Ahad), jalankan generator dua kali dengan urutan
                        berbeda — mis. grup A <b>Jumat→Ahad</b>, grup B <b>Ahad→Jumat</b>.
                    </p>
                </div>

                <!-- Tambah manual + Tukar -->
                <div class="space-y-4">
                    <div class="bg-white border border-gray-200 rounded-2xl p-5">
                        <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                            <span class="text-indigo-500">＋</span> Tambah Manual — {{ guruNama(guruId) }}
                        </h3>
                        <div class="flex gap-2 mt-3">
                            <input v-model="manual.tanggal" type="date"
                                class="flex-1 px-2.5 py-2 rounded-lg border border-gray-200 text-sm" />
                            <button @click="submitManual" :disabled="!manual.tanggal"
                                class="px-4 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white text-sm font-semibold">
                                Tambah
                            </button>
                        </div>
                        <input v-model="manual.alasan" type="text" placeholder="Alasan (opsional)"
                            class="w-full px-2.5 py-2 rounded-lg border border-gray-200 text-sm mt-2" />
                    </div>

                    <div class="bg-white border border-gray-200 rounded-2xl p-5">
                        <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                            <span class="text-indigo-500">⇄</span> Tukar Libur dengan Guru Lain
                        </h3>
                        <div class="grid grid-cols-2 gap-2 mt-3">
                            <label class="text-xs text-gray-500">Tanggal libur {{ guruNama(guruId) }}
                                <select v-model="tukar.tanggal_a" class="mt-1 w-full px-2 py-2 rounded-lg border border-gray-200 text-sm">
                                    <option value="">— pilih —</option>
                                    <option v-for="l in liburList" :key="l.id" :value="l.tanggal">{{ l.tanggal_label }}</option>
                                </select>
                            </label>
                            <label class="text-xs text-gray-500">Guru lain
                                <select v-model.number="tukar.tendik_b" class="mt-1 w-full px-2 py-2 rounded-lg border border-gray-200 text-sm">
                                    <option :value="0">— pilih —</option>
                                    <option v-for="g in guruLain" :key="g.id" :value="g.id">{{ g.nama }}</option>
                                </select>
                            </label>
                        </div>
                        <label class="text-xs text-gray-500 block mt-2">Tanggal libur guru lain (yang ditukar)
                            <input v-model="tukar.tanggal_b" type="date"
                                class="mt-1 w-full px-2.5 py-2 rounded-lg border border-gray-200 text-sm" />
                        </label>
                        <button @click="submitTukar" :disabled="!tukar.tanggal_a || !tukar.tendik_b || !tukar.tanggal_b"
                            class="w-full mt-3 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-700 disabled:opacity-40 text-white text-sm font-semibold transition-colors">
                            Tukar Libur
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pilih guru utk lihat daftar -->
            <div class="flex flex-wrap items-center gap-2 mt-6 mb-3">
                <span class="text-xs font-semibold text-gray-500">Lihat libur:</span>
                <button v-for="g in guruMukim" :key="g.id" @click="pilihGuru(g.id)"
                    :class="['px-3 py-1.5 rounded-full text-xs font-semibold border transition-colors',
                        g.id === guruId ? 'bg-indigo-600 border-indigo-600 text-white'
                                        : 'bg-white border-gray-200 text-gray-600 hover:border-indigo-300']">
                    {{ g.nama }}
                </button>
            </div>

            <!-- Daftar libur -->
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-800">Daftar Libur {{ guruNama(guruId) }} — {{ tahun }}</h3>
                    <span class="text-xs text-gray-400">{{ liburList.length }} hari</span>
                </div>
                <div v-if="!liburList.length" class="p-8 text-center text-sm text-gray-400">
                    Belum ada libur ditetapkan.
                </div>
                <table v-else class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-400 text-xs">
                        <tr>
                            <th class="text-left px-5 py-2 font-medium">Tanggal</th>
                            <th class="text-left px-3 py-2 font-medium">Tipe</th>
                            <th class="text-left px-3 py-2 font-medium">Keterangan</th>
                            <th class="text-right px-5 py-2 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="l in liburList" :key="l.id" class="border-t border-gray-50 hover:bg-gray-50/60">
                            <td class="px-5 py-2.5 font-medium text-gray-800">{{ l.tanggal_label }}</td>
                            <td class="px-3 py-2.5">
                                <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', tipeBadge(l.tipe)]">{{ tipeLabel(l.tipe) }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-gray-500 text-xs">
                                {{ l.alasan || '—' }}
                                <span v-if="l.ditukar_dengan" class="text-violet-500">· tukar dgn {{ l.ditukar_dengan }}</span>
                            </td>
                            <td class="px-5 py-2.5 text-right whitespace-nowrap">
                                <button @click="pindah(l)" class="text-xs text-indigo-600 hover:underline mr-3">Pindah</button>
                                <button @click="hapus(l)" class="text-xs text-red-500 hover:underline">Hapus</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

const props = defineProps({
    guruMukim: { type: Array, default: () => [] },
    semuaGuru: { type: Array, default: () => [] },
    guruId:    { type: Number, default: null },
    tahun:     { type: Number, default: () => new Date().getFullYear() },
    liburList: { type: Array, default: () => [] },
    hariOpsi:  { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
})

const filterTahun = ref(props.tahun)
const bulanOpsi = [
    { v: 1, label: 'Januari' }, { v: 2, label: 'Februari' }, { v: 3, label: 'Maret' },
    { v: 4, label: 'April' }, { v: 5, label: 'Mei' }, { v: 6, label: 'Juni' },
    { v: 7, label: 'Juli' }, { v: 8, label: 'Agustus' }, { v: 9, label: 'September' },
    { v: 10, label: 'Oktober' }, { v: 11, label: 'November' }, { v: 12, label: 'Desember' },
]

const kelolaIds = ref(props.semuaGuru.filter(g => g.is_mukim).map(g => g.id))

const gen = reactive({
    bulan: new Date().getMonth() + 1,
    tahun: props.tahun,
    hari: ['jumat', 'ahad'],
    guru_ids: props.guruMukim.map(g => g.id),
    alasan: '',
})
const manual = reactive({ tanggal: '', alasan: '' })
const tukar = reactive({ tanggal_a: '', tendik_b: 0, tanggal_b: '', alasan: '' })

const guruLain = computed(() => props.guruMukim.filter(g => g.id !== props.guruId))
const guruNama = (id) => props.guruMukim.find(g => g.id === id)?.nama ?? '—'

const opts = { preserveScroll: true }
const r = (n) => route(`admin.smart-payroll.libur-tendik.${n}`)

function reload() {
    router.get(r('index'), { guru_id: props.guruId, tahun: filterTahun.value }, { preserveState: false })
}
function pilihGuru(id) {
    router.get(r('index'), { guru_id: id, tahun: filterTahun.value }, { preserveState: false })
}
function simpanMukim() {
    router.post(r('kelola-mukim'), { guru_ids: kelolaIds.value }, opts)
}
function toggleHari(h) {
    const i = gen.hari.indexOf(h)
    if (i >= 0) gen.hari.splice(i, 1); else gen.hari.push(h)
}
function toggleTarget(id) {
    const i = gen.guru_ids.indexOf(id)
    if (i >= 0) gen.guru_ids.splice(i, 1); else gen.guru_ids.push(id)
}
function toggleSemuaTarget() {
    gen.guru_ids = gen.guru_ids.length === props.guruMukim.length ? [] : props.guruMukim.map(g => g.id)
}
function submitGenerate() {
    router.post(r('generate'), { ...gen }, opts)
}
function submitManual() {
    router.post(r('store'),
        { tenaga_pendidik_id: props.guruId, tanggal: [manual.tanggal], alasan: manual.alasan },
        { ...opts, onSuccess: () => { manual.tanggal = ''; manual.alasan = '' } })
}
function submitTukar() {
    router.post(r('tukar'),
        { tendik_a: props.guruId, tanggal_a: tukar.tanggal_a,
          tendik_b: tukar.tendik_b, tanggal_b: tukar.tanggal_b, alasan: tukar.alasan },
        { ...opts, onSuccess: () => { tukar.tanggal_a = ''; tukar.tendik_b = 0; tukar.tanggal_b = '' } })
}
function pindah(l) {
    const tgl = window.prompt(`Pindahkan libur ${l.tanggal_label} ke tanggal (YYYY-MM-DD):`, l.tanggal)
    if (!tgl) return
    router.patch(route('admin.smart-payroll.libur-tendik.pindah', l.id), { tanggal_baru: tgl }, opts)
}
async function hapus(l) {
    if (!(await confirm({ title: `Hapus libur ${l.tanggal_label}?`, variant: 'danger', confirmLabel: 'Ya, Hapus' }))) return
    router.delete(route('admin.smart-payroll.libur-tendik.destroy', l.id), opts)
}

function tipeLabel(t) { return { rutin: 'Rolling', manual: 'Manual', tukar: 'Tukar' }[t] ?? t }
function tipeBadge(t) {
    return { rutin: 'bg-indigo-50 text-indigo-600', manual: 'bg-gray-100 text-gray-600',
             tukar: 'bg-violet-50 text-violet-600' }[t] ?? 'bg-gray-100 text-gray-600'
}
</script>

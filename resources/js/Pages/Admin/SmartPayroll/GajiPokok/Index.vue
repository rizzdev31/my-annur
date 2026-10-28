<template>
    <AdminLayout title="Gaji Pokok" subtitle="Smart Payroll">

        <Head title="Gaji Pokok" />

        <!-- Header -->
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xl font-semibold text-gray-900">Gaji Pokok per Guru</h2>
                    <span v-if="periode.berjalan"
                        class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700">
                        periode berjalan
                    </span>
                </div>
                <p class="text-sm text-gray-500 mt-0.5">
                    <b class="text-gray-700">{{ periode.label }}</b>
                    <span v-if="periode.dari"> · berlaku {{ periode.dari }} &ndash; {{ periode.sampai }}</span>
                </p>
                <!-- Gaji pokok tidak dikurangi keterlambatan/alfa; yang mengurangi
                     hanya potongan per guru (simpanan, voucher, lazismu, pinjaman). -->
                <p class="text-xs text-gray-400 mt-1">
                    Gaji pokok tidak terpotong keterlambatan atau alfa. Potongan per guru
                    (simpanan, voucher, lazismu, pinjaman) tetap mengurangi gaji bersih.
                </p>
            </div>
        </div>

        <!-- Ringkasan -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500">Guru aktif</p>
                <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ ringkasan.guru }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500">Sudah berkomponen</p>
                <p class="text-2xl font-bold text-emerald-600 tabular-nums">{{ ringkasan.ada_komponen }}</p>
                <p class="text-[11px] text-gray-400">dari {{ ringkasan.guru }} guru</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500">Perlu ditinjau</p>
                <p class="text-2xl font-bold text-amber-600 tabular-nums">{{ ringkasan.perlu_ditinjau }}</p>
                <p class="text-[11px] text-gray-400">nominalnya belum dari berkas resmi</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500">Total gaji pokok</p>
                <p class="text-2xl font-bold text-indigo-700 tabular-nums">{{ rp(ringkasan.total_gaji_pokok) }}</p>
            </div>
        </div>

        <!-- Saringan -->
        <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4">
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[230px]">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Periode</label>
                    <select :value="periode.tahun + '-' + periode.bulan" @change="pilihPeriode($event.target.value)"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option v-for="o in periodeOpsi" :key="o.tahun + '-' + o.bulan" :value="o.tahun + '-' + o.bulan">
                            {{ o.label }} &middot; {{ o.dari }}&ndash;{{ o.sampai }}
                        </option>
                    </select>
                </div>
                <div class="min-w-[200px]">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Cari nama</label>
                    <input v-model="cari" @input="cariTunda" type="text" placeholder="Nama guru…"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                </div>
                <div class="flex gap-1">
                    <button v-for="s in saringOpsi" :key="s.nilai" @click="pilihSaring(s.nilai)"
                        class="px-3 py-2 text-xs font-semibold rounded-xl border transition-colors"
                        :class="saring === s.nilai
                            ? 'bg-indigo-600 text-white border-indigo-600'
                            : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'">
                        {{ s.label }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabel -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50/70 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            <th class="px-4 py-3 text-left">Guru</th>
                            <th v-for="s in slotDef" :key="s.kunci" class="px-3 py-3 text-right whitespace-nowrap">
                                {{ s.label }}
                            </th>
                            <th class="px-4 py-3 text-right bg-indigo-50/60 text-indigo-700 whitespace-nowrap">
                                Jumlah Keseluruhan
                            </th>
                            <th class="px-3 py-3 text-right whitespace-nowrap">Hadir</th>
                            <th class="px-3 py-3 text-right whitespace-nowrap">JP Inval</th>
                            <th class="px-3 py-3 text-right whitespace-nowrap">Gaji Bersih</th>
                            <th class="px-3 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="g in guru" :key="g.id" class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium text-gray-800">{{ g.nama }}</p>
                                <p class="text-[11px] text-gray-400">{{ g.jabatan_utama }}</p>
                                <span v-if="g.perlu_ditinjau"
                                    class="inline-block mt-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700">
                                    perlu ditinjau
                                </span>
                                <span v-else-if="!g.ada_komponen"
                                    class="inline-block mt-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-600">
                                    masih cara lama
                                </span>
                            </td>
                            <td v-for="s in g.slot" :key="s.kunci" class="px-3 py-3 text-right">
                                <span v-if="s.nominal > 0" class="text-sm text-gray-700 tabular-nums">
                                    {{ rp(s.nominal) }}
                                </span>
                                <span v-else class="text-sm text-gray-300">—</span>
                                <p v-if="s.isi_label" class="text-[10px] text-gray-400 leading-tight">{{ s.isi_label }}</p>
                            </td>
                            <td class="px-4 py-3 text-right bg-indigo-50/40">
                                <span class="text-sm font-bold text-indigo-700 tabular-nums">
                                    {{ rp(g.ada_komponen ? g.total : g.gaji_pokok_berlaku) }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-right text-sm text-gray-600 tabular-nums whitespace-nowrap">
                                <template v-if="g.slip_ada">
                                    {{ g.hadir }}/{{ g.hari_kerja }}
                                    <span v-if="g.alfa" class="text-red-500 text-[11px]"> ·{{ g.alfa }}A</span>
                                </template>
                                <span v-else class="text-gray-300">—</span>
                            </td>
                            <td class="px-3 py-3 text-right text-sm text-gray-600 tabular-nums">
                                <span v-if="g.slip_ada">{{ g.jp_pengganti ?? 0 }}</span>
                                <span v-else class="text-gray-300">—</span>
                            </td>
                            <td class="px-3 py-3 text-right text-sm text-gray-700 tabular-nums whitespace-nowrap">
                                <span v-if="g.slip_ada">{{ rp(g.gaji_bersih) }}</span>
                                <span v-else class="text-gray-300">—</span>
                            </td>
                            <td class="px-3 py-3 text-right">
                                <button v-if="bolehUbah" @click="bukaUbah(g)"
                                    class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition-colors">
                                    Ubah
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!guru.length">
                            <td :colspan="slotDef.length + 6" class="px-5 py-12 text-center text-sm text-gray-500">
                                Tidak ada guru yang cocok dengan saringan ini.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Dialog ubah -->
        <div v-if="ubah.buka" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/40" @click="ubah.kirim || (ubah.buka = false)"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-xl p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-semibold text-gray-900">Tetapkan Gaji Pokok</h3>
                <p class="text-sm text-gray-500 mt-0.5">{{ ubah.nama }}</p>

                <div class="mt-4 space-y-3">
                    <div v-for="s in ubah.slot" :key="s.kunci"
                        class="rounded-xl border border-gray-200 p-3">
                        <p class="text-xs font-semibold text-gray-700 mb-2">{{ s.label }}</p>
                        <div class="flex gap-2">
                            <input v-if="s.berlabel" v-model="s.isi_label" type="text"
                                :placeholder="'Nama ' + s.label.toLowerCase()"
                                class="flex-1 px-3 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                            <input v-model.number="s.nominal" type="number" min="0" step="1000" placeholder="0"
                                class="w-40 px-3 py-2 text-sm text-right border border-gray-200 rounded-xl tabular-nums focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                    </div>
                </div>

                <div class="mt-4 rounded-xl bg-indigo-50 border border-indigo-100 p-3 flex items-center justify-between">
                    <span class="text-sm font-semibold text-indigo-900">Jumlah Keseluruhan</span>
                    <span class="text-lg font-bold text-indigo-700 tabular-nums">{{ rp(totalUbah) }}</span>
                </div>

                <div class="mt-4 grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Berlaku sejak</label>
                        <input v-model="ubah.berlaku_mulai" type="date"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        <p class="text-[11px] text-gray-400 mt-1">
                            Nominal lama tetap tersimpan untuk periode sebelum tanggal ini.
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Catatan (opsional)</label>
                        <input v-model="ubah.catatan" type="text" maxlength="300" placeholder="Mis. sesuai berkas 28 Okt"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                </div>

                <p v-if="ubah.galat" class="mt-3 text-sm text-red-600">{{ ubah.galat }}</p>

                <div class="flex justify-end gap-2 mt-5">
                    <button @click="ubah.buka = false" :disabled="ubah.kirim"
                        class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-xl">Batal</button>
                    <button @click="simpan" :disabled="ubah.kirim || !ubah.berlaku_mulai"
                        class="px-4 py-2 text-sm font-semibold rounded-xl"
                        :class="!ubah.kirim && ubah.berlaku_mulai
                            ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                            : 'bg-gray-100 text-gray-400 cursor-not-allowed'">
                        {{ ubah.kirim ? 'Menyimpan…' : 'Simpan' }}
                    </button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    guru: { type: Array, default: () => [] },
    slotDef: { type: Array, default: () => [] },
    periode: { type: Object, default: () => ({}) },
    periodeOpsi: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const bolehUbah = computed(() => usePage().props.auth?.user?.role === 'super_admin')
const cari = ref(props.filters.q ?? '')
const saring = ref(props.filters.saring ?? '')

const saringOpsi = [
    { nilai: '', label: 'Semua' },
    { nilai: 'perlu_ditinjau', label: 'Perlu ditinjau' },
    { nilai: 'belum_ada', label: 'Belum berkomponen' },
]

const rp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID')

function reload(extra = {}) {
    router.get(route('admin.smart-payroll.gaji-pokok.index'), {
        bulan: props.periode.bulan,
        tahun: props.periode.tahun,
        q: cari.value || undefined,
        saring: saring.value || undefined,
        ...extra,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

function pilihPeriode(val) {
    const [tahun, bulan] = String(val).split('-')
    reload({ bulan, tahun })
}
function pilihSaring(val) { saring.value = val; reload() }

let timer = null
function cariTunda() {
    clearTimeout(timer)
    timer = setTimeout(reload, 350)
}

// ── Dialog penetapan ─────────────────────────────────────────────────────────
const ubah = reactive({
    buka: false, id: null, nama: '', slot: [],
    // Bawaannya awal periode yang sedang dilihat, bukan hari ini: penetapan
    // gaji hampir selalu dimaksudkan berlaku untuk periode itu seluruhnya.
    berlaku_mulai: '', catatan: '', galat: '', kirim: false,
})

const totalUbah = computed(() => ubah.slot.reduce((a, s) => a + (Number(s.nominal) || 0), 0))

function awalPeriodeIso() {
    const o = props.periodeOpsi.find(
        (x) => x.bulan === props.periode.bulan && x.tahun === props.periode.tahun)
    if (!o) return new Date().toISOString().slice(0, 10)
    // "26 Sep 2026" -> ISO. Dibuat dari string yang sudah diformat server.
    const d = new Date(o.dari + ' UTC')
    return isNaN(d) ? new Date().toISOString().slice(0, 10) : d.toISOString().slice(0, 10)
}

function bukaUbah(g) {
    Object.assign(ubah, {
        buka: true, id: g.id, nama: g.nama,
        slot: g.slot.map((s) => ({ ...s })),
        berlaku_mulai: awalPeriodeIso(),
        catatan: g.catatan ?? '', galat: '', kirim: false,
    })
}

function simpan() {
    ubah.kirim = true; ubah.galat = ''
    router.post(route('admin.smart-payroll.gaji-pokok.simpan', ubah.id), {
        berlaku_mulai: ubah.berlaku_mulai,
        catatan: ubah.catatan || null,
        slot: ubah.slot.map((s) => ({
            kunci: s.kunci,
            nominal: Number(s.nominal) || 0,
            label: s.berlabel ? (s.isi_label || null) : null,
        })),
    }, {
        preserveScroll: true,
        onSuccess: () => { ubah.buka = false },
        // Inertia membalas 303 + errors; tanpa onError galatnya hilang diam-diam.
        onError: (e) => { ubah.galat = Object.values(e)[0] ?? 'Gagal menyimpan.' },
        onFinish: () => { ubah.kirim = false },
    })
}
</script>

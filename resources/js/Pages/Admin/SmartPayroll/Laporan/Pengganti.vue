<template>
    <AdminLayout>
        <Head title="Laporan Guru Pengganti" />

        <!-- HEADER -->
        <div class="no-print mb-4">
            <h1 class="text-xl font-bold text-gray-800">Laporan Guru Pengganti</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Khusus sesi yang diampu guru pengganti (inval) — siapa menggantikan siapa, JP yang diampu, dan vakasinya.
            </p>
        </div>

        <!-- FILTER -->
        <div class="no-print bg-white rounded-2xl border border-gray-200 p-4 mb-4">
            <!-- Jendela waktu: ikut periode gaji (sejalan dengan slip) atau rentang bebas -->
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <div class="flex gap-1 p-1 bg-gray-100 rounded-xl">
                    <button @click="form.mode = 'periode'"
                        :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                            form.mode === 'periode' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-500']">
                        Periode Penggajian
                    </button>
                    <button @click="form.mode = 'tanggal'"
                        :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                            form.mode === 'tanggal' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-500']">
                        Rentang Tanggal
                    </button>
                </div>
                <span class="text-xs text-gray-500">
                    Menampilkan: <b class="text-gray-700">{{ label }}</b>
                    <span class="text-gray-400"> ({{ rentang.mulai }} → {{ rentang.selesai }})</span>
                </span>
                <!-- Sumber tarif menentukan apakah angkanya sama dengan slip -->
                <span class="text-[11px] font-semibold px-2 py-1 rounded-full"
                    :class="sumberTarif === 'slip' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
                    {{ sumberTarif === 'slip'
                        ? 'Nominal sesuai slip yang terbit'
                        : 'Perkiraan — tarif yang berlaku sekarang' }}
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <!-- Pilihan periode -->
                <div v-if="form.mode === 'periode'" class="lg:col-span-2">
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Periode penggajian</label>
                    <select v-model="form.periode_id" :class="inp">
                        <option v-for="p in periodeList" :key="p.id" :value="p.id">
                            {{ p.label }} ({{ p.mulai }} → {{ p.sampai }})
                        </option>
                    </select>
                </div>

                <!-- Rentang tanggal bebas -->
                <template v-else>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Dari tanggal</label>
                        <input v-model="form.tanggal_awal" type="date" :class="inp" />
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Sampai tanggal</label>
                        <input v-model="form.tanggal_akhir" type="date" :class="inp" />
                    </div>
                </template>

                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Guru pengganti</label>
                    <select v-model="form.pengganti_id" :class="inp">
                        <option :value="null">Semua</option>
                        <option v-for="g in guruList" :key="'p'+g.id" :value="g.id">{{ g.nama }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Guru digantikan</label>
                    <select v-model="form.guru_id" :class="inp">
                        <option :value="null">Semua</option>
                        <option v-for="g in guruList" :key="'a'+g.id" :value="g.id">{{ g.nama }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Jenis kelas</label>
                    <select v-model="form.jenis_kelas" :class="inp">
                        <option :value="null">Semua</option>
                        <option value="sekolah">Sekolah / Mapel</option>
                        <option value="tahfidz">Tahfidz</option>
                        <option value="tahsin">Tahsin</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 mt-3">
                <button @click="terapkan" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition">
                    Tampilkan
                </button>
                <button @click="reset" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-600 text-sm font-semibold hover:bg-gray-200 transition">
                    Reset
                </button>
                <button @click="cetak" class="px-4 py-2 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold hover:bg-gray-50 transition">
                    Cetak
                </button>
            </div>
        </div>

        <!-- RINGKASAN -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <div v-for="k in kartu" :key="k.label"
                :class="['rounded-2xl border p-3.5', k.cls]">
                <p class="text-2xl font-extrabold tabular-nums leading-none">{{ k.value }}</p>
                <p class="text-[11.5px] font-medium mt-1">{{ k.label }}</p>
                <p v-if="k.sub" class="text-[10.5px] opacity-70 mt-0.5">{{ k.sub }}</p>
            </div>
        </div>

        <div v-if="!rows.length" class="bg-white rounded-2xl border border-dashed border-gray-200 py-14 px-6 text-center">
            <h3 class="font-semibold text-gray-700">Tidak ada sesi pengganti</h3>
            <p class="text-sm text-gray-500 mt-1">Belum ada penggantian mengajar pada jendela waktu ini.</p>
        </div>

        <template v-else>
            <!-- REKAP PER GURU PENGGANTI -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-4">
                <div class="px-5 py-3 border-b border-gray-100 flex items-baseline justify-between">
                    <h2 class="text-sm font-bold text-gray-800">Rekap per Guru Pengganti</h2>
                    <span class="text-[11px] text-gray-500">{{ rekap.length }} guru · untuk dicocokkan dengan slip gaji</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50/70">
                            <tr class="text-[11px] font-semibold text-gray-500 uppercase text-left">
                                <th class="px-5 py-2.5">Guru Pengganti</th>
                                <th class="px-3 py-2.5 text-center">Sesi</th>
                                <th class="px-3 py-2.5 text-center">Diampu</th>
                                <th class="px-3 py-2.5 text-center">Tidak Datang</th>
                                <th class="px-3 py-2.5 text-center">Belum Absen</th>
                                <th class="px-3 py-2.5 text-center">JP Dibayar</th>
                                <th class="px-3 py-2.5 text-right">Tarif/JP</th>
                                <th class="px-5 py-2.5 text-right">Total Vakasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="r in rekap" :key="r.pengganti_id" class="hover:bg-gray-50/50">
                                <td class="px-5 py-2.5 font-medium text-gray-800">{{ r.pengganti }}</td>
                                <td class="px-3 py-2.5 text-center tabular-nums text-gray-600">{{ r.sesi }}</td>
                                <td class="px-3 py-2.5 text-center tabular-nums font-semibold text-emerald-700">{{ r.sesi_datang }}</td>
                                <td class="px-3 py-2.5 text-center tabular-nums" :class="r.sesi_tidak ? 'text-red-600 font-semibold' : 'text-gray-400'">{{ r.sesi_tidak }}</td>
                                <td class="px-3 py-2.5 text-center tabular-nums" :class="r.sesi_belum ? 'text-amber-600 font-semibold' : 'text-gray-400'">{{ r.sesi_belum }}</td>
                                <td class="px-3 py-2.5 text-center tabular-nums font-semibold text-gray-800">{{ r.jp }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-gray-600">{{ rp(r.tarif) }}</td>
                                <td class="px-5 py-2.5 text-right tabular-nums font-bold text-indigo-700">{{ rp(r.nominal) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50/70">
                            <tr class="text-sm font-bold text-gray-800">
                                <td class="px-5 py-2.5">Total</td>
                                <td class="px-3 py-2.5 text-center tabular-nums">{{ ringkasan.total_sesi }}</td>
                                <td class="px-3 py-2.5 text-center tabular-nums">{{ ringkasan.sesi_datang }}</td>
                                <td class="px-3 py-2.5 text-center tabular-nums">{{ ringkasan.sesi_tidak }}</td>
                                <td class="px-3 py-2.5 text-center tabular-nums">{{ ringkasan.sesi_belum }}</td>
                                <td class="px-3 py-2.5 text-center tabular-nums">{{ ringkasan.total_jp }}</td>
                                <td class="px-3 py-2.5"></td>
                                <td class="px-5 py-2.5 text-right tabular-nums text-indigo-700">{{ rp(ringkasan.total_vakasi) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- DAFTAR PER SESI -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-baseline justify-between">
                    <h2 class="text-sm font-bold text-gray-800">Daftar per Sesi</h2>
                    <span class="text-[11px] text-gray-500">{{ rows.length }} sesi</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50/70">
                            <tr class="text-[11px] font-semibold text-gray-500 uppercase text-left">
                                <th class="px-4 py-2.5">Tanggal</th>
                                <th class="px-3 py-2.5">Jam</th>
                                <th class="px-3 py-2.5">Kelas</th>
                                <th class="px-3 py-2.5">Mapel</th>
                                <th class="px-3 py-2.5">Digantikan</th>
                                <th class="px-3 py-2.5">Pengganti</th>
                                <th class="px-3 py-2.5 text-center">JP</th>
                                <th class="px-3 py-2.5">Status</th>
                                <th class="px-4 py-2.5 text-right">Vakasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="r in rows" :key="r.id" class="hover:bg-gray-50/50">
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <p class="text-gray-800">{{ r.tanggal }}</p>
                                    <p class="text-[10.5px] text-gray-500">{{ r.hari }}</p>
                                </td>
                                <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap tabular-nums">{{ r.jam }}</td>
                                <td class="px-3 py-2.5 text-gray-700">
                                    {{ r.kelas }}
                                    <span v-if="r.jenis_kelas && r.jenis_kelas !== 'sekolah'"
                                        class="ml-1 text-[9.5px] font-bold px-1.5 py-0.5 rounded bg-violet-50 text-violet-700 uppercase">{{ r.jenis_kelas }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-gray-600">{{ r.mapel }}</td>
                                <td class="px-3 py-2.5 text-gray-600">{{ r.guru_asli }}</td>
                                <td class="px-3 py-2.5 font-medium text-gray-800">{{ r.pengganti }}</td>
                                <td class="px-3 py-2.5 text-center tabular-nums font-semibold"
                                    :class="r.jp > 0 ? 'text-gray-800' : 'text-gray-300'">{{ r.jp }}</td>
                                <td class="px-3 py-2.5">
                                    <span :class="['text-[10.5px] font-semibold px-2 py-0.5 rounded-lg whitespace-nowrap', statusCls(r.status)]">
                                        {{ r.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums"
                                    :class="r.nominal > 0 ? 'font-semibold text-indigo-700' : 'text-gray-300'">
                                    {{ r.nominal > 0 ? rp(r.nominal) : '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="px-5 py-3 text-[11px] text-gray-500 border-t border-gray-100">
                    <span v-if="sumberTarif === 'slip'">Tarif per JP diambil dari slip yang sudah terbit pada periode ini,
                        jadi nominalnya sama dengan yang dibayarkan.</span>
                    <span v-else>Periode ini belum digenerate penggajiannya, jadi nominal memakai tarif yang berlaku
                        sekarang — anggap sebagai perkiraan.</span>
                    Hanya sesi yang benar-benar diampu pengganti yang dibayar. Sesi bertanda
                    <b>Pengganti tidak datang</b> tidak menghasilkan vakasi dan berdampak pada kinerja pengganti,
                    bukan pada guru yang digantikan.
                </p>
            </div>

            <!-- Guru paling sering digantikan -->
            <div v-if="ringkasan.paling_digantikan?.length" class="bg-white rounded-2xl border border-gray-200 p-5 mt-4">
                <h2 class="text-sm font-bold text-gray-800 mb-2">Paling Sering Digantikan</h2>
                <div class="flex flex-wrap gap-2">
                    <span v-for="g in ringkasan.paling_digantikan" :key="g.nama"
                        class="text-xs px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800">
                        {{ g.nama }} · {{ g.sesi }} sesi
                    </span>
                </div>
            </div>
        </template>
    </AdminLayout>
</template>

<script setup>
import { reactive, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    mode: { type: String, default: 'periode' },
    label: { type: String, default: '' },
    rentang: { type: Object, default: () => ({ mulai: '', selesai: '' }) },
    periodeList: { type: Array, default: () => [] },
    periodeId: { type: [Number, String], default: null },
    guruList: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    rows: { type: Array, default: () => [] },
    rekap: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
    sumberTarif: { type: String, default: 'setting' },
})

const inp = 'w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-400'

const form = reactive({
    mode: props.mode,
    periode_id: props.periodeId,
    tanggal_awal: props.filters.tanggal_awal,
    tanggal_akhir: props.filters.tanggal_akhir,
    pengganti_id: props.filters.pengganti_id,
    guru_id: props.filters.guru_id,
    jenis_kelas: props.filters.jenis_kelas,
})

function terapkan() {
    const q = { mode: form.mode }
    if (form.mode === 'periode') q.periode_id = form.periode_id
    else { q.tanggal_awal = form.tanggal_awal; q.tanggal_akhir = form.tanggal_akhir }
    if (form.pengganti_id) q.pengganti_id = form.pengganti_id
    if (form.guru_id) q.guru_id = form.guru_id
    if (form.jenis_kelas) q.jenis_kelas = form.jenis_kelas
    router.get(route('admin.smart-payroll.laporan.pengganti'), q, { preserveScroll: true, preserveState: true })
}

function reset() {
    router.get(route('admin.smart-payroll.laporan.pengganti'), {}, { preserveScroll: true })
}

function cetak() { window.print() }

const kartu = computed(() => [
    { label: 'Sesi pengganti', value: props.ringkasan.total_sesi ?? 0,
      sub: `${props.ringkasan.jumlah_pengganti ?? 0} guru pengganti`, cls: 'bg-white border-gray-200 text-gray-800' },
    { label: 'Diampu (dibayar)', value: props.ringkasan.sesi_datang ?? 0,
      cls: 'bg-emerald-50 border-emerald-100 text-emerald-800' },
    { label: 'Pengganti tidak datang', value: props.ringkasan.sesi_tidak ?? 0,
      cls: 'bg-rose-50 border-rose-100 text-rose-800' },
    { label: 'JP dibayar', value: props.ringkasan.total_jp ?? 0,
      cls: 'bg-white border-gray-200 text-gray-800' },
    { label: 'Total vakasi', value: rp(props.ringkasan.total_vakasi ?? 0),
      cls: 'bg-indigo-50 border-indigo-100 text-indigo-800' },
])

function statusCls(s) {
    return {
        datang: 'bg-emerald-50 text-emerald-700',
        tidak_datang: 'bg-red-50 text-red-600',
        belum_absen: 'bg-amber-50 text-amber-700',
    }[s] ?? 'bg-gray-100 text-gray-600'
}

function rp(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }
</script>

<style scoped>
@media print {
    .no-print { display: none !important; }
}
</style>

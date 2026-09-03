<template>
    <AdminLayout title="Penunjukan Piket" subtitle="Guru Piket">

        <Head title="Penunjukan Piket" />

        <div class="flex items-center justify-between mb-5 gap-3 flex-wrap">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Penunjukan Guru Piket</h2>
                <p class="text-sm text-gray-400 mt-0.5">Tunjuk piket per hari. Window aktif (boleh menilai/mengabsen) mengikuti jam absen masuk–pulang piket itu. Boleh &gt;1 piket/hari.</p>
            </div>
            <div class="flex items-end gap-2">
                <select v-model.number="f.bulan" @change="apply" :class="inp">
                    <option v-for="(b,i) in bulanNama" :key="i" :value="i+1">{{ b }}</option>
                </select>
                <input v-model.number="f.tahun" type="number" @change="apply" :class="inp + ' w-24'" />
            </div>
        </div>

        <!-- Form tunjuk -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-5">
            <p class="text-sm font-semibold text-gray-800 mb-3">Tunjuk Piket Baru</p>
            <div class="grid md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal</label>
                    <input v-model="form.tanggal" type="date" :class="inp" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Guru Piket</label>
                    <select v-model.number="form.tenaga_pendidik_id" :class="inp">
                        <option :value="null">Pilih guru...</option>
                        <option v-for="g in guruOpsi" :key="g.id" :value="g.id">{{ g.nama }}</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button @click="tunjuk" :disabled="!valid"
                        class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-50">
                        Tunjuk
                    </button>
                </div>
            </div>
        </div>

        <!-- Form tunjuk RENTANG -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-5">
            <p class="text-sm font-semibold text-gray-800 mb-1">Tunjuk Piket Rentang</p>
            <p class="text-xs text-gray-400 mb-3">Tunjuk sekaligus untuk beberapa hari (mis. sebulan) tanpa input harian. Pilih hari kalau mau (kosong = semua hari dalam rentang).</p>
            <div class="grid md:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Guru Piket</label>
                    <select v-model.number="formR.tenaga_pendidik_id" :class="inp">
                        <option :value="null">Pilih guru...</option>
                        <option v-for="g in guruOpsi" :key="g.id" :value="g.id">{{ g.nama }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Dari Tanggal</label>
                    <input v-model="formR.tanggal_mulai" type="date" :class="inp" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Sampai Tanggal</label>
                    <input v-model="formR.tanggal_selesai" type="date" :min="formR.tanggal_mulai" :class="inp" />
                </div>
                <div class="flex items-end">
                    <button @click="tunjukRentang" :disabled="!validR || savingR"
                        class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold disabled:opacity-50">
                        {{ savingR ? 'Memproses…' : 'Tunjuk Rentang' }}
                    </button>
                </div>
            </div>
            <div class="mt-3">
                <label class="block text-xs font-medium text-gray-500 mb-1.5">Hari (opsional)</label>
                <div class="flex flex-wrap gap-2">
                    <button v-for="h in hariOpsi" :key="h.v" type="button" @click="toggleHari(h.v)"
                        :class="['px-3 py-1.5 rounded-lg text-xs font-semibold border transition',
                            formR.hari.includes(h.v) ? 'bg-emerald-50 border-emerald-300 text-emerald-700' : 'bg-white border-gray-200 text-gray-500']">
                        {{ h.t }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabel -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50/60 text-xs text-gray-400 uppercase">
                    <th class="px-4 py-2.5 text-left">Tanggal</th>
                    <th class="px-3 py-2.5 text-left">Guru Piket</th>
                    <th class="px-3 py-2.5 text-left">Laporan Harian</th>
                    <th class="px-3 py-2.5 text-center">Penilaian</th>
                    <th class="px-3 py-2.5 text-center">Vakasi</th>
                    <th class="px-3 py-2.5 text-right">Aksi</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="j in jadwal" :key="j.id" class="hover:bg-gray-50/40">
                        <td class="px-4 py-2.5 font-medium text-gray-700">{{ j.tanggal }}</td>
                        <td class="px-3 py-2.5 font-semibold text-gray-700">{{ j.guru }}</td>
                        <td class="px-3 py-2.5 text-gray-500">
                            <span v-if="j.catatan_harian" class="text-gray-600">{{ j.catatan_harian }}</span>
                            <span v-else class="text-amber-500 text-xs">belum diisi</span>
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-indigo-50 text-indigo-700">{{ j.jumlah_penilaian }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <span :class="['text-xs font-semibold', j.vakasi_dibayar ? 'text-emerald-600' : 'text-gray-400']">{{ j.vakasi_dibayar ? 'Dibayar' : '—' }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-right">
                            <button @click="hapus(j)" class="text-xs font-semibold text-red-500 hover:underline">Hapus</button>
                        </td>
                    </tr>
                    <tr v-if="!jadwal.length"><td colspan="6" class="py-12 text-center text-gray-400 text-sm">Belum ada penunjukan piket pada bulan ini.</td></tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive, computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

const props = defineProps({
    jadwal: { type: Array, default: () => [] },
    filter: { type: Object, default: () => ({}) },
    guruOpsi: { type: Array, default: () => [] },
})

const bulanNama = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']

const f = reactive({ bulan: props.filter.bulan ?? (new Date().getMonth() + 1), tahun: props.filter.tahun ?? new Date().getFullYear() })
const today = new Date().toISOString().slice(0, 10)
const form = reactive({ tanggal: today, tenaga_pendidik_id: null })

const valid = computed(() => form.tanggal && form.tenaga_pendidik_id)

const apply = () => router.get(route('admin.piket.jadwal.index'), { ...f }, { preserveState: true, preserveScroll: true })

function tunjuk() {
    if (!valid.value) return
    router.post(route('admin.piket.jadwal.store'), { ...form }, {
        preserveScroll: true,
        onSuccess: () => { form.tenaga_pendidik_id = null },
    })
}

// ── Tunjuk rentang ──────────────────────────────────────────────────────────
const hariOpsi = [
    { v: 'senin', t: 'Senin' }, { v: 'selasa', t: 'Selasa' }, { v: 'rabu', t: 'Rabu' },
    { v: 'kamis', t: 'Kamis' }, { v: 'jumat', t: "Jum'at" }, { v: 'sabtu', t: 'Sabtu' }, { v: 'ahad', t: 'Ahad' },
]
const formR = reactive({ tenaga_pendidik_id: null, tanggal_mulai: today, tanggal_selesai: today, hari: [] })
const savingR = ref(false)
const validR = computed(() => formR.tenaga_pendidik_id && formR.tanggal_mulai && formR.tanggal_selesai && formR.tanggal_selesai >= formR.tanggal_mulai)
function toggleHari(v) { const i = formR.hari.indexOf(v); i >= 0 ? formR.hari.splice(i, 1) : formR.hari.push(v) }
function tunjukRentang() {
    if (!validR.value) return
    savingR.value = true
    router.post(route('admin.piket.jadwal.rentang'), { ...formR }, {
        preserveScroll: true,
        onSuccess: () => { formR.tenaga_pendidik_id = null; formR.hari = [] },
        onFinish: () => { savingR.value = false },
    })
}

async function hapus(j) {
    if (!(await confirm({ title: 'Hapus penugasan piket?', message: `${j.guru} pada ${j.tanggal}`, variant: 'danger', confirmLabel: 'Ya, Hapus' }))) return
    router.delete(route('admin.piket.jadwal.destroy', j.id), { preserveScroll: true })
}

const inp = 'px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white'
</script>

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
import { reactive, computed } from 'vue'
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

async function hapus(j) {
    if (!(await confirm({ title: 'Hapus penugasan piket?', message: `${j.guru} pada ${j.tanggal}`, variant: 'danger', confirmLabel: 'Ya, Hapus' }))) return
    router.delete(route('admin.piket.jadwal.destroy', j.id), { preserveScroll: true })
}

const inp = 'px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white'
</script>

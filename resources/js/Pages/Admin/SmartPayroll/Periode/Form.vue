<template>
    <AdminLayout :title="isEdit ? 'Edit Periode' : 'Buat Periode Penggajian'" subtitle="Smart Payroll">

        <Head :title="isEdit ? 'Edit Periode' : 'Buat Periode Penggajian'" />

        <div class="flex items-center gap-4 mb-6">
            <Link :href="route('admin.smart-payroll.periode.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <h2 class="text-xl font-semibold text-gray-900">
                {{ isEdit ? 'Edit Periode' : 'Buat Periode Baru' }}
            </h2>
        </div>

        <div class="max-w-lg">
            <form @submit.prevent="submit" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Nama Periode <span class="text-red-500">*</span>
                    </label>
                    <input v-model="form.nama" type="text" placeholder="cth: Gaji Maret 2026"
                        :class="inputCls(form.errors.nama)" />
                    <ErrMsg :e="form.errors.nama" />
                </div>

                <div class="grid grid-cols-2 gap-4" v-if="!isEdit">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Bulan <span class="text-red-500">*</span>
                        </label>
                        <select v-model.number="form.bulan" :class="inputCls(form.errors.bulan)" @change="autoNama">
                            <option v-for="b in bulanList" :key="b.v" :value="b.v">{{ b.l }}</option>
                        </select>
                        <ErrMsg :e="form.errors.bulan" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Tahun <span class="text-red-500">*</span>
                        </label>
                        <input v-model.number="form.tahun" type="number" min="2020" max="2099"
                            :class="inputCls(form.errors.tahun)" @change="autoNama" />
                        <ErrMsg :e="form.errors.tahun" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Tanggal Mulai <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.tanggal_mulai" type="date" :class="inputCls(form.errors.tanggal_mulai)" />
                        <ErrMsg :e="form.errors.tanggal_mulai" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Tanggal Selesai <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.tanggal_selesai" type="date" :min="form.tanggal_mulai"
                            :class="inputCls(form.errors.tanggal_selesai)" />
                        <ErrMsg :e="form.errors.tanggal_selesai" />
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <Link :href="route('admin.smart-payroll.periode.index')"
                        class="flex-1 py-2.5 text-center rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Batal
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60 transition-colors">
                        {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan' : 'Buat Periode') }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

// ── Inline component ──────────────────────────────────────────────────────────
const ErrMsg = {
    props: { e: String },
    template: `<p v-if="e" class="mt-1 text-xs text-red-500">{{ e }}</p>`,
}

const props = defineProps({
    periode: { type: Object, default: null },
})

const isEdit = computed(() => !!props.periode)
const now = new Date()

const form = useForm({
    nama: props.periode?.nama ?? '',
    bulan: props.periode?.bulan ?? now.getMonth() + 1,
    tahun: props.periode?.tahun ?? now.getFullYear(),
    tanggal_mulai: props.periode?.tanggal_mulai ?? '',
    tanggal_selesai: props.periode?.tanggal_selesai ?? '',
})

const bulanList = [
    { v: 1, 'l': 'Januari' }, { v: 2, 'l': 'Februari' }, { v: 3, 'l': 'Maret' },
    { v: 4, 'l': 'April' }, { v: 5, 'l': 'Mei' }, { v: 6, 'l': 'Juni' },
    { v: 7, 'l': 'Juli' }, { v: 8, 'l': 'Agustus' }, { v: 9, 'l': 'September' },
    { v: 10, 'l': 'Oktober' }, { v: 11, 'l': 'November' }, { v: 12, 'l': 'Desember' },
]

function autoNama() {
    const b = bulanList.find(x => x.v === form.bulan)
    form.nama = `Gaji ${b?.l} ${form.tahun}`

    // Auto set tanggal mulai-selesai
    const d = new Date(form.tahun, form.bulan - 1, 1)
    const akhir = new Date(form.tahun, form.bulan, 0)
    form.tanggal_mulai = d.toISOString().slice(0, 10)
    form.tanggal_selesai = akhir.toISOString().slice(0, 10)
}

// Auto-fill saat pertama load
if (!isEdit.value && !form.nama) autoNama()

function inputCls(e) {
    const b = 'w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 transition-all bg-white'
    return e ? `${b} border-red-300 focus:ring-red-100` : `${b} border-gray-200 focus:border-indigo-500 focus:ring-indigo-100`
}

function submit() {
    isEdit.value
        ? form.put(route('admin.smart-payroll.periode.update', props.periode.id))
        : form.post(route('admin.smart-payroll.periode.store'))
}
</script>
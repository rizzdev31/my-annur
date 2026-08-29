<template>
    <AdminLayout :title="isEdit ? 'Edit Gaji Pokok' : 'Setting Gaji Pokok'" subtitle="Pengaturan">

        <Head :title="isEdit ? 'Edit Gaji Pokok' : 'Setting Gaji Pokok'" />

        <div class="flex items-center gap-4 mb-6">
            <Link :href="route('admin.smart-payroll.setting-gaji.pokok.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div>
                <h2 class="text-xl font-semibold text-gray-900">{{ isEdit ? 'Edit Gaji Pokok' : 'Setting Gaji Pokok Baru' }}</h2>
                <p class="text-sm text-gray-400 mt-0.5">Setting akan otomatis menonaktifkan setting lama untuk jabatan
                    yang sama</p>
            </div>
        </div>

        <div class="max-w-lg">
            <form @submit.prevent="submit" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">

                <div v-if="!isEdit">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Jabatan <span class="text-red-500">*</span>
                    </label>
                    <select v-model="form.jabatan_id" :class="inputCls(form.errors.jabatan_id)">
                        <option value="">-- Pilih Jabatan --</option>
                        <optgroup v-for="(items, tipe) in jabatanByTipe" :key="tipe" :label="labelTipe[tipe]">
                            <option v-for="j in items" :key="j.id" :value="j.id">
                                {{ j.nama_jabatan }} ({{ j.kode_jabatan }})
                            </option>
                        </optgroup>
                    </select>
                    <FieldErr :e="form.errors.jabatan_id" />
                </div>
                <div v-else>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Jabatan</label>
                    <div class="px-4 py-2.5 bg-gray-50 rounded-xl border border-gray-200 text-sm text-gray-600">
                        {{ setting?.jabatan?.nama_jabatan }}
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Nominal Gaji Pokok <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-gray-400">Rp</span>
                        <input v-model.number="form.nominal" type="number" min="0" step="1000" placeholder="0"
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 bg-white" />
                    </div>
                    <p v-if="form.nominal > 0" class="text-xs text-indigo-600 mt-1">
                        {{ formatRp(form.nominal) }} per bulan
                    </p>
                    <FieldErr :e="form.errors.nominal" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Berlaku Mulai <span class="text-red-500">*</span>
                    </label>
                    <input v-model="form.berlaku_mulai" type="date" :class="inputCls(form.errors.berlaku_mulai)" />
                    <FieldErr :e="form.errors.berlaku_mulai" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Keterangan</label>
                    <textarea v-model="form.keterangan" rows="2" placeholder="Catatan optional..."
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 resize-none">
                    </textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <Link :href="route('admin.smart-payroll.setting-gaji.pokok.index')"
                        class="flex-1 py-2.5 text-center rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Batal
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60 transition-colors">
                        {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan' : 'Buat Setting') }}
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

const props = defineProps({
    setting: { type: Object, default: null },
    jabatan: { type: Array, default: () => [] },
})

const isEdit = computed(() => !!props.setting)

const form = useForm({
    jabatan_id: props.setting?.jabatan_id ?? '',
    nominal: props.setting?.nominal ?? 0,
    berlaku_mulai: props.setting?.berlaku_mulai ?? new Date().toISOString().slice(0, 10),
    keterangan: props.setting?.keterangan ?? '',
})

const labelTipe = { struktural: 'Struktural', fungsional: 'Fungsional', mengajar: 'Mengajar' }

const jabatanByTipe = computed(() => {
    const g = {}
    props.jabatan.forEach(j => {
        if (!g[j.tipe]) g[j.tipe] = []
        g[j.tipe].push(j)
    })
    return g
})

function formatRp(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }
function inputCls(e) {
    const b = 'w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 transition-all bg-white'
    return e ? `${b} border-red-300 focus:border-red-400 focus:ring-red-100` : `${b} border-gray-200 focus:border-indigo-500 focus:ring-indigo-100`
}

function submit() {
    isEdit.value
        ? form.put(route('admin.smart-payroll.setting-gaji.pokok.update', props.setting.id))
        : form.post(route('admin.smart-payroll.setting-gaji.pokok.store'))
}
</script>

<script>
const FieldErr = { props: { e: String }, template: `<p v-if="e" class="mt-1 text-xs text-red-500">{{ e }}</p>` }
export { FieldErr }
</script>
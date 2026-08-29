<template>
    <AdminLayout :title="isEdit ? 'Edit Jabatan' : 'Tambah Jabatan'" subtitle="Master Data">

        <Head :title="isEdit ? 'Edit Jabatan' : 'Tambah Jabatan'" />

        <div class="flex items-center gap-4 mb-6">
            <Link :href="route('admin.master.jabatan.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div>
                <h2 class="text-xl font-semibold text-gray-900">{{ isEdit ? 'Edit Jabatan' : 'Tambah Jabatan' }}</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    {{ isEdit ? `Ubah data jabatan ${form.nama_jabatan}` : 'Buat jabatan baru' }}
                </p>
            </div>
        </div>

        <div class="max-w-xl">
            <form @submit.prevent="submit" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Nama Jabatan <span class="text-red-500">*</span>
                    </label>
                    <input v-model="form.nama_jabatan" type="text"
                        placeholder="cth: Wali Kelas, Kepala Madrasah, Ustadz"
                        :class="inputCls(form.errors.nama_jabatan)" />
                    <p v-if="form.errors.nama_jabatan" class="mt-1 text-xs text-red-500">{{ form.errors.nama_jabatan }}
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Kode Jabatan <span class="text-red-500">*</span>
                    </label>
                    <input v-model="form.kode_jabatan" type="text" placeholder="cth: WK, KM, UDZ" :disabled="isEdit"
                        :class="[inputCls(form.errors.kode_jabatan), isEdit ? 'bg-gray-50 text-gray-400 cursor-not-allowed' : '']"
                        style="text-transform:uppercase" />
                    <p class="text-xs text-gray-400 mt-1">Maksimal 10 karakter, huruf & angka saja.</p>
                    <p v-if="form.errors.kode_jabatan" class="mt-1 text-xs text-red-500">{{ form.errors.kode_jabatan }}
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Tipe Jabatan <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-3 gap-2">
                        <button v-for="t in tipeOptions" :key="t.value" type="button" @click="form.tipe = t.value"
                            :class="[
                                'flex flex-col items-center gap-1.5 px-3 py-3 rounded-xl border-2 transition-all text-sm',
                                form.tipe === t.value
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-medium'
                                    : 'border-gray-200 text-gray-600 hover:border-gray-300'
                            ]">
                            <span class="text-xl">{{ t.icon }}</span>
                            <span>{{ t.label }}</span>
                            <span class="text-xs font-normal"
                                :class="form.tipe === t.value ? 'text-indigo-500' : 'text-gray-400'">
                                {{ t.desc }}
                            </span>
                        </button>
                    </div>
                    <p v-if="form.errors.tipe" class="mt-1 text-xs text-red-500">{{ form.errors.tipe }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Deskripsi</label>
                    <textarea v-model="form.deskripsi" rows="3" placeholder="Penjelasan singkat tentang jabatan ini..."
                        :class="inputCls(form.errors.deskripsi) + ' resize-none'"></textarea>
                </div>

                <!-- Wajib ikut Kegiatan Penting -->
                <div class="flex items-center justify-between p-3.5 bg-gray-50 rounded-xl border border-gray-100">
                    <div class="pr-3">
                        <p class="text-sm font-medium text-gray-700">Wajib ikut Kegiatan Penting</p>
                        <p class="text-xs text-gray-400">Matikan untuk jabatan yang dikecualikan (mis. Satpam, Kebersihan) agar kinerja piket-nya tidak terpotong.</p>
                    </div>
                    <button type="button" @click="form.wajib_kegiatan = !form.wajib_kegiatan"
                        :class="form.wajib_kegiatan ? 'bg-indigo-600' : 'bg-gray-300'"
                        class="relative rounded-full shrink-0" style="height:24px;width:44px;">
                        <span class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform"
                            :class="form.wajib_kegiatan ? 'translate-x-5' : 'translate-x-0.5'"></span>
                    </button>
                </div>

                <!-- Info setelah jabatan dibuat -->
                <div v-if="!isEdit"
                    class="flex items-start gap-3 p-3.5 bg-indigo-50 rounded-xl border border-indigo-100">
                    <svg class="w-4 h-4 text-indigo-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-xs text-indigo-700">
                        Setelah jabatan dibuat, Anda bisa langsung menambahkan
                        <strong>tugas jabatan</strong> dan <strong>setting gaji pokok</strong>
                        dari halaman daftar jabatan.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Link :href="route('admin.master.jabatan.index')"
                        class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                        Batal
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors disabled:opacity-60">
                        <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Buat Jabatan') }}
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
    jabatan: { type: Object, default: null },
})

const isEdit = computed(() => !!props.jabatan)

const form = useForm({
    nama_jabatan: props.jabatan?.nama_jabatan ?? '',
    kode_jabatan: props.jabatan?.kode_jabatan ?? '',
    tipe: props.jabatan?.tipe ?? 'fungsional',
    deskripsi: props.jabatan?.deskripsi ?? '',
    wajib_kegiatan: props.jabatan?.wajib_kegiatan ?? true,
})

const tipeOptions = [
    { value: 'struktural', label: 'Struktural', icon: '🏛️', desc: 'Kepala, Waka, dll' },
    { value: 'fungsional', label: 'Fungsional', icon: '⚙️', desc: 'Wali kelas, dll' },
    { value: 'mengajar', label: 'Mengajar', icon: '📚', desc: 'Ustadz, Guru' },
]

function inputCls(err) {
    const base = 'w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 transition-all bg-white'
    return err
        ? `${base} border-red-300 focus:border-red-500 focus:ring-red-100`
        : `${base} border-gray-200 focus:border-indigo-500 focus:ring-indigo-100`
}

function submit() {
    if (isEdit.value) {
        form.put(route('admin.master.jabatan.update', props.jabatan.id))
    } else {
        form.post(route('admin.master.jabatan.store'))
    }
}
</script>
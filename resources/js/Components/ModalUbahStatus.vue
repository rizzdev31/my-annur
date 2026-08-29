<template>
    <Transition name="modal">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="$emit('close')" />

            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">

                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Ubah Status Kepegawaian</h3>
                            <p class="text-xs text-gray-400 mt-0.5">{{ guru?.nama }}</p>
                        </div>
                    </div>
                </div>

                <!-- Status saat ini -->
                <div class="px-6 py-3 bg-gray-50 border-b border-gray-100 flex items-center gap-3">
                    <span class="text-xs text-gray-500">Status saat ini:</span>
                    <span :class="['px-2.5 py-1 rounded-lg text-xs font-medium', guru?.status_badge?.class]">
                        {{ guru?.status_badge?.label }}
                    </span>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="px-6 py-5 space-y-4">

                    <!-- Pilih status baru -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Status Baru <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <button v-for="s in statusOptions" :key="s.value" type="button"
                                @click="form.status_baru = s.value"
                                :disabled="s.value === guru?.status_kepegawaian || (s.disabled)" :class="[
                                    'flex items-center gap-2.5 px-4 py-3 rounded-xl border-2 text-sm font-medium text-left transition-all',
                                    form.status_baru === s.value
                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                        : 'border-gray-200 text-gray-600 hover:border-gray-300 hover:bg-gray-50',
                                    (s.value === guru?.status_kepegawaian || s.disabled)
                                        ? 'opacity-40 cursor-not-allowed'
                                        : 'cursor-pointer'
                                ]">
                                <span class="text-base">{{ s.icon }}</span>
                                <span>{{ s.label }}</span>
                            </button>
                        </div>

                        <!-- Warning untuk status permanen -->
                        <div v-if="isStatusPermanen"
                            class="mt-3 flex items-start gap-2 p-3 bg-red-50 border border-red-100 rounded-xl">
                            <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <p class="text-xs text-red-600">
                                <strong>Perhatian:</strong> Status ini <strong>permanen</strong> dan tidak bisa diubah
                                kembali ke aktif.
                                Pastikan data sudah benar sebelum menyimpan.
                            </p>
                        </div>
                    </div>

                    <!-- Tanggal Efektif -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Tanggal Efektif <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.tanggal_efektif" type="date"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" />
                            <p v-if="errors.tanggal_efektif" class="mt-1 text-xs text-red-500">{{ errors.tanggal_efektif
                                }}</p>
                        </div>

                        <!-- Tanggal Kembali (hanya untuk status sementara) -->
                        <div v-if="isStatusSementara">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Estimasi Kembali
                                <span v-if="form.status_baru === 'cuti' || form.status_baru === 'cuti_sakit'"
                                    class="text-red-500">*</span>
                            </label>
                            <input v-model="form.tanggal_kembali" type="date"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" />
                            <p v-if="errors.tanggal_kembali" class="mt-1 text-xs text-red-500">{{ errors.tanggal_kembali
                                }}</p>
                        </div>
                    </div>

                    <!-- Alasan -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Alasan / Keterangan <span class="text-red-500">*</span>
                        </label>
                        <textarea v-model="form.alasan" rows="3" :placeholder="alasanPlaceholder"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 resize-none">
                        </textarea>
                        <p v-if="errors.alasan" class="mt-1 text-xs text-red-500">{{ errors.alasan }}</p>
                    </div>

                    <!-- Upload Dokumen -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Dokumen Pendukung
                            <span class="text-gray-400 font-normal">(opsional)</span>
                        </label>
                        <div class="flex items-center gap-3">
                            <label
                                class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-dashed border-gray-300 text-sm text-gray-500 hover:border-indigo-400 hover:text-indigo-600 cursor-pointer transition-colors flex-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                </svg>
                                {{ dokumenNama || 'Pilih file (PDF/JPG/PNG)' }}
                                <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png"
                                    @change="onDokumenChange" />
                            </label>
                            <button v-if="dokumenNama" type="button" @click="removeDokumen"
                                class="p-2.5 rounded-xl text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Surat resign, surat dokter, SK cuti, dll. Maks 5MB.</p>
                    </div>

                </form>

                <!-- Footer -->
                <div class="flex gap-3 px-6 pb-5">
                    <button type="button" @click="$emit('close')"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button @click="submit" :disabled="!form.status_baru || loading" :class="[
                        'flex-1 py-2.5 rounded-xl text-sm font-semibold transition-colors disabled:opacity-50',
                        isStatusPermanen
                            ? 'bg-red-600 hover:bg-red-700 text-white'
                            : 'bg-indigo-600 hover:bg-indigo-700 text-white'
                    ]">
                        {{ loading ? 'Menyimpan...' : 'Simpan Status' }}
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    show: { type: Boolean, default: false },
    guru: { type: Object, default: null },
})

const emit = defineEmits(['close', 'success'])

const loading = ref(false)
const dokumenNama = ref('')
const errors = ref({})

const form = ref({
    status_baru: '',
    tanggal_efektif: new Date().toISOString().split('T')[0],
    tanggal_kembali: '',
    alasan: '',
    dokumen_file: null,
})

// Reset form saat modal dibuka
watch(() => props.show, (val) => {
    if (val) {
        form.value = {
            status_baru: '',
            tanggal_efektif: new Date().toISOString().split('T')[0],
            tanggal_kembali: '',
            alasan: '',
            dokumen_file: null,
        }
        errors.value = {}
        dokumenNama.value = ''
    }
})

const statusOptions = computed(() => {
    const current = props.guru?.status_kepegawaian ?? 'aktif'
    const isPermanent = ['resign', 'pensiun', 'meninggal'].includes(current)

    return [
        { value: 'aktif', label: 'Aktif', icon: '✅', disabled: isPermanent },
        { value: 'cuti', label: 'Cuti', icon: '🏖️', disabled: isPermanent },
        { value: 'cuti_sakit', label: 'Cuti Sakit', icon: '🏥', disabled: isPermanent },
        { value: 'nonaktif_sementara', label: 'Nonaktif Sementara', icon: '⏸️', disabled: isPermanent },
        { value: 'resign', label: 'Resign', icon: '📤', disabled: isPermanent },
        { value: 'pensiun', label: 'Pensiun', icon: '🎖️', disabled: isPermanent },
        { value: 'meninggal', label: 'Meninggal', icon: '🕊️', disabled: isPermanent },
    ]
})

const isStatusPermanen = computed(() => ['resign', 'pensiun', 'meninggal'].includes(form.value.status_baru))
const isStatusSementara = computed(() => ['cuti', 'cuti_sakit', 'nonaktif_sementara'].includes(form.value.status_baru))

const alasanPlaceholder = computed(() => {
    const map = {
        'aktif': 'Alasan pengaktifan kembali...',
        'cuti': 'Keperluan pribadi / keluarga...',
        'cuti_sakit': 'Nama penyakit / kondisi kesehatan...',
        'nonaktif_sementara': 'Alasan nonaktif sementara...',
        'resign': 'Alasan mengundurkan diri...',
        'pensiun': 'Keterangan pensiun...',
        'meninggal': 'Tanggal dan keterangan...',
    }
    return map[form.value.status_baru] ?? 'Tulis alasan perubahan status...'
})

function onDokumenChange(e) {
    const file = e.target.files[0]
    if (file) {
        form.value.dokumen_file = file
        dokumenNama.value = file.name
    }
}

function removeDokumen() {
    form.value.dokumen_file = null
    dokumenNama.value = ''
}

function submit() {
    if (!form.value.status_baru || !form.value.alasan || !form.value.tanggal_efektif) {
        errors.value = {
            status_baru: !form.value.status_baru ? 'Pilih status baru' : '',
            alasan: !form.value.alasan ? 'Alasan wajib diisi' : '',
            tanggal_efektif: !form.value.tanggal_efektif ? 'Tanggal efektif wajib diisi' : '',
        }
        return
    }

    loading.value = true

    const formData = new FormData()
    formData.append('status_baru', form.value.status_baru)
    formData.append('tanggal_efektif', form.value.tanggal_efektif)
    formData.append('alasan', form.value.alasan)
    if (form.value.tanggal_kembali) {
        formData.append('tanggal_kembali', form.value.tanggal_kembali)
    }
    if (form.value.dokumen_file) {
        formData.append('dokumen_file', form.value.dokumen_file)
    }

    router.post(
        route('admin.master.tenaga-pendidik.ubah-status', props.guru.id),
        formData,
        {
            onSuccess: () => { emit('success'); emit('close') },
            onError: (e) => { errors.value = e },
            onFinish: () => { loading.value = false },
        }
    )
}
</script>

<style scoped>
.modal-enter-active {
    transition: all 0.2s ease;
}

.modal-leave-active {
    transition: all 0.15s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
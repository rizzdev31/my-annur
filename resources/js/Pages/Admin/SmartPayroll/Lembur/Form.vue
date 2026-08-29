<template>
    <AdminLayout>
        <div class="flex items-center gap-3 mb-5">
            <Link :href="route('admin.smart-payroll.lembur.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </Link>
            <h1 class="text-lg font-bold text-gray-800">Buat Lembur Kolektif</h1>
        </div>

        <div class="max-w-3xl bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">Jabatan *</label>
                    <select v-model.number="f.jabatan_id" class="w-full px-3 py-2.5 rounded-xl text-sm border border-gray-200">
                        <option :value="null" disabled>Pilih jabatan</option>
                        <option v-for="j in jabatan" :key="j.id" :value="j.id">{{ j.nama_jabatan }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">Setting Vakasi Lembur *</label>
                    <select v-model.number="f.setting_vakasi_id" class="w-full px-3 py-2.5 rounded-xl text-sm border border-gray-200">
                        <option :value="null" disabled>Pilih tarif lembur</option>
                        <option v-for="s in settingLembur" :key="s.id" :value="s.id">
                            {{ s.nama }} — {{ rupiah(s.nominal) }} (min {{ s.min_durasi_menit || 0 }}m)
                        </option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1.5">Judul *</label>
                <input v-model="f.judul" type="text" placeholder="Mis. Lembur persiapan ujian"
                    class="w-full px-3 py-2.5 rounded-xl text-sm border border-gray-200" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1.5">Deskripsi</label>
                <textarea v-model="f.deskripsi" rows="2"
                    class="w-full px-3 py-2.5 rounded-xl text-sm border border-gray-200"></textarea>
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">Tanggal *</label>
                    <input v-model="f.tanggal" type="date" class="w-full px-3 py-2.5 rounded-xl text-sm border border-gray-200" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">Jam Mulai *</label>
                    <input v-model="f.jam_mulai" type="time" class="w-full px-3 py-2.5 rounded-xl text-sm border border-gray-200" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">Durasi (menit) *</label>
                    <input v-model.number="f.durasi_menit" type="number" min="1"
                        class="w-full px-3 py-2.5 rounded-xl text-sm border border-gray-200" />
                </div>
            </div>

            <div v-if="jamSelesai || minWarn" class="text-xs px-3 py-2 rounded-lg"
                :class="minWarn ? 'bg-red-50 text-red-600' : 'bg-indigo-50 text-indigo-600'">
                <span v-if="jamSelesai">Jam selesai otomatis: <b>{{ jamSelesai }}</b> · upload bukti dibuka setelah jam ini.</span>
                <span v-if="minWarn"> ⚠ Durasi di bawah minimal setting ({{ selectedSetting?.min_durasi_menit }} menit).</span>
            </div>

            <!-- Peserta -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold text-gray-500">Peserta (guru jabatan ini) *</label>
                    <button type="button" @click="toggleAll" class="text-xs text-indigo-600 font-medium">
                        {{ allSelected ? 'Kosongkan' : 'Pilih semua' }}
                    </button>
                </div>
                <div v-if="!f.jabatan_id" class="text-sm text-gray-400 py-4 text-center border border-dashed border-gray-200 rounded-xl">
                    Pilih jabatan dulu untuk menampilkan guru.
                </div>
                <div v-else-if="!guruFiltered.length" class="text-sm text-gray-400 py-4 text-center border border-dashed border-gray-200 rounded-xl">
                    Tidak ada guru aktif pada jabatan ini.
                </div>
                <div v-else class="grid sm:grid-cols-2 gap-2 max-h-64 overflow-y-auto">
                    <label v-for="g in guruFiltered" :key="g.id"
                        class="flex items-center gap-2.5 px-3 py-2 rounded-xl border cursor-pointer"
                        :class="f.guru_ids.includes(g.id) ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200'">
                        <input type="checkbox" :value="g.id" v-model="f.guru_ids" class="accent-indigo-600" />
                        <img v-if="g.foto" :src="g.foto" class="w-7 h-7 rounded-full object-cover" />
                        <div v-else class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-[11px] font-bold text-indigo-600">
                            {{ g.nama?.charAt(0) }}
                        </div>
                        <span class="text-sm text-gray-700 truncate">{{ g.nama }}</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <Link :href="route('admin.smart-payroll.lembur.index')"
                    class="px-4 py-2 rounded-xl text-sm font-semibold text-gray-600 border border-gray-200">Batal</Link>
                <button @click="submit" :disabled="!valid || saving"
                    class="px-5 py-2 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50">
                    {{ saving ? 'Menyimpan…' : 'Simpan & Tugaskan' }}
                </button>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    jabatan: { type: Array, default: () => [] },
    settingLembur: { type: Array, default: () => [] },
    guru: { type: Array, default: () => [] },
})

const f = ref({
    jabatan_id: null, setting_vakasi_id: null, judul: '', deskripsi: '',
    tanggal: new Date().toISOString().slice(0, 10), jam_mulai: '', durasi_menit: null, guru_ids: [],
})
const saving = ref(false)

const guruFiltered = computed(() =>
    !f.value.jabatan_id ? [] : props.guru.filter(g => (g.jabatan_ids || []).includes(f.value.jabatan_id))
)
const allSelected = computed(() => guruFiltered.value.length > 0 && f.value.guru_ids.length === guruFiltered.value.length)
const selectedSetting = computed(() => props.settingLembur.find(s => s.id === f.value.setting_vakasi_id))

const jamSelesai = computed(() => {
    if (!f.value.jam_mulai || !f.value.durasi_menit) return null
    const [h, m] = f.value.jam_mulai.split(':').map(Number)
    const total = h * 60 + m + Number(f.value.durasi_menit)
    const hh = String(Math.floor(total / 60) % 24).padStart(2, '0')
    const mm = String(total % 60).padStart(2, '0')
    return `${hh}:${mm}`
})
const minWarn = computed(() => {
    const min = selectedSetting.value?.min_durasi_menit
    return min && f.value.durasi_menit && Number(f.value.durasi_menit) < min
})
const valid = computed(() =>
    f.value.jabatan_id && f.value.setting_vakasi_id && f.value.judul && f.value.tanggal &&
    f.value.jam_mulai && f.value.durasi_menit > 0 && !minWarn.value && f.value.guru_ids.length > 0
)

// Reset peserta saat ganti jabatan
watch(() => f.value.jabatan_id, () => { f.value.guru_ids = [] })

function toggleAll() {
    f.value.guru_ids = allSelected.value ? [] : guruFiltered.value.map(g => g.id)
}
function rupiah(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }
function submit() {
    saving.value = true
    router.post(route('admin.smart-payroll.lembur.store'), f.value, {
        onFinish: () => saving.value = false,
    })
}
</script>

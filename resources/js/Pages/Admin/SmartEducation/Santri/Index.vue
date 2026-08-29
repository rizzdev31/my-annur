<template>
    <AdminLayout title="Santri" subtitle="Smart Education">

        <Head title="Santri" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Data Santri</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    {{ summary.aktif }} aktif dari {{ summary.total }} santri · {{ summary.putra }} putra · {{ summary.putri }} putri
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <ImportExcel :template-url="route('admin.smart-education.santri.template-import')"
                    :import-url="route('admin.smart-education.santri.import')" label="Import Santri" />
                <button @click="openCreate"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Santri
                </button>
            </div>
        </div>

        <!-- Pencarian -->
        <div class="mb-4">
            <input v-model="q" type="text" placeholder="Cari nama / NIP..."
                class="w-full sm:w-72 px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all bg-white" />
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Santri</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">Kelas</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">WhatsApp</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="s in filtered" :key="s.id"
                        :class="['hover:bg-gray-50/40 transition-colors', !s.is_aktif ? 'opacity-60' : '']">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div :class="[
                                    'w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold shrink-0',
                                    s.jenis_kelamin === 'P' ? 'bg-pink-100 text-pink-600' : 'bg-sky-100 text-sky-600'
                                ]">{{ initial(s.nama_lengkap) }}</div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ s.nama_lengkap }}</p>
                                    <p class="text-xs text-gray-400 font-mono">{{ s.nip }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 hidden md:table-cell">
                            <div v-if="s.kelas.length" class="flex flex-wrap gap-1">
                                <span v-for="k in s.kelas" :key="k.id" :class="[
                                    'px-2 py-0.5 rounded-lg text-xs font-medium',
                                    k.jenis === 'tahfidz' ? 'bg-violet-50 text-violet-700' : 'bg-sky-50 text-sky-700'
                                ]">{{ k.nama }}</span>
                            </div>
                            <span v-else class="text-xs text-gray-400">Belum ada kelas</span>
                        </td>
                        <td class="px-5 py-3.5 hidden lg:table-cell">
                            <span class="text-sm text-gray-600">{{ s.no_whatsapp || '—' }}</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span :class="[
                                'inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium',
                                s.is_aktif ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'
                            ]">
                                <span :class="['w-1.5 h-1.5 rounded-full', s.is_aktif ? 'bg-emerald-500' : 'bg-gray-400']"></span>
                                {{ s.is_aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                            <span :class="['ml-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-medium', s.punya_password ? 'bg-teal-50 text-teal-700' : 'bg-amber-50 text-amber-600']" title="Status Portal Santri">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                {{ s.punya_password ? 'Portal aktif' : 'Belum aktivasi' }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex justify-end gap-1">
                                <button @click="openPassword(s)" title="Password Portal Santri"
                                    class="p-2 rounded-lg text-gray-400 hover:text-teal-600 hover:bg-teal-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                </button>
                                <button @click="openEdit(s)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button v-if="s.is_aktif" @click="hapus(s)" title="Nonaktifkan"
                                    class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                </button>
                                <button v-else @click="aktifkan(s)" title="Aktifkan kembali"
                                    class="p-2 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!filtered.length">
                        <td colspan="5" class="py-14 text-center text-sm text-gray-400">
                            {{ q ? 'Santri tidak ditemukan.' : 'Belum ada santri. Tambahkan sekarang.' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal -->
        <Transition name="modal">
            <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeForm" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                    <h3 class="text-base font-semibold text-gray-900 mb-5">{{ editTarget ? 'Edit Santri' : 'Tambah Santri' }}</h3>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">NIP / NIS <span class="text-red-500">*</span></label>
                                <input v-model="form.nip" type="text" placeholder="cth: 2024001" :class="inputCls(form.errors?.nip)" />
                                <p v-if="form.errors?.nip" class="mt-1 text-xs text-red-500">{{ form.errors.nip }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Jenis Kelamin <span class="text-red-500">*</span></label>
                                <select v-model="form.jenis_kelamin" :class="inputCls(form.errors?.jenis_kelamin)">
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Lengkap <span class="text-red-500">*</span></label>
                            <input v-model="form.nama_lengkap" type="text" placeholder="Nama lengkap santri" :class="inputCls(form.errors?.nama_lengkap)" />
                            <p v-if="form.errors?.nama_lengkap" class="mt-1 text-xs text-red-500">{{ form.errors.nama_lengkap }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Panggilan</label>
                                <input v-model="form.nama_panggilan" type="text" :class="inputCls()" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">No. WhatsApp (Ortu)</label>
                                <input v-model="form.no_whatsapp" type="text" placeholder="08xxxx" :class="inputCls()" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Tempat Lahir</label>
                                <input v-model="form.tempat_lahir" type="text" :class="inputCls()" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Tanggal Lahir</label>
                                <input v-model="form.tanggal_lahir" type="date" :class="inputCls()" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                            <input v-model="form.email" type="email" :class="inputCls(form.errors?.email)" />
                            <p v-if="form.errors?.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Program Qur'an</label>
                                <select v-model="form.program_quran" :class="inputCls()">
                                    <option :value="null">— belum ditentukan</option>
                                    <option value="tahsin">Tahsin</option>
                                    <option value="tahfidz">Tahfidz</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Level Tahsin</label>
                                <select v-model="form.tahsin_level" :class="inputCls()" :disabled="form.program_quran !== 'tahsin'">
                                    <option :value="null">—</option>
                                    <option v-for="lv in [1,2,3,4,5]" :key="lv" :value="lv">Level {{ lv }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Kelas (boleh sekolah & tahfidz)</label>
                            <div v-if="kelas.length" class="flex flex-wrap gap-2 p-3 rounded-xl border border-gray-200 bg-gray-50/50 max-h-40 overflow-y-auto">
                                <label v-for="k in kelas" :key="k.id" :class="[
                                    'cursor-pointer px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors',
                                    form.kelas_ids.includes(k.id)
                                        ? (k.jenis === 'tahfidz' ? 'bg-violet-600 text-white border-violet-600' : 'bg-indigo-600 text-white border-indigo-600')
                                        : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-300'
                                ]">
                                    <input type="checkbox" :value="k.id" v-model="form.kelas_ids" class="hidden" />
                                    {{ k.nama }} <span class="opacity-60">· {{ k.jenis === 'tahfidz' ? 'Tahfidz' : 'Sekolah' }}</span>
                                </label>
                            </div>
                            <p v-else class="text-xs text-gray-400">Belum ada kelas. Tambahkan di menu Kelas dulu.</p>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button @click="closeForm" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">Batal</button>
                        <button @click="submit" :disabled="saving"
                            class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60 transition-colors">
                            {{ saving ? 'Menyimpan...' : (editTarget ? 'Simpan' : 'Tambah') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Modal Password Portal Santri -->
        <Transition name="modal">
            <div v-if="pwTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="pwTarget = null">
                <div class="absolute inset-0 bg-black/40"></div>
                <div class="relative bg-white rounded-2xl w-full max-w-sm p-6 shadow-xl">
                    <h3 class="text-base font-semibold text-gray-900">Password Portal Santri</h3>
                    <p class="text-sm text-gray-500 mt-0.5">{{ pwTarget.nama_lengkap }} · NIS {{ pwTarget.nip }}</p>
                    <p class="mt-3 text-xs px-3 py-2 rounded-lg" :class="pwTarget.punya_password ? 'bg-teal-50 text-teal-700' : 'bg-amber-50 text-amber-700'">
                        Status: <b>{{ pwTarget.punya_password ? 'Portal aktif (wali sudah punya password)' : 'Belum aktivasi' }}</b>
                    </p>

                    <label class="block text-xs font-medium text-gray-600 mt-4 mb-1">Set password langsung (min 6)</label>
                    <input v-model="pwBaru" type="text" placeholder="Password untuk diserahkan ke wali"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" />
                    <button @click="setPw" :disabled="pwBaru.length < 6" class="w-full mt-2 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-50">Simpan Password</button>

                    <div class="border-t border-gray-100 my-4"></div>
                    <button @click="resetPw" class="w-full py-2.5 rounded-xl bg-red-50 text-red-600 text-sm font-semibold hover:bg-red-100">Reset (kosongkan) password</button>
                    <p class="text-[11px] text-gray-400 mt-1.5">Reset → wali aktivasi ulang via tanggal lahir / OTP WhatsApp.</p>

                    <button @click="pwTarget = null" class="w-full mt-4 py-2 text-sm text-gray-500 font-medium">Tutup</button>
                </div>
            </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'
import ImportExcel from '@/Components/ImportExcel.vue'

const props = defineProps({
    santri: { type: Array, default: () => [] },
    kelas: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
})

const q = ref('')
const filtered = computed(() => {
    const t = q.value.trim().toLowerCase()
    if (!t) return props.santri
    return props.santri.filter(s =>
        s.nama_lengkap.toLowerCase().includes(t) || (s.nip ?? '').toLowerCase().includes(t)
    )
})

const showForm = ref(false)
const editTarget = ref(null)
const saving = ref(false)
const blank = () => ({
    nip: '', nama_lengkap: '', nama_panggilan: '', email: '', jenis_kelamin: 'L',
    tempat_lahir: '', tanggal_lahir: '', no_whatsapp: '', program_quran: null, tahsin_level: null, kelas_ids: [], errors: {},
})
const form = reactive(blank())

function openCreate() {
    editTarget.value = null
    Object.assign(form, blank())
    showForm.value = true
}
function openEdit(s) {
    editTarget.value = s
    Object.assign(form, {
        nip: s.nip, nama_lengkap: s.nama_lengkap, nama_panggilan: s.nama_panggilan ?? '',
        email: s.email ?? '', jenis_kelamin: s.jenis_kelamin, tempat_lahir: s.tempat_lahir ?? '',
        tanggal_lahir: s.tanggal_lahir ?? '', no_whatsapp: s.no_whatsapp ?? '',
        program_quran: s.program_quran ?? null, tahsin_level: s.tahsin_level ?? null,
        kelas_ids: [...(s.kelas_ids ?? [])], errors: {},
    })
    showForm.value = true
}
function closeForm() {
    showForm.value = false
    editTarget.value = null
    Object.assign(form, blank())
}
function submit() {
    saving.value = true
    const payload = {
        nip: form.nip, nama_lengkap: form.nama_lengkap, nama_panggilan: form.nama_panggilan,
        email: form.email, jenis_kelamin: form.jenis_kelamin, tempat_lahir: form.tempat_lahir,
        tanggal_lahir: form.tanggal_lahir || null, no_whatsapp: form.no_whatsapp,
        program_quran: form.program_quran || null, tahsin_level: form.tahsin_level || null, kelas_ids: form.kelas_ids,
    }
    const opts = {
        preserveScroll: true,
        onSuccess: () => closeForm(),
        onError: (e) => { form.errors = e },
        onFinish: () => saving.value = false,
    }
    if (editTarget.value) router.put(route('admin.smart-education.santri.update', editTarget.value.id), payload, opts)
    else router.post(route('admin.smart-education.santri.store'), payload, opts)
}
async function hapus(s) {
    if (!(await confirm({ title: `Nonaktifkan santri "${s.nama_lengkap}"?`, variant: 'danger', confirmLabel: 'Ya, Nonaktifkan' }))) return
    router.delete(route('admin.smart-education.santri.destroy', s.id), { preserveScroll: true })
}
async function aktifkan(s) {
    if (!(await confirm({ title: `Aktifkan kembali santri "${s.nama_lengkap}"?`, variant: 'success', confirmLabel: 'Ya, Aktifkan' }))) return
    router.patch(route('admin.smart-education.santri.aktifkan', s.id), {}, { preserveScroll: true })
}
function initial(nama) {
    return (nama ?? '?').trim().charAt(0).toUpperCase()
}

// ── Password Portal Santri ──────────────────────────────────────────────────
const pwTarget = ref(null)
const pwBaru = ref('')
function openPassword(s) { pwTarget.value = s; pwBaru.value = '' }
async function resetPw() {
    const s = pwTarget.value
    if (!(await confirm({ title: `Reset password Portal "${s.nama_lengkap}"?`, message: 'Password dikosongkan & sesi login dicabut. Wali harus aktivasi ulang (via tanggal lahir / OTP).', variant: 'danger', confirmLabel: 'Ya, Reset' }))) return
    router.patch(route('admin.smart-education.santri.reset-password-portal', s.id), {}, { preserveScroll: true, onSuccess: () => { pwTarget.value = null } })
}
function setPw() {
    if (pwBaru.value.length < 6) return
    router.patch(route('admin.smart-education.santri.set-password-portal', pwTarget.value.id), { password: pwBaru.value }, { preserveScroll: true, onSuccess: () => { pwTarget.value = null } })
}
function inputCls(e) {
    const b = 'w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 transition-all bg-white'
    return e ? `${b} border-red-300 focus:ring-red-100` : `${b} border-gray-200 focus:border-indigo-500 focus:ring-indigo-100`
}
</script>

<style scoped>
.modal-enter-active { transition: all 0.2s ease; }
.modal-leave-active { transition: all 0.15s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>

<template>
    <AdminLayout title="Setting Jabatan" subtitle="Master Data">

        <Head :title="`Jabatan — ${guru.nama}`" />

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <Link :href="route('admin.master.tenaga-pendidik.show', guru.id)"
                    class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div class="flex items-center gap-3">
                    <img v-if="guru.foto" :src="guru.foto" class="w-10 h-10 rounded-xl object-cover shrink-0" />
                    <div v-else
                        class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-400 to-violet-500 flex items-center justify-center shrink-0">
                        <span class="text-white font-bold text-sm">{{ guru.nama?.charAt(0) }}</span>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Setting Jabatan</h2>
                        <p class="text-sm text-gray-400 mt-0.5">{{ guru.nama }} · {{ guru.nip }}</p>
                    </div>
                </div>
            </div>
            <button @click="showModal = true"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Jabatan
            </button>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

            <!-- ── Kiri: Ringkasan Gaji ──────────────────────────────────── -->
            <div class="space-y-4">

                <!-- Total gaji pokok -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <p class="text-sm font-semibold text-gray-800">Ringkasan Gaji Pokok</p>
                        <p class="text-xs text-gray-400 mt-0.5">Total dari semua jabatan aktif</p>
                    </div>
                    <div class="px-5 py-4 space-y-3">
                        <div v-for="d in detailGajiPokok" :key="d.jabatan_id"
                            class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-gray-700 truncate">{{ d.nama_jabatan }}</p>
                                <span :class="['text-xs px-1.5 py-0.5 rounded font-medium', badgeSumber(d.sumber)]">
                                    {{ labelSumber[d.sumber] ?? d.sumber }}
                                </span>
                            </div>
                            <p class="text-sm font-bold text-indigo-700 shrink-0">{{ formatRp(d.nominal) }}</p>
                        </div>

                        <div v-if="!detailGajiPokok.length" class="text-xs text-gray-400 text-center py-2">
                            Belum ada setting gaji pokok
                        </div>

                        <div v-if="detailGajiPokok.length > 1"
                            class="pt-2 border-t border-gray-100 flex justify-between items-center">
                            <p class="text-xs text-gray-500 font-medium">Total</p>
                            <p class="text-base font-black text-indigo-700">{{ formatRp(totalGajiPokok) }}</p>
                        </div>
                        <p class="text-xs text-gray-400">per bulan</p>
                    </div>
                    <div class="px-5 py-3 border-t border-gray-50 bg-gray-50/30">
                        <Link :href="route('admin.smart-payroll.setting-gaji.pokok.index')"
                            class="text-xs text-indigo-600 hover:underline flex items-center gap-1">
                            Atur setting gaji pokok jabatan →
                        </Link>
                    </div>
                </div>

                <!-- Info guru -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-3">
                    <p class="text-sm font-semibold text-gray-800">Info Guru</p>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-400">Status</span>
                            <span :class="['text-xs font-medium px-2 py-0.5 rounded-lg',
                                guru.is_aktif ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600']">
                                {{ guru.is_aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-400">Jabatan awal</span>
                            <span class="text-xs font-medium text-gray-700">{{ guru.jabatan }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-400">Jabatan aktif</span>
                            <span class="text-xs font-bold text-indigo-700">{{ jabatanAktif.length }}</span>
                        </div>
                        <div class="flex justify-between" v-if="jabatanAktif.length > 1">
                            <span class="text-xs text-gray-400">Status</span>
                            <span class="text-xs font-semibold text-amber-600">Rangkap jabatan</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Kanan: Daftar Jabatan ──────────────────────────────────── -->
            <div class="xl:col-span-2 space-y-4">

                <!-- Jabatan Aktif -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <p class="text-sm font-semibold text-gray-800">
                            Jabatan Aktif
                            <span class="ml-2 text-xs font-normal text-gray-400">({{ jabatanAktif.length }}
                                jabatan)</span>
                        </p>
                    </div>

                    <div class="divide-y divide-gray-50">
                        <div v-for="j in jabatanAktif" :key="j.pivot_id" :class="['px-5 py-5 transition-colors hover:bg-gray-50/40',
                            j.adalah_utama ? 'bg-indigo-50/20' : '']">
                            <div class="flex items-start justify-between gap-4">

                                <!-- Kiri: info jabatan -->
                                <div class="flex items-start gap-3 flex-1 min-w-0">
                                    <!-- Avatar tipe jabatan -->
                                    <div
                                        :class="['w-11 h-11 rounded-xl flex items-center justify-center text-sm font-bold shrink-0', bgTipe(j.tipe)]">
                                        {{ j.kode_jabatan || j.nama_jabatan?.charAt(0) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="text-sm font-semibold text-gray-900">{{ j.nama_jabatan }}</p>
                                            <span v-if="j.adalah_utama"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">
                                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                                                Utama
                                            </span>
                                            <span
                                                :class="['text-xs px-2 py-0.5 rounded-lg font-medium capitalize', tipeClass(j.tipe)]">
                                                {{ j.tipe }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-400 mt-1">
                                            Berlaku mulai {{ j.berlaku_mulai }}
                                            <template v-if="j.ditetapkan_oleh">
                                                · Ditetapkan oleh {{ j.ditetapkan_oleh }}
                                            </template>
                                        </p>
                                        <p v-if="j.keterangan" class="text-xs text-gray-500 mt-1 italic">
                                            "{{ j.keterangan }}"
                                        </p>
                                        <!-- Gaji pokok jabatan ini -->
                                        <div v-if="gajiJabatan(j.jabatan_id)"
                                            class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50 rounded-lg">
                                            <span class="text-xs text-emerald-500">💰</span>
                                            <span class="text-xs text-emerald-600">Gaji pokok:</span>
                                            <span class="text-xs font-bold text-emerald-700">
                                                {{ formatRp(gajiJabatan(j.jabatan_id)) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Kanan: aksi -->
                                <div class="flex flex-col items-end gap-2 shrink-0">
                                    <!-- Set Utama -->
                                    <button v-if="!j.adalah_utama" @click="setUtama(j)"
                                        class="px-3 py-1.5 rounded-xl text-xs font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 transition-colors border border-indigo-200">
                                        Jadikan Utama
                                    </button>
                                    <!-- Edit -->
                                    <button @click="editJabatan(j)"
                                        class="px-3 py-1.5 rounded-xl text-xs font-medium text-gray-600 bg-gray-50 hover:bg-gray-100 transition-colors border border-gray-200">
                                        Edit
                                    </button>
                                    <!-- Lepas -->
                                    <button @click="lepas(j)" :disabled="jabatanAktif.length <= 1" :class="[
                                        'px-3 py-1.5 rounded-xl text-xs font-medium transition-colors border',
                                        jabatanAktif.length <= 1
                                            ? 'text-gray-300 border-gray-100 cursor-not-allowed'
                                            : 'text-red-500 bg-red-50 hover:bg-red-100 border-red-200'
                                    ]" :title="jabatanAktif.length <= 1 ? 'Minimal 1 jabatan harus aktif' : ''">
                                        Lepas
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="!jabatanAktif.length" class="px-5 py-10 text-center">
                            <p class="text-sm text-gray-400">Belum ada jabatan aktif dari sistem pivot.</p>
                            <button @click="showModal = true" class="mt-3 text-sm text-indigo-600 hover:underline">
                                Tambah jabatan →
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Riwayat Jabatan -->
                <div v-if="riwayatJabatan.length" class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <button @click="showRiwayat = !showRiwayat"
                        class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-gray-50 transition-colors">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Riwayat Jabatan</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ riwayatJabatan.length }} jabatan sudah berakhir
                            </p>
                        </div>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="showRiwayat ? 'rotate-180' : ''"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div v-if="showRiwayat" class="divide-y divide-gray-50">
                        <div v-for="j in riwayatJabatan" :key="j.pivot_id"
                            class="px-5 py-4 flex items-center gap-4 opacity-60">
                            <div
                                :class="['w-9 h-9 rounded-lg flex items-center justify-center text-xs font-bold shrink-0', bgTipe(j.tipe)]">
                                {{ j.kode_jabatan || j.nama_jabatan?.charAt(0) }}
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-700">{{ j.nama_jabatan }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ j.berlaku_mulai }} – {{ j.berlaku_selesai }}
                                    <span v-if="j.keterangan"> · {{ j.keterangan }}</span>
                                </p>
                            </div>
                            <span v-if="j.adalah_utama" class="text-xs text-gray-400 shrink-0">Pernah utama</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ MODAL TAMBAH JABATAN ════════════════════════════════════════ -->
        <Transition name="modal">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeModal" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">

                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-base font-semibold text-gray-900">Tambah Jabatan</h3>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Untuk <strong>{{ guru.nama }}</strong>
                        </p>
                    </div>

                    <div class="px-6 py-5 space-y-4">

                        <!-- Pilih jabatan -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Jabatan <span class="text-red-500">*</span>
                            </label>
                            <div class="space-y-1.5 max-h-52 overflow-y-auto">
                                <button v-for="j in jabatanTersedia" :key="j.id" type="button"
                                    @click="form.jabatan_id = j.id" :class="[
                                        'w-full flex items-center justify-between gap-3 px-4 py-3 rounded-xl border-2 text-left transition-all',
                                        form.jabatan_id === j.id ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300',
                                        j.sudah_aktif ? 'opacity-40 cursor-not-allowed' : ''
                                    ]" :disabled="j.sudah_aktif">
                                    <div class="flex items-center gap-3">
                                        <div
                                            :class="['w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold shrink-0', bgTipe(j.tipe)]">
                                            {{ j.kode_jabatan }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-800">{{ j.nama_jabatan }}</p>
                                            <p class="text-xs text-gray-400 capitalize">{{ j.tipe }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <p v-if="j.gaji_pokok > 0" class="text-xs font-bold text-indigo-600">
                                            {{ formatRp(j.gaji_pokok) }}
                                        </p>
                                        <p v-else class="text-xs text-gray-400">Belum ada gaji</p>
                                        <p v-if="j.sudah_aktif" class="text-xs text-emerald-600 mt-0.5">Aktif</p>
                                    </div>
                                </button>
                            </div>
                            <p v-if="formErrors.jabatan_id" class="mt-1 text-xs text-red-500">{{ formErrors.jabatan_id
                                }}</p>
                        </div>

                        <!-- Berlaku mulai -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Berlaku Mulai <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.berlaku_mulai" type="date"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 bg-white" />
                            <p v-if="formErrors.berlaku_mulai" class="mt-1 text-xs text-red-500">{{
                                formErrors.berlaku_mulai }}
                            </p>
                        </div>

                        <!-- Jadikan utama -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer"
                            @click="form.adalah_utama = !form.adalah_utama">
                            <div>
                                <p class="text-sm font-medium text-gray-800">Jadikan Jabatan Utama</p>
                                <p class="text-xs text-gray-400 mt-0.5">Jabatan utama = referensi gaji pokok primer</p>
                            </div>
                            <div :class="['relative rounded-full transition-colors flex-shrink-0',
                                form.adalah_utama ? 'bg-indigo-600' : 'bg-gray-300']" style="height:22px;width:40px">
                                <span :class="[
                                    'absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200',
                                    form.adalah_utama ? 'translate-x-5' : 'translate-x-0.5']">
                                </span>
                            </div>
                        </div>

                        <!-- Keterangan SK -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Keterangan / No. SK
                            </label>
                            <input v-model="form.keterangan" type="text"
                                placeholder="cth: SK No. 001/2025 tentang penugasan rangkap jabatan"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 bg-white" />
                        </div>

                        <!-- Preview gaji jika jabatan dipilih -->
                        <div v-if="jabatanDipilih && jabatanDipilih.gaji_pokok > 0"
                            class="flex items-center gap-2 px-4 py-3 bg-emerald-50 border border-emerald-100 rounded-xl">
                            <span class="text-emerald-500">💰</span>
                            <p class="text-xs text-emerald-700">
                                Gaji pokok jabatan ini: <strong>{{ formatRp(jabatanDipilih.gaji_pokok) }}</strong>/bulan
                                <span v-if="jabatanAktif.length > 0"> → Total baru: <strong>{{ formatRp(totalGajiPokok +
                                        jabatanDipilih.gaji_pokok) }}</strong></span>
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-3 px-6 pb-5 pt-1 border-t border-gray-50">
                        <button @click="closeModal"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">
                            Batal
                        </button>
                        <button @click="submitTambah" :disabled="!form.jabatan_id || saving"
                            class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60 transition-colors">
                            {{ saving ? 'Menyimpan...' : 'Tambah Jabatan' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- ══ MODAL EDIT JABATAN ══════════════════════════════════════════ -->
        <Transition name="modal">
            <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showEditModal = false" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">

                    <h3 class="text-base font-semibold text-gray-900 mb-1">Edit Jabatan</h3>
                    <p class="text-xs text-gray-500 mb-5">{{ editTarget?.nama_jabatan }}</p>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Berlaku Mulai</label>
                            <input v-model="editForm.berlaku_mulai" type="date"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Berlaku Selesai</label>
                            <input v-model="editForm.berlaku_selesai" type="date" :min="editForm.berlaku_mulai"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white" />
                            <p class="text-xs text-gray-400 mt-1">Kosongkan jika masih berlaku</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Keterangan / No. SK</label>
                            <input v-model="editForm.keterangan" type="text" placeholder="Keterangan atau nomor SK..."
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white" />
                        </div>
                    </div>

                    <div class="flex gap-3 mt-6">
                        <button @click="showEditModal = false"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">
                            Batal
                        </button>
                        <button @click="submitEdit" :disabled="savingEdit"
                            class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60 transition-colors">
                            {{ savingEdit ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

const props = defineProps({
    guru: { type: Object, required: true },
    jabatanAktif: { type: Array, default: () => [] },
    riwayatJabatan: { type: Array, default: () => [] },
    jabatanList: { type: Array, default: () => [] },
    detailGajiPokok: { type: Array, default: () => [] },
    totalGajiPokok: { type: Number, default: 0 },
})

const showRiwayat = ref(false)
const showModal = ref(false)
const saving = ref(false)
const formErrors = ref({})

const form = reactive({
    jabatan_id: null,
    berlaku_mulai: new Date().toISOString().slice(0, 10),
    adalah_utama: false,
    keterangan: '',
})

// Jabatan yang belum aktif untuk guru ini
const jabatanTersedia = computed(() =>
    props.jabatanList.filter(j => !j.sudah_aktif)
)

const jabatanDipilih = computed(() =>
    props.jabatanList.find(j => j.id === form.jabatan_id) ?? null
)

function closeModal() {
    showModal.value = false
    formErrors.value = {}
    Object.assign(form, { jabatan_id: null, berlaku_mulai: new Date().toISOString().slice(0, 10), adalah_utama: false, keterangan: '' })
}

function submitTambah() {
    saving.value = true
    router.post(route('admin.master.jabatan-guru.store', props.guru.id), { ...form }, {
        onSuccess: () => closeModal(),
        onError: (e) => { formErrors.value = e },
        onFinish: () => saving.value = false,
    })
}

// ── Edit Modal ────────────────────────────────────────────────────────────────
const showEditModal = ref(false)
const savingEdit = ref(false)
const editTarget = ref(null)
const editForm = reactive({ berlaku_mulai: '', berlaku_selesai: '', keterangan: '' })

function editJabatan(j) {
    editTarget.value = j
    Object.assign(editForm, {
        berlaku_mulai: j.berlaku_mulai_raw ?? '',
        berlaku_selesai: j.berlaku_selesai_raw ?? '',
        keterangan: j.keterangan ?? '',
    })
    showEditModal.value = true
}

function submitEdit() {
    savingEdit.value = true
    router.patch(
        route('admin.master.jabatan-guru.update', [props.guru.id, editTarget.value.pivot_id]),
        { ...editForm },
        {
            onSuccess: () => { showEditModal.value = false },
            onFinish: () => savingEdit.value = false,
            preserveScroll: true,
        }
    )
}

// ── Aksi ─────────────────────────────────────────────────────────────────────
function setUtama(j) {
    router.patch(route('admin.master.jabatan-guru.set-utama', [props.guru.id, j.pivot_id]), {}, {
        preserveScroll: true,
    })
}

async function lepas(j) {
    if (props.jabatanAktif.length <= 1) return
    if (!(await confirm({ title: `Lepas jabatan ${j.nama_jabatan}?`, message: 'Jabatan akan dicatat sebagai berakhir hari ini.', variant: 'danger', confirmLabel: 'Ya, Lepas' }))) return
    router.delete(route('admin.master.jabatan-guru.destroy', [props.guru.id, j.pivot_id]), {
        preserveScroll: true,
    })
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function gajiJabatan(jabatanId) {
    return props.detailGajiPokok.find(d => d.jabatan_id === jabatanId)?.nominal ?? 0
}

function bgTipe(t) {
    return {
        struktural: 'bg-indigo-100 text-indigo-700',
        fungsional: 'bg-violet-100 text-violet-700',
        mengajar: 'bg-teal-100 text-teal-700',
    }[t] ?? 'bg-gray-100 text-gray-700'
}

function tipeClass(t) {
    return {
        struktural: 'bg-indigo-50 text-indigo-600',
        fungsional: 'bg-violet-50 text-violet-600',
        mengajar: 'bg-teal-50 text-teal-600',
    }[t] ?? 'bg-gray-50 text-gray-600'
}

function badgeSumber(s) {
    return {
        override_individu: 'bg-amber-50 text-amber-700',
        setting_jabatan: 'bg-emerald-50 text-emerald-700',
        tidak_ada: 'bg-gray-100 text-gray-500',
    }[s] ?? 'bg-gray-100 text-gray-500'
}

const labelSumber = {
    override_individu: 'Override individu',
    setting_jabatan: 'Dari setting jabatan',
    tidak_ada: 'Belum diset',
}

function formatRp(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }
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
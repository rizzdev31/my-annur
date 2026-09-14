<template>
    <AdminLayout title="Detail Pengajuan" subtitle="Pengajuan Izin">

        <Head :title="`Izin ${pengajuan.nama_guru}`" />

        <!-- Header -->
        <div class="flex items-center gap-4 mb-6">
            <Link :href="route('admin.smart-payroll.pengajuan-izin.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xl font-semibold text-gray-900">{{ pengajuan.jenis }}</h2>
                    <span :class="['px-2.5 py-1 rounded-full text-xs font-semibold', badgeStatus]">
                        {{ labelStatus }}
                    </span>
                    <span v-if="sifat" :class="['px-2.5 py-1 rounded-full text-xs font-semibold', sifat.kelas]">
                        {{ sifat.teks }}
                    </span>
                </div>
                <p class="text-sm text-gray-400 mt-0.5">Diajukan {{ pengajuan.created_at }}</p>
            </div>

            <!-- Keputusan hanya untuk yang masih menunggu -->
            <div v-if="pengajuan.is_pending" class="flex gap-2 shrink-0">
                <button @click="bukaSetujui"
                    class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold">
                    ✓ Setujui
                </button>
                <button @click="bukaTolak"
                    class="px-4 py-2 rounded-xl bg-red-50 text-red-700 hover:bg-red-100 text-sm font-semibold">
                    ✕ Tolak
                </button>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">

            <!-- ── Kolom kiri: isi pengajuan ───────────────────────────── -->
            <div class="lg:col-span-2 space-y-6">

                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">Rincian Izin</h3>
                    <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-4">
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">Jenis</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ pengajuan.jenis }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">Lama Izin</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ pengajuan.jumlah_hari }} hari kerja</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">Tanggal Mulai</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ pengajuan.tanggal_mulai }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">Tanggal Selesai</dt>
                            <dd class="text-sm font-medium text-gray-800">{{ pengajuan.tanggal_selesai }}</dd>
                        </div>
                        <div v-if="pengajuan.jam_mulai">
                            <dt class="text-xs text-gray-400 mb-0.5">
                                {{ pengajuan.is_datang_terlambat ? 'Boleh Datang Sampai' : 'Jam Izin' }}
                            </dt>
                            <dd class="text-sm font-medium text-gray-800">
                                {{ pengajuan.jam_mulai }}<span v-if="pengajuan.jam_selesai"> – {{ pengajuan.jam_selesai }}</span>
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-5 pt-5 border-t border-gray-100">
                        <dt class="text-xs text-gray-400 mb-1.5">Alasan</dt>
                        <dd class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">
                            {{ pengajuan.alasan || '—' }}
                        </dd>
                    </div>

                    <!-- Sifat izin: menjelaskan dampaknya ke absensi & kegiatan -->
                    <div v-if="sifat" :class="['mt-5 rounded-xl px-4 py-3 text-xs leading-relaxed', sifat.kotak]">
                        {{ sifat.penjelasan }}
                    </div>
                </div>

                <!-- Dokumen pendukung -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Dokumen Pendukung</h3>
                    <a v-if="pengajuan.file_dokumen" :href="pengajuan.file_dokumen" target="_blank"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-indigo-200 bg-indigo-50 text-indigo-700 text-sm font-medium hover:bg-indigo-100">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-width="2"
                                d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                        </svg>
                        {{ pengajuan.nama_dokumen || 'Buka dokumen' }}
                    </a>
                    <p v-else class="text-sm text-gray-400">Tidak ada dokumen yang dilampirkan.</p>

                    <!-- Pratinjau langsung bila berupa gambar, agar admin tak perlu membuka tab baru -->
                    <img v-if="dokumenGambar" :src="pengajuan.file_dokumen" alt="Dokumen"
                        class="mt-4 w-full max-w-md rounded-xl border border-gray-200" />
                </div>
            </div>

            <!-- ── Kolom kanan: pemohon & keputusan ────────────────────── -->
            <div class="space-y-6">

                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">Pemohon</h3>
                    <div class="flex items-center gap-3">
                        <img v-if="pengajuan.foto_guru" :src="pengajuan.foto_guru" alt=""
                            class="w-12 h-12 rounded-xl object-cover shrink-0" />
                        <div v-else
                            class="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-700 grid place-items-center font-bold shrink-0">
                            {{ inisial }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ pengajuan.nama_guru }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ pengajuan.jabatan }}</p>
                        </div>
                    </div>
                    <dl class="mt-4 pt-4 border-t border-gray-100 space-y-2">
                        <div class="flex justify-between gap-2">
                            <dt class="text-xs text-gray-400">NIP</dt>
                            <dd class="text-xs font-medium text-gray-700">{{ pengajuan.nip || '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">Keputusan</h3>

                    <div v-if="pengajuan.is_pending" class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3">
                        <p class="text-xs text-amber-800">Masih menunggu keputusan admin.</p>
                    </div>

                    <dl v-else class="space-y-3">
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">Status</dt>
                            <dd><span :class="['px-2.5 py-1 rounded-full text-xs font-semibold', badgeStatus]">{{ labelStatus }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">Diproses Oleh</dt>
                            <dd class="text-sm text-gray-800">{{ pengajuan.diproses_oleh || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400 mb-0.5">Tanggal Keputusan</dt>
                            <dd class="text-sm text-gray-800">{{ pengajuan.tanggal_keputusan || '—' }}</dd>
                        </div>
                        <div v-if="pengajuan.catatan_admin">
                            <dt class="text-xs text-gray-400 mb-0.5">Catatan</dt>
                            <dd class="text-sm text-gray-700 whitespace-pre-line">{{ pengajuan.catatan_admin }}</dd>
                        </div>
                    </dl>

                    <p class="mt-4 pt-4 border-t border-gray-100 text-xs"
                        :class="pengajuan.absensi_diupdate ? 'text-emerald-600' : 'text-gray-400'">
                        {{ pengajuan.absensi_diupdate ? '✓ Absensi sudah diperbarui' : 'Absensi belum diperbarui' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- ── Modal setujui ──────────────────────────────────────────── -->
        <div v-if="modalSetujui" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,.45)">
            <div class="w-full max-w-md bg-white rounded-2xl p-5">
                <h3 class="text-base font-semibold text-gray-900">Setujui Pengajuan</h3>
                <p class="text-xs text-gray-400 mt-0.5 mb-4">{{ pengajuan.nama_guru }} · {{ pengajuan.jenis }}</p>

                <template v-if="pengajuan.is_datang_terlambat">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Batas jam datang</label>
                    <input v-model="jamSetujui" type="time" step="60" :class="fieldCls" class="mb-3" />
                </template>

                <label class="block text-xs font-medium text-gray-500 mb-1">Catatan (opsional)</label>
                <textarea v-model="catatanSetujui" rows="3" :class="fieldCls" placeholder="Catatan untuk guru…"></textarea>

                <div class="flex gap-2 mt-4">
                    <button @click="modalSetujui = false" class="flex-1 py-2.5 rounded-xl bg-gray-100 text-gray-600 text-sm font-semibold">Batal</button>
                    <button @click="kirimSetujui" :disabled="loading"
                        class="flex-1 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold disabled:opacity-50">
                        {{ loading ? 'Memproses…' : 'Ya, Setujui' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Modal tolak ────────────────────────────────────────────── -->
        <div v-if="modalTolak" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,.45)">
            <div class="w-full max-w-md bg-white rounded-2xl p-5">
                <h3 class="text-base font-semibold text-gray-900">Tolak Pengajuan</h3>
                <p class="text-xs text-gray-400 mt-0.5 mb-4">{{ pengajuan.nama_guru }} · {{ pengajuan.jenis }}</p>

                <label class="block text-xs font-medium text-gray-500 mb-1">
                    Alasan penolakan <span class="text-red-500">*</span>
                </label>
                <textarea v-model="catatanTolak" rows="3" :class="fieldCls" placeholder="Wajib diisi…"></textarea>
                <p v-if="errorTolak" class="text-xs text-red-500 mt-1">Alasan penolakan wajib diisi.</p>

                <div class="flex gap-2 mt-4">
                    <button @click="modalTolak = false" class="flex-1 py-2.5 rounded-xl bg-gray-100 text-gray-600 text-sm font-semibold">Batal</button>
                    <button @click="kirimTolak" :disabled="loading"
                        class="flex-1 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-semibold disabled:opacity-50">
                        {{ loading ? 'Memproses…' : 'Ya, Tolak' }}
                    </button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ pengajuan: { type: Object, required: true } })

const inisial = computed(() =>
    (props.pengajuan.nama_guru || '?').split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase())

const labelStatus = computed(() => ({
    pending: 'Menunggu', disetujui: 'Disetujui', ditolak: 'Ditolak', dibatalkan: 'Dibatalkan',
}[props.pengajuan.status] ?? props.pengajuan.status))

const badgeStatus = computed(() => ({
    pending: 'bg-amber-100 text-amber-700',
    disetujui: 'bg-emerald-100 text-emerald-700',
    ditolak: 'bg-red-100 text-red-700',
    dibatalkan: 'bg-gray-100 text-gray-500',
}[props.pengajuan.status] ?? 'bg-gray-100 text-gray-500'))

// Sifat izin menentukan apakah guru tetap dihitung masuk kerja — penting
// karena hanya izin sehari penuh yang membebaskannya dari absensi kegiatan.
const sifat = computed(() => {
    if (props.pengajuan.is_datang_terlambat) return {
        teks: 'Datang Terlambat', kelas: 'bg-sky-100 text-sky-700', kotak: 'bg-sky-50 text-sky-800',
        penjelasan: 'Guru tetap masuk kerja, hanya datang lebih lambat. Ia tetap terdaftar pada absensi kegiatan hari itu.',
    }
    if (props.pengajuan.is_sementara) return {
        teks: 'Izin Sementara', kelas: 'bg-violet-100 text-violet-700', kotak: 'bg-violet-50 text-violet-800',
        penjelasan: 'Izin berbasis jam — guru tetap masuk kerja. Ia hanya dibebaskan dari kegiatan yang jamnya berada di dalam rentang izin ini.',
    }
    return null
})

const dokumenGambar = computed(() =>
    /\.(jpe?g|png|webp)$/i.test(props.pengajuan.file_dokumen || ''))

// ── Keputusan ────────────────────────────────────────────────────────────
const loading = ref(false)
const modalSetujui = ref(false)
const modalTolak = ref(false)
const catatanSetujui = ref('')
const catatanTolak = ref('')
const errorTolak = ref(false)
const jamSetujui = ref('')

function bukaSetujui() {
    catatanSetujui.value = ''
    jamSetujui.value = props.pengajuan.jam_mulai || ''
    modalSetujui.value = true
}
function bukaTolak() {
    catatanTolak.value = ''
    errorTolak.value = false
    modalTolak.value = true
}

function kirimSetujui() {
    loading.value = true
    const payload = { catatan: catatanSetujui.value }
    if (props.pengajuan.is_datang_terlambat && jamSetujui.value) payload.jam_mulai = jamSetujui.value

    router.post(route('admin.smart-payroll.pengajuan-izin.setujui', props.pengajuan.id), payload, {
        onSuccess: () => { modalSetujui.value = false },
        onFinish: () => { loading.value = false },
    })
}

function kirimTolak() {
    if (!catatanTolak.value.trim()) { errorTolak.value = true; return }
    loading.value = true
    router.post(route('admin.smart-payroll.pengajuan-izin.tolak', props.pengajuan.id),
        { catatan: catatanTolak.value }, {
            onSuccess: () => { modalTolak.value = false },
            onFinish: () => { loading.value = false },
        })
}

const fieldCls = 'w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all bg-white'
</script>

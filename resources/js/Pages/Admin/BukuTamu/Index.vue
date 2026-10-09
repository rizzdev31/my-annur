<template>
    <AdminLayout title="Buku Tamu" subtitle="Administrasi">

        <Head title="Buku Tamu" />

        <div class="flex items-start justify-between gap-3 mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Buku Tamu</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    Satu tautan per kegiatan — tamu mengisi sendiri dari ponselnya, nomor urut diberikan sistem.
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <input v-model.number="filterTahun" @change="gantiTahun" type="number"
                    class="w-24 px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-indigo-500" />
                <button @click="bukaForm()"
                    class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl">
                    + Kegiatan
                </button>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-3 mb-6">
            <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3">
                <p class="text-xs text-gray-400">Kegiatan</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">{{ summary.kegiatan ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3">
                <p class="text-xs text-gray-400">Masih Terbuka</p>
                <p class="text-2xl font-bold text-emerald-700 mt-0.5">{{ summary.terbuka ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-indigo-100 bg-indigo-50 px-4 py-3">
                <p class="text-xs text-gray-400">Total Tamu</p>
                <p class="text-2xl font-bold text-indigo-700 mt-0.5">{{ summary.tamu ?? 0 }}</p>
            </div>
        </div>

        <div v-if="!kegiatan.length" class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
            <p class="text-sm text-gray-400">Belum ada kegiatan pada tahun {{ tahun }}.</p>
            <p class="text-xs text-gray-300 mt-1">Buat kegiatan, lalu bagikan tautannya ke tamu.</p>
        </div>

        <div v-for="k in kegiatan" :key="k.id"
            class="bg-white rounded-2xl border border-gray-200 p-5 mb-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-base font-semibold text-gray-900">{{ k.nama }}</h3>
                        <span v-if="!k.is_dibuka" class="px-2 py-0.5 rounded-lg bg-gray-100 text-gray-500 text-[11px] font-semibold">Ditutup</span>
                        <span v-else-if="k.kedaluwarsa" class="px-2 py-0.5 rounded-lg bg-amber-50 text-amber-700 text-[11px] font-semibold">Kedaluwarsa</span>
                        <span v-else class="px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-700 text-[11px] font-semibold">Terbuka</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ k.rentang }}<span v-if="k.lokasi"> · {{ k.lokasi }}</span>
                        · <b class="text-gray-600">{{ k.jumlah_tamu }} tamu</b>
                    </p>
                    <p v-if="k.dibuka_sampai" class="text-[11px] text-gray-400 mt-0.5">
                        Pengisian ditutup otomatis: {{ k.dibuka_sampai }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2 shrink-0">
                    <Link :href="route('admin.smart-payroll.buku-tamu.show', k.id)"
                        class="px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-semibold">Detail &amp; Tamu</Link>
                    <button @click="salin(k)" class="px-3 py-1.5 rounded-lg bg-sky-50 text-sky-700 text-xs font-semibold">
                        {{ tersalin === k.id ? 'Tersalin ✓' : 'Salin Tautan' }}
                    </button>
                    <button @click="toggle(k)" class="px-3 py-1.5 rounded-lg text-xs font-semibold"
                        :class="k.is_dibuka ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'">
                        {{ k.is_dibuka ? 'Tutup' : 'Buka' }}
                    </button>
                    <button @click="bukaForm(k)" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 text-xs font-semibold">Edit</button>
                    <a :href="route('admin.smart-payroll.buku-tamu.cetak', k.id)"
                        class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 text-xs font-semibold">Daftar Hadir PDF</a>
                </div>
            </div>

            <div class="mt-3 flex items-center gap-2 rounded-xl bg-gray-50 px-3 py-2">
                <span class="text-[11px] font-semibold text-gray-400 shrink-0">TAUTAN TAMU</span>
                <code class="text-[11px] text-gray-600 truncate">{{ k.tautan }}</code>
                <a :href="k.tautan" target="_blank" rel="noopener"
                    class="ml-auto shrink-0 text-[11px] font-bold text-sky-600">Buka</a>
            </div>
        </div>

        <!-- Modal kegiatan -->
        <div v-if="showForm" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl w-full max-w-md p-6 max-h-[92vh] overflow-y-auto">
                <h3 class="text-base font-semibold text-gray-900 mb-4">
                    {{ editTarget ? 'Edit Kegiatan' : 'Kegiatan Baru' }}
                </h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Nama Kegiatan <span class="text-red-500">*</span></label>
                        <input v-model="form.nama" type="text" placeholder="cth: Kunjungan Studi Banding" :class="inp" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal <span class="text-red-500">*</span></label>
                            <input v-model="form.tanggal" type="date" :class="inp" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">s/d <span class="text-gray-400">(opsional)</span></label>
                            <input v-model="form.tanggal_selesai" type="date" :min="form.tanggal" :class="inp" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Lokasi</label>
                        <input v-model="form.lokasi" type="text" placeholder="cth: Aula Utama" :class="inp" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Penyelenggara</label>
                        <input v-model="form.penyelenggara" type="text" placeholder="Pondok Pesantren An-Nur" :class="inp" />
                        <p class="text-[11px] text-gray-400 mt-1">Tampil sebagai judul di halaman tamu.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">
                            Tutup pengisian otomatis <span class="text-gray-400">(opsional)</span>
                        </label>
                        <input v-model="form.dibuka_sampai" type="datetime-local" :class="inp" />
                        <p class="text-[11px] text-gray-400 mt-1">
                            Dikosongkan = sehari setelah kegiatan. Penting agar tautan yang beredar
                            tidak bisa diisi berbulan-bulan kemudian.
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Deskripsi</label>
                        <textarea v-model="form.deskripsi" rows="2" :class="inp"></textarea>
                    </div>
                </div>
                <div class="flex gap-2 mt-5">
                    <button @click="showForm = false" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold">Batal</button>
                    <button @click="simpan" :disabled="!form.nama || !form.tanggal || busy"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-semibold disabled:opacity-50">
                        Simpan
                    </button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    kegiatan: { type: Array, default: () => [] },
    tahun: { type: Number, default: new Date().getFullYear() },
    summary: { type: Object, default: () => ({}) },
})

const inp = 'w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white'
const busy = ref(false)
const filterTahun = ref(props.tahun)
const tersalin = ref(null)

const gantiTahun = () => router.get(route('admin.smart-payroll.buku-tamu.index'),
    { tahun: filterTahun.value }, { preserveState: true, preserveScroll: true })

const showForm = ref(false)
const editTarget = ref(null)
const kosong = () => ({
    nama: '', deskripsi: '', tanggal: new Date().toISOString().slice(0, 10),
    tanggal_selesai: '', lokasi: '', penyelenggara: '', dibuka_sampai: '',
})
const form = reactive(kosong())

function bukaForm(k = null) {
    editTarget.value = k
    Object.assign(form, k ? {
        nama: k.nama, deskripsi: k.deskripsi ?? '', tanggal: k.tanggal,
        tanggal_selesai: k.tanggal_selesai ?? '', lokasi: k.lokasi ?? '',
        penyelenggara: k.penyelenggara ?? '',
        dibuka_sampai: k.dibuka_sampai ? k.dibuka_sampai.replace(' ', 'T') : '',
    } : kosong())
    showForm.value = true
}

function simpan() {
    busy.value = true
    const muatan = {
        ...form,
        tanggal_selesai: form.tanggal_selesai || null,
        dibuka_sampai: form.dibuka_sampai || null,
        lokasi: form.lokasi || null,
        penyelenggara: form.penyelenggara || null,
        deskripsi: form.deskripsi || null,
    }
    const opsi = {
        preserveScroll: true,
        onSuccess: () => { showForm.value = false },
        onFinish: () => busy.value = false,
    }
    editTarget.value
        ? router.put(route('admin.smart-payroll.buku-tamu.update', editTarget.value.id), muatan, opsi)
        : router.post(route('admin.smart-payroll.buku-tamu.store'), muatan, opsi)
}

const toggle = (k) => router.patch(route('admin.smart-payroll.buku-tamu.toggle', k.id), {}, { preserveScroll: true })

async function salin(k) {
    try {
        await navigator.clipboard.writeText(k.tautan)
    } catch (_) {
        // Peramban lama / konteks tanpa izin salin: biarkan admin menyalin manual.
        window.prompt('Salin tautan ini:', k.tautan)
        return
    }
    tersalin.value = k.id
    setTimeout(() => { if (tersalin.value === k.id) tersalin.value = null }, 2000)
}
</script>

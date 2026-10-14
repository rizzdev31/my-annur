<template>
    <AdminLayout title="Hasil Ujian Qur'an" subtitle="Smart Education">
        <Head title="Hasil Tasmi' & Tasnif" />

        <div class="mb-5">
            <h2 class="text-xl font-semibold text-gray-900">Hasil Tasmi' &amp; Tasnif</h2>
            <p class="text-sm text-gray-400 mt-0.5">
                Seluruh hasil ujian Qur'an dalam satu daftar — beri masukan, batalkan yang keliru,
                cetak sertifikat 2 halaman.
            </p>
        </div>

        <!-- Ringkasan -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
            <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3">
                <p class="text-xs text-gray-400">Hasil</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">{{ summary.total }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3">
                <p class="text-xs text-gray-400">Lulus</p>
                <p class="text-2xl font-bold text-emerald-700 mt-0.5">{{ summary.lulus }}</p>
            </div>
            <div class="rounded-2xl border border-rose-100 bg-rose-50 px-4 py-3">
                <p class="text-xs text-gray-400">Belum Lulus</p>
                <p class="text-2xl font-bold text-rose-700 mt-0.5">{{ summary.belum_lulus }}</p>
            </div>
            <div class="rounded-2xl border border-amber-100 bg-amber-50 px-4 py-3">
                <p class="text-xs text-gray-400">Menunggu Dinilai</p>
                <p class="text-2xl font-bold text-amber-700 mt-0.5">{{ summary.menunggu }}</p>
            </div>
            <!-- Inilah yang paling dicari: hasil yang belum ditanggapi pimpinan -->
            <div class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3">
                <p class="text-xs text-gray-400">Belum Diberi Masukan</p>
                <p class="text-2xl font-bold text-sky-700 mt-0.5">{{ summary.tanpa_masukan }}</p>
            </div>
        </div>

        <!-- Filter -->
        <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-5 flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Jenis</label>
                <select v-model="f.tipe" @change="muat" class="px-3 py-2 rounded-xl border border-gray-200 text-sm">
                    <option value="semua">Semua</option>
                    <option value="tasmi">Tasmi' (Tahfidz)</option>
                    <option value="tasnif">Tasnif (Tahsin)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select v-model="f.status" @change="muat" class="px-3 py-2 rounded-xl border border-gray-200 text-sm">
                    <option value="selesai">Sudah dinilai</option>
                    <option value="ditugaskan">Menunggu dinilai</option>
                    <option value="dibatalkan">Dibatalkan</option>
                    <option value="semua">Semua</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Hasil</label>
                <select v-model="f.hasil" @change="muat" class="px-3 py-2 rounded-xl border border-gray-200 text-sm">
                    <option value="semua">Semua</option>
                    <option value="lulus">Lulus</option>
                    <option value="belum">Belum lulus</option>
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-500 mb-1">Cari santri</label>
                <input v-model="f.cari" @keyup.enter="muat" type="text" placeholder="Nama atau NIS…"
                    class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm" />
            </div>
            <button @click="muat" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold">Terapkan</button>
        </div>

        <!-- Daftar -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Santri</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Ujian</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold text-gray-400 uppercase">Nilai</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">Penguji</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Masukan</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-gray-400 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="h in hasilUjian" :key="h.jenis + h.id" class="hover:bg-gray-50/40">
                        <td class="px-5 py-3.5">
                            <p class="text-sm font-medium text-gray-800">{{ h.santri }}</p>
                            <p class="text-[11px] text-gray-400 font-mono">{{ h.nip || '—' }}</p>
                        </td>
                        <td class="px-3 py-3.5">
                            <span :class="['px-2 py-0.5 rounded text-[10px] font-bold',
                                h.jenis === 'tasmi' ? 'bg-violet-50 text-violet-700' : 'bg-sky-50 text-sky-700']">
                                {{ h.jenis === 'tasmi' ? "TASMI'" : 'TASNIF' }}
                            </span>
                            <p class="text-sm text-gray-700 mt-0.5">{{ h.capaian }}</p>
                            <p class="text-[11px] text-gray-400">{{ h.tanggal }}</p>
                        </td>
                        <td class="px-3 py-3.5 text-center">
                            <template v-if="h.status === 'selesai'">
                                <p :class="['text-lg font-bold', h.lulus ? 'text-emerald-600' : 'text-rose-600']">
                                    {{ h.nilai }}
                                </p>
                                <p class="text-[10px] font-semibold" :class="h.lulus ? 'text-emerald-600' : 'text-rose-600'">
                                    {{ h.lulus ? 'LULUS' : 'BELUM' }}
                                </p>
                                <!-- Rata-rata bisa menutupi satu komponen yang jatuh -->
                                <p v-if="h.lulus && h.ada_rubrik_rendah" class="text-[10px] text-amber-600 mt-0.5">
                                    ada komponen &lt; {{ h.ambang }}
                                </p>
                            </template>
                            <span v-else :class="['px-2 py-0.5 rounded text-[10px] font-semibold', statusCls(h.status)]">
                                {{ h.status }}
                            </span>
                        </td>
                        <td class="px-3 py-3.5 hidden md:table-cell">
                            <p class="text-xs text-gray-600">{{ h.penguji }}</p>
                            <p class="text-[11px] text-gray-400">pengampu: {{ h.pengampu }}</p>
                        </td>
                        <td class="px-3 py-3.5">
                            <p v-if="h.masukan" class="text-xs text-gray-600 line-clamp-2">{{ h.masukan }}</p>
                            <span v-else-if="h.status === 'selesai'" class="text-[11px] text-sky-600 font-semibold">belum ada</span>
                            <span v-else class="text-[11px] text-gray-300">—</span>
                        </td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <button @click="buka(h)" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-indigo-600 hover:bg-indigo-50">
                                Rincian
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!hasilUjian.length">
                        <td colspan="6" class="py-14 text-center text-sm text-gray-400">
                            Tidak ada hasil ujian pada filter ini.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Rincian + masukan -->
        <div v-if="aktif" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl w-full max-w-lg p-6 max-h-[92vh] overflow-y-auto">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">{{ aktif.santri }}</h3>
                        <p class="text-xs text-gray-400">
                            {{ aktif.jenis === 'tasmi' ? "Tasmi'" : 'Tasnif' }} · {{ aktif.capaian }} · {{ aktif.tanggal }}
                        </p>
                    </div>
                    <span v-if="aktif.status === 'selesai'"
                        :class="['px-2.5 py-1 rounded-lg text-xs font-bold',
                            aktif.lulus ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700']">
                        {{ aktif.nilai }} · {{ aktif.lulus ? 'LULUS' : 'BELUM LULUS' }}
                    </span>
                </div>

                <!-- Rubrik: dasar penilaian penguji -->
                <div v-if="aktif.rubrik_ada" class="mt-4 rounded-xl border border-gray-200 overflow-hidden">
                    <div v-for="r in aktif.rubrik" :key="r.label"
                        class="flex items-center justify-between px-3.5 py-2 border-b border-gray-50 last:border-0">
                        <span class="text-xs text-gray-600">{{ r.label }}</span>
                        <span :class="['text-sm font-bold',
                            r.nilai !== null && r.nilai < aktif.ambang ? 'text-amber-600' : 'text-gray-800']">
                            {{ r.nilai ?? '—' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between px-3.5 py-2 bg-gray-50">
                        <span class="text-xs font-semibold text-gray-700">Nilai akhir (ambang {{ aktif.ambang }})</span>
                        <span class="text-base font-bold text-gray-900">{{ aktif.nilai }}</span>
                    </div>
                </div>
                <p v-else-if="aktif.status === 'selesai'" class="mt-4 text-xs text-gray-400">
                    Rincian rubrik tidak tersedia (dinilai sebelum rubrik dipakai).
                </p>

                <div v-if="aktif.catatan" class="mt-3 rounded-xl bg-gray-50 px-3.5 py-2.5">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Catatan Penguji</p>
                    <p class="text-xs text-gray-700 mt-0.5 italic">{{ aktif.catatan }}</p>
                </div>

                <div v-if="aktif.alasan_batal" class="mt-3 rounded-xl bg-rose-50 border border-rose-200 px-3.5 py-2.5">
                    <p class="text-[11px] font-semibold text-rose-700 uppercase tracking-wide">Dibatalkan</p>
                    <p class="text-xs text-rose-700 mt-0.5">{{ aktif.alasan_batal }}
                        <span v-if="aktif.dibatalkan_oleh" class="text-rose-400">({{ aktif.dibatalkan_oleh }})</span>
                    </p>
                </div>

                <!-- Masukan pimpinan -->
                <div v-if="aktif.status === 'selesai'" class="mt-4 pt-4 border-t border-gray-100">
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                        Masukan Pimpinan
                        <span class="font-normal text-gray-400">— tampil di halaman nilai sertifikat</span>
                    </label>
                    <textarea v-model="fMasukan" rows="3" placeholder="cth: makhorijul huruf perlu dibimbing lagi pada huruf tebal."
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm"></textarea>
                    <p v-if="aktif.masukan_oleh" class="text-[11px] text-gray-400 mt-1">
                        Terakhir oleh {{ aktif.masukan_oleh }} · {{ aktif.masukan_pada }}
                    </p>
                </div>

                <p v-if="galat" class="mt-3 rounded-xl bg-red-50 border border-red-200 px-3.5 py-2 text-xs text-red-700">{{ galat }}</p>

                <div class="flex flex-wrap gap-2 mt-5">
                    <button @click="aktif = null" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold">Tutup</button>
                    <a v-if="aktif.boleh_sertifikat"
                        :href="route('admin.smart-education.hasil-ujian-quran.sertifikat', [aktif.jenis, aktif.id])"
                        class="px-4 py-2.5 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold">Sertifikat</a>
                    <button v-if="aktif.status !== 'dibatalkan'" @click="mintaBatal"
                        class="px-4 py-2.5 rounded-xl bg-rose-50 text-rose-700 text-sm font-semibold">Batalkan</button>
                    <button v-if="aktif.status === 'selesai'" @click="simpanMasukan" :disabled="sibuk"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-semibold disabled:opacity-50">
                        {{ sibuk ? 'Menyimpan…' : 'Simpan Masukan' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Konfirmasi batal -->
        <div v-if="batal" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl w-full max-w-sm p-6">
                <h3 class="text-base font-semibold text-gray-900">Batalkan hasil ini?</h3>
                <p class="text-xs text-gray-500 mt-1 mb-3">
                    {{ batal.santri }} · {{ batal.capaian }}. Tugas tambahan pengujinya ikut dibatalkan sehingga
                    <strong>vakasinya tidak terbayar</strong>.
                    <template v-if="batal.jenis === 'tasmi' && batal.status === 'selesai'">
                        Status juz dikembalikan ke "selesai" agar tasmi' bisa diulang.
                    </template>
                    <template v-else-if="batal.jenis === 'tasnif' && batal.status === 'selesai'">
                        Level santri TIDAK diturunkan otomatis.
                    </template>
                </p>
                <input v-model="alasanBatal" type="text" placeholder="Alasan pembatalan (wajib)"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm mb-2" />
                <p v-if="galat" class="mb-2 rounded-xl bg-red-50 border border-red-200 px-3.5 py-2 text-xs text-red-700">{{ galat }}</p>
                <div class="flex gap-2 mt-2">
                    <button @click="batal = null" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold">Tidak</button>
                    <button @click="kirimBatal" :disabled="sibuk"
                        class="flex-1 py-2.5 rounded-xl bg-rose-600 text-white text-sm font-semibold disabled:opacity-50">
                        {{ sibuk ? 'Membatalkan…' : 'Ya, Batalkan' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    hasilUjian: { type: Array, default: () => [] },
    filter: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
})

const f = reactive({
    tipe: props.filter.tipe ?? 'semua',
    status: props.filter.status ?? 'selesai',
    hasil: props.filter.hasil ?? 'semua',
    cari: props.filter.cari ?? '',
})

const aktif = ref(null)
const batal = ref(null)
const fMasukan = ref('')
const alasanBatal = ref('')
const sibuk = ref(false)
const galat = ref(null)

function statusCls(s) {
    return {
        ditugaskan: 'bg-amber-50 text-amber-700',
        selesai: 'bg-emerald-50 text-emerald-700',
        dibatalkan: 'bg-gray-100 text-gray-500',
    }[s] ?? 'bg-gray-100 text-gray-600'
}

function muat() {
    router.get(route('admin.smart-education.hasil-ujian-quran.index'), { ...f },
        { preserveState: true, preserveScroll: true, replace: true })
}

function buka(h) {
    aktif.value = h
    fMasukan.value = h.masukan ?? ''
    galat.value = null
}

function mintaBatal() {
    batal.value = aktif.value
    alasanBatal.value = ''
    galat.value = null
}

function kirim(url, data, onOk) {
    sibuk.value = true
    galat.value = null
    router.post(url, data, {
        preserveScroll: true,
        onSuccess: onOk,
        onError: (e) => { galat.value = Object.values(e).join(' · ') },
        onFinish: () => sibuk.value = false,
    })
}

function simpanMasukan() {
    kirim(route('admin.smart-education.hasil-ujian-quran.masukan', [aktif.value.jenis, aktif.value.id]),
        { masukan: fMasukan.value || null }, () => { aktif.value = null })
}

function kirimBatal() {
    if (!alasanBatal.value.trim()) {
        galat.value = 'Alasan pembatalan wajib diisi.'
        return
    }
    kirim(route('admin.smart-education.hasil-ujian-quran.batal', [batal.value.jenis, batal.value.id]),
        { alasan: alasanBatal.value }, () => { batal.value = null; aktif.value = null })
}
</script>

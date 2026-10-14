<template>
    <AdminLayout title="Sertifikat" subtitle="Smart Education">
        <Head :title="`Sertifikat ${jenis === 'tasmi' ? 'Tasmi' : 'Tasnif'} — ${sertifikat.santri.nama}`" />

        <div class="no-print mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <Link :href="route('admin.smart-education.hasil-ujian-quran.index')"
                    class="text-xs font-semibold text-gray-400 hover:text-gray-600">← Hasil Ujian Qur'an</Link>
                <h2 class="text-xl font-semibold text-gray-900 mt-1">{{ sertifikat.santri.nama }}</h2>
                <p class="text-sm text-gray-400">
                    {{ jenis === 'tasmi' ? `Juz ${sertifikat.juz}` : sertifikat.level_label }} ·
                    nilai {{ sertifikat.nilai }} · {{ sertifikat.predikat }}
                </p>
            </div>
            <button @click="cetak" class="px-4 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-semibold">
                Cetak / Simpan PDF
            </button>
        </div>

        <p class="no-print mb-4 text-xs text-gray-400">
            Dua lembar: <strong>halaman depan</strong> memuat nama &amp; pernyataan lulus,
            <strong>halaman nilai</strong> memuat rincian rubrik, catatan penguji, dan masukan pimpinan.
            Keduanya ikut tercetak.
        </p>

        <SertifikatQuran :data="sertifikat" :jenis="jenis" />
    </AdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import SertifikatQuran from '@/Shared/SertifikatQuran.vue'

defineProps({
    sertifikat: { type: Object, required: true },
    jenis: { type: String, default: 'tasmi' },
})

function cetak() { window.print() }
</script>

<style scoped>
@media print {
    .no-print { display: none !important; }
}
</style>

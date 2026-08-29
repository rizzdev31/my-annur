<template>
    <AdminLayout title="Smart Tahsin" subtitle="Smart Education">

        <Head title="Smart Tahsin" />

        <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-900">Smart Tahsin</h2>
            <p class="text-sm text-gray-400 mt-0.5">Pengaturan tahsin — generate jadwal (jam mengikuti pola Tahfidz), materi & penilaian per level.</p>
        </div>

        <!-- Statistik -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <div class="bg-white rounded-2xl border border-gray-200 p-4"><p class="text-xs text-gray-400">Mapel Tahsin</p><p class="text-2xl font-bold text-violet-600 mt-1">{{ stat.mapel_tahsin }}</p></div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4"><p class="text-xs text-gray-400">Kelas Tahsin</p><p class="text-2xl font-bold text-indigo-600 mt-1">{{ stat.kelas_tahsin }}</p></div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4"><p class="text-xs text-gray-400">Santri Tahsin</p><p class="text-2xl font-bold text-gray-900 mt-1">{{ stat.santri }}</p></div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4"><p class="text-xs text-gray-400">Jadwal Tahsin</p><p class="text-2xl font-bold text-sky-600 mt-1">{{ stat.jadwal_tahsin }}</p></div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <!-- Generator jadwal -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="text-base font-semibold text-gray-900 mb-1">Generator Jadwal Tahsin</h3>
                <p class="text-xs text-gray-400 mb-4">Buat semua slot mingguan (Sen–Jum) sekaligus. Jam mengikuti pola Tahfidz. Tahun ajaran aktif: <b>{{ tahunAjaranAktif || '—' }}</b></p>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Guru Pengampu</label>
                        <select v-model="gen.tenaga_pendidik_id" :class="fieldCls">
                            <option :value="null">Pilih guru...</option>
                            <option v-for="g in guruOpsi" :key="g.id" :value="g.id">{{ g.nama }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Kelas Tahsin</label>
                            <select v-model="gen.kelas_id" :class="fieldCls">
                                <option :value="null">Pilih kelas...</option>
                                <option v-for="k in kelasTahsinOpsi" :key="k.id" :value="k.id">{{ k.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Mapel Tahsin</label>
                            <select v-model="gen.mata_pelajaran_id" :class="fieldCls">
                                <option :value="null">Pilih mapel...</option>
                                <option v-for="m in mapelTahsin" :key="m.id" :value="m.id">{{ m.nama }}</option>
                            </select>
                        </div>
                    </div>
                    <button @click="generate" :disabled="!genValid || generating"
                        class="w-full py-2.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold disabled:opacity-50 transition-colors">
                        {{ generating ? 'Membuat...' : 'Buat Jadwal Standar' }}
                    </button>
                    <p v-if="kelasTahsinOpsi.length === 0 || mapelTahsin.length === 0" class="text-xs text-amber-600">
                        Lengkapi dulu kelas tahsin (menu Kelas) & mapel tipe tahsin (menu Mata Pelajaran).
                    </p>
                </div>
            </div>

            <!-- Pola jam (read-only, mengikuti tahfidz) -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="text-base font-semibold text-gray-900 mb-1">Pola Jam (mengikuti Tahfidz)</h3>
                <p class="text-xs text-gray-400 mb-4">Jam tahsin = jam tahfidz. Atur di <b>Smart Tahfidz → Pola Sesi</b>. {{ jpPerSesi }} JP / sesi.</p>
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="rounded-xl bg-gray-50 border border-gray-200 p-3 text-center">
                        <p class="text-xs text-gray-400">Sesi Pagi</p>
                        <p class="text-sm font-bold text-gray-800 mt-1">{{ jamSesiInfo.pagi[0] }} – {{ jamSesiInfo.pagi[1] }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 border border-gray-200 p-3 text-center">
                        <p class="text-xs text-gray-400">Sesi Sore</p>
                        <p class="text-sm font-bold text-gray-800 mt-1">{{ jamSesiInfo.sore[0] }} – {{ jamSesiInfo.sore[1] }}</p>
                    </div>
                </div>
                <div class="space-y-1.5">
                    <div v-for="(sesi, hari) in polaJadwal" :key="hari" class="flex items-center justify-between text-sm">
                        <span class="capitalize text-gray-600">{{ hari }}</span>
                        <span class="text-gray-400 text-xs">{{ Array.isArray(sesi) ? sesi.join(', ') : sesi }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 rounded-2xl border border-violet-100 bg-violet-50/60 p-4 text-sm text-violet-800">
            <b>Catatan:</b> Mapel & jadwal Tahfidz/Tahsin <b>tidak</b> diatur lewat menu "Jadwal Mengajar" umum (itu khusus kelas sekolah).
            Gunakan generator ini agar kelas & mapel tahsin selalu cocok (tanpa duplikat / salah pasang).
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive, ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    guruOpsi: { type: Array, default: () => [] },
    kelasTahsinOpsi: { type: Array, default: () => [] },
    mapelTahsin: { type: Array, default: () => [] },
    tahunAjaranAktif: { type: String, default: null },
    polaJadwal: { type: Object, default: () => ({}) },
    jamSesiInfo: { type: Object, default: () => ({ pagi: ['—', '—'], sore: ['—', '—'] }) },
    jpPerSesi: { type: Number, default: 1 },
    stat: { type: Object, default: () => ({}) },
    setup: { type: Object, default: () => ({}) },
})

const gen = reactive({ tenaga_pendidik_id: null, kelas_id: null, mata_pelajaran_id: null })
const generating = ref(false)
const genValid = computed(() => gen.tenaga_pendidik_id && gen.kelas_id && gen.mata_pelajaran_id)

function generate() {
    if (!genValid.value) return
    generating.value = true
    router.post(route('admin.smart-education.tahsin.generate-jadwal'), { ...gen }, {
        preserveScroll: true,
        onFinish: () => { generating.value = false },
        onSuccess: () => { gen.kelas_id = null; gen.mata_pelajaran_id = null },
    })
}

const fieldCls = 'w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100 transition-all bg-white'
</script>

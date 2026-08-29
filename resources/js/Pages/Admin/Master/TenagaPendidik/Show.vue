<template>
    <AdminLayout title="Profil Tenaga Pendidik" subtitle="Master Data">

        <Head :title="'Profil — ' + guru.nama" />

        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <Link :href="route('admin.master.tenaga-pendidik.index')"
                    class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h2 class="text-xl font-semibold text-gray-900">Profil Tenaga Pendidik</h2>
            </div>
            <Link :href="route('admin.master.tenaga-pendidik.edit', guru.id)"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit Data
            </Link>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

            <!-- ── Kolom Kiri: Profil ────────────────────────────────────── -->
            <div class="space-y-4">

                <!-- Avatar + Info -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 text-center">
                    <div class="relative inline-block mb-4">
                        <img v-if="guru.foto" :src="guru.foto"
                            class="w-24 h-24 rounded-2xl object-cover ring-4 ring-indigo-50 mx-auto" />
                        <div v-else
                            class="w-24 h-24 rounded-2xl bg-gradient-to-br from-indigo-400 to-violet-500 flex items-center justify-center mx-auto ring-4 ring-indigo-50">
                            <span class="text-white text-3xl font-bold">{{ guru.nama?.charAt(0) }}</span>
                        </div>
                        <span :class="[
                            'absolute -bottom-1 -right-1 w-5 h-5 rounded-full border-2 border-white',
                            guru.is_aktif ? 'bg-emerald-400' : 'bg-gray-300'
                        ]"></span>
                    </div>

                    <h3 class="text-base font-semibold text-gray-900">{{ guru.nama }}</h3>
                    <p class="text-sm text-gray-400 mt-0.5">{{ guru.email }}</p>

                    <!-- Jabatan badges — semua jabatan aktif -->
                    <div class="flex flex-wrap items-center justify-center gap-1.5 mt-3">
                        <span v-for="j in guru.jabatan_aktif" :key="j.pivot_id" :class="[
                            'inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium',
                            j.adalah_utama ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600'
                        ]">
                            <span v-if="j.adalah_utama" class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                            {{ j.nama_jabatan }}
                        </span>
                        <!-- Fallback jika pivot belum ada -->
                        <span v-if="!guru.jabatan_aktif?.length"
                            class="inline-flex items-center px-3 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-medium">
                            {{ guru.jabatan }}
                        </span>
                    </div>

                    <div class="flex items-center justify-center mt-3">
                        <span :class="['px-3 py-1 rounded-full text-xs font-semibold',
                            guru.status_badge?.class ?? 'bg-emerald-100 text-emerald-700']">
                            {{ guru.status_badge?.label ?? 'Aktif' }}
                        </span>
                    </div>
                </div>

                <!-- Absensi bulan ini -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <p class="text-sm font-semibold text-gray-800 mb-3">Absensi Bulan Ini</p>
                    <div class="grid grid-cols-5 gap-1">
                        <div v-for="a in absensiItems" :key="a.label" class="text-center">
                            <p :class="['text-xl font-bold', a.color]">{{ a.value }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ a.label }}</p>
                        </div>
                    </div>
                </div>

                <!-- Info rekening -->
                <div v-if="guru.no_rekening" class="bg-white rounded-2xl border border-gray-200 p-5">
                    <p class="text-sm font-semibold text-gray-800 mb-3">Rekening</p>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-400">Bank</span>
                            <span class="text-xs font-medium text-gray-700">{{ guru.nama_bank }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-400">No. Rekening</span>
                            <span class="text-xs font-mono font-medium text-gray-700">{{ guru.no_rekening }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-400">Atas Nama</span>
                            <span class="text-xs font-medium text-gray-700">{{ guru.nama_rekening }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Kolom Kanan ────────────────────────────────────────────── -->
            <div class="xl:col-span-2 space-y-4">

                <!-- ══ SECTION: JABATAN (RANGKAP) ══════════════════════════ -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800">Jabatan</h3>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ guru.jabatan_aktif?.length ?? 0 }} jabatan aktif
                                <span v-if="(guru.jabatan_aktif?.length ?? 0) > 1" class="text-amber-600 font-medium">
                                    · Rangkap jabatan
                                </span>
                            </p>
                        </div>
                        <Link :href="route('admin.master.jabatan-guru.index', guru.id)"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Setting Jabatan
                        </Link>
                    </div>

                    <!-- Daftar jabatan aktif — read only, tidak ada inline edit -->
                    <div class="divide-y divide-gray-50">
                        <div v-for="j in guru.jabatan_aktif" :key="j.pivot_id" :class="['px-5 py-4 flex items-center gap-3',
                            j.adalah_utama ? 'bg-indigo-50/20' : '']">
                            <div
                                :class="['w-9 h-9 rounded-xl flex items-center justify-center shrink-0 text-xs font-bold', bgTipe(j.tipe)]">
                                {{ j.kode_jabatan || j.nama_jabatan?.charAt(0) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="text-sm font-semibold text-gray-800 truncate">{{ j.nama_jabatan }}</p>
                                    <span v-if="j.adalah_utama"
                                        class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold shrink-0">
                                        Utama
                                    </span>
                                </div>
                                <p class="text-xs text-gray-400 mt-0.5 capitalize">{{ j.tipe }}</p>
                            </div>
                            <!-- Gaji pokok jabatan ini -->
                            <div v-if="gajiPerJabatan(j.jabatan_id) > 0" class="text-right shrink-0">
                                <p class="text-xs font-bold text-emerald-700">{{ formatRp(gajiPerJabatan(j.jabatan_id))
                                    }}</p>
                                <p class="text-xs text-gray-400">/bulan</p>
                            </div>
                        </div>

                        <div v-if="!guru.jabatan_aktif?.length" class="px-5 py-6 text-center">
                            <p class="text-sm text-gray-400">Belum ada jabatan aktif.</p>
                            <Link :href="route('admin.master.jabatan-guru.index', guru.id)"
                                class="text-xs text-indigo-600 hover:underline mt-1 inline-block">
                                Setting jabatan →
                            </Link>
                        </div>
                    </div>

                    <!-- Total gaji pokok -->
                    <div v-if="guru.total_gaji_pokok > 0"
                        class="px-5 py-3 border-t border-gray-100 bg-gray-50/30 flex items-center justify-between">
                        <p class="text-xs text-gray-500">Total gaji pokok</p>
                        <p class="text-sm font-bold text-indigo-700">
                            {{ formatRp(guru.total_gaji_pokok) }}
                            <span class="text-gray-400 font-normal">/bulan</span>
                        </p>
                    </div>

                    <!-- Link ke halaman dedicated -->
                    <div class="px-5 py-3 border-t border-gray-100">
                        <Link :href="route('admin.master.jabatan-guru.index', guru.id)"
                            class="text-xs text-indigo-600 hover:text-indigo-700 flex items-center gap-1 font-medium">
                            Lihat & kelola semua jabatan, riwayat, dan detail gaji →
                        </Link>
                    </div>
                </div>

                <!-- ══ Data Pribadi ══════════════════════════════════════════ -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">Data Pribadi</h3>
                    <div class="grid grid-cols-2 gap-x-8 gap-y-3">
                        <InfoRow label="NIP" :value="guru.nip" />
                        <InfoRow label="NIK" :value="guru.nik" />
                        <InfoRow label="Jenis Guru" :value="guru.jenis_guru" />
                        <InfoRow label="Jenis Kelamin" :value="guru.jenis_kelamin" />
                        <InfoRow label="Tempat Lahir" :value="guru.tempat_lahir" />
                        <InfoRow label="Tanggal Lahir" :value="guru.tanggal_lahir" />
                        <InfoRow label="Pendidikan" :value="guru.pendidikan_terakhir" />
                        <InfoRow label="Jurusan" :value="guru.jurusan" />
                        <InfoRow label="No. HP" :value="guru.no_hp" />
                        <InfoRow label="Tanggal Masuk" :value="guru.tanggal_masuk" />
                    </div>
                    <div class="mt-3 pt-3 border-t border-gray-50">
                        <InfoRow label="Alamat" :value="guru.alamat" />
                    </div>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    guru: { type: Object, required: true },
    jabatanList: { type: Array, default: () => [] },
    rekapBulanIni: { type: Object, default: () => ({}) },
    riwayatStatus: { type: Array, default: () => [] },
})

// ── Absensi ───────────────────────────────────────────────────────────────────
const absensiItems = computed(() => [
    { label: 'Hadir', value: props.rekapBulanIni.hadir ?? 0, color: 'text-emerald-600' },
    { label: 'Terlambat', value: props.rekapBulanIni.terlambat ?? 0, color: 'text-amber-500' },
    { label: 'Izin', value: props.rekapBulanIni.izin ?? 0, color: 'text-blue-500' },
    { label: 'Sakit', value: props.rekapBulanIni.sakit ?? 0, color: 'text-indigo-500' },
    { label: 'Alfa', value: props.rekapBulanIni.alfa ?? 0, color: 'text-red-500' },
])

// ── Jabatan helpers (read-only di halaman ini) ────────────────────────────────
function gajiPerJabatan(jabatanId) {
    const d = props.guru.detail_gaji_pokok?.find(d => d.jabatan_id === jabatanId)
    return d?.nominal ?? 0
}

function bgTipe(t) {
    return {
        struktural: 'bg-indigo-100 text-indigo-700',
        fungsional: 'bg-violet-100 text-violet-700',
        mengajar: 'bg-teal-100 text-teal-700',
    }[t] ?? 'bg-gray-100 text-gray-700'
}

function formatRp(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID') }
</script>

<script>
const InfoRow = {
    props: { label: String, value: [String, Number, null] },
    template: `<div><p class="text-xs text-gray-400">{{ label }}</p><p class="text-sm font-medium text-gray-800 mt-0.5">{{ value || '—' }}</p></div>`,
}
export { InfoRow }
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
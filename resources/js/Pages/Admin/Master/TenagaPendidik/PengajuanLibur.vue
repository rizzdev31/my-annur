<template>
    <AdminLayout title="Pengajuan Hari Libur" subtitle="Tenaga Pendidik">
        <Head title="Pengajuan Hari Libur" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Pengajuan Hari Libur</h2>
                <p class="text-sm text-gray-400 mt-0.5">Setujui usulan hari libur dari guru. Setelah disetujui, jalankan <b>Generate Jam Kerja</b> (mode "Dari Hari Libur Guru").</p>
            </div>
            <Link :href="route('admin.smart-payroll.setting-gaji.jam-kerja.index')"
                class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">Ke Generate Jam Kerja →</Link>
        </div>

        <p v-if="msg" :class="msg.ok ? 'text-emerald-700 bg-emerald-50' : 'text-red-600 bg-red-50'" class="text-sm rounded-xl px-3 py-2 mb-4">{{ msg.text }}</p>

        <div v-if="!list.length" class="bg-white rounded-2xl border border-gray-200 py-14 text-center text-sm text-gray-400">
            Tidak ada pengajuan libur yang menunggu persetujuan.
        </div>

        <div v-else class="space-y-3">
            <div v-for="p in list" :key="p.id" class="bg-white rounded-2xl border border-gray-200 p-4 flex items-center gap-4">
                <img v-if="p.foto" :src="p.foto" class="w-11 h-11 rounded-full object-cover shrink-0" />
                <div v-else class="w-11 h-11 rounded-full bg-indigo-100 grid place-items-center font-bold text-indigo-600 shrink-0">{{ p.nama?.charAt(0) }}</div>

                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-gray-800 truncate">{{ p.nama }}</p>
                    <p class="text-xs text-gray-400">{{ p.jabatan }}</p>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1.5 text-xs">
                        <span class="text-gray-400">Sekarang:
                            <b class="text-gray-600">{{ p.hari_libur.length ? p.hari_libur.map(labelHari).join(', ') : '—' }}</b>
                        </span>
                        <span class="text-amber-600">Diajukan:
                            <b>{{ p.hari_libur_diajukan.length ? p.hari_libur_diajukan.map(labelHari).join(', ') : 'tidak ada libur' }}</b>
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button @click="setujui(p)" :disabled="p.busy"
                        class="px-3.5 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold disabled:opacity-50">{{ p.busy ? '…' : 'Setujui' }}</button>
                    <button @click="tolak(p)" :disabled="p.busy"
                        class="px-3.5 py-2 rounded-xl bg-white border border-gray-200 text-gray-600 text-xs font-semibold disabled:opacity-50">Tolak</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    pengajuan: { type: Array, default: () => [] },
})

const list = ref(props.pengajuan.map(p => ({ ...p, busy: false })))
const msg = ref(null)

const hariOpsi = { senin: 'Sen', selasa: 'Sel', rabu: 'Rab', kamis: 'Kam', jumat: 'Jum', sabtu: 'Sab', ahad: 'Ahd' }
const labelHari = (k) => hariOpsi[k] ?? k

async function setujui(p) {
    p.busy = true; msg.value = null
    try {
        await window.axios.post(`/admin/master/tenaga-pendidik/${p.id}/libur/setujui`)
        list.value = list.value.filter(x => x.id !== p.id)
        msg.value = { ok: true, text: `Libur ${p.nama} disetujui. Jangan lupa Generate Jam Kerja.` }
    } catch (e) {
        p.busy = false
        msg.value = { ok: false, text: e.response?.data?.message || 'Gagal menyetujui.' }
    }
}

async function tolak(p) {
    p.busy = true; msg.value = null
    try {
        await window.axios.post(`/admin/master/tenaga-pendidik/${p.id}/libur/tolak`)
        list.value = list.value.filter(x => x.id !== p.id)
        msg.value = { ok: true, text: `Pengajuan ${p.nama} ditolak.` }
    } catch (e) {
        p.busy = false
        msg.value = { ok: false, text: e.response?.data?.message || 'Gagal menolak.' }
    }
}
</script>

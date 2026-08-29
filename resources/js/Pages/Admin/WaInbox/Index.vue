<template>
    <AdminLayout title="Kotak Masuk WA" subtitle="Balasan Wali">
        <Head title="Kotak Masuk WhatsApp" />

        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Kotak Masuk WhatsApp</h2>
                <p class="text-sm text-gray-400 mt-0.5">Balasan wali santri via webhook Fonnte</p>
            </div>
            <div class="flex items-center gap-2">
                <button v-if="ringkasan.belum_baca" @click="bacaSemua"
                    class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50">
                    Tandai semua dibaca
                </button>
                <button @click="reload('unread')" :class="['px-3 py-2.5 rounded-xl text-sm font-semibold',
                    filter === 'unread' ? 'bg-indigo-600 text-white' : 'border border-gray-200 text-gray-600']">
                    Belum dibaca ({{ ringkasan.belum_baca }})
                </button>
                <button @click="reload('all')" :class="['px-3 py-2.5 rounded-xl text-sm font-semibold',
                    filter === 'all' ? 'bg-indigo-600 text-white' : 'border border-gray-200 text-gray-600']">
                    Semua ({{ ringkasan.total }})
                </button>
            </div>
        </div>

        <!-- Cara setup webhook -->
        <div class="mb-5 rounded-2xl border border-blue-100 bg-blue-50 p-4 text-xs text-blue-700">
            <p class="font-semibold mb-1">Setup di dashboard Fonnte (menu Device → Webhook):</p>
            <p>Tempel URL berikut sebagai <b>Incoming Webhook</b>:</p>
            <code class="block mt-1 bg-white/70 rounded px-2 py-1 break-all">{{ webhook_url }}<span v-if="secret_ada">?secret=***</span></code>
            <p class="mt-1" v-if="!secret_ada">⚠️ <code>WA_WEBHOOK_SECRET</code> di .env masih kosong — sebaiknya isi lalu tambahkan <code>?secret=NILAI</code> di URL.</p>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
            <div v-if="!rows.length" class="p-10 text-center text-sm text-gray-400">Belum ada balasan masuk.</div>
            <div v-for="m in rows" :key="m.id"
                :class="['flex items-start gap-3 px-5 py-3.5 border-b border-gray-50', !m.dibaca ? 'bg-indigo-50/40' : '']">
                <div class="w-9 h-9 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 text-sm font-bold shrink-0">
                    {{ (m.nama || m.pengirim || '?')[0].toUpperCase() }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold text-gray-800">{{ m.nama || m.pengirim }}</span>
                        <span v-if="m.santri" class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">wali {{ m.santri }}</span>
                        <span v-if="!m.dibaca" class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-600 font-semibold">BARU</span>
                        <span class="text-[11px] text-gray-400 ml-auto">{{ m.waktu }}</span>
                    </div>
                    <p class="text-sm text-gray-700 mt-1 whitespace-pre-wrap break-words">{{ m.pesan || '(tanpa teks)' }}</p>
                    <p class="text-[11px] text-gray-400 mt-0.5">{{ m.pengirim }}</p>
                </div>
                <button v-if="!m.dibaca" @click="baca(m)" class="text-xs text-indigo-600 hover:underline shrink-0">Tandai dibaca</button>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    rows:        { type: Array, default: () => [] },
    filter:      { type: String, default: 'all' },
    ringkasan:   { type: Object, default: () => ({}) },
    webhook_url: { type: String, default: '' },
    secret_ada:  { type: Boolean, default: false },
})

const opts = { preserveScroll: true }
function reload(f) { router.get(route('admin.smart-payroll.wa-inbox.index'), { filter: f }, { preserveState: false }) }
function baca(m) { router.patch(route('admin.smart-payroll.wa-inbox.baca', m.id), {}, opts) }
function bacaSemua() { router.post(route('admin.smart-payroll.wa-inbox.baca-semua'), {}, opts) }
</script>

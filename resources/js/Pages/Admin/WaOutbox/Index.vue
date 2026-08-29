<template>
    <AdminLayout title="Monitor WhatsApp" subtitle="Outbox Notifikasi">
        <Head title="Monitor WhatsApp" />

        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Monitor Outbox WhatsApp</h2>
                <p class="text-sm text-gray-400 mt-0.5">Status pengiriman notifikasi ke wali santri (Fonnte)</p>
            </div>
            <div class="flex items-center gap-2">
                <button v-if="ringkasan.failed > 0" @click="retryGagal"
                    class="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold">
                    Kirim Ulang Semua Gagal ({{ ringkasan.failed }})
                </button>
                <button @click="reload('all')" class="px-3 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50">
                    ↻ Refresh
                </button>
            </div>
        </div>

        <!-- Status Fonnte / token invalid hint -->
        <div v-if="!fonnte.aktif || !fonnte.token_ada"
            class="mb-4 rounded-2xl border border-amber-100 bg-amber-50 p-4 text-sm text-amber-700">
            ⚠️ Fonnte belum aktif / token kosong — WA tidak akan terkirim. Isi <code>FONNTE_TOKEN</code> & <code>FONNTE_ENABLED=true</code> di .env.
        </div>
        <div v-if="adaTokenInvalid"
            class="mb-4 rounded-2xl border border-red-100 bg-red-50 p-4 text-sm text-red-700">
            🔑 Ada pesan gagal <b>"token invalid"</b>. Pastikan memakai <b>TOKEN DEVICE</b> Fonnte
            (Dashboard Fonnte → menu <b>Device</b> → salin <i>Token</i> perangkat), <b>bukan</b> token akun.
            Perbarui <code>FONNTE_TOKEN</code> di .env → <code>php artisan config:clear</code>, lalu "Kirim Ulang".
        </div>

        <!-- Ringkasan -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
            <div v-for="s in kartu" :key="s.key" @click="reload(s.key)"
                :class="['rounded-xl border px-4 py-3 cursor-pointer transition-colors',
                    filterStatus === s.key ? 'ring-2 ring-indigo-300 ' + s.bg : s.bg]">
                <p :class="['text-2xl font-bold', s.color]">{{ ringkasan[s.key] ?? 0 }}</p>
                <p class="text-xs text-gray-500">{{ s.label }}</p>
            </div>
        </div>

        <!-- Tabel -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
            <div v-if="!rows.length" class="p-10 text-center text-sm text-gray-400">Belum ada notifikasi WhatsApp.</div>
            <table v-else class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-400 text-xs">
                    <tr>
                        <th class="text-left px-5 py-2 font-medium">Santri</th>
                        <th class="text-left px-3 py-2 font-medium">Tujuan</th>
                        <th class="text-left px-3 py-2 font-medium">Jenis</th>
                        <th class="text-left px-3 py-2 font-medium">Status</th>
                        <th class="text-left px-3 py-2 font-medium">Keterangan</th>
                        <th class="text-left px-3 py-2 font-medium">Waktu</th>
                        <th class="text-right px-5 py-2 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in rows" :key="r.id" class="border-t border-gray-50 hover:bg-gray-50/60">
                        <td class="px-5 py-2.5 font-medium text-gray-800">{{ r.santri }}</td>
                        <td class="px-3 py-2.5 font-mono text-xs text-gray-500">{{ r.tujuan || '—' }}</td>
                        <td class="px-3 py-2.5 capitalize text-gray-600">{{ r.jenis }}</td>
                        <td class="px-3 py-2.5">
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', badge(r.status)]">{{ label(r.status) }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-xs max-w-[220px]">
                            <span v-if="r.error" class="text-red-500">{{ r.error }}</span>
                            <button v-else @click="lihat(r)" class="text-indigo-500 hover:underline">Lihat pesan</button>
                            <span v-if="r.attempts" class="text-gray-300 ml-1">· {{ r.attempts }}x</span>
                        </td>
                        <td class="px-3 py-2.5 text-xs text-gray-500">{{ r.sent_at || r.dibuat }}</td>
                        <td class="px-5 py-2.5 text-right">
                            <button v-if="r.status === 'failed' || r.status === 'pending'"
                                @click="retry(r)" class="text-xs text-indigo-600 hover:underline">Kirim ulang</button>
                            <button v-else @click="lihat(r)" class="text-xs text-gray-400 hover:underline">Detail</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal lihat pesan -->
        <div v-if="detail" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="detail = null">
            <div class="bg-white rounded-2xl w-full max-w-md p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-800">Isi Pesan</h3>
                    <button @click="detail = null" class="text-gray-400">✕</button>
                </div>
                <div class="rounded-xl bg-[#e5ddd5] p-3">
                    <div class="bg-[#dcf8c6] rounded-lg p-2.5 text-[12px] text-gray-800 whitespace-pre-wrap">{{ detail.pesan }}</div>
                </div>
                <p class="text-xs text-gray-400 mt-3">Tujuan: {{ detail.tujuan || '—' }} · {{ detail.dibuat }}</p>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

const props = defineProps({
    rows:         { type: Array, default: () => [] },
    filterStatus: { type: String, default: 'all' },
    ringkasan:    { type: Object, default: () => ({}) },
    fonnte:       { type: Object, default: () => ({}) },
})

const kartu = [
    { key: 'all',     label: 'Total',    bg: 'bg-white border-gray-100',       color: 'text-gray-800' },
    { key: 'pending', label: 'Menunggu', bg: 'bg-blue-50 border-blue-100',     color: 'text-blue-600' },
    { key: 'sent',    label: 'Terkirim', bg: 'bg-emerald-50 border-emerald-100',color: 'text-emerald-600' },
    { key: 'failed',  label: 'Gagal',    bg: 'bg-red-50 border-red-100',       color: 'text-red-600' },
    { key: 'skipped', label: 'Dilewati', bg: 'bg-gray-50 border-gray-100',     color: 'text-gray-500' },
]
const detail = ref(null)
const opts = { preserveScroll: true, preserveState: false }

const adaTokenInvalid = computed(() => props.rows.some(r =>
    r.status === 'failed' && /token|invalid/i.test(r.error || '')))

function reload(status) { router.get(route('admin.smart-payroll.wa-outbox.index'), { status }, opts) }
function retry(r) { router.post(route('admin.smart-payroll.wa-outbox.retry', r.id), {}, { preserveScroll: true }) }
async function retryGagal() {
    if (await confirm({ title: 'Kirim ulang semua pesan gagal?', variant: 'primary', confirmLabel: 'Ya, Kirim Ulang' })) router.post(route('admin.smart-payroll.wa-outbox.retry-gagal'), {}, { preserveScroll: true })
}
function lihat(r) { detail.value = r }

function label(s) { return { pending: 'Menunggu', sent: 'Terkirim', failed: 'Gagal', skipped: 'Dilewati' }[s] ?? s }
function badge(s) {
    return { pending: 'bg-blue-50 text-blue-600', sent: 'bg-emerald-50 text-emerald-600',
             failed: 'bg-red-50 text-red-500', skipped: 'bg-gray-100 text-gray-500' }[s] ?? 'bg-gray-100 text-gray-500'
}
</script>

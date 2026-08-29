<template>
    <AdminLayout title="Akses Ditolak" subtitle="RBAC">
        <Head title="Akses Ditolak" />

        <div class="max-w-xl mx-auto mt-6 sm:mt-12 text-center">
            <div class="w-16 h-16 rounded-2xl bg-amber-50 flex items-center justify-center mx-auto mb-5">
                <svg class="w-8 h-8 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 15v2m0-8v.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z" />
                </svg>
            </div>

            <h2 class="text-xl font-bold text-gray-900">Anda tidak punya akses ke halaman ini</h2>
            <p class="text-sm text-gray-500 mt-1.5 leading-relaxed">
                Halaman ini di luar peran/modul yang diberikan ke akun Anda.
                Hubungi <b>Superadmin</b> jika butuh akses tambahan.
            </p>

            <!-- Menu yang boleh diakses -->
            <div v-if="modulSaya.length" class="mt-7 text-left">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2 px-1">Menu yang bisa Anda buka</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <Link v-for="m in modulSaya" :key="m.kode" :href="hrefBeranda(m)"
                        class="flex items-center gap-3 p-3.5 rounded-xl bg-white border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50/40 transition-colors group">
                        <span class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                            <svg class="w-4.5 h-4.5" style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </span>
                        <span class="text-sm font-semibold text-gray-700 group-hover:text-indigo-700">{{ m.nama }}</span>
                    </Link>
                </div>
            </div>

            <div v-else class="mt-7 p-5 rounded-xl bg-gray-50 border border-gray-200">
                <p class="text-sm text-gray-500">
                    Akun Anda <b>belum diberi peran apa pun</b>. Silakan hubungi Superadmin
                    untuk mendapatkan akses.
                </p>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
    modulSaya: { type: Array, default: () => [] },
})

function hrefBeranda(m) {
    try { return m.beranda ? route(m.beranda) : '#' } catch { return '#' }
}
</script>

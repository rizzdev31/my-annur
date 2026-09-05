<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import PageHeader from '../components/PageHeader.vue'
import { notif, loadNotif, bacaSatu, bacaSemua } from '../store/notif'
import { push, cekPush, aktifkanPush, matikanPush, ujiPush } from '../push'

const router = useRouter()

// Ikon + warna per tipe notifikasi.
function tipeMap(tipe) {
    switch (tipe) {
        case 'tugas_baru':   return { c: '#0C78FF', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' }
        case 'tugas_update': return { c: '#6366F1', icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' }
        case 'penggajian':   return { c: '#0284C7', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V6m0 12v-2m9-4a9 9 0 11-18 0 9 9 0 0118 0z' }
        case 'koreksi':      return { c: '#F59E0B', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' }
        case 'absensi':      return { c: '#059669', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7l2 2 4-4' }
        default:             return { c: '#0C78FF', icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9' }
    }
}

async function buka(n) {
    await bacaSatu(n.id)
    if (n.link) router.push(n.link)
}

onMounted(() => { loadNotif(); cekPush() })
</script>

<template>
    <div>
        <PageHeader title="Notifikasi" />

        <!-- Notifikasi HP (Web Push). Izin HANYA diminta lewat tap tombol —
             browser tidak akan bertanya lagi bila sekali ditolak. -->
        <div class="rounded-2xl bg-white border border-gray-100 p-3 mb-3">
            <div class="flex items-start gap-2.5">
                <span class="shrink-0 text-lg leading-none mt-0.5">🔔</span>
                <div class="flex-1 min-w-0">
                    <p class="text-[13px] font-extrabold text-gray-800">Notifikasi di HP</p>
                    <p class="text-[11px] text-gray-400 leading-snug">
                        Pengingat absen &amp; jurnal muncul di layar HP walau aplikasi ditutup.
                    </p>

                    <!-- iPhone: tombol pasti gagal sebelum dipasang ke Layar Utama,
                         jadi tampilkan panduannya, bukan tombolnya. -->
                    <div v-if="push.perluHomeScreen" class="mt-2 rounded-xl bg-amber-50 border border-amber-100 px-2.5 py-2">
                        <p class="text-[11px] text-amber-800 leading-snug">
                            <b>iPhone:</b> pasang dulu ke Layar Utama — ketuk tombol
                            <b>Bagikan</b> di Safari → <b>Tambah ke Layar Utama</b>, lalu buka aplikasi
                            dari ikonnya dan kembali ke halaman ini.
                        </p>
                    </div>

                    <p v-else-if="!push.didukung" class="mt-2 text-[11px] text-gray-400">
                        Browser ini belum mendukung notifikasi HP.
                    </p>

                    <div v-else class="mt-2 flex flex-wrap gap-2">
                        <button v-if="!push.aktif" @click="aktifkanPush" :disabled="push.sibuk"
                            class="px-4 py-1.5 rounded-lg bg-[#0C78FF] text-white text-xs font-bold disabled:opacity-60">
                            {{ push.sibuk ? 'Memproses…' : 'Aktifkan' }}
                        </button>
                        <template v-else>
                            <span class="px-2.5 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-bold">✓ Aktif</span>
                            <button @click="ujiPush" :disabled="push.sibuk"
                                class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 text-xs font-bold disabled:opacity-60">
                                Coba kirim
                            </button>
                            <button @click="matikanPush" :disabled="push.sibuk"
                                class="px-3 py-1.5 rounded-lg bg-red-50 text-red-600 text-xs font-bold disabled:opacity-60">
                                Matikan
                            </button>
                        </template>
                    </div>

                    <p v-if="push.pesan" class="mt-2 text-[11px] text-gray-500 leading-snug">{{ push.pesan }}</p>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between mb-3 -mt-1">
            <p class="text-xs text-gray-400">
                <span v-if="notif.unread > 0" class="font-semibold text-[#0C78FF]">{{ notif.unread }} belum dibaca</span>
                <span v-else>Semua sudah dibaca</span>
            </p>
            <button v-if="notif.unread > 0" @click="bacaSemua"
                class="text-xs font-semibold text-[#0C78FF] active:opacity-70">Tandai semua dibaca</button>
        </div>

        <!-- Loading -->
        <div v-if="notif.loading && !notif.items.length" class="space-y-2">
            <div v-for="i in 4" :key="i" class="h-16 rounded-2xl bg-white border border-gray-100 animate-pulse"></div>
        </div>

        <!-- Kosong -->
        <div v-else-if="!notif.items.length" class="text-center py-16">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-[#0C78FF]/10 grid place-items-center mb-3">
                <svg class="w-8 h-8 text-[#0C78FF]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            </div>
            <p class="text-sm font-semibold text-gray-700">Belum ada notifikasi</p>
            <p class="text-xs text-gray-400 mt-1">Pemberitahuan penting akan muncul di sini.</p>
        </div>

        <!-- Daftar -->
        <div v-else class="space-y-2">
            <button v-for="n in notif.items" :key="n.id" @click="buka(n)"
                class="w-full text-left flex items-start gap-3 p-3 rounded-2xl border transition active:scale-[0.99]"
                :class="n.sudah_dibaca ? 'bg-white border-gray-100' : 'bg-[#0C78FF]/[0.04] border-[#0C78FF]/20'">
                <span class="w-9 h-9 rounded-xl grid place-items-center shrink-0 mt-0.5" :style="{ background: tipeMap(n.tipe).c + '1A' }">
                    <svg class="w-[18px] h-[18px]" :style="{ color: tipeMap(n.tipe).c }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" :d="tipeMap(n.tipe).icon"/></svg>
                </span>
                <span class="flex-1 min-w-0">
                    <span class="flex items-center gap-2">
                        <span class="block text-[13.5px] font-bold text-gray-900 leading-tight truncate">{{ n.judul }}</span>
                        <span v-if="!n.sudah_dibaca" class="w-2 h-2 rounded-full bg-[#0C78FF] shrink-0"></span>
                    </span>
                    <span class="block text-[12px] text-gray-500 leading-snug mt-0.5">{{ n.pesan }}</span>
                    <span class="block text-[10.5px] text-gray-400 mt-1">{{ n.waktu }}</span>
                </span>
                <svg v-if="n.link" class="w-4 h-4 text-gray-300 shrink-0 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>
</template>

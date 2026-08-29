<script setup>
import { ref, computed } from 'vue'
import { pwa, promptInstall, dismissInstall } from '../store/pwa'
import BottomSheet from './BottomSheet.vue'

const show = computed(() => !pwa.installed && !pwa.dismissed && (pwa.canInstall || pwa.isIOS))
const iosSheet = ref(false)

async function pasang() {
    if (pwa.isIOS) { iosSheet.value = true; return }
    const hasil = await promptInstall()
    if (hasil === 'accepted' || hasil === 'no-prompt') dismissInstall()
}
</script>

<template>
    <Transition name="drop">
        <div v-if="show" class="mb-3 flex items-center gap-3 rounded-2xl bg-gradient-to-br from-[#06346B] to-[#0C78FF] text-white p-3 shadow-lg shadow-blue-900/20">
            <img src="/guru-icon-192.png" alt="" class="w-10 h-10 rounded-xl shrink-0 ring-1 ring-white/25" />
            <div class="min-w-0 flex-1">
                <p class="text-[13px] font-bold leading-tight">Pasang Aplikasi An-Nur Guru</p>
                <p class="text-[11px] text-white/70 leading-tight mt-0.5">Akses cepat dari layar utama HP, seperti aplikasi biasa.</p>
            </div>
            <button @click="pasang"
                class="shrink-0 text-[12px] font-bold bg-white text-[#06346B] rounded-xl px-3 py-2 active:scale-95 transition">
                Pasang
            </button>
            <button @click="dismissInstall" aria-label="Tutup" class="shrink-0 w-7 h-7 -mr-1 grid place-items-center rounded-lg text-white/60 active:bg-white/10">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
            </button>
        </div>
    </Transition>

    <!-- Instruksi iOS (tak ada prompt otomatis di Safari) -->
    <BottomSheet v-model="iosSheet" title="Pasang di iPhone/iPad" subtitle="Lewat Safari — beberapa langkah singkat">
        <ol class="space-y-3 py-1">
            <li class="flex items-start gap-3">
                <span class="w-6 h-6 rounded-full bg-[#0C78FF]/10 text-[#0C78FF] grid place-items-center text-xs font-bold shrink-0">1</span>
                <p class="text-sm text-gray-700 leading-snug">Ketuk ikon <b>Bagikan</b>
                    <svg class="inline w-4 h-4 -mt-0.5 text-[#0C78FF]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4l4 4M6 12v6a2 2 0 002 2h8a2 2 0 002-2v-6" /></svg>
                    di bar bawah Safari.</p>
            </li>
            <li class="flex items-start gap-3">
                <span class="w-6 h-6 rounded-full bg-[#0C78FF]/10 text-[#0C78FF] grid place-items-center text-xs font-bold shrink-0">2</span>
                <p class="text-sm text-gray-700 leading-snug">Gulir dan pilih <b>“Tambah ke Layar Utama”</b> (Add to Home Screen).</p>
            </li>
            <li class="flex items-start gap-3">
                <span class="w-6 h-6 rounded-full bg-[#0C78FF]/10 text-[#0C78FF] grid place-items-center text-xs font-bold shrink-0">3</span>
                <p class="text-sm text-gray-700 leading-snug">Ketuk <b>Tambah</b>. Ikon An-Nur Guru muncul di layar utama.</p>
            </li>
        </ol>
        <button @click="iosSheet = false; dismissInstall()" class="w-full mt-2 py-3 rounded-2xl bg-[#0C78FF] text-white font-bold active:scale-[0.99] transition">Mengerti</button>
    </BottomSheet>
</template>

<style scoped>
.drop-enter-active, .drop-leave-active { transition: all .3s cubic-bezier(.22,1,.36,1); }
.drop-enter-from, .drop-leave-to { opacity: 0; transform: translateY(-8px); }
</style>

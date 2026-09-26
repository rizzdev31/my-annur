<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../api'

// Pengumuman kini bisa LEBIH DARI SATU sekaligus (mis. beberapa notulensi rapat),
// dan isinya bisa pamflet gambar atau berkas PDF. Ditampilkan satu per satu:
// tutup yang di depan → lanjut ke berikutnya.
const daftar = ref([])
const ke = ref(0)
const show = ref(false)
const jangan = ref(false)
const imgLoaded = ref(false)

const p = computed(() => daftar.value[ke.value] ?? null)
const sisa = computed(() => Math.max(0, daftar.value.length - ke.value - 1))
const dismissKey = (x) => `guru_pengumuman_dismiss_${x.id}_${x.versi ?? 0}`

onMounted(async () => {
    try {
        const res = await api.get('/pengumuman/aktif')
        // Klien lama memakai `data` (satu objek); di sini pakai `daftar` bila ada.
        const semua = res.data.daftar ?? (res.data.data ? [res.data.data] : [])
        daftar.value = semua
            .filter((x) => x && (x.gambar_url || x.file_url || x.isi))
            .filter((x) => localStorage.getItem(dismissKey(x)) !== '1')
        if (!daftar.value.length) return
        show.value = true
    } catch (_) {/* diamkan */}
})

function tutup() {
    if (jangan.value && p.value) localStorage.setItem(dismissKey(p.value), '1')
    jangan.value = false
    imgLoaded.value = false
    if (ke.value < daftar.value.length - 1) { ke.value += 1; return }   // lanjut berikutnya
    show.value = false
}
function bukaLink() {
    if (p.value?.link_url) window.open(p.value.link_url, '_blank', 'noopener')
}
function bukaBerkas() {
    if (p.value?.file_url) window.open(p.value.file_url, '_blank', 'noopener')
}
</script>

<template>
    <Transition name="pop">
        <div v-if="show && p" class="fixed inset-0 z-[80] flex items-center justify-center p-5"
            style="background: rgba(10,15,30,0.74); backdrop-filter: blur(3px)" @click.self="tutup">

            <!-- Kartu: LEBAR mengikuti foto (w-fit), panel bawah menyesuaikan lebar foto -->
            <div class="pop-card w-fit max-w-[92vw] flex flex-col items-stretch">
                <!-- Frame gambar -->
                <div class="group relative rounded-3xl overflow-hidden bg-white shadow-2xl ring-1 ring-white/10">
                    <img :src="p.gambar_url" :alt="p.judul || 'Pengumuman'" @load="imgLoaded = true"
                        @click="bukaLink"
                        class="block w-auto h-auto max-w-[92vw] max-h-[72vh] transition-all duration-500"
                        :class="[p.link_url ? 'cursor-pointer group-hover:scale-[1.03]' : '', imgLoaded ? 'opacity-100' : 'opacity-0']" />

                    <!-- Skeleton saat memuat -->
                    <div v-if="!imgLoaded" class="absolute inset-0 min-w-[220px] min-h-[300px] grid place-items-center bg-gray-100">
                        <div class="w-7 h-7 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
                    </div>

                    <!-- Tombol tutup (hover membesar & gelap) -->
                    <button @click="tutup" title="Tutup"
                        class="absolute top-2.5 right-2.5 w-9 h-9 rounded-full bg-black/45 text-white grid place-items-center
                               backdrop-blur-sm transition-all duration-200 hover:bg-black/75 hover:scale-110 active:scale-95">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
                    </button>

                    <!-- Overlay hover bila ada link -->
                    <div v-if="p.link_url" @click="bukaLink"
                        class="absolute inset-x-0 bottom-0 pt-10 pb-3 px-3 bg-gradient-to-t from-black/60 to-transparent
                               opacity-0 translate-y-1 group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-300 cursor-pointer">
                        <span class="inline-flex items-center gap-1.5 text-[12px] font-bold text-white bg-white/15 backdrop-blur px-3 py-1.5 rounded-full">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            Ketuk untuk selengkapnya
                        </span>
                    </div>
                </div>

                <!-- Panel bawah (lebar = lebar foto) -->
                <div class="mt-3 bg-white rounded-2xl p-3.5 shadow-xl">
                    <p v-if="p.judul" class="text-sm font-extrabold text-gray-900 mb-2.5 text-center">{{ p.judul }}</p>
                    <!-- Pengumuman berkas (mis. notulensi rapat) -->
                    <div v-if="p.tipe === 'pdf' && p.file_url" class="mb-2.5">
                        <p v-if="p.isi" class="text-[12px] text-gray-500 text-center mb-2">{{ p.isi }}</p>
                        <button @click="bukaBerkas"
                            class="w-full py-3 rounded-xl bg-[#0C78FF] text-white text-sm font-bold active:scale-[0.99] transition">
                            Buka Notulensi (PDF)
                        </button>
                        <p v-if="p.nama_file" class="text-[10px] text-gray-400 text-center mt-1 truncate">{{ p.nama_file }}</p>
                    </div>
                    <p v-if="sisa > 0" class="text-[11px] text-gray-400 text-center mb-1.5">{{ sisa }} pengumuman lain menyusul</p>

                    <label class="flex items-center justify-center gap-2 cursor-pointer select-none mb-3">
                        <input type="checkbox" v-model="jangan" class="w-4 h-4 rounded accent-[#0C78FF]" />
                        <span class="text-[12px] text-gray-500">Jangan tampilkan lagi</span>
                    </label>

                    <div class="flex gap-2.5">
                        <button v-if="p.link_url" @click="bukaLink"
                            class="flex-1 py-2.5 rounded-xl bg-gray-100 text-gray-600 text-sm font-bold transition-all hover:bg-gray-200 active:scale-[0.98]">Buka</button>
                        <button @click="tutup"
                            class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-[#0C78FF] to-[#0958c9] text-white text-sm font-bold
                                   shadow-lg shadow-[#0C78FF]/25 transition-all hover:-translate-y-0.5 hover:shadow-xl active:translate-y-0 active:scale-[0.98]">
                            {{ p.link_url ? 'Tutup' : 'Mengerti' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
/* Backdrop fade + kartu spring pop */
.pop-enter-active { transition: opacity .32s ease; }
.pop-leave-active { transition: opacity .26s ease; }
.pop-enter-from, .pop-leave-to { opacity: 0; }

.pop-enter-active .pop-card { animation: popIn .44s cubic-bezier(.34, 1.56, .64, 1); }
.pop-leave-active .pop-card { animation: popOut .24s ease forwards; }

@keyframes popIn {
    0%   { opacity: 0; transform: scale(.86) translateY(14px); }
    100% { opacity: 1; transform: none; }
}
@keyframes popOut {
    0%   { opacity: 1; transform: none; }
    100% { opacity: 0; transform: scale(.94) translateY(6px); }
}
</style>

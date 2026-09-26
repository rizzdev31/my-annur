<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../api'

const emit = defineEmits(['loaded'])
// Pengumuman bisa lebih dari satu (mis. beberapa notulensi/pamflet) dan bisa
// berupa berkas PDF. Ditampilkan satu per satu: tutup → lanjut ke berikutnya.
const daftar = ref([])
const ke = ref(0)
const show = ref(false)

const p = computed(() => daftar.value[ke.value] ?? null)
const sisa = computed(() => Math.max(0, daftar.value.length - ke.value - 1))
const seenKey = (x) => `santri_peng_seen_${x.id}`

onMounted(async () => {
    try {
        const res = (await api.get('/pengumuman')).data
        const semua = res.daftar ?? (res.data ? [res.data] : [])
        daftar.value = semua.filter((x) => x && (x.gambar_url || x.file_url || x.isi))
        // Tampilkan otomatis bila ada yang belum pernah dilihat pada versi ini.
        const belum = daftar.value.some((x) => localStorage.getItem(seenKey(x)) !== String(x.versi))
        if (daftar.value.length && belum) show.value = true
    } catch (_) {/* diamkan */}
    finally { emit('loaded', daftar.value.length > 0) }
})

function tutup() {
    if (p.value) localStorage.setItem(seenKey(p.value), String(p.value.versi))
    if (ke.value < daftar.value.length - 1) { ke.value += 1; return }
    show.value = false
}
function bukaBerkas() {
    if (p.value?.file_url) window.open(p.value.file_url, '_blank', 'noopener')
}
function open() { if (daftar.value.length) { ke.value = 0; show.value = true } }
defineExpose({ open, has: () => daftar.value.length > 0 })
</script>

<template>
    <Transition name="peng">
        <div v-if="show && p" class="fixed inset-0 z-[90] flex items-center justify-center p-5" @click.self="tutup">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
            <div class="relative w-full max-w-sm">
                <button @click="tutup" class="absolute -top-3 -right-3 z-10 w-9 h-9 rounded-full bg-white shadow-lg grid place-items-center text-gray-500 active:scale-90 transition">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
                <component :is="p.link_url ? 'a' : 'div'" :href="p.link_url || undefined" :target="p.link_url ? '_blank' : undefined" class="block rounded-2xl overflow-hidden shadow-2xl bg-white">
                    <img v-if="p.gambar_url" :src="p.gambar_url" :alt="p.judul || 'Pengumuman'" class="w-full h-auto object-contain" />
                    <p v-if="p.judul" class="text-center text-sm font-bold text-gray-700 px-4 pt-3" :class="p.gambar_url ? 'py-2.5' : ''">{{ p.judul }}</p>
                    <div v-if="p.tipe === 'pdf' && p.file_url" class="px-4 pb-4 pt-1">
                        <p v-if="p.isi" class="text-[12px] text-gray-500 text-center mb-2.5">{{ p.isi }}</p>
                        <button type="button" @click.stop.prevent="bukaBerkas"
                            class="w-full py-3 rounded-xl bg-[#0C78FF] text-white text-sm font-bold active:scale-[0.99] transition">
                            Buka Berkas (PDF)
                        </button>
                    </div>
                    <p v-else-if="p.isi && !p.gambar_url" class="text-[12px] text-gray-500 text-center px-4 pb-4">{{ p.isi }}</p>
                    <p v-if="sisa > 0" class="text-[11px] text-gray-400 text-center pb-3">{{ sisa }} pengumuman lain menyusul</p>
                </component>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.peng-enter-active .relative, .peng-leave-active .relative { transition: transform .35s cubic-bezier(.32,.72,0,1), opacity .35s; }
.peng-enter-from .relative, .peng-leave-to .relative { transform: scale(.9); opacity: 0; }
.peng-enter-active > div:first-child, .peng-leave-active > div:first-child { transition: opacity .3s; }
.peng-enter-from > div:first-child, .peng-leave-to > div:first-child { opacity: 0; }
</style>

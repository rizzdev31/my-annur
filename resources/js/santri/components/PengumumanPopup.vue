<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'

const emit = defineEmits(['loaded'])
const p = ref(null)
const show = ref(false)

onMounted(async () => {
    try {
        const data = (await api.get('/pengumuman')).data.data
        if (data && data.gambar_url) {
            p.value = data
            const seen = localStorage.getItem('santri_peng_seen')
            if (String(seen) !== String(data.versi)) show.value = true
        }
    } catch (_) {/* diamkan */}
    finally { emit('loaded', !!p.value) }
})

function tutup() {
    if (p.value?.versi) localStorage.setItem('santri_peng_seen', String(p.value.versi))
    show.value = false
}
function open() { if (p.value) show.value = true }
defineExpose({ open, has: () => !!p.value })
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
                    <img :src="p.gambar_url" :alt="p.judul || 'Pengumuman'" class="w-full h-auto object-contain" />
                    <p v-if="p.judul" class="text-center text-sm font-bold text-gray-700 px-4 py-2.5">{{ p.judul }}</p>
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

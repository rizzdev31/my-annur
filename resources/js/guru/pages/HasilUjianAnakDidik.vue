<script setup>
/**
 * HASIL UJIAN ANAK DIDIK — tasmi' & tasnif seluruh santri yang diampu guru ini.
 *
 * Dibuat karena pengampu sebelumnya tidak punya tempat untuk tahu hasil ujian
 * anak didiknya: hasil hanya terlihat oleh PENGUJI. Padahal pengampulah yang
 * membimbing hariannya dan perlu menindaklanjuti catatan penguji serta masukan
 * pimpinan.
 *
 * Daftarnya dibaca dari keanggotaan kelas SAAT INI, jadi santri yang naik kelas
 * atau pindah halaqoh otomatis ikut ke pengampu barunya.
 */
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
import { toast } from '../store/toast'

const router = useRouter()
const loading = ref(true)
const data = ref({ kelas: [], hasil: [], ringkasan: {} })
const saring = ref('semua')      // semua | lulus | belum | menunggu
const terbuka = ref(null)

const daftar = computed(() => {
    const h = data.value.hasil || []
    if (saring.value === 'lulus')    return h.filter(x => x.status === 'selesai' && x.lulus)
    if (saring.value === 'belum')    return h.filter(x => x.status === 'selesai' && !x.lulus)
    if (saring.value === 'menunggu') return h.filter(x => x.status === 'ditugaskan')
    return h
})

async function muat() {
    loading.value = true
    try {
        data.value = (await api.get('/education/laporan/hasil-ujian')).data.data
    } catch (e) {
        toast.error(e.response?.data?.message || 'Gagal memuat hasil ujian.')
    } finally { loading.value = false }
}
onMounted(muat)

function buka(h) { terbuka.value = terbuka.value === kunci(h) ? null : kunci(h) }
function kunci(h) { return h.jenis + '-' + h.id }

function lihatSertifikat(h) {
    router.push({
        name: h.jenis === 'tasmi' ? 'sertifikat-tasmi' : 'sertifikat-tasnif',
        params: { id: h.id },
    })
}
</script>

<template>
    <div class="px-4 pt-3 pb-24">
        <h1 class="text-lg font-bold text-gray-800">Hasil Ujian Anak Didik</h1>
        <p class="text-xs text-gray-400 mt-0.5 mb-3">
            Tasmi' &amp; tasnif santri yang Anda ampu saat ini. Ikut berpindah bila santri naik kelas.
        </p>

        <div v-if="loading" class="pt-16 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>

        <template v-else>
            <!-- Ringkasan -->
            <div class="grid grid-cols-4 gap-2 mb-3">
                <button v-for="s in [
                    { k: 'semua', label: 'Semua', nilai: data.ringkasan.total, cls: 'text-gray-800' },
                    { k: 'lulus', label: 'Lulus', nilai: data.ringkasan.lulus, cls: 'text-emerald-600' },
                    { k: 'belum', label: 'Belum', nilai: data.ringkasan.belum_lulus, cls: 'text-rose-600' },
                    { k: 'menunggu', label: 'Dinilai', nilai: data.ringkasan.menunggu, cls: 'text-amber-600' },
                ]" :key="s.k" @click="saring = s.k"
                    :class="['rounded-2xl border px-2 py-2.5 text-center transition',
                        saring === s.k ? 'border-[#0C78FF] bg-blue-50' : 'border-gray-100 bg-white']">
                    <p class="text-[10px] text-gray-400">{{ s.label }}</p>
                    <p :class="['text-lg font-bold leading-tight', s.cls]">{{ s.nilai ?? 0 }}</p>
                </button>
            </div>

            <p v-if="!data.kelas.length" class="rounded-2xl bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800">
                Anda belum terdaftar mengampu kelas tahfidz/tahsin, jadi belum ada anak didik yang bisa ditampilkan.
            </p>

            <div v-else-if="!daftar.length" class="rounded-2xl bg-white border border-gray-100 px-4 py-10 text-center">
                <p class="text-sm text-gray-400">Belum ada hasil ujian pada saringan ini.</p>
            </div>

            <!-- Daftar hasil -->
            <div v-else class="space-y-2">
                <div v-for="h in daftar" :key="kunci(h)" class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                    <button @click="buka(h)" class="w-full px-3.5 py-3 flex items-center gap-3 text-left active:bg-gray-50">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-gray-800 truncate">{{ h.santri }}</p>
                            <p class="text-[11px] text-gray-400 truncate">
                                <span :class="['font-bold', h.jenis === 'tasmi' ? 'text-violet-600' : 'text-sky-600']">
                                    {{ h.jenis === 'tasmi' ? "TASMI'" : 'TASNIF' }}
                                </span>
                                · {{ h.capaian }} · {{ h.kelas }} · {{ h.tanggal }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <template v-if="h.status === 'selesai'">
                                <p :class="['text-base font-bold leading-none', h.lulus ? 'text-emerald-600' : 'text-rose-600']">
                                    {{ h.nilai }}
                                </p>
                                <p :class="['text-[10px] font-bold', h.lulus ? 'text-emerald-600' : 'text-rose-600']">
                                    {{ h.lulus ? 'LULUS' : 'BELUM' }}
                                </p>
                            </template>
                            <span v-else class="px-2 py-0.5 rounded-lg bg-amber-50 text-amber-700 text-[10px] font-bold">
                                MENUNGGU
                            </span>
                        </div>
                    </button>

                    <!-- Rincian -->
                    <div v-if="terbuka === kunci(h)" class="px-3.5 pb-3.5 border-t border-gray-50 pt-3">
                        <div v-if="h.rubrik_ada" class="grid grid-cols-2 gap-1.5 mb-2.5">
                            <div v-for="r in h.rubrik" :key="r.label"
                                class="flex items-center justify-between px-2.5 py-1.5 rounded-xl bg-gray-50">
                                <span class="text-[11px] text-gray-500">{{ r.label }}</span>
                                <span :class="['text-sm font-bold',
                                    r.nilai !== null && r.nilai < h.ambang ? 'text-amber-600' : 'text-gray-700']">
                                    {{ r.nilai ?? '—' }}
                                </span>
                            </div>
                        </div>
                        <p v-else-if="h.status === 'selesai'" class="text-[11px] text-gray-400 mb-2.5">
                            Rincian rubrik tidak tersedia untuk hasil ini.
                        </p>
                        <p v-else class="text-[11px] text-gray-400 mb-2.5">
                            Penguji: {{ h.penguji }} — belum dinilai.
                        </p>

                        <div v-if="h.catatan" class="rounded-xl bg-gray-50 px-3 py-2 mb-2">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Catatan Penguji</p>
                            <p class="text-xs text-gray-600 italic mt-0.5">{{ h.catatan }}</p>
                            <p class="text-[10px] text-gray-400 text-right mt-0.5">— {{ h.penguji }}</p>
                        </div>

                        <!-- Arahan yang perlu ditindaklanjuti pengampu -->
                        <div v-if="h.masukan" class="rounded-xl bg-emerald-50 border border-emerald-100 px-3 py-2 mb-2">
                            <p class="text-[10px] font-bold text-emerald-700 uppercase tracking-wide">Masukan Pimpinan</p>
                            <p class="text-xs text-emerald-800 mt-0.5">{{ h.masukan }}</p>
                            <p v-if="h.masukan_oleh" class="text-[10px] text-emerald-600 text-right mt-0.5">
                                — {{ h.masukan_oleh }}
                            </p>
                        </div>

                        <button v-if="h.boleh_sertifikat" @click="lihatSertifikat(h)"
                            class="w-full py-2.5 rounded-xl bg-[#0C78FF] text-white text-xs font-bold active:scale-[0.99] transition">
                            Lihat Sertifikat
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

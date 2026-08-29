<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../api'
import PageHeader from '../components/PageHeader.vue'

const tanggal = ref('')
const jadwal = ref([])
const loading = ref(true)
const error = ref('')

async function load() {
    loading.value = true; error.value = ''
    try {
        const res = await api.get('/education/tahfidz/jadwal-hari-ini')
        const d = res.data.data ?? res.data
        tanggal.value = d.tanggal
        jadwal.value = d.jadwal ?? []
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat kelas tahfidz.'
    } finally { loading.value = false }
}
onMounted(load)

// Kelompokkan per kelas (1 kelas bisa punya banyak sesi/hari) — mirror Flutter.
// Pilih 1 sesi relevan: dalam_jam → sudah_absen → belum absen → pertama.
const groups = computed(() => {
    const byKelas = new Map()
    for (const j of jadwal.value) {
        const key = j.kelas_id ?? `x${j.jadwal_id}`
        if (!byKelas.has(key)) byKelas.set(key, [])
        byKelas.get(key).push(j)
    }
    const out = []
    for (const list of byKelas.values()) {
        const today = list.filter((j) => j.is_today)
        const chosen =
            today.find((j) => j.dalam_jam) ||
            today.find((j) => j.sudah_absen) ||
            today.find((j) => !j.sudah_absen) ||
            today[0] || list[0]
        out.push({
            kelasId: chosen.kelas_id ?? chosen.jadwal_id,
            jadwalId: chosen.jadwal_id,
            kelas: list[0].kelas,
            mapel: chosen.mata_pelajaran,
            jumlahSantri: list[0].jumlah_santri,
            totalSesi: list.length,
            adaHariIni: list.some((j) => j.is_today),
            sudahAbsenHariIni: list.some((j) => j.is_today && j.sudah_absen),
            wajibAbsen: list.some((j) => j.is_today && j.wajib_absen),
            dalamJam: list.some((j) => j.dalam_jam),
        })
    }
    // yang ada hari ini di atas
    return out.sort((a, b) => (a.adaHariIni === b.adaHariIni ? 0 : a.adaHariIni ? -1 : 1))
})
</script>

<template>
    <div>
        <PageHeader title="Kelas Tahfidz" />

        <div v-if="loading" class="pt-10 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div v-else-if="error" class="pt-8 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button @click="load" class="mt-3 px-4 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold">Coba lagi</button>
        </div>

        <template v-else>
            <p class="text-xs text-gray-400 mb-1">{{ tanggal }}</p>
            <p class="text-[11px] text-gray-400 mb-3">Setoran/jurnal bisa kapan saja · absen saat jam mengajar.</p>

            <div v-if="!groups.length" class="pt-16 text-center text-sm text-gray-400">Anda belum memegang kelas tahfidz.</div>

            <ul v-else class="space-y-3">
                <RouterLink v-for="g in groups" :key="g.kelasId" :to="{ name: 'tahfidz-roster', params: { jadwalId: g.jadwalId } }"
                    class="block rounded-2xl bg-white border border-gray-100 p-4 active:scale-[0.99] transition"
                    :class="g.adaHariIni ? '' : 'opacity-80'">
                    <div class="flex items-start gap-3">
                        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 grid place-items-center shrink-0">
                            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-gray-800 truncate">{{ g.kelas }}</p>
                            <p class="text-[11px] text-gray-400">{{ g.mapel }} · {{ g.jumlahSantri }} santri · {{ g.totalSesi }} sesi/minggu</p>
                            <div class="flex items-center gap-1.5 mt-2">
                                <span v-if="!g.adaHariIni" class="text-[10px] font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">Tak ada sesi hari ini</span>
                                <span v-else-if="g.sudahAbsenHariIni" class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">✓ Sudah absen</span>
                                <span v-else-if="g.wajibAbsen" class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">Perlu absen</span>
                                <span v-else-if="g.dalamJam" class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">Berlangsung</span>
                                <span v-else class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">Ada sesi hari ini</span>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-300 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </div>
                </RouterLink>
            </ul>
        </template>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api'
import { toast } from '../store/toast'
import PageHeader from '../components/PageHeader.vue'
import BottomSheet from '../components/BottomSheet.vue'

const router = useRouter()
const tab = ref('tasmi')
const loading = ref(true)
const error = ref('')
const tasmiList = ref([])
const tasnifList = ref([])
const aktif = ref(null)     // { jenis:'tasmi'|'tasnif', item }
const saving = ref(false)

const AMBANG = 8
const RUBRIK = {
    tasmi: [['kelancaran', 'Kelancaran'], ['makhorijul_huruf', 'Makhorijul Huruf'], ['tajwid', 'Tajwid'], ['fashohah', 'Fashohah']],
    tasnif: [['pemahaman_materi', 'Pemahaman Materi'], ['kelancaran', 'Kelancaran'], ['fashohah', 'Fashohah'], ['makhorijul_huruf', 'Makhorijul Huruf']],
}
const f = reactive({ catatan: '' })

const rubrikAktif = computed(() => aktif.value ? RUBRIK[aktif.value.jenis] : [])
const rata = computed(() => {
    if (!aktif.value) return null
    const keys = RUBRIK[aktif.value.jenis].map((r) => r[0])
    if (keys.some((k) => f[k] === '' || f[k] == null)) return null
    const vals = keys.map((k) => Number(f[k]))
    if (vals.some((v) => isNaN(v) || v < 1 || v > 10)) return null
    return Math.round((vals.reduce((a, b) => a + b, 0) / vals.length) * 100) / 100
})
const lulus = computed(() => rata.value != null && rata.value >= AMBANG)

async function load() {
    loading.value = true; error.value = ''
    try {
        const [a, b] = await Promise.all([
            api.get('/education/tahfidz/tasmi-saya'),
            api.get('/education/tahsin/tasnif-saya'),
        ])
        tasmiList.value = a.data.data ?? []
        tasnifList.value = b.data.data ?? []
    } catch (e) { error.value = e.response?.data?.message || 'Gagal memuat data ujian.' }
    finally { loading.value = false }
}
onMounted(load)

function buka(jenis, item) {
    aktif.value = { jenis, item }
    Object.keys(f).forEach((k) => delete f[k])
    RUBRIK[jenis].forEach((r) => { f[r[0]] = '' })
    f.catatan = ''
}

async function kirim() {
    const { jenis, item } = aktif.value
    for (const [k, label] of RUBRIK[jenis]) {
        const v = Number(f[k])
        if (f[k] === '' || isNaN(v) || v < 1 || v > 10) return toast.warning(`${label}: isi nilai 1–10.`)
    }
    if (!f.catatan.trim() || f.catatan.trim().length < 3) return toast.warning('Catatan wajib (min 3 huruf).')
    saving.value = true
    try {
        const body = { catatan: f.catatan.trim() }
        RUBRIK[jenis].forEach((r) => { body[`nilai_${r[0]}`] = Number(f[r[0]]) })
        const url = jenis === 'tasmi'
            ? `/education/tahfidz/tasmi/${item.id}/nilai`
            : `/education/tahsin/tasnif/${item.id}/nilai`
        const res = await api.post(url, body)
        const isLulus = res.data.data?.lulus
        aktif.value = null
        toast.success(res.data.message || 'Nilai tersimpan.')
        // Tasnif lulus → langsung tampilkan sertifikat kenaikan level.
        if (jenis === 'tasnif' && isLulus) { router.push({ name: 'sertifikat-tasnif', params: { id: item.id } }); return }
        await load()
    } catch (e) {
        toast.error(e.response?.data?.errors ? Object.values(e.response.data.errors)[0][0] : (e.response?.data?.message || 'Gagal menyimpan nilai.'))
    } finally { saving.value = false }
}
</script>

<template>
    <div>
        <PageHeader title="Tasmi' & Tasnif" />

        <div class="flex gap-2 mb-4 bg-gray-100 rounded-2xl p-1">
            <button @click="tab = 'tasmi'" class="flex-1 py-2 rounded-xl text-sm font-bold relative" :class="tab === 'tasmi' ? 'bg-white text-emerald-600 shadow-sm' : 'text-gray-400'">
                Tasmi' <span class="text-[10px]">(Tahfidz)</span>
                <span v-if="tasmiList.length" class="absolute -top-1 -right-0.5 min-w-4 h-4 px-1 rounded-full bg-emerald-500 text-white text-[9px] grid place-items-center">{{ tasmiList.length }}</span>
            </button>
            <button @click="tab = 'tasnif'" class="flex-1 py-2 rounded-xl text-sm font-bold relative" :class="tab === 'tasnif' ? 'bg-white text-emerald-600 shadow-sm' : 'text-gray-400'">
                Tasnif <span class="text-[10px]">(Tahsin)</span>
                <span v-if="tasnifList.length" class="absolute -top-1 -right-0.5 min-w-4 h-4 px-1 rounded-full bg-emerald-500 text-white text-[9px] grid place-items-center">{{ tasnifList.length }}</span>
            </button>
        </div>

        <div v-if="loading" class="pt-10 flex justify-center"><div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div></div>
        <div v-else-if="error" class="pt-8 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button @click="load" class="mt-3 px-4 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold">Coba lagi</button>
        </div>

        <template v-else>
            <!-- ══ TASMI' ══ -->
            <template v-if="tab === 'tasmi'">
                <p class="text-xs text-gray-400 mb-3">Santri ditunjuk untuk Anda uji tasmi' (per juz). Lulus bila rata-rata ≥ {{ AMBANG }}.</p>
                <div v-if="!tasmiList.length" class="pt-14 text-center text-sm text-gray-400">Belum ada tugas tasmi'.</div>
                <ul v-else class="space-y-3">
                    <li v-for="t in tasmiList" :key="t.id" class="rounded-2xl bg-white border border-gray-100 p-4 flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 grid place-items-center shrink-0"><span class="text-emerald-600 font-extrabold text-sm">Juz</span></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-gray-800 truncate">{{ t.santri }}</p>
                            <p class="text-[11px] text-gray-400">Juz {{ t.juz }} · pengampu {{ t.pengampu }}</p>
                        </div>
                        <button @click="buka('tasmi', t)" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold active:scale-95 transition">Nilai</button>
                    </li>
                </ul>
            </template>

            <!-- ══ TASNIF ══ -->
            <template v-else>
                <p class="text-xs text-gray-400 mb-3">Ujian kenaikan level Tahsin. Rubrik: Pemahaman Materi, Kelancaran, Fashohah, Makhorijul Huruf. Lulus (≥{{ AMBANG }}) → santri naik level.</p>
                <div v-if="!tasnifList.length" class="pt-14 text-center text-sm text-gray-400">Belum ada tugas tasnif.</div>
                <ul v-else class="space-y-3">
                    <li v-for="t in tasnifList" :key="t.id" class="rounded-2xl bg-white border border-gray-100 p-4 flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-violet-50 grid place-items-center shrink-0"><span class="text-violet-600 font-extrabold text-[10px] text-center leading-tight">Lv {{ t.level }}</span></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-gray-800 truncate">{{ t.santri }}</p>
                            <p class="text-[11px] text-gray-400">{{ t.level_label }} · pengampu {{ t.pengampu }}</p>
                        </div>
                        <button @click="buka('tasnif', t)" class="px-4 py-2 rounded-xl bg-violet-600 text-white text-xs font-bold active:scale-95 transition">Uji</button>
                    </li>
                </ul>
            </template>
        </template>

        <!-- Sheet penilaian (dinamis tasmi/tasnif) -->
        <BottomSheet :model-value="!!aktif" @update:model-value="aktif = null"
            :title="aktif?.item?.santri"
            :subtitle="aktif ? (aktif.jenis === 'tasmi' ? `Tasmi' Juz ${aktif.item.juz}` : `Tasnif ${aktif.item.level_label}`) : ''">
            <template v-if="aktif">
                <p class="text-[11px] font-bold text-gray-400 mb-2">RUBRIK PENILAIAN (1–10)</p>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div v-for="[k, label] in rubrikAktif" :key="k">
                        <label class="block text-[11px] font-medium text-gray-600 mb-1">{{ label }} <span class="text-red-500">*</span></label>
                        <input v-model="f[k]" type="number" min="1" max="10" step="0.5" inputmode="decimal" placeholder="1–10"
                            class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-emerald-500" />
                    </div>
                </div>

                <div class="rounded-2xl p-3.5 mb-4 flex items-center justify-between transition-colors"
                    :class="rata == null ? 'bg-gray-50' : lulus ? 'bg-emerald-50 border border-emerald-100' : 'bg-red-50 border border-red-100'">
                    <div>
                        <p class="text-[11px] text-gray-500">Rata-rata (nilai akhir)</p>
                        <p class="text-2xl font-extrabold" :class="rata == null ? 'text-gray-300' : lulus ? 'text-emerald-600' : 'text-red-500'">{{ rata == null ? '—' : rata }}</p>
                    </div>
                    <span v-if="rata != null" class="text-xs font-extrabold px-3 py-1.5 rounded-full" :class="lulus ? 'bg-emerald-600 text-white' : 'bg-red-500 text-white'">
                        {{ lulus ? (aktif.jenis === 'tasnif' ? 'LULUS · NAIK LEVEL' : 'LULUS') : `Belum lulus (min ${AMBANG})` }}
                    </span>
                    <span v-else class="text-[11px] text-gray-400">Isi 4 rubrik</span>
                </div>

                <label class="block text-[11px] font-medium text-gray-600 mb-1">Catatan <span class="text-red-500">*</span></label>
                <textarea v-model="f.catatan" rows="2" placeholder="mis. lancar, perlu perbaikan tajwid…" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:border-emerald-500 mb-4"></textarea>

                <div class="flex gap-3">
                    <button @click="aktif = null" class="flex-1 py-3 rounded-xl bg-gray-100 text-gray-600 font-semibold text-sm">Batal</button>
                    <button @click="kirim" :disabled="saving" class="flex-1 py-3 rounded-xl text-white font-bold text-sm shadow-lg disabled:opacity-60"
                        :class="aktif.jenis === 'tasnif' ? 'bg-gradient-to-r from-violet-500 to-violet-600 shadow-violet-600/20' : 'bg-gradient-to-r from-emerald-500 to-emerald-600 shadow-emerald-600/20'">
                        {{ saving ? 'Menyimpan…' : (aktif.jenis === 'tasnif' ? 'Simpan & Naik Level' : "Simpan Nilai Tasmi'") }}
                    </button>
                </div>
            </template>
        </BottomSheet>
    </div>
</template>

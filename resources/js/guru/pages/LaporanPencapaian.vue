<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api'
import PageHeader from '../components/PageHeader.vue'

const route = useRoute()
const router = useRouter()
const jenis = computed(() => (route.params.jenis === 'tahsin' ? 'tahsin' : 'tahfidz'))
const isTahfidz = computed(() => jenis.value === 'tahfidz')

const data = ref(null)
const loading = ref(true)
const error = ref('')
const kelasId = ref('')
const detail = ref(null)     // mode 'anak'
const loadingDetail = ref(false)

async function loadKelas() {
    loading.value = true; error.value = ''; detail.value = null
    try {
        const params = kelasId.value ? { kelas_id: kelasId.value } : {}
        const res = await api.get(`/education/laporan/${jenis.value}`, { params })
        data.value = res.data.data ?? res.data
    } catch (e) {
        error.value = e.response?.data?.message || 'Gagal memuat laporan.'
    } finally { loading.value = false }
}
onMounted(loadKelas)
watch(jenis, () => { kelasId.value = ''; loadKelas() })

async function bukaDetail(s) {
    loadingDetail.value = true
    try {
        const res = await api.get(`/education/laporan/${jenis.value}`, { params: { kelas_id: kelasId.value, santri_id: s.santri_id } })
        const d = res.data.data ?? res.data
        detail.value = d.detail
    } catch (_) {/* diamkan */} finally { loadingDetail.value = false }
}

const juzColor = (st) => ({ selesai: 'bg-emerald-500 text-white', tasmi_lulus: 'bg-emerald-600 text-white', proses: 'bg-amber-400 text-white' }[st] || 'bg-gray-100 text-gray-400')
const lvColor = (st) => ({ lewat: 'bg-emerald-500', berjalan: 'bg-[#0C78FF]', belum: 'bg-gray-200' }[st] || 'bg-gray-200')
</script>

<template>
    <div>
        <PageHeader :title="isTahfidz ? 'Pencapaian Tahfidz' : 'Pencapaian Tahsin'" />

        <!-- Kelas -->
        <div class="rounded-2xl bg-white border border-gray-100 p-4 mb-4">
            <label class="block text-[11px] font-medium text-gray-600 mb-1">Kelas</label>
            <select v-model="kelasId" @change="loadKelas" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm outline-none">
                <option value="">— pilih kelas —</option>
                <option v-for="k in (data?.kelas_opsi || [])" :key="k.id" :value="k.id">{{ k.nama }}</option>
            </select>
        </div>

        <div v-if="loading" class="pt-6 flex justify-center">
            <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div v-else-if="error" class="pt-8 text-center">
            <p class="text-sm text-gray-500">{{ error }}</p>
            <button @click="loadKelas" class="mt-3 px-4 py-2 rounded-xl bg-[#0C78FF] text-white text-sm font-semibold">Coba lagi</button>
        </div>

        <template v-else>
            <div v-if="!data.kelas" class="pt-10 text-center text-sm text-gray-400">Pilih kelas untuk melihat pencapaian santri.</div>
            <div v-else-if="!data.rows?.length" class="pt-10 text-center text-sm text-gray-400">Belum ada data pencapaian.</div>

            <ul v-else class="space-y-2.5">
                <li v-for="r in data.rows" :key="r.santri_id" @click="bukaDetail(r)"
                    class="rounded-2xl bg-white border border-gray-100 p-3.5 active:scale-[0.99] transition">
                    <div class="flex items-center gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-gray-800 truncate">{{ r.nama }}</p>
                            <!-- Tahfidz -->
                            <p v-if="isTahfidz" class="text-[11px] text-gray-400">
                                {{ r.total_ayat }} ayat · {{ r.persen }}% · {{ r.juz_selesai }} juz
                                <span v-if="r.rata_nilai != null"> · rata {{ r.rata_nilai }}</span>
                            </p>
                            <!-- Tahsin -->
                            <p v-else class="text-[11px] text-gray-400">
                                Level {{ r.level }} · {{ r.materi_lulus }}/{{ r.materi_total }} materi
                                <span v-if="r.level_selesai" class="text-emerald-600 font-bold"> · Lengkap</span>
                                <span v-if="r.rata_nilai != null"> · rata {{ r.rata_nilai }}</span>
                            </p>
                        </div>
                        <div v-if="isTahfidz" class="w-12 shrink-0">
                            <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full bg-emerald-500" :style="{ width: Math.min(100, r.persen) + '%' }"></div>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </div>
                </li>
            </ul>
        </template>

        <!-- Overlay detail (anak) -->
        <Transition name="pop">
            <div v-if="detail || loadingDetail" class="fixed inset-0 z-[70] bg-[#F7F9FB] overflow-y-auto">
                <div class="sticky top-0 bg-white border-b border-gray-100 px-4 h-14 flex items-center gap-3">
                    <button @click="detail = null" class="w-9 h-9 rounded-full bg-gray-100 grid place-items-center">
                        <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <p class="font-bold text-gray-800 text-sm truncate">{{ detail?.santri?.nama || 'Detail' }}</p>
                </div>

                <div v-if="loadingDetail" class="pt-16 flex justify-center">
                    <div class="w-8 h-8 border-2 border-[#0C78FF] border-t-transparent rounded-full animate-spin"></div>
                </div>

                <div v-else-if="detail" class="p-4 pb-20 space-y-4">
                    <!-- ═══ TAHFIDZ ═══ -->
                    <template v-if="isTahfidz">
                        <div class="grid grid-cols-3 gap-3">
                            <div class="rounded-2xl bg-white border border-gray-100 p-3 text-center">
                                <p class="text-xl font-extrabold text-emerald-600">{{ detail.persen }}%</p><p class="text-[10px] text-gray-400">Hafal</p></div>
                            <div class="rounded-2xl bg-white border border-gray-100 p-3 text-center">
                                <p class="text-xl font-extrabold text-[#0C78FF]">{{ detail.juz_selesai }}</p><p class="text-[10px] text-gray-400">Juz selesai</p></div>
                            <div class="rounded-2xl bg-white border border-gray-100 p-3 text-center">
                                <p class="text-xl font-extrabold text-violet-600">{{ detail.total_ayat }}</p><p class="text-[10px] text-gray-400">Total ayat</p></div>
                        </div>

                        <div class="rounded-2xl bg-white border border-gray-100 p-4">
                            <p class="text-sm font-bold text-gray-800 mb-2">Peta Juz</p>
                            <div class="grid grid-cols-6 gap-1.5">
                                <div v-for="j in detail.juz_grid" :key="j.juz"
                                    class="aspect-square rounded-lg grid place-items-center text-[11px] font-bold" :class="juzColor(j.status)">
                                    {{ j.juz }}
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-white border border-gray-100 p-4">
                            <p class="text-sm font-bold text-gray-800 mb-2">Rekap Setoran</p>
                            <div v-for="c in detail.recap" :key="c.jenis" class="flex justify-between text-xs py-1.5 border-b border-gray-50 last:border-0">
                                <span class="text-gray-600">{{ c.label }}</span>
                                <span class="text-gray-400">{{ c.count }}× · {{ c.ayat }} ayat<span v-if="c.rata != null"> · rata {{ c.rata }}</span></span>
                            </div>
                        </div>

                        <!-- Tasmi' Lulus + breakdown rubrik + sertifikat -->
                        <div v-if="detail.tasmi_lulus?.length" class="rounded-2xl bg-white border border-gray-100 p-4">
                            <p class="text-sm font-bold text-gray-800 mb-2 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Tasmi' Lulus ({{ detail.tasmi_lulus.length }} juz)
                            </p>
                            <div v-for="t in detail.tasmi_lulus" :key="t.id" class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-3 mb-2 last:mb-0">
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <p class="text-sm font-extrabold text-emerald-700">Juz {{ t.juz }}</p>
                                        <p class="text-[10px] text-gray-400">{{ t.tanggal }} · penguji {{ t.penguji }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-extrabold text-emerald-600 leading-none">{{ t.nilai }}</p>
                                        <p class="text-[9px] text-gray-400">rata-rata</p>
                                    </div>
                                </div>
                                <div v-if="t.rubrik_ada" class="grid grid-cols-4 gap-1.5 mb-2">
                                    <div v-for="(v, k) in { Kelancaran: t.rubrik.kelancaran, 'Makhraj': t.rubrik.makhorijul_huruf, Tajwid: t.rubrik.tajwid, Fashohah: t.rubrik.fashohah }" :key="k"
                                        class="rounded-lg bg-white border border-emerald-100 py-1.5 text-center">
                                        <p class="text-[8px] text-gray-400 leading-tight">{{ k }}</p>
                                        <p class="text-[13px] font-extrabold text-gray-700">{{ v ?? '–' }}</p>
                                    </div>
                                </div>
                                <p v-else class="text-[10px] text-gray-400 italic mb-2">Dinilai sebelum sistem 4 rubrik.</p>
                                <button @click="router.push({ name: 'sertifikat-tasmi', params: { id: t.id } })"
                                    class="w-full py-2 rounded-lg bg-gradient-to-r from-emerald-500 to-emerald-600 text-white text-xs font-bold flex items-center justify-center gap-1.5 active:scale-[0.98] transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Lihat Sertifikat
                                </button>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-white border border-gray-100 p-4">
                            <p class="text-sm font-bold text-gray-800 mb-2">Riwayat</p>
                            <div v-if="!detail.riwayat?.length" class="text-xs text-gray-400 py-2">Belum ada riwayat.</div>
                            <div v-for="(r, i) in detail.riwayat" :key="i" class="py-2 border-b border-gray-50 last:border-0">
                                <div class="flex justify-between">
                                    <span class="text-xs font-semibold text-gray-700">{{ r.label }}</span>
                                    <span class="text-[11px] text-gray-400">{{ r.tanggal }}</span>
                                </div>
                                <p class="text-[11px] text-gray-500">{{ r.rentang }}<span v-if="r.nilai != null"> · nilai {{ r.nilai }}</span></p>
                                <p v-if="r.catatan" class="text-[11px] text-gray-400 italic">{{ r.catatan }}</p>
                            </div>
                        </div>
                    </template>

                    <!-- ═══ TAHSIN ═══ -->
                    <template v-else>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="rounded-2xl bg-white border border-gray-100 p-3 text-center">
                                <p class="text-xl font-extrabold text-violet-600">{{ detail.santri?.level }}</p><p class="text-[10px] text-gray-400">Level</p></div>
                            <div class="rounded-2xl bg-white border border-gray-100 p-3 text-center">
                                <p class="text-xl font-extrabold text-emerald-600">{{ detail.rekap?.lulus }}</p><p class="text-[10px] text-gray-400">Lulus</p></div>
                            <div class="rounded-2xl bg-white border border-gray-100 p-3 text-center">
                                <p class="text-xl font-extrabold text-[#0C78FF]">{{ detail.rekap?.rata ?? '–' }}</p><p class="text-[10px] text-gray-400">Rata nilai</p></div>
                        </div>

                        <div class="rounded-2xl bg-white border border-gray-100 p-4">
                            <p class="text-sm font-bold text-gray-800 mb-3">Progres Level</p>
                            <div v-for="lv in detail.level_grid" :key="lv.level" class="mb-3 last:mb-0">
                                <div class="flex justify-between text-[11px] mb-1">
                                    <span class="text-gray-600 font-medium">{{ lv.label }}</span>
                                    <span class="text-gray-400">{{ lv.lulus }}/{{ lv.total }}<span v-if="lv.rata != null"> · rata {{ lv.rata }}</span></span>
                                </div>
                                <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full rounded-full" :class="lvColor(lv.status)" :style="{ width: (lv.total ? Math.min(100, lv.lulus / lv.total * 100) : 0) + '%' }"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Tasnif Lulus (ujian kenaikan level) + breakdown rubrik + sertifikat -->
                        <div v-if="detail.tasnif_lulus?.length" class="rounded-2xl bg-white border border-gray-100 p-4">
                            <p class="text-sm font-bold text-gray-800 mb-2 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Tasnif Lulus ({{ detail.tasnif_lulus.length }} level)
                            </p>
                            <div v-for="t in detail.tasnif_lulus" :key="t.id" class="rounded-xl border border-violet-100 bg-violet-50/40 p-3 mb-2 last:mb-0">
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <p class="text-sm font-extrabold text-violet-700">{{ t.level_label }}</p>
                                        <p class="text-[10px] text-gray-400">{{ t.tanggal }} · penguji {{ t.penguji }}</p>
                                    </div>
                                    <div class="text-right"><p class="text-lg font-extrabold text-violet-600 leading-none">{{ t.nilai }}</p><p class="text-[9px] text-gray-400">rata-rata</p></div>
                                </div>
                                <div class="grid grid-cols-4 gap-1.5 mb-2">
                                    <div v-for="(v, k) in { 'Pemahaman': t.rubrik.pemahaman_materi, 'Kelancaran': t.rubrik.kelancaran, 'Fashohah': t.rubrik.fashohah, 'Makhraj': t.rubrik.makhorijul_huruf }" :key="k"
                                        class="rounded-lg bg-white border border-violet-100 py-1.5 text-center">
                                        <p class="text-[8px] text-gray-400 leading-tight">{{ k }}</p>
                                        <p class="text-[13px] font-extrabold text-gray-700">{{ v ?? '–' }}</p>
                                    </div>
                                </div>
                                <button @click="router.push({ name: 'sertifikat-tasnif', params: { id: t.id } })"
                                    class="w-full py-2 rounded-lg bg-gradient-to-r from-violet-500 to-violet-600 text-white text-xs font-bold flex items-center justify-center gap-1.5 active:scale-[0.98] transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Lihat Sertifikat
                                </button>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-white border border-gray-100 p-4">
                            <p class="text-sm font-bold text-gray-800 mb-2">Riwayat Penilaian</p>
                            <div v-if="!detail.riwayat?.length" class="text-xs text-gray-400 py-2">Belum ada riwayat.</div>
                            <div v-for="(r, i) in detail.riwayat" :key="i" class="py-2 border-b border-gray-50 last:border-0">
                                <div class="flex justify-between">
                                    <span class="text-xs font-semibold text-gray-700 truncate">{{ r.materi }}</span>
                                    <span class="text-[11px]" :class="r.lulus ? 'text-emerald-600 font-bold' : 'text-gray-400'">{{ r.nilai }}{{ r.lulus ? ' ✓' : '' }}</span>
                                </div>
                                <p class="text-[11px] text-gray-400">{{ r.tanggal }}<span v-if="r.catatan"> · {{ r.catatan }}</span></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.pop-enter-active, .pop-leave-active { transition: opacity .2s ease; }
.pop-enter-from, .pop-leave-to { opacity: 0; }
</style>

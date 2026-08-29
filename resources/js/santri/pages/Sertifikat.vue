<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api'
import { toast } from '../store/toast'

const route = useRoute()
const router = useRouter()
const jenis = route.params.jenis === 'tasnif' ? 'tasnif' : 'tasmi'
const id = route.params.id
const loading = ref(true)
const c = ref(null)

const url = jenis === 'tasmi' ? `/tahfidz/tasmi/${id}/sertifikat` : `/tahsin/tasnif/${id}/sertifikat`
const judulUjian = computed(() => jenis === 'tasmi' ? 'Tasmi\' Al-Qur\'an' : 'Ujian Kenaikan Tingkat (Tasnif)')
const capaian = computed(() => jenis === 'tasmi' ? `Juz ${c.value?.juz}` : c.value?.level_label)

async function load() {
    try { c.value = (await api.get(url)).data.data }
    catch (e) { toast.error(e.response?.data?.message || 'Sertifikat tidak tersedia.'); router.back() }
    finally { loading.value = false }
}
onMounted(load)
function cetak() { window.print() }
</script>

<template>
    <div class="cert-root min-h-screen bg-[#EEF1F4]">
        <div class="no-print sticky top-0 z-10 flex items-center justify-between px-4 h-14 bg-white/90 backdrop-blur border-b border-gray-100">
            <button @click="router.back()" class="w-9 h-9 rounded-xl bg-gray-100 grid place-items-center text-gray-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg></button>
            <span class="text-sm font-bold text-gray-700">Sertifikat</span>
            <button v-if="c" @click="cetak" class="px-3.5 h-9 rounded-xl bg-[#0F766E] text-white text-xs font-bold">Cetak / PDF</button>
        </div>

        <div v-if="loading" class="pt-24 flex justify-center"><div class="w-8 h-8 border-2 border-[#0F766E] border-t-transparent rounded-full animate-spin"></div></div>

        <div v-else-if="c" class="cert-wrap p-4 flex justify-center">
            <div class="cert">
                <div class="cert-border">
                    <span class="corner tl"></span><span class="corner tr"></span><span class="corner bl"></span><span class="corner br"></span>
                    <div class="cert-inner">
                        <p class="bismillah">﷽</p>
                        <h2 class="lembaga">{{ c.lembaga }}</h2>
                        <p class="program">Program {{ c.program }}</p>
                        <p class="alamat">{{ c.alamat }}</p>
                        <div class="rule"><span></span><i>❁</i><span></span></div>
                        <h1 class="judul">SERTIFIKAT</h1>
                        <p class="sub">Kelulusan {{ judulUjian }}</p>
                        <p class="nomor">Nomor: {{ c.nomor }}</p>
                        <p class="diberikan">Dengan bangga diberikan kepada</p>
                        <p class="nama">{{ c.santri.nama }}</p>
                        <p v-if="c.santri.nip" class="nip">NIS. {{ c.santri.nip }}</p>
                        <p class="narasi">Telah dinyatakan <b>LULUS</b> dalam {{ judulUjian }} <b>{{ capaian }}</b> dengan predikat <b class="predikat">{{ c.predikat }}</b>.</p>
                        <div class="rubrik">
                            <div v-for="r in c.rubrik" :key="r.label" class="rub-box"><p class="rub-label">{{ r.label }}</p><p class="rub-nilai">{{ r.nilai ?? '–' }}</p></div>
                            <div class="rub-box rub-avg"><p class="rub-label">Nilai Akhir</p><p class="rub-nilai">{{ c.nilai }}</p></div>
                        </div>
                        <p v-if="c.catatan" class="catatan">“{{ c.catatan }}”</p>
                        <div class="ttd">
                            <div class="ttd-col"><p class="ttd-role">Pengampu</p><div class="ttd-space"></div><p class="ttd-nama">{{ c.pengampu }}</p></div>
                            <div class="ttd-seal"><div class="seal"><span class="seal-in">لُلُوْس<br><small>{{ jenis === 'tasmi' ? 'TASMI\'' : 'TASNIF' }}</small></span></div><p class="ttd-tanggal">Sidoarjo, {{ c.tanggal }}</p></div>
                            <div class="ttd-col"><p class="ttd-role">Penguji</p><div class="ttd-space"></div><p class="ttd-nama">{{ c.penguji }}</p></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.cert-wrap { --gold: #b8912f; --emer: #0f766e; --ink: #23303a; }
.cert { width: 100%; max-width: 960px; aspect-ratio: 1.414 / 1; background: radial-gradient(circle at 15% 10%, #fff 0%, #fbf7ec 55%, #f6efdd 100%); box-shadow: 0 18px 50px rgba(30,40,60,.18); color: var(--ink); font-family: Georgia, 'Times New Roman', serif; container-type: inline-size; }
.cert-border { height: 100%; margin: 2.2%; border: 2.5px solid var(--gold); outline: 6px solid var(--gold); outline-offset: 5px; position: relative; display: flex; }
.corner { position: absolute; width: 26px; height: 26px; border: 3px solid var(--emer); }
.corner.tl { top:-3px; left:-3px; border-right:0; border-bottom:0; } .corner.tr { top:-3px; right:-3px; border-left:0; border-bottom:0; }
.corner.bl { bottom:-3px; left:-3px; border-right:0; border-top:0; } .corner.br { bottom:-3px; right:-3px; border-left:0; border-top:0; }
.cert-inner { flex:1; padding:4.5% 6% 3.5%; text-align:center; display:flex; flex-direction:column; align-items:center; }
.bismillah { font-size:6cqw; color:var(--gold); line-height:1; margin-bottom:1.5%; }
.lembaga { font-size:3.4cqw; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
.program { font-size:2.1cqw; color:var(--emer); font-weight:600; }
.alamat { font-size:1.5cqw; color:#8a7f6a; font-family:system-ui,sans-serif; }
.rule { display:flex; align-items:center; gap:10px; width:55%; margin:2.5% 0 1.5%; }
.rule span { flex:1; height:2px; background:linear-gradient(90deg,transparent,var(--gold)); } .rule span:last-child { background:linear-gradient(90deg,var(--gold),transparent); }
.rule i { color:var(--gold); font-style:normal; font-size:2.4cqw; }
.judul { font-size:6.6cqw; font-weight:700; letter-spacing:.22em; color:var(--emer); text-indent:.22em; }
.sub { font-size:2.1cqw; color:var(--gold); font-style:italic; }
.nomor { font-size:1.5cqw; color:#8a7f6a; margin-top:.8%; font-family:system-ui,sans-serif; }
.diberikan { font-size:1.9cqw; color:#6b6151; margin-top:2.6%; }
.nama { font-size:5.2cqw; font-weight:700; margin-top:.3%; border-bottom:2px dotted var(--gold); padding:0 4% 1%; line-height:1.1; }
.nip { font-size:1.6cqw; color:#8a7f6a; margin-top:1%; font-family:system-ui,sans-serif; }
.narasi { font-size:2.1cqw; line-height:1.6; max-width:82%; margin-top:2.4%; } .narasi .predikat { color:var(--emer); }
.rubrik { display:flex; gap:2%; margin-top:2.6%; width:92%; justify-content:center; }
.rub-box { flex:1; background:#fff; border:1.5px solid #e7dcc0; border-radius:10px; padding:1.4% 0; }
.rub-label { font-size:1.25cqw; color:#8a7f6a; font-family:system-ui,sans-serif; }
.rub-nilai { font-size:3.2cqw; font-weight:700; font-family:system-ui,sans-serif; }
.rub-avg { background:var(--emer); border-color:var(--emer); } .rub-avg .rub-label { color:#d6efe9; } .rub-avg .rub-nilai { color:#fff; }
.catatan { font-size:1.6cqw; color:#6b6151; font-style:italic; margin-top:1.8%; max-width:80%; }
.ttd { margin-top:auto; padding-top:3%; display:flex; align-items:flex-end; justify-content:space-between; width:100%; }
.ttd-col { width:30%; } .ttd-role { font-size:1.6cqw; color:#6b6151; font-family:system-ui,sans-serif; } .ttd-space { height:4cqw; }
.ttd-nama { font-size:1.9cqw; font-weight:700; border-top:1.5px solid var(--ink); padding-top:4%; }
.ttd-seal { display:flex; flex-direction:column; align-items:center; gap:6px; }
.seal { width:11cqw; height:11cqw; border-radius:50%; border:3px double var(--gold); color:var(--emer); display:grid; place-items:center; text-align:center; background:radial-gradient(circle,#fff,#fbf3dd); transform:rotate(-8deg); }
.seal-in { font-size:2.4cqw; font-weight:700; line-height:1; } .seal-in small { font-size:1.1cqw; letter-spacing:.1em; color:var(--gold); font-family:system-ui,sans-serif; }
.ttd-tanggal { font-size:1.5cqw; color:#6b6151; font-family:system-ui,sans-serif; }
@media print { .no-print { display:none !important; } .cert-root,.cert-wrap { background:#fff !important; padding:0 !important; } .cert { box-shadow:none; max-width:100%; } @page { size:A4 landscape; margin:8mm; } }
:global(html),:global(body) { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
</style>

<template>
    <!-- DOKUMEN SLIP GAJI — gaya invoice modern -->
    <div id="report" class="slip-doc bg-white max-w-4xl mx-auto text-[#1b1b1b]
        rounded-xl overflow-hidden shadow-lg border border-[#e2e2e2]
        print:shadow-none print:border-0 print:rounded-none print:max-w-full">

        <!-- HEADER -->
        <div class="px-8 md:px-10 py-8 flex flex-col md:flex-row justify-between md:items-center gap-6
            border-b border-[#e2e2e2] bg-[#f3f3f3]">
            <div class="flex items-start gap-4">
                <img v-if="logo" :src="logo" class="w-12 h-12 object-contain rounded-lg shrink-0" />
                <div v-else class="w-12 h-12 rounded-lg bg-[#0041c8] flex items-center justify-center text-white font-bold shrink-0">AN</div>
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold text-[#0041c8] leading-none tracking-tight">SLIP GAJI</h1>
                    <p class="mono-label mt-2">No. Slip: #{{ slip.id }}</p>
                </div>
            </div>
            <div class="flex gap-8">
                <div>
                    <span class="mono-label block mb-1">PERIODE</span>
                    <span class="text-base font-bold text-[#1b1b1b]">{{ periode.label }}</span>
                </div>
                <div>
                    <span class="mono-label block mb-1">TANGGAL CETAK</span>
                    <span class="text-base font-bold text-[#0041c8]">{{ tglCetak }}</span>
                </div>
            </div>
        </div>

        <!-- ISSUER / PENERIMA -->
        <div class="grid grid-cols-1 md:grid-cols-2 px-8 md:px-10 py-8 gap-10 border-b border-[#e2e2e2]">
            <div>
                <span class="mono-label px-2 py-1 rounded bg-[#dce1ff] text-[#0041c8] inline-block mb-3">PEMBERI KERJA</span>
                <h2 class="text-lg font-bold text-[#1b1b1b] mb-2">{{ instansi.nama }}</h2>
                <div class="text-sm text-[#434656] space-y-0.5 leading-relaxed">
                    <p>{{ instansi.alamat }}</p>
                    <p v-if="instansi.perihal" class="italic">{{ instansi.perihal }}</p>
                </div>
            </div>
            <div>
                <span class="mono-label px-2 py-1 rounded bg-[#ffe16d] text-[#705d00] inline-block mb-3">PENERIMA</span>
                <h2 class="text-lg font-bold text-[#1b1b1b] mb-2 uppercase">{{ guru.nama }}</h2>
                <div class="text-sm text-[#434656] space-y-0.5 leading-relaxed">
                    <p v-if="guru.jabatan">{{ guru.jabatan }}</p>
                    <p class="mt-2 pt-2 border-t border-[#e2e2e2] inline-block text-[#1b1b1b]">NIP: {{ guru.nip || '—' }}</p>
                </div>
            </div>
        </div>

        <!-- RINCIAN PENERIMAAN -->
        <div class="px-8 md:px-10 pt-8">
            <div class="w-full overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[520px]">
                    <thead>
                        <tr class="border-b-2 border-[#e2e2e2] text-[#434656]">
                            <th class="py-3 pr-4 mono-label w-10 text-center">#</th>
                            <th class="py-3 px-4 mono-label">KOMPONEN PENERIMAAN</th>
                            <th class="py-3 px-4 mono-label text-right w-28">JUMLAH</th>
                            <th class="py-3 pl-4 mono-label text-right w-44">NOMINAL</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <tr v-for="(r, i) in slip.penerimaan" :key="'p' + i" class="border-b border-[#e2e2e2]">
                            <td class="py-4 pr-4 text-center mono-label text-[#434656]">{{ String(i + 1).padStart(2, '0') }}</td>
                            <td class="py-4 px-4 font-semibold text-[#1b1b1b]">{{ r.label }}</td>
                            <td class="py-4 px-4 text-right text-[#434656]">{{ r.qty || '—' }}</td>
                            <td class="py-4 pl-4 text-right font-bold text-[#1b1b1b] tabular-nums whitespace-nowrap">{{ rupiah(r.nominal) }}</td>
                        </tr>
                        <tr v-if="!slip.penerimaan?.length">
                            <td colspan="4" class="py-6 text-center text-[#434656] italic text-sm">Tidak ada komponen penerimaan.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RINGKASAN: POTONGAN + TOTAL -->
        <div class="flex flex-col md:flex-row px-8 md:px-10 py-8 gap-10">
            <!-- Potongan + TTD -->
            <div class="flex-1 order-2 md:order-1">
                <h3 class="mono-label mb-3">POTONGAN</h3>
                <div class="rounded-lg border border-[#e2e2e2] bg-[#f3f3f3] divide-y divide-[#e2e2e2]">
                    <div v-for="(r, i) in slip.potongan" :key="'d' + i" class="flex justify-between items-baseline px-4 py-2.5 text-sm">
                        <span class="text-[#434656]">{{ r.label }}<span v-if="r.qty" class="text-[11px]"> · {{ r.qty }}</span></span>
                        <span class="tabular-nums font-semibold text-[#9c1c00] whitespace-nowrap">{{ rupiah(r.nominal) }}</span>
                    </div>
                    <div v-if="!slip.potongan?.length" class="px-4 py-3 text-sm text-[#434656] italic">Tidak ada potongan.</div>
                </div>

                <!-- Tanda tangan -->
                <div class="mt-8 text-sm">
                    <p class="text-[#434656]">Sidoarjo, {{ tglCetak }}</p>
                    <p class="text-[#1b1b1b] mt-0.5">Bendahara,</p>
                    <div class="h-14"></div>
                    <p class="font-bold underline decoration-1 underline-offset-2 uppercase">{{ instansi.bendahara }}</p>
                    <p v-if="slip.ada_koreksi" class="mt-3 text-[11px] text-[#9c1c00] italic no-print">* terdapat koreksi manual</p>
                </div>
            </div>

            <!-- Totals -->
            <div class="w-full md:w-[340px] order-1 md:order-2 flex flex-col gap-3">
                <div class="flex justify-between items-center py-2 border-b border-[#e2e2e2]">
                    <span class="text-sm text-[#434656]">Total Penerimaan</span>
                    <span class="font-bold text-base tabular-nums">{{ rupiah(slip.total_pendapatan) }}</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-[#e2e2e2]">
                    <span class="text-sm text-[#434656]">Total Potongan</span>
                    <span class="font-bold text-base tabular-nums text-[#9c1c00]">− {{ rupiah(slip.total_potongan) }}</span>
                </div>
                <div class="flex justify-between items-center gap-3 mt-2 px-5 py-4 rounded-lg bg-[#dce1ff]">
                    <span class="text-sm font-bold text-[#001551] uppercase tracking-wide">Gaji Diterima</span>
                    <span class="text-2xl font-bold text-[#0041c8] tabular-nums whitespace-nowrap">{{ rupiah(slip.gaji_bersih) }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    instansi: { type: Object, default: () => ({}) },
    guru: { type: Object, default: () => ({}) },
    periode: { type: Object, default: () => ({}) },
    slip: { type: Object, default: () => ({}) },
    logo: { type: String, default: null },
})

const tglCetak = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })

function rupiah(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID')
}
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap');
</style>

<style scoped>
.slip-doc {
    font-family: 'Space Grotesk', ui-sans-serif, system-ui, sans-serif;
}
.mono-label {
    font-family: 'Space Grotesk', ui-monospace, monospace;
    font-size: 11px;
    line-height: 1;
    letter-spacing: 0.05em;
    font-weight: 500;
    text-transform: uppercase;
    color: #434656;
}

@media print {
    #report {
        max-width: 100% !important;
        margin: 0 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>

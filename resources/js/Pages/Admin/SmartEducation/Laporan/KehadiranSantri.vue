<template>
    <AdminLayout title="Kehadiran Santri" subtitle="Smart Education">

        <Head title="Laporan Kehadiran Santri" />

        <div class="print:hidden">
            <div class="flex flex-wrap gap-2 mb-5">
                <Link :href="route('admin.smart-education.laporan.index')"
                    class="px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50">Jurnal Pembelajaran</Link>
                <span class="px-4 py-2 rounded-xl text-sm font-semibold bg-indigo-600 text-white shadow-sm shadow-indigo-200">Kehadiran Santri</span>
                <Link :href="route('admin.smart-education.laporan.mengajar-quran')"
                    class="px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50">Mengajar Tahfidz & Tahsin</Link>
                <Link :href="route('admin.smart-education.laporan.tahfidz')"
                    class="px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50">Tahfidz</Link>
                <Link :href="route('admin.smart-education.laporan.tahsin')"
                    class="px-4 py-2 rounded-xl text-sm font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50">Tahsin</Link>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-3 flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Kop Sekolah</label>
                    <select v-model="kopKey" :class="fieldCls">
                        <option v-for="k in kopOpsi" :key="k.key" :value="k.key">{{ k.nama }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Bulan</label>
                    <select v-model.number="f.bulan" :class="fieldCls">
                        <option v-for="b in bulanOpsi" :key="b.value" :value="b.value">{{ b.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Tahun</label>
                    <select v-model.number="f.tahun" :class="fieldCls">
                        <option v-for="t in tahunOpsi" :key="t" :value="t">{{ t }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Kelas (opsional)</label>
                    <select v-model="f.kelas_id" @change="f.santri_id = null" :class="fieldCls">
                        <option :value="null">Semua kelas (rekap)</option>
                        <option v-for="k in kelasOpsi" :key="k.id" :value="k.id">{{ k.nama }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Per Santri (opsional)</label>
                    <select v-model="f.santri_id" :class="fieldCls" :disabled="!f.kelas_id">
                        <option :value="null">Semua santri kelas</option>
                        <option v-for="s in santriOpsi" :key="s.id" :value="s.id">{{ s.nama_lengkap }}</option>
                    </select>
                </div>
                <button @click="terapkan"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl">Tampilkan</button>

                <div class="ml-auto flex items-end gap-2">
                    <button @click="ttdBuka = !ttdBuka"
                        class="px-3 py-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 text-sm font-semibold rounded-xl">Tanda Tangan</button>
                    <button @click="cetak"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                        Cetak / PDF
                    </button>
                </div>
            </div>

            <div v-if="ttdBuka" class="bg-white rounded-2xl border border-gray-200 p-4 mb-3 grid grid-cols-2 md:grid-cols-4 gap-3">
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Kota</label><input v-model="ttd.kota" :class="fieldCls + ' w-full'" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Jabatan Kiri</label><input v-model="ttd.kiriJab" :class="fieldCls + ' w-full'" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Nama Kiri</label><input v-model="ttd.kiriNama" :class="fieldCls + ' w-full'" placeholder="Nama Kepala Sekolah" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">NIP Kiri</label><input v-model="ttd.kiriNip" :class="fieldCls + ' w-full'" /></div>
                <div class="col-start-1"><label class="block text-xs font-medium text-gray-500 mb-1">Jabatan Kanan</label><input v-model="ttd.kananJab" :class="fieldCls + ' w-full'" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Nama Kanan</label><input v-model="ttd.kananNama" :class="fieldCls + ' w-full'" placeholder="Nama Wali Kelas" /></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">NIP Kanan</label><input v-model="ttd.kananNip" :class="fieldCls + ' w-full'" /></div>
            </div>

            <!-- Penjelasan cara hitung: agar angka di laporan ini tidak dibaca
                 sebagai versi lain dari jurnal, dan agar kepala sekolah tahu
                 persis apa yang masuk penyebut. -->
            <div class="bg-indigo-50/60 border border-indigo-100 rounded-2xl p-4 mb-5 text-xs text-indigo-900/80 leading-relaxed">
                <p class="font-semibold text-indigo-900 mb-1">Cara hitung</p>
                <p>
                    Dasar laporan adalah <b>setiap sesi yang absensi santrinya diisi</b> — oleh guru, guru inval, atau
                    guru piket. Status sesi bagi guru (terlaksana / tidak terlaksana) tidak memengaruhi angka santri.
                    Sesi yang absensinya tidak pernah diisi siapa pun tidak ikut dihitung sehingga tidak membebani santri.
                </p>
                <p class="mt-1">
                    <b>Kehadiran</b> = (hadir + telat) ÷ seluruh sesi yang tercatat untuk santri tersebut.
                    <b>Tanpa Izin/Sakit</b> memakai penyebut yang sama setelah izin dan sakit dikeluarkan — inilah ukuran
                    kedisiplinan, karena santri yang sering sakit tidak sepatutnya disamakan dengan yang membolos.
                </p>
            </div>
        </div>

        <div id="laporan-cetak" class="bg-white rounded-2xl border border-gray-200 p-6 sm:p-9 print:p-0 print:border-0 print:rounded-none">
            <div class="kop-head-wrap">
                <KopSurat :kop="kop" />
            </div>

            <div class="text-center mt-5 mb-5">
                <h2 class="inline-block text-base sm:text-lg font-bold text-gray-900 uppercase tracking-wide border-b-2 border-[#2E3160] pb-1">
                    Laporan Kehadiran Pembelajaran Santri
                </h2>
                <p class="mt-2 text-sm text-gray-700">
                    <template v-if="mode === 'anak'">Santri : <b>{{ detail.santri.nama }}</b><span v-if="detail.santri.nip"> · {{ detail.santri.nip }}</span></template>
                    <template v-else-if="mode === 'kelas'">Kelas : <b>{{ kelas?.nama }}</b></template>
                    <template v-else>Rekapitulasi Seluruh Kelas</template>
                </p>
                <p class="text-xs text-gray-500 mt-0.5">Periode: {{ periodeLabel }}</p>
            </div>

            <!-- Ringkasan -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6 text-center">
                <div class="border border-gray-300 rounded-lg py-2.5">
                    <p class="text-[11px] text-gray-500">Sesi Tercatat</p>
                    <p class="text-lg font-bold text-gray-900 mt-0.5">{{ ringkasan.sesi }}</p>
                </div>
                <div class="border border-gray-300 rounded-lg py-2.5">
                    <p class="text-[11px] text-gray-500">{{ mode === 'anak' ? 'Total Tercatat' : 'Santri Terdata' }}</p>
                    <p class="text-lg font-bold text-gray-900 mt-0.5">{{ mode === 'anak' ? ringkasan.total : ringkasan.santri }}</p>
                </div>
                <div class="border border-gray-300 rounded-lg py-2.5">
                    <p class="text-[11px] text-gray-500">Kehadiran</p>
                    <p class="text-lg font-bold text-indigo-600 mt-0.5">{{ fmt(ringkasan.persen) }}%</p>
                </div>
                <div class="border border-gray-300 rounded-lg py-2.5">
                    <p class="text-[11px] text-gray-500">Tanpa Izin/Sakit</p>
                    <p class="text-lg font-bold text-emerald-600 mt-0.5">{{ fmt(ringkasan.persen_efektif) }}%</p>
                </div>
            </div>

            <div class="flex flex-wrap justify-center gap-2 mb-6 text-xs">
                <span v-for="s in statusRingkas" :key="s.k"
                    class="px-2.5 py-1 rounded-full border font-semibold" :class="s.cls">
                    {{ s.label }}: {{ ringkasan[s.k] }}
                </span>
            </div>

            <!-- ── MODE RINGKAS: seluruh kelas ───────────────────────────── -->
            <div v-if="mode === 'ringkas'" class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead><tr class="bg-[#2E3160] text-white">
                        <th class="border border-gray-200 px-2 py-2 w-10">No</th>
                        <th class="border border-gray-200 px-2 py-2 text-left">Kelas</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">Santri</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">Sesi</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">H</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">T</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">I</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">S</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">A</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">Kehadiran</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">Tanpa I/S</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">Perlu Perhatian</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="(r, i) in perKelas" :key="r.kelas_id" class="hover:bg-gray-50/60">
                            <td class="border border-gray-200 px-2 py-2 text-center">{{ i + 1 }}</td>
                            <td class="border border-gray-200 px-2 py-2">
                                <Link :href="link({ kelas_id: r.kelas_id })" class="font-semibold text-indigo-700 hover:underline print:text-gray-900 print:no-underline">{{ r.kelas }}</Link>
                                <span class="block text-[11px] text-gray-400 capitalize">{{ r.jenis }}</span>
                            </td>
                            <td class="border border-gray-200 px-2 py-2 text-center">{{ r.jumlah_santri }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center">{{ r.sesi }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center">{{ r.hadir }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center">{{ r.telat }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center">{{ r.izin }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center">{{ r.sakit }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center" :class="r.alpha ? 'text-rose-600 font-semibold' : ''">{{ r.alpha }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center font-semibold">{{ fmt(r.persen) }}%</td>
                            <td class="border border-gray-200 px-2 py-2 text-center font-semibold" :class="persenCls(r.persen_efektif)">{{ fmt(r.persen_efektif) }}%</td>
                            <td class="border border-gray-200 px-2 py-2 text-center">
                                <span :class="r.perhatian ? 'text-rose-600 font-bold' : 'text-gray-400'">{{ r.perhatian }}</span>
                                <span class="text-gray-400"> / {{ r.jumlah_santri }}</span>
                            </td>
                        </tr>
                        <tr v-if="!perKelas.length"><td colspan="12" class="border border-gray-200 py-10 text-center text-gray-400">Belum ada absensi santri yang tercatat pada periode ini.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- ── MODE KELAS: daftar santri ─────────────────────────────── -->
            <div v-else-if="mode === 'kelas'" class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead><tr class="bg-[#2E3160] text-white">
                        <th class="border border-gray-200 px-2 py-2 w-10">No</th>
                        <th class="border border-gray-200 px-2 py-2 text-left">Nama &amp; NIP</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">Ikut</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">Sesi</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">H</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">T</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">I</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">S</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">A</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">Kehadiran</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">Tanpa I/S</th>
                        <th class="border border-gray-200 px-2 py-2 text-center">Keterangan</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="(r, i) in perSantri" :key="r.santri_id" class="hover:bg-gray-50/60">
                            <td class="border border-gray-200 px-2 py-2 text-center">{{ i + 1 }}</td>
                            <td class="border border-gray-200 px-2 py-2">
                                <Link :href="link({ kelas_id: filter.kelas_id, santri_id: r.santri_id })"
                                    class="font-semibold text-indigo-700 hover:underline print:text-gray-900 print:no-underline">{{ r.nama }}</Link>
                                <p class="text-[11px] text-gray-500 font-mono">{{ r.nip || '—' }}</p>
                            </td>
                            <td class="border border-gray-200 px-2 py-2 text-center font-semibold">{{ r.ikut }} / {{ r.total }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center">{{ r.sesi }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center">{{ r.hadir }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center">{{ r.telat }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center">{{ r.izin }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center">{{ r.sakit }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center" :class="r.alpha ? 'text-rose-600 font-semibold' : ''">{{ r.alpha }}</td>
                            <td class="border border-gray-200 px-2 py-2 text-center font-semibold">{{ fmt(r.persen) }}%</td>
                            <td class="border border-gray-200 px-2 py-2 text-center font-semibold" :class="persenCls(r.persen_efektif)">{{ fmt(r.persen_efektif) }}%</td>
                            <td class="border border-gray-200 px-2 py-2 text-center">
                                <span class="px-2 py-0.5 rounded-full border text-[11px] font-semibold whitespace-nowrap" :class="katCls(r.kategori)">{{ katLabel(r.kategori) }}</span>
                            </td>
                        </tr>
                        <tr v-if="!perSantri.length"><td colspan="12" class="border border-gray-200 py-10 text-center text-gray-400">Belum ada data absensi pada periode ini.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- ── MODE ANAK: rincian satu santri ────────────────────────── -->
            <div v-else-if="mode === 'anak'">
                <div class="mb-6">
                    <h3 class="text-sm font-bold text-gray-800 mb-2">Rincian per Kelas / Mata Pelajaran</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse">
                            <thead><tr class="bg-[#2E3160] text-white">
                                <th class="border border-gray-200 px-2 py-2 text-left">Kelas</th>
                                <th class="border border-gray-200 px-2 py-2 text-left">Mata Pelajaran</th>
                                <th class="border border-gray-200 px-2 py-2 text-center">Ikut</th>
                                <th class="border border-gray-200 px-2 py-2 text-center">H</th>
                                <th class="border border-gray-200 px-2 py-2 text-center">T</th>
                                <th class="border border-gray-200 px-2 py-2 text-center">I</th>
                                <th class="border border-gray-200 px-2 py-2 text-center">S</th>
                                <th class="border border-gray-200 px-2 py-2 text-center">A</th>
                                <th class="border border-gray-200 px-2 py-2 text-center">Kehadiran</th>
                                <th class="border border-gray-200 px-2 py-2 text-center">Tanpa I/S</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in detail.per_kelas" :key="i">
                                    <td class="border border-gray-200 px-2 py-2 font-semibold">{{ r.kelas }}</td>
                                    <td class="border border-gray-200 px-2 py-2 text-xs text-gray-600">{{ r.mapel.join(', ') || '—' }}</td>
                                    <td class="border border-gray-200 px-2 py-2 text-center font-semibold">{{ r.ikut }} / {{ r.total }}</td>
                                    <td class="border border-gray-200 px-2 py-2 text-center">{{ r.hadir }}</td>
                                    <td class="border border-gray-200 px-2 py-2 text-center">{{ r.telat }}</td>
                                    <td class="border border-gray-200 px-2 py-2 text-center">{{ r.izin }}</td>
                                    <td class="border border-gray-200 px-2 py-2 text-center">{{ r.sakit }}</td>
                                    <td class="border border-gray-200 px-2 py-2 text-center" :class="r.alpha ? 'text-rose-600 font-semibold' : ''">{{ r.alpha }}</td>
                                    <td class="border border-gray-200 px-2 py-2 text-center font-semibold">{{ fmt(r.persen) }}%</td>
                                    <td class="border border-gray-200 px-2 py-2 text-center font-semibold" :class="persenCls(r.persen_efektif)">{{ fmt(r.persen_efektif) }}%</td>
                                </tr>
                                <tr v-if="!detail.per_kelas.length"><td colspan="10" class="border border-gray-200 py-8 text-center text-gray-400">Belum ada data.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-gray-800 mb-2">
                        Daftar Ketidakhadiran
                        <span class="font-normal text-gray-500">({{ detail.ketidakhadiran.length }} kejadian)</span>
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse">
                            <thead><tr class="bg-[#2E3160] text-white">
                                <th class="border border-gray-200 px-2 py-2 w-10">No</th>
                                <th class="border border-gray-200 px-2 py-2 text-left">Tanggal</th>
                                <th class="border border-gray-200 px-2 py-2 text-left">Kelas</th>
                                <th class="border border-gray-200 px-2 py-2 text-left">Mata Pelajaran</th>
                                <th class="border border-gray-200 px-2 py-2 text-left">Guru</th>
                                <th class="border border-gray-200 px-2 py-2 text-center">Status</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in detail.ketidakhadiran" :key="i">
                                    <td class="border border-gray-200 px-2 py-2 text-center">{{ i + 1 }}</td>
                                    <td class="border border-gray-200 px-2 py-2 whitespace-nowrap">{{ r.tanggal }}</td>
                                    <td class="border border-gray-200 px-2 py-2">{{ r.kelas }}</td>
                                    <td class="border border-gray-200 px-2 py-2">{{ r.mapel }}</td>
                                    <td class="border border-gray-200 px-2 py-2 text-xs text-gray-600">{{ r.guru }}</td>
                                    <td class="border border-gray-200 px-2 py-2 text-center">
                                        <span class="px-2 py-0.5 rounded-full border text-[11px] font-semibold capitalize" :class="statusCls(r.status)">{{ r.status }}</span>
                                    </td>
                                </tr>
                                <tr v-if="!detail.ketidakhadiran.length"><td colspan="6" class="border border-gray-200 py-8 text-center text-emerald-600 font-medium">Tidak pernah absen pada periode ini.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tanda tangan -->
            <div class="grid grid-cols-2 gap-6 mt-10 text-sm text-center">
                <div>
                    <p>&nbsp;</p>
                    <p>{{ ttd.kiriJab }}</p>
                    <div class="h-20"></div>
                    <p class="font-semibold underline underline-offset-2">{{ ttd.kiriNama || '(……………………………)' }}</p>
                    <p class="text-xs">NIP. {{ ttd.kiriNip || '……………………' }}</p>
                </div>
                <div>
                    <p>{{ ttd.kota }}, {{ tanggalCetak }}</p>
                    <p>{{ ttd.kananJab }}</p>
                    <div class="h-20"></div>
                    <p class="font-semibold underline underline-offset-2">{{ ttd.kananNama || '(……………………………)' }}</p>
                    <p class="text-xs">NIP. {{ ttd.kananNip || '……………………' }}</p>
                </div>
            </div>

            <div class="kop-foot-wrap mt-6">
                <KopFooter :kop="kop" />
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import KopSurat from './Partials/KopSurat.vue'
import KopFooter from './Partials/KopFooter.vue'

const props = defineProps({
    mode: { type: String, default: 'ringkas' },
    kelas: { type: Object, default: null },
    filter: { type: Object, default: () => ({}) },
    periodeLabel: { type: String, default: '' },
    tanggalCetak: { type: String, default: '' },
    ringkasan: { type: Object, default: () => ({}) },
    perKelas: { type: Array, default: () => [] },
    perSantri: { type: Array, default: () => [] },
    detail: { type: Object, default: null },
    bulanOpsi: { type: Array, default: () => [] },
    tahunOpsi: { type: Array, default: () => [] },
    kelasOpsi: { type: Array, default: () => [] },
    santriOpsi: { type: Array, default: () => [] },
    kopOpsi: { type: Array, default: () => [] },
    ambang: { type: Object, default: () => ({ rajin: 90, perhatian: 85 }) },
})

const kopKey = ref(props.kopOpsi[0]?.key ?? 'smp')
const kop = computed(() => props.kopOpsi.find(k => k.key === kopKey.value) ?? props.kopOpsi[0] ?? { nama: '', alamat: '' })

const f = reactive({
    bulan: props.filter.bulan,
    tahun: props.filter.tahun,
    kelas_id: props.filter.kelas_id ?? null,
    santri_id: props.filter.santri_id ?? null,
})

const ttdBuka = ref(false)
const ttd = reactive({
    kota: 'Sidoarjo', kiriJab: 'Kepala Sekolah', kiriNama: '', kiriNip: '',
    kananJab: 'Wali Kelas,', kananNama: '', kananNip: '',
})

const statusRingkas = [
    { k: 'hadir', label: 'Hadir', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    { k: 'telat', label: 'Telat', cls: 'bg-amber-50 text-amber-700 border-amber-200' },
    { k: 'izin',  label: 'Izin',  cls: 'bg-sky-50 text-sky-700 border-sky-200' },
    { k: 'sakit', label: 'Sakit', cls: 'bg-violet-50 text-violet-700 border-violet-200' },
    { k: 'alpha', label: 'Alpha', cls: 'bg-rose-50 text-rose-700 border-rose-200' },
]

function link(extra = {}) {
    return route('admin.smart-education.laporan.kehadiran-santri',
        { bulan: f.bulan, tahun: f.tahun, kelas_id: null, santri_id: null, ...extra })
}
function terapkan() {
    router.get(route('admin.smart-education.laporan.kehadiran-santri'),
        { bulan: f.bulan, tahun: f.tahun, kelas_id: f.kelas_id, santri_id: f.santri_id },
        { preserveState: true, preserveScroll: true })
}
function cetak() { window.print() }

// Satu angka desimal, tanpa ".0" yang mengganggu saat dicetak.
function fmt(v) { return Number(v ?? 0).toFixed(1).replace(/\.0$/, '') }

function persenCls(v) {
    if (v >= props.ambang.rajin) return 'text-emerald-600'
    if (v >= props.ambang.perhatian) return 'text-amber-600'
    return 'text-rose-600'
}
function katLabel(k) {
    return { rajin: 'Rajin', cukup: 'Cukup', berizin: 'Sering Izin/Sakit', perhatian: 'Perlu Perhatian', kosong: '—' }[k] ?? k
}
function katCls(k) {
    return {
        rajin: 'bg-emerald-50 text-emerald-700 border-emerald-300',
        cukup: 'bg-sky-50 text-sky-700 border-sky-300',
        berizin: 'bg-amber-50 text-amber-700 border-amber-300',
        perhatian: 'bg-rose-50 text-rose-700 border-rose-300',
    }[k] ?? 'bg-gray-50 text-gray-500 border-gray-200'
}
function statusCls(s) {
    return {
        alpha: 'bg-rose-50 text-rose-700 border-rose-300',
        izin: 'bg-sky-50 text-sky-700 border-sky-300',
        sakit: 'bg-violet-50 text-violet-700 border-violet-300',
    }[s] ?? 'bg-gray-50 text-gray-500 border-gray-200'
}

const fieldCls = 'px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all bg-white'
</script>

<style scoped>
@media print {
    @page { margin: 12mm; }
    #laporan-cetak { font-size: 10.5px; }
    :deep(*) { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .kop-head-wrap { margin-bottom: 2mm; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
}
</style>

<template>
    <AdminLayout title="Ujian Sekolah" subtitle="Smart Education">

        <Head title="Ujian Sekolah" />

        <div class="flex items-start justify-between gap-3 mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Ujian Sekolah</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    Pembelajaran diganti sesi ujian berpenjaga — penjaganya boleh guru mana saja, bukan hanya yang berjadwal.
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <input v-model.number="filterTahun" @change="gantiTahun" type="number"
                    class="w-24 px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-indigo-500" />
                <button @click="bukaPaket"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl">
                    + Paket Ujian
                </button>
            </div>
        </div>

        <!-- Ringkasan -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div v-for="k in kartu" :key="k.label" :class="['rounded-2xl border px-4 py-3', k.bg]">
                <p class="text-xs text-gray-400">{{ k.label }}</p>
                <p :class="['text-2xl font-bold mt-0.5', k.warna]">{{ k.nilai }}</p>
            </div>
        </div>

        <div class="rounded-2xl bg-sky-50 border border-sky-100 px-4 py-3 mb-6 text-xs text-sky-800 leading-relaxed">
            <b>Cara kerjanya.</b> Setiap sesi ujian menjadi jadwal sehari atas nama penjaganya, jadi penjaga
            mengabsen seperti mengajar dan mengisi kehadiran santri. Pembelajaran reguler kelas peserta
            dimatikan lewat <b>Libur Pembelajaran</b> tanpa mengisi absensi santri, supaya kehadiran hari itu
            tidak terhitung dua kali. Vakasi penjaga dihitung <b>per sesi</b>; bila di-inval, penggantinya yang
            dibayar dengan nominal yang sama.
        </div>

        <!-- Daftar paket -->
        <div v-if="!ujian.length" class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
            <p class="text-sm text-gray-400">Belum ada paket ujian pada tahun {{ tahun }}.</p>
            <p class="text-xs text-gray-300 mt-1">Buat paket dulu, lalu susun jadwal sesi &amp; penjaganya.</p>
        </div>

        <div v-for="u in ujian" :key="u.id" class="bg-white rounded-2xl border border-gray-200 mb-4 overflow-hidden"
            :class="u.is_dibatalkan ? 'opacity-60' : ''">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-base font-semibold text-gray-900">{{ u.nama }}</h3>
                        <span v-if="u.is_dibatalkan" class="px-2 py-0.5 rounded-lg bg-red-50 text-red-600 text-[11px] font-semibold">Dibatalkan</span>
                        <span v-else-if="u.libur_aktif" class="px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-700 text-[11px] font-semibold">
                            Pembelajaran dimatikan
                        </span>
                        <span v-else class="px-2 py-0.5 rounded-lg bg-amber-50 text-amber-700 text-[11px] font-semibold">
                            Pembelajaran masih berjalan
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ u.rentang }}<span v-if="u.durasi_hari > 1"> · {{ u.durasi_hari }} hari</span>
                        · {{ u.sesi.length }} sesi
                        <span v-if="u.belum_penjaga" class="text-amber-600 font-semibold"> · {{ u.belum_penjaga }} belum ada penjaga</span>
                    </p>
                    <p v-if="u.is_dibatalkan && u.alasan_pembatalan" class="text-[11px] text-red-500 mt-0.5">{{ u.alasan_pembatalan }}</p>
                </div>

                <div v-if="!u.is_dibatalkan" class="flex flex-wrap gap-2 shrink-0">
                    <button @click="bukaSesi(u)" class="px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-semibold">+ Sesi</button>
                    <button @click="bukaGenerator(u)" class="px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-semibold">Susun Massal</button>
                    <button @click="sebar(u)" :disabled="!u.belum_penjaga"
                        class="px-3 py-1.5 rounded-lg bg-violet-50 text-violet-700 text-xs font-semibold disabled:opacity-40">
                        Sebar Penjaga
                    </button>
                    <button @click="sinkronLibur(u)" :disabled="!u.sesi.length"
                        class="px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-semibold disabled:opacity-40">
                        {{ u.libur_aktif ? 'Perbarui Libur' : 'Matikan Pembelajaran' }}
                    </button>
                    <button @click="bukaBatal(u)" class="px-3 py-1.5 rounded-lg bg-red-50 text-red-600 text-xs font-semibold">Batalkan</button>
                </div>
            </div>

            <div v-if="!u.sesi.length" class="px-5 py-8 text-center text-sm text-gray-400">
                Belum ada sesi. Pakai <b>+ Sesi</b> untuk satu-satu, atau <b>Susun Massal</b> untuk banyak kelas sekaligus.
            </div>

            <table v-else class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Tanggal &amp; Jam</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Kelas</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Mata Ujian</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Penjaga</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold text-gray-400 uppercase">Status</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-gray-400 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="s in u.sesi" :key="s.id" class="hover:bg-gray-50/40">
                        <td class="px-5 py-3">
                            <p class="text-sm text-gray-800">{{ s.tanggal_label }}</p>
                            <p class="text-xs text-gray-400 tabular-nums">{{ s.jam }} · {{ s.jumlah_jp }} JP</p>
                        </td>
                        <td class="px-3 py-3 text-sm text-gray-700">
                            {{ s.kelas }}
                            <span v-if="s.ruangan" class="block text-[11px] text-gray-400">Ruang {{ s.ruangan }}</span>
                        </td>
                        <td class="px-3 py-3 text-sm text-gray-700">{{ s.mapel }}</td>
                        <td class="px-3 py-3">
                            <p v-if="s.penjaga" class="text-sm text-gray-800">{{ s.penjaga }}</p>
                            <p v-else class="text-xs text-amber-600 font-semibold">belum ditunjuk</p>
                            <p v-if="s.inval_oleh" class="text-[11px] text-sky-600">→ inval {{ s.inval_oleh }}</p>
                            <p v-if="s.nominal_vakasi > 0" class="text-[11px] text-gray-400">
                                {{ rupiah(s.nominal_vakasi) }}<span v-if="s.vakasi_dibayar"> · sudah dibayar</span>
                            </p>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <span :class="['px-2 py-1 rounded-lg text-[11px] font-semibold', badge(s.status).cls]">
                                {{ badge(s.status).label }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <template v-if="!u.is_dibatalkan">
                                <button @click="bukaPenjaga(s)" class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-[11px] font-semibold">
                                    {{ s.penjaga_id ? 'Ganti' : 'Tunjuk' }}
                                </button>
                                <button v-if="s.penjaga_id && !s.inval_oleh && !s.sudah_dijaga" @click="bukaInval(s)"
                                    class="ml-1 px-2.5 py-1 rounded-lg bg-sky-50 text-sky-700 text-[11px] font-semibold">Inval</button>
                                <button v-if="s.inval_oleh && !s.sudah_dijaga" @click="batalInval(s)"
                                    class="ml-1 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 text-[11px] font-semibold">Batal Inval</button>
                                <button v-if="!s.vakasi_dibayar" @click="hapusSesi(s)"
                                    class="ml-1 px-2.5 py-1 rounded-lg bg-red-50 text-red-600 text-[11px] font-semibold">Hapus</button>
                            </template>
                            <span v-else class="text-xs text-gray-300">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ═══════════ MODAL: PAKET ═══════════ -->
        <div v-if="modalPaket" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl w-full max-w-md p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Paket Ujian Baru</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama <span class="text-red-500">*</span></label>
                        <input v-model="fPaket.nama" type="text" placeholder="cth: UAS Semester 1" :class="inp" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Mulai <span class="text-red-500">*</span></label>
                            <input v-model="fPaket.tanggal_mulai" type="date" :class="inp" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Selesai <span class="text-gray-400 font-normal">(opsional)</span>
                            </label>
                            <input v-model="fPaket.tanggal_selesai" type="date" :min="fPaket.tanggal_mulai" :class="inp" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Keterangan</label>
                        <textarea v-model="fPaket.keterangan" rows="2" :class="inp"></textarea>
                    </div>
                </div>
                <div class="flex gap-2 mt-5">
                    <button @click="modalPaket = false" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold">Batal</button>
                    <button @click="simpanPaket" :disabled="!fPaket.nama || !fPaket.tanggal_mulai || busy"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-semibold disabled:opacity-50">Simpan</button>
                </div>
            </div>
        </div>

        <!-- ═══════════ MODAL: SESI ═══════════ -->
        <div v-if="modalSesi" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl w-full max-w-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Tambah Sesi Ujian</h3>
                <p class="text-xs text-gray-400 mt-0.5 mb-4">{{ paketAktif?.nama }} · {{ paketAktif?.rentang }}</p>
                <div class="space-y-3">
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tanggal</label>
                            <select v-model="fSesi.tanggal" :class="inp">
                                <option v-for="t in paketAktif?.tanggal_cakupan ?? []" :key="t" :value="t">{{ t }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Jam Mulai</label>
                            <input v-model="fSesi.jam_mulai" type="time" :class="inp" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Jam Selesai</label>
                            <input v-model="fSesi.jam_selesai" type="time" :class="inp" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Kelas</label>
                            <select v-model.number="fSesi.kelas_id" :class="inp">
                                <option :value="null">— pilih</option>
                                <option v-for="k in kelasOpsi" :key="k.id" :value="k.id">{{ k.nama }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Mata Ujian</label>
                            <select v-model.number="fSesi.mata_pelajaran_id" :class="inp">
                                <option :value="null">— pilih</option>
                                <option v-for="m in mapelOpsi" :key="m.id" :value="m.id">{{ m.nama }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Ruangan</label>
                            <input v-model="fSesi.ruangan" type="text" placeholder="opsional" :class="inp" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Catatan</label>
                            <input v-model="fSesi.catatan" type="text" placeholder="opsional" :class="inp" />
                        </div>
                    </div>
                </div>
                <div class="flex gap-2 mt-5">
                    <button @click="modalSesi = false" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold">Batal</button>
                    <button @click="simpanSesi" :disabled="!sesiValid || busy"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-semibold disabled:opacity-50">Tambah</button>
                </div>
            </div>
        </div>

        <!-- ═══════════ MODAL: GENERATOR ═══════════ -->
        <div v-if="modalGen" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[92vh] overflow-y-auto p-6">
                <h3 class="text-base font-semibold text-gray-900">Susun Sesi Massal</h3>
                <p class="text-xs text-gray-400 mt-0.5 mb-4">
                    Setiap slot jam di bawah akan dibuat untuk <b>setiap kelas</b> yang dipilih.
                    Sesi yang bertabrakan jam dilewati dan dilaporkan.
                </p>

                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Kelas <span class="text-gray-400 font-normal">({{ fGen.kelas_ids.length }} dipilih)</span>
                </label>
                <div class="flex flex-wrap gap-1.5 p-3 rounded-xl border border-gray-200 bg-gray-50/50 max-h-36 overflow-y-auto mb-4">
                    <button v-for="k in kelasOpsi" :key="k.id" type="button" @click="toggleKelas(k.id)"
                        :class="['px-2.5 py-1.5 rounded-lg text-xs font-medium border',
                            fGen.kelas_ids.includes(k.id) ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200']">
                        {{ k.nama }}
                    </button>
                </div>

                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">Slot Jam &amp; Mata Ujian</label>
                    <button @click="tambahSlot" class="px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-semibold">+ Slot</button>
                </div>
                <div v-for="(sl, i) in fGen.slot" :key="i" class="grid grid-cols-12 gap-2 mb-2">
                    <select v-model="sl.tanggal" class="col-span-3 px-2 py-2 rounded-lg border border-gray-200 text-xs">
                        <option v-for="t in paketAktif?.tanggal_cakupan ?? []" :key="t" :value="t">{{ t }}</option>
                    </select>
                    <input v-model="sl.jam_mulai" type="time" class="col-span-2 px-2 py-2 rounded-lg border border-gray-200 text-xs" />
                    <input v-model="sl.jam_selesai" type="time" class="col-span-2 px-2 py-2 rounded-lg border border-gray-200 text-xs" />
                    <select v-model.number="sl.mata_pelajaran_id" class="col-span-4 px-2 py-2 rounded-lg border border-gray-200 text-xs">
                        <option :value="null">— mata ujian</option>
                        <option v-for="m in mapelOpsi" :key="m.id" :value="m.id">{{ m.nama }}</option>
                    </select>
                    <button @click="fGen.slot.splice(i, 1)" class="col-span-1 rounded-lg bg-red-50 text-red-600 text-xs font-bold">×</button>
                </div>

                <p class="text-[11px] text-gray-500 mt-3">
                    Akan dibuat <b>{{ fGen.kelas_ids.length * fGen.slot.filter(s => s.tanggal && s.jam_mulai && s.jam_selesai && s.mata_pelajaran_id).length }}</b> sesi.
                </p>

                <div class="flex gap-2 mt-5">
                    <button @click="modalGen = false" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold">Batal</button>
                    <button @click="simpanGen" :disabled="!genValid || busy"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-semibold disabled:opacity-50">Susun</button>
                </div>
            </div>
        </div>

        <!-- ═══════════ MODAL: PENJAGA / INVAL ═══════════ -->
        <div v-if="modalPenjaga" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl w-full max-w-lg max-h-[92vh] overflow-y-auto p-6">
                <h3 class="text-base font-semibold text-gray-900">
                    {{ modeInval ? 'Inval Penjaga' : (sesiAktif?.penjaga_id ? 'Ganti Penjaga' : 'Tunjuk Penjaga') }}
                </h3>
                <p class="text-xs text-gray-400 mt-0.5 mb-1">
                    {{ sesiAktif?.tanggal_label }} · {{ sesiAktif?.jam }} · {{ sesiAktif?.kelas }} — {{ sesiAktif?.mapel }}
                </p>
                <p v-if="modeInval" class="text-[11px] text-sky-700 bg-sky-50 rounded-lg px-3 py-2 mb-3 leading-snug">
                    Penjaga <b>{{ sesiAktif?.penjaga }}</b> berhalangan. Sesi dialihkan ke guru lain; penjaga asli
                    menjadi netral di kinerja dan <b>vakasinya ikut pindah</b> ke pengganti.
                </p>

                <input v-if="modeInval" v-model="fInval.alasan" type="text" placeholder="Alasan (dicatat sebagai jejak keputusan)"
                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm mb-3" />

                <input v-model="cariGuru" type="text" placeholder="Cari nama guru…"
                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm mb-3" />

                <p v-if="loadingCalon" class="text-xs text-gray-400 py-6 text-center">Memeriksa jadwal setiap guru…</p>
                <div v-else class="rounded-xl border border-gray-200 divide-y divide-gray-50 max-h-72 overflow-y-auto">
                    <button v-for="c in calonTampil" :key="c.id" type="button" :disabled="!c.boleh"
                        @click="pilihPenjaga(c)"
                        class="w-full text-left px-3 py-2.5 hover:bg-gray-50 disabled:bg-gray-50/50 disabled:cursor-not-allowed">
                        <div class="flex items-center justify-between gap-2">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium truncate"
                                    :class="c.boleh ? 'text-gray-800' : 'text-gray-400'">{{ c.nama }}</span>
                                <span v-if="c.alasan" class="block text-[11px] text-red-500">{{ c.alasan }}</span>
                                <span v-else class="block text-[11px] text-gray-400">
                                    Jaga hari ini: {{ c.jaga_hari_ini }} · total paket: {{ c.jaga_paket }}
                                </span>
                            </span>
                            <span v-if="c.id === sesiAktif?.penjaga_id"
                                class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">penjaga saat ini</span>
                        </div>
                    </button>
                    <p v-if="!calonTampil.length" class="px-3 py-6 text-center text-sm text-gray-400">Tidak ada guru yang cocok.</p>
                </div>

                <button @click="modalPenjaga = false" class="w-full mt-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold">Tutup</button>
            </div>
        </div>

        <!-- ═══════════ MODAL: BATALKAN PAKET ═══════════ -->
        <div v-if="modalBatal" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl w-full max-w-md p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-1">Batalkan "{{ paketAktif?.nama }}"?</h3>
                <p class="text-xs text-gray-500 leading-relaxed mb-4">
                    Jadwal sesi ujian beserta absensinya dihapus dan pembelajaran reguler dipulihkan.
                    <b class="text-gray-700">Sesi yang vakasinya sudah dibayar dipertahankan</b> karena slipnya sudah terbit.
                </p>
                <input v-model="fBatal.alasan" type="text" placeholder="Alasan (opsional)"
                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm mb-4" />
                <div class="flex gap-2">
                    <button @click="modalBatal = false" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold">Tutup</button>
                    <button @click="kirimBatal" class="flex-1 py-2.5 rounded-xl bg-red-600 text-white text-sm font-semibold">Ya, Batalkan</button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

const props = defineProps({
    ujian: { type: Array, default: () => [] },
    tahun: { type: Number, default: new Date().getFullYear() },
    kelasOpsi: { type: Array, default: () => [] },
    mapelOpsi: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
})

const inp = 'w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white'
const busy = ref(false)
const filterTahun = ref(props.tahun)
const rupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID')

const kartu = computed(() => [
    { label: 'Paket Ujian', nilai: props.summary.paket ?? 0, bg: 'bg-white border-gray-200', warna: 'text-gray-900' },
    { label: 'Total Sesi', nilai: props.summary.sesi ?? 0, bg: 'bg-indigo-50 border-indigo-100', warna: 'text-indigo-700' },
    { label: 'Belum Ada Penjaga', nilai: props.summary.belum_penjaga ?? 0, bg: 'bg-amber-50 border-amber-100', warna: 'text-amber-700' },
    { label: 'Belum Diabsen', nilai: props.summary.belum_diabsen ?? 0, bg: 'bg-rose-50 border-rose-100', warna: 'text-rose-700' },
])

function badge(st) {
    return {
        belum_penjaga: { label: 'Belum ada penjaga', cls: 'bg-amber-50 text-amber-700' },
        ditugaskan:    { label: 'Ditugaskan', cls: 'bg-sky-50 text-sky-700' },
        inval:         { label: 'Di-inval', cls: 'bg-violet-50 text-violet-700' },
        dijaga:        { label: 'Dijaga', cls: 'bg-emerald-50 text-emerald-700' },
        dijaga_inval:  { label: 'Dijaga (inval)', cls: 'bg-emerald-50 text-emerald-700' },
        tidak_dijaga:  { label: 'Tidak dijaga', cls: 'bg-red-50 text-red-600' },
    }[st] ?? { label: st, cls: 'bg-gray-100 text-gray-600' }
}

const gantiTahun = () => router.get(route('admin.smart-education.ujian.index'),
    { tahun: filterTahun.value }, { preserveState: true, preserveScroll: true })

const kirim = (url, data = {}, metode = 'post') => {
    busy.value = true
    router[metode](url, data, {
        preserveScroll: true,
        onFinish: () => { busy.value = false },
    })
}

// ── Paket ───────────────────────────────────────────────────────────────────
const modalPaket = ref(false)
const paketAktif = ref(null)
const fPaket = reactive({ nama: '', tanggal_mulai: new Date().toISOString().slice(0, 10), tanggal_selesai: '', keterangan: '' })

function bukaPaket() {
    Object.assign(fPaket, { nama: '', tanggal_mulai: new Date().toISOString().slice(0, 10), tanggal_selesai: '', keterangan: '' })
    modalPaket.value = true
}
function simpanPaket() {
    busy.value = true
    router.post(route('admin.smart-education.ujian.store'),
        { ...fPaket, tanggal_selesai: fPaket.tanggal_selesai || null },
        { preserveScroll: true, onSuccess: () => { modalPaket.value = false }, onFinish: () => busy.value = false })
}

const modalBatal = ref(false)
const fBatal = reactive({ alasan: '' })
function bukaBatal(u) { paketAktif.value = u; fBatal.alasan = ''; modalBatal.value = true }
function kirimBatal() {
    busy.value = true
    router.post(route('admin.smart-education.ujian.batalkan', paketAktif.value.id), { alasan: fBatal.alasan },
        { preserveScroll: true, onSuccess: () => { modalBatal.value = false }, onFinish: () => busy.value = false })
}

function sinkronLibur(u) {
    kirim(route('admin.smart-education.ujian.sinkron-libur', u.id))
}
function sebar(u) {
    kirim(route('admin.smart-education.ujian.sebar-penjaga', u.id))
}

// ── Sesi ────────────────────────────────────────────────────────────────────
const modalSesi = ref(false)
const fSesi = reactive({ tanggal: '', jam_mulai: '07:30', jam_selesai: '09:30', kelas_id: null, mata_pelajaran_id: null, ruangan: '', catatan: '' })
const sesiValid = computed(() => fSesi.tanggal && fSesi.jam_mulai && fSesi.jam_selesai && fSesi.kelas_id && fSesi.mata_pelajaran_id)

function bukaSesi(u) {
    paketAktif.value = u
    Object.assign(fSesi, { tanggal: u.tanggal_cakupan[0], jam_mulai: '07:30', jam_selesai: '09:30', kelas_id: null, mata_pelajaran_id: null, ruangan: '', catatan: '' })
    modalSesi.value = true
}
function simpanSesi() {
    busy.value = true
    router.post(route('admin.smart-education.ujian.sesi.store', paketAktif.value.id), { ...fSesi },
        { preserveScroll: true, onSuccess: () => { modalSesi.value = false }, onFinish: () => busy.value = false })
}
async function hapusSesi(s) {
    if (!(await confirm({ title: `Hapus sesi ${s.kelas} ${s.jam}?`, message: 'Jadwal & absensi sesi ini ikut terhapus.', variant: 'danger', confirmLabel: 'Ya, Hapus' }))) return
    kirim(route('admin.smart-education.ujian.sesi.destroy', s.id), {}, 'delete')
}

// ── Generator ───────────────────────────────────────────────────────────────
const modalGen = ref(false)
const fGen = reactive({ kelas_ids: [], slot: [] })
const genValid = computed(() => fGen.kelas_ids.length > 0
    && fGen.slot.some(s => s.tanggal && s.jam_mulai && s.jam_selesai && s.mata_pelajaran_id))

function bukaGenerator(u) {
    paketAktif.value = u
    fGen.kelas_ids = []
    fGen.slot = [{ tanggal: u.tanggal_cakupan[0], jam_mulai: '07:30', jam_selesai: '09:30', mata_pelajaran_id: null }]
    modalGen.value = true
}
const toggleKelas = (id) => {
    const i = fGen.kelas_ids.indexOf(id)
    i >= 0 ? fGen.kelas_ids.splice(i, 1) : fGen.kelas_ids.push(id)
}
const tambahSlot = () => {
    const akhir = fGen.slot[fGen.slot.length - 1]
    fGen.slot.push({
        tanggal: akhir?.tanggal ?? paketAktif.value.tanggal_cakupan[0],
        jam_mulai: akhir?.jam_selesai ?? '07:30', jam_selesai: '', mata_pelajaran_id: null,
    })
}
function simpanGen() {
    busy.value = true
    router.post(route('admin.smart-education.ujian.sesi.generate', paketAktif.value.id), {
        kelas_ids: fGen.kelas_ids,
        slot: fGen.slot.filter(s => s.tanggal && s.jam_mulai && s.jam_selesai && s.mata_pelajaran_id),
    }, { preserveScroll: true, onSuccess: () => { modalGen.value = false }, onFinish: () => busy.value = false })
}

// ── Penjaga & inval ─────────────────────────────────────────────────────────
const modalPenjaga = ref(false)
const modeInval = ref(false)
const sesiAktif = ref(null)
const calon = ref([])
const loadingCalon = ref(false)
const cariGuru = ref('')
const fInval = reactive({ alasan: '' })

const calonTampil = computed(() => {
    const q = cariGuru.value.trim().toLowerCase()
    return q ? calon.value.filter(c => c.nama.toLowerCase().includes(q)) : calon.value
})

async function muatCalon(s) {
    loadingCalon.value = true; calon.value = []
    try {
        const res = await fetch(route('admin.smart-education.ujian.sesi.calon-penjaga', s.id),
            { headers: { Accept: 'application/json' } })
        calon.value = (await res.json())?.data?.calon ?? []
    } catch (_) { calon.value = [] } finally { loadingCalon.value = false }
}
function bukaPenjaga(s) {
    sesiAktif.value = s; modeInval.value = false; cariGuru.value = ''
    modalPenjaga.value = true; muatCalon(s)
}
function bukaInval(s) {
    sesiAktif.value = s; modeInval.value = true; cariGuru.value = ''; fInval.alasan = ''
    modalPenjaga.value = true; muatCalon(s)
}
function pilihPenjaga(c) {
    const url = modeInval.value
        ? route('admin.smart-education.ujian.sesi.inval', sesiAktif.value.id)
        : route('admin.smart-education.ujian.sesi.penjaga', sesiAktif.value.id)
    const data = modeInval.value
        ? { pengganti_id: c.id, alasan: fInval.alasan }
        : { penjaga_id: c.id }

    busy.value = true
    router.post(url, data, {
        preserveScroll: true,
        onSuccess: () => { modalPenjaga.value = false },
        onFinish: () => busy.value = false,
    })
}
async function batalInval(s) {
    if (!(await confirm({ title: 'Batalkan inval sesi ini?', message: 'Sesi kembali menjadi tugas penjaga yang ditunjuk.', confirmLabel: 'Ya, Batalkan' }))) return
    kirim(route('admin.smart-education.ujian.sesi.batal-inval', s.id))
}
</script>

<template>
    <AdminLayout title="Shift Satpam" subtitle="Jam Kerja">
        <Head title="Jadwal Shift Satpam" />

        <div class="mb-5">
            <h1 class="text-xl font-bold text-gray-800">Jadwal Shift Satpam</h1>
            <p class="text-sm text-gray-400 mt-0.5">Atur shift bergilir. Shift menimpa jam kerja untuk rentang tanggalnya — tanpa mengubah setting jam kerja asli.</p>
        </div>

        <!-- ══ Papan roster: apa yang SEHARUSNYA berlaku pekan ini ══
             Dihitung dari aturan rotasi, bukan dibaca dari baris roster —
             jadi kalau suatu saat yang tertulis menyimpang, selisihnya
             justru yang terlihat di sini. -->
        <div v-if="papan" class="bg-white rounded-2xl border border-gray-100 p-4 mb-6 overflow-x-auto">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h2 class="font-semibold text-gray-800">
                    Roster Blok {{ papan.blok_ke }}
                    <span class="text-gray-400 font-normal text-sm">{{ papan.dari }} s/d {{ papan.sampai }}</span>
                </h2>
                <div class="flex items-center gap-2 text-xs">
                    <span :class="papan.celah.length
                        ? 'bg-red-50 text-red-700 border-red-200'
                        : 'bg-emerald-50 text-emerald-700 border-emerald-200'"
                        class="px-2 py-1 rounded-lg border font-semibold">
                        {{ papan.celah.length ? papan.celah.length + ' pos kosong' : 'Terjaga 24 jam penuh' }}
                    </span>
                    <span :class="papan.jam_rata
                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                        : 'bg-amber-50 text-amber-700 border-amber-200'"
                        class="px-2 py-1 rounded-lg border font-semibold">
                        {{ papan.jam_rata ? 'Jam rata' : 'Jam timpang' }}
                    </span>
                </div>
            </div>

            <p v-if="papan.celah.length" class="text-xs text-red-700 mb-2">
                Tidak ada penjaga pada:
                <b v-for="(c, i) in papan.celah" :key="i">{{ c.dari }}–{{ c.sampai }}<span v-if="i < papan.celah.length - 1">, </span></b>
            </p>

            <table class="w-full text-xs border-separate" style="border-spacing: 2px">
                <thead>
                    <tr class="text-gray-400">
                        <th class="text-left font-medium px-2 py-1">Petugas</th>
                        <th v-for="h in papan.hari" :key="h" class="font-medium px-2 py-1 capitalize">{{ h }}</th>
                        <th class="font-medium px-2 py-1 text-right">Jam</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="b in papan.baris" :key="b.tp_id">
                        <td class="px-2 py-1.5 bg-gray-50 rounded-lg">
                            <div class="font-semibold text-gray-800">{{ b.nama }}</div>
                            <div class="text-gray-400 capitalize">{{ b.peran }} · libur {{ b.libur }}</div>
                        </td>
                        <td v-for="h in papan.hari" :key="h" class="px-1.5 py-1.5 text-center rounded-lg"
                            :class="!b.hari[h] ? 'bg-gray-100 text-gray-400'
                                : (b.hari[h].dua_belas ? 'bg-amber-50 text-amber-800 font-semibold'
                                                       : 'bg-sky-50 text-sky-800')">
                            {{ b.hari[h] ? b.hari[h].jam : 'libur' }}
                        </td>
                        <td class="px-2 py-1.5 text-right font-semibold text-gray-700">{{ b.jam }}j</td>
                    </tr>
                </tbody>
            </table>

            <p class="text-[11px] text-gray-400 mt-2">
                Kuning = giliran 12 jam karena salah satu rekan libur hari itu.
                Rotasi maju tiap 2 pekan (pagi → siang → malam) dan terbit otomatis tiap malam,
                jadi roster tidak pernah habis.
            </p>
        </div>

        <div v-else-if="papanGalat" class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-6">
            <h2 class="font-semibold text-amber-900 text-sm">Papan roster belum bisa ditampilkan</h2>
            <p class="text-xs text-amber-800 mt-1">{{ papanGalat }}</p>
        </div>

        <div class="grid lg:grid-cols-2 gap-4 mb-6">
            <!-- Tetapkan manual -->
            <div class="bg-white rounded-2xl border border-gray-100 p-4">
                <h2 class="font-semibold text-gray-800 mb-3">Tetapkan Shift</h2>
                <div class="grid gap-2">
                    <select v-model="f.tenaga_pendidik_id" class="rounded-lg border-gray-200 text-sm">
                        <option :value="null" disabled>Pilih satpam…</option>
                        <option v-for="s in satpam" :key="s.id" :value="s.id">{{ s.nama }}</option>
                    </select>
                    <select v-model="f.setting_jam_kerja_id" class="rounded-lg border-gray-200 text-sm">
                        <option :value="null" disabled>Pilih shift…</option>
                        <option v-for="o in shiftOptions" :key="o.id" :value="o.id">{{ o.nama }} ({{ o.jam }})</option>
                    </select>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="text-[11px] text-gray-500">Mulai</label><input v-model="f.tanggal_mulai" type="date" class="w-full rounded-lg border-gray-200 text-sm" /></div>
                        <div><label class="text-[11px] text-gray-500">Selesai</label><input v-model="f.tanggal_selesai" type="date" class="w-full rounded-lg border-gray-200 text-sm" /></div>
                    </div>
                    <button @click="tetapkan" :disabled="f.processing || !f.tenaga_pendidik_id || !f.setting_jam_kerja_id || !f.tanggal_mulai || !f.tanggal_selesai"
                        class="mt-1 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">Tetapkan</button>
                </div>
            </div>

            <!-- Rotasi cepat -->
            <div class="bg-white rounded-2xl border border-indigo-100 p-4">
                <h2 class="font-semibold text-gray-800 mb-1">Rotasi Cepat</h2>
                <p class="text-xs text-gray-400 mb-3">Bagi shift bergilir otomatis ke satpam terpilih selama beberapa periode.</p>
                <div class="grid gap-2">
                    <div>
                        <p class="text-[11px] text-gray-500 mb-1">Satpam (urut giliran)</p>
                        <label v-for="s in satpam" :key="s.id" class="flex items-center gap-2 text-sm py-0.5">
                            <input type="checkbox" :value="s.id" v-model="r.tenaga_pendidik_ids" /> {{ s.nama }}
                        </label>
                    </div>
                    <div>
                        <p class="text-[11px] text-gray-500 mb-1">Shift (urut)</p>
                        <label v-for="o in shiftOptions" :key="o.id" class="flex items-center gap-2 text-sm py-0.5">
                            <input type="checkbox" :value="o.id" v-model="r.setting_jam_kerja_ids" /> {{ o.nama }} <span class="text-xs text-gray-400">({{ o.jam }})</span>
                        </label>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div><label class="text-[11px] text-gray-500">Mulai</label><input v-model="r.tanggal_mulai" type="date" class="w-full rounded-lg border-gray-200 text-sm" /></div>
                        <div><label class="text-[11px] text-gray-500">Interval</label>
                            <select v-model="r.interval" class="w-full rounded-lg border-gray-200 text-sm"><option value="mingguan">Mingguan</option><option value="bulanan">Bulanan</option></select></div>
                        <div><label class="text-[11px] text-gray-500">Jml periode</label><input v-model.number="r.jumlah_periode" type="number" min="1" max="24" class="w-full rounded-lg border-gray-200 text-sm" /></div>
                    </div>
                    <button @click="rotasi" :disabled="r.processing || !r.tenaga_pendidik_ids.length || !r.setting_jam_kerja_ids.length || !r.tanggal_mulai"
                        class="mt-1 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">Buat Rotasi</button>
                </div>
            </div>
        </div>

        <!-- Daftar satpam + shift -->
        <div v-if="!satpam.length" class="bg-white rounded-2xl border border-dashed border-gray-200 py-12 text-center text-gray-400">
            Belum ada guru berjabatan keamanan/satpam. Set jabatannya dulu di Master Jabatan.
        </div>
        <div v-for="s in satpam" :key="s.id" class="bg-white rounded-2xl border border-gray-100 p-4 mb-3">
            <div class="flex items-center gap-3 mb-2">
                <div class="flex-1">
                    <p class="font-semibold text-gray-800">{{ s.nama }} <span class="text-xs text-gray-400 font-normal">· {{ s.jabatan }}</span></p>
                    <p class="text-xs text-gray-500">Shift hari ini: <b class="text-indigo-600">{{ s.shift_hari_ini || '—' }}</b></p>
                </div>
            </div>
            <div v-if="s.shifts.length" class="divide-y divide-gray-50 border-t border-gray-50">
                <div v-for="sh in s.shifts" :key="sh.id" class="flex items-center gap-3 py-2 text-sm">
                    <span class="flex-1 text-gray-700">{{ sh.shift }} <span class="text-xs text-gray-400">{{ sh.jam }}</span></span>
                    <span class="text-xs text-gray-500 tabular-nums">{{ sh.mulai }} – {{ sh.selesai }}</span>
                    <button @click="hapus(sh)" class="text-red-500 text-xs font-semibold">Hapus</button>
                </div>
            </div>
            <p v-else class="text-xs text-gray-400">Belum ada jadwal shift mendatang.</p>
        </div>
    </AdminLayout>
</template>

<script setup>
import { useForm, router, Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
    papan: { type: Object, default: null },
    papanGalat: { type: String, default: null },
    satpam: { type: Array, default: () => [] },
    shiftOptions: { type: Array, default: () => [] },
})

const f = useForm({ tenaga_pendidik_id: null, setting_jam_kerja_id: null, tanggal_mulai: '', tanggal_selesai: '', keterangan: '' })
const r = useForm({ tenaga_pendidik_ids: [], setting_jam_kerja_ids: [], tanggal_mulai: '', interval: 'mingguan', jumlah_periode: 4 })

function tetapkan() { f.post(route('admin.smart-payroll.jadwal-shift.store'), { preserveScroll: true, onSuccess: () => f.reset('tenaga_pendidik_id', 'setting_jam_kerja_id') }) }
function rotasi() { r.post(route('admin.smart-payroll.jadwal-shift.rotasi'), { preserveScroll: true }) }
function hapus(sh) { if (window.confirm('Hapus jadwal shift ini?')) router.delete(route('admin.smart-payroll.jadwal-shift.destroy', sh.id), { preserveScroll: true }) }
</script>

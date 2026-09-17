<template>
    <AdminLayout title="Kelas" subtitle="Smart Education">

        <Head title="Kelas" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Kelas</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    {{ summary.sekolah }} kelas sekolah · {{ summary.tahfidz }} kelas tahfidz
                </p>
            </div>
            <button @click="openCreate"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Kelas
            </button>
        </div>

        <!-- Filter jenis -->
        <div class="flex gap-2 mb-4">
            <button v-for="f in filters" :key="f.val" @click="filter = f.val" :class="[
                'px-3.5 py-1.5 rounded-xl text-sm font-medium transition-colors',
                filter === f.val ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-200' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'
            ]">{{ f.label }}</button>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Kelas</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Jenis</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">Wali Kelas</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">Santri</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="k in filtered" :key="k.id"
                        :class="['hover:bg-gray-50/40 transition-colors', !k.is_aktif ? 'opacity-60' : '']">
                        <td class="px-5 py-3.5">
                            <p class="text-sm font-medium text-gray-800">{{ k.nama }}</p>
                            <p v-if="k.tingkat || k.tahun_ajaran" class="text-xs text-gray-400">
                                {{ [k.tingkat, k.tahun_ajaran].filter(Boolean).join(' · ') }}
                            </p>
                        </td>
                        <td class="px-5 py-3.5">
                            <span :class="[
                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium',
                                k.jenis === 'tahfidz' ? 'bg-violet-50 text-violet-700'
                                    : k.jenis === 'tahsin' ? 'bg-amber-50 text-amber-700' : 'bg-sky-50 text-sky-700'
                            ]">
                                {{ k.jenis === 'tahfidz' ? 'Tahfidz' : k.jenis === 'tahsin' ? (k.level_tahsin === 6 ? 'Tahsin · Persiapan Tahfidz' : 'Tahsin Lv ' + (k.level_tahsin ?? '?')) : 'Sekolah' }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 hidden md:table-cell">
                            <span class="text-sm text-gray-600">{{ k.wali_kelas || '—' }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <span class="text-sm font-semibold text-gray-700">{{ k.jumlah_santri }}</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span :class="[
                                'inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium',
                                k.is_aktif ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'
                            ]">
                                <span :class="['w-1.5 h-1.5 rounded-full', k.is_aktif ? 'bg-emerald-500' : 'bg-gray-400']"></span>
                                {{ k.is_aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex justify-end gap-1">
                                <button v-if="k.is_aktif" @click="openAtur(k)" title="Atur Santri (centang)"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a3 3 0 10-3-3" /></svg>
                                    Atur Santri
                                </button>
                                <button v-if="k.jumlah_santri > 0" @click="openNaik(k)" title="Naik / Pindah Kelas"
                                    class="p-2 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                                </button>
                                <button @click="openEdit(k)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button v-if="k.is_aktif" @click="hapus(k)" title="Nonaktifkan kelas"
                                    class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                </button>
                                <button v-else @click="aktifkan(k)" title="Aktifkan kelas"
                                    class="p-2 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!filtered.length">
                        <td colspan="6" class="py-14 text-center text-sm text-gray-400">Belum ada kelas.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal -->
        <Transition name="modal">
            <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeForm" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-5">{{ editTarget ? 'Edit Kelas' : 'Tambah Kelas' }}</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Kelas <span class="text-red-500">*</span></label>
                            <input v-model="form.nama" type="text" placeholder="cth: VII A, VIII B" :class="inputCls(form.errors?.nama)" />
                            <p v-if="form.errors?.nama" class="mt-1 text-xs text-red-500">{{ form.errors.nama }}</p>
                        </div>
                        <div v-if="form.jenis === 'sekolah'">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Deskriptif <span class="text-gray-400 font-normal">(opsional)</span></label>
                            <input v-model="form.nama_deskriptif" type="text" placeholder="cth: Ibnu Sina, Lubna" :class="inputCls()" />
                            <p class="mt-1 text-[11px] text-gray-400">Nama tokoh kelas — ikut tersinkron ke RamahAnak.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Jenis <span class="text-red-500">*</span></label>
                                <select v-model="form.jenis" :class="inputCls(form.errors?.jenis)">
                                    <option value="sekolah">Sekolah</option>
                                    <option value="tahfidz">Tahfidz</option>
                                    <option value="tahsin">Tahsin</option>
                                </select>
                            </div>
                            <div v-if="form.jenis === 'tahsin'">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Level Tahsin <span class="text-red-500">*</span></label>
                                <select v-model.number="form.level_tahsin" :class="inputCls(form.errors?.level_tahsin)">
                                    <option :value="null">— pilih</option>
                                    <option v-for="lv in [1,2,3,4,5]" :key="lv" :value="lv">Level {{ lv }}</option>
                                    <option :value="6">Persiapan Tahfidz</option>
                                </select>
                            </div>
                            <div v-else>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Tingkat</label>
                                <input v-model="form.tingkat" type="text" placeholder="cth: VII, Ula" :class="inputCls()" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tahun Ajaran</label>
                            <select v-model="form.tahun_ajaran_id" :class="inputCls()">
                                <option :value="null">—</option>
                                <option v-for="t in tahunAjaran" :key="t.id" :value="t.id">{{ t.nama }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Wali Kelas</label>
                            <select v-model="form.wali_kelas_id" :class="inputCls()">
                                <option :value="null">—</option>
                                <option v-for="g in guru" :key="g.id" :value="g.id">{{ g.nama }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button @click="closeForm" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">Batal</button>
                        <button @click="submit" :disabled="saving"
                            class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60 transition-colors">
                            {{ saving ? 'Menyimpan...' : (editTarget ? 'Simpan' : 'Tambah') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Modal Naik / Pindah Kelas -->
        <Transition name="modal">
            <div v-if="naikSumber" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="naikSumber = null">
                <div class="absolute inset-0 bg-black/40"></div>
                <div class="relative bg-white rounded-2xl w-full max-w-lg p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                    <h3 class="text-base font-semibold text-gray-900">Naik / Pindah Kelas</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Dari <b>{{ naikSumber.nama }}</b> ({{ naikSumber.jenis }}) · {{ naikSumber.tahun_ajaran || '—' }}</p>

                    <div class="grid grid-cols-2 gap-3 mt-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Kelas Tujuan (sejenis)</label>
                            <select v-model.number="naikTujuanId" :class="inputCls()">
                                <option :value="null">— pilih kelas tujuan —</option>
                                <option v-for="k in kelasTujuanOpsi" :key="k.id" :value="k.id">{{ k.nama }} · {{ k.tahun_ajaran || '—' }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal Naik</label>
                            <input v-model="naikTanggal" type="date" :class="inputCls()" />
                        </div>
                    </div>
                    <p v-if="!kelasTujuanOpsi.length" class="text-xs text-amber-600 mt-2">Belum ada kelas {{ naikSumber.jenis }} lain sebagai tujuan. Buat kelas tujuan (tahun ajaran berikutnya) dulu.</p>

                    <div class="mt-4">
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-medium text-gray-600">Santri yang naik ({{ naikSantri.length - kecuali.length }}/{{ naikSantri.length }})</label>
                            <span class="text-[11px] text-gray-400">Centang = tinggal kelas (dikecualikan)</span>
                        </div>
                        <div v-if="naikLoading" class="py-6 text-center text-sm text-gray-400">Memuat santri…</div>
                        <div v-else class="border border-gray-100 rounded-xl divide-y divide-gray-50 max-h-64 overflow-y-auto">
                            <label v-for="s in naikSantri" :key="s.id" class="flex items-center gap-3 px-3 py-2 cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" :checked="kecuali.includes(s.id)" @change="toggleKecuali(s.id)" class="rounded border-gray-300 text-amber-500 focus:ring-amber-200" />
                                <span class="text-sm" :class="kecuali.includes(s.id) ? 'text-gray-400 line-through' : 'text-gray-700'">{{ s.nama }}</span>
                                <span v-if="s.nip" class="ml-auto text-[11px] text-gray-400">{{ s.nip }}</span>
                            </label>
                            <p v-if="!naikSantri.length" class="px-3 py-4 text-sm text-gray-400 text-center">Tidak ada santri aktif.</p>
                        </div>
                    </div>

                    <div class="mt-3 text-[11px] text-gray-500 bg-gray-50 rounded-lg px-3 py-2">
                        Data lama <b>tidak dihapus</b> — keanggotaan kelas sebelumnya tersimpan sebagai riwayat (bisa dipakai lagi).
                    </div>

                    <div class="flex gap-2 mt-5">
                        <button @click="naikSumber = null" class="flex-1 py-2.5 rounded-xl bg-gray-100 text-gray-600 text-sm font-semibold">Batal</button>
                        <button @click="submitNaik" :disabled="!naikTujuanId || naikSaving" class="flex-1 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold disabled:opacity-50">
                            {{ naikSaving ? 'Memproses…' : 'Proses Naik Kelas' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Modal Atur Santri (centang) -->
        <Transition name="modal">
            <div v-if="atur" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="!aturSaving && (atur = null)"></div>
                <div class="relative bg-white rounded-2xl w-full max-w-2xl shadow-xl max-h-[92vh] flex flex-col">

                    <!-- Header -->
                    <div class="px-6 pt-5 pb-3 border-b border-gray-100">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">Atur Santri · {{ atur.nama }}</h3>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Centang santri yang menjadi anggota kelas ini. Santri yang sudah punya {{ aturData?.kelas?.slot_label || 'kelas' }} lain akan <b>dipindah</b> ke sini.
                                </p>
                            </div>
                            <span class="shrink-0 text-xs font-semibold px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700">{{ dipilih.length }} santri</span>
                        </div>

                        <template v-if="!aturKonfirmasi">
                            <div class="flex flex-wrap gap-2 mt-3">
                                <input v-model="aturQ" type="text" placeholder="Cari nama / NIS…" class="flex-1 min-w-[180px] px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500" />
                                <select v-model="aturJk" class="px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white">
                                    <option value="semua">L & P</option>
                                    <option value="L">Putra</option>
                                    <option value="P">Putri</option>
                                </select>
                            </div>
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                <button v-for="f in aturFilters" :key="f.val" @click="aturStatus = f.val"
                                    class="px-2.5 py-1 rounded-lg text-xs font-medium"
                                    :class="aturStatus === f.val ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                                    {{ f.label }} <span class="opacity-70">({{ f.n }})</span>
                                </button>
                            </div>
                        </template>
                    </div>

                    <!-- Isi -->
                    <div class="flex-1 overflow-y-auto px-6 py-3">
                        <div v-if="aturLoading" class="py-10 text-center text-sm text-gray-400">Memuat santri…</div>

                        <!-- Langkah 1: centang -->
                        <template v-else-if="!aturKonfirmasi">
                            <div class="flex items-center justify-between mb-2 text-xs">
                                <span class="text-gray-400">{{ daftarAtur.length }} santri ditampilkan</span>
                                <div class="flex gap-3">
                                    <button @click="centangSemua(true)" class="font-semibold text-indigo-600 hover:underline">Centang semua yang tampil</button>
                                    <button @click="centangSemua(false)" class="font-semibold text-gray-500 hover:underline">Hapus centang yang tampil</button>
                                </div>
                            </div>
                            <div class="border border-gray-100 rounded-xl divide-y divide-gray-50">
                                <label v-for="s in daftarAtur" :key="s.id"
                                    class="flex items-center gap-3 px-3 py-2 cursor-pointer hover:bg-gray-50"
                                    :class="perubahanBaris(s)">
                                    <input type="checkbox" :checked="pilih.has(s.id)" @change="toggle(s.id)"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-200" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm text-gray-800 truncate">{{ s.nama }}</p>
                                        <p class="text-[11px] text-gray-400">{{ s.nip || '—' }} · {{ s.jenis_kelamin === 'P' ? 'Putri' : 'Putra' }}</p>
                                    </div>
                                    <span v-if="s.anggota && !pilih.has(s.id)" class="text-[10px] font-semibold px-2 py-0.5 rounded bg-red-50 text-red-600">akan keluar</span>
                                    <span v-else-if="!s.anggota && pilih.has(s.id)" class="text-[10px] font-semibold px-2 py-0.5 rounded"
                                        :class="s.kelas_lain ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'">
                                        {{ s.kelas_lain ? `pindah dari ${s.kelas_lain}` : 'masuk' }}
                                    </span>
                                    <span v-else-if="s.anggota" class="text-[10px] text-gray-400">anggota</span>
                                    <span v-else-if="s.kelas_lain" class="text-[10px] text-gray-400 truncate max-w-[120px]">{{ s.kelas_lain }}</span>
                                    <span v-else class="text-[10px] text-gray-300">belum ada kelas</span>
                                </label>
                                <p v-if="!daftarAtur.length" class="px-3 py-6 text-sm text-gray-400 text-center">Tidak ada santri yang cocok.</p>
                            </div>
                        </template>

                        <!-- Langkah 2: konfirmasi -->
                        <template v-else>
                            <div class="grid grid-cols-3 gap-2 mb-4 text-center">
                                <div class="rounded-xl bg-emerald-50 py-2"><p class="text-lg font-bold text-emerald-700">{{ diff.baru.length }}</p><p class="text-[11px] text-emerald-700">masuk baru</p></div>
                                <div class="rounded-xl bg-amber-50 py-2"><p class="text-lg font-bold text-amber-700">{{ diff.pindah.length }}</p><p class="text-[11px] text-amber-700">dipindah ke sini</p></div>
                                <div class="rounded-xl bg-red-50 py-2"><p class="text-lg font-bold text-red-600">{{ diff.keluar.length }}</p><p class="text-[11px] text-red-600">keluar</p></div>
                            </div>

                            <div v-if="['tahfidz','tahsin'].includes(atur.jenis) && (diff.pindah.length || diff.baru.length)"
                                class="mb-3 rounded-xl bg-sky-50 border border-sky-100 px-3 py-2 text-[11px] text-sky-800 leading-snug">
                                <b>Pencapaian tidak berubah karena pindah kelas.</b>
                                Hafalan tahfidz lanjut dari capaian terakhir; santri dari tahsin memulai hafalan tahfidz baru
                                (nilai tahsinnya tetap tersimpan); level tahsin santri yang sudah punya nilai tidak diubah.
                            </div>

                            <div v-if="diff.pindah.length" class="mb-3">
                                <p class="text-xs font-semibold text-gray-700 mb-1">Dipindah ke {{ atur.nama }}</p>
                                <ul class="text-xs text-gray-600 space-y-1">
                                    <li v-for="s in diff.pindah" :key="s.id">
                                        {{ s.nama }} <span class="text-gray-400">— dari {{ s.kelas_lain }}</span>
                                        <span v-if="catatanPencapaian(s)" class="block text-[11px] text-emerald-700">↳ {{ catatanPencapaian(s) }}</span>
                                    </li>
                                </ul>
                            </div>
                            <div v-if="diff.baru.length" class="mb-3">
                                <p class="text-xs font-semibold text-gray-700 mb-1">Masuk baru</p>
                                <ul class="text-xs text-gray-600 space-y-1">
                                    <li v-for="s in diff.baru" :key="s.id">
                                        {{ s.nama }}
                                        <span v-if="catatanPencapaian(s)" class="block text-[11px] text-emerald-700">↳ {{ catatanPencapaian(s) }}</span>
                                    </li>
                                </ul>
                            </div>
                            <div v-if="diff.keluar.length" class="mb-3 rounded-xl bg-red-50 border border-red-100 p-3">
                                <p class="text-xs font-semibold text-red-700 mb-1">⚠ Keluar dan TIDAK punya {{ aturData?.kelas?.slot_label }} lagi</p>
                                <p class="text-xs text-red-600">{{ diff.keluar.map(s => s.nama).join(', ') }}</p>
                                <p class="text-[11px] text-red-500 mt-1">Santri ini tidak akan muncul di absensi kelas mana pun sampai dimasukkan ke kelas lain.</p>
                            </div>

                            <label class="block text-xs font-medium text-gray-600 mb-1 mt-2">Berlaku mulai</label>
                            <input v-model="aturTanggal" type="date" :class="inputCls()" />
                            <p class="text-[11px] text-gray-500 mt-2 bg-gray-50 rounded-lg px-3 py-2">
                                Data lama <b>tidak dihapus</b> — keanggotaan sebelumnya tersimpan sebagai riwayat, sehingga absensi & laporan yang sudah ada tetap tercatat di kelas lama.
                                <template v-if="['tahfidz','tahsin'].includes(atur.jenis)"> Pengampu kelas yang terdampak akan dikabari.</template>
                            </p>
                        </template>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t border-gray-100 flex items-center gap-2">
                        <p v-if="!aturKonfirmasi" class="flex-1 text-xs text-gray-500">
                            <template v-if="adaPerubahan">
                                <span class="text-emerald-600 font-semibold">+{{ diff.baru.length + diff.pindah.length }} masuk</span>
                                <span v-if="diff.pindah.length" class="text-amber-600"> ({{ diff.pindah.length }} pindah)</span>
                                · <span class="text-red-600 font-semibold">−{{ diff.keluar.length }} keluar</span>
                            </template>
                            <template v-else>Belum ada perubahan.</template>
                        </p>
                        <template v-if="!aturKonfirmasi">
                            <button @click="atur = null" class="px-4 py-2.5 rounded-xl bg-gray-100 text-gray-600 text-sm font-semibold">Batal</button>
                            <button @click="aturKonfirmasi = true" :disabled="!adaPerubahan || aturLoading"
                                class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-50">Tinjau Perubahan</button>
                        </template>
                        <template v-else>
                            <button @click="aturKonfirmasi = false" :disabled="aturSaving" class="flex-1 py-2.5 rounded-xl bg-gray-100 text-gray-600 text-sm font-semibold">Kembali</button>
                            <button @click="simpanAtur" :disabled="aturSaving"
                                class="flex-1 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold disabled:opacity-50">
                                {{ aturSaving ? 'Menyimpan…' : 'Simpan Perubahan' }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { confirm } from '@/composables/useConfirm'

const props = defineProps({
    kelas: { type: Array, default: () => [] },
    tahunAjaran: { type: Array, default: () => [] },
    guru: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
})

const filters = [
    { val: 'semua', label: 'Semua' },
    { val: 'sekolah', label: 'Sekolah' },
    { val: 'tahfidz', label: 'Tahfidz' },
    { val: 'tahsin', label: 'Tahsin' },
]
const filter = ref('semua')
const filtered = computed(() =>
    filter.value === 'semua' ? props.kelas : props.kelas.filter(k => k.jenis === filter.value)
)

const showForm = ref(false)
const editTarget = ref(null)
const saving = ref(false)
const blank = () => ({ nama: '', nama_deskriptif: '', jenis: 'sekolah', level_tahsin: null, tingkat: '', tahun_ajaran_id: null, wali_kelas_id: null, errors: {} })
const form = reactive(blank())

function openCreate() {
    editTarget.value = null
    Object.assign(form, blank())
    showForm.value = true
}
function openEdit(k) {
    editTarget.value = k
    Object.assign(form, {
        nama: k.nama, nama_deskriptif: k.nama_deskriptif ?? '', jenis: k.jenis, level_tahsin: k.level_tahsin ?? null, tingkat: k.tingkat ?? '',
        tahun_ajaran_id: k.tahun_ajaran_id ?? null, wali_kelas_id: k.wali_kelas_id ?? null, errors: {},
    })
    showForm.value = true
}
function closeForm() {
    showForm.value = false
    editTarget.value = null
    Object.assign(form, blank())
}
function submit() {
    saving.value = true
    const payload = {
        nama: form.nama, nama_deskriptif: form.jenis === 'sekolah' ? form.nama_deskriptif : null, jenis: form.jenis,
        level_tahsin: form.jenis === 'tahsin' ? form.level_tahsin : null,
        tingkat: form.tingkat,
        tahun_ajaran_id: form.tahun_ajaran_id, wali_kelas_id: form.wali_kelas_id,
    }
    const opts = {
        preserveScroll: true,
        onSuccess: () => closeForm(),
        onError: (e) => { form.errors = e },
        onFinish: () => saving.value = false,
    }
    if (editTarget.value) router.put(route('admin.smart-education.kelas.update', editTarget.value.id), payload, opts)
    else router.post(route('admin.smart-education.kelas.store'), payload, opts)
}
async function aktifkan(k) {
    router.patch(route('admin.smart-education.kelas.aktifkan', k.id), {}, { preserveScroll: true })
}
async function hapus(k) {
    if (!(await confirm({ title: `Nonaktifkan kelas "${k.nama}"?`, variant: 'danger', confirmLabel: 'Ya, Nonaktifkan' }))) return
    router.delete(route('admin.smart-education.kelas.destroy', k.id), { preserveScroll: true })
}
function inputCls(e) {
    const b = 'w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 transition-all bg-white'
    return e ? `${b} border-red-300 focus:ring-red-100` : `${b} border-gray-200 focus:border-indigo-500 focus:ring-indigo-100`
}

// ── Naik / Pindah Kelas ──────────────────────────────────────────────────────
const naikSumber = ref(null)          // kelas sumber
const naikTujuanId = ref(null)
const naikTanggal = ref(new Date().toISOString().slice(0, 10))
const naikSantri = ref([])            // santri aktif di sumber
const kecuali = ref([])               // santri_id yang tinggal kelas
const naikLoading = ref(false)
const naikSaving = ref(false)

// Kelas tujuan yang valid: satu slot (sekolah↔sekolah, tahfidz/tahsin↔tahfidz/tahsin)
// & bukan diri sendiri — Persiapan Tahfidz bisa langsung dinaikkan ke kelas tahfidz.
const slotOf = (jenis) => (jenis === 'sekolah' ? 'sekolah' : 'quran')
const kelasTujuanOpsi = computed(() =>
    props.kelas.filter(k => naikSumber.value && slotOf(k.jenis) === slotOf(naikSumber.value.jenis) && k.id !== naikSumber.value.id && k.is_aktif))

async function openNaik(k) {
    naikSumber.value = k; naikTujuanId.value = null; kecuali.value = []
    naikTanggal.value = new Date().toISOString().slice(0, 10)
    naikSantri.value = []; naikLoading.value = true
    try {
        const res = await fetch(route('admin.smart-education.kelas.santri', k.id), { headers: { Accept: 'application/json' } })
        naikSantri.value = (await res.json()).data ?? []
    } catch (_) {/* */ } finally { naikLoading.value = false }
}
// ── Atur Santri (centang) ─────────────────────────────────────────────────────
const atur = ref(null)              // kelas yang sedang diatur
const aturData = ref(null)          // { kelas, saran_jk, santri: [...] }
const aturLoading = ref(false)
const aturSaving = ref(false)
const aturKonfirmasi = ref(false)
const aturQ = ref('')
const aturJk = ref('semua')
const aturStatus = ref('semua')
const aturTanggal = ref(new Date().toISOString().slice(0, 10))
const pilih = ref(new Set())

async function openAtur(k) {
    atur.value = k; aturData.value = null; aturKonfirmasi.value = false
    aturQ.value = ''; aturStatus.value = 'semua'; aturJk.value = 'semua'
    aturTanggal.value = new Date().toISOString().slice(0, 10)
    pilih.value = new Set(); aturLoading.value = true
    try {
        const res = await fetch(route('admin.smart-education.kelas.atur-santri.data', k.id), { headers: { Accept: 'application/json' } })
        aturData.value = (await res.json()).data
        pilih.value = new Set(aturData.value.santri.filter(s => s.anggota).map(s => s.id))
        // Kelas Putra/Putri → langsung saring jenis kelamin yang relevan.
        if (aturData.value.saran_jk) aturJk.value = aturData.value.saran_jk
    } catch (_) {
        atur.value = null
    } finally { aturLoading.value = false }
}

const semuaSantri = computed(() => aturData.value?.santri ?? [])
const cocokCari = (s) => {
    const q = aturQ.value.trim().toLowerCase()
    return (!q || s.nama.toLowerCase().includes(q) || (s.nip || '').toLowerCase().includes(q))
        && (aturJk.value === 'semua' || s.jenis_kelamin === aturJk.value)
}
const aturFilters = computed(() => {
    const dasar = semuaSantri.value.filter(cocokCari)
    return [
        { val: 'semua',   label: 'Semua',                   n: dasar.length },
        { val: 'anggota', label: 'Anggota kelas ini',       n: dasar.filter(s => s.anggota).length },
        { val: 'tanpa',   label: 'Belum punya kelas',       n: dasar.filter(s => !s.anggota && !s.kelas_lain).length },
        { val: 'lain',    label: 'Di kelas lain',           n: dasar.filter(s => s.kelas_lain).length },
    ]
})
const daftarAtur = computed(() => semuaSantri.value.filter(cocokCari).filter(s =>
    aturStatus.value === 'anggota' ? s.anggota
        : aturStatus.value === 'tanpa' ? (!s.anggota && !s.kelas_lain)
        : aturStatus.value === 'lain' ? !!s.kelas_lain
        : true))

function toggle(id) {
    const baru = new Set(pilih.value)
    baru.has(id) ? baru.delete(id) : baru.add(id)
    pilih.value = baru
}
function centangSemua(nyala) {
    const baru = new Set(pilih.value)
    daftarAtur.value.forEach(s => nyala ? baru.add(s.id) : baru.delete(s.id))
    pilih.value = baru
}

const dipilih = computed(() => [...pilih.value])
const diff = computed(() => {
    const s = semuaSantri.value
    return {
        baru:   s.filter(x => !x.anggota && pilih.value.has(x.id) && !x.kelas_lain),
        pindah: s.filter(x => !x.anggota && pilih.value.has(x.id) && x.kelas_lain),
        keluar: s.filter(x => x.anggota && !pilih.value.has(x.id)),
    }
})
// Pencapaian yang dibawa santri ke kelas ini — sama dengan aturan di server
// (KenaikanKelasService / Santri::tempatkanLevelTahsin).
const labelLevel = (lv) => (lv === 6 ? 'Persiapan Tahfidz' : `Level ${lv ?? '?'}`)
function catatanPencapaian(s) {
    const k = aturData.value?.kelas
    if (!k) return null
    if (k.jenis === 'tahfidz') {
        if (s.hafalan_ayat > 0) return `hafalan lanjut dari capaian terakhir (${s.hafalan_ayat} ayat)`
        return s.progres_tahsin
            ? `mulai hafalan tahfidz baru · nilai tahsin (${labelLevel(s.tahsin_level)}) tetap tersimpan`
            : 'mulai hafalan tahfidz baru'
    }
    if (k.jenis === 'tahsin') {
        const hafalan = s.hafalan_ayat > 0 ? ` · hafalan tahfidz ${s.hafalan_ayat} ayat tetap tersimpan` : ''
        if (s.progres_tahsin) {
            const beda = k.level_tahsin && s.tahsin_level !== k.level_tahsin ? ` (kelas ${labelLevel(k.level_tahsin)})` : ''
            return `level tetap ${labelLevel(s.tahsin_level)}${beda}, lanjut dari nilai sebelumnya${hafalan}`
        }
        return `ditempatkan di ${labelLevel(k.level_tahsin)} (belum punya nilai tahsin)${hafalan}`
    }
    return null
}

const adaPerubahan = computed(() => diff.value.baru.length + diff.value.pindah.length + diff.value.keluar.length > 0)
const perubahanBaris = (s) => (s.anggota !== pilih.value.has(s.id) ? 'bg-indigo-50/40' : '')

function simpanAtur() {
    aturSaving.value = true
    router.post(route('admin.smart-education.kelas.atur-santri', atur.value.id),
        { santri_ids: dipilih.value, tanggal: aturTanggal.value },
        {
            preserveScroll: true,
            onSuccess: () => { atur.value = null },
            onFinish: () => { aturSaving.value = false },
        })
}

function toggleKecuali(id) { const i = kecuali.value.indexOf(id); i >= 0 ? kecuali.value.splice(i, 1) : kecuali.value.push(id) }
function submitNaik() {
    if (!naikTujuanId.value) return
    naikSaving.value = true
    router.post(route('admin.smart-education.kelas.naik-kelas', naikSumber.value.id),
        { kelas_tujuan_id: naikTujuanId.value, kecuali: kecuali.value, tanggal: naikTanggal.value },
        { preserveScroll: true, onFinish: () => { naikSaving.value = false }, onSuccess: () => { naikSumber.value = null } })
}
</script>

<style scoped>
.modal-enter-active { transition: all 0.2s ease; }
.modal-leave-active { transition: all 0.15s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>

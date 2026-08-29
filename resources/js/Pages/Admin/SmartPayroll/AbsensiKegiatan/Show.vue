<template>
    <AdminLayout :title="kegiatan.nama_kegiatan" subtitle="Absensi Kegiatan">

        <Head :title="kegiatan.nama_kegiatan" />

        <!-- Header -->
        <div class="flex items-center gap-4 mb-6">
            <Link :href="route('admin.smart-payroll.absensi-kegiatan.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div class="flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xl font-semibold text-gray-900">{{ kegiatan.nama_kegiatan }}</h2>
                    <StatusBadge :status="kegiatan.status" />
                </div>
                <p class="text-sm text-gray-400 mt-0.5">
                    {{ kegiatan.tanggal_label }}
                    <span v-if="kegiatan.jam_mulai"> · {{ kegiatan.jam_mulai }}{{ kegiatan.jam_selesai ? ' – ' +
                        kegiatan.jam_selesai : '' }}</span>
                    <span v-if="kegiatan.lokasi"> · {{ kegiatan.lokasi }}</span>
                </p>
            </div>
            <!-- Tombol aksi -->
            <div class="flex items-center gap-2">
                <button v-if="kegiatan.status === 'berlangsung'" @click="showEdit = true"
                    class="px-3 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-xl hover:bg-gray-200 transition">
                    Edit
                </button>
                <button v-if="kegiatan.status !== 'selesai'" @click="hapusKegiatan"
                    class="p-2 rounded-xl text-gray-400 hover:text-red-600 hover:bg-red-50 transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
                <div v-if="kegiatan.status === 'berlangsung'" class="flex flex-col items-end gap-1">
                    <button @click="konfirmasiSelesai = true"
                        class="px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-xl hover:bg-emerald-700 transition">
                        Selesaikan & Distribusi Vakasi
                    </button>
                    <p v-if="kegiatan.vakasi_per_peserta" class="text-xs text-gray-400">
                        {{ hadir }} peserta × {{ formatRp(kegiatan.vakasi_per_peserta) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Flash messages -->
        <div v-if="$page.props.flash?.success"
            class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ $page.props.flash.success }}
        </div>
        <div v-if="$page.props.flash?.error"
            class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            {{ $page.props.flash.error }}
        </div>

        <!-- Info tugas sumber -->
        <div v-if="tugas_sumber"
            class="mb-4 flex items-center gap-3 px-4 py-3 bg-violet-50 border border-violet-200 rounded-xl">
            <MonitorIcon :name="tugas_sumber.tipe === 'tugas_jabatan' ? 'document' : 'clipboard'" class="text-violet-500 shrink-0" />
            <div class="flex-1">
                <p class="text-xs text-violet-400 font-medium">
                    {{ tugas_sumber.tipe === 'tugas_jabatan' ? 'Tugas Jabatan' : 'Tugas Tambahan' }}
                    {{ tugas_sumber.jabatan ? '— ' + tugas_sumber.jabatan : '' }}
                </p>
                <p class="text-sm font-semibold text-violet-800">{{ tugas_sumber.nama }}</p>
            </div>
            <a :href="tugas_sumber.url" class="text-xs text-violet-600 hover:underline font-medium shrink-0">
                Lihat Tugas
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Kolom kiri: Info + Statistik + Tambah Peserta -->
            <div class="space-y-4">

                <!-- Info Kegiatan -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-3">
                    <p class="text-sm font-semibold text-gray-800">Info Kegiatan</p>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Pengabsen</span>
                            <span class="font-medium">{{ kegiatan.pengabsen.nama }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Jabatan</span>
                            <span class="font-medium">{{ kegiatan.pengabsen.jabatan }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Vakasi/Peserta</span>
                            <span class="font-bold text-indigo-700">
                                {{ kegiatan.vakasi_per_peserta ? formatRp(kegiatan.vakasi_per_peserta) : 'Belum diset'
                                }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Vakasi Pengabsen + Peserta -->
                <div v-if="kegiatan.vakasi_per_peserta"
                    class="bg-white rounded-2xl border border-violet-200 overflow-hidden">
                    <div class="bg-violet-600 px-4 py-2.5">
                        <p class="text-xs font-semibold text-white">Distribusi Vakasi</p>
                    </div>
                    <div class="p-4 space-y-2.5 text-xs">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <MonitorIcon name="user" size="sm" class="text-violet-500" />
                                <span class="text-gray-600">Pengabsen (setelah verif admin)</span>
                            </div>
                            <span class="font-bold text-violet-700">{{ formatRp(kegiatan.vakasi_per_peserta) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <MonitorIcon name="users" size="sm" class="text-violet-500" />
                                <span class="text-gray-600">Peserta hadir (otomatis saat selesai)</span>
                            </div>
                            <span class="font-bold text-violet-700">{{ formatRp(kegiatan.vakasi_per_peserta) }}</span>
                        </div>
                        <p class="text-gray-400 text-xs pt-1 border-t border-gray-100">
                            Vakasi pengabsen dicairkan setelah admin verifikasi penugasan
                        </p>
                    </div>
                </div>

                <!-- Statistik -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <p class="text-sm font-semibold text-gray-800 mb-3">Statistik Kehadiran</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div v-for="s in statItems" :key="s.label" :class="['rounded-xl p-3 text-center', s.bg]">
                            <p :class="['text-2xl font-black', s.text]">{{ s.val }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ s.label }}</p>
                        </div>
                    </div>
                    <!-- Progress bar -->
                    <div class="mt-4 h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div :style="{ width: pctHadir + '%' }"
                            class="h-full bg-emerald-500 rounded-full transition-all duration-500" />
                    </div>
                    <p class="text-xs text-center text-gray-400 mt-1">{{ pctHadir }}% hadir</p>
                </div>

                <!-- Tambah Peserta -->
                <div v-if="kegiatan.status === 'berlangsung'"
                    class="bg-white rounded-2xl border border-gray-200 p-5 space-y-3">
                    <p class="text-sm font-semibold text-gray-800">Tambah Peserta</p>

                    <!-- Toggle mode: semua / manual -->
                    <div class="flex gap-2">
                        <button type="button" @click="modeTambah = 'semua'"
                            :class="['flex-1 py-2 rounded-xl text-xs font-semibold border-2 transition inline-flex items-center justify-center gap-1.5', modeTambah === 'semua' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-500 hover:border-gray-300']">
                            <MonitorIcon name="users" size="sm" /> Semua Guru
                        </button>
                        <button type="button" @click="modeTambah = 'manual'"
                            :class="['flex-1 py-2 rounded-xl text-xs font-semibold border-2 transition inline-flex items-center justify-center gap-1.5', modeTambah === 'manual' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-500 hover:border-gray-300']">
                            <MonitorIcon name="user" size="sm" /> Pilih Manual
                        </button>
                    </div>

                    <!-- Info mode semua guru -->
                    <div v-if="modeTambah === 'semua'"
                        class="p-3 bg-blue-50 border border-blue-100 rounded-xl text-xs text-blue-700 space-y-1">
                        <p class="font-semibold">Guru yang akan ditambahkan:</p>
                        <p>Semua guru aktif <strong>kecuali</strong> yang hari ini:</p>
                        <ul class="list-disc list-inside space-y-0.5 text-blue-600 ml-1">
                            <li>Berstatus izin / sakit / alfa</li>
                            <li>Sedang libur (hari libur nasional/pesantren)</li>
                            <li>Tidak memiliki jam masuk kerja hari ini</li>
                        </ul>
                        <p class="text-blue-400 mt-1 italic">Peserta yang tidak masuk dapat diubah statusnya setelah
                            ditambahkan</p>
                    </div>

                    <!-- Pilih manual -->
                    <div v-if="modeTambah === 'manual'">
                        <select v-model="tambahIds" multiple
                            class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-400 h-28 bg-white">
                            <option v-for="g in guru_tersedia" :key="g.id" :value="g.id">
                                {{ g.nama }} — {{ g.jabatan }}
                            </option>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Ctrl+Click untuk pilih banyak</p>
                    </div>

                    <button @click="tambahPeserta" :disabled="(modeTambah === 'manual' && !tambahIds.length) || loading"
                        class="w-full py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold disabled:opacity-50 hover:bg-indigo-700 transition">
                        {{ modeTambah === 'semua'
                            ? 'Tambah Semua Guru Hadir'
                            : 'Tambah (' + tambahIds.length + ')' }}
                    </button>
                </div>

            </div>

            <!-- Kolom kanan: Tabel Absensi -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-800">
                            Daftar Kehadiran
                            <span class="text-gray-400 font-normal ml-1">({{ peserta.length }} peserta)</span>
                        </p>
                        <!-- Bulk update semua hadir -->
                        <button v-if="kegiatan.status === 'berlangsung' && peserta.length" @click="setSemuaHadir"
                            class="text-xs text-indigo-600 hover:underline">
                            Set Semua Hadir
                        </button>
                    </div>

                    <!-- Tabel -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr class="text-xs font-semibold text-gray-500 uppercase text-left">
                                    <th class="px-4 py-3">Guru</th>
                                    <th class="px-4 py-3">Status Kehadiran</th>
                                    <th class="px-4 py-3">Jam Hadir</th>
                                    <th class="px-4 py-3">Vakasi</th>
                                    <th v-if="kegiatan.status === 'berlangsung'" class="px-4 py-3">Keterangan</th>
                                    <th v-if="kegiatan.status === 'berlangsung'" class="px-3 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="p in peserta" :key="p.id" class="hover:bg-gray-50/50">
                                    <!-- Guru -->
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <img v-if="p.foto" :src="p.foto"
                                                class="w-7 h-7 rounded-full object-cover" />
                                            <div v-else
                                                class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600">
                                                {{ p.nama?.charAt(0) }}
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-800">{{ p.nama }}</p>
                                                <p class="text-xs text-gray-400">{{ p.jabatan }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Status Kehadiran -->
                                    <td class="px-4 py-3">
                                        <select v-if="kegiatan.status === 'berlangsung'"
                                            v-model="draftAbsensi[p.id].status_kehadiran"
                                            :class="['px-2 py-1 rounded-lg text-xs font-semibold border-0 focus:ring-2 focus:ring-indigo-300', kehadiranCls(draftAbsensi[p.id].status_kehadiran)]">
                                            <option value="hadir">Hadir</option>
                                            <option value="terlambat">Terlambat</option>
                                            <option value="izin">Izin</option>
                                            <option value="alfa">Alfa</option>
                                        </select>
                                        <span v-else
                                            :class="['px-2 py-0.5 rounded-lg text-xs font-semibold', kehadiranCls(p.status_kehadiran)]">
                                            {{ kehadiranLabel[p.status_kehadiran] }}
                                        </span>
                                    </td>

                                    <!-- Jam Hadir -->
                                    <td class="px-4 py-3">
                                        <input v-if="kegiatan.status === 'berlangsung'"
                                            v-model="draftAbsensi[p.id].jam_hadir" type="time"
                                            class="text-xs px-2 py-1 rounded-lg border border-gray-200 focus:outline-none focus:border-indigo-400 w-24" />
                                        <span v-else class="text-xs font-mono text-gray-600">
                                            {{ p.jam_hadir ?? '—' }}
                                        </span>
                                    </td>

                                    <!-- Vakasi -->
                                    <td class="px-4 py-3">
                                        <span v-if="p.vakasi_diberikan" class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700">
                                            <MonitorIcon name="check" size="sm" class="!w-3.5 !h-3.5" /> {{ formatRp(p.nominal_vakasi) }}
                                        </span>
                                        <span v-else-if="['hadir', 'terlambat'].includes(p.status_kehadiran)"
                                            class="text-xs text-gray-400 italic">
                                            {{ kegiatan.vakasi_per_peserta ? formatRp(kegiatan.vakasi_per_peserta) :
                                            'Saat selesai' }}
                                        </span>
                                        <span v-else class="text-gray-300">—</span>
                                    </td>

                                    <!-- Keterangan -->
                                    <td v-if="kegiatan.status === 'berlangsung'" class="px-4 py-3">
                                        <input v-model="draftAbsensi[p.id].keterangan" type="text"
                                            placeholder="Keterangan..."
                                            class="text-xs px-2 py-1 rounded-lg border border-gray-200 focus:outline-none focus:border-indigo-400 w-28" />
                                    </td>
                                    <!-- Hapus peserta -->
                                    <td v-if="kegiatan.status === 'berlangsung'" class="px-3 py-3">
                                        <button @click="hapusPeserta(p.id, p.nama)"
                                            class="p-1 rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 transition"
                                            title="Hapus dari daftar">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!peserta.length">
                                    <td :colspan="kegiatan.status === 'berlangsung' ? 5 : 4"
                                        class="py-12 text-center text-sm text-gray-400">
                                        Belum ada peserta. Tambah dari panel kiri.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Tombol simpan absensi -->
                    <div v-if="kegiatan.status === 'berlangsung' && peserta.length"
                        class="px-5 py-4 border-t border-gray-100 flex justify-end">
                        <button @click="simpanAbsensi" :disabled="loading"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition disabled:opacity-60">
                            {{ loading ? 'Menyimpan...' : 'Simpan Absensi' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Edit Kegiatan -->
        <Teleport to="body">
            <div v-if="showEdit" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl p-6 max-w-md w-full shadow-2xl">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Edit Info Kegiatan</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kegiatan *</label>
                            <input v-model="editForm.nama_kegiatan" type="text"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-violet-400" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal *</label>
                                <input v-model="editForm.tanggal_kegiatan" type="date"
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-violet-400" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Jam Mulai</label>
                                <input v-model="editForm.jam_mulai" type="time"
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-violet-400" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Jam Selesai</label>
                                <input v-model="editForm.jam_selesai" type="time"
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-violet-400" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Vakasi/Orang (Rp)</label>
                                <input v-model="editForm.vakasi_per_peserta" type="number" min="0"
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-violet-400" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi</label>
                            <input v-model="editForm.lokasi" type="text" placeholder="cth: Aula Pesantren"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-violet-400" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                            <textarea v-model="editForm.deskripsi" rows="2" placeholder="Opsional..."
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-violet-400 resize-none" />
                        </div>
                    </div>
                    <div class="flex gap-3 mt-5">
                        <button @click="showEdit = false"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                            Batal
                        </button>
                        <button @click="simpanEdit" :disabled="!editForm.nama_kegiatan || !editForm.tanggal_kegiatan || loading"
                            class="flex-1 py-2.5 rounded-xl bg-violet-600 text-white text-sm font-semibold hover:bg-violet-700 disabled:opacity-50 transition">
                            {{ loading ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal konfirmasi selesai -->
        <Teleport to="body">
            <div v-if="konfirmasiSelesai" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl p-6 max-w-sm w-full shadow-2xl">
                    <div class="text-center mb-4">
                        <div
                            class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <MonitorIcon name="check" class="text-emerald-600 !w-8 !h-8" />
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Selesaikan Kegiatan?</h3>
                        <div class="mt-2 text-left bg-gray-50 rounded-xl p-3 space-y-1.5 text-xs text-gray-600">
                            <p class="font-semibold text-gray-700 mb-1">Yang akan terjadi:</p>
                            <div class="flex items-start gap-2">
                                <MonitorIcon name="check" size="sm" class="text-emerald-500 shrink-0 mt-0.5" />
                                <span>Vakasi <strong class="text-violet-700">{{ formatRp(kegiatan.vakasi_per_peserta ??
                                        0) }}</strong> ke <strong>{{ hadir }} peserta</strong> hadir</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <MonitorIcon name="check" size="sm" class="text-blue-500 shrink-0 mt-0.5" />
                                <span>Tugas pengabsen ditandai selesai, menunggu verifikasi admin</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <MonitorIcon name="clock" size="sm" class="text-amber-500 shrink-0 mt-0.5" />
                                <span>Vakasi pengabsen cair setelah admin verifikasi di halaman tugas</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button @click="konfirmasiSelesai = false"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                            Batal
                        </button>
                        <button @click="selesaikan" :disabled="loading"
                            class="flex-1 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 disabled:opacity-60 transition">
                            {{ loading ? 'Memproses...' : 'Ya, Selesaikan' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <AppConfirm ref="confirm" />
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppConfirm from '@/Components/AppConfirm.vue'
import StatusBadge from '@/Components/Monitor/StatusBadge.vue'
import MonitorIcon from '@/Components/Monitor/MonitorIcon.vue'

const confirm = ref(null)

const props = defineProps({
    kegiatan: { type: Object, default: () => ({}) },
    tugas_sumber: { type: Object, default: null },
    peserta: { type: Array, default: () => [] },
    guru_tersedia: { type: Array, default: () => [] },
    vakasi: { type: Array, default: () => [] },
})

// ─── Draft absensi (editable) ─────────────────────────────────────────────────
const draftAbsensi = reactive({})
props.peserta.forEach(p => {
    draftAbsensi[p.id] = {
        id: p.id,
        status_kehadiran: p.status_kehadiran,
        jam_hadir: p.jam_hadir ?? '',
        keterangan: p.keterangan ?? '',
    }
})

// ─── State ────────────────────────────────────────────────────────────────────
const loading = ref(false)
const konfirmasiSelesai = ref(false)
const tambahIds = ref([])
const modeTambah = ref('semua') // 'semua' | 'manual'

// Edit kegiatan
const showEdit = ref(false)
const editForm = reactive({
    nama_kegiatan:    props.kegiatan.nama_kegiatan ?? '',
    tanggal_kegiatan: props.kegiatan.tanggal_kegiatan ?? '',
    jam_mulai:        props.kegiatan.jam_mulai ?? '',
    jam_selesai:      props.kegiatan.jam_selesai ?? '',
    lokasi:           props.kegiatan.lokasi ?? '',
    deskripsi:        props.kegiatan.deskripsi ?? '',
    vakasi_per_peserta: props.kegiatan.vakasi_per_peserta ?? '',
})

// ─── Statistik ────────────────────────────────────────────────────────────────
const hadir = computed(() => Object.values(draftAbsensi).filter(d => ['hadir', 'terlambat'].includes(d.status_kehadiran)).length)
// Expose hadir untuk template
const izin = computed(() => Object.values(draftAbsensi).filter(d => d.status_kehadiran === 'izin').length)
const alfa = computed(() => Object.values(draftAbsensi).filter(d => d.status_kehadiran === 'alfa').length)
const pctHadir = computed(() => props.peserta.length ? Math.round(hadir.value / props.peserta.length * 100) : 0)

const statItems = computed(() => [
    { label: 'Hadir', val: hadir.value, bg: 'bg-emerald-50', text: 'text-emerald-600' },
    { label: 'Izin', val: izin.value, bg: 'bg-blue-50', text: 'text-blue-600' },
    { label: 'Alfa', val: alfa.value, bg: 'bg-red-50', text: 'text-red-600' },
    { label: 'Total', val: props.peserta.length, bg: 'bg-gray-50', text: 'text-gray-700' },
])

// ─── Actions ──────────────────────────────────────────────────────────────────
function tambahPeserta() {
    if (modeTambah.value === 'manual' && !tambahIds.value.length) return
    loading.value = true

    const payload = modeTambah.value === 'semua'
        ? { semua_guru: true }
        : { tenaga_pendidik_ids: tambahIds.value }

    router.post(
        route('admin.smart-payroll.absensi-kegiatan.tambah-peserta', props.kegiatan.id),
        payload,
        { preserveScroll: true, onFinish: () => { loading.value = false; tambahIds.value = [] } }
    )
}

function setSemuaHadir() {
    Object.values(draftAbsensi).forEach(d => d.status_kehadiran = 'hadir')
}

function simpanAbsensi() {
    loading.value = true
    const payload = Object.values(draftAbsensi).map(d => ({
        id: d.id,
        status_kehadiran: d.status_kehadiran,
        jam_hadir: d.jam_hadir || null,
        keterangan: d.keterangan || null,
    }))
    router.patch(
        route('admin.smart-payroll.absensi-kegiatan.update-bulk', props.kegiatan.id),
        { absensi: payload },
        { preserveScroll: true, onFinish: () => { loading.value = false } }
    )
}

function selesaikan() {
    loading.value = true
    router.post(
        route('admin.smart-payroll.absensi-kegiatan.selesaikan', props.kegiatan.id),
        {},
        { onFinish: () => { loading.value = false; konfirmasiSelesai.value = false } }
    )
}

function simpanEdit() {
    if (!editForm.nama_kegiatan || !editForm.tanggal_kegiatan) return
    loading.value = true
    router.patch(
        route('admin.smart-payroll.absensi-kegiatan.update', props.kegiatan.id),
        {
            nama_kegiatan:    editForm.nama_kegiatan,
            tanggal_kegiatan: editForm.tanggal_kegiatan,
            jam_mulai:        editForm.jam_mulai || null,
            jam_selesai:      editForm.jam_selesai || null,
            lokasi:           editForm.lokasi || null,
            deskripsi:        editForm.deskripsi || null,
            vakasi_per_peserta: editForm.vakasi_per_peserta || null,
        },
        {
            preserveScroll: false,
            onSuccess: () => { showEdit.value = false },
            onFinish: () => { loading.value = false },
        }
    )
}

function hapusPeserta(id, nama) {
    confirm.value.ask(
        { title: 'Hapus Peserta?', message: `"${nama}" akan dikeluarkan dari daftar peserta kegiatan ini.`,
          variant: 'danger', confirmLabel: 'Ya, Hapus' },
        (done) => router.post(route('admin.smart-payroll.absensi-kegiatan.hapus-peserta', id), {},
            { preserveScroll: true, onFinish: done }),
    )
}

function hapusKegiatan() {
    confirm.value.ask(
        { title: 'Hapus Kegiatan?', message: `Kegiatan "${props.kegiatan.nama_kegiatan}" beserta seluruh data absensi peserta akan dihapus permanen.`,
          variant: 'danger', confirmLabel: 'Ya, Hapus', irreversible: true },
        (done) => router.post(route('admin.smart-payroll.absensi-kegiatan.destroy', props.kegiatan.id), {},
            { onFinish: done }),
    )
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
const statusLabel = { berlangsung: 'Berlangsung', selesai: 'Selesai', dibatalkan: 'Dibatalkan' }
const statusCls = (s) => ({
    berlangsung: 'bg-emerald-100 text-emerald-700',
    selesai: 'bg-blue-100 text-blue-700',
    dibatalkan: 'bg-red-100 text-red-600',
})[s] ?? 'bg-gray-100 text-gray-500'

const kehadiranLabel = { hadir: 'Hadir', terlambat: 'Terlambat', izin: 'Izin', alfa: 'Alfa' }
const kehadiranCls = (s) => ({
    hadir: 'bg-emerald-100 text-emerald-700',
    terlambat: 'bg-amber-100 text-amber-700',
    izin: 'bg-blue-100 text-blue-700',
    alfa: 'bg-red-100 text-red-600',
})[s] ?? 'bg-gray-100 text-gray-500'

function formatRp(n) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n ?? 0)
}
</script>
<template>
    <AdminLayout :title="tugas.judul" subtitle="Tugas Tambahan">

        <Head :title="tugas.judul" />

        <!-- Header -->
        <div class="flex items-center gap-4 mb-6">
            <Link :href="route('admin.smart-payroll.tugas-tambahan.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div class="flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xl font-semibold text-gray-900">{{ tugas.judul }}</h2>
                    <StatusBadge :status="tugas.status" />
                    <!-- Badge tipe pengerjaan -->
                    <span
                        :class="['px-2.5 py-1 rounded-full text-xs font-semibold', tugas.tipe_pengerjaan === 'absen_kegiatan' ? 'bg-violet-100 text-violet-700' : 'bg-blue-100 text-blue-700']">
                        {{ tugas.tipe_pengerjaan === 'absen_kegiatan' ? 'Absen Kegiatan' : 'Mandiri' }}
                    </span>
                </div>
                <p class="text-sm text-gray-400 mt-0.5">{{ tugas.deskripsi ?? 'Tidak ada deskripsi.' }}</p>
            </div>
            <!-- Tombol untuk tipe absen_kegiatan -->
            <div v-if="tugas.tipe_pengerjaan === 'absen_kegiatan'" class="flex gap-2">
                <Link :href="route('admin.smart-payroll.absensi-kegiatan.index')"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-violet-100 text-violet-700 text-sm font-semibold rounded-xl hover:bg-violet-200 transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    Semua Kegiatan
                </Link>
            </div>
        </div>

        <!-- Banner Workflow absen_kegiatan -->
        <div v-if="tugas.tipe_pengerjaan === 'absen_kegiatan'"
            class="mb-4 bg-violet-50 border border-violet-200 rounded-2xl p-4">
            <p class="text-sm font-semibold text-violet-800 mb-2">Alur Kerja Absensi Kegiatan</p>
            <div class="flex items-start gap-2 flex-wrap">
                <div v-for="(step, i) in workflowSteps" :key="i"
                    class="flex items-center gap-1.5 text-xs text-violet-700">
                    <div
                        class="w-5 h-5 rounded-full bg-violet-600 text-white flex items-center justify-center text-xs font-bold shrink-0">
                        {{ i + 1 }}
                    </div>
                    <span>{{ step }}</span>
                    <span v-if="i < workflowSteps.length - 1" class="text-violet-300 ml-1">→</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Kolom kiri: Info + Progress + Assign -->
            <div class="lg:col-span-1 space-y-4">

                <!-- Info Tugas -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-3">
                    <p class="text-sm font-semibold text-gray-800">Info Tugas</p>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Tipe Penerima</span>
                            <span class="font-medium capitalize">{{ tugas.tipe }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Tipe Pengerjaan</span>
                            <span class="font-medium">
                                {{ tugas.tipe_pengerjaan === 'absen_kegiatan' ? 'Absen Kegiatan' : 'Mandiri' }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Tanggal Mulai</span>
                            <span class="font-medium">{{ tugas.tanggal_mulai }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Batas Selesai</span>
                            <span class="font-medium">{{ tugas.tanggal_selesai ?? 'Tidak ada' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Vakasi Default</span>
                            <span class="font-bold text-indigo-700">
                                {{ tugas.nominal_vakasi > 0 ? formatRp(tugas.nominal_vakasi) : 'Tanpa Vakasi' }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Wajib Laporan</span>
                            <span :class="['font-medium', tugas.wajib_laporan ? 'text-orange-600' : 'text-gray-500']">
                                {{ tugas.wajib_laporan ? 'Ya' : 'Tidak' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Info vakasi peserta (absen_kegiatan) -->
                <div v-if="tugas.tipe_pengerjaan === 'absen_kegiatan'"
                    class="bg-white rounded-2xl border border-violet-200 overflow-hidden">
                    <div class="bg-violet-600 px-5 py-3">
                        <p class="text-sm font-semibold text-white">Skema Vakasi Kegiatan</p>
                        <p class="text-xs text-violet-200 mt-0.5">Otomatis saat kegiatan diselesaikan</p>
                    </div>
                    <div class="p-4 space-y-3 text-xs">
                        <div class="flex items-start gap-2">
                            <MonitorIcon name="user" size="sm" class="shrink-0 text-violet-500 mt-0.5" />
                            <div>
                                <p class="font-semibold text-gray-700">Guru Pengabsen</p>
                                <p class="text-gray-500 mt-0.5">
                                    Masuk ke <span class="font-mono bg-gray-100 px-1 rounded">vakasi_tugas_tambahan</span>
                                    di slip gaji — otomatis saat kegiatan selesai
                                </p>
                                <p v-if="tugas.nominal_vakasi > 0" class="font-bold text-violet-700 mt-0.5">
                                    {{ formatRp(tugas.nominal_vakasi) }}
                                </p>
                                <p v-else class="text-gray-400 italic mt-0.5">Nominal dari setting per penerima</p>
                            </div>
                        </div>
                        <div class="h-px bg-violet-100" />
                        <div class="flex items-start gap-2">
                            <MonitorIcon name="users" size="sm" class="shrink-0 text-violet-500 mt-0.5" />
                            <div>
                                <p class="font-semibold text-gray-700">Peserta yang Hadir</p>
                                <p class="text-gray-500 mt-0.5">
                                    Masuk ke <span class="font-mono bg-gray-100 px-1 rounded">vakasi_peserta_kegiatan</span>
                                    di slip gaji — distribusi otomatis per orang
                                </p>
                                <p v-if="tugas.nominal_vakasi > 0" class="font-bold text-violet-700 mt-0.5">
                                    {{ formatRp(tugas.nominal_vakasi) }} / orang
                                </p>
                                <p v-else class="text-gray-400 italic mt-0.5">Ditetapkan saat buat kegiatan</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progress -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-3">
                    <p class="text-sm font-semibold text-gray-800">Progress</p>
                    <div v-for="s in progressItems" :key="s.label" class="flex justify-between items-center text-sm">
                        <span class="text-gray-500">{{ s.label }}</span>
                        <span :class="['font-bold', s.color]">{{ s.value }}</span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div :style="{ width: progressPct + '%' }"
                            class="h-full bg-emerald-500 rounded-full transition-all duration-500" />
                    </div>
                    <p class="text-xs text-center text-gray-400">{{ progressPct }}% selesai</p>
                </div>

                <!-- Assign penerima / pengabsen baru -->
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <!-- Header kartu -->
                    <div :class="['px-4 py-3 border-b border-gray-100 flex items-center gap-2',
                        tugas.tipe_pengerjaan === 'absen_kegiatan' ? 'bg-indigo-50' : 'bg-gray-50']">
                        <div :class="['w-7 h-7 rounded-lg flex items-center justify-center shrink-0',
                            tugas.tipe_pengerjaan === 'absen_kegiatan' ? 'bg-indigo-100' : 'bg-gray-200']">
                            <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800">
                                {{ tugas.tipe_pengerjaan === 'absen_kegiatan' ? 'Tambah Guru Pengabsen' : 'Tambah Penerima' }}
                            </p>
                            <p v-if="tugas.tipe_pengerjaan === 'absen_kegiatan'"
                                class="text-xs text-indigo-500 leading-tight mt-0.5">
                                Guru bisa di-assign berulang kali untuk kegiatan berbeda
                            </p>
                        </div>
                    </div>

                    <div class="p-4 space-y-3">
                        <!-- Empty state (hanya jika belum ada guru aktif sama sekali) -->
                        <div v-if="!guruBelumAssign.length"
                            class="py-6 text-center text-xs text-gray-400 bg-gray-50 rounded-xl">
                            Belum ada guru aktif yang terdaftar
                        </div>

                        <template v-else>
                            <!-- Search -->
                            <div class="flex items-center gap-2 px-3 py-2 border border-gray-200 rounded-xl bg-gray-50">
                                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <input v-model="cariAssign" type="text" placeholder="Cari nama guru..."
                                    class="flex-1 text-xs bg-transparent focus:outline-none text-gray-700 placeholder-gray-400 min-w-0" />
                                <button v-if="cariAssign" @click="cariAssign = ''"
                                    class="text-gray-300 hover:text-gray-500">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- List guru -->
                            <div class="border border-gray-100 rounded-xl overflow-hidden max-h-52 overflow-y-auto divide-y divide-gray-50">
                                <div v-for="g in assignGuruFiltered" :key="g.id"
                                    @click="toggleAssign(g.id)"
                                    :class="['flex items-center gap-3 px-3 py-2.5 cursor-pointer transition select-none',
                                        assignIds.includes(g.id) ? 'bg-indigo-50' : 'hover:bg-gray-50']">
                                    <!-- Checkbox -->
                                    <div :class="['w-4 h-4 rounded border flex items-center justify-center shrink-0 transition',
                                        assignIds.includes(g.id)
                                            ? 'bg-indigo-600 border-indigo-600'
                                            : 'border-gray-300 bg-white']">
                                        <svg v-if="assignIds.includes(g.id)"
                                            class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <!-- Avatar -->
                                    <img v-if="g.foto" :src="g.foto" class="w-7 h-7 rounded-full object-cover shrink-0" />
                                    <div v-else
                                        class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 text-xs font-bold shrink-0">
                                        {{ g.nama?.charAt(0) }}
                                    </div>
                                    <!-- Info -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <p class="text-xs font-semibold text-gray-800 truncate">{{ g.nama }}</p>
                                            <!-- Badge berapa kali sudah di-assign -->
                                            <span v-if="assignedCountMap[g.id]"
                                                class="shrink-0 px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs font-bold leading-none"
                                                :title="`Sudah di-assign ${assignedCountMap[g.id]} kali`">
                                                {{ assignedCountMap[g.id] }}×
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-400 truncate">{{ g.jabatan }}</p>
                                    </div>
                                </div>
                                <div v-if="!assignGuruFiltered.length"
                                    class="py-5 text-center text-xs text-gray-400">
                                    Tidak ada guru ditemukan
                                </div>
                            </div>

                            <!-- Baris kontrol: counter + shortcuts -->
                            <div class="flex items-center justify-between px-1">
                                <p class="text-xs text-gray-400">
                                    <span class="font-semibold text-indigo-600">{{ assignIds.length }}</span>
                                    dipilih
                                </p>
                                <div class="flex gap-3">
                                    <button @click="pilihSemuaAssign"
                                        class="text-xs text-indigo-600 hover:underline font-medium">
                                        Pilih Semua
                                    </button>
                                    <button v-if="assignIds.length" @click="assignIds = []"
                                        class="text-xs text-gray-400 hover:underline">
                                        Reset
                                    </button>
                                </div>
                            </div>

                            <!-- Tombol Assign -->
                            <button @click="assign" :disabled="!assignIds.length || assigning"
                                :class="['w-full py-2.5 rounded-xl text-sm font-semibold transition flex items-center justify-center gap-2',
                                    assignIds.length && !assigning
                                        ? 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm shadow-indigo-200'
                                        : 'bg-gray-100 text-gray-400 cursor-not-allowed']">
                                <svg v-if="!assigning" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                </svg>
                                <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                {{ assigning ? 'Menyimpan...' : assignIds.length ? `Assign ${assignIds.length} Guru` : 'Pilih guru dulu' }}
                            </button>
                        </template>
                    </div>
                </div>

            </div>

            <!-- Kolom kanan: Tabel penugasan -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-800">
                            Daftar Penerima
                            <span class="text-gray-400 font-normal ml-1">({{ penugasan.length }} orang)</span>
                        </p>
                        <!-- Filter status -->
                        <div class="flex gap-1">
                            <button v-for="f in filterOpt" :key="f.val" @click="filterStatus = f.val"
                                :class="['px-3 py-1 rounded-lg text-xs font-semibold transition', filterStatus === f.val ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200']">
                                {{ f.label }}
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr class="text-xs font-semibold text-gray-500 uppercase text-left">
                                    <th class="px-4 py-3">Guru</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Bukti</th>
                                    <th class="px-4 py-3">Vakasi</th>
                                    <th class="px-4 py-3">Verifikasi</th>
                                    <th v-if="tugas.tipe_pengerjaan === 'absen_kegiatan'" class="px-4 py-3">Kegiatan
                                    </th>
                                    <th class="px-4 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="p in penugasanFiltered" :key="p.id" class="hover:bg-gray-50/50">
                                    <!-- Guru -->
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <img v-if="p.guru_foto" :src="p.guru_foto"
                                                class="w-7 h-7 rounded-full object-cover" />
                                            <div v-else
                                                class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 text-xs font-bold">
                                                {{ p.guru_nama?.charAt(0) }}
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-800">{{ p.guru_nama }}</p>
                                                <p class="text-xs text-gray-400">{{ p.guru_jabatan }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-4 py-3">
                                        <span
                                            :class="['px-2 py-0.5 rounded-lg text-xs font-semibold', statusPengerjaanCls(p.status_pengerjaan)]">
                                            {{ statusPengerjaanLabel[p.status_pengerjaan] ?? p.status_pengerjaan }}
                                        </span>
                                    </td>

                                    <!-- Bukti -->
                                    <td class="px-4 py-3">
                                        <div v-if="p.bukti_tipe" class="flex items-center gap-1.5">
                                            <MonitorIcon size="sm" class="text-gray-400 shrink-0"
                                                :name="p.bukti_tipe === 'foto' ? 'camera' : p.bukti_tipe === 'link' ? 'link' : 'document'" />
                                            <a v-if="p.bukti_tipe === 'link' && p.link_bukti" :href="p.link_bukti"
                                                target="_blank"
                                                class="text-xs text-indigo-600 hover:underline truncate max-w-[100px]">
                                                Lihat Link
                                            </a>
                                            <button v-else-if="p.bukti_tipe === 'foto' && p.file_laporan_url"
                                                @click="lihatFoto(p.file_laporan_url, p.guru_nama)"
                                                class="text-xs text-indigo-600 hover:underline">
                                                Lihat Foto
                                            </button>
                                            <span v-else class="text-xs text-gray-500 truncate max-w-[100px]">
                                                {{ p.teks_bukti ?? p.laporan ?? '—' }}
                                            </span>
                                        </div>
                                        <span v-else class="text-gray-300 text-sm">—</span>
                                    </td>

                                    <!-- Vakasi -->
                                    <td class="px-4 py-3 text-xs">
                                        <span v-if="p.nominal_vakasi > 0" class="font-semibold text-indigo-700">
                                            {{ formatRp(p.nominal_vakasi) }}
                                        </span>
                                        <span v-else class="text-gray-300">—</span>
                                    </td>

                                    <!-- Verifikasi -->
                                    <td class="px-4 py-3">
                                        <div v-if="p.status_pengerjaan === 'selesai'" class="flex items-center gap-1">
                                            <span v-if="p.disetujui === true"
                                                class="text-xs font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-lg">Disetujui</span>
                                            <span v-else-if="p.disetujui === false"
                                                class="text-xs font-semibold text-red-600 bg-red-50 px-2 py-0.5 rounded-lg">Ditolak</span>
                                            <span v-else
                                                class="text-xs font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-lg">Pending</span>
                                        </div>
                                        <span v-else class="text-gray-300 text-sm">—</span>
                                    </td>

                                    <!-- Kegiatan (absen_kegiatan) -->
                                    <td v-if="tugas.tipe_pengerjaan === 'absen_kegiatan'" class="px-4 py-3">
                                        <div class="space-y-1.5">
                                            <!-- Info kegiatan yang sudah ada (jika ada) -->
                                            <div v-if="p.kegiatan_id" class="space-y-1">
                                                <Link
                                                    :href="route('admin.smart-payroll.absensi-kegiatan.show', p.kegiatan_id)"
                                                    class="text-xs text-violet-600 hover:underline font-medium block truncate max-w-[140px]">
                                                    {{ p.kegiatan_nama ?? 'Lihat Kegiatan' }}
                                                </Link>
                                                <div class="flex items-center gap-1.5">
                                                    <span
                                                        :class="['px-1.5 py-0.5 rounded text-xs font-semibold', p.kegiatan_status === 'selesai' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700']">
                                                        {{ p.kegiatan_status === 'selesai' ? 'Selesai' : 'Berlangsung' }}
                                                    </span>
                                                    <span v-if="p.kegiatan_hadir !== undefined"
                                                        class="text-xs text-gray-400">
                                                        {{ p.kegiatan_hadir }}/{{ p.kegiatan_total }} hadir
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Tombol buat kegiatan: selalu tampil selama kegiatan belum berlangsung -->
                                            <button
                                                v-if="!p.kegiatan_id || p.kegiatan_status === 'selesai'"
                                                @click="bukaPilihKegiatan(p)"
                                                :class="['inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-lg transition',
                                                    p.kegiatan_id
                                                        ? 'bg-gray-100 text-gray-600 hover:bg-violet-100 hover:text-violet-700'
                                                        : 'bg-violet-600 text-white hover:bg-violet-700']">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                </svg>
                                                {{ p.kegiatan_id ? 'Kegiatan Baru' : 'Buat Kegiatan' }}
                                            </button>
                                        </div>
                                    </td>

                                    <!-- Aksi Verifikasi -->
                                    <td class="px-4 py-3">
                                        <div v-if="p.status_pengerjaan === 'selesai' && p.disetujui === null"
                                            class="flex gap-1">
                                            <button @click="verifikasi(p.id, true)"
                                                class="px-2.5 py-1 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 transition">
                                                Setujui
                                            </button>
                                            <button @click="verifikasi(p.id, false)"
                                                class="px-2.5 py-1 bg-red-500 text-white text-xs font-semibold rounded-lg hover:bg-red-600 transition">
                                                Tolak
                                            </button>
                                        </div>
                                    </td>

                                </tr>
                                <tr v-if="!penugasanFiltered.length">
                                    <td colspan="6" class="py-12 text-center text-sm text-gray-400">
                                        Belum ada penerima tugas.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Buat Kegiatan -->
        <Teleport to="body">
            <div v-if="showModalKegiatan" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full flex flex-col max-h-[90vh]">

                    <!-- Header modal (sticky) -->
                    <div class="px-6 pt-6 pb-4 border-b border-gray-100 shrink-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-bold text-gray-900">Buat Kegiatan Absensi</h3>
                            <!-- Label "Kegiatan Baru" jika penugasan sudah punya kegiatan sebelumnya -->
                            <span v-if="selectedPenugasan?.kegiatan_id"
                                class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs font-semibold">
                                Kegiatan Baru
                            </span>
                        </div>
                        <div class="flex items-center gap-1.5 mt-1">
                            <svg class="w-3.5 h-3.5 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <p class="text-sm text-violet-600 font-medium">
                                Pengabsen: {{ selectedPenugasan?.guru_nama ?? '—' }}
                            </p>
                            <span v-if="selectedPenugasan?.kegiatan_id"
                                class="text-xs text-gray-400">
                                · Akan dibuat terpisah dari kegiatan sebelumnya
                            </span>
                        </div>
                    </div>

                    <!-- Body modal (scrollable) -->
                    <div class="overflow-y-auto px-6 py-4 space-y-4 flex-1">

                        <!-- ── Info Kegiatan ── -->
                        <div class="space-y-3">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Info Kegiatan</p>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kegiatan <span class="text-red-400">*</span></label>
                                <input v-model="formKegiatan.nama_kegiatan" type="text"
                                    placeholder="cth: Briefing Mingguan Tim Branding"
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-violet-400" />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal <span class="text-red-400">*</span></label>
                                    <input v-model="formKegiatan.tanggal_kegiatan" type="date"
                                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-violet-400" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Jam Mulai</label>
                                    <input v-model="formKegiatan.jam_mulai" type="time"
                                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-violet-400" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi</label>
                                <input v-model="formKegiatan.lokasi" type="text" placeholder="cth: Aula Pesantren"
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-violet-400" />
                            </div>
                        </div>

                        <!-- ── Peserta Kegiatan ── -->
                        <div class="space-y-3 pt-1">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Peserta Kegiatan</p>

                            <!-- Mode toggle -->
                            <div class="flex gap-2 p-1 bg-gray-100 rounded-xl">
                                <button @click="pesertaMode = 'semua'; selectedPesertaIds = []"
                                    :class="['flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-sm font-semibold transition',
                                        pesertaMode === 'semua'
                                            ? 'bg-white text-violet-700 shadow-sm'
                                            : 'text-gray-500 hover:text-gray-700']">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Semua Guru
                                </button>
                                <button @click="pesertaMode = 'manual'"
                                    :class="['flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-sm font-semibold transition',
                                        pesertaMode === 'manual'
                                            ? 'bg-white text-violet-700 shadow-sm'
                                            : 'text-gray-500 hover:text-gray-700']">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    Pilih Manual
                                    <span v-if="selectedPesertaIds.length"
                                        class="ml-1 bg-violet-600 text-white text-xs font-bold rounded-full px-1.5 py-0.5 leading-none">
                                        {{ selectedPesertaIds.length }}
                                    </span>
                                </button>
                            </div>

                            <!-- Semua Guru info -->
                            <div v-if="pesertaMode === 'semua'"
                                class="flex gap-3 bg-violet-50 border border-violet-100 rounded-xl p-3.5">
                                <svg class="w-5 h-5 text-violet-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div class="text-xs text-violet-700 space-y-0.5">
                                    <p class="font-semibold">Semua guru aktif otomatis ditambahkan sebagai peserta</p>
                                    <p class="text-violet-600 opacity-80">Dikecualikan otomatis: guru yang izin/sakit/alfa pada tanggal kegiatan.</p>
                                    <p class="text-amber-700 font-medium">
                                        Guru pengabsen (<span class="font-semibold">{{ selectedPenugasan?.guru_nama }}</span>) tidak masuk daftar peserta — vakasi-nya dicatat terpisah sebagai pengabsen.
                                    </p>
                                </div>
                            </div>

                            <!-- Pilih Manual — daftar guru -->
                            <div v-else>
                                <div class="border border-gray-200 rounded-xl overflow-hidden">
                                    <!-- Search -->
                                    <div class="px-3 py-2 border-b border-gray-100 bg-gray-50">
                                        <input v-model="cariGuru" type="text" placeholder="Cari nama guru..."
                                            class="w-full text-sm bg-transparent focus:outline-none text-gray-700 placeholder-gray-400" />
                                    </div>
                                    <!-- List -->
                                    <div class="max-h-44 overflow-y-auto divide-y divide-gray-50">
                                        <div v-for="g in guruFiltered" :key="g.id"
                                            @click="togglePeserta(g.id)"
                                            :class="['flex items-center gap-3 px-3 py-2.5 cursor-pointer transition',
                                                selectedPesertaIds.includes(g.id)
                                                    ? 'bg-violet-50'
                                                    : 'hover:bg-gray-50']">
                                            <!-- Checkbox visual -->
                                            <div :class="['w-4 h-4 rounded flex items-center justify-center border shrink-0 transition',
                                                selectedPesertaIds.includes(g.id)
                                                    ? 'bg-violet-600 border-violet-600'
                                                    : 'border-gray-300']">
                                                <svg v-if="selectedPesertaIds.includes(g.id)"
                                                    class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </div>
                                            <!-- Avatar -->
                                            <div v-if="g.foto">
                                                <img :src="g.foto" class="w-7 h-7 rounded-full object-cover" />
                                            </div>
                                            <div v-else
                                                class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 text-xs font-bold shrink-0">
                                                {{ g.nama?.charAt(0) }}
                                            </div>
                                            <!-- Info -->
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-800 truncate">{{ g.nama }}</p>
                                                <p class="text-xs text-gray-400 truncate">{{ g.jabatan }}</p>
                                            </div>
                                        </div>
                                        <div v-if="guruFiltered.length === 0"
                                            class="py-6 text-center text-sm text-gray-400">
                                            Tidak ada guru ditemukan
                                        </div>
                                    </div>
                                </div>
                                <!-- Select all shortcut -->
                                <div class="flex items-center justify-between mt-2 px-1">
                                    <p class="text-xs text-gray-400">
                                        {{ selectedPesertaIds.length }} dari {{ guruTersedia.length }} dipilih
                                    </p>
                                    <div class="flex gap-3">
                                        <button @click="pilihSemua"
                                            class="text-xs text-violet-600 hover:underline font-medium">
                                            Pilih Semua
                                        </button>
                                        <button v-if="selectedPesertaIds.length" @click="selectedPesertaIds = []"
                                            class="text-xs text-gray-400 hover:underline">
                                            Reset
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div><!-- /body -->

                    <!-- Footer modal (sticky) -->
                    <div class="px-6 py-4 border-t border-gray-100 flex gap-3 shrink-0">
                        <button @click="tutupModalKegiatan"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition">
                            Batal
                        </button>
                        <button @click="buatKegiatan"
                            :disabled="!formKegiatan.nama_kegiatan || !formKegiatan.tanggal_kegiatan
                                || (pesertaMode === 'manual' && !selectedPesertaIds.length)
                                || loadingKegiatan"
                            class="flex-1 py-2.5 rounded-xl bg-violet-600 text-white text-sm font-semibold hover:bg-violet-700 disabled:opacity-50 transition">
                            <span v-if="loadingKegiatan">Membuat...</span>
                            <span v-else-if="pesertaMode === 'semua'">Buat + Semua Guru</span>
                            <span v-else>Buat + {{ selectedPesertaIds.length }} Peserta</span>
                        </button>
                    </div>

                </div>
            </div>
        </Teleport>

        <!-- Modal foto -->
        <Teleport to="body">
            <div v-if="fotoModal.url" @click.self="fotoModal.url = null"
                class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4">
                <div class="max-w-lg w-full">
                    <p class="text-white text-sm text-center mb-2 opacity-80">{{ fotoModal.title }}</p>
                    <img :src="fotoModal.url" class="w-full rounded-2xl shadow-2xl max-h-[75vh] object-contain" />
                    <button @click="fotoModal.url = null" class="block mx-auto mt-4 text-white/70 hover:text-white text-sm">Tutup</button>
                </div>
            </div>
        </Teleport>

        <AppConfirm ref="confirm" />
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppConfirm from '@/Components/AppConfirm.vue'
import StatusBadge from '@/Components/Monitor/StatusBadge.vue'
import MonitorIcon from '@/Components/Monitor/MonitorIcon.vue'

const confirm = ref(null)

const props = defineProps({
    tugas: { type: Object, default: () => ({}) },
    penugasan: { type: Array, default: () => [] },
    guru: { type: Array, default: () => [] },
    vakasi: { type: Array, default: () => [] },
})

// ─── Filter ───────────────────────────────────────────────────────────────────
const workflowSteps = [
    'Admin tunjuk guru sebagai pengabsen (assign di sini)',
    'Admin klik "Buat Kegiatan" di baris guru → pilih peserta langsung',
    'Guru absen peserta via Flutter → klik Selesaikan',
    'Vakasi guru & peserta hadir otomatis masuk slip gaji',
]

const filterOpt = [
    { val: '', label: 'Semua' },
    { val: 'belum', label: 'Belum' },
    { val: 'selesai', label: 'Selesai' },
    { val: 'pending_verif', label: 'Pending' },
]
const filterStatus = ref('')
const penugasanFiltered = computed(() => {
    if (!filterStatus.value) return props.penugasan
    if (filterStatus.value === 'pending_verif')
        return props.penugasan.filter(p => p.status_pengerjaan === 'selesai' && p.disetujui === null)
    return props.penugasan.filter(p => p.status_pengerjaan === filterStatus.value)
})

// ─── Progress ─────────────────────────────────────────────────────────────────
const progressItems = computed(() => [
    { label: 'Total Penerima', value: props.penugasan.length, color: 'text-gray-700' },
    { label: 'Selesai', value: props.penugasan.filter(p => p.status_pengerjaan === 'selesai').length, color: 'text-emerald-600' },
    { label: 'Disetujui', value: props.penugasan.filter(p => p.disetujui === true).length, color: 'text-indigo-600' },
    { label: 'Belum', value: props.penugasan.filter(p => p.status_pengerjaan === 'belum').length, color: 'text-gray-400' },
])

const progressPct = computed(() => {
    if (!props.penugasan.length) return 0
    return Math.round(props.penugasan.filter(p => p.disetujui === true).length / props.penugasan.length * 100)
})

// ─── Assign ───────────────────────────────────────────────────────────────────
// Tampilkan SEMUA guru — tidak ada batasan duplikasi untuk tipe absen_kegiatan.
// Satu guru bisa di-assign berkali-kali; tiap baris penugasan = satu sesi kegiatan.
const guruBelumAssign = computed(() => props.guru)

// Hitung berapa kali tiap guru sudah di-assign pada tugas ini
const assignedCountMap = computed(() => {
    const map = {}
    props.penugasan.forEach(p => {
        map[p.guru_id] = (map[p.guru_id] || 0) + 1
    })
    return map
})

const cariAssign = ref('')
const assignIds  = ref([])
const assigning  = ref(false)

const assignGuruFiltered = computed(() => {
    const q = cariAssign.value.toLowerCase()
    if (!q) return guruBelumAssign.value
    return guruBelumAssign.value.filter(
        g => g.nama?.toLowerCase().includes(q) || g.jabatan?.toLowerCase().includes(q)
    )
})

function toggleAssign(id) {
    const idx = assignIds.value.indexOf(id)
    if (idx >= 0) assignIds.value.splice(idx, 1)
    else assignIds.value.push(id)
}

function pilihSemuaAssign() {
    assignIds.value = guruBelumAssign.value.map(g => g.id)
}

function assign() {
    if (!assignIds.value.length) return
    assigning.value = true
    router.post(route('admin.smart-payroll.tugas-tambahan.assign', props.tugas.id),
        { tenaga_pendidik_ids: assignIds.value },
        { preserveScroll: true, onFinish: () => { assigning.value = false; assignIds.value = []; cariAssign.value = '' } }
    )
}

// ─── Verifikasi ───────────────────────────────────────────────────────────────
function verifikasi(id, disetujui) {
    confirm.value.ask(
        disetujui
            ? { title: 'Setujui Tugas?', message: 'Tugas tambahan ini akan disetujui.',
                variant: 'success', confirmLabel: 'Ya, Setujui' }
            : { title: 'Tolak Tugas?', message: 'Tugas tambahan ini akan ditolak.',
                variant: 'danger', confirmLabel: 'Ya, Tolak' },
        (done) => router.patch(route('admin.smart-payroll.tugas-tambahan.verifikasi', id),
            { disetujui }, { preserveScroll: true, onFinish: done }),
    )
}

// ─── Modal Foto ───────────────────────────────────────────────────────────────
const fotoModal = ref({ url: null, title: '' })
function lihatFoto(url, nama) { fotoModal.value = { url, title: 'Bukti — ' + nama } }

// ─── Modal Buat Kegiatan (per-guru) ──────────────────────────────────────────
const showModalKegiatan  = ref(false)
const loadingKegiatan    = ref(false)
/** Penugasan row yang sedang di-target (berisi id, guru_nama, guru_id, dll.) */
const selectedPenugasan  = ref(null)
/** Mode peserta: 'semua' = semua guru aktif, 'manual' = pilih dari list */
const pesertaMode        = ref('semua')
const selectedPesertaIds = ref([])
const cariGuru           = ref('')

// Daftar guru yang bisa dipilih sebagai peserta — exclude pengabsen yang sedang dipilih.
// Pengabsen adalah penanggung jawab kegiatan, bukan peserta biasa.
const guruTersedia = computed(() => {
    const pengabsenId = selectedPenugasan.value?.guru_id
    return pengabsenId ? props.guru.filter(g => g.id !== pengabsenId) : props.guru
})

const guruFiltered = computed(() => {
    const q = cariGuru.value.toLowerCase()
    if (!q) return guruTersedia.value
    return guruTersedia.value.filter(
        g => g.nama?.toLowerCase().includes(q) || g.jabatan?.toLowerCase().includes(q)
    )
})

function pilihSemua() {
    // Pilih semua guru kecuali pengabsen
    selectedPesertaIds.value = guruTersedia.value.map(g => g.id)
}

function togglePeserta(id) {
    const idx = selectedPesertaIds.value.indexOf(id)
    if (idx >= 0) selectedPesertaIds.value.splice(idx, 1)
    else selectedPesertaIds.value.push(id)
}

/** Dipanggil dari tombol "Buat Kegiatan" per baris tabel */
function bukaPilihKegiatan(penugasanRow) {
    selectedPenugasan.value  = penugasanRow
    pesertaMode.value        = 'semua'
    selectedPesertaIds.value = []
    cariGuru.value           = ''
    formKegiatan.value = {
        nama_kegiatan:    '',
        tanggal_kegiatan: new Date().toISOString().split('T')[0],
        jam_mulai:        '',
        lokasi:           '',
    }
    showModalKegiatan.value = true
}

function tutupModalKegiatan() {
    showModalKegiatan.value  = false
    selectedPenugasan.value  = null
    pesertaMode.value        = 'semua'
    selectedPesertaIds.value = []
    cariGuru.value           = ''
}

const formKegiatan = ref({
    nama_kegiatan:    '',
    tanggal_kegiatan: new Date().toISOString().split('T')[0],
    jam_mulai:        '',
    lokasi:           '',
})

function buatKegiatan() {
    if (!formKegiatan.value.nama_kegiatan || !formKegiatan.value.tanggal_kegiatan) return
    if (!selectedPenugasan.value) return
    if (pesertaMode.value === 'manual' && !selectedPesertaIds.value.length) return
    loadingKegiatan.value = true

    // Jika penugasan yang dipilih sudah punya kegiatan (mode "Kegiatan Baru" dari baris selesai),
    // jangan link ke penugasan_id lama agar tidak bentrok — cukup pengabsen_id saja.
    const penugasanId = selectedPenugasan.value.kegiatan_id ? null : selectedPenugasan.value.id

    router.post(route('admin.smart-payroll.absensi-kegiatan.store'), {
        sumber_tipe:      'tugas_tambahan',
        sumber_id:        props.tugas.id,
        penugasan_id:     penugasanId,
        pengabsen_id:     selectedPenugasan.value.guru_id,
        nama_kegiatan:    formKegiatan.value.nama_kegiatan,
        tanggal_kegiatan: formKegiatan.value.tanggal_kegiatan,
        jam_mulai:        formKegiatan.value.jam_mulai  || null,
        lokasi:           formKegiatan.value.lokasi     || null,
        semua_guru:       pesertaMode.value === 'semua',
        peserta_ids:      pesertaMode.value === 'manual' ? selectedPesertaIds.value : [],
    }, {
        preserveScroll: false,
        onSuccess: () => {
            loadingKegiatan.value = false
            tutupModalKegiatan()
        },
        onError: (errors) => {
            loadingKegiatan.value = false
            const msg = Object.values(errors).flat().join(' | ')
            alert('Gagal membuat kegiatan:\n' + (msg || 'Terjadi kesalahan.'))
        },
    })
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
const statusLabel = {
    draft: 'Draft', aktif: 'Aktif', selesai: 'Selesai', dibatalkan: 'Dibatalkan'
}

const statusCls = (s) => ({
    aktif: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    draft: 'bg-gray-50 text-gray-500 border-gray-200',
    selesai: 'bg-blue-50 text-blue-700 border-blue-200',
    dibatalkan: 'bg-red-50 text-red-600 border-red-200',
})[s] ?? 'bg-gray-50 text-gray-500 border-gray-200'

const statusPengerjaanLabel = {
    belum: 'Belum', sedang: 'Sedang', selesai: 'Selesai', tidak_selesai: 'Tidak Selesai'
}

const statusPengerjaanCls = (s) => ({
    belum: 'bg-gray-100 text-gray-500',
    sedang: 'bg-blue-100 text-blue-700',
    selesai: 'bg-emerald-100 text-emerald-700',
    tidak_selesai: 'bg-red-100 text-red-600',
})[s] ?? 'bg-gray-100 text-gray-500'

function formatRp(n) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n)
}
</script>
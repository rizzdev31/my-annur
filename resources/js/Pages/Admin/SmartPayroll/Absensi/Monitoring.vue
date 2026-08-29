<template>
    <AdminLayout title="Monitoring Harian" subtitle="Smart Payroll">

        <Head title="Monitoring Harian" />

        <!-- ══ HERO ══ -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-indigo-600 to-violet-600 text-white p-5 sm:p-6 mb-5">
            <div class="absolute -right-8 -top-10 w-48 h-48 rounded-full bg-white/10"></div>
            <div class="absolute -right-16 top-16 w-40 h-40 rounded-full bg-white/5"></div>
            <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="flex items-center gap-1.5 text-[11px] font-semibold bg-white/15 backdrop-blur px-2.5 py-1 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-300 animate-pulse"></span>
                            Live Monitoring
                        </span>
                        <span v-if="hari_libur" class="text-[11px] font-semibold bg-amber-400/90 text-amber-950 px-2.5 py-1 rounded-full">
                            Libur · {{ hari_libur.nama }}
                        </span>
                    </div>
                    <h2 class="text-2xl font-bold leading-tight">Monitoring Harian</h2>
                    <p class="text-white/70 text-sm mt-0.5">{{ hari }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <input v-model="fTanggal" type="date" @change="applyFilter"
                            class="pl-9 pr-3 py-2.5 rounded-xl bg-white/15 backdrop-blur border border-white/20 text-sm text-white placeholder-white/60 focus:outline-none focus:bg-white/25 [color-scheme:dark]" />
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-white/70 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <button @click="applyFilter" title="Muat ulang"
                        class="p-2.5 rounded-xl bg-white/15 backdrop-blur border border-white/20 text-white hover:bg-white/25 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- ══ STAT CARDS ══ -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <!-- Kehadiran (with ring) -->
            <div class="bg-white rounded-2xl border border-gray-100 p-4 flex items-center gap-4 hover:shadow-sm transition-shadow">
                <div class="relative w-14 h-14 shrink-0">
                    <svg class="w-14 h-14 -rotate-90" viewBox="0 0 36 36">
                        <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#ECFDF5" stroke-width="3.5" />
                        <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#10B981" stroke-width="3.5" stroke-linecap="round" :stroke-dasharray="`${attendancePct} 100`" />
                    </svg>
                    <span class="absolute inset-0 grid place-items-center text-[13px] font-bold text-emerald-600">{{ attendancePct }}%</span>
                </div>
                <div class="min-w-0">
                    <p class="text-2xl font-bold text-gray-900 leading-none">{{ hadirCount }}<span class="text-sm text-gray-400 font-semibold">/{{ summary.total_guru ?? 0 }}</span></p>
                    <p class="text-xs text-gray-500 mt-1 font-medium">Guru Hadir</p>
                </div>
            </div>

            <!-- Belum absen / Alfa / Mengajar -->
            <div v-for="c in statCards" :key="c.key"
                class="bg-white rounded-2xl border border-gray-100 p-4 flex items-center gap-3.5 hover:shadow-sm transition-shadow">
                <span class="w-11 h-11 rounded-xl grid place-items-center shrink-0" :style="{ background: c.c + '18' }">
                    <svg class="w-[22px] h-[22px]" :style="{ color: c.c }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" :d="c.icon" /></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-2xl font-bold text-gray-900 leading-none">{{ c.value }}</p>
                    <p class="text-xs text-gray-500 mt-1 font-medium truncate">{{ c.label }}</p>
                    <p v-if="c.sub" class="text-[10.5px] mt-0.5 font-semibold" :style="{ color: c.c }">{{ c.sub }}</p>
                </div>
            </div>
        </div>

        <!-- ══ DISTRIBUSI + AKTIVITAS ══ -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-5">
            <!-- Distribusi kehadiran -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-sm font-bold text-gray-800">Distribusi Kehadiran</p>
                    <span class="text-xs text-gray-400">{{ summary.total_guru ?? 0 }} tenaga pendidik</span>
                </div>
                <div class="flex h-3.5 rounded-full overflow-hidden bg-gray-100 mb-4">
                    <div v-for="d in distList" :key="d.key" :style="{ width: d.pct + '%', background: d.c }"
                        class="h-full transition-all" :title="`${d.label}: ${d.count}`"></div>
                    <div v-if="!distList.length" class="w-full grid place-items-center text-[10px] text-gray-400">Belum ada data</div>
                </div>
                <div class="flex flex-wrap gap-x-4 gap-y-2">
                    <div v-for="d in distList" :key="d.key" class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full" :style="{ background: d.c }"></span>
                        <span class="text-xs text-gray-600">{{ d.label }}</span>
                        <span class="text-xs font-bold text-gray-900">{{ d.count }}</span>
                    </div>
                </div>
            </div>

            <!-- Ringkasan aktivitas -->
            <div class="bg-white rounded-2xl border border-gray-100 p-5">
                <p class="text-sm font-bold text-gray-800 mb-3">Aktivitas Hari Ini</p>
                <div class="space-y-2.5">
                    <div v-for="a in aktivitasRingkas" :key="a.label" class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg grid place-items-center shrink-0" :style="{ background: a.c + '18' }">
                            <svg class="w-4 h-4" :style="{ color: a.c }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="a.icon" /></svg>
                        </span>
                        <span class="text-xs text-gray-500 flex-1">{{ a.label }}</span>
                        <span class="text-base font-bold text-gray-900">{{ a.value }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ TABS + FILTER ══ -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
            <div class="flex items-center gap-1.5 overflow-x-auto scrollbar-hide -mx-1 px-1">
                <button v-for="t in statusTabs" :key="t.key" @click="fStatus = t.key"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap border transition-all"
                    :class="fStatus === t.key ? 'text-white border-transparent shadow-sm' : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300'"
                    :style="fStatus === t.key ? { background: t.c } : {}">
                    {{ t.label }}
                    <span class="px-1.5 py-px rounded-full text-[10px] font-bold"
                        :class="fStatus === t.key ? 'bg-white/25' : 'bg-gray-100 text-gray-500'">{{ t.count }}</span>
                </button>
            </div>
            <div class="flex items-center gap-2 sm:ml-auto">
                <div class="relative">
                    <input v-model="fSearch" type="text" placeholder="Cari guru..."
                        class="pl-8 pr-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-indigo-500 w-40 sm:w-48" />
                    <svg class="w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <select v-model="fJabatan" @change="applyFilter"
                    class="px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none hidden sm:block">
                    <option value="">Semua Jabatan</option>
                    <option v-for="j in jabatan" :key="j.id" :value="j.id">{{ j.nama_jabatan }}</option>
                </select>
                <div class="flex rounded-xl border border-gray-200 bg-white p-0.5 shrink-0">
                    <button @click="viewMode = 'card'" class="p-1.5 rounded-lg transition-colors" :class="viewMode === 'card' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-400'" title="Kartu">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    </button>
                    <button @click="viewMode = 'table'" class="p-1.5 rounded-lg transition-colors" :class="viewMode === 'table' ? 'bg-indigo-50 text-indigo-600' : 'text-gray-400'" title="Tabel">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- ══ VIEW: CARD ══ -->
        <div v-if="viewMode === 'card'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
            <div v-for="g in dataFiltered" :key="g.id"
                class="group bg-white rounded-2xl border border-gray-100 overflow-hidden hover:shadow-md hover:border-indigo-100 transition-all">
                <!-- accent bar -->
                <div class="h-1" :style="{ background: accent(g) }"></div>

                <!-- Header -->
                <div class="p-4 flex items-center gap-3">
                    <img v-if="g.foto" :src="g.foto" class="w-11 h-11 rounded-xl object-cover shrink-0 ring-1 ring-gray-100" />
                    <div v-else class="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-500 grid place-items-center shrink-0 text-sm font-bold text-white">
                        {{ g.nama?.charAt(0) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-800 truncate">{{ g.nama }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ g.jabatan }}</p>
                    </div>
                    <!-- Skor ring -->
                    <div class="relative w-12 h-12 shrink-0">
                        <svg class="w-12 h-12 -rotate-90" viewBox="0 0 36 36">
                            <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#F1F5F9" stroke-width="3.5" />
                            <circle cx="18" cy="18" r="15.9155" fill="none" :stroke="skorColor(g.skor_harian)" stroke-width="3.5" stroke-linecap="round" :stroke-dasharray="`${g.skor_harian} 100`" />
                        </svg>
                        <span class="absolute inset-0 grid place-items-center text-[11px] font-bold" :style="{ color: skorColor(g.skor_harian) }">{{ g.skor_harian }}</span>
                    </div>
                </div>

                <!-- Rows -->
                <div class="px-4 pb-2 space-y-1.5">
                    <!-- Absensi -->
                    <div class="flex items-center justify-between py-1.5 border-t border-gray-50">
                        <div class="flex items-center gap-2 text-gray-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            <span class="text-xs font-medium">Absensi</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span v-if="g.absensi.jam_masuk" class="text-[11px] text-gray-400 tabular-nums">{{ g.absensi.jam_masuk }}</span>
                            <span :class="['text-[11px] font-semibold px-2 py-0.5 rounded-md', statusCls(g.absensi.status)]">{{ statusLabel[g.absensi.status] ?? g.absensi.status }}</span>
                            <button @click="openKoreksi(g, 'harian')" class="p-1 rounded-md text-gray-300 hover:text-amber-600 hover:bg-amber-50 transition-colors opacity-0 group-hover:opacity-100">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Mengajar -->
                    <div class="flex items-center justify-between py-1.5 border-t border-gray-50">
                        <div class="flex items-center gap-2 text-gray-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.42A12 12 0 0112 21a12 12 0 01-6.16-10.42L12 14z"/></svg>
                            <span class="text-xs font-medium">Mengajar</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div v-if="g.mengajar.jadwal_count > 0" class="w-14 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full rounded-full transition-all" :style="{ width: (g.mengajar.selesai_count / g.mengajar.jadwal_count * 100) + '%', background: g.mengajar.selesai_count === g.mengajar.jadwal_count ? '#10B981' : '#F59E0B' }"></div>
                            </div>
                            <span class="text-[11px] text-gray-500 tabular-nums">{{ g.mengajar.jadwal_count ? `${g.mengajar.selesai_count}/${g.mengajar.jadwal_count}` : '—' }}</span>
                            <span v-if="g.mengajar.jp_terlaksana > 0" class="text-[11px] font-bold text-sky-600">{{ g.mengajar.jp_terlaksana }}JP</span>
                        </div>
                    </div>

                    <!-- Log Kerja -->
                    <div class="flex items-center justify-between py-1.5 border-t border-gray-50">
                        <div class="flex items-center gap-2 text-gray-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span class="text-xs font-medium">Log Kerja</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span v-if="g.log_kerja.durasi_jam > 0" class="text-[11px] text-gray-400">{{ g.log_kerja.durasi_jam }}j</span>
                            <button v-if="g.log_kerja.submitted > 0" @click="openVerifikasi(g)"
                                class="text-[11px] font-semibold px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 hover:bg-amber-100 transition-colors">
                                {{ g.log_kerja.submitted }} perlu verifikasi
                            </button>
                            <span v-else-if="g.log_kerja.diverifikasi > 0" class="text-[11px] font-semibold px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700">{{ g.log_kerja.diverifikasi }} terverifikasi</span>
                            <span v-else class="text-[11px] text-gray-300">—</span>
                        </div>
                    </div>

                    <!-- Tugas (gabungan) -->
                    <div v-if="g.tugas_tambahan.aktif > 0 || g.tugas_jabatan.selesai > 0" class="flex items-center justify-between py-1.5 border-t border-gray-50">
                        <div class="flex items-center gap-2 text-gray-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="text-xs font-medium">Tugas</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span v-if="g.tugas_tambahan.aktif > 0" :class="['text-[11px] font-semibold px-2 py-0.5 rounded-md', g.tugas_tambahan.selesai === g.tugas_tambahan.aktif ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700']">
                                Tambahan {{ g.tugas_tambahan.selesai }}/{{ g.tugas_tambahan.aktif }}
                            </span>
                            <span v-if="g.tugas_jabatan.selesai > 0" class="text-[11px] font-semibold px-2 py-0.5 rounded-md bg-violet-50 text-violet-700">Jabatan {{ g.tugas_jabatan.selesai }}</span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-4 py-2.5 border-t border-gray-50 flex items-center justify-between bg-gray-50/50">
                    <button @click="openKoreksi(g, 'harian')" class="flex items-center gap-1 text-xs font-medium text-amber-600 hover:text-amber-700">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Koreksi
                    </button>
                    <a :href="route('admin.smart-payroll.monitoring.detail', g.id) + `?bulan=${bulanSekarang}&tahun=${tahunSekarang}`"
                        class="flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-700">
                        Detail Kinerja
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>

            <div v-if="!dataFiltered.length" class="col-span-full py-16 text-center bg-white rounded-2xl border border-gray-100">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-gray-50 grid place-items-center mb-3">
                    <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <p class="text-sm font-semibold text-gray-600">Tidak ada data</p>
                <p class="text-xs text-gray-400 mt-0.5">Coba ubah filter atau tanggal.</p>
            </div>
        </div>

        <!-- ══ VIEW: TABLE ══ -->
        <div v-else class="bg-white rounded-2xl border border-gray-100 overflow-x-auto">
            <table class="w-full min-w-[820px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="px-4 py-3 text-left text-[11px] font-bold text-gray-400 uppercase tracking-wider">Tenaga Pendidik</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold text-gray-400 uppercase tracking-wider">Absensi</th>
                        <th class="px-4 py-3 text-left text-[11px] font-bold text-gray-400 uppercase tracking-wider">Mengajar</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold text-gray-400 uppercase tracking-wider">Log Kerja</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold text-gray-400 uppercase tracking-wider">Tugas</th>
                        <th class="px-4 py-3 text-center text-[11px] font-bold text-gray-400 uppercase tracking-wider">Skor</th>
                        <th class="px-4 py-3 text-right text-[11px] font-bold text-gray-400 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="g in dataFiltered" :key="g.id" class="hover:bg-indigo-50/30 transition-colors">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <img v-if="g.foto" :src="g.foto" class="w-8 h-8 rounded-lg object-cover shrink-0" />
                                <div v-else class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-500 grid place-items-center text-xs font-bold text-white shrink-0">{{ g.nama?.charAt(0) }}</div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-800 truncate">{{ g.nama }}</p>
                                    <p class="text-xs text-gray-400 truncate">{{ g.jabatan }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span :class="['text-[11px] font-semibold px-2 py-0.5 rounded-md', statusCls(g.absensi.status)]">{{ statusLabel[g.absensi.status] ?? g.absensi.status }}</span>
                            <p v-if="g.absensi.jam_masuk" class="text-[10px] text-gray-400 mt-0.5 tabular-nums">{{ g.absensi.jam_masuk }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div v-if="g.mengajar.jadwal_count > 0" class="w-16 h-1.5 rounded-full bg-gray-100 overflow-hidden shrink-0">
                                    <div class="h-full rounded-full" :style="{ width: (g.mengajar.selesai_count / g.mengajar.jadwal_count * 100) + '%', background: g.mengajar.selesai_count === g.mengajar.jadwal_count ? '#10B981' : '#F59E0B' }"></div>
                                </div>
                                <span class="text-xs text-gray-600 tabular-nums">{{ g.mengajar.jadwal_count ? `${g.mengajar.selesai_count}/${g.mengajar.jadwal_count}` : '—' }}</span>
                                <span v-if="g.mengajar.jp_terlaksana > 0" class="text-[11px] font-semibold text-sky-600">{{ g.mengajar.jp_terlaksana }}JP</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button v-if="g.log_kerja.submitted > 0" @click="openVerifikasi(g)" class="text-[11px] font-semibold px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 hover:bg-amber-100">{{ g.log_kerja.submitted }} pending</button>
                            <span v-else-if="g.log_kerja.diverifikasi > 0" class="text-[11px] text-emerald-600 font-medium">{{ g.log_kerja.diverifikasi }} ✓</span>
                            <span v-else class="text-xs text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-gray-500">
                            <span v-if="g.tugas_tambahan.selesai + g.tugas_jabatan.selesai > 0" class="font-semibold text-violet-600">+{{ g.tugas_tambahan.selesai + g.tugas_jabatan.selesai }}</span>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center justify-center min-w-[34px] text-xs font-bold px-2 py-1 rounded-lg"
                                :style="{ background: skorColor(g.skor_harian) + '18', color: skorColor(g.skor_harian) }">{{ g.skor_harian }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <button @click="openKoreksi(g, 'harian')" class="px-2.5 py-1.5 rounded-lg text-xs font-medium border border-amber-200 text-amber-600 hover:bg-amber-50">Koreksi</button>
                                <a :href="route('admin.smart-payroll.monitoring.detail', g.id) + `?bulan=${bulanSekarang}&tahun=${tahunSekarang}`" class="px-2.5 py-1.5 rounded-lg text-xs font-medium border border-indigo-200 text-indigo-600 hover:bg-indigo-50">Detail</a>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!dataFiltered.length">
                        <td colspan="7" class="px-4 py-14 text-center text-sm text-gray-400">Tidak ada data.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ══ MODAL KOREKSI ══ -->
        <div v-if="showKoreksi" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50" @click="showKoreksi = false">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Koreksi Aktivitas</h3>
                    <p class="text-xs text-gray-400 mt-0.5">{{ koreksiTarget?.nama }} · {{ tanggal }}</p>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipe</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button v-for="t in tipeKoreksiOpts(koreksiTarget)" :key="t.key" type="button"
                                @click="kForm.tipe = t.key"
                                :class="[`py-2.5 px-3 rounded-xl border-2 text-sm font-medium text-left transition-all`,
                                    kForm.tipe === t.key ? `border-indigo-500 bg-indigo-50 text-indigo-700` : `border-gray-200 text-gray-600`]">
                                {{ t.label }}
                            </button>
                        </div>
                    </div>

                    <template v-if="kForm.tipe === 'harian'">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status Baru</label>
                            <div class="grid grid-cols-4 gap-1.5">
                                <button v-for="s in statusOptions" :key="s.value" type="button"
                                    @click="kForm.nilai_baru = s.value" :class="[`py-2 rounded-xl border-2 text-xs font-semibold transition-all text-center`,
                                        kForm.nilai_baru === s.value ? s.active : `border-gray-200 text-gray-600`]">
                                    {{ s.label }}
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Jam Masuk</label>
                                <input v-model="kForm.jam_masuk" type="time"
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Jam Pulang</label>
                                <input v-model="kForm.jam_pulang" type="time"
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white" />
                            </div>
                        </div>
                    </template>

                    <template v-else-if="kForm.tipe === 'mengajar'">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sesi Mengajar</label>
                            <select v-model="kForm.referensi_id"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white">
                                <option v-for="m in koreksiTarget?.mengajar?.detail" :key="m.id" :value="m.id">
                                    {{ m.mata_pelajaran }} · {{ m.kelas }} ({{ m.status }})
                                </option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Status Baru</label>
                                <select v-model="kForm.nilai_baru"
                                    class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white">
                                    <option value="terlaksana">Terlaksana</option>
                                    <option value="tidak_terlaksana">Tidak Terlaksana</option>
                                    <option value="pengganti">Pengganti</option>
                                    <option value="izin">Izin (Hangus)</option>
                                    <option value="libur">Libur</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">JP Terlaksana</label>
                                <input v-model.number="kForm.jp_terlaksana" type="number" min="0"
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white" />
                            </div>
                        </div>
                    </template>

                    <template v-else-if="kForm.tipe === 'tugas_tambahan'">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tugas</label>
                            <select v-model="kForm.referensi_id"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white">
                                <option v-for="t in koreksiTarget?.tugas_tambahan?.detail" :key="t.id" :value="t.id">
                                    {{ t.judul }} ({{ t.status }})
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Status Baru</label>
                            <select v-model="kForm.nilai_baru"
                                class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white">
                                <option value="belum_mulai">Belum Mulai</option>
                                <option value="sedang_berjalan">Sedang Berjalan</option>
                                <option value="selesai">Selesai</option>
                            </select>
                        </div>
                    </template>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Alasan Koreksi <span class="text-red-500">*</span>
                        </label>
                        <textarea v-model="kForm.alasan" rows="2" placeholder="Jelaskan alasan koreksi ini..." :class="[`w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none bg-white resize-none`,
                            kErr.alasan ? `border-red-300` : `border-gray-200 focus:border-indigo-500`]" />
                        <p v-if="kErr.alasan" class="mt-1 text-xs text-red-500">{{ kErr.alasan }}</p>
                    </div>
                </div>
                <div class="flex gap-3 px-6 pb-6">
                    <button @click="showKoreksi = false"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600">Batal</button>
                    <button @click="submitKoreksi" :disabled="kLoading"
                        class="flex-1 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold disabled:opacity-60">
                        {{ kLoading ? 'Menyimpan...' : 'Simpan Koreksi' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ══ MODAL VERIFIKASI LOG ══ -->
        <div v-if="showVerifikasi" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50" @click="showVerifikasi = false">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm" @click.stop>
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Verifikasi Log Kerja</h3>
                    <p class="text-xs text-gray-400 mt-0.5">{{ verifikasiTarget?.nama }}</p>
                </div>
                <div class="px-6 py-4 max-h-64 overflow-y-auto space-y-2">
                    <div v-for="l in verifikasiTarget?.log_kerja?.detail?.filter(x => x.status === 'submitted')"
                        :key="l.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ l.judul }}</p>
                            <p class="text-xs text-gray-400">{{ l.tugas }} · {{ l.durasi }}</p>
                        </div>
                        <div class="flex gap-1 shrink-0 ml-2">
                            <button @click="doVerifikasi(l.id, 'verifikasi')"
                                class="px-2.5 py-1.5 rounded-lg text-xs bg-emerald-600 text-white font-medium hover:bg-emerald-700">✓</button>
                            <button @click="doVerifikasi(l.id, 'tolak')"
                                class="px-2.5 py-1.5 rounded-lg text-xs bg-red-100 text-red-600 font-medium hover:bg-red-200">✗</button>
                        </div>
                    </div>
                </div>
                <div class="px-6 pb-5 pt-1">
                    <button @click="showVerifikasi = false"
                        class="w-full py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600">Tutup</button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    data: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    tanggal: { type: String, default: '' },
    hari: { type: String, default: '' },
    hari_libur: { type: Object, default: null },
    jabatan: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const fTanggal = ref(props.filters.tanggal ?? props.tanggal)
const fSearch = ref(props.filters.search ?? '')
const fJabatan = ref(props.filters.jabatan_id ?? '')
const fStatus = ref('')
const viewMode = ref('card')

const bulanSekarang = new Date().getMonth() + 1
const tahunSekarang = new Date().getFullYear()

const statusLabel = {
    hadir: 'Hadir', terlambat: 'Terlambat', izin: 'Izin', sakit: 'Sakit',
    alfa: 'Alfa', libur: 'Libur', dinas_luar: 'Dinas Luar', belum: 'Belum', izin_sakit: 'Izin Sakit',
}
const statusCls = (s) => ({
    hadir: 'bg-emerald-50 text-emerald-700', terlambat: 'bg-amber-50 text-amber-700',
    izin: 'bg-blue-50 text-blue-700', sakit: 'bg-indigo-50 text-indigo-700',
    izin_sakit: 'bg-indigo-50 text-indigo-700', dinas_luar: 'bg-violet-50 text-violet-700',
    alfa: 'bg-red-50 text-red-600', libur: 'bg-gray-100 text-gray-500',
    belum: 'bg-gray-100 text-gray-400',
}[s] ?? 'bg-gray-100 text-gray-500')

const skorColor = (v) => v >= 80 ? '#10B981' : v >= 50 ? '#F59E0B' : '#EF4444'

// Aksen kiri kartu berdasar kondisi paling penting untuk dipantau.
const accent = (g) => {
    if (g.absensi.status === 'alfa') return '#EF4444'
    if (g.absensi.status === 'belum') return '#CBD5E1'
    if (g.log_kerja.submitted > 0) return '#F59E0B'
    if (g.mengajar.jadwal_count > 0 && g.mengajar.selesai_count === g.mengajar.jadwal_count) return '#10B981'
    return '#818CF8'
}

// ── Distribusi kehadiran (dihitung dari data) ──────────────────────────────────
const dist = computed(() => {
    const c = { hadir: 0, terlambat: 0, izin: 0, izin_sakit: 0, sakit: 0, alfa: 0, belum: 0, libur: 0, dinas_luar: 0 }
    props.data.forEach(g => { const s = g.absensi.status; if (c[s] !== undefined) c[s]++; else c.belum++ })
    return c
})
const hadirCount = computed(() => dist.value.hadir + dist.value.terlambat + dist.value.dinas_luar)
const attendancePct = computed(() => {
    const t = props.summary.total_guru || props.data.length || 0
    return t ? Math.round(hadirCount.value / t * 100) : 0
})

const distList = computed(() => {
    const t = props.data.length || 1
    const defs = [
        { key: 'hadir', label: 'Hadir', c: '#10B981' },
        { key: 'terlambat', label: 'Terlambat', c: '#F59E0B' },
        { key: 'izin', label: 'Izin', c: '#3B82F6' },
        { key: 'izin_sakit', label: 'Izin Sakit', c: '#60A5FA' },
        { key: 'sakit', label: 'Sakit', c: '#6366F1' },
        { key: 'dinas_luar', label: 'Dinas', c: '#8B5CF6' },
        { key: 'belum', label: 'Belum Absen', c: '#CBD5E1' },
        { key: 'alfa', label: 'Alfa', c: '#EF4444' },
        { key: 'libur', label: 'Libur', c: '#94A3B8' },
    ]
    return defs.map(d => ({ ...d, count: dist.value[d.key] || 0, pct: Math.round((dist.value[d.key] || 0) / t * 100) }))
        .filter(d => d.count > 0)
})

const mengajarStat = computed(() => {
    let jadwal = 0, selesai = 0, jp = 0, aktifGuru = 0
    props.data.forEach(g => {
        jadwal += g.mengajar.jadwal_count; selesai += g.mengajar.selesai_count
        jp += g.mengajar.jp_terlaksana; if (g.mengajar.jadwal_count > 0) aktifGuru++
    })
    return { jadwal, selesai, jp, aktifGuru, pct: jadwal ? Math.round(selesai / jadwal * 100) : 0 }
})
const logPendingGuru = computed(() => props.data.filter(g => g.log_kerja.submitted > 0).length)

const statCards = computed(() => [
    { key: 'belum', label: 'Belum Absen', value: dist.value.belum, sub: dist.value.belum ? 'perlu tindak lanjut' : 'semua sudah', c: '#64748B',
      icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { key: 'alfa', label: 'Alfa / Tanpa Ket.', value: dist.value.alfa, sub: dist.value.alfa ? 'tidak hadir' : 'nihil', c: '#EF4444',
      icon: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { key: 'mengajar', label: 'Sesi Mengajar', value: `${mengajarStat.value.selesai}/${mengajarStat.value.jadwal}`, sub: `${mengajarStat.value.jp} JP · ${mengajarStat.value.pct}%`, c: '#0EA5E9',
      icon: 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.42A12 12 0 0112 21a12 12 0 01-6.16-10.42L12 14z' },
])

const aktivitasRingkas = computed(() => [
    { label: 'Total JP Terlaksana', value: props.summary.total_jp ?? 0, c: '#0EA5E9', icon: 'M12 14l9-5-9-5-9 5 9 5z' },
    { label: 'Log Perlu Verifikasi', value: props.summary.log_pending ?? 0, c: '#F59E0B', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { label: 'Tugas Selesai', value: props.summary.tugas_selesai ?? 0, c: '#8B5CF6', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
])

const statusTabs = computed(() => [
    { key: '', label: 'Semua', count: props.data.length, c: '#6366F1' },
    { key: 'hadir', label: 'Hadir', count: hadirCount.value, c: '#10B981' },
    { key: 'belum', label: 'Belum Absen', count: dist.value.belum, c: '#64748B' },
    { key: 'alfa', label: 'Alfa', count: dist.value.alfa, c: '#EF4444' },
    { key: 'mengajar', label: 'Mengajar', count: mengajarStat.value.aktifGuru, c: '#0EA5E9' },
    { key: 'log_pending', label: 'Log Pending', count: logPendingGuru.value, c: '#F59E0B' },
])

const dataFiltered = computed(() => {
    let list = props.data
    if (fSearch.value) list = list.filter(g => g.nama.toLowerCase().includes(fSearch.value.toLowerCase()))
    if (fStatus.value === 'belum') list = list.filter(g => g.absensi.status === 'belum')
    else if (fStatus.value === 'alfa') list = list.filter(g => g.absensi.status === 'alfa')
    else if (fStatus.value === 'hadir') list = list.filter(g => ['hadir', 'terlambat', 'dinas_luar'].includes(g.absensi.status))
    else if (fStatus.value === 'mengajar') list = list.filter(g => g.mengajar.jadwal_count > 0)
    else if (fStatus.value === 'log_pending') list = list.filter(g => g.log_kerja.submitted > 0)
    return list
})

function applyFilter() {
    router.get(route('admin.smart-payroll.monitoring.index'), {
        tanggal: fTanggal.value,
        jabatan_id: fJabatan.value || undefined,
    }, { preserveState: true, replace: true })
}

// ── Modal Koreksi ─────────────────────────────────────────────────────────────
const showKoreksi = ref(false)
const kLoading = ref(false)
const koreksiTarget = ref(null)
const kForm = reactive({ tipe: 'harian', referensi_id: null, nilai_baru: 'hadir', alasan: '', jam_masuk: '', jam_pulang: '', menit_terlambat: 0, jp_terlaksana: 0, materi: '', keterangan: '' })
const kErr = reactive({ alasan: '' })

const statusOptions = [
    { value: 'hadir', label: 'Hadir', active: 'border-emerald-500 bg-emerald-50 text-emerald-700' },
    { value: 'terlambat', label: 'Terlambat', active: 'border-amber-500 bg-amber-50 text-amber-700' },
    { value: 'izin', label: 'Izin', active: 'border-blue-500 bg-blue-50 text-blue-700' },
    { value: 'sakit', label: 'Sakit', active: 'border-indigo-500 bg-indigo-50 text-indigo-700' },
    { value: 'alfa', label: 'Alfa', active: 'border-red-500 bg-red-50 text-red-600' },
    { value: 'libur', label: 'Libur', active: 'border-gray-400 bg-gray-100 text-gray-600' },
    { value: 'dinas_luar', label: 'Dinas', active: 'border-violet-500 bg-violet-50 text-violet-700' },
    { value: 'izin_sakit', label: 'Izin Sakit', active: 'border-indigo-400 bg-indigo-50 text-indigo-700' },
]

function tipeKoreksiOpts(g) {
    if (!g) return []
    const opts = [{ key: 'harian', label: 'Absensi Harian' }]
    if (g.mengajar?.jadwal_count > 0) opts.push({ key: 'mengajar', label: 'Mengajar' })
    if (g.tugas_tambahan?.aktif > 0) opts.push({ key: 'tugas_tambahan', label: 'Tugas Tambahan' })
    if (g.tugas_jabatan?.selesai > 0) opts.push({ key: 'tugas_jabatan', label: 'Tugas Jabatan' })
    return opts
}

function openKoreksi(g, tipe = 'harian') {
    koreksiTarget.value = g
    Object.assign(kForm, {
        tipe,
        referensi_id: g.absensi?.id ?? null,
        nilai_baru: g.absensi?.status === 'belum' ? 'hadir' : (g.absensi?.status ?? 'hadir'),
        alasan: '', jam_masuk: g.absensi?.jam_masuk ?? '', jam_pulang: g.absensi?.jam_pulang ?? '',
        menit_terlambat: g.absensi?.menit_terlambat ?? 0, jp_terlaksana: 0, materi: '', keterangan: '',
    })
    kErr.alasan = ''
    showKoreksi.value = true
}

function submitKoreksi() {
    kErr.alasan = kForm.alasan.trim() ? '' : 'Alasan wajib diisi'
    if (kErr.alasan) return

    kLoading.value = true
    router.post(route('admin.smart-payroll.monitoring.koreksi'), {
        tipe: kForm.tipe,
        referensi_id: kForm.referensi_id ?? koreksiTarget.value?.absensi?.id,
        tenaga_pendidik_id: koreksiTarget.value?.id,
        tanggal: props.tanggal,
        field: 'status',
        nilai_baru: kForm.nilai_baru,
        alasan: kForm.alasan,
        jam_masuk: kForm.jam_masuk || undefined,
        jam_pulang: kForm.jam_pulang || undefined,
        menit_terlambat: kForm.menit_terlambat || undefined,
        jp_terlaksana: kForm.jp_terlaksana || undefined,
        materi: kForm.materi || undefined,
        keterangan: kForm.keterangan || undefined,
    }, {
        onSuccess: () => { showKoreksi.value = false },
        onFinish: () => kLoading.value = false,
        preserveScroll: true,
    })
}

// ── Modal Verifikasi Log ──────────────────────────────────────────────────────
const showVerifikasi = ref(false)
const verifikasiTarget = ref(null)

function openVerifikasi(g) {
    verifikasiTarget.value = g
    showVerifikasi.value = true
}

function doVerifikasi(logId, aksi) {
    router.post(route('admin.smart-payroll.monitoring.verifikasi-log', logId), { aksi }, {
        preserveScroll: true,
    })
}
</script>

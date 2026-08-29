<template>
    <div class="min-h-screen flex bg-gray-100 print:block print:min-h-0 print:bg-white">

        <!-- Overlay mobile -->
        <div v-if="sidebarOpen && isMobile" @click="sidebarOpen = false"
            class="fixed inset-0 bg-black/30 z-40 lg:hidden print:hidden" />

        <!-- ══ SIDEBAR ══════════════════════════════════════════════════════ -->
        <aside class="print:hidden" :class="[
            'fixed inset-y-0 left-0 z-50 flex flex-col bg-white border-r border-gray-200 transition-all duration-300',
            sidebarOpen ? 'w-60' : 'w-0 lg:w-16 overflow-hidden',
        ]">

            <!-- Logo -->
            <Link :href="route('admin.dashboard')"
                class="flex items-center gap-3 px-4 h-16 border-b border-gray-100 shrink-0 hover:bg-gray-50 transition-colors"
                :class="sidebarOpen ? '' : 'lg:justify-center lg:px-0'">
                <img v-if="!logoError" src="/logo.png" alt="An Nur Smart System"
                    class="w-9 h-9 rounded-xl object-contain shrink-0 bg-white ring-1 ring-gray-100" @error="logoError = true" />
                <div v-else
                    class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 flex items-center justify-center shrink-0 shadow-sm shadow-indigo-200">
                    <span class="text-white font-bold text-sm tracking-tight">AN</span>
                </div>
                <Transition name="fade">
                    <div v-if="sidebarOpen" class="min-w-0">
                        <p class="text-sm font-bold text-gray-900 leading-none">An Nur</p>
                        <p class="text-[11px] text-gray-400 mt-1 tracking-wide uppercase">Smart System</p>
                    </div>
                </Transition>
            </Link>

            <!-- Search -->
            <Transition name="fade">
                <div v-if="sidebarOpen" class="px-4 py-3 border-b border-gray-100 shrink-0">
                    <div
                        class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-xl border border-transparent focus-within:border-indigo-200 focus-within:bg-white focus-within:ring-2 focus-within:ring-indigo-100 transition-all">
                        <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input ref="searchInput" v-model="searchMenu" type="text" placeholder="Cari menu..."
                            @keydown.esc="searchMenu = ''; $event.target.blur()"
                            class="bg-transparent text-xs text-gray-600 placeholder-gray-400 outline-none w-full" />
                        <span v-if="!searchMenu"
                            class="text-[10px] text-gray-400 font-mono shrink-0 px-1.5 py-0.5 bg-white border border-gray-200 rounded">⌘K</span>
                    </div>
                </div>
            </Transition>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto py-3 px-3 scrollbar-hide">

                <!-- Hasil pencarian menu -->
                <template v-if="searchActive">
                    <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-widest text-gray-400">
                        Hasil "{{ searchMenu }}"
                    </p>
                    <SidebarItem v-for="m in searchResults" :key="m.href" :href="m.href" :icon="m.icon"
                        :label="m.label" :active="isActiveUrl(m.href)" :collapsed="false" />
                    <div v-if="!searchResults.length" class="px-3 py-8 text-center">
                        <p class="text-xs text-gray-400">Menu tidak ditemukan</p>
                    </div>
                </template>

                <template v-else>

                <!-- Dashboard (superadmin) -->
                <SidebarItem v-if="isSuper" :href="route('admin.dashboard')" icon="grid" label="Dashboard"
                    :active="isActive('admin.dashboard')" :collapsed="!sidebarOpen" />

                <!-- ══ MASTER DATA (superadmin) ═══════════════════════════════ -->
                <SidebarSection v-if="sidebarOpen && isSuper" label="Master Data" />

                <template v-if="isSuper">
                <SidebarItem :href="route('admin.master.tenaga-pendidik.index')" icon="users" label="Tenaga Pendidik"
                    :active="isActive('admin.master.tenaga-pendidik')" :collapsed="!sidebarOpen" />

                <SidebarGroup icon="briefcase" label="Jabatan"
                    :active="isActive('admin.master.jabatan') || isActive('admin.master.jabatan-guru')"
                    :collapsed="!sidebarOpen">
                    <SidebarSubItem :href="route('admin.master.jabatan.index')" label="Daftar Jabatan" />
                    <SidebarSubItem :href="route('admin.master.jabatan.multi')" label="Multi Jabatan Guru" />
                </SidebarGroup>

                <SidebarGroup icon="calendar" label="Jadwal Mengajar"
                    :active="isActive('admin.master.jadwal-mengajar') || isActive('admin.master.tahun-ajaran') || isActive('admin.master.mata-pelajaran')"
                    :collapsed="!sidebarOpen">
                    <SidebarSubItem :href="route('admin.master.tahun-ajaran.index')" label="Tahun Ajaran" />
                    <SidebarSubItem :href="route('admin.master.mata-pelajaran.index')" label="Mata Pelajaran" />
                    <SidebarSubItem :href="route('admin.master.jadwal-mengajar.index')" label="Jadwal Mengajar" />
                </SidebarGroup>
                </template>

                <!-- ══ SMART PAYROLL ════════════════════════════════════════ -->
                <SidebarSection v-if="sidebarOpen && boleh('absensi','monitoring','kinerja','tugas_jabatan','tugas_tambahan','absensi_kegiatan','lembur','pengajuan_izin','gaji_periode','gaji_data','gaji_laporan','kalender_libur')" label="Smart Payroll" />

                <!-- 1. Absensi -->
                <SidebarGroup v-if="boleh('absensi')" icon="clock" label="Absensi" :active="isActive('admin.smart-payroll.absensi')"
                    :collapsed="!sidebarOpen">
                    <SidebarSubItem :href="route('admin.smart-payroll.absensi.harian')" label="Absensi Harian" />
                    <SidebarSubItem :href="route('admin.smart-payroll.absensi.mengajar')" label="Absensi Mengajar" />
                    <SidebarSubItem :href="route('admin.smart-payroll.absensi.rekap')" label="Rekap Absensi" />
                    <SidebarSubItem :href="route('admin.smart-payroll.absensi.koreksi.index')"
                        label="Koreksi Absensi" />
                </SidebarGroup>
                <!-- 2. Monitoring (fitur terpisah — cocok utk peran pimpinan) -->
                <SidebarItem v-if="boleh('monitoring')" :href="route('admin.smart-payroll.monitoring.index')" icon="monitor"
                    label="Monitoring Harian" :active="isActive('admin.smart-payroll.monitoring')"
                    :collapsed="!sidebarOpen" />

                <!-- 3. Kinerja -->
                <SidebarGroup v-if="boleh('kinerja')" icon="trophy" label="Kinerja" :active="isActive('admin.smart-payroll.kinerja')"
                    :collapsed="!sidebarOpen">
                    <SidebarSubItem :href="route('admin.smart-payroll.kinerja.index')" label="Rekap Kinerja" />
                    <SidebarSubItem :href="route('admin.smart-payroll.kinerja.log-kerja')" label="Log Kerja" />
                    <SidebarSubItem :href="route('admin.smart-payroll.kinerja.punishment.index')" label="Punishment" />
                </SidebarGroup>

                <!-- 4. Tugas -->
                <SidebarGroup v-if="boleh('tugas_jabatan','tugas_tambahan','absensi_kegiatan','lembur')" icon="clipboard" label="Tugas"
                    :active="isActive('admin.smart-payroll.tugas-jabatan') || isActive('admin.smart-payroll.tugas-tambahan') || isActive('admin.smart-payroll.absensi-kegiatan') || isActive('admin.smart-payroll.lembur')"
                    :collapsed="!sidebarOpen">
                    <SidebarSubItem v-if="boleh('tugas_jabatan')" :href="route('admin.smart-payroll.tugas-jabatan.index')" label="Tugas Jabatan" />
                    <SidebarSubItem v-if="boleh('tugas_tambahan')" :href="route('admin.smart-payroll.tugas-tambahan.index')" label="Tugas Tambahan" />
                    <SidebarSubItem v-if="boleh('absensi_kegiatan')" :href="route('admin.smart-payroll.absensi-kegiatan.index')"
                        label="Absensi Kegiatan" />
                    <SidebarSubItem v-if="boleh('lembur')" :href="route('admin.smart-payroll.lembur.index')" label="Lembur" />
                </SidebarGroup>

                <!-- 5. Pengajuan Izin -->
                <SidebarItem v-if="boleh('pengajuan_izin')" :href="route('admin.smart-payroll.pengajuan-izin.index')" icon="inbox"
                    label="Pengajuan Izin" :active="isActive('admin.smart-payroll.pengajuan-izin')"
                    :collapsed="!sidebarOpen" :badge="pendingCount > 0 ? pendingCount : null" />

                <!-- 6-8. Kalender Libur / Penggajian / Laporan -->
                <SidebarGroup v-if="boleh('kalender_libur')" icon="calendar" label="Kalender Libur"
                    :active="isActive('admin.smart-payroll.hari-libur') || isActive('admin.smart-payroll.libur-tendik')"
                    :collapsed="!sidebarOpen">
                    <SidebarSubItem :href="route('admin.smart-payroll.hari-libur.index')" label="Hari Libur" />
                    <SidebarSubItem :href="route('admin.smart-payroll.libur-tendik.index')" label="Libur Individu" />
                </SidebarGroup>
                <SidebarGroup v-if="boleh('gaji_periode','gaji_data')" icon="dollar" label="Penggajian"
                    :active="isActive('admin.smart-payroll.penggajian') || isActive('admin.smart-payroll.periode')"
                    :collapsed="!sidebarOpen">
                    <SidebarSubItem v-if="boleh('gaji_periode')" :href="route('admin.smart-payroll.periode.index')" label="Periode Gaji" />
                    <SidebarSubItem v-if="boleh('gaji_data')" :href="route('admin.smart-payroll.penggajian.index')" label="Data Gaji" />
                </SidebarGroup>
                <SidebarGroup v-if="boleh('gaji_laporan')" icon="chart" label="Laporan" :active="isActive('admin.smart-payroll.laporan')"
                    :collapsed="!sidebarOpen">
                    <SidebarSubItem :href="route('admin.smart-payroll.laporan.ringkasan')" label="Ringkasan" />
                    <SidebarSubItem :href="route('admin.smart-payroll.laporan.kehadiran')" label="Kehadiran" />
                    <SidebarSubItem :href="route('admin.smart-payroll.laporan.mengajar')" label="Absensi Mengajar" />
                    <SidebarSubItem :href="route('admin.smart-payroll.laporan.absensi')" label="Absensi" />
                    <SidebarSubItem :href="route('admin.smart-payroll.laporan.penggajian')" label="Penggajian" />
                    <SidebarSubItem :href="route('admin.smart-payroll.laporan.vakasi')" label="Vakasi" />
                </SidebarGroup>

                <!-- ══ SMART EDUCATION ══════════════════════════════════════ -->
                <SidebarSection v-if="sidebarOpen && boleh('se_santri','se_kelas','se_ekskul','se_jurnal','se_tahfidz','se_tahsin','se_laporan')" label="Smart Education" />

                <SidebarGroup v-if="boleh('se_santri','se_kelas','se_ekskul')" icon="users" label="Data Santri"
                    :active="isActive('admin.smart-education.santri') || isActive('admin.smart-education.kelas') || isActive('admin.smart-education.ekstrakurikuler')"
                    :collapsed="!sidebarOpen">
                    <SidebarSubItem v-if="boleh('se_santri')" :href="route('admin.smart-education.santri.index')" label="Santri" />
                    <SidebarSubItem v-if="boleh('se_kelas')" :href="route('admin.smart-education.kelas.index')" label="Kelas" />
                    <SidebarSubItem v-if="boleh('se_ekskul')" :href="route('admin.smart-education.ekstrakurikuler.index')" label="Ekstrakurikuler" />
                </SidebarGroup>
                <SidebarItem v-if="boleh('se_jurnal')" :href="route('admin.smart-education.jurnal.index')" icon="clipboard" label="Jurnal Mengajar"
                    :active="isActive('admin.smart-education.jurnal')" :collapsed="!sidebarOpen" />
                <SidebarGroup v-if="boleh('se_tahfidz')" icon="trophy" label="Tahfidz"
                    :active="isActive('admin.smart-education.tahfidz') || isActive('admin.smart-education.tahfidz-monitoring')"
                    :collapsed="!sidebarOpen">
                    <SidebarSubItem :href="route('admin.smart-education.tahfidz.index')" label="Pengaturan" />
                    <SidebarSubItem :href="route('admin.smart-education.tahfidz-monitoring.index')" label="Monitoring" />
                </SidebarGroup>
                <SidebarGroup v-if="boleh('se_tahsin')" icon="monitor" label="Tahsin"
                    :active="isActive('admin.smart-education.tahsin') || isActive('admin.smart-education.materi-tahsin') || isActive('admin.smart-education.tahsin-monitoring')"
                    :collapsed="!sidebarOpen">
                    <SidebarSubItem :href="route('admin.smart-education.tahsin.index')" label="Pengaturan" />
                    <SidebarSubItem :href="route('admin.smart-education.materi-tahsin.index')" label="Materi" />
                    <SidebarSubItem :href="route('admin.smart-education.tahsin-monitoring.index')" label="Monitoring" />
                </SidebarGroup>
                <SidebarItem v-if="boleh('se_laporan')" :href="route('admin.smart-education.laporan.index')" icon="chart" label="Laporan"
                    :active="isActive('admin.smart-education.laporan')" :collapsed="!sidebarOpen" />

                <!-- ══ KESISWAAN ═══════════════════════════════════════════ -->
                <SidebarSection v-if="sidebarOpen && boleh('perizinan_santri','smart_health','smart_habbit','piket')" label="Kesiswaan" />
                <SidebarItem v-if="boleh('perizinan_santri')" :href="route('admin.perizinan.index')" icon="calendar" label="Perizinan Santri"
                    :active="isActive('admin.perizinan')" :collapsed="!sidebarOpen" />
                <SidebarItem v-if="boleh('smart_health')" :href="route('admin.smart-health.index')" icon="academic-cap" label="Smart Health"
                    :active="isActive('admin.smart-health')" :collapsed="!sidebarOpen" />

                <!-- Smart Habbit (controlling & kebiasaan santri) -->
                <SidebarGroup v-if="boleh('smart_habbit')" icon="qrcode" label="Smart Habbit"
                    :active="isActive('admin.smart-habbit')"
                    :collapsed="!sidebarOpen">
                    <SidebarSubItem :href="route('admin.smart-habbit.controlling.index')" label="Setting Controlling" />
                    <SidebarSubItem :href="route('admin.smart-habbit.controlling.scan')" label="Scan Kiosk" />
                    <SidebarSubItem :href="route('admin.smart-habbit.controlling.kartu')" label="Kartu Barcode" />
                    <SidebarSubItem :href="route('admin.smart-habbit.controlling.rekap')" label="Rekap Controlling" />
                    <SidebarSubItem :href="route('admin.smart-habbit.controlling.harian')" label="Detail Harian" />
                    <SidebarSubItem :href="route('admin.smart-habbit.eksekusi.index')" label="Smart Eksekusi" />
                    <SidebarSubItem :href="route('admin.smart-habbit.outbox.index')" label="Monitor Outbox" />
                </SidebarGroup>

                <!-- Guru Piket (penilaian kedisiplinan) -->
                <SidebarGroup v-if="boleh('piket')" icon="trophy" label="Guru Piket"
                    :active="isActive('admin.piket') || isActive('admin.smart-payroll.kegiatan-penting')"
                    :collapsed="!sidebarOpen">
                    <SidebarSubItem :href="route('admin.piket.kategori.index')" label="Rubrik Kategori" />
                    <SidebarSubItem :href="route('admin.piket.jadwal.index')" label="Penunjukan Piket" />
                    <SidebarSubItem :href="route('admin.piket.sanggah.index')" label="Sanggah Penilaian" />
                    <SidebarSubItem :href="route('admin.smart-payroll.kegiatan-penting.index')" label="Kegiatan Penting" />
                    <SidebarSubItem :href="route('admin.smart-payroll.kegiatan-penting.laporan')" label="Laporan Kegiatan" />
                </SidebarGroup>

                <!-- ══ SARANA ══════════════════════════════════════════════ -->
                <SidebarSection v-if="sidebarOpen && boleh('inventaris')" label="Sarana" />
                <SidebarItem v-if="boleh('inventaris')" :href="route('admin.inventaris.index')" icon="briefcase" label="Inventaris"
                    :active="isActive('admin.inventaris')" :collapsed="!sidebarOpen" />

                <!-- ══ PENGATURAN ═══════════════════════════════════════════ -->
                <SidebarSection v-if="sidebarOpen && (isSuper || boleh('whatsapp'))" label="Pengaturan" />

                <!-- Konfigurasi penggajian (superadmin) -->
                <template v-if="isSuper">
                <SidebarGroup icon="dollar" label="Setting Gaji"
                    :active="isActive('admin.smart-payroll.setting-gaji') || isActive('admin.smart-payroll.setting-potongan') || isActive('admin.smart-payroll.jadwal-shift')"
                    :collapsed="!sidebarOpen">
                    <SidebarSubItem :href="route('admin.smart-payroll.setting-gaji.pokok.index')" label="Gaji Pokok" />
                    <SidebarSubItem :href="route('admin.smart-payroll.setting-gaji.vakasi.index')" label="Vakasi" />
                    <SidebarSubItem :href="route('admin.smart-payroll.setting-gaji.jam-kerja.index')" label="Jam Kerja" />
                    <SidebarSubItem :href="route('admin.smart-payroll.jadwal-shift.index')" label="Shift Satpam" />
                    <SidebarSubItem :href="route('admin.smart-payroll.setting-potongan.index')" label="Potongan Gaji" />
                </SidebarGroup>
                <SidebarGroup icon="sliders" label="Setting Operasional"
                    :active="isActive('admin.smart-payroll.setting-kinerja') || isActive('admin.smart-payroll.setting-lokasi') || isActive('admin.smart-payroll.setting-pengajuan') || isActive('admin.smart-payroll.setting-notifikasi')"
                    :collapsed="!sidebarOpen">
                    <SidebarSubItem :href="route('admin.smart-payroll.setting-kinerja.index')" label="Penilaian Kinerja" />
                    <SidebarSubItem :href="route('admin.smart-payroll.setting-lokasi.index')" label="Lokasi Absensi" />
                    <SidebarSubItem :href="route('admin.smart-payroll.setting-pengajuan.index')" label="Aturan Pengajuan" />
                    <SidebarSubItem :href="route('admin.smart-payroll.setting-notifikasi.index')" label="Notifikasi" />
                </SidebarGroup>
                </template>

                <!-- WhatsApp (modul whatsapp) -->
                <SidebarGroup v-if="boleh('whatsapp')" icon="inbox" label="WhatsApp"
                    :active="isActive('admin.smart-payroll.setting-wa') || isActive('admin.smart-payroll.wa-outbox') || isActive('admin.smart-payroll.wa-inbox')"
                    :collapsed="!sidebarOpen">
                    <SidebarSubItem :href="route('admin.smart-payroll.setting-wa.index')" label="Template Pesan" />
                    <SidebarSubItem :href="route('admin.smart-payroll.wa-inbox.index')" label="Kotak Masuk" />
                    <SidebarSubItem :href="route('admin.smart-payroll.wa-outbox.index')" label="Monitor Outbox" />
                </SidebarGroup>

                <!-- Sistem (superadmin) -->
                <SidebarGroup v-if="isSuper" icon="settings" label="Sistem"
                    :active="isActive('admin.peran') || isActive('admin.akun') || isActive('admin.pengumuman')"
                    :collapsed="!sidebarOpen">
                    <SidebarSubItem :href="route('admin.peran.index')" label="Kelola Peran" />
                    <SidebarSubItem :href="route('admin.akun.index')" label="Kelola Akun" />
                    <SidebarSubItem :href="route('admin.pengumuman.index')" label="Pengumuman" />
                </SidebarGroup>

                </template>

            </nav>

            <!-- User card bawah sidebar -->
            <div v-if="sidebarOpen" class="p-3 border-t border-gray-100 shrink-0">
                <div
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 cursor-pointer transition-colors">
                    <div class="relative shrink-0">
                        <img v-if="userFoto" :src="userFoto"
                            class="w-8 h-8 rounded-full object-cover ring-2 ring-indigo-100" />
                        <div v-else
                            class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 flex items-center justify-center">
                            <span class="text-white text-xs font-bold">{{ userInitial }}</span>
                        </div>
                        <span
                            class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-emerald-400 rounded-full border-2 border-white"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate leading-none">{{ userName }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ roleLabel }}</p>
                    </div>
                    <button @click="logout" title="Keluar"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Toggle collapse desktop -->
            <div class="hidden lg:block p-2 border-t border-gray-100 shrink-0">
                <button @click="sidebarOpen = !sidebarOpen"
                    class="w-full flex items-center justify-center h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4 transition-transform duration-300" :class="sidebarOpen ? '' : 'rotate-180'"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                    </svg>
                </button>
            </div>
        </aside>

        <!-- ══ MAIN ══════════════════════════════════════════════════════════ -->
        <div class="print:!ml-0 print:min-h-0" :class="[
            'flex-1 flex flex-col min-h-screen min-w-0 transition-all duration-300',
            sidebarOpen ? 'lg:ml-60' : 'lg:ml-16'
        ]">

            <!-- Topbar -->
            <header
                class="sticky top-0 z-40 h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-6 print:hidden">

                <!-- Kiri: hamburger + breadcrumb -->
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = !sidebarOpen"
                        class="p-2 -ml-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors lg:hidden">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="hidden sm:inline text-gray-400">An Nur</span>
                            <svg class="hidden sm:inline w-4 h-4 text-gray-300" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            <span class="font-semibold text-gray-900 truncate">{{ title }}</span>
                        </div>
                        <p v-if="subtitle" class="text-xs text-gray-400 truncate mt-0.5">{{ subtitle }}</p>
                    </div>
                </div>

                <!-- Kanan -->
                <div class="flex items-center gap-1.5">

                    <!-- Tanggal -->
                    <span
                        class="hidden md:inline-flex items-center gap-1.5 text-xs text-gray-400 bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 mr-2">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        {{ tanggalHariIni }}
                    </span>

                    <!-- Notifikasi -->
                    <div class="relative">
                        <button @click="toggleNotif" title="Notifikasi"
                            class="relative p-2 rounded-xl transition-colors"
                            :class="notifOpen ? 'text-indigo-600 bg-indigo-50' : 'text-gray-400 hover:text-indigo-600 hover:bg-indigo-50'">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span v-if="notifUnread > 0"
                                class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 bg-red-500 text-white text-[10px] font-bold rounded-full ring-2 ring-white flex items-center justify-center animate-[pulse_2s_ease-in-out_infinite]">
                                {{ notifUnread > 9 ? '9+' : notifUnread }}
                            </span>
                        </button>

                        <!-- Backdrop penutup (klik luar → tutup) -->
                        <div v-if="notifOpen" class="fixed inset-0 z-[55]" @click="notifOpen = false"></div>

                        <div v-if="notifOpen"
                            class="dropdown-pop absolute right-0 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] bg-white rounded-2xl shadow-2xl shadow-indigo-900/10 border border-gray-100 z-[56] overflow-hidden">
                                <!-- Header gradient -->
                                <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-indigo-600 to-violet-600 text-white">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                        <p class="text-sm font-bold">Notifikasi</p>
                                        <span v-if="notifUnread > 0" class="px-1.5 py-0.5 rounded-full bg-white/20 text-[10px] font-bold">{{ notifUnread }} baru</span>
                                    </div>
                                    <button v-if="notifUnread > 0" @click="tandaiSemuaDibaca"
                                        class="text-[11px] font-semibold text-white/90 hover:text-white transition-colors">
                                        Tandai semua
                                    </button>
                                </div>

                                <div class="max-h-[26rem] overflow-y-auto scrollbar-hide">
                                    <div v-if="notifLoading" class="py-12 text-center text-xs text-gray-400">
                                        <svg class="w-5 h-5 mx-auto mb-2 animate-spin text-indigo-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                                        Memuat…
                                    </div>
                                    <template v-else-if="notifList.length">
                                        <button v-for="n in notifList" :key="n.id" @click="bukaNotif(n)"
                                            class="w-full text-left flex gap-3 px-4 py-3 hover:bg-indigo-50/50 transition-colors border-b border-gray-50 last:border-0"
                                            :class="!n.sudah_dibaca ? 'bg-indigo-50/40' : ''">
                                            <span class="w-9 h-9 rounded-xl grid place-items-center shrink-0" :style="{ background: notifStyle(n.tipe).c + '1A' }">
                                                <svg class="w-[18px] h-[18px]" :style="{ color: notifStyle(n.tipe).c }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" :d="notifStyle(n.tipe).icon"/></svg>
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-1.5">
                                                    <p class="text-xs font-bold text-gray-800 truncate">{{ n.judul }}</p>
                                                    <span v-if="!n.sudah_dibaca" class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                                </div>
                                                <p class="text-[11px] text-gray-500 line-clamp-2 mt-0.5 leading-snug">{{ n.pesan }}</p>
                                                <p class="text-[10px] text-gray-400 mt-1">{{ n.waktu }}</p>
                                            </div>
                                            <svg v-if="n.link" class="w-3.5 h-3.5 text-gray-300 shrink-0 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg>
                                        </button>
                                    </template>
                                    <div v-else class="py-14 text-center">
                                        <div class="w-12 h-12 mx-auto rounded-2xl bg-indigo-50 grid place-items-center mb-2.5">
                                            <svg class="w-6 h-6 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5" />
                                            </svg>
                                        </div>
                                        <p class="text-xs font-semibold text-gray-600">Belum ada notifikasi</p>
                                        <p class="text-[11px] text-gray-400 mt-0.5">Pemberitahuan penting akan muncul di sini.</p>
                                    </div>
                                </div>
                            </div>
                    </div>

                    <div class="w-px h-6 bg-gray-200 mx-1"></div>

                    <!-- User dropdown -->
                    <div class="relative">
                        <button @click="userMenuOpen = !userMenuOpen"
                            class="flex items-center gap-2.5 pl-2 pr-3 py-1.5 rounded-xl hover:bg-gray-100 transition-colors">
                            <div class="relative">
                                <img v-if="userFoto" :src="userFoto"
                                    class="w-8 h-8 rounded-full object-cover shadow-sm" />
                                <div v-else
                                    class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 flex items-center justify-center shadow-sm">
                                    <span class="text-white text-xs font-bold">{{ userInitial }}</span>
                                </div>
                            </div>
                            <div class="hidden sm:block text-left">
                                <p class="text-sm font-semibold text-gray-800 leading-none">{{ userName }}</p>
                                <p class="text-xs text-gray-400 leading-none mt-0.5">{{ roleLabel }}</p>
                            </div>
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div v-if="userMenuOpen" class="fixed inset-0 z-[55]" @click="userMenuOpen = false"></div>
                        <div v-if="userMenuOpen"
                                class="dropdown-pop absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-gray-100 py-1.5 z-[56] overflow-hidden">
                                <div class="px-4 py-3 bg-gray-50 border-b border-gray-100 mb-1 flex items-center gap-3">
                                    <img v-if="userFoto" :src="userFoto"
                                        class="w-9 h-9 rounded-full object-cover shrink-0" />
                                    <div v-else
                                        class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 flex items-center justify-center shrink-0">
                                        <span class="text-white text-sm font-bold">{{ userInitial }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-800 truncate">{{ userName }}</p>
                                        <p class="text-xs text-gray-400 truncate">{{ userEmail }}</p>
                                    </div>
                                </div>
                                <div class="px-4 py-2 flex items-center gap-2 text-xs text-gray-400">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-600 font-semibold">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                        {{ roleLabel }}
                                    </span>
                                </div>
                                <div class="h-px bg-gray-100 my-1.5 mx-3"></div>
                                <button @click="logout"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    Keluar
                                </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Flash message -->
            <FlashMessage />

            <!-- Host global konfirmasi aksi (useConfirm) -->
            <ConfirmHost />

            <!-- Page content -->
            <main class="flex-1 p-4 sm:p-6 print:p-0 min-w-0 overflow-x-hidden">
                <slot />
            </main>

            <!-- Footer -->
            <footer class="px-4 sm:px-6 py-4 border-t border-gray-200 bg-white print:hidden">
                <p class="text-xs text-gray-400 text-center">
                    © {{ tahun }} An Nur Smart System — Mubosta Dev
                </p>
            </footer>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import SidebarItem from '@/Components/Sidebar/SidebarItem.vue'
import SidebarGroup from '@/Components/Sidebar/SidebarGroup.vue'
import SidebarSubItem from '@/Components/Sidebar/SidebarSubItem.vue'
import SidebarSection from '@/Components/Sidebar/SidebarSection.vue'
import FlashMessage from '@/Components/FlashMessage.vue'
import ConfirmHost from '@/Components/ConfirmHost.vue'

defineProps({
    title: { type: String, default: 'Dashboard' },
    subtitle: { type: String, default: '' },
})

const page = usePage()
const sidebarOpen = ref(true)
const userMenuOpen = ref(false)
const isMobile = ref(false)
const searchMenu = ref('')
const searchInput = ref(null)
const logoError = ref(false)

// ── User info ─────────────────────────────────────────────────────────────────
const userInitial = computed(() => (page.props.auth?.user?.name ?? 'A').charAt(0).toUpperCase())
const userName = computed(() => page.props.auth?.user?.name ?? '')
const userEmail = computed(() => page.props.auth?.user?.email ?? '')
// `foto` dari HandleInertiaRequests sudah berupa URL penuh (asset('storage/..')).
const userFoto = computed(() => page.props.auth?.user?.foto ?? null)
const pendingCount = computed(() => page.props.auth?.pending_pengajuan ?? 0)
const tahun = new Date().getFullYear()

// ── RBAC: filter menu per modul ────────────────────────────────────────────────
const isSuper = computed(() => page.props.auth?.is_superadmin ?? false)
const modulSaya = computed(() => page.props.auth?.modul ?? [])
function boleh(...kodes) {
    if (isSuper.value) return true
    return kodes.some(k => modulSaya.value.includes(k))
}
const roleLabel = computed(() => isSuper.value ? 'Super Admin' : 'Admin')

// ── Notifikasi (dropdown lonceng) ──────────────────────────────────────────────
const notifOpen = ref(false)
const notifList = ref([])
const notifLoading = ref(false)
const notifUnread = ref(page.props.auth?.unread_notif ?? 0)

function xsrfToken() {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
    return m ? decodeURIComponent(m[1]) : ''
}
async function apiGet(url) {
    const r = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    return r.json()
}
async function apiPost(url) {
    const r = await fetch(url, {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
    })
    return r.json()
}

// Ikon + warna per tipe notifikasi (selaras dgn PWA guru).
function notifStyle(tipe) {
    switch (tipe) {
        case 'tugas_baru':   return { c: '#0C78FF', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' }
        case 'tugas_update': return { c: '#6366F1', icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' }
        case 'penggajian':   return { c: '#0284C7', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V6m0 12v-2m9-4a9 9 0 11-18 0 9 9 0 0118 0z' }
        case 'koreksi':      return { c: '#F59E0B', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' }
        case 'absensi':      return { c: '#059669', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7l2 2 4-4' }
        default:             return { c: '#6366F1', icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9' }
    }
}

async function loadNotif() {
    notifLoading.value = true
    try {
        const res = await apiGet(route('admin.notifikasi.index'))
        if (res.success) { notifList.value = res.data; notifUnread.value = res.unread }
    } catch (e) { /* diamkan — badge tetap dari nilai awal */ }
    finally { notifLoading.value = false }
}
function toggleNotif() {
    notifOpen.value = !notifOpen.value
    if (notifOpen.value) loadNotif()
}
async function tandaiSemuaDibaca() {
    try {
        await apiPost(route('admin.notifikasi.baca-semua'))
        notifList.value = notifList.value.map(n => ({ ...n, sudah_dibaca: true }))
        notifUnread.value = 0
    } catch (e) { /* noop */ }
}
async function bukaNotif(n) {
    if (!n.sudah_dibaca) {
        try { await apiPost(route('admin.notifikasi.baca', n.id)) } catch (e) { /* noop */ }
        n.sudah_dibaca = true
        notifUnread.value = Math.max(0, notifUnread.value - 1)
    }
    notifOpen.value = false
    if (n.link) router.visit(n.link)
}

const tanggalHariIni = computed(() =>
    new Date().toLocaleDateString('id-ID', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    })
)

// ── Helper active route ───────────────────────────────────────────────────────
function isActive(routeName) {
    try { return route().current(routeName + '*') } catch { return false }
}
function isActiveUrl(href) {
    try {
        const path = page.url.split('?')[0].replace(/\/$/, '')
        const h = href.replace(/^https?:\/\/[^/]+/, '').replace(/\/$/, '')
        return path === h || path.startsWith(h + '/')
    } catch { return false }
}

// ── Pencarian menu (flat list semua menu) ──────────────────────────────────────
const menuLinks = computed(() => {
    const r = (name) => { try { return route(name) } catch { return null } }
    return [
        { label: 'Dashboard', href: r('admin.dashboard'), icon: 'grid' },
        { label: 'Tenaga Pendidik', href: r('admin.master.tenaga-pendidik.index'), icon: 'users' },
        { label: 'Daftar Jabatan', href: r('admin.master.jabatan.index'), icon: 'briefcase' },
        { label: 'Multi Jabatan Guru', href: r('admin.master.jabatan.multi'), icon: 'briefcase' },
        { label: 'Tahun Ajaran', href: r('admin.master.tahun-ajaran.index'), icon: 'calendar' },
        { label: 'Mata Pelajaran', href: r('admin.master.mata-pelajaran.index'), icon: 'calendar' },
        { label: 'Jadwal Mengajar', href: r('admin.master.jadwal-mengajar.index'), icon: 'calendar' },
        { label: 'Absensi Harian', href: r('admin.smart-payroll.absensi.harian'), icon: 'clock' },
        { label: 'Absensi Mengajar', href: r('admin.smart-payroll.absensi.mengajar'), icon: 'clock' },
        { label: 'Rekap Absensi', href: r('admin.smart-payroll.absensi.rekap'), icon: 'clock' },
        { label: 'Koreksi Absensi', href: r('admin.smart-payroll.absensi.koreksi.index'), icon: 'clock' },
        { label: 'Monitoring Harian', href: r('admin.smart-payroll.monitoring.index'), icon: 'monitor' },
        { label: 'Rekap Kinerja', href: r('admin.smart-payroll.kinerja.index'), icon: 'trophy' },
        { label: 'Log Kerja', href: r('admin.smart-payroll.kinerja.log-kerja'), icon: 'trophy' },
        { label: 'Punishment', href: r('admin.smart-payroll.kinerja.punishment.index'), icon: 'trophy' },
        { label: 'Tugas Jabatan', href: r('admin.smart-payroll.tugas-jabatan.index'), icon: 'clipboard' },
        { label: 'Tugas Tambahan', href: r('admin.smart-payroll.tugas-tambahan.index'), icon: 'clipboard' },
        { label: 'Absensi Kegiatan', href: r('admin.smart-payroll.absensi-kegiatan.index'), icon: 'clipboard' },
        { label: 'Lembur', href: r('admin.smart-payroll.lembur.index'), icon: 'clipboard' },
        { label: 'Pengajuan Izin', href: r('admin.smart-payroll.pengajuan-izin.index'), icon: 'inbox' },
        { label: 'Hari Libur', href: r('admin.smart-payroll.hari-libur.index'), icon: 'ban' },
        { label: 'Libur Individu', href: r('admin.smart-payroll.libur-tendik.index'), icon: 'calendar' },
        { label: 'Periode Gaji', href: r('admin.smart-payroll.periode.index'), icon: 'dollar' },
        { label: 'Data Gaji', href: r('admin.smart-payroll.penggajian.index'), icon: 'dollar' },
        { label: 'Laporan Ringkasan', href: r('admin.smart-payroll.laporan.ringkasan'), icon: 'chart' },
        { label: 'Laporan Kehadiran', href: r('admin.smart-payroll.laporan.kehadiran'), icon: 'chart' },
        { label: 'Laporan Absensi Mengajar', href: r('admin.smart-payroll.laporan.mengajar'), icon: 'chart' },
        { label: 'Laporan Absensi', href: r('admin.smart-payroll.laporan.absensi'), icon: 'chart' },
        { label: 'Laporan Penggajian', href: r('admin.smart-payroll.laporan.penggajian'), icon: 'chart' },
        { label: 'Laporan Vakasi', href: r('admin.smart-payroll.laporan.vakasi'), icon: 'chart' },
        { label: 'Gaji Pokok', href: r('admin.smart-payroll.setting-gaji.pokok.index'), icon: 'settings' },
        { label: 'Setting Vakasi', href: r('admin.smart-payroll.setting-gaji.vakasi.index'), icon: 'settings' },
        { label: 'Jam Kerja', href: r('admin.smart-payroll.setting-gaji.jam-kerja.index'), icon: 'settings' },
        { label: 'Setting Kinerja', href: r('admin.smart-payroll.setting-kinerja.index'), icon: 'bar-chart' },
        { label: 'Lokasi Absensi', href: r('admin.smart-payroll.setting-lokasi.index'), icon: 'map-pin' },
        { label: 'Setting Potongan', href: r('admin.smart-payroll.setting-potongan.index'), icon: 'minus-circle' },
        { label: 'Setting Notifikasi', href: r('admin.smart-payroll.setting-notifikasi.index'), icon: 'bell' },
        { label: 'Setting Pengajuan', href: r('admin.smart-payroll.setting-pengajuan.index'), icon: 'sliders' },
        { label: 'Santri', href: r('admin.smart-education.santri.index'), icon: 'academic-cap' },
        { label: 'Kelas', href: r('admin.smart-education.kelas.index'), icon: 'book' },
        { label: 'Ekstrakurikuler', href: r('admin.smart-education.ekstrakurikuler.index'), icon: 'academic-cap' },
        { label: 'Jurnal Mengajar', href: r('admin.smart-education.jurnal.index'), icon: 'clipboard' },
        { label: 'Laporan Pembelajaran', href: r('admin.smart-education.laporan.index'), icon: 'chart' },
        { label: 'Smart Tahfidz', href: r('admin.smart-education.tahfidz.index'), icon: 'book' },
        { label: 'Monitoring Tahfidz', href: r('admin.smart-education.tahfidz-monitoring.index'), icon: 'trophy' },
        { label: 'Smart Tahsin', href: r('admin.smart-education.tahsin.index'), icon: 'book' },
        { label: 'Materi Tahsin', href: r('admin.smart-education.materi-tahsin.index'), icon: 'book' },
        { label: 'Monitoring Tahsin', href: r('admin.smart-education.tahsin-monitoring.index'), icon: 'bar-chart' },
        { label: 'Setting Controlling', href: r('admin.smart-habbit.controlling.index'), icon: 'qrcode' },
        { label: 'Scan Kiosk Controlling', href: r('admin.smart-habbit.controlling.scan'), icon: 'qrcode' },
        { label: 'Kartu Barcode', href: r('admin.smart-habbit.controlling.kartu'), icon: 'qrcode' },
        { label: 'Rekap Controlling', href: r('admin.smart-habbit.controlling.rekap'), icon: 'chart' },
        { label: 'Detail Harian Controlling', href: r('admin.smart-habbit.controlling.harian'), icon: 'monitor' },
        { label: 'Smart Eksekusi', href: r('admin.smart-habbit.eksekusi.index'), icon: 'clipboard' },
        { label: 'Monitor Outbox', href: r('admin.smart-habbit.outbox.index'), icon: 'inbox' },
        { label: 'Rubrik Kategori Piket', href: r('admin.piket.kategori.index'), icon: 'trophy' },
        { label: 'Penunjukan Piket', href: r('admin.piket.jadwal.index'), icon: 'trophy' },
        { label: 'Sanggah Penilaian', href: r('admin.piket.sanggah.index'), icon: 'trophy' },
        { label: 'Perizinan Santri', href: r('admin.perizinan.index'), icon: 'calendar' },
        { label: 'Smart Health', href: r('admin.smart-health.index'), icon: 'academic-cap' },
        { label: 'Inventaris', href: r('admin.inventaris.index'), icon: 'briefcase' },
        { label: 'Template WhatsApp', href: r('admin.smart-payroll.setting-wa.index'), icon: 'inbox' },
        { label: 'Monitor WhatsApp', href: r('admin.smart-payroll.wa-outbox.index'), icon: 'monitor' },
        { label: 'Kotak Masuk WA', href: r('admin.smart-payroll.wa-inbox.index'), icon: 'inbox' },
    ].filter(m => m.href)
})
const searchActive = computed(() => searchMenu.value.trim().length > 0)
const searchResults = computed(() => {
    const q = searchMenu.value.trim().toLowerCase()
    if (!q) return []
    return menuLinks.value.filter(m => m.label.toLowerCase().includes(q))
})

// ── Logout ────────────────────────────────────────────────────────────────────
function logout() {
    userMenuOpen.value = false
    router.post(route('logout'))
}

// ── Responsive ────────────────────────────────────────────────────────────────
function checkMobile() {
    isMobile.value = window.innerWidth < 1024
    sidebarOpen.value = !isMobile.value
}

// ── Shortcut ⌘K / Ctrl+K → buka sidebar & fokus pencarian ──────────────────────
function onKeydown(e) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault()
        sidebarOpen.value = true
        nextTick(() => searchInput.value?.focus())
    }
}

// Polling badge notifikasi (tiap 60 dtk) agar lonceng jadi reminder real-time.
let notifTimer = null
async function pollNotifCount() {
    try {
        const res = await apiGet(route('admin.notifikasi.count'))
        if (res.success) notifUnread.value = res.unread
    } catch (e) { /* diamkan */ }
}

onMounted(() => {
    checkMobile()
    window.addEventListener('resize', checkMobile)
    window.addEventListener('keydown', onKeydown)
    notifTimer = setInterval(pollNotifCount, 60000)
})
onUnmounted(() => {
    window.removeEventListener('resize', checkMobile)
    window.removeEventListener('keydown', onKeydown)
    if (notifTimer) clearInterval(notifTimer)
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Animasi masuk dropdown — pakai @keyframes (self-completing) agar tak pernah
   tersangkut di opacity:0 seperti transition berbasis toggle-class. */
.dropdown-pop {
    animation: dropdownPop 0.16s ease-out;
    transform-origin: top right;
}

@keyframes dropdownPop {
    from { opacity: 0; transform: translateY(-6px) scale(0.98); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
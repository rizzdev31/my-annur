<template>
    <AdminLayout title="Monitoring Kinerja" subtitle="Smart Payroll">

        <Head title="Monitoring Kinerja" />

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xl font-semibold text-gray-900">Monitoring Kinerja</h2>
                    <span v-if="periode?.berjalan"
                        class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700">
                        periode berjalan
                    </span>
                </div>
                <!-- Jendela penilaian WAJIB terlihat: label "Oktober" di sini berarti
                     26 Sep-25 Okt, bukan 1-31 Oktober. Tanpa baris ini, tanggal 26-31
                     terbaca seolah periode lama masih berjalan. -->
                <p v-if="periode" class="text-sm text-gray-500 mt-0.5">
                    <b class="text-gray-700">{{ periode.label }}</b> ·
                    dinilai {{ periode.dari }} &ndash; {{ periode.sampai }}
                    <span v-if="!periode.ikut_gaji" class="text-amber-600">(bulan kalender)</span>
                </p>
                <p v-else class="text-sm text-gray-400 mt-0.5">Log kerja harian & skor efisiensi tenaga pendidik</p>
            </div>
            <div class="flex items-center gap-2">
                <!-- Filter bulan/tahun -->
                <select v-model="filterBulan" @change="doFilter"
                    class="px-3 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:border-indigo-500 bg-white">
                    <option v-for="b in bulanList" :key="b.value" :value="b.value">{{ b.label }}</option>
                </select>
                <select v-model="filterTahun" @change="doFilter"
                    class="px-3 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 focus:outline-none focus:border-indigo-500 bg-white">
                    <option v-for="t in tahunList" :key="t" :value="t">{{ t }}</option>
                </select>
                <button @click="hitungRekap" :disabled="menghitung"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors disabled:opacity-60">
                    <svg class="w-4 h-4" :class="menghitung ? `animate-spin` : ``" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    {{ menghitung ? 'Menghitung...' : 'Hitung Rekap' }}
                </button>
                <button @click="resetSemua" :disabled="mereset"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-semibold transition-colors disabled:opacity-60">
                    {{ mereset ? 'Mereset…' : 'Reset Semua' }}
                </button>
            </div>
        </div>

        <!-- Summary strip -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                <p class="text-2xl font-bold text-gray-900">{{ ringkasan.total_log_bulan_ini ?? 0 }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Log kerja bulan ini</p>
            </div>
            <div class="bg-white rounded-xl border border-amber-200 bg-amber-50/30 px-4 py-3">
                <p class="text-2xl font-bold text-amber-600">{{ ringkasan.log_pending_verifikasi ?? 0 }}</p>
                <p class="text-xs text-amber-500 mt-0.5">Pending verifikasi</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                <p class="text-2xl font-bold text-indigo-600">{{ Math.round(ringkasan.rata_skor_total ?? 0) }}%</p>
                <p class="text-xs text-gray-400 mt-0.5">Rata-rata skor kinerja</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-2xl font-bold text-emerald-600">{{ ringkasan.guru_sangat_baik ?? 0 }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Sangat baik</p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold text-red-500">{{ ringkasan.guru_perlu_perhatian ?? 0 }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Perlu perhatian</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kunci skor periode: kewenangan superadmin, dikerjakan dari sini.
             Rutenya tetap dijaga role:super_admin, jadi menyembunyikan tombol
             bukan satu-satunya pengamanan. -->
        <div v-if="bolehMengunci && periode?.kunci" class="bg-white rounded-2xl border border-gray-200 p-4 mb-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-gray-800">Kunci Skor Periode</h3>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold"
                            :class="semuaTerkunci ? 'bg-emerald-50 text-emerald-700'
                                : (periode.kunci.terkunci > 0 ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600')">
                            {{ periode.kunci.terkunci }} dari {{ periode.kunci.total }} terkunci
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        Skor yang terkunci berhenti berubah mengikuti data yang masuk belakangan.
                        <span v-if="periode.kunci.periode_penggajian_terkunci" class="text-amber-700 font-medium">
                            Periode penggajiannya sudah dikunci bendahara, jadi skornya beku dari atas.
                        </span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button v-if="!semuaTerkunci" @click="bukaDialogKunci('kunci')"
                        class="px-4 py-2 text-sm font-semibold rounded-xl bg-[#2E3160] text-white hover:bg-[#252850] transition">
                        Kunci Periode Ini
                    </button>
                    <button v-if="periode.kunci.terkunci > 0" @click="bukaDialogKunci('buka')"
                        class="px-4 py-2 text-sm font-semibold rounded-xl border border-gray-200 text-gray-700 hover:bg-gray-50 transition">
                        Buka Kunci
                    </button>
                </div>
            </div>
        </div>

        <!-- Dialog kunci / buka kunci -->
        <div v-if="kunciDialog.buka" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/40" @click="kunciDialog.kirim || (kunciDialog.buka = false)"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ kunciDialog.mode === 'kunci' ? 'Kunci Skor Periode' : 'Buka Kunci Skor Periode' }}
                </h3>
                <p class="text-sm text-gray-500 mt-1">
                    <b>{{ periode?.label }}</b> &middot; dinilai {{ periode?.dari }} &ndash; {{ periode?.sampai }}
                </p>

                <div class="mt-4 rounded-xl p-3 text-xs space-y-1"
                    :class="kunciDialog.mode === 'kunci'
                        ? 'bg-gray-50 border border-gray-100 text-gray-600'
                        : 'bg-amber-50 border border-amber-100 text-amber-800'">
                    <template v-if="kunciDialog.mode === 'kunci'">
                        <p>Skor {{ periode.kunci.total - periode.kunci.terkunci }} guru akan dibekukan apa adanya.</p>
                        <p>Penghitungan terjadwal berhenti menyentuhnya, dan skor lama tetap tersimpan di riwayat.</p>
                        <p>Masih bisa dibuka lagi dari sini selama periode penggajiannya belum dikunci bendahara.</p>
                    </template>
                    <template v-else>
                        <p>Kunci {{ periode.kunci.terkunci }} skor akan dibuka. Angkanya TIDAK diubah.</p>
                        <p>Sesudah terbuka, skornya bisa bergeser lagi bila ada yang menghitung ulang.</p>
                    </template>
                </div>

                <label v-if="kunciDialog.mode === 'kunci'"
                    class="flex gap-3 items-start mt-3 p-3 rounded-xl border cursor-pointer"
                    :class="kunciDialog.hitungDulu ? 'border-indigo-500 bg-indigo-50/50' : 'border-gray-200'">
                    <input type="checkbox" v-model="kunciDialog.hitungDulu" class="mt-0.5 rounded border-gray-300" />
                    <span>
                        <span class="block text-sm font-semibold text-gray-800">Hitung ulang dulu sebelum dikunci</span>
                        <span class="block text-xs text-gray-500 mt-0.5">
                            Angkanya jadi paling mutakhir, tapi bisa berbeda dari yang terlihat sekarang
                            bila ada data yang berubah. Biarkan mati untuk membekukan apa adanya.
                        </span>
                    </span>
                </label>

                <div class="mt-4">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                        Alasan <span class="text-red-500">*</span>
                    </label>
                    <textarea v-model="kunciDialog.alasan" rows="2" maxlength="500"
                        :placeholder="kunciDialog.mode === 'kunci'
                            ? 'Mis. skor periode ini sudah final, penggajian selesai'
                            : 'Mis. ada tugas yang baru dibereskan, perlu dihitung ulang'"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    <p class="text-[11px] text-gray-400 mt-1">
                        Tercatat di riwayat skor tiap guru dan terlihat guru di PWA.
                    </p>
                </div>

                <p v-if="kunciDialog.galat" class="mt-3 text-sm text-red-600">{{ kunciDialog.galat }}</p>

                <div class="flex justify-end gap-2 mt-5">
                    <button @click="kunciDialog.buka = false" :disabled="kunciDialog.kirim"
                        class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-xl">Batal</button>
                    <button @click="kirimKunci" :disabled="kunciDialog.kirim || kunciDialog.alasan.trim().length < 5"
                        class="px-4 py-2 text-sm font-semibold rounded-xl"
                        :class="!kunciDialog.kirim && kunciDialog.alasan.trim().length >= 5
                            ? (kunciDialog.mode === 'kunci'
                                ? 'bg-[#2E3160] text-white hover:bg-[#252850]'
                                : 'bg-amber-600 text-white hover:bg-amber-700')
                            : 'bg-gray-100 text-gray-400 cursor-not-allowed'">
                        {{ kunciDialog.kirim ? 'Menyimpan…'
                            : (kunciDialog.mode === 'kunci' ? 'Kunci' : 'Buka Kunci') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-5 gap-5">

            <!-- ── Kolom Kiri: Log Pending Verifikasi ─────────────────────── -->
            <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Log Pending Verifikasi</h3>
                        <p class="text-xs text-gray-400 mt-0.5">{{ logPending.length }} log menunggu review</p>
                    </div>
                    <button v-if="logPending.length > 0" @click="verifikasiBulk"
                        class="px-3 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors">
                        ✓ Setujui Semua
                    </button>
                </div>

                <div class="divide-y divide-gray-50 max-h-[500px] overflow-y-auto">
                    <div v-for="log in logPending" :key="log.id" class="p-4 hover:bg-gray-50/50 transition-colors">
                        <!-- Header log -->
                        <div class="flex items-start gap-3 mb-2.5">
                            <img v-if="log.foto_guru" :src="log.foto_guru"
                                class="w-8 h-8 rounded-full object-cover shrink-0 mt-0.5" />
                            <div v-else
                                class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center shrink-0 mt-0.5">
                                <span class="text-xs font-bold text-indigo-700">{{ log.nama_guru?.charAt(0) }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-gray-800 truncate">{{ log.nama_guru }}</p>
                                    <p class="text-xs text-gray-400 shrink-0">{{ log.tanggal }}</p>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5">{{ log.jabatan }}</p>
                            </div>
                        </div>

                        <!-- Konten log -->
                        <div class="bg-gray-50 rounded-xl px-3 py-2.5 mb-3">
                            <p class="text-sm font-medium text-gray-800">{{ log.judul }}</p>
                            <div class="flex items-center gap-3 mt-1">
                                <span class="text-xs text-gray-400">{{ log.kategori }}</span>
                                <span v-if="log.durasi_format" class="text-xs text-indigo-600">⏱ {{ log.durasi_format
                                    }}</span>
                            </div>
                            <p v-if="log.deskripsi" class="text-xs text-gray-500 mt-1 line-clamp-2">{{ log.deskripsi }}
                            </p>
                        </div>

                        <!-- Aksi -->
                        <div class="flex gap-2">
                            <button @click="bukaModalVerifikasi(log)"
                                class="flex-1 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-semibold transition-colors">
                                ✓ Verifikasi
                            </button>
                            <button @click="bukaModalTolak(log)"
                                class="flex-1 py-2 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold transition-colors">
                                ✕ Tolak
                            </button>
                        </div>
                    </div>

                    <div v-if="!logPending.length" class="py-12 text-center">
                        <p class="text-3xl mb-2">✅</p>
                        <p class="text-sm text-gray-500">Semua log sudah diverifikasi</p>
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between gap-3">
                    <Link :href="route('admin.smart-payroll.kinerja.log-kerja')"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                        Lihat semua log kerja →
                    </Link>
                    <!-- Jejak pergerakan skor: siapa naik, siapa turun, kenapa. -->
                    <Link :href="route('admin.smart-payroll.kinerja.log-aktivitas',
                            { bulan: filterBulan, tahun: filterTahun })"
                        class="text-xs font-medium text-gray-600 hover:text-gray-900 flex items-center gap-1">
                        Log aktivitas skor →
                    </Link>
                </div>
            </div>

            <!-- ── Kolom Kanan: Rekap Kinerja Semua Guru ─────────────────── -->
            <div class="xl:col-span-3 bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800">Rekap Kinerja Bulanan</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Skor tidak mempengaruhi gaji — hanya untuk evaluasi</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/50">
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                    Pendidik</th>
                                <th
                                    class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                    Log</th>
                                <th
                                    class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">
                                    Keaktifan</th>
                                <th
                                    class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">
                                    Penugasan</th>
                                <th
                                    class="px-5 py-3 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                    Skor</th>
                                <th
                                    class="px-5 py-3 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                    Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="r in rekapList" :key="r.id" class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <img v-if="r.foto" :src="r.foto"
                                            class="w-7 h-7 rounded-full object-cover shrink-0" />
                                        <div v-else
                                            class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center shrink-0">
                                            <span class="text-xs font-bold text-indigo-700">{{ r.nama?.charAt(0)
                                                }}</span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-800">{{ r.nama }}</p>
                                            <p class="text-xs text-gray-400">{{ r.jabatan }}</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-5 py-3.5 text-center">
                                    <p class="text-sm font-semibold text-gray-700">{{ r.total_log }}</p>
                                    <p class="text-xs text-gray-400">{{ r.total_durasi_jam }}j</p>
                                </td>

                                <td class="px-5 py-3.5 hidden lg:table-cell">
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="text-sm font-semibold text-gray-700">{{ r.skor_keaktifan }}%</span>
                                        <div class="w-16 bg-gray-100 rounded-full h-1.5">
                                            <div class="h-1.5 rounded-full bg-indigo-400 transition-all"
                                                :style="{ width: r.skor_keaktifan + '%' }"></div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-5 py-3.5 hidden lg:table-cell">
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="text-sm font-semibold text-gray-700">{{ r.skor_penugasan }}%</span>
                                        <div class="w-16 bg-gray-100 rounded-full h-1.5">
                                            <div class="h-1.5 rounded-full bg-teal-400 transition-all"
                                                :style="{ width: r.skor_penugasan + '%' }"></div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-5 py-3.5 text-center">
                                    <span
                                        :class="['inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold', r.badge_skor.class]">
                                        {{ r.skor_total }}%
                                    </span>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ r.label_skor }}</p>
                                </td>

                                <td class="px-5 py-3.5 text-right">
                                    <Link :href="route('admin.smart-payroll.kinerja.detail-guru', r.guru_id)"
                                        class="p-2 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors inline-block">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </Link>
                                </td>
                            </tr>

                            <tr v-if="!rekapList.length">
                                <td colspan="6" class="py-12 text-center">
                                    <p class="text-sm text-gray-400">Belum ada rekap. Klik "Hitung Rekap" untuk
                                        kalkulasi.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── Modal Verifikasi ─────────────────────────────────────────────── -->
        <Transition name="modal">
            <div v-if="showModalVerifikasi" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showModalVerifikasi = false" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <h3 class="font-semibold text-gray-900 mb-1">Verifikasi Log Kerja</h3>
                    <p class="text-sm text-gray-500 mb-4">{{ aksiTarget?.judul }}</p>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Catatan (opsional)</label>
                    <textarea v-model="catatanAksi" rows="2" placeholder="Catatan verifikasi..."
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 resize-none mb-4">
                    </textarea>
                    <div class="flex gap-3">
                        <button @click="showModalVerifikasi = false"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600">Batal</button>
                        <button @click="doVerifikasi" :disabled="loading"
                            class="flex-1 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold disabled:opacity-60">
                            {{ loading ? 'Menyimpan...' : 'Verifikasi' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- ── Modal Tolak ──────────────────────────────────────────────────── -->
        <Transition name="modal">
            <div v-if="showModalTolak" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showModalTolak = false" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <h3 class="font-semibold text-gray-900 mb-1">Tolak Log Kerja</h3>
                    <p class="text-sm text-gray-500 mb-4">{{ aksiTarget?.judul }}</p>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Alasan Penolakan <span class="text-red-500">*</span>
                    </label>
                    <textarea v-model="catatanAksi" rows="3" placeholder="Tulis alasan penolakan..."
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100 resize-none mb-4">
                    </textarea>
                    <div class="flex gap-3">
                        <button @click="showModalTolak = false"
                            class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600">Batal</button>
                        <button @click="doTolak" :disabled="!catatanAksi || loading"
                            class="flex-1 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-semibold disabled:opacity-60">
                            {{ loading ? 'Menyimpan...' : 'Tolak' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    logPending: { type: Array, default: () => [] },
    rekapList: { type: Array, default: () => [] },
    ringkasan: { type: Object, default: () => ({}) },
    periode: { type: Object, default: null },
    periodeOpsi: { type: Array, default: () => [] },
    bulan: { type: Number, default: () => new Date().getMonth() + 1 },
    tahun: { type: Number, default: () => new Date().getFullYear() },
    filters: { type: Object, default: () => ({}) },
})

// ── Filter ────────────────────────────────────────────────────────────────────
const filterBulan = ref(props.bulan)
const filterTahun = ref(props.tahun)

const tahunList = computed(() => {
    const y = new Date().getFullYear(); return [y, y - 1, y - 2]
})

const bulanList = [
    { value: 1, label: 'Januari' }, { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' }, { value: 4, label: 'April' },
    { value: 5, label: 'Mei' }, { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' }, { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' }, { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' }, { value: 12, label: 'Desember' },
]

function doFilter() {
    router.get(route('admin.smart-payroll.kinerja.index'), {
        bulan: filterBulan.value,
        tahun: filterTahun.value,
    }, { preserveState: true, replace: true })
}

// ── Hitung rekap ──────────────────────────────────────────────────────────────
const menghitung = ref(false)
function hitungRekap() {
    menghitung.value = true
    router.post(route('admin.smart-payroll.kinerja.hitung-rekap'), {
        bulan: filterBulan.value,
        tahun: filterTahun.value,
    }, {
        onFinish: () => menghitung.value = false,
    })
}

// ── Kunci skor periode (superadmin) ──────────────────────────────────────────
// Dulu hanya bisa lewat perintah di server, sehingga kewenangan superadmin
// bergantung pada orang lain yang punya akses konsol.
const bolehMengunci = computed(() => usePage().props.auth?.user?.role === 'super_admin')
const semuaTerkunci = computed(() =>
    !!props.periode?.kunci && props.periode.kunci.total > 0
    && props.periode.kunci.terkunci === props.periode.kunci.total)

const kunciDialog = reactive({
    buka: false, mode: 'kunci', alasan: '', hitungDulu: false, galat: '', kirim: false,
})

function bukaDialogKunci(mode) {
    Object.assign(kunciDialog,
        { buka: true, mode, alasan: '', hitungDulu: false, galat: '', kirim: false })
}

function kirimKunci() {
    if (kunciDialog.alasan.trim().length < 5) return
    kunciDialog.kirim = true; kunciDialog.galat = ''
    const rute = kunciDialog.mode === 'kunci'
        ? 'admin.smart-payroll.kinerja.kunci-periode'
        : 'admin.smart-payroll.kinerja.buka-kunci-periode'
    const muatan = {
        bulan: filterBulan.value,
        tahun: filterTahun.value,
        alasan: kunciDialog.alasan.trim(),
    }
    if (kunciDialog.mode === 'kunci') muatan.hitung_ulang_dulu = kunciDialog.hitungDulu

    router.post(route(rute), muatan, {
        preserveScroll: true,
        onSuccess: () => { kunciDialog.buka = false },
        // Inertia membalas 303 + errors; tanpa onError galatnya hilang diam-diam.
        onError: (e) => { kunciDialog.galat = Object.values(e)[0] ?? 'Gagal memproses.' },
        onFinish: () => { kunciDialog.kirim = false },
    })
}

const mereset = ref(false)
function resetSemua() {
    // Reset massal menghapus semua override satu periode — alasannya wajib dan
    // ikut tersimpan di riwayat tiap guru. Skor lama tidak hilang.
    const alasan = window.prompt('Reset kinerja SEMUA guru periode ini.\nAlasan (wajib, min. 5 karakter):')
    if (alasan === null) return
    if (alasan.trim().length < 5) { window.alert('Alasan reset wajib diisi minimal 5 karakter.'); return }
    mereset.value = true
    router.post(route('admin.smart-payroll.kinerja.reset-semua'), {
        bulan: filterBulan.value,
        tahun: filterTahun.value,
        alasan: alasan.trim(),
    }, {
        onFinish: () => mereset.value = false,
    })
}

// ── Verifikasi bulk ───────────────────────────────────────────────────────────
function verifikasiBulk() {
    router.post(route('admin.smart-payroll.kinerja.verifikasi-bulk'), {
        bulan: filterBulan.value,
        tahun: filterTahun.value,
    })
}

// ── Modal verifikasi / tolak ──────────────────────────────────────────────────
const showModalVerifikasi = ref(false)
const showModalTolak = ref(false)
const aksiTarget = ref(null)
const catatanAksi = ref('')
const loading = ref(false)

function bukaModalVerifikasi(log) {
    aksiTarget.value = log
    catatanAksi.value = ''
    showModalVerifikasi.value = true
}

function bukaModalTolak(log) {
    aksiTarget.value = log
    catatanAksi.value = ''
    showModalTolak.value = true
}

function doVerifikasi() {
    loading.value = true
    router.post(route('admin.smart-payroll.kinerja.log.verifikasi', aksiTarget.value.id), {
        catatan: catatanAksi.value
    }, {
        onSuccess: () => showModalVerifikasi.value = false,
        onFinish: () => loading.value = false,
    })
}

function doTolak() {
    if (!catatanAksi.value) return
    loading.value = true
    router.post(route('admin.smart-payroll.kinerja.log.tolak', aksiTarget.value.id), {
        catatan: catatanAksi.value
    }, {
        onSuccess: () => showModalTolak.value = false,
        onFinish: () => loading.value = false,
    })
}
</script>

<style scoped>
.modal-enter-active {
    transition: all 0.2s ease;
}

.modal-leave-active {
    transition: all 0.15s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
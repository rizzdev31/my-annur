<template>
    <AdminLayout title="Setting Penilaian Kinerja" subtitle="Smart Payroll">

        <Head title="Setting Kinerja" />

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Setting Penilaian Kinerja</h2>
                <p class="text-sm text-gray-400 mt-0.5">Konfigurasi bobot & nilai per status — dinamis dan fleksibel</p>
            </div>
            <button @click="openTambah"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl">
                + Tambah Setting
            </button>
        </div>

        <!-- Formula visual -->
        <div class="mb-5 p-5 bg-gradient-to-br from-slate-50 to-indigo-50 border border-indigo-100 rounded-2xl">
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Formula Perhitungan</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="bg-white rounded-xl p-4 border border-blue-100">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xl">📋</span>
                        <p class="text-sm font-bold text-blue-700">Absensi (50%)</p>
                    </div>
                    <div class="text-xs text-gray-500 space-y-1">
                        <p>Setiap status punya <strong>nilai sendiri</strong>:</p>
                        <p>Hadir=100, Izin=70, Sakit=80, Alfa=0 ...</p>
                        <p class="text-blue-600 font-medium mt-1">Sub: Harian (70%) + Mengajar (30%)</p>
                    </div>
                </div>
                <div class="bg-white rounded-xl p-4 border border-violet-100">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xl">✅</span>
                        <p class="text-sm font-bold text-violet-700">Tugas (30%)</p>
                    </div>
                    <div class="text-xs text-gray-500 space-y-1">
                        <p>Penugasan: selesai & disetujui / total</p>
                        <p>Jabatan: realisasi disetujui / target wajib</p>
                        <p class="text-violet-600 font-medium mt-1">Sub: Penugasan (60%) + Jabatan (40%)</p>
                    </div>
                </div>
                <div class="bg-white rounded-xl p-4 border border-teal-100">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xl">📝</span>
                        <p class="text-sm font-bold text-teal-700">Administrasi (20%)</p>
                    </div>
                    <div class="text-xs text-gray-500 space-y-1">
                        <p>Laporan: sesi absen mengajar + ada materi</p>
                        <p>Log: log harian yang di-submit</p>
                        <p class="text-teal-600 font-medium mt-1">Sub: Laporan (60%) + Log (40%)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daftar setting -->
        <div class="space-y-4">
            <div v-for="s in settings" :key="s.id" :class="['bg-white rounded-2xl border overflow-hidden',
                s.is_default ? 'border-indigo-300 shadow-sm shadow-indigo-100' : 'border-gray-200',
                !s.is_aktif ? 'opacity-60' : '']">

                <!-- Header card -->
                <div class="px-5 py-4 flex items-center justify-between border-b border-gray-50">
                    <div class="flex items-center gap-3">
                        <span v-if="s.is_default"
                            class="px-2.5 py-1 rounded-lg bg-indigo-600 text-white text-xs font-bold">DEFAULT</span>
                        <span v-if="!s.is_bobot_valid"
                            class="px-2.5 py-1 rounded-lg bg-red-50 text-red-600 text-xs font-bold">⚠ Bobot Tidak
                            Valid</span>
                        <p class="text-base font-semibold text-gray-900">{{ s.nama }}</p>
                        <p v-if="s.keterangan" class="text-xs text-gray-400 hidden lg:block truncate max-w-xs">{{
                            s.keterangan }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button v-if="!s.is_default" @click="setDefault(s)"
                            class="px-3 py-1.5 rounded-lg border border-indigo-200 text-indigo-600 text-xs font-medium hover:bg-indigo-50">Set
                            Default</button>
                        <button @click="openEdit(s)"
                            class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                        <button v-if="!s.is_default" @click="hapus(s)"
                            class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Progress bar bobot -->
                <div class="flex h-6 overflow-hidden">
                    <div :style="{ width: s.bobot_absensi + '%' }"
                        class="bg-blue-500 flex items-center justify-center text-white text-xs font-bold">
                        {{ s.bobot_absensi }}%
                    </div>
                    <div :style="{ width: s.bobot_tugas + '%' }"
                        class="bg-violet-500 flex items-center justify-center text-white text-xs font-bold">
                        {{ s.bobot_tugas }}%
                    </div>
                    <div :style="{ width: s.bobot_administrasi + '%' }"
                        class="bg-teal-500 flex items-center justify-center text-white text-xs font-bold">
                        {{ s.bobot_administrasi }}%
                    </div>
                    <div v-if="(s.bobot_piket ?? 0) > 0" :style="{ width: s.bobot_piket + '%' }"
                        class="bg-amber-500 flex items-center justify-center text-white text-xs font-bold">
                        {{ s.bobot_piket }}%
                    </div>
                </div>

                <!-- Detail komponen -->
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">

                    <!-- ABSENSI -->
                    <div class="space-y-3">
                        <p class="text-xs font-bold text-blue-700 uppercase tracking-wider">
                            📋 Absensi — {{ s.bobot_absensi }}%
                        </p>
                        <!-- Nilai per status -->
                        <div class="space-y-1.5">
                            <p class="text-xs font-semibold text-gray-600">Nilai per Status Kehadiran:</p>
                            <div v-for="st in statusAbsensi" :key="st.key" class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">{{ st.label }}</span>
                                <div class="flex items-center gap-2">
                                    <div class="w-20 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                        <div :style="{ width: s[st.field] + '%' }"
                                            :class="['h-full rounded-full', st.color]" />
                                    </div>
                                    <span :class="['text-xs font-bold w-10 text-right', st.textColor]">
                                        {{ s[st.field] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <!-- Sub-bobot -->
                        <div class="pt-2 border-t border-gray-100 space-y-1 text-xs text-gray-500">
                            <div class="flex justify-between">
                                <span>Sub: Harian</span><span class="font-semibold">{{ s.bobot_absensi_harian }}%</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Sub: Mengajar</span><span class="font-semibold">{{ s.bobot_absensi_mengajar
                                    }}%</span>
                            </div>
                            <div v-if="s.hitung_penalty_terlambat" class="text-blue-600 pt-1">
                                Penalty: -{{ s.penalty_per_terlambat }}%/kejadian (maks {{ s.max_penalty_terlambat }}%)
                            </div>
                        </div>
                    </div>

                    <!-- TUGAS -->
                    <div class="space-y-3">
                        <p class="text-xs font-bold text-violet-700 uppercase tracking-wider">
                            ✅ Tugas — {{ s.bobot_tugas }}%
                        </p>
                        <div class="space-y-1.5 text-xs text-gray-500">
                            <div class="flex justify-between">
                                <span>Sub: Penugasan Tambahan</span><span class="font-semibold">{{
                                    s.bobot_tugas_tambahan }}%</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Sub: Tugas Jabatan</span><span class="font-semibold">{{ s.bobot_tugas_jabatan
                                    }}%</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-gray-100">
                                <span>Jika tidak ada tugas</span>
                                <span :class="['font-semibold',
                                    s.jika_tidak_ada_tugas === 'sempurna' ? 'text-emerald-600' :
                                        s.jika_tidak_ada_tugas === 'nol' ? 'text-red-500' : 'text-gray-400']">
                                    {{ { sempurna: 'Skor 100', nol: 'Skor 0', skip: 'Skip' }[s.jika_tidak_ada_tugas] }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- ADMINISTRASI -->
                    <div class="space-y-3">
                        <p class="text-xs font-bold text-teal-700 uppercase tracking-wider">
                            📝 Administrasi — {{ s.bobot_administrasi }}%
                        </p>
                        <div class="space-y-1.5 text-xs text-gray-500">
                            <div class="flex justify-between">
                                <span>Sub: Laporan Mengajar</span><span class="font-semibold">{{
                                    s.bobot_laporan_mengajar }}%</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Sub: Log Kerja</span><span class="font-semibold">{{ s.bobot_log_kerja }}%</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-gray-100">
                                <span>Target log/hari</span><span class="font-semibold">{{ s.target_log_per_hari
                                    }}×</span>
                            </div>
                        </div>
                    </div>

                    <!-- PIKET -->
                    <div class="space-y-3">
                        <p class="text-xs font-bold text-amber-700 uppercase tracking-wider">
                            🧭 Piket — {{ s.bobot_piket ?? 0 }}%
                        </p>
                        <div class="space-y-1.5 text-xs text-gray-500">
                            <p>Skor mulai <b>100</b>, ± dari penilaian guru piket (apresiasi/catatan).</p>
                            <div class="flex justify-between pt-2 border-t border-gray-100">
                                <span>Sumber nilai</span><span class="font-semibold">Penilaian Guru Piket</span>
                            </div>
                            <div v-if="(s.bobot_piket ?? 0) === 0" class="text-amber-600 pt-1">Netral (belum aktif)</div>
                        </div>
                    </div>
                </div>

                <!-- Grade bar -->
                <div class="px-5 pb-4 flex items-center gap-2 flex-wrap">
                    <p class="text-xs text-gray-400">Grade:</p>
                    <span class="text-xs px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-bold">A ≥ {{
                        s.grade_a }}</span>
                    <span class="text-xs px-2.5 py-1 rounded-lg bg-teal-50 text-teal-700 font-bold">B ≥ {{ s.grade_b
                        }}</span>
                    <span class="text-xs px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 font-bold">C ≥ {{ s.grade_c
                        }}</span>
                    <span class="text-xs px-2.5 py-1 rounded-lg bg-orange-50 text-orange-700 font-bold">D ≥ {{ s.grade_d
                        }}</span>
                    <span class="text-xs px-2.5 py-1 rounded-lg bg-red-50 text-red-700 font-bold">E &lt; {{ s.grade_d
                        }}</span>
                </div>
            </div>

            <div v-if="!settings.length" class="bg-white rounded-2xl border border-gray-200 py-16 text-center">
                <p class="text-3xl mb-3">📊</p>
                <p class="text-sm font-semibold text-gray-700">Belum ada setting kinerja</p>
                <button @click="openTambah"
                    class="mt-4 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl">Buat
                    Setting</button>
            </div>
        </div>


        <!-- ══ MODAL FORM ══ -->
        <div v-if="showForm" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col" @click.stop>

                <!-- Header sticky -->
                <div class="px-6 py-4 border-b border-gray-100 shrink-0">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-900">
                            {{ editTarget ? 'Edit' : 'Tambah' }} Setting Kinerja
                        </h3>
                        <!-- Indikator total bobot real-time -->
                        <div class="flex items-center gap-2">
                            <div class="flex overflow-hidden rounded-lg h-3 w-32">
                                <div :style="{ width: Math.min(form.bobot_absensi, 100) + '%' }"
                                    class="bg-blue-500 h-full transition-all" />
                                <div :style="{ width: Math.min(form.bobot_tugas, 100 - form.bobot_absensi) + '%' }"
                                    class="bg-violet-500 h-full transition-all" />
                                <div :style="{ width: Math.min(form.bobot_administrasi, 100 - form.bobot_absensi - form.bobot_tugas) + '%' }"
                                    class="bg-teal-500 h-full transition-all" />
                                <div :style="{ width: Math.min(form.bobot_piket || 0, Math.max(0, 100 - form.bobot_absensi - form.bobot_tugas - form.bobot_administrasi)) + '%' }"
                                    class="bg-amber-500 h-full transition-all" />
                                <div class="bg-gray-200 h-full flex-1 transition-all" />
                            </div>
                            <span
                                :class="['text-xs font-bold tabular-nums', Math.abs(totalBobot - 100) < 0.01 ? 'text-emerald-600' : 'text-red-500']">
                                {{ totalBobot }}%
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Body scroll -->
                <div class="overflow-y-auto flex-1 px-6 py-5 space-y-5">

                    <!-- Nama -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama <span
                                class="text-red-500">*</span></label>
                        <input v-model="form.nama" type="text" placeholder="cth: Setting Kinerja Standar"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white" />
                    </div>

                    <!-- ── ABSENSI ── -->
                    <div class="bg-blue-50/40 border border-blue-100 rounded-2xl p-4 space-y-4">
                        <p class="text-sm font-bold text-blue-800">📋 Komponen Absensi</p>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1.5">Bobot Total (%)</label>
                                <input v-model.number="form.bobot_absensi" type="number" min="0" max="100" step="5"
                                    class="w-full px-3 py-2 rounded-xl border border-blue-200 text-sm focus:outline-none bg-white font-bold text-blue-700" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1.5">Sub: Harian (%)</label>
                                <input v-model.number="form.bobot_absensi_harian" type="number" min="0" max="100"
                                    step="5"
                                    class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1.5">Sub: Mengajar (%)</label>
                                <input v-model.number="form.bobot_absensi_mengajar" type="number" min="0" max="100"
                                    step="5"
                                    class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white" />
                            </div>
                        </div>

                        <!-- Nilai per status — yang bisa diatur -->
                        <div>
                            <p class="text-xs font-semibold text-gray-700 mb-2">
                                Nilai per Status Kehadiran
                                <span class="font-normal text-gray-400 ml-1">(hadir = 100 sebagai basis, atur
                                    sisanya)</span>
                            </p>
                            <div class="grid grid-cols-2 gap-3">
                                <div v-for="st in statusAbsensi" :key="st.key"
                                    class="flex items-center justify-between px-3 py-2.5 bg-white rounded-xl border border-gray-200">
                                    <div class="flex items-center gap-2">
                                        <div :class="['w-2.5 h-2.5 rounded-full shrink-0', st.dot]" />
                                        <span class="text-sm text-gray-700">{{ st.label }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input v-model.number="form[st.field]" type="number" min="0" max="100" step="5"
                                            :class="['w-16 px-2 py-1 rounded-lg border text-sm text-center focus:outline-none', st.inputBorder]" />
                                        <span class="text-xs text-gray-400">%</span>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-gray-400 mt-2">
                                💡 <strong>Libur</strong> tidak dihitung — dikecualikan dari hari kerja efektif.
                                <strong>Terlambat</strong> dihitung dengan nilai di atas, bukan dengan penalty (penalty
                                opsional di bawah).
                            </p>
                        </div>

                        <!-- Penalty opsional -->
                        <div class="border-t border-blue-100 pt-3">
                            <div class="flex items-center justify-between cursor-pointer select-none mb-3"
                                @click="form.hitung_penalty_terlambat = !form.hitung_penalty_terlambat">
                                <div>
                                    <p class="text-xs font-semibold text-gray-700">Penalty Tambahan Keterlambatan
                                        (Opsional)</p>
                                    <p class="text-xs text-gray-400">Aktifkan jika ingin hukuman EKSTRA di atas
                                        nilai_terlambat</p>
                                </div>
                                <div :class="['relative rounded-full transition-colors shrink-0', form.hitung_penalty_terlambat ? 'bg-blue-600' : 'bg-gray-300']"
                                    style="height:20px;width:36px">
                                    <span :class="['absolute top-0.5 w-3.5 h-3.5 bg-white rounded-full shadow transition-transform',
                                        form.hitung_penalty_terlambat ? 'translate-x-4' : 'translate-x-0.5']" />
                                </div>
                            </div>
                            <div v-if="form.hitung_penalty_terlambat" class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1.5">Toleransi (menit)</label>
                                    <input v-model.number="form.toleransi_terlambat_menit" type="number" min="0"
                                        max="60"
                                        class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1.5">Potongan/kejadian (%)</label>
                                    <input v-model.number="form.penalty_per_terlambat" type="number" min="0" max="50"
                                        step="0.5"
                                        class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1.5">Maks penalty (%)</label>
                                    <input v-model.number="form.max_penalty_terlambat" type="number" min="0" max="100"
                                        step="5"
                                        class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── TUGAS ── -->
                    <div class="bg-violet-50/40 border border-violet-100 rounded-2xl p-4 space-y-4">
                        <p class="text-sm font-bold text-violet-800">✅ Komponen Tugas</p>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1.5">Bobot Total (%)</label>
                                <input v-model.number="form.bobot_tugas" type="number" min="0" max="100" step="5"
                                    class="w-full px-3 py-2 rounded-xl border border-violet-200 text-sm focus:outline-none bg-white font-bold text-violet-700" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1.5">Sub: Penugasan (%)</label>
                                <input v-model.number="form.bobot_tugas_tambahan" type="number" min="0" max="100"
                                    step="5"
                                    class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1.5">Sub: Jabatan (%)</label>
                                <input v-model.number="form.bobot_tugas_jabatan" type="number" min="0" max="100"
                                    step="5"
                                    class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-2">Jika Tidak Ada Tugas</label>
                            <div class="grid grid-cols-3 gap-2">
                                <button v-for="opt in tujuanOpts" :key="opt.v" type="button"
                                    @click="form.jika_tidak_ada_tugas = opt.v"
                                    :class="['py-2.5 px-3 rounded-xl border-2 text-left transition-all',
                                        form.jika_tidak_ada_tugas === opt.v ? 'border-violet-500 bg-violet-50' : 'border-gray-200 hover:border-gray-300']">
                                    <p
                                        :class="['text-xs font-bold', form.jika_tidak_ada_tugas === opt.v ? 'text-violet-700' : 'text-gray-700']">
                                        {{ opt.l }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ opt.d }}</p>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ── ADMINISTRASI ── -->
                    <div class="bg-teal-50/40 border border-teal-100 rounded-2xl p-4 space-y-4">
                        <p class="text-sm font-bold text-teal-800">📝 Komponen Administrasi</p>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1.5">Bobot Total (%)</label>
                                <input v-model.number="form.bobot_administrasi" type="number" min="0" max="100" step="5"
                                    class="w-full px-3 py-2 rounded-xl border border-teal-200 text-sm focus:outline-none bg-white font-bold text-teal-700" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1.5">Sub: Laporan Mengajar
                                    (%)</label>
                                <input v-model.number="form.bobot_laporan_mengajar" type="number" min="0" max="100"
                                    step="5"
                                    class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white" />
                                <p class="text-xs text-gray-400 mt-1">Sesi absen mengajar + ada materi</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1.5">Sub: Log Kerja (%)</label>
                                <input v-model.number="form.bobot_log_kerja" type="number" min="0" max="100" step="5"
                                    class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Target Log Kerja per Hari
                                Kerja</label>
                            <div class="flex items-center gap-2">
                                <input v-model.number="form.target_log_per_hari" type="number" min="0.5" max="5"
                                    step="0.5"
                                    class="w-24 px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white" />
                                <span class="text-xs text-gray-400">log per hari</span>
                            </div>
                        </div>
                    </div>

                    <!-- ── KOMPONEN PIKET ── -->
                    <div class="bg-amber-50/40 border border-amber-100 rounded-2xl p-4 space-y-2">
                        <p class="text-sm font-bold text-amber-800">🧭 Komponen Piket</p>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1.5">Bobot Total (%)</label>
                                <input v-model.number="form.bobot_piket" type="number" min="0" max="100" step="5"
                                    class="w-full px-3 py-2 rounded-xl border border-amber-200 text-sm focus:outline-none bg-white font-bold text-amber-700" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1.5">Skor Min. Piket (floor)</label>
                                <input v-model.number="form.skor_min_piket" type="number" min="0" max="100" step="5"
                                    class="w-full px-3 py-2 rounded-xl border border-amber-200 text-sm focus:outline-none bg-white font-bold text-amber-700" />
                            </div>
                            <div class="flex items-end">
                                <p class="text-xs text-gray-400">Sub-skor piket = 100 − Σcatatan + Σapresiasi, dibatasi [floor..100]. Poin rubrik skala 1–10. Floor melindungi guru agar catatan tak menjatuhkan skor ke 0. Isi bobot 0 = netral.</p>
                            </div>
                        </div>
                    </div>

                    <!-- ── GRADE ── -->
                    <div class="bg-gray-50 rounded-2xl p-4">
                        <p class="text-sm font-bold text-gray-700 mb-3">🎓 Ambang Batas Grade</p>
                        <div class="grid grid-cols-4 gap-3">
                            <div v-for="g in gradeFields" :key="g.key">
                                <label :class="['block text-xs font-bold mb-1.5', g.color]">Grade {{ g.label }}
                                    (≥)</label>
                                <input v-model.number="form[g.key]" type="number" min="0" max="100"
                                    class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white text-center" />
                            </div>
                        </div>
                    </div>

                    <!-- Keterangan & toggle default -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Keterangan</label>
                        <textarea v-model="form.keterangan" rows="2"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none bg-white resize-none" />
                    </div>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer select-none"
                        @click="form.is_default = !form.is_default">
                        <div>
                            <p class="text-sm font-medium text-gray-800">Jadikan Default</p>
                            <p class="text-xs text-gray-400 mt-0.5">Dipakai otomatis saat hitung rekap</p>
                        </div>
                        <div :class="['relative rounded-full transition-colors shrink-0', form.is_default ? 'bg-indigo-600' : 'bg-gray-300']"
                            style="height:22px;width:40px">
                            <span :class="['absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform',
                                form.is_default ? 'translate-x-5' : 'translate-x-0.5']" />
                        </div>
                    </div>
                </div>

                <!-- Footer sticky -->
                <div class="flex gap-3 px-6 py-4 border-t border-gray-50 shrink-0">
                    <button @click="closeForm"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Batal
                    </button>
                    <button @click="submitForm" :disabled="loading || Math.abs(totalBobot - 100) > 0.01"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60 transition-colors">
                        {{ loading ? 'Menyimpan...' : (editTarget ? 'Simpan' : 'Tambah Setting') }}
                    </button>
                </div>
            </div>
        </div>

        <AppConfirm ref="confirm" />
    </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppConfirm from '@/Components/AppConfirm.vue'

const confirm = ref(null)

const props = defineProps({
    settings: { type: Array, default: () => [] },
})

const showForm = ref(false)
const loading = ref(false)
const editTarget = ref(null)

const statusAbsensi = [
    { key: 'hadir', field: 'nilai_hadir', label: 'Hadir', dot: 'bg-emerald-500', textColor: 'text-emerald-700', color: 'bg-emerald-500', inputBorder: 'border-emerald-200' },
    { key: 'terlambat', field: 'nilai_terlambat', label: 'Terlambat', dot: 'bg-amber-500', textColor: 'text-amber-700', color: 'bg-amber-500', inputBorder: 'border-amber-200' },
    { key: 'izin', field: 'nilai_izin', label: 'Izin / Izin Sakit', dot: 'bg-blue-500', textColor: 'text-blue-700', color: 'bg-blue-500', inputBorder: 'border-blue-200' },
    { key: 'sakit', field: 'nilai_sakit', label: 'Sakit', dot: 'bg-indigo-500', textColor: 'text-indigo-700', color: 'bg-indigo-500', inputBorder: 'border-indigo-200' },
    { key: 'dinas_luar', field: 'nilai_dinas_luar', label: 'Dinas Luar', dot: 'bg-violet-500', textColor: 'text-violet-700', color: 'bg-violet-500', inputBorder: 'border-violet-200' },
    { key: 'alfa', field: 'nilai_alfa', label: 'Alfa', dot: 'bg-red-500', textColor: 'text-red-700', color: 'bg-red-500', inputBorder: 'border-red-200' },
]

const tujuanOpts = [
    { v: 'sempurna', l: 'Skor 100', d: 'Dianggap sempurna' },
    { v: 'nol', l: 'Skor 0', d: 'Mengurangi skor total' },
    { v: 'skip', l: 'Skip', d: 'Tidak dihitung' },
]

const gradeFields = [
    { key: 'grade_a', label: 'A — Sangat Baik', color: 'text-emerald-600' },
    { key: 'grade_b', label: 'B — Baik', color: 'text-teal-600' },
    { key: 'grade_c', label: 'C — Cukup', color: 'text-amber-600' },
    { key: 'grade_d', label: 'D — Perlu Perhatian', color: 'text-orange-600' },
]

const defaultForm = () => ({
    nama: '', is_aktif: true, is_default: false,
    bobot_absensi: 50, bobot_absensi_harian: 70, bobot_absensi_mengajar: 30,
    nilai_hadir: 100, nilai_terlambat: 75, nilai_izin: 70,
    nilai_sakit: 80, nilai_dinas_luar: 100, nilai_alfa: 0,
    hitung_penalty_terlambat: false, toleransi_terlambat_menit: 0,
    penalty_per_terlambat: 5, max_penalty_terlambat: 20,
    bobot_tugas: 30, bobot_tugas_tambahan: 60, bobot_tugas_jabatan: 40,
    jika_tidak_ada_tugas: 'sempurna',
    bobot_administrasi: 20, bobot_laporan_mengajar: 60, bobot_log_kerja: 40,
    target_log_per_hari: 1,
    bobot_piket: 0, skor_min_piket: 50,
    grade_a: 90, grade_b: 75, grade_c: 60, grade_d: 40,
    keterangan: '',
})

const form = reactive(defaultForm())

// Piket = penyesuaian (+/−), TIDAK dijadikan bobot → total 3 komponen inti saja = 100.
const totalBobot = computed(() =>
    Number((form.bobot_absensi + form.bobot_tugas + form.bobot_administrasi).toFixed(2))
)

function openTambah() {
    editTarget.value = null
    Object.assign(form, defaultForm())
    showForm.value = true
}

function openEdit(s) {
    editTarget.value = s
    Object.assign(form, { ...s })
    showForm.value = true
}

function closeForm() { showForm.value = false; editTarget.value = null }

function submitForm() {
    if (Math.abs(totalBobot.value - 100) > 0.01) return
    loading.value = true
    const action = editTarget.value
        ? () => router.put(route('admin.smart-payroll.setting-kinerja.update', editTarget.value.id), { ...form })
        : () => router.post(route('admin.smart-payroll.setting-kinerja.store'), { ...form })
    action()
    router.on('finish', () => { loading.value = false; closeForm() })
}

function setDefault(s) {
    router.post(route('admin.smart-payroll.setting-kinerja.set-default', s.id), {}, { preserveScroll: true })
}

function hapus(s) {
    confirm.value.ask(
        { title: 'Hapus Setting Kinerja?', message: `"${s.nama}" akan dihapus permanen.`,
          variant: 'danger', confirmLabel: 'Ya, Hapus', irreversible: true },
        (done) => router.delete(route('admin.smart-payroll.setting-kinerja.destroy', s.id),
            { preserveScroll: true, onFinish: done }),
    )
}
</script>
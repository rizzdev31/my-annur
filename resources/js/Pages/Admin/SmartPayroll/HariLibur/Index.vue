<template>
    <AdminLayout title="Hari Libur" subtitle="Smart Payroll">

        <Head title="Hari Libur" />

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Hari Libur</h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    Kelola libur nasional, pesantren, dan darurat — mempengaruhi absensi & penggajian
                </p>
            </div>
            <div class="flex items-center gap-2">
                <input v-model.number="filterTahun" type="number" @change="applyFilter"
                    class="w-24 px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-indigo-500" />
                <button @click="openDarurat"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    🚨 Libur Darurat
                </button>
            </div>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-3 lg:grid-cols-6 gap-2 mb-5">
            <div v-for="s in summaryCards" :key="s.label" :class="[`rounded-xl border px-3 py-2.5 text-center`, s.bg]">
                <p :class="[`text-xl font-bold`, s.color]">{{ s.value }}</p>
                <p class="text-xs text-gray-400 mt-0.5 leading-tight">{{ s.label }}</p>
            </div>
        </div>

        <!-- Info -->
        <div class="mb-5 p-4 bg-blue-50 border border-blue-100 rounded-2xl">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs text-blue-700">
                <div class="flex items-start gap-2">
                    <span class="text-base shrink-0">🇮🇩</span>
                    <div>
                        <p class="font-semibold">Nasional</p>
                        <p class="mt-0.5 text-blue-500">Toggle aktif/nonaktif jika pesantren tetap masuk di hari itu</p>
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <span class="text-base shrink-0">🕌</span>
                    <div>
                        <p class="font-semibold">Pesantren</p>
                        <p class="mt-0.5 text-blue-500">Kebijakan internal: haul, event, kegiatan pesantren</p>
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <span class="text-base shrink-0">🚨</span>
                    <div>
                        <p class="font-semibold">Darurat</p>
                        <p class="mt-0.5 text-blue-500">Otomatis ubah absensi ke "libur" + bisa di-rollback</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
                :class="['px-4 py-2 rounded-xl text-sm font-medium transition-colors border',
                    activeTab === t.key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300']">
                {{ t.icon }} {{ t.label }}
                <span class="ml-1 text-xs opacity-70">({{ hitungTab(t.key) }})</span>
            </button>

            <template v-if="activeTab !== 'darurat'">
                <button @click="openTambah"
                    class="ml-auto inline-flex items-center gap-1.5 px-4 py-2 border border-indigo-200 text-indigo-700 bg-indigo-50 hover:bg-indigo-100 text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah {{ activeTab === 'nasional' ? `Nasional` : `Pesantren` }}
                </button>
                <button v-if="activeTab === 'nasional'" @click="openImport"
                    class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-medium rounded-xl transition-colors">
                    📥 Import Preset
                </button>
            </template>
            <p v-else class="ml-auto text-xs text-gray-400">
                Gunakan tombol <span class="font-semibold text-red-600">🚨 Libur Darurat</span> di kanan atas
            </p>
        </div>

        <!-- Tabel -->
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase">Nama & Tanggal
                        </th>
                        <th
                            class="px-5 py-3.5 text-center text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">
                            Durasi</th>
                        <th
                            class="px-5 py-3.5 text-center text-xs font-semibold text-gray-400 uppercase hidden lg:table-cell">
                            Pengaruh Gaji</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold text-gray-400 uppercase">Status</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="h in hariLiburFiltered" :key="h.id"
                        :class="[`hover:bg-gray-50/40 transition-colors`, (!h.is_aktif || h.is_dibatalkan) ? `opacity-50` : ``]">
                        <td class="px-5 py-4">
                            <div class="flex items-start gap-3">
                                <div
                                    :class="['w-10 h-10 rounded-xl flex items-center justify-center text-xl shrink-0',
                                        h.sumber === 'nasional' ? 'bg-blue-50' : h.sumber === 'darurat' ? 'bg-red-50' : 'bg-violet-50']">
                                    {{ sourIcon[h.sumber] }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-800">{{ h.nama }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        {{ h.tanggal }}<template v-if="h.tanggal_selesai"> – {{ h.tanggal_selesai
                                            }}</template>
                                    </p>
                                    <p v-if="h.keterangan"
                                        class="text-xs text-gray-400 italic mt-0.5 truncate max-w-xs">{{
                                        h.keterangan }}</p>
                                    <div v-if="h.is_dibatalkan"
                                        class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 bg-red-50 border border-red-100 rounded-lg">
                                        <span class="text-xs text-red-600">Dibatalkan — {{ h.alasan_pembatalan }}</span>
                                    </div>
                                    <div v-if="h.is_darurat && h.absensi_terdampak > 0 && !h.is_dibatalkan"
                                        class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 border border-amber-100 rounded-lg">
                                        <span class="text-xs text-amber-600">{{ h.absensi_terdampak }} absensi
                                            diperbarui
                                            otomatis</span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-center hidden md:table-cell">
                            <span class="text-sm font-semibold text-gray-700">{{ h.durasi_hari }}</span>
                            <span class="text-xs text-gray-400 ml-1">hari</span>
                        </td>
                        <td class="px-5 py-4 text-center hidden lg:table-cell">
                            <span :class="['text-xs font-medium px-2.5 py-1 rounded-lg',
                                h.pengaruh_gaji ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-400']">
                                {{ h.pengaruh_gaji ? 'Ya' : 'Tidak' }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <button v-if="!h.is_darurat && !h.is_dibatalkan" @click="toggleAktif(h)"
                                :class="[`relative w-9 h-5 rounded-full transition-colors`, h.is_aktif ? `bg-indigo-500` : `bg-gray-300`]">
                                <span
                                    :class="[`absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform`, h.is_aktif ? `translate-x-4` : `translate-x-0.5`]" />
                            </button>
                            <span v-else-if="h.is_darurat && !h.is_dibatalkan"
                                class="text-xs px-2.5 py-1 rounded-lg bg-red-50 text-red-600 font-medium">Aktif</span>
                            <span v-else
                                class="text-xs px-2.5 py-1 rounded-lg bg-gray-100 text-gray-400">Nonaktif</span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex justify-end gap-1">
                                <button v-if="!h.is_darurat && !h.is_dibatalkan" @click="openEdit(h)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button v-if="h.is_darurat && !h.is_dibatalkan" @click="openBatalkan(h)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                    </svg>
                                </button>
                                <button v-if="!h.is_darurat || h.is_dibatalkan" @click="hapus(h)"
                                    class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!hariLiburFiltered.length">
                        <td colspan="5" class="py-16 text-center">
                            <p class="text-3xl mb-3">{{tabs.find(t => t.key === activeTab)?.icon}}</p>
                            <p class="text-sm font-semibold text-gray-700 mb-1">Belum ada hari libur {{tabs.find(t =>
                                t.key ===
                                activeTab)?.label }}</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ════════════ SEMUA MODAL ════════════ -->

        <!-- MODAL TAMBAH / EDIT -->
        <div v-if="showForm" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>

                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">
                        {{ formTitle }}
                    </h3>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ formSubtitle }}
                    </p>
                </div>

                <div class="px-6 py-5 space-y-4">

                    <!-- Switch sumber (hanya saat tambah) -->
                    <div v-if="!editTarget">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Hari Libur</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" @click="form.sumber = 'nasional'"
                                :class="['py-3 px-4 rounded-xl border-2 text-left transition-all',
                                    form.sumber === 'nasional' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300']">
                                <p class="text-base mb-0.5">🇮🇩</p>
                                <p
                                    :class="[`text-sm font-semibold`, form.sumber === `nasional` ? `text-blue-700` : `text-gray-700`]">
                                    Nasional</p>
                                <p
                                    :class="[`text-xs mt-0.5`, form.sumber === `nasional` ? `text-blue-500` : `text-gray-400`]">
                                    Libur negara Indonesia</p>
                            </button>
                            <button type="button" @click="form.sumber = 'pesantren'"
                                :class="['py-3 px-4 rounded-xl border-2 text-left transition-all',
                                    form.sumber === 'pesantren' ? 'border-violet-500 bg-violet-50' : 'border-gray-200 hover:border-gray-300']">
                                <p class="text-base mb-0.5">🕌</p>
                                <p
                                    :class="[`text-sm font-semibold`, form.sumber === `pesantren` ? `text-violet-700` : `text-gray-700`]">
                                    Pesantren</p>
                                <p
                                    :class="[`text-xs mt-0.5`, form.sumber === `pesantren` ? `text-violet-500` : `text-gray-400`]">
                                    Kebijakan internal</p>
                            </button>
                        </div>
                    </div>

                    <!-- Nama -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Hari Libur <span
                                class="text-red-500">*</span></label>
                        <input v-model="form.nama" type="text"
                            :placeholder="form.sumber === `nasional` ? `cth: Idul Fitri, Isra Miraj, Hari Natal` : `cth: Haul Pesantren, Libur Semester`"
                            :class="[`w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 focus:ring-indigo-100 transition-all bg-white`,
                                errForm.nama ? `border-red-300` : `border-gray-200 focus:border-indigo-500`]" />
                        <p v-if="errForm.nama" class="mt-1 text-xs text-red-500">{{ errForm.nama }}</p>
                    </div>

                    <!-- Tanggal -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tanggal Mulai <span
                                    class="text-red-500">*</span></label>
                            <input v-model="form.tanggal" type="date"
                                :class="['w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 bg-white transition-all',
                                    errForm.tanggal ? 'border-red-300 focus:ring-red-100' : 'border-gray-200 focus:border-indigo-500 focus:ring-indigo-100']" />
                            <p v-if="errForm.tanggal" class="mt-1 text-xs text-red-500">{{ errForm.tanggal }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tanggal Selesai <span
                                    class="text-gray-400 font-normal">(opsional)</span></label>
                            <input v-model="form.tanggal_selesai" type="date" :min="form.tanggal"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white" />
                        </div>
                    </div>

                    <!-- Keterangan -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Keterangan</label>
                        <textarea v-model="form.keterangan" rows="2" resize-none
                            :placeholder="form.sumber === `pesantren` ? `cth: Haul akbar, libur akhir semester...` : `Catatan tambahan...`"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white resize-none" />
                    </div>

                    <!-- Toggle pengaruh gaji -->
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer select-none"
                        @click="form.pengaruh_gaji = !form.pengaruh_gaji">
                        <div>
                            <p class="text-sm font-medium text-gray-800">Berpengaruh ke Penggajian</p>
                            <p class="text-xs text-gray-400 mt-0.5">Hari ini dikurangi dari hari kerja — guru tidak
                                dihitung
                                alfa</p>
                        </div>
                        <div :class="[`relative rounded-full transition-colors shrink-0`, form.pengaruh_gaji ? `bg-indigo-600` : `bg-gray-300`]"
                            style="width:40px;height:22px">
                            <span :class="['absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200',
                                form.pengaruh_gaji ? 'translate-x-5' : 'translate-x-0.5']" />
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 px-6 pb-6">
                    <button @click="closeForm"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">Batal</button>
                    <button @click="submitForm" :disabled="loadingForm"
                        :class="['flex-1 py-2.5 rounded-xl text-white text-sm font-semibold disabled:opacity-60 transition-colors',
                            form.sumber === 'nasional' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-violet-600 hover:bg-violet-700']">
                        {{ loadingForm ? 'Menyimpan...' : formSubmitLabel }}
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL DARURAT -->
        <div v-if="showDarurat" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>
                <div class="px-6 py-5 border-b border-red-50 bg-red-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center text-2xl shrink-0">
                            🚨</div>
                        <div>
                            <h3 class="text-base font-semibold text-red-900">Libur Darurat / Mendadak</h3>
                            <p class="text-xs text-red-600 mt-0.5">Absensi hari itu otomatis diubah ke "libur"</p>
                        </div>
                    </div>
                </div>
                <div
                    class="mx-6 mt-5 p-3.5 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-700 space-y-1">
                    <p class="font-semibold">Yang terjadi otomatis:</p>
                    <p>✓ Absensi <strong>alfa/hadir/terlambat</strong> di tanggal ini → diubah ke <strong>libur</strong>
                    </p>
                    <p>✓ Guru yang belum absen → dibuatkan record <strong>libur</strong> otomatis</p>
                    <p>✓ Bisa di-rollback jika salah</p>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama / Alasan Libur <span
                                class="text-red-500">*</span></label>
                        <input v-model="formDarurat.nama" type="text"
                            placeholder="cth: Pengajian Akbar, Kondisi Darurat"
                            :class="['w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 bg-white transition-all',
                                errDarurat.nama ? 'border-red-300 focus:ring-red-100' : 'border-gray-200 focus:border-indigo-500 focus:ring-indigo-100']" />
                        <p v-if="errDarurat.nama" class="mt-1 text-xs text-red-500">{{ errDarurat.nama }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tanggal Mulai <span
                                    class="text-red-500">*</span></label>
                            <input v-model="formDarurat.tanggal" type="date"
                                :class="['w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 bg-white transition-all',
                                    errDarurat.tanggal ? 'border-red-300 focus:ring-red-100' : 'border-gray-200 focus:border-indigo-500 focus:ring-indigo-100']" />
                            <p v-if="errDarurat.tanggal" class="mt-1 text-xs text-red-500">{{ errDarurat.tanggal }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tanggal Selesai</label>
                            <input v-model="formDarurat.tanggal_selesai" type="date" :min="formDarurat.tanggal"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-indigo-500 bg-white" />
                            <p class="text-xs text-gray-400 mt-1">Kosong = 1 hari saja</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Keterangan / Alasan Detail <span
                                class="text-red-500">*</span></label>
                        <textarea v-model="formDarurat.keterangan" rows="3"
                            placeholder="Jelaskan alasan dan kondisi yang menyebabkan libur mendadak ini..."
                            :class="['w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 bg-white resize-none transition-all',
                                errDarurat.keterangan ? 'border-red-300 focus:ring-red-100' : 'border-gray-200 focus:border-indigo-500 focus:ring-indigo-100']" />
                        <p v-if="errDarurat.keterangan" class="mt-1 text-xs text-red-500">{{ errDarurat.keterangan }}
                        </p>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-amber-50 border border-amber-200 rounded-xl cursor-pointer select-none"
                        @click="formDarurat.pengaruh_gaji = !formDarurat.pengaruh_gaji">
                        <div>
                            <p class="text-sm font-medium text-gray-800">Gaji Tetap Penuh (Tidak Dipotong)</p>
                            <p class="text-xs text-gray-400 mt-0.5">Guru tetap mendapat vakasi kehadiran di hari ini</p>
                        </div>
                        <div :class="[`relative rounded-full transition-colors shrink-0`, formDarurat.pengaruh_gaji ? `bg-indigo-600` : `bg-gray-300`]"
                            style="width:40px;height:22px">
                            <span :class="['absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200',
                                formDarurat.pengaruh_gaji ? 'translate-x-5' : 'translate-x-0.5']" />
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 px-6 pb-6">
                    <button @click="showDarurat = false"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">Batal</button>
                    <button @click="submitDarurat" :disabled="loadingDarurat"
                        class="flex-1 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-bold disabled:opacity-60 transition-colors">
                        {{ loadingDarurat ? 'Memproses...' : 'Tambah & Update Absensi' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL BATALKAN DARURAT -->
        <div v-if="showBatalkan" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm" @click.stop>
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Batalkan Libur Darurat</h3>
                    <p class="text-xs text-red-500 mt-0.5">Absensi akan di-rollback ke status sebelumnya</p>
                </div>
                <div class="px-6 py-5">
                    <div v-if="batalkanTarget" class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-xl">
                        <p class="text-sm font-semibold text-amber-800">{{ batalkanTarget.nama }}</p>
                        <p class="text-xs text-amber-600 mt-1">{{ batalkanTarget.tanggal }}<template
                                v-if="batalkanTarget.tanggal_selesai"> – {{ batalkanTarget.tanggal_selesai }}</template>
                        </p>
                        <p v-if="batalkanTarget.absensi_terdampak > 0" class="text-xs text-amber-700 mt-2">
                            {{ batalkanTarget.absensi_terdampak }} absensi akan dikembalikan ke <strong>alfa</strong>
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Alasan Pembatalan <span
                                class="text-red-500">*</span></label>
                        <textarea v-model="formBatalkan.alasan" rows="3"
                            placeholder="Jelaskan mengapa libur darurat ini dibatalkan..."
                            :class="['w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 bg-white resize-none transition-all',
                                errBatalkan.alasan ? 'border-red-300 focus:ring-red-100' : 'border-gray-200 focus:border-indigo-500 focus:ring-indigo-100']" />
                        <p v-if="errBatalkan.alasan" class="mt-1 text-xs text-red-500">{{ errBatalkan.alasan }}</p>
                    </div>
                </div>
                <div class="flex gap-3 px-6 pb-6">
                    <button @click="showBatalkan = false"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">Batal</button>
                    <button @click="submitBatalkan" :disabled="loadingBatalkan"
                        class="flex-1 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold disabled:opacity-60 transition-colors">
                        {{ loadingBatalkan ? 'Memproses...' : 'Batalkan & Rollback' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL IMPORT NASIONAL -->
        <div v-if="showImport" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">📥 Import Libur Nasional {{ filterTahun }}</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Pilih dan import sekaligus dari preset hari libur nasional
                        Indonesia
                    </p>
                </div>
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs text-gray-500">{{ importSelected.length }} dari {{
                            nasionalTersediaValid.length }}
                            dipilih</p>
                        <div class="flex gap-3">
                            <button @click="importSelected = nasionalTersediaValid.map((_, i) => i)"
                                class="text-xs text-indigo-600 hover:underline">Pilih Semua</button>
                            <button @click="importSelected = []"
                                class="text-xs text-gray-400 hover:underline">Reset</button>
                        </div>
                    </div>
                    <div class="space-y-1.5 max-h-72 overflow-y-auto">
                        <label v-for="(n, i) in nasionalTersediaValid" :key="i"
                            :class="['flex items-center gap-3 px-4 py-3 rounded-xl border cursor-pointer transition-all',
                                importSelected.includes(i) ? 'border-blue-400 bg-blue-50' : 'border-gray-200 hover:border-gray-300']">
                            <input type="checkbox" :value="i" v-model="importSelected"
                                class="rounded text-blue-600 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800">{{ n.nama }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ formatTanggal(n.tanggal) }}</p>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="flex gap-3 px-6 pb-6 pt-2">
                    <button @click="showImport = false"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50">Batal</button>
                    <button @click="submitImport" :disabled="!importSelected.length"
                        class="flex-1 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold disabled:opacity-60 transition-colors">
                        Import {{ importSelected.length }} Hari Libur
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
    hari_libur: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    tahun: { type: Number, default: new Date().getFullYear() },
    nasional_tersedia: { type: Array, default: () => [] },
})

// ── State ──────────────────────────────────────────────────────────────────
const filterTahun = ref(props.tahun)

// ── Computed label modal ──────────────────────────────────────────────────
const formTitle = computed(() => {
    if (editTarget.value) {
        const icon = editTarget.value.sumber === 'nasional' ? '🇮🇩' : '🕌'
        return icon + ' Edit Hari Libur'
    }
    const icon = form.sumber === 'nasional' ? '🇮🇩' : '🕌'
    return icon + ' ' + (form.sumber === 'nasional' ? 'Tambah Hari Libur Nasional' : 'Tambah Hari Libur Pesantren')
})

const formSubtitle = computed(() => {
    if (editTarget.value) {
        return (editTarget.value.sumber_label ?? editTarget.value.sumber) + ' - ' + editTarget.value.tanggal
    }
    return form.sumber === 'nasional'
        ? 'Bisa di-toggle aktif/nonaktif per hari'
        : 'Kebijakan libur internal pesantren'
})

const formSubmitLabel = computed(() =>
    editTarget.value ? 'Simpan Perubahan' : 'Tambah Hari Libur'
)

// Icon per sumber (computed aman tanpa emoji di template)
const sourIcon = {
    nasional: '🇮🇩',
    pesantren: '🕌',
    darurat: '🚨',
}
const activeTab = ref('nasional')

// Modal visible flags — simple boolean, bukan state string
const showForm = ref(false)
const showDarurat = ref(false)
const showBatalkan = ref(false)
const showImport = ref(false)

const tabs = [
    { key: 'nasional', label: 'Nasional', icon: '🇮🇩' },
    { key: 'pesantren', label: 'Pesantren', icon: '🕌' },
    { key: 'darurat', label: 'Darurat', icon: '🚨' },
]

// ── Computed ──────────────────────────────────────────────────────────────
const summaryCards = computed(() => [
    { label: 'Total', value: props.summary.total ?? 0, bg: 'bg-white border-gray-200', color: 'text-gray-900' },
    { label: 'Aktif', value: props.summary.aktif ?? 0, bg: 'bg-emerald-50 border-emerald-100', color: 'text-emerald-700' },
    { label: 'Nonaktif', value: props.summary.nonaktif ?? 0, bg: 'bg-gray-50 border-gray-200', color: 'text-gray-500' },
    { label: 'Nasional', value: props.summary.nasional ?? 0, bg: 'bg-blue-50 border-blue-100', color: 'text-blue-700' },
    { label: 'Pesantren', value: props.summary.pesantren ?? 0, bg: 'bg-violet-50 border-violet-100', color: 'text-violet-700' },
    { label: 'Darurat', value: props.summary.darurat ?? 0, bg: 'bg-red-50 border-red-100', color: 'text-red-600' },
])

const hariLiburFiltered = computed(() =>
    props.hari_libur.filter(h => h.sumber === activeTab.value)
)

const nasionalTersediaValid = computed(() =>
    props.nasional_tersedia.filter(n => n.tanggal)
)

function hitungTab(key) {
    return props.hari_libur.filter(h => h.sumber === key).length
}

// ── Filter tahun ────────────────────────────────────────────────────────────
function applyFilter() {
    router.get(route('admin.smart-payroll.hari-libur.index'), { tahun: filterTahun.value })
}

// ── Toggle Aktif ─────────────────────────────────────────────────────────────
function toggleAktif(h) {
    router.patch(route('admin.smart-payroll.hari-libur.toggle', h.id), {}, { preserveScroll: true })
}

// ── Hapus ─────────────────────────────────────────────────────────────────────
function hapus(h) {
    confirm.value.ask(
        { title: 'Hapus Hari Libur?', message: `"${h.nama}" akan dihapus permanen.`,
          variant: 'danger', confirmLabel: 'Ya, Hapus', irreversible: true },
        (done) => router.delete(route('admin.smart-payroll.hari-libur.destroy', h.id),
            { preserveScroll: true, onFinish: done }),
    )
}

// ── Form Tambah / Edit ─────────────────────────────────────────────────────────
const loadingForm = ref(false)
const editTarget = ref(null)
const form = reactive({ nama: '', tanggal: '', tanggal_selesai: '', sumber: 'pesantren', keterangan: '', pengaruh_gaji: true })
const errForm = reactive({ nama: '', tanggal: '' })

function openTambah() {
    editTarget.value = null
    Object.assign(form, {
        nama: '', tanggal: '', tanggal_selesai: '', keterangan: '',
        sumber: activeTab.value === 'darurat' ? 'pesantren' : activeTab.value,
        pengaruh_gaji: true,
    })
    errForm.nama = ''; errForm.tanggal = ''
    showForm.value = true
}

function openEdit(h) {
    editTarget.value = h
    Object.assign(form, {
        nama: h.nama,
        tanggal: h.tanggal_raw,
        tanggal_selesai: h.tanggal_selesai_raw ?? '',
        sumber: h.sumber,
        keterangan: h.keterangan ?? '',
        pengaruh_gaji: h.pengaruh_gaji,
    })
    errForm.nama = ''; errForm.tanggal = ''
    showForm.value = true
}

function closeForm() {
    showForm.value = false
    editTarget.value = null
}

function submitForm() {
    errForm.nama = form.nama.trim() ? '' : 'Nama wajib diisi'
    errForm.tanggal = form.tanggal ? '' : 'Tanggal wajib diisi'
    if (errForm.nama || errForm.tanggal) return

    loadingForm.value = true

    if (editTarget.value) {
        router.put(route('admin.smart-payroll.hari-libur.update', editTarget.value.id), { ...form }, {
            onSuccess: () => closeForm(),
            onError: (e) => { if (e.nama) errForm.nama = e.nama; if (e.tanggal) errForm.tanggal = e.tanggal },
            onFinish: () => loadingForm.value = false,
            preserveScroll: true,
        })
    } else {
        router.post(route('admin.smart-payroll.hari-libur.store'), { ...form }, {
            onSuccess: () => { closeForm(); activeTab.value = form.sumber },
            onError: (e) => { if (e.nama) errForm.nama = e.nama; if (e.tanggal) errForm.tanggal = e.tanggal },
            onFinish: () => loadingForm.value = false,
        })
    }
}

// ── Darurat ─────────────────────────────────────────────────────────────────
const loadingDarurat = ref(false)
const formDarurat = reactive({ nama: '', tanggal: '', tanggal_selesai: '', keterangan: '', pengaruh_gaji: true })
const errDarurat = reactive({ nama: '', tanggal: '', keterangan: '' })

function openDarurat() {
    Object.assign(formDarurat, { nama: '', tanggal: '', tanggal_selesai: '', keterangan: '', pengaruh_gaji: true })
    errDarurat.nama = ''; errDarurat.tanggal = ''; errDarurat.keterangan = ''
    showDarurat.value = true
}

function submitDarurat() {
    errDarurat.nama = formDarurat.nama.trim() ? '' : 'Wajib diisi'
    errDarurat.tanggal = formDarurat.tanggal ? '' : 'Wajib diisi'
    errDarurat.keterangan = formDarurat.keterangan.trim() ? '' : 'Wajib diisi'
    if (errDarurat.nama || errDarurat.tanggal || errDarurat.keterangan) return

    loadingDarurat.value = true
    router.post(route('admin.smart-payroll.hari-libur.darurat'), { ...formDarurat }, {
        onSuccess: () => { showDarurat.value = false; activeTab.value = 'darurat' },
        onError: (e) => { if (e.nama) errDarurat.nama = e.nama; if (e.tanggal) errDarurat.tanggal = e.tanggal; if (e.keterangan) errDarurat.keterangan = e.keterangan },
        onFinish: () => loadingDarurat.value = false,
    })
}

// ── Batalkan Darurat ─────────────────────────────────────────────────────────
const loadingBatalkan = ref(false)
const batalkanTarget = ref(null)
const formBatalkan = reactive({ alasan: '' })
const errBatalkan = reactive({ alasan: '' })

function openBatalkan(h) {
    batalkanTarget.value = h
    formBatalkan.alasan = ''
    errBatalkan.alasan = ''
    showBatalkan.value = true
}

function submitBatalkan() {
    errBatalkan.alasan = formBatalkan.alasan.trim() ? '' : 'Alasan wajib diisi'
    if (errBatalkan.alasan) return

    loadingBatalkan.value = true
    router.post(route('admin.smart-payroll.hari-libur.batalkan', batalkanTarget.value.id), { ...formBatalkan }, {
        onSuccess: () => { showBatalkan.value = false },
        onError: (e) => { if (e.alasan) errBatalkan.alasan = e.alasan },
        onFinish: () => loadingBatalkan.value = false,
        preserveScroll: true,
    })
}

// ── Import Nasional ──────────────────────────────────────────────────────────
const importSelected = ref([])

function openImport() {
    importSelected.value = []
    showImport.value = true
}

function submitImport() {
    if (!importSelected.value.length) return
    const items = importSelected.value.map(i => nasionalTersediaValid.value[i])
    router.post(route('admin.smart-payroll.hari-libur.import-nasional'), { items, pengaruh_gaji: true }, {
        onSuccess: () => { showImport.value = false; importSelected.value = []; activeTab.value = 'nasional' },
    })
}

// ── Helper ───────────────────────────────────────────────────────────────────
function formatTanggal(t) {
    if (!t) return ''
    try { return new Date(t).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }
    catch { return t }
}
</script>
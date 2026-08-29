<template>
    <AdminLayout title="Setting Lokasi Absensi" subtitle="Smart Payroll">

        <Head title="Setting Lokasi Absensi" />

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Setting Lokasi Absensi</h2>
                <p class="text-sm text-gray-400 mt-0.5">Konfigurasi lokasi GPS & WiFi — global atau per guru</p>
            </div>
            <div class="flex gap-2">
                <button @click="activeTab = activeTab === 'lokasi' ? 'guru' : 'lokasi'"
                    class="inline-flex items-center gap-2 px-4 py-2.5 border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-medium rounded-xl transition-colors">
                    {{ activeTab === 'lokasi' ? '👤 Lihat per Guru' : '📍 Lihat Lokasi' }}
                </button>
                <button @click="openTambah"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    + Tambah Lokasi
                </button>
            </div>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-3 lg:grid-cols-6 gap-2 mb-5">
            <div v-for="s in summaryCards" :key="s.label" :class="['rounded-xl border px-3 py-2.5 text-center', s.bg]">
                <p :class="['text-xl font-bold leading-none', s.color]">{{ s.value }}</p>
                <p class="text-xs text-gray-400 mt-1 leading-tight">{{ s.label }}</p>
            </div>
        </div>

        <!-- Info logika validasi -->
        <div class="mb-5 p-4 bg-amber-50 border border-amber-100 rounded-2xl">
            <p class="text-xs font-semibold text-amber-800 mb-2">🔒 Urutan Validasi Absensi Flutter</p>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-2 text-xs text-amber-700">
                <div v-for="(step, i) in logicSteps" :key="i" class="flex items-start gap-2">
                    <span class="shrink-0 font-bold">{{ i + 1 }}.</span>
                    <div>
                        <p class="font-semibold">{{ step.title }}</p>
                        <p class="text-amber-600">{{ step.desc }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── TAB: DAFTAR LOKASI ── -->
        <template v-if="activeTab === 'lokasi'">
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-5">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50/50 border-b border-gray-100">
                            <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase">Nama & Tipe
                            </th>
                            <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase">Konfigurasi
                            </th>
                            <th
                                class="px-5 py-3.5 text-center text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">
                                Lingkup</th>
                            <th
                                class="px-5 py-3.5 text-center text-xs font-semibold text-gray-400 uppercase hidden lg:table-cell">
                                Izin</th>
                            <th class="px-5 py-3.5 text-center text-xs font-semibold text-gray-400 uppercase">Status
                            </th>
                            <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template v-for="s in setting" :key="s.id">
                            <tr :class="['hover:bg-gray-50/40 transition-colors', !s.is_aktif ? 'opacity-50' : '']">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div :class="['w-10 h-10 rounded-xl flex items-center justify-center text-xl shrink-0',
                                            s.tipe === 'koordinat' ? 'bg-blue-50' : 'bg-violet-50']">
                                            {{ s.tipe === 'koordinat' ? '📍' : '📶' }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800">{{ s.nama }}</p>
                                            <div class="flex gap-1.5 mt-0.5">
                                                <span
                                                    :class="['text-xs px-1.5 py-0.5 rounded font-medium',
                                                        s.tipe === 'koordinat' ? 'bg-blue-50 text-blue-700' : 'bg-violet-50 text-violet-700']">
                                                    {{ s.tipe_label }}
                                                </span>
                                                <span v-if="s.guru_assigned?.length > 0"
                                                    class="text-xs px-1.5 py-0.5 rounded bg-teal-50 text-teal-700 cursor-pointer"
                                                    @click="toggleExpandLokasi(s.id)">
                                                    {{ s.guru_assigned.length }} guru
                                                    {{ expandedLokasi.includes(s.id) ? '▲' : '▼' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <template v-if="s.tipe === 'koordinat'">
                                        <p class="text-sm font-mono text-gray-700">{{ s.latitude }}, {{ s.longitude }}
                                        </p>
                                        <p class="text-xs text-gray-400 mt-0.5">Radius: <strong>{{ s.radius_meter
                                                }}m</strong></p>
                                    </template>
                                    <template v-else>
                                        <p class="text-sm font-semibold text-gray-700">{{ s.wifi_ssid ?? '—' }}</p>
                                        <p v-if="s.wifi_bssid" class="text-xs font-mono text-gray-400">{{ s.wifi_bssid
                                            }}</p>
                                    </template>
                                </td>
                                <td class="px-5 py-4 text-center hidden md:table-cell">
                                    <span
                                        :class="['text-xs font-medium px-2.5 py-1 rounded-lg',
                                            s.lingkup === 'global' ? 'bg-gray-100 text-gray-600' : 'bg-indigo-50 text-indigo-700']">
                                        {{ s.lingkup_label }}
                                    </span>
                                    <p v-if="s.lingkup === 'per_user'" class="text-xs text-gray-400 mt-0.5">
                                        {{ s.guru_assigned?.length ?? 0 }} guru
                                    </p>
                                </td>
                                <td class="px-5 py-4 hidden lg:table-cell">
                                    <div class="space-y-1">
                                        <div
                                            :class="['text-xs px-2 py-0.5 rounded text-center font-medium',
                                                s.izinkan_dinas_luar ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-400']">
                                            Dinas Luar {{ s.izinkan_dinas_luar ? '✓' : '✗' }}
                                        </div>
                                        <div
                                            :class="['text-xs px-2 py-0.5 rounded text-center font-medium',
                                                s.izinkan_izin_aktif ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-400']">
                                            Izin Aktif {{ s.izinkan_izin_aktif ? '✓' : '✗' }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <!-- FIX: pakai route toggle yang benar dengan ID -->
                                    <button @click="toggleAktif(s)" :class="['relative w-9 h-5 rounded-full transition-colors',
                                        s.is_aktif ? 'bg-indigo-500' : 'bg-gray-300']">
                                        <span :class="['absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform',
                                            s.is_aktif ? 'translate-x-4' : 'translate-x-0.5']" />
                                    </button>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex justify-end gap-1">
                                        <button v-if="s.lingkup === 'per_user'" @click="openAssignGuru(s)"
                                            class="p-2 rounded-lg text-gray-400 hover:text-teal-600 hover:bg-teal-50"
                                            title="Assign Guru">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </button>
                                        <button @click="openEdit(s)"
                                            class="p-2 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button @click="hapus(s)"
                                            class="p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Sub-row: list guru yang di-assign -->
                            <tr v-if="expandedLokasi.includes(s.id) && s.guru_assigned?.length">
                                <td colspan="6" class="px-5 py-3 bg-teal-50/30">
                                    <div class="flex flex-wrap gap-2">
                                        <div v-for="g in s.guru_assigned" :key="g.id"
                                            class="flex items-center gap-2 px-3 py-1.5 bg-white border border-teal-100 rounded-xl text-xs">
                                            <span class="font-semibold text-gray-700">{{ g.nama }}</span>
                                            <span class="text-gray-400">{{ g.jabatan }}</span>
                                            <span
                                                :class="['px-1.5 py-0.5 rounded text-xs font-medium',
                                                    g.konteks === 'default' ? 'bg-gray-100 text-gray-600' :
                                                        g.konteks === 'jadwal' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700']">
                                                {{ g.konteks }}
                                            </span>
                                            <button @click="unassignGuru(s, g.id)"
                                                class="ml-1 text-gray-300 hover:text-red-500 transition-colors">✕</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!setting.length">
                            <td colspan="6" class="py-14 text-center text-sm text-gray-400">
                                Belum ada setting lokasi.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- ── TAB: PER GURU ── -->
        <template v-else>
            <div class="flex items-center gap-2 mb-4">
                <input v-model="searchGuru" type="text" placeholder="Cari nama guru..."
                    class="px-4 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-indigo-500 w-52" />
                <span class="text-xs text-gray-400 ml-auto">{{ guruFiltered.length }} guru</span>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                <div v-for="g in guruFiltered" :key="g.id"
                    class="bg-white rounded-2xl border border-gray-200 p-4 hover:shadow-sm transition-shadow">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-9 h-9 rounded-xl bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-700 shrink-0">
                                {{ g.nama.charAt(0) }}
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800">{{ g.nama }}</p>
                                <p class="text-xs text-gray-400">{{ g.jabatan }}</p>
                            </div>
                        </div>
                        <button @click="openAssignLokasiToGuru(g)"
                            class="px-3 py-1.5 rounded-xl border border-indigo-200 text-indigo-600 hover:bg-indigo-50 text-xs font-medium transition-colors">
                            Setting Lokasi
                        </button>
                    </div>
                    <div v-if="g.lokasi_assigned?.length > 0" class="space-y-1.5">
                        <div v-for="l in g.lokasi_assigned" :key="l.id"
                            class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-xl">
                            <span class="text-sm shrink-0">{{ l.tipe === 'koordinat' ? '📍' : '📶' }}</span>
                            <p class="text-xs font-medium text-gray-700 flex-1 truncate">{{ l.nama }}</p>
                            <span :class="['text-xs px-1.5 py-0.5 rounded font-medium',
                                l.konteks === 'default' ? 'bg-gray-100 text-gray-600' :
                                    l.konteks === 'jadwal' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700']">
                                {{ l.konteks }}
                            </span>
                        </div>
                    </div>
                    <div v-else class="px-3 py-2 bg-blue-50 rounded-xl">
                        <p class="text-xs text-blue-600">Menggunakan lokasi global pesantren</p>
                    </div>
                </div>
            </div>
        </template>

        <!-- Test Validasi -->
        <div class="mt-5 bg-white rounded-2xl border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-800 mb-4">🧪 Test Validasi Lokasi</h3>
            <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Guru (opsional)</label>
                    <select v-model="testForm.tenaga_pendidik_id" :class="fieldCls()">
                        <option value="">Global</option>
                        <option v-for="g in guru" :key="g.id" :value="g.id">{{ g.nama }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Latitude</label>
                    <input v-model="testForm.latitude" type="number" step="0.0000001" placeholder="-7.1234"
                        :class="fieldCls()" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Longitude</label>
                    <input v-model="testForm.longitude" type="number" step="0.0000001" placeholder="112.1234"
                        :class="fieldCls()" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">WiFi SSID</label>
                    <input v-model="testForm.wifi_ssid" type="text" placeholder="PonpesAnNur" :class="fieldCls()" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Status</label>
                    <select v-model="testForm.status" :class="fieldCls()">
                        <option value="hadir">Hadir</option>
                        <option value="dinas_luar">Dinas Luar</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button @click="jalankanTest" :disabled="testLoading"
                        class="w-full py-2 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold disabled:opacity-60">
                        {{ testLoading ? 'Testing...' : 'Test' }}
                    </button>
                </div>
            </div>
            <div v-if="testResult" class="mt-3 p-3 rounded-xl text-sm"
                :class="testResult.valid ? 'bg-emerald-50 border border-emerald-200' : 'bg-red-50 border border-red-200'">
                <div class="flex items-center gap-2">
                    <span :class="['font-bold', testResult.valid ? 'text-emerald-700' : 'text-red-700']">
                        {{ testResult.valid ? '✓ Valid' : '✗ Tidak Valid' }}
                    </span>
                    <span class="text-xs px-2 py-0.5 rounded-lg bg-white/70 text-gray-600">
                        {{ testResult.tipe_validasi }}
                    </span>
                </div>
                <p class="mt-1 text-xs" :class="testResult.valid ? 'text-emerald-700' : 'text-red-700'">
                    {{ testResult.pesan }}
                </p>
                <p v-if="testResult.jarak_meter" class="text-xs text-gray-500 mt-0.5">
                    Jarak: {{ testResult.jarak_meter }}m
                </p>
            </div>
        </div>

        <!-- ══ MODAL TAMBAH/EDIT ══ -->
        <div v-if="showForm" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50"
            @click.self="closeForm">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-5 border-b border-gray-100 sticky top-0 bg-white z-10">
                    <h3 class="text-base font-semibold text-gray-900">
                        {{ editTarget ? 'Edit Lokasi' : 'Tambah Lokasi Absensi' }}
                    </h3>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <!-- Nama -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Nama <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.nama" type="text" placeholder="cth: Area Pesantren Utama"
                            :class="fieldCls(err.nama)" />
                        <p v-if="err.nama" class="mt-1 text-xs text-red-500">{{ err.nama }}</p>
                    </div>

                    <!-- Tipe (hanya saat tambah) -->
                    <div v-if="!editTarget">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipe</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button"
                                v-for="t in [{ v: 'koordinat', icon: '📍', l: 'GPS Koordinat' }, { v: 'wifi', icon: '📶', l: 'WiFi' }]"
                                :key="t.v" @click="form.tipe = t.v"
                                :class="['py-2.5 rounded-xl border-2 text-center text-xs font-semibold transition-all',
                                    form.tipe === t.v ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600']">
                                {{ t.icon }} {{ t.l }}
                            </button>
                        </div>
                    </div>
                    <!-- Tipe badge (readonly saat edit) -->
                    <div v-else class="flex items-center gap-2 p-3 bg-gray-50 rounded-xl">
                        <span class="text-sm">{{ editTarget.tipe === 'koordinat' ? '📍' : '📶' }}</span>
                        <span class="text-sm font-medium text-gray-700">{{ editTarget.tipe_label }}</span>
                        <span class="text-xs text-gray-400">(tidak bisa diubah)</span>
                    </div>

                    <!-- Lingkup -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Lingkup</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button"
                                v-for="l in [{ v: 'global', l: '🌐 Semua Guru' }, { v: 'per_user', l: '👤 Per Guru' }]" :key="l.v"
                                @click="form.lingkup = l.v"
                                :class="['py-2.5 rounded-xl border-2 text-center text-xs font-semibold transition-all',
                                    form.lingkup === l.v ? 'border-violet-500 bg-violet-50 text-violet-700' : 'border-gray-200 text-gray-600']">
                                {{ l.l }}
                            </button>
                        </div>
                    </div>

                    <!-- Field Koordinat -->
                    <template v-if="form.tipe === 'koordinat'">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Latitude <span class="text-red-500">*</span>
                                </label>
                                <input v-model.number="form.latitude" type="number" step="0.0000001"
                                    :class="fieldCls(err.latitude)" />
                                <p v-if="err.latitude" class="mt-1 text-xs text-red-500">{{ err.latitude }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Longitude <span class="text-red-500">*</span>
                                </label>
                                <input v-model.number="form.longitude" type="number" step="0.0000001"
                                    :class="fieldCls(err.longitude)" />
                                <p v-if="err.longitude" class="mt-1 text-xs text-red-500">{{ err.longitude }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Radius (meter)</label>
                            <input v-model.number="form.radius_meter" type="number" min="10" max="5000"
                                :class="fieldCls()" />
                            <div class="flex gap-2 mt-2">
                                <button v-for="r in [50, 100, 200, 500]" :key="r" type="button"
                                    @click="form.radius_meter = r"
                                    :class="['px-2.5 py-1 rounded-lg text-xs border transition-colors',
                                        form.radius_meter === r ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-500']">
                                    {{ r }}m
                                </button>
                            </div>
                        </div>
                        <button type="button" @click="ambilLokasi"
                            class="w-full py-2.5 rounded-xl border border-dashed border-indigo-300 text-indigo-600 text-sm hover:bg-indigo-50 transition-colors">
                            📍 Ambil Koordinat Perangkat Ini
                        </button>
                    </template>

                    <!-- Field WiFi -->
                    <template v-else-if="form.tipe === 'wifi'">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                WiFi SSID <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.wifi_ssid" type="text" placeholder="PonpesAnNur"
                                :class="fieldCls(err.wifi_ssid)" />
                            <p v-if="err.wifi_ssid" class="mt-1 text-xs text-red-500">{{ err.wifi_ssid }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">WiFi BSSID (opsional)</label>
                            <input v-model="form.wifi_bssid" type="text" placeholder="AA:BB:CC:DD:EE:FF"
                                :class="fieldCls()" />
                            <p class="text-xs text-gray-400 mt-1">MAC address router — lebih aman dari SSID saja</p>
                        </div>
                    </template>

                    <!-- Keterangan -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Keterangan</label>
                        <textarea v-model="form.keterangan" rows="2" :class="fieldCls() + ' resize-none'"
                            placeholder="Deskripsi area / kegunaan lokasi ini..." />
                    </div>

                    <!-- Toggle izin -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl cursor-pointer select-none"
                            @click="form.izinkan_dinas_luar = !form.izinkan_dinas_luar">
                            <div>
                                <p class="text-sm font-medium text-gray-800">Izinkan Dinas Luar</p>
                                <p class="text-xs text-gray-400">Guru dengan status dinas luar boleh absen dari mana
                                    saja</p>
                            </div>
                            <div :class="['relative rounded-full transition-colors shrink-0',
                                form.izinkan_dinas_luar ? 'bg-indigo-600' : 'bg-gray-300']"
                                style="height:22px;width:40px">
                                <span :class="['absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200',
                                    form.izinkan_dinas_luar ? 'translate-x-5' : 'translate-x-0.5']" />
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl cursor-pointer select-none"
                            @click="form.izinkan_izin_aktif = !form.izinkan_izin_aktif">
                            <div>
                                <p class="text-sm font-medium text-gray-800">Izinkan Izin Aktif</p>
                                <p class="text-xs text-gray-400">Guru yang punya izin disetujui (sakit/izin) boleh absen
                                    bebas
                                </p>
                            </div>
                            <div :class="['relative rounded-full transition-colors shrink-0',
                                form.izinkan_izin_aktif ? 'bg-indigo-600' : 'bg-gray-300']"
                                style="height:22px;width:40px">
                                <span :class="['absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200',
                                    form.izinkan_izin_aktif ? 'translate-x-5' : 'translate-x-0.5']" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 px-6 pb-6 pt-2 border-t border-gray-50 sticky bottom-0 bg-white">
                    <button @click="closeForm"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600">
                        Batal
                    </button>
                    <button @click="submitForm" :disabled="loading"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold disabled:opacity-60">
                        {{ loading ? 'Menyimpan...' : (editTarget ? 'Simpan Perubahan' : 'Tambah Lokasi') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ══ MODAL ASSIGN GURU KE LOKASI ══ -->
        <div v-if="showAssign" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50"
            @click.self="showAssign = false">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Assign Guru → {{ assignTarget?.nama }}</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Pilih guru yang menggunakan lokasi ini</p>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Guru <span class="text-red-500">*</span>
                        </label>
                        <select v-model="assignForm.tenaga_pendidik_id" :class="fieldCls()">
                            <option value="">-- Pilih Guru --</option>
                            <option v-for="g in guru" :key="g.id" :value="g.id">
                                {{ g.nama }} ({{ g.jabatan }})
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Konteks</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="k in ['default', 'jadwal', 'sementara']" :key="k" type="button"
                                @click="assignForm.konteks = k"
                                :class="['py-2 rounded-xl border-2 text-xs font-semibold capitalize transition-all text-center',
                                    assignForm.konteks === k ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600']">
                                {{ k }}
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Berlaku Mulai</label>
                            <input v-model="assignForm.berlaku_mulai" type="date" :class="fieldCls()" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Berlaku Selesai</label>
                            <input v-model="assignForm.berlaku_selesai" type="date" :class="fieldCls()" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Keterangan</label>
                        <input v-model="assignForm.keterangan" type="text"
                            placeholder="cth: Piket gedung B semester ini" :class="fieldCls()" />
                    </div>
                </div>
                <div class="flex gap-3 px-6 pb-6">
                    <button @click="showAssign = false"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600">
                        Batal
                    </button>
                    <button @click="submitAssign" :disabled="!assignForm.tenaga_pendidik_id"
                        class="flex-1 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold disabled:opacity-60">
                        Assign Guru
                    </button>
                </div>
            </div>
        </div>

        <!-- ══ MODAL ASSIGN LOKASI KE GURU ══ -->
        <div v-if="showAssignToGuru" class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/50"
            @click.self="showAssignToGuru = false">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-5 border-b border-gray-100 sticky top-0 bg-white">
                    <h3 class="text-base font-semibold text-gray-900">
                        Setting Lokasi: {{ assignToGuruTarget?.nama }}
                    </h3>
                    <p class="text-xs text-gray-400 mt-0.5">Pilih lokasi yang bisa dipakai guru ini</p>
                </div>
                <div class="px-6 py-5">
                    <div class="mb-4 p-3 bg-blue-50 border border-blue-100 rounded-xl text-xs text-blue-700">
                        Lokasi <strong>global</strong> otomatis berlaku untuk semua guru.
                        Di sini hanya tambahkan lokasi <strong>per-user</strong> tambahan.
                    </div>
                    <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                        <label v-for="s in lokasiPerUser" :key="s.id"
                            :class="['flex items-start gap-3 px-4 py-3 rounded-xl border cursor-pointer transition-all',
                                guruLokasiSelected.includes(s.id) ? 'border-teal-400 bg-teal-50' : 'border-gray-200 hover:border-gray-300']">
                            <input type="checkbox" :value="s.id" v-model="guruLokasiSelected"
                                class="rounded text-teal-600 mt-0.5 shrink-0" />
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-800">
                                    {{ s.tipe === 'koordinat' ? '📍' : '📶' }} {{ s.nama }}
                                </p>
                                <p class="text-xs text-gray-400">{{ s.tipe_label }}</p>
                            </div>
                        </label>
                        <p v-if="!lokasiPerUser.length" class="text-xs text-gray-400 text-center py-4">
                            Belum ada lokasi per-user. Tambahkan lokasi dengan lingkup "Per Guru" dulu.
                        </p>
                    </div>
                </div>
                <div class="flex gap-3 px-6 pb-6 border-t border-gray-50 pt-4">
                    <button @click="showAssignToGuru = false"
                        class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600">
                        Batal
                    </button>
                    <button @click="submitAssignToGuru"
                        class="flex-1 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold">
                        Simpan ({{ guruLokasiSelected.length }} lokasi)
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
    setting: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    guru: { type: Array, default: () => [] },
})

// ── State UI ──────────────────────────────────────────────────────────────────
const activeTab = ref('lokasi')
const searchGuru = ref('')
const expandedLokasi = ref([])

const logicSteps = [
    { title: 'Izin Aktif', desc: 'Punya izin disetujui hari ini → bebas' },
    { title: 'Dinas Luar', desc: 'Status dinas_luar → bebas' },
    { title: 'Lokasi User', desc: 'Cek WiFi/GPS lokasi per-guru' },
    { title: 'Lokasi Global', desc: 'Cek WiFi/GPS lokasi pesantren' },
    { title: 'Ditolak', desc: 'Tidak ada yang cocok → invalid' },
]

const summaryCards = computed(() => [
    { label: 'Total', value: props.summary.total ?? 0, bg: 'bg-white border-gray-200', color: 'text-gray-900' },
    { label: 'Global', value: props.summary.global ?? 0, bg: 'bg-gray-50 border-gray-200', color: 'text-gray-700' },
    { label: 'Per Guru', value: props.summary.per_user ?? 0, bg: 'bg-indigo-50 border-indigo-100', color: 'text-indigo-700' },
    { label: 'GPS', value: props.summary.koordinat ?? 0, bg: 'bg-blue-50 border-blue-100', color: 'text-blue-700' },
    { label: 'WiFi', value: props.summary.wifi ?? 0, bg: 'bg-violet-50 border-violet-100', color: 'text-violet-700' },
    { label: 'Aktif', value: props.summary.aktif ?? 0, bg: 'bg-emerald-50 border-emerald-100', color: 'text-emerald-700' },
])

const guruFiltered = computed(() => {
    if (!searchGuru.value) return props.guru
    const s = searchGuru.value.toLowerCase()
    return props.guru.filter(g =>
        g.nama.toLowerCase().includes(s) || g.jabatan.toLowerCase().includes(s))
})

const lokasiPerUser = computed(() =>
    props.setting.filter(s => s.lingkup === 'per_user' && s.is_aktif))

function toggleExpandLokasi(id) {
    const i = expandedLokasi.value.indexOf(id)
    i >= 0 ? expandedLokasi.value.splice(i, 1) : expandedLokasi.value.push(id)
}

// ── Toggle aktif ──────────────────────────────────────────────────────────────
function toggleAktif(s) {
    const id = s?.id
    if (!id) {
        console.error('[toggleAktif] ID tidak ditemukan:', s)
        return
    }
    router.patch(
        `/admin/smart-payroll/setting-lokasi/${id}/toggle`,
        {},
        { preserveScroll: true }
    )
}

function hapus(s) {
    const id = s?.id
    if (!id) return
    confirm.value.ask(
        { title: 'Hapus Lokasi Absensi?', message: `"${s.nama}" akan dihapus. Semua assign guru pada lokasi ini ikut terhapus.`,
          variant: 'danger', confirmLabel: 'Ya, Hapus', irreversible: true },
        (done) => router.delete(`/admin/smart-payroll/setting-lokasi/${id}`,
            { preserveScroll: true, onFinish: done }),
    )
}

// ── Form Tambah/Edit ──────────────────────────────────────────────────────────
const showForm = ref(false)
const loading = ref(false)
const editTarget = ref(null)

const form = reactive({
    nama: '', tipe: 'koordinat', lingkup: 'global',
    latitude: null, longitude: null, radius_meter: 100,
    wifi_ssid: '', wifi_bssid: '',
    izinkan_dinas_luar: true, izinkan_izin_aktif: true,
    keterangan: '',
})
const err = reactive({ nama: '', latitude: '', longitude: '', wifi_ssid: '' })

function resetForm() {
    Object.assign(form, {
        nama: '', tipe: 'koordinat', lingkup: 'global',
        latitude: null, longitude: null, radius_meter: 100,
        wifi_ssid: '', wifi_bssid: '',
        izinkan_dinas_luar: true, izinkan_izin_aktif: true,
        keterangan: '',
    })
    Object.assign(err, { nama: '', latitude: '', longitude: '', wifi_ssid: '' })
}

function openTambah() {
    editTarget.value = null
    resetForm()
    showForm.value = true
}

function openEdit(s) {
    editTarget.value = s
    Object.assign(form, {
        nama: s.nama,
        tipe: s.tipe,
        lingkup: s.lingkup,
        latitude: s.latitude,
        longitude: s.longitude,
        radius_meter: s.radius_meter ?? 100,
        wifi_ssid: s.wifi_ssid ?? '',
        wifi_bssid: s.wifi_bssid ?? '',
        izinkan_dinas_luar: s.izinkan_dinas_luar,
        izinkan_izin_aktif: s.izinkan_izin_aktif,
        keterangan: s.keterangan ?? '',
    })
    Object.assign(err, { nama: '', latitude: '', longitude: '', wifi_ssid: '' })
    showForm.value = true
}

function closeForm() {
    showForm.value = false
    editTarget.value = null
}

function validate() {
    err.nama = form.nama.trim() ? '' : 'Nama wajib diisi'
    err.latitude = ''
    err.longitude = ''
    err.wifi_ssid = ''
    if (form.tipe === 'koordinat') {
        err.latitude = form.latitude != null ? '' : 'Latitude wajib'
        err.longitude = form.longitude != null ? '' : 'Longitude wajib'
    }
    if (form.tipe === 'wifi') {
        err.wifi_ssid = form.wifi_ssid.trim() ? '' : 'SSID wajib'
    }
    return !Object.values(err).some(Boolean)
}

function submitForm() {
    if (!validate()) return
    loading.value = true
    const payload = { ...form }

    if (editTarget.value) {
        const id = editTarget.value?.id
        if (!id) {
            console.error('[submitForm] editTarget.id tidak ditemukan:', editTarget.value)
            loading.value = false
            return
        }
        const updateUrl = `/admin/smart-payroll/setting-lokasi/${id}`
        router.put(updateUrl, payload, {
            onSuccess: () => { loading.value = false; closeForm() },
            onError: () => { loading.value = false },
        })
    } else {
        router.post(
            route('admin.smart-payroll.setting-lokasi.store'),
            payload,
            {
                onSuccess: () => { loading.value = false; closeForm() },
                onError: () => { loading.value = false },
            }
        )
    }
}

function ambilLokasi() {
    if (!navigator.geolocation) return alert('Geolocation tidak didukung di browser ini.')
    navigator.geolocation.getCurrentPosition(
        pos => {
            form.latitude = Number(pos.coords.latitude.toFixed(7))
            form.longitude = Number(pos.coords.longitude.toFixed(7))
        },
        e => alert('Gagal ambil lokasi: ' + e.message),
        { enableHighAccuracy: true, timeout: 10000 }
    )
}

// ── Assign Guru ke Lokasi ─────────────────────────────────────────────────────
const showAssign = ref(false)
const assignTarget = ref(null)
const assignForm = reactive({
    tenaga_pendidik_id: '', konteks: 'default',
    berlaku_mulai: '', berlaku_selesai: '', keterangan: '',
})

function openAssignGuru(s) {
    assignTarget.value = s
    Object.assign(assignForm, {
        tenaga_pendidik_id: '', konteks: 'default',
        berlaku_mulai: '', berlaku_selesai: '', keterangan: '',
    })
    showAssign.value = true
}

function submitAssign() {
    router.post(
        `/admin/smart-payroll/setting-lokasi/${assignTarget.value.id}/assign-guru`,
        { ...assignForm },
        {
            onSuccess: () => { showAssign.value = false },
            preserveScroll: true,
        }
    )
}

function unassignGuru(s, guruId) {
    confirm.value.ask(
        { title: 'Lepas Guru dari Lokasi?', message: 'Guru ini akan dilepas dari lokasi absensi.',
          variant: 'danger', confirmLabel: 'Ya, Lepas' },
        (done) => router.post(`/admin/smart-payroll/setting-lokasi/${s.id}/unassign-guru`,
            { tenaga_pendidik_id: guruId }, { preserveScroll: true, onFinish: done }),
    )
}

// ── Assign Lokasi ke Guru ─────────────────────────────────────────────────────
const showAssignToGuru = ref(false)
const assignToGuruTarget = ref(null)
const guruLokasiSelected = ref([])

function openAssignLokasiToGuru(g) {
    assignToGuruTarget.value = g
    guruLokasiSelected.value = g.lokasi_assigned?.map(l => l.id) ?? []
    showAssignToGuru.value = true
}

function submitAssignToGuru() {
    const lokasi = guruLokasiSelected.value.map(id => ({
        setting_lokasi_id: id,
        konteks: 'default',
        berlaku_mulai: null, berlaku_selesai: null, keterangan: null,
    }))
    router.post(
        route('admin.smart-payroll.setting-lokasi.assign-to-guru', { guru: assignToGuruTarget.value.id }),
        { lokasi },
        {
            onSuccess: () => { showAssignToGuru.value = false },
            preserveScroll: true,
        }
    )
}

// ── Test Validasi ─────────────────────────────────────────────────────────────
const testForm = reactive({
    tenaga_pendidik_id: '', latitude: null, longitude: null,
    wifi_ssid: '', status: 'hadir',
})
const testResult = ref(null)
const testLoading = ref(false)

async function jalankanTest() {
    testLoading.value = true
    testResult.value = null
    try {
        const res = await fetch(route('admin.smart-payroll.setting-lokasi.test'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
            },
            body: JSON.stringify({ ...testForm }),
        })
        testResult.value = await res.json()
    } catch (e) {
        testResult.value = { valid: false, pesan: 'Error: ' + e.message, tipe_validasi: 'error' }
    } finally {
        testLoading.value = false
    }
}

// ── Helper ────────────────────────────────────────────────────────────────────
function fieldCls(e) {
    const b = 'w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 transition-all bg-white'
    return e
        ? `${b} border-red-300 focus:ring-red-100`
        : `${b} border-gray-200 focus:border-indigo-500 focus:ring-indigo-100`
}
</script>
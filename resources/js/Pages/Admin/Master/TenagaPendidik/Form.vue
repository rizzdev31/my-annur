<template>
    <AdminLayout :title="isEdit ? 'Edit Tenaga Pendidik' : 'Tambah Tenaga Pendidik'" subtitle="Master Data">

        <Head :title="isEdit ? 'Edit Tenaga Pendidik' : 'Tambah Tenaga Pendidik'" />

        <!-- Header -->
        <div class="flex items-center gap-4 mb-6">
            <Link :href="route('admin.master.tenaga-pendidik.index')"
                class="p-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div>
                <h2 class="text-xl font-semibold text-gray-900">
                    {{ isEdit ? 'Edit Tenaga Pendidik' : 'Tambah Tenaga Pendidik' }}
                </h2>
                <p class="text-sm text-gray-400 mt-0.5">
                    {{ isEdit ? 'Perbarui data ' + form.name : 'Isi data lengkap tenaga pendidik baru' }}
                </p>
            </div>
        </div>

        <form @submit.prevent="submit" enctype="multipart/form-data">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

                <!-- ── Kolom Kiri: Foto + Info Akun ──────────────────────── -->
                <div class="space-y-4">

                    <!-- Foto Profil -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h3 class="text-sm font-semibold text-gray-800 mb-4">Foto Profil</h3>

                        <!-- Preview foto -->
                        <div class="flex flex-col items-center gap-4">
                            <div class="relative">
                                <img v-if="fotoPreview" :src="fotoPreview"
                                    class="w-28 h-28 rounded-2xl object-cover ring-4 ring-indigo-50" />
                                <div v-else
                                    class="w-28 h-28 rounded-2xl bg-gradient-to-br from-indigo-100 to-violet-100 flex items-center justify-center ring-4 ring-indigo-50">
                                    <svg class="w-12 h-12 text-indigo-300" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <!-- Upload button overlay -->
                                <button type="button" @click="$refs.fotoInput.click()"
                                    class="absolute -bottom-2 -right-2 w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center shadow-md hover:bg-indigo-700 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <circle cx="12" cy="13" r="3" />
                                    </svg>
                                </button>
                            </div>

                            <input ref="fotoInput" type="file" accept="image/*" class="hidden" @change="onFotoChange" />

                            <div class="text-center">
                                <button type="button" @click="$refs.fotoInput.click()"
                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-700">
                                    {{ fotoPreview ? 'Ganti Foto' : 'Upload Foto' }}
                                </button>
                                <p class="text-xs text-gray-400 mt-0.5">JPG, PNG · Maks 2MB</p>
                            </div>

                            <p v-if="fotoPreview" @click="removeFoto"
                                class="text-xs text-red-400 cursor-pointer hover:text-red-500">
                                Hapus foto
                            </p>
                        </div>
                        <FieldError :error="form.errors.foto" />
                    </div>

                    <!-- Info Akun -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h3 class="text-sm font-semibold text-gray-800 mb-4">Akun Login</h3>
                        <div class="space-y-3.5">

                            <FormField label="Username" required :error="form.errors.username">
                                <input v-model="form.username" type="text" placeholder="cth: ahmad.fauzi"
                                    :class="inputClass(form.errors.username)" />
                                <p class="text-xs text-gray-400 mt-1">Huruf, angka, strip, underscore</p>
                            </FormField>

                            <FormField label="Email" required :error="form.errors.email">
                                <input v-model="form.email" type="email" placeholder="email@annur.sch.id"
                                    :class="inputClass(form.errors.email)" />
                            </FormField>

                            <FormField :label="isEdit ? 'Password Baru' : 'Password'" :required="!isEdit"
                                :error="form.errors.password">
                                <div class="relative">
                                    <input v-model="form.password" :type="showPass ? 'text' : 'password'"
                                        :placeholder="isEdit ? 'Kosongkan jika tidak diubah' : 'Min. 8 karakter'"
                                        :class="inputClass(form.errors.password)" />
                                    <button type="button" @click="showPass = !showPass"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path v-if="!showPass" stroke-linecap="round" stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                        </svg>
                                    </button>
                                </div>
                            </FormField>

                            <FormField v-if="form.password" label="Konfirmasi Password"
                                :error="form.errors.password_confirmation">
                                <input v-model="form.password_confirmation" :type="showPass ? 'text' : 'password'"
                                    placeholder="Ulangi password"
                                    :class="inputClass(form.errors.password_confirmation)" />
                            </FormField>

                        </div>
                    </div>
                </div>

                <!-- ── Kolom Kanan: Data Profil ───────────────────────────── -->
                <div class="xl:col-span-2 space-y-4">

                    <!-- Data Pribadi -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h3 class="text-sm font-semibold text-gray-800 mb-4">Data Pribadi</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                            <FormField label="Nama Lengkap" required :error="form.errors.name" class="sm:col-span-2">
                                <input v-model="form.name" type="text" placeholder="Nama lengkap sesuai KTP"
                                    :class="inputClass(form.errors.name)" />
                            </FormField>

                            <FormField label="NIP" required :error="form.errors.nip">
                                <input v-model="form.nip" type="text" placeholder="cth: TP-2024-001"
                                    :class="inputClass(form.errors.nip)" />
                            </FormField>

                            <FormField label="NIK (KTP)" :error="form.errors.nik">
                                <input v-model="form.nik" type="text" maxlength="16" placeholder="16 digit NIK"
                                    :class="inputClass(form.errors.nik)" />
                            </FormField>

                            <FormField label="Jenis Kelamin" required :error="form.errors.jenis_kelamin">
                                <select v-model="form.jenis_kelamin" :class="inputClass(form.errors.jenis_kelamin)">
                                    <option value="">-- Pilih --</option>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </FormField>

                            <FormField label="Nomor HP" :error="form.errors.no_hp">
                                <input v-model="form.no_hp" type="tel" placeholder="cth: 0812xxxxxxxx"
                                    :class="inputClass(form.errors.no_hp)" />
                            </FormField>

                            <FormField label="Tempat Lahir" :error="form.errors.tempat_lahir">
                                <input v-model="form.tempat_lahir" type="text" placeholder="Kota tempat lahir"
                                    :class="inputClass(form.errors.tempat_lahir)" />
                            </FormField>

                            <FormField label="Tanggal Lahir" :error="form.errors.tanggal_lahir">
                                <input v-model="form.tanggal_lahir" type="date"
                                    :class="inputClass(form.errors.tanggal_lahir)" />
                            </FormField>

                            <FormField label="Pendidikan Terakhir" :error="form.errors.pendidikan_terakhir">
                                <select v-model="form.pendidikan_terakhir"
                                    :class="inputClass(form.errors.pendidikan_terakhir)">
                                    <option value="">-- Pilih --</option>
                                    <option value="SMA">SMA/MA/SMK</option>
                                    <option value="D3">D3</option>
                                    <option value="S1">S1</option>
                                    <option value="S2">S2</option>
                                    <option value="S3">S3</option>
                                    <option value="Pesantren">Pesantren</option>
                                </select>
                            </FormField>

                            <FormField label="Jurusan/Prodi" :error="form.errors.jurusan">
                                <input v-model="form.jurusan" type="text" placeholder="cth: Pendidikan Agama Islam"
                                    :class="inputClass(form.errors.jurusan)" />
                            </FormField>

                            <FormField label="Alamat Lengkap" :error="form.errors.alamat" class="sm:col-span-2">
                                <textarea v-model="form.alamat" rows="2" placeholder="Alamat tempat tinggal"
                                    :class="inputClass(form.errors.alamat) + ' resize-none'"></textarea>
                            </FormField>

                        </div>
                    </div>

                    <!-- Data Kepegawaian -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h3 class="text-sm font-semibold text-gray-800 mb-4">Data Kepegawaian</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                            <FormField label="Jabatan" required :error="form.errors.jabatan_id">
                                <select v-model="form.jabatan_id" :class="inputClass(form.errors.jabatan_id)">
                                    <option value="">-- Pilih Jabatan --</option>
                                    <optgroup v-for="tipe in jabatanByTipe" :key="tipe.label" :label="tipe.label">
                                        <option v-for="j in tipe.items" :key="j.id" :value="j.id">
                                            {{ j.nama_jabatan }} ({{ j.kode_jabatan }})
                                        </option>
                                    </optgroup>
                                </select>
                            </FormField>

                            <FormField label="Jenis Guru" required :error="form.errors.jenis_guru">
                                <select v-model="form.jenis_guru" :class="inputClass(form.errors.jenis_guru)">
                                    <option value="">-- Pilih --</option>
                                    <option value="mukim">Mukim (tinggal di pesantren)</option>
                                    <option value="non_mukim">Non Mukim (dari luar)</option>
                                </select>
                            </FormField>

                            <FormField label="Tanggal Masuk" required :error="form.errors.tanggal_masuk">
                                <input v-model="form.tanggal_masuk" type="date"
                                    :class="inputClass(form.errors.tanggal_masuk)" />
                            </FormField>

                            <FormField label="Setting Jam Kerja" :error="form.errors.setting_jam_kerja_id">
                                <select v-model="form.setting_jam_kerja_id"
                                    :class="inputClass(form.errors.setting_jam_kerja_id)">
                                    <option value="">-- Gunakan Default --</option>
                                    <option v-for="j in jamKerja" :key="j.id" :value="j.id">
                                        {{ j.nama }} ({{ j.jam_masuk }} - {{ j.jam_pulang }})
                                    </option>
                                </select>
                                <p class="text-xs text-gray-400 mt-1">Kosongkan untuk pakai jam kerja default</p>
                            </FormField>

                            <FormField label="Libur Individu (Guru Mukim Shift)" :error="form.errors.is_mukim">
                                <label class="flex items-start gap-2.5 cursor-pointer px-3 py-2.5 rounded-xl border border-gray-200 hover:border-indigo-300 transition-colors">
                                    <input v-model="form.is_mukim" type="checkbox"
                                        class="mt-0.5 w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    <span class="text-xs text-gray-600 leading-snug">
                                        Aktifkan bila guru ini bekerja shift &amp; liburnya <b>rolling per tanggal</b>
                                        (mis. satpam asrama). Mengaktifkan menu "Libur Individu" untuknya.
                                    </span>
                                </label>
                            </FormField>

                            <FormField label="Hari Libur Mingguan" :error="form.errors.hari_libur">
                                <div class="flex flex-wrap gap-2">
                                    <label v-for="h in hariOpsi" :key="h.key"
                                        class="flex items-center gap-1.5 px-3 py-2 rounded-xl border cursor-pointer transition-colors"
                                        :class="form.hari_libur.includes(h.key) ? 'border-amber-400 bg-amber-50 text-amber-700' : 'border-gray-200 text-gray-600 hover:border-amber-300'">
                                        <input type="checkbox" :value="h.key" v-model="form.hari_libur" class="w-4 h-4 rounded text-amber-600" />
                                        <span class="text-xs font-medium">{{ h.label }}</span>
                                    </label>
                                </div>
                                <p class="text-xs text-gray-400 mt-1.5">Hari libur tetap mingguan (mis. Sabtu). Dipakai saat Generate Jam Kerja "Dari Hari Libur Guru". Kosongkan bila tidak ada.</p>
                            </FormField>

                        </div>
                    </div>

                    <!-- Data Rekening -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <h3 class="text-sm font-semibold text-gray-800 mb-1">Data Rekening</h3>
                        <p class="text-xs text-gray-400 mb-4">Untuk transfer gaji bulanan</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                            <FormField label="Nama Bank" :error="form.errors.nama_bank">
                                <select v-model="form.nama_bank" :class="inputClass(form.errors.nama_bank)">
                                    <option value="">-- Pilih Bank --</option>
                                    <option>BSI</option>
                                    <option>BRI</option>
                                    <option>BNI</option>
                                    <option>BCA</option>
                                    <option>Mandiri</option>
                                    <option>BTN</option>
                                    <option>Muamalat</option>
                                </select>
                            </FormField>

                            <FormField label="No. Rekening" :error="form.errors.no_rekening">
                                <input v-model="form.no_rekening" type="text" placeholder="Nomor rekening"
                                    :class="inputClass(form.errors.no_rekening)" />
                            </FormField>

                            <FormField label="Nama Pemilik Rekening" :error="form.errors.nama_rekening">
                                <input v-model="form.nama_rekening" type="text" placeholder="Sesuai buku tabungan"
                                    :class="inputClass(form.errors.nama_rekening)" />
                            </FormField>

                        </div>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="flex items-center justify-end gap-3">
                        <Link :href="route('admin.master.tenaga-pendidik.index')"
                            class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                            Batal
                        </Link>
                        <button type="submit" :disabled="form.processing"
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm shadow-indigo-200 disabled:opacity-60">
                            <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4" />
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Simpan Data') }}
                        </button>
                    </div>

                </div>
            </div>
        </form>

    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FormField from '@/Components/FormField.vue'
import FieldError from '@/Components/FieldError.vue'

const props = defineProps({
    guru: { type: Object, default: null },
    jabatan: { type: Array, default: () => [] },
    jamKerja: { type: Array, default: () => [] },
})

const isEdit = computed(() => !!props.guru)
const showPass = ref(false)
const fotoPreview = ref(props.guru?.foto ?? null)

// Inisialisasi form
const hariOpsi = [
    { key: 'senin', label: 'Sen' }, { key: 'selasa', label: 'Sel' }, { key: 'rabu', label: 'Rab' },
    { key: 'kamis', label: 'Kam' }, { key: 'jumat', label: 'Jum' }, { key: 'sabtu', label: 'Sab' },
    { key: 'ahad', label: 'Ahd' },
]

const form = useForm({
    // Akun
    name: props.guru?.name ?? '',
    email: props.guru?.email ?? '',
    username: props.guru?.username ?? '',
    password: '',
    password_confirmation: '',
    // Profil
    nip: props.guru?.nip ?? '',
    nik: props.guru?.nik ?? '',
    jabatan_id: props.guru?.jabatan_id ?? '',
    jenis_kelamin: props.guru?.jenis_kelamin ?? '',
    jenis_guru: props.guru?.jenis_guru ?? '',
    tempat_lahir: props.guru?.tempat_lahir ?? '',
    tanggal_lahir: props.guru?.tanggal_lahir ?? '',
    pendidikan_terakhir: props.guru?.pendidikan_terakhir ?? '',
    jurusan: props.guru?.jurusan ?? '',
    no_hp: props.guru?.no_hp ?? '',
    alamat: props.guru?.alamat ?? '',
    tanggal_masuk: props.guru?.tanggal_masuk ?? '',
    setting_jam_kerja_id: props.guru?.setting_jam_kerja_id ?? '',
    is_mukim: props.guru?.is_mukim ?? false,
    hari_libur: props.guru?.hari_libur ?? [],
    // Rekening
    no_rekening: props.guru?.no_rekening ?? '',
    nama_bank: props.guru?.nama_bank ?? '',
    nama_rekening: props.guru?.nama_rekening ?? '',
    // Foto
    foto: null,
    _method: isEdit.value ? 'PUT' : '',
})

// Group jabatan by tipe untuk optgroup
const jabatanByTipe = computed(() => {
    const tipeMap = { struktural: 'Struktural', fungsional: 'Fungsional', mengajar: 'Mengajar' }
    const groups = {}
    props.jabatan.forEach(j => {
        const t = j.tipe ?? 'lainnya'
        if (!groups[t]) groups[t] = { label: tipeMap[t] ?? t, items: [] }
        groups[t].items.push(j)
    })
    return Object.values(groups)
})

// Foto handler
function onFotoChange(e) {
    const file = e.target.files[0]
    if (!file) return
    form.foto = file
    fotoPreview.value = URL.createObjectURL(file)
}

function removeFoto() {
    form.foto = null
    fotoPreview.value = null
    if (document.querySelector('input[type=file]')) {
        document.querySelector('input[type=file]').value = ''
    }
}

// Input class helper
function inputClass(error) {
    const base = 'w-full px-4 py-2.5 rounded-xl border text-sm focus:outline-none focus:ring-2 transition-all bg-white'
    return error
        ? `${base} border-red-300 focus:border-red-500 focus:ring-red-100`
        : `${base} border-gray-200 focus:border-indigo-500 focus:ring-indigo-100`
}

function submit() {
    if (isEdit.value) {
        form.post(route('admin.master.tenaga-pendidik.update', props.guru.id), {
            forceFormData: true,
        })
    } else {
        form.post(route('admin.master.tenaga-pendidik.store'), {
            forceFormData: true,
        })
    }
}
</script>
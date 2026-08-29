<script setup>
import { RouterLink } from 'vue-router'
import PageHeader from '../components/PageHeader.vue'

const items = [
    { to: { name: 'laporan-pembelajaran' }, label: 'Laporan Pembelajaran', desc: 'Jurnal mengajar & kehadiran santri per kelas', bg: 'bg-sky-50', fg: 'text-sky-600',
      icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253' },
    { to: { name: 'laporan-pencapaian', params: { jenis: 'tahfidz' } }, label: 'Pencapaian Tahfidz', desc: 'Hafalan, juz, & nilai setoran per santri', bg: 'bg-emerald-50', fg: 'text-emerald-600',
      icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { to: { name: 'laporan-pencapaian', params: { jenis: 'tahsin' } }, label: 'Pencapaian Tahsin', desc: 'Level, materi lulus, & nilai per santri', bg: 'bg-violet-50', fg: 'text-violet-600',
      icon: 'M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z' },
]
</script>

<template>
    <div>
        <PageHeader title="Laporan" />
        <p class="text-xs text-gray-400 mb-4">Pilih jenis laporan yang ingin dilihat.</p>

        <ul class="space-y-3">
            <RouterLink v-for="it in items" :key="it.label" :to="it.to"
                class="block rounded-2xl bg-white border border-gray-100 p-4 active:scale-[0.99] transition">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl grid place-items-center shrink-0" :class="it.bg">
                        <svg class="w-6 h-6" :class="it.fg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" :d="it.icon"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-800">{{ it.label }}</p>
                        <p class="text-[11px] text-gray-400">{{ it.desc }}</p>
                    </div>
                    <svg class="w-5 h-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </div>
            </RouterLink>
        </ul>
    </div>
</template>

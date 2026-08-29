<script setup>
import { computed } from 'vue'
const props = defineProps({ kop: { type: Object, required: true } })
const inisial = computed(() =>
    (props.kop?.brand || 'AN').replace(/[^A-Za-z]/g, '').slice(0, 2).toUpperCase())

// Ukuran logo per-kop: MA eMAS (rasio sangat lebar) dibuat lebih kecil.
const logoCls = computed(() => props.kop?.key === 'ma'
    ? 'max-h-[34px] sm:max-h-[40px] max-w-[130px] sm:max-w-[160px]'
    : 'max-h-[50px] sm:max-h-[58px] max-w-[190px] sm:max-w-[240px]')
</script>

<template>
    <div class="flex items-center justify-between gap-4 pb-3 border-b-[3px] border-[#2E3160]">
        <!-- Logo lockup (kiri) — batas per-kop (MA eMAS lebih kecil karena rasionya lebih lebar) -->
        <img v-if="kop.logo" :src="kop.logo" :alt="kop.brand" :class="['w-auto h-auto object-contain object-left', logoCls]" />
        <!-- Fallback sementara bila file logo belum ditaruh di public/img/kop -->
        <div v-else class="flex items-center gap-2.5 text-[#2E3160]">
            <span
                class="w-14 h-14 rounded-xl bg-[#2E3160] grid place-items-center text-white font-extrabold text-lg shrink-0">{{
                inisial }}</span>
            <span class="text-xl sm:text-2xl font-extrabold">{{ kop.brand }}</span>
        </div>

        <!-- Badge akreditasi / mitra (kanan) -->
        <div v-if="kop.badges?.length" class="shrink-0 flex items-center gap-1.5 sm:gap-2.5">
            <img v-for="(b, i) in kop.badges" :key="i" :src="b" alt="" class="h-9 sm:h-12 w-auto object-contain" />
        </div>
    </div>
</template>

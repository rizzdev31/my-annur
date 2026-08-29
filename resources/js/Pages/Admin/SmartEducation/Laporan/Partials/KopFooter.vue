<script setup>
defineProps({ kop: { type: Object, required: true } })
</script>

<template>
    <div class="bg-[#2E3160] text-white text-center px-4 py-2.5 rounded-lg print:rounded-none">
        <p v-if="kop.nss || kop.npsn" class="text-[11px] font-semibold tracking-wide">
            <span v-if="kop.nss">NSS : {{ kop.nss }}</span>
            <span v-if="kop.nss && kop.npsn" class="text-white/40"> | </span>
            <span v-if="kop.npsn">NPSN : {{ kop.npsn }}</span>
        </p>
        <p class="text-[11px] text-white/90 leading-snug" :class="{ 'mt-0.5': kop.nss || kop.npsn }">
            {{ kop.alamat }}<span v-if="kop.telp"> Tlp {{ kop.telp }}</span>
        </p>
    </div>
</template>

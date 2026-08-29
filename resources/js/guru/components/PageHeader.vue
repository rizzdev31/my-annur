<script setup>
import { useRouter } from 'vue-router'
defineProps({ title: String })
const router = useRouter()
</script>

<template>
    <div class="flex items-center gap-3 mb-4">
        <button @click="router.back()"
            class="w-9 h-9 rounded-xl bg-white border border-gray-100 grid place-items-center text-gray-500 active:scale-95 transition">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </button>
        <h1 class="text-lg font-extrabold text-gray-900">{{ title }}</h1>
    </div>
</template>

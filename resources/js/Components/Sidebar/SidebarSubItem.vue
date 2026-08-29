<template>
    <Link :href="href" :class="[
        'flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm transition-all duration-150',
        isActive
            ? 'text-indigo-700 font-medium bg-indigo-50/60'
            : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100'
    ]">
        <span :class="['w-1.5 h-1.5 rounded-full shrink-0', isActive ? 'bg-indigo-500' : 'bg-gray-300']"></span>
        {{ label }}
    </Link>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
    href: { type: String, required: true },
    label: { type: String, required: true },
})

const page = usePage()
// Cocok tepat pada path (abaikan query string) atau sebagai segmen induk —
// hindari over-match antar menu berprefix sama (mis. /absensi vs /absensi-mengajar).
const isActive = computed(() => {
    const path = page.url.split('?')[0].replace(/\/$/, '')
    const href = props.href.replace(/\/$/, '')
    return path === href || path.startsWith(href + '/')
})
</script>
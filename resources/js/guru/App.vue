<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import Layout from './Layout.vue'
import ToastHost from './components/ToastHost.vue'

const route = useRoute()
const useShell = computed(() => !route.meta.guest && !route.meta.bare)
</script>

<template>
    <Layout v-if="useShell"><router-view /></Layout>
    <router-view v-else />
    <ToastHost />
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import Layout from './Layout.vue'
import ToastHost from './components/ToastHost.vue'
import { auth } from './store/auth'

const route = useRoute()
const useShell = computed(() => !route.meta.guest && !route.meta.bare)

onMounted(() => { if (auth.isLoggedIn && !auth.santri) auth.fetchMe() })
</script>

<template>
    <Layout v-if="useShell"><router-view /></Layout>
    <router-view v-else />
    <ToastHost />
</template>

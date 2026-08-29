import { reactive } from 'vue'

// State UI ringan yang dibagi antar komponen (mis. buka/tutup drawer).
export const ui = reactive({
    drawer: false,
})

import { reactive } from 'vue'

export const toastState = reactive({ items: [] })
let id = 0

function push(text, type = 'info', ms = 3000) {
    const t = { id: ++id, text, type }
    toastState.items.push(t)
    setTimeout(() => { const i = toastState.items.findIndex((x) => x.id === t.id); if (i >= 0) toastState.items.splice(i, 1) }, ms)
}
export const toast = Object.assign((t, type, ms) => push(t, type, ms), {
    success: (t, ms) => push(t, 'success', ms),
    error: (t, ms) => push(t, 'error', ms),
    info: (t, ms) => push(t, 'info', ms),
    warning: (t, ms) => push(t, 'warning', ms),
})

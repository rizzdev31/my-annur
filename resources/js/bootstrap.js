import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// CSRF: sertakan token dari meta agar POST/PUT/DELETE via window.axios ke route
// web tidak kena 419 (mis. Setujui pengajuan libur, Generate jam kerja).
const csrf = document.head.querySelector('meta[name="csrf-token"]');
if (csrf) window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.content;

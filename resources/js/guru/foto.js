// Kompres foto sisi-klien sebelum upload.
// Guru sering ambil dari kamera (4–8 MB) → sering kena limit server (max 3072 KB) &
// membebani penyimpanan. Helper ini me-resize ke sisi terpanjang `maksSisi` dan
// re-encode JPEG kualitas `kualitas`, sehingga hasil biasanya ±200–500 KB.
//
// Aman & non-blok: kalau file bukan gambar, gagal di-decode, atau hasil kompres
// justru lebih besar, file ASLI dikembalikan apa adanya (biarkan validasi server
// yang menolak bila memang terlalu besar).

const DEFAULT = {
    maksSisi: 1600,     // px — cukup tajam untuk bukti foto, hemat ukuran
    kualitas: 0.72,     // 0..1 kualitas JPEG
    maksByte: 1.6 * 1024 * 1024, // ceiling ~1.6 MB — aman di bawah limit terkecil server (2048 KB)
}

function bacaGambar(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file)
        const img = new Image()
        img.onload = () => { URL.revokeObjectURL(url); resolve(img) }
        img.onerror = (e) => { URL.revokeObjectURL(url); reject(e) }
        img.src = url
    })
}

/**
 * @param {File} file
 * @param {{maksSisi?:number, kualitas?:number, maksByte?:number}} [opts]
 * @returns {Promise<File>} file JPEG terkompres (atau file asli bila tak perlu/tak bisa)
 */
export async function kompresFoto(file, opts = {}) {
    const o = { ...DEFAULT, ...opts }
    try {
        if (!file || !file.type?.startsWith('image/')) return file
        // GIF animasi & SVG jangan disentuh (canvas merusak animasi/vektor).
        if (file.type === 'image/gif' || file.type === 'image/svg+xml') return file

        const img = await bacaGambar(file)
        let { width: w, height: h } = img
        if (!w || !h) return file

        // Skala agar sisi terpanjang = maksSisi (tak memperbesar bila sudah kecil).
        const skala = Math.min(1, o.maksSisi / Math.max(w, h))
        const tw = Math.round(w * skala)
        const th = Math.round(h * skala)

        const canvas = document.createElement('canvas')
        canvas.width = tw
        canvas.height = th
        const ctx = canvas.getContext('2d')
        ctx.drawImage(img, 0, 0, tw, th)

        // Encode; turunkan kualitas bertahap bila masih di atas target byte.
        let q = o.kualitas
        let blob = await encode(canvas, q)
        while (blob && blob.size > o.maksByte && q > 0.4) {
            q -= 0.12
            blob = await encode(canvas, q)
        }
        if (!blob) return file

        // Kalau hasil malah lebih besar dari asli (mis. foto sudah teroptimasi), pakai asli.
        if (blob.size >= file.size && skala === 1) return file

        const nama = (file.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg'
        return new File([blob], nama, { type: 'image/jpeg', lastModified: Date.now() })
    } catch (_) {
        return file // apa pun yang gagal → jangan hambat guru, pakai file asli
    }
}

function encode(canvas, q) {
    return new Promise((resolve) => {
        if (canvas.toBlob) canvas.toBlob((b) => resolve(b), 'image/jpeg', q)
        else resolve(null)
    })
}

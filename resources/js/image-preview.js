// Pratinjau gambar lokal sebelum unggah. Hanya saran: validasi final dilakukan server (00 K).
// Markup: [data-image-field] berisi input[data-image-input][data-max-kb], img[data-image-preview], [data-image-warning].

const DEFAULT_MAX_KB = 2048;

function formatSize(kb) {
    return kb >= 1024 ? `${(kb / 1024).toFixed(1).replace('.', ',')} MB` : `${Math.round(kb)} KB`;
}

document.querySelectorAll('[data-image-input]').forEach((input) => {
    const field = input.closest('[data-image-field]') || input.parentElement;
    const preview = field.querySelector('[data-image-preview]');
    const warning = field.querySelector('[data-image-warning]');
    const maxKb = Number.parseInt(input.dataset.maxKb, 10) || DEFAULT_MAX_KB;
    let objectUrl = null;

    const clear = () => {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }

        if (preview) {
            preview.classList.add('d-none');
            preview.removeAttribute('src');
        }

        if (warning) {
            warning.classList.add('d-none');
            warning.textContent = '';
        }
    };

    input.addEventListener('change', () => {
        clear();

        const files = Array.from(input.files || []);

        if (files.length === 0) {
            return;
        }

        const first = files[0];

        if (preview && first.type.startsWith('image/')) {
            objectUrl = URL.createObjectURL(first);
            preview.src = objectUrl;
            preview.classList.remove('d-none');
        }

        const oversized = files.filter((file) => file.size / 1024 > maxKb);

        if (warning && oversized.length > 0) {
            const names = oversized.map((file) => file.name).join(', ');
            warning.textContent = `Ukuran berkas melebihi ${formatSize(maxKb)} (${names}). Server akan menolaknya; pilih gambar yang lebih kecil.`;
            warning.classList.remove('d-none');
        }
    });
});

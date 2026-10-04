import * as bootstrap from 'bootstrap';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '@fontsource/plus-jakarta-sans/400.css';
import '@fontsource/plus-jakarta-sans/500.css';
import '@fontsource/plus-jakarta-sans/600.css';
import '@fontsource/plus-jakarta-sans/700.css';

window.bootstrap = bootstrap;

// ---------------------------------------------------------------- Tombol memuat (x-loading-button)
// Markup: <button data-loading-button data-loading-text="Memproses…"> berisi
// [data-loading-spinner] (kelas d-none), opsional [data-loading-icon], dan [data-loading-label].
function setLoading(button) {
    if (button.dataset.loadingActive === '1') {
        return;
    }

    button.dataset.loadingActive = '1';

    const label = button.querySelector('[data-loading-label]');
    const spinner = button.querySelector('[data-loading-spinner]');
    const icon = button.querySelector('[data-loading-icon]');

    if (label) {
        button.dataset.originalLabel = label.textContent;
        label.textContent = button.dataset.loadingText || 'Memproses…';
    }

    if (spinner) {
        spinner.classList.remove('d-none');
    }

    if (icon) {
        icon.classList.add('d-none');
    }

    button.setAttribute('aria-busy', 'true');
    button.disabled = true;
}

function resetLoading(button) {
    if (button.dataset.loadingActive !== '1') {
        return;
    }

    delete button.dataset.loadingActive;

    const label = button.querySelector('[data-loading-label]');
    const spinner = button.querySelector('[data-loading-spinner]');
    const icon = button.querySelector('[data-loading-icon]');

    if (label && button.dataset.originalLabel !== undefined) {
        label.textContent = button.dataset.originalLabel;
    }

    if (spinner) {
        spinner.classList.add('d-none');
    }

    if (icon) {
        icon.classList.remove('d-none');
    }

    button.removeAttribute('aria-busy');
    button.disabled = false;
}

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (event.defaultPrevented || !(form instanceof HTMLFormElement)) {
        return;
    }

    // Formulir yang tidak memuat ulang halaman (unduhan, tab baru) tidak boleh mengunci tombol.
    if (form.hasAttribute('data-no-loading') || (form.target && form.target !== '_self')) {
        return;
    }

    const buttons = form.querySelectorAll('[data-loading-button]');

    if (buttons.length === 0) {
        return;
    }

    // Ditunda satu tick: tombol pengirim yang langsung dinonaktifkan tidak ikut terkirim oleh browser.
    window.setTimeout(() => {
        buttons.forEach(setLoading);
        form.setAttribute('aria-busy', 'true');
    }, 0);
});

// Tombol Back/Forward memulihkan halaman dari cache: kembalikan semua status memuat.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-loading-button]').forEach(resetLoading);
    document.querySelectorAll('form[aria-busy]').forEach((form) => form.removeAttribute('aria-busy'));
    document.querySelectorAll('[data-table][aria-busy]').forEach((table) => table.removeAttribute('aria-busy'));
});

// ---------------------------------------------------------------- Fallback gambar (x-image)
// Satu listener terdelegasi pada fase capture (00 K); event error pada <img> tidak bubbling.
function applyImageFallback(img) {
    const fallback = img.dataset.fallback;

    if (!fallback || img.dataset.fallbackApplied === '1') {
        return;
    }

    img.dataset.fallbackApplied = '1';
    img.removeAttribute('srcset');
    img.src = fallback;
}

function handleImageError(event) {
    const target = event.target;

    if (target instanceof HTMLImageElement && target.matches('img[data-fallback]')) {
        applyImageFallback(target);
    }
}

document.addEventListener('error', handleImageError, true);

// Gambar yang sudah gagal sebelum skrip ini aktif tidak akan memicu error lagi: periksa ulang.
function recheckBrokenImages() {
    document.querySelectorAll('img[data-fallback]').forEach((img) => {
        if (img.complete && img.naturalWidth === 0) {
            applyImageFallback(img);
        }
    });
}

// ---------------------------------------------------------------- Ringkasan galat formulir
function focusErrorsSummary() {
    const summary = document.querySelector('[data-errors-summary]');

    if (summary) {
        summary.focus();
    }
}

// ---------------------------------------------------------------- Penghitung karakter (x-form.textarea)
function initCharCounters() {
    document.querySelectorAll('[data-char-counter]').forEach((counter) => {
        const input = document.getElementById(counter.dataset.counterFor);
        const max = Number.parseInt(counter.dataset.counterMax, 10);

        if (!input || Number.isNaN(max)) {
            return;
        }

        const announce = counter.parentElement ? counter.parentElement.querySelector('[data-counter-announce]') : null;
        let lastMessage = '';

        const update = () => {
            const used = input.value.length;
            const remaining = max - used;

            counter.textContent = `${used} / ${max}`;

            if (!announce) {
                return;
            }

            // Pembaca layar hanya diberi tahu saat mendekati batas (10% terakhir), bukan tiap ketikan.
            let message = '';

            if (remaining <= 0) {
                message = 'Batas karakter tercapai.';
            } else if (remaining <= Math.ceil(max * 0.1)) {
                message = `Sisa ${remaining} karakter.`;
            }

            if (message !== lastMessage) {
                announce.textContent = message;
                lastMessage = message;
            }
        };

        input.addEventListener('input', update);
        update();
    });
}

// ---------------------------------------------------------------- aria-expanded pada pemicu offcanvas
function initOffcanvasToggles() {
    document.querySelectorAll('.offcanvas').forEach((panel) => {
        if (!panel.id) {
            return;
        }

        const triggers = document.querySelectorAll(`[data-bs-toggle="offcanvas"][data-bs-target="#${CSS.escape(panel.id)}"]`);

        panel.addEventListener('show.bs.offcanvas', () => {
            triggers.forEach((trigger) => trigger.setAttribute('aria-expanded', 'true'));
        });

        panel.addEventListener('hidden.bs.offcanvas', () => {
            triggers.forEach((trigger) => trigger.setAttribute('aria-expanded', 'false'));
        });
    });
}

// ---------------------------------------------------------------- Galeri layanan (modal + carousel Bootstrap)
// Markup: modal [data-gallery-modal] berisi .carousel; tombol pemicu membawa data-gallery-index.
function initGalleryModals() {
    document.querySelectorAll('[data-gallery-modal]').forEach((modal) => {
        const carousel = modal.querySelector('.carousel');

        if (!carousel) {
            return;
        }

        modal.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            const raw = trigger && trigger.dataset ? trigger.dataset.galleryIndex : undefined;
            const parsed = Number.parseInt(raw ?? '0', 10);
            const index = Number.isNaN(parsed) ? 0 : parsed;

            carousel.querySelectorAll('.carousel-item').forEach((item, position) => {
                item.classList.toggle('active', position === index);
            });
        });

        modal.addEventListener('shown.bs.modal', () => carousel.focus());
    });
}

function init() {
    recheckBrokenImages();
    focusErrorsSummary();
    initCharCounters();
    initOffcanvasToggles();
    initGalleryModals();

    if (document.querySelector('[data-image-input]')) {
        import('./image-preview.js');
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

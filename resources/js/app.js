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

// ---------------------------------------------------------------- Carousel banner beranda (#1, F4A-R17)
// Markup: .carousel[data-banner-carousel] berisi .carousel-inner[data-banner-live], indikator Bootstrap, dan tombol
// [data-banner-toggle] (Jeda/Putar; awalnya hidden karena tanpa JavaScript tombol ini tidak berguna). Berputar tiap
// 6000 ms kecuali prefers-reduced-motion: reduce; berhenti saat kursor mouse berada di atasnya, saat fokus berada di
// dalamnya, dan saat dijeda pengguna. aria-live bernilai off selama berputar dan polite selama berhenti.
function initBannerCarousel() {
    document.querySelectorAll('[data-banner-carousel]').forEach((root) => {
        if (root.querySelectorAll('.carousel-item').length < 2) {
            return;
        }

        const instance = window.bootstrap.Carousel.getOrCreateInstance(root, {
            interval: 6000,
            ride: false,
            pause: false,
            wrap: true,
            touch: true,
            keyboard: true,
        });
        const live = root.querySelector('[data-banner-live]');
        const toggle = root.querySelector('[data-banner-toggle]');
        const toggleLabel = toggle ? toggle.querySelector('[data-banner-toggle-label]') : null;
        const toggleIcon = toggle ? toggle.querySelector('[data-banner-toggle-icon]') : null;

        // Pengguna dengan prefers-reduced-motion mulai dalam keadaan dijeda; tombol Putar tetap dapat memulai putaran.
        let userPaused = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let hovered = false;
        let focused = false;

        const sync = () => {
            const running = !userPaused && !hovered && !focused;

            if (running) {
                instance.cycle();
            } else {
                instance.pause();
            }

            if (live) {
                live.setAttribute('aria-live', running ? 'off' : 'polite');
            }

            if (toggle) {
                toggle.setAttribute('aria-pressed', userPaused ? 'true' : 'false');

                if (toggleLabel) {
                    toggleLabel.textContent = userPaused ? 'Putar' : 'Jeda';
                }

                if (toggleIcon) {
                    toggleIcon.classList.toggle('bi-pause-fill', !userPaused);
                    toggleIcon.classList.toggle('bi-play-fill', userPaused);
                }
            }
        };

        if (toggle) {
            toggle.hidden = false;
            toggle.addEventListener('click', () => {
                userPaused = !userPaused;
                sync();
            });
        }

        // Hanya kursor mouse yang menjeda; sentuhan tidak meninggalkan status "hover" yang menggantung.
        root.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'mouse') {
                hovered = true;
                sync();
            }
        });
        root.addEventListener('pointerleave', (event) => {
            if (event.pointerType === 'mouse') {
                hovered = false;
                sync();
            }
        });
        // Hanya fokus keyboard yang menjeda; fokus sisa klik mouse pada tombol tidak boleh menghentikan putaran.
        root.addEventListener('focusin', (event) => {
            let keyboardFocus = true;

            try {
                keyboardFocus = event.target.matches(':focus-visible');
            } catch (error) {
                keyboardFocus = true; // Peramban lama tanpa :focus-visible: perilaku semula.
            }

            if (keyboardFocus) {
                focused = true;
                sync();
            }
        });
        root.addEventListener('focusout', (event) => {
            if (!root.contains(event.relatedTarget)) {
                focused = false;
                sync();
            }
        });

        sync();
    });
}

// ---------------------------------------------------------------- Peta tersemat (x-map-embed, F4B-R33)
// Markup: [data-map-embed] berisi [data-map-fallback] (kotak pengganti statis: teks status [data-map-fallback-text],
// alamat, tombol Petunjuk Arah) dan, bila ada URL peta, iframe [data-map-frame] yang menumpuk di atasnya. Tanpa
// JavaScript iframe tetap tampil. Dengan JavaScript: bila peramban offline, iframe disembunyikan dan teks pengganti
// diganti; selain itu event load iframe menandai peta termuat, dan bila belum termuat dalam 8000 ms sejak peta
// mendekati viewport (IntersectionObserver; tanpa itu, sejak awal) iframe disembunyikan. Sengaja memakai event load, bukan listener galat baru: hanya ada satu delegasi galat (gambar).
const MAP_LOAD_TIMEOUT = 8000;
const MAP_UNAVAILABLE_TEXT = 'Peta tidak dapat dimuat. Alamat dan tombol Petunjuk Arah tetap dapat dipakai.';

function initMapEmbeds() {
    document.querySelectorAll('[data-map-embed]').forEach((root) => {
        const frame = root.querySelector('[data-map-frame]');

        if (!frame) {
            return;
        }

        const message = root.querySelector('[data-map-fallback-text]');
        const markUnavailable = () => {
            root.classList.add('is-unavailable');

            if (message) {
                message.textContent = MAP_UNAVAILABLE_TEXT;
            }
        };

        if (navigator.onLine === false) {
            markUnavailable();

            return;
        }

        frame.addEventListener('load', () => {
            root.classList.add('is-loaded');
        });

        // Iframe berinfo loading="lazy" baru dimuat mendekati viewport: pewaktu dimulai saat peta hampir terlihat,
        // bukan saat halaman dimuat, supaya peta di bawah lipatan tidak ditandai gagal sebelum sempat dimuat.
        const startTimer = () => {
            window.setTimeout(() => {
                if (!root.classList.contains('is-loaded')) {
                    markUnavailable();
                }
            }, MAP_LOAD_TIMEOUT);
        };

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    observer.disconnect();
                    startTimer();
                }
            }, { rootMargin: '300px' });

            observer.observe(root);
        } else {
            startTimer();
        }
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

        // Instance dibuat sejak awal agar pendengar keyboard (panah kiri/kanan) sudah terpasang sebelum
        // ada klik pada tombol kontrol; tanpa putar otomatis.
        window.bootstrap.Carousel.getOrCreateInstance(carousel, { keyboard: true, ride: false, interval: false });

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

// ---------------------------------------------------------------- Wizard pendaftaran (F6-R17, F6-R21)
// Judul langkah ([data-wizard-heading], tabindex="-1") menerima fokus saat langkah berganti, kecuali bila ringkasan
// galat ([data-errors-summary]) sudah memintanya. Wizard berfungsi penuh tanpa berkas ini.
function focusWizardHeading() {
    if (document.querySelector('[data-errors-summary]')) {
        return;
    }

    const heading = document.querySelector('[data-wizard-heading]');

    if (heading) {
        heading.focus({ preventScroll: true });
    }
}

// Markup: [data-payment-toggle] membungkus radio name="payment_type" dan blok [data-payment-block="bpjs|asuransi"].
// Tanpa JavaScript semua blok tampil dengan teks bantuan; dengan JavaScript hanya blok yang sesuai pilihan yang tampil
// dan isian pada blok yang disembunyikan dinonaktifkan agar tidak terkirim (server menolak BPJS pada pembayaran selain BPJS).
function initPaymentToggles() {
    document.querySelectorAll('[data-payment-toggle]').forEach((root) => {
        const radios = root.querySelectorAll('input[type="radio"][name="payment_type"]');
        const blocks = root.querySelectorAll('[data-payment-block]');

        if (radios.length === 0 || blocks.length === 0) {
            return;
        }

        const sync = () => {
            const chosen = Array.from(radios).find((radio) => radio.checked);
            const value = chosen ? chosen.value : '';

            blocks.forEach((block) => {
                const visible = block.dataset.paymentBlock === value;

                block.hidden = !visible;
                block.querySelectorAll('input, select, textarea').forEach((field) => {
                    field.disabled = !visible;
                });
            });
        };

        radios.forEach((radio) => radio.addEventListener('change', sync));
        sync();
    });
}

function init() {
    recheckBrokenImages();
    focusErrorsSummary();
    focusWizardHeading();
    initPaymentToggles();
    initCharCounters();
    initOffcanvasToggles();
    initGalleryModals();
    initBannerCarousel();
    initMapEmbeds();

    if (document.querySelector('[data-image-input]')) {
        import('./image-preview.js');
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

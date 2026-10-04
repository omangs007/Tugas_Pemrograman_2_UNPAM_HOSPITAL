// Editor rich text (Quill 2) untuk <x-form.rich-text> (10 §3.5, F2-R2). Dimuat lazy oleh admin.js.
// Kontrak markup: [data-rich-text] > textarea[data-rich-text-input], [data-rich-text-editor],
// [data-rich-text-toolbar], [data-rich-text-surface], [data-rich-text-status], dan modal [data-rich-text-image-modal].
// CSP produksi: script-src 'self', img-src 'self' data: (tanpa URL objek lokal). Gambar sisipan selalu memakai
// URL /storage/... dari respons server; FileReader hanya untuk pratinjau di modal (data URL).
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const Delta = Quill.import('delta');

// Himpunan tag yang diizinkan purifier: h2-h4, strong, em, u, ol/ul/li, blockquote, a, img.
const FORMATS = ['header', 'bold', 'italic', 'underline', 'list', 'blockquote', 'link', 'image'];
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const IMAGE_MAX_BYTES = 2048 * 1024;

const TEXT = {
    fileRequired: 'Pilih berkas gambar terlebih dahulu.',
    fileType: 'File harus berupa gambar JPG, PNG, atau WebP.',
    fileSize: 'Ukuran gambar maksimal 2 MB.',
    altRequired: 'Teks alternatif gambar wajib diisi.',
    uploadFailed: 'Gambar gagal diunggah. Periksa koneksi Anda, lalu coba lagi.',
    uploading: 'Mengunggah…',
    submit: 'Unggah dan sisipkan',
    inserted: 'Gambar disisipkan.',
    useButton: 'Untuk menyisipkan gambar, gunakan tombol Sisipkan gambar pada toolbar.',
    linkPlaceholder: 'https://',
    linkLabel: 'Alamat tautan',
};

function tooLong(max) {
    return `Isi editor melebihi batas ${max} karakter. Persingkat isi atau kurangi jumlah gambar.`;
}

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    return meta ? meta.getAttribute('content') || '' : '';
}

function isStoragePath(source) {
    return typeof source === 'string' && source.startsWith('/storage/');
}

function firstServerMessage(payload) {
    if (payload && payload.errors && typeof payload.errors === 'object') {
        for (const messages of Object.values(payload.errors)) {
            if (Array.isArray(messages) && messages.length > 0 && typeof messages[0] === 'string') {
                return messages[0];
            }
        }
    }

    if (payload && typeof payload.message === 'string' && payload.message !== '') {
        return payload.message;
    }

    return TEXT.uploadFailed;
}

// POST multipart ke admin.media.editor-upload; respons sukses {url, alt, media_id}.
async function uploadImage(url, file, altText) {
    const body = new FormData();
    body.append('file', file);
    body.append('alt_text', altText);

    let response;

    try {
        response = await fetch(url, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
    } catch {
        throw new Error(TEXT.uploadFailed);
    }

    let payload = null;

    try {
        payload = await response.json();
    } catch {
        payload = null;
    }

    if (!response.ok) {
        throw new Error(firstServerMessage(payload));
    }

    if (!payload || !isStoragePath(payload.url)) {
        throw new Error(TEXT.uploadFailed);
    }

    return payload;
}

function validateFile(file) {
    if (!IMAGE_TYPES.includes(file.type)) {
        return TEXT.fileType;
    }

    if (file.size > IMAGE_MAX_BYTES) {
        return TEXT.fileSize;
    }

    return null;
}

// Dialog unggah gambar (modal Bootstrap). Mengembalikan open(onInsert, onClosed).
function createImageDialog(modal, uploadUrl) {
    const fileInput = modal.querySelector('[data-rich-text-image-file]');
    const altInput = modal.querySelector('[data-rich-text-image-alt]');
    const preview = modal.querySelector('[data-rich-text-image-preview]');
    const errorBox = modal.querySelector('[data-rich-text-image-error]');
    const submitButton = modal.querySelector('[data-rich-text-image-submit]');
    const instance = window.bootstrap.Modal.getOrCreateInstance(modal);

    let onInsert = null;
    let onClosed = null;
    let readToken = 0;

    const showError = (message, field) => {
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');

        [fileInput, altInput].forEach((input) => input.removeAttribute('aria-invalid'));

        if (field) {
            field.setAttribute('aria-invalid', 'true');
            field.focus();
        }
    };

    const clearError = () => {
        errorBox.textContent = '';
        errorBox.classList.add('d-none');
        [fileInput, altInput].forEach((input) => input.removeAttribute('aria-invalid'));
    };

    const hidePreview = () => {
        readToken += 1;
        preview.classList.add('d-none');
        preview.removeAttribute('src');
    };

    const setBusy = (busy) => {
        submitButton.disabled = busy;
        fileInput.disabled = busy;
        altInput.disabled = busy;
        submitButton.textContent = busy ? TEXT.uploading : TEXT.submit;

        if (busy) {
            submitButton.setAttribute('aria-busy', 'true');
        } else {
            submitButton.removeAttribute('aria-busy');
        }
    };

    fileInput.addEventListener('change', () => {
        clearError();
        hidePreview();

        const file = fileInput.files && fileInput.files[0];

        if (!file) {
            return;
        }

        const problem = validateFile(file);

        if (problem) {
            showError(problem, fileInput);

            return;
        }

        // Pratinjau saja: data URL (diizinkan img-src 'self' data:), bukan URL objek lokal.
        const token = readToken;
        const reader = new FileReader();

        reader.addEventListener('load', () => {
            if (token === readToken && typeof reader.result === 'string') {
                preview.setAttribute('src', reader.result);
                preview.classList.remove('d-none');
            }
        });
        reader.readAsDataURL(file);
    });

    submitButton.addEventListener('click', async () => {
        clearError();

        const file = fileInput.files && fileInput.files[0];

        if (!file) {
            showError(TEXT.fileRequired, fileInput);

            return;
        }

        const problem = validateFile(file);

        if (problem) {
            showError(problem, fileInput);

            return;
        }

        const altText = altInput.value.trim();

        if (altText === '') {
            showError(TEXT.altRequired, altInput);

            return;
        }

        setBusy(true);

        try {
            const payload = await uploadImage(uploadUrl, file, altText);

            if (onInsert) {
                onInsert(payload);
            }

            instance.hide();
        } catch (error) {
            showError(error instanceof Error ? error.message : TEXT.uploadFailed, null);
        } finally {
            setBusy(false);
        }
    });

    altInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            submitButton.click();
        }
    });

    modal.addEventListener('shown.bs.modal', () => fileInput.focus());

    modal.addEventListener('hidden.bs.modal', () => {
        fileInput.value = '';
        altInput.value = '';
        hidePreview();
        clearError();
        setBusy(false);

        const closed = onClosed;

        onInsert = null;
        onClosed = null;

        if (closed) {
            closed();
        }
    });

    return (insertHandler, closedHandler) => {
        onInsert = insertHandler;
        onClosed = closedHandler;
        instance.show();
    };
}

function initRichText(root) {
    if (root.dataset.richTextReady === '1') {
        return;
    }

    const textarea = root.querySelector('[data-rich-text-input]');
    const editor = root.querySelector('[data-rich-text-editor]');
    const toolbar = root.querySelector('[data-rich-text-toolbar]');
    const surface = root.querySelector('[data-rich-text-surface]');
    const status = root.querySelector('[data-rich-text-status]');
    const modal = root.querySelector('[data-rich-text-image-modal]');

    if (!textarea || !editor || !toolbar || !surface || !modal || !window.bootstrap) {
        return; // marka tidak lengkap: textarea biasa tetap berfungsi
    }

    root.dataset.richTextReady = '1';

    const uploadUrl = root.dataset.uploadUrl || '';
    const minHeight = Number.parseInt(root.dataset.minHeight, 10) || 240;
    const maxLength = Number.parseInt(root.dataset.maxLength, 10) || 0;
    const labelId = `${textarea.id}-label`;

    const announce = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    // Modal dipindahkan ke <body> (praktik Bootstrap); kontrolnya tanpa atribut name sehingga tidak ikut terkirim.
    document.body.append(modal);
    const openImageDialog = createImageDialog(modal, uploadUrl);

    textarea.hidden = true;
    editor.hidden = false;

    const quill = new Quill(surface, {
        theme: 'snow',
        formats: FORMATS,
        modules: {
            toolbar: {
                container: toolbar,
                handlers: {
                    // Menggantikan dialog bawaan Quill untuk embed gambar dengan modal alt text.
                    image: () => {
                        const range = quill.getSelection(true);

                        openImageDialog(
                            (payload) => {
                                quill.updateContents(
                                    new Delta().retain(range.index).delete(range.length).insert({ image: payload.url }, { alt: payload.alt }),
                                    'user',
                                );
                                quill.setSelection(range.index + 1, 0, 'silent');
                                announce(TEXT.inserted);
                            },
                            () => quill.focus(),
                        );
                    },
                },
            },
            // Tempel/seret berkas gambar tidak boleh menanam data URL; arahkan ke tombol toolbar.
            uploader: {
                mimetypes: IMAGE_TYPES,
                handler: () => announce(TEXT.useButton),
            },
            clipboard: {
                // Gambar tempelan hanya diterima bila sudah berada di /storage/ (sisanya dibuang sanitizer server).
                matchers: [['img', (node, delta) => (isStoragePath(node.getAttribute('src')) ? delta : new Delta())]],
            },
        },
    });

    quill.root.setAttribute('role', 'textbox');
    quill.root.setAttribute('aria-multiline', 'true');
    quill.root.setAttribute('aria-labelledby', labelId);
    quill.root.style.minHeight = `${minHeight}px`;

    const linkBox = surface.querySelector('.ql-tooltip input[type="text"]');

    if (linkBox) {
        linkBox.setAttribute('placeholder', TEXT.linkPlaceholder);
        linkBox.setAttribute('aria-label', TEXT.linkLabel);
    }

    const initial = textarea.value.trim();

    if (initial !== '') {
        quill.setContents(quill.clipboard.convert({ html: initial }), 'silent');
        quill.history.clear();
    }

    const serialize = () => {
        if (quill.getText().trim() === '' && quill.root.querySelector('img') === null) {
            return '';
        }

        return quill.getSemanticHTML();
    };

    const form = textarea.form;

    if (form) {
        form.addEventListener('submit', (event) => {
            const html = serialize();

            if (maxLength > 0 && html.length > maxLength) {
                event.preventDefault();
                announce(tooLong(maxLength));
                quill.focus();

                return;
            }

            textarea.value = html;
        });
    }

    // Tautan ringkasan galat (#id textarea) tidak dapat memfokuskan textarea tersembunyi.
    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href^="#"]') : null;

        if (link && link.getAttribute('href') === `#${textarea.id}`) {
            event.preventDefault();
            quill.focus();
        }
    });
}

document.querySelectorAll('[data-rich-text]').forEach(initRichText);

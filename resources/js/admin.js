// Entri skrip dashboard admin: perilaku bersama (Bootstrap, font, fallback gambar, tombol memuat) + gaya admin.
import './app.js';
import '../scss/admin.scss';

// ---------------------------------------------------------------- Modal konfirmasi (x-confirm-modal)
// Satu modal melayani banyak baris: tombol pemicu membawa data-confirm-action dan data-confirm-message.
document.querySelectorAll('[data-confirm-modal]').forEach((modal) => {
    const form = modal.querySelector('form[data-confirm-form]');
    const text = modal.querySelector('[data-confirm-text]');

    if (!form) {
        return;
    }

    const defaultAction = form.getAttribute('action');
    const defaultText = text ? text.textContent : '';

    modal.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;

        if (trigger && trigger.dataset.confirmAction) {
            form.setAttribute('action', trigger.dataset.confirmAction);
        }

        if (trigger && trigger.dataset.confirmMessage && text) {
            text.textContent = trigger.dataset.confirmMessage;
        }
    });

    modal.addEventListener('hidden.bs.modal', () => {
        form.setAttribute('action', defaultAction ?? '');

        if (text) {
            text.textContent = defaultText;
        }

        const reason = form.querySelector('textarea[name="reason"]');

        if (reason) {
            reason.value = '';
        }
    });
});

// ---------------------------------------------------------------- Pencarian/filter data-table: status memuat
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (event.defaultPrevented || !(form instanceof HTMLFormElement) || !form.matches('form[role="search"]')) {
        return;
    }

    const table = form.closest('[data-table]');

    if (table) {
        table.setAttribute('aria-busy', 'true');
    }
});

// ---------------------------------------------------------------- Editor rich text (x-form.rich-text), dimuat lazy
if (document.querySelector('[data-rich-text]')) {
    import('./editor.js');
}

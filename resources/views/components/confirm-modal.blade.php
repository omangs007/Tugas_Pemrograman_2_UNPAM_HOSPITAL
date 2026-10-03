@props([
    'id',
    'title',
    'message' => '',
    'confirmLabel' => 'Ya, lanjutkan',
    'cancelLabel' => 'Batal',
    'variant' => 'primary',
    'method' => 'POST',
    'reasonRequired' => false,
    'action' => '#',
])
@php
    $verb = strtoupper((string) $method);
    $verb = in_array($verb, ['POST', 'PATCH', 'DELETE'], true) ? $verb : 'POST';
    $variant = in_array($variant, ['danger', 'primary'], true) ? $variant : 'primary';
@endphp
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}-title" aria-hidden="true" data-confirm-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ $action }}" data-confirm-form>
                @csrf
                @if ($verb !== 'POST')
                    @method($verb)
                @endif
                <div class="modal-header">
                    <h2 class="modal-title h5" id="{{ $id }}-title">{{ $title }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p data-confirm-text>{{ $message }}</p>
                    {{ $slot }}
                    @if ($reasonRequired)
                        <div class="mb-0">
                            <label for="{{ $id }}-reason" class="form-label fw-semibold">Alasan <span class="required-mark" aria-hidden="true">*</span></label>
                            <textarea class="form-control" id="{{ $id }}-reason" name="reason" rows="3" required aria-required="true"></textarea>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ $cancelLabel }}</button>
                    <x-loading-button :variant="$variant">{{ $confirmLabel }}</x-loading-button>
                </div>
            </form>
        </div>
    </div>
</div>

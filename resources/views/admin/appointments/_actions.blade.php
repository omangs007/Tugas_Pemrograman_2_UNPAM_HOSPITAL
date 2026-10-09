{{--
    Tombol aksi status satu pendaftaran (F7A-R18). Dipakai detail ($context = 'detail') dan antrean T10 ($context = 'queue').
    Variabel: $appointment (relasi patientProfile dan clinic termuat), $checks (list<TransitionCheck> dari
    AppointmentStatusService::transitionChecks), $context, $returnTo (path kembali yang sah atau null).
    Konfirmasi, check-in, dan selesai = form POST kecil. Batalkan dan Koreksi memerlukan alasan: pada detail membuka modal
    statis (modal-batal, modal-koreksi); pada antrean menjadi tautan ke detail. Check-in yang ditolak CHECKIN_NOT_OPEN =
    tombol aria-disabled (tetap dalam urutan fokus) dengan teks terlihat "Dibuka HH.MM"; penolakan lain tanpa tombol.
--}}
@php
    $context = ($context ?? 'detail') === 'queue' ? 'queue' : 'detail';
    $returnTo = isset($returnTo) && is_string($returnTo) && $returnTo !== '' ? $returnTo : null;
    $code = $appointment->booking_code;
    $isCorrection = $appointment->status === \App\Enums\AppointmentStatus::CheckedIn;
    $subject = $appointment->queue_label.' '.$appointment->patientProfile->full_name;
    $actionUrl = route('admin.appointments.transition', ['appointment' => $code]);
    $detailUrl = route('admin.appointments.show', array_filter(['appointment' => $code, 'kembali' => $returnTo]));
    $size = $context === 'queue' ? 'btn-sm' : '';
    $needsReason = fn (string $to): bool => $to === 'cancelled' || ($to === 'confirmed' && $isCorrection);
    $styles = [
        'confirmed' => $isCorrection
            ? ['Koreksi Check-in', 'bi-arrow-counterclockwise', 'outline-warning']
            : ['Konfirmasi', 'bi-check-circle', 'primary'],
        'checked_in' => ['Check-in', 'bi-person-check', 'success'],
        'completed' => ['Selesai', 'bi-check2-all', 'success'],
        'cancelled' => ['Batalkan', 'bi-x-circle', 'outline-danger'],
    ];
    $visible = collect($checks)->contains(
        fn ($check): bool => $check->allowed() || $check->errorCode === \App\Exceptions\InvalidTransitionException::CHECKIN_NOT_OPEN,
    );
@endphp
<div class="d-flex flex-wrap align-items-center gap-2" data-appointment-actions>
    @foreach ($checks as $check)
        @php
            $to = $check->to->value;
            [$text, $icon, $variant] = $styles[$to];
        @endphp
        @if ($check->allowed())
            @if ($needsReason($to))
                @if ($context === 'detail')
                    <button type="button" class="btn btn-{{ $variant }} {{ $size }}" data-bs-toggle="modal" data-bs-target="#{{ $to === 'cancelled' ? 'modal-batal' : 'modal-koreksi' }}"><i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $text }}<span class="visually-hidden"> {{ $subject }}</span></button>
                @else
                    <a class="btn btn-{{ $variant }} {{ $size }}" href="{{ $detailUrl }}"><i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $text }}<span class="visually-hidden"> {{ $subject }}, dilakukan di halaman detail</span></a>
                @endif
            @else
                <form method="POST" action="{{ $actionUrl }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="to" value="{{ $to }}">
                    <input type="hidden" name="expected_status" value="{{ $appointment->status->value }}">
                    @if ($returnTo !== null)
                        <input type="hidden" name="kembali" value="{{ $returnTo }}">
                    @endif
                    <x-loading-button :variant="$variant" :icon="$icon" loading-text="Memproses…" class="{{ $size }}">{{ $text }}<span class="visually-hidden"> {{ $subject }}</span></x-loading-button>
                </form>
            @endif
        @elseif ($check->errorCode === \App\Exceptions\InvalidTransitionException::CHECKIN_NOT_OPEN)
            <span class="d-inline-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-secondary {{ $size }}" aria-disabled="true" aria-describedby="checkin-open-{{ $appointment->id }}"><i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $text }}<span class="visually-hidden"> {{ $subject }}</span></button>
                <span class="small text-body-secondary" id="checkin-open-{{ $appointment->id }}">Dibuka {{ $check->opensAt() }}</span>
            </span>
        @endif
    @endforeach
    @unless ($visible)
        <span class="text-body-secondary">@if ($context === 'detail')Tidak ada aksi yang tersedia untuk status ini.@else-@endif</span>
    @endunless
</div>

@if ($context === 'detail')
    @foreach ($checks as $check)
        @if ($check->allowed() && $needsReason($check->to->value))
            @php
                $to = $check->to->value;
                $isCancel = $to === 'cancelled';
                $modalId = $isCancel ? 'modal-batal' : 'modal-koreksi';
            @endphp
            <x-confirm-modal
                :id="$modalId"
                :title="$isCancel ? 'Batalkan pendaftaran '.$code.'?' : 'Koreksi check-in '.$code.'?'"
                :message="$isCancel ? 'Kuota dilepas dan nomor antrean tidak dipakai ulang. Pasien berakun menerima pemberitahuan.' : 'Status kembali menjadi Dikonfirmasi. Koreksi hanya dapat dilakukan pada hari kunjungan.'"
                :confirm-label="$isCancel ? 'Ya, batalkan' : 'Ya, koreksi'"
                :variant="$isCancel ? 'danger' : 'primary'"
                :action="$actionUrl">
                <input type="hidden" name="to" value="{{ $to }}">
                <input type="hidden" name="expected_status" value="{{ $appointment->status->value }}">
                @if ($returnTo !== null)
                    <input type="hidden" name="kembali" value="{{ $returnTo }}">
                @endif
                <div class="mb-0">
                    <label for="{{ $modalId }}-alasan" class="form-label fw-semibold">Alasan <span class="required-mark" aria-hidden="true">*</span></label>
                    <textarea class="form-control" id="{{ $modalId }}-alasan" name="reason" rows="3" minlength="5" maxlength="255" required aria-required="true" aria-describedby="{{ $modalId }}-alasan-help"></textarea>
                    <div class="form-text" id="{{ $modalId }}-alasan-help">Wajib diisi, 5 sampai 255 karakter. Alasan tercatat pada riwayat status.</div>
                </div>
            </x-confirm-modal>
        @endif
    @endforeach
@endif

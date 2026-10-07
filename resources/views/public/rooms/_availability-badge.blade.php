@if ($room->beds_available > 0)
    <span class="badge text-bg-success"><i class="bi bi-check-circle" aria-hidden="true"></i> Tersedia</span>
@else
    <span class="badge text-bg-danger"><i class="bi bi-x-circle" aria-hidden="true"></i> Penuh</span>
@endif

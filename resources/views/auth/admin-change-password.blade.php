<x-layouts.auth
    title="Ganti Password"
    :subtitle="$forced ? 'Anda harus mengganti password sementara sebelum melanjutkan.' : 'Gunakan password yang kuat dan belum pernah Anda pakai di tempat lain.'">
    <form method="POST" action="{{ route('admin.password.update') }}" novalidate>
        @csrf
        @method('PUT')

        <x-form.input name="current_password" label="Password saat ini" type="password" required autocomplete="current-password" />
        <x-form.input name="password" label="Password baru" type="password" required autocomplete="new-password" help="Minimal 8 karakter, berisi huruf dan angka." />
        <x-form.input name="password_confirmation" label="Konfirmasi password baru" type="password" required autocomplete="new-password" />

        <x-loading-button class="w-100 mt-2" loading-text="Menyimpan…">Simpan Password</x-loading-button>
    </form>

    <x-slot:footer>
        @unless ($forced)
            <p class="mb-1"><a href="{{ route('admin.dashboard') }}">Kembali ke dasbor</a></p>
        @endunless
        <form method="POST" action="{{ route('admin.logout') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-link p-0 align-baseline">Keluar</button>
        </form>
    </x-slot:footer>
</x-layouts.auth>

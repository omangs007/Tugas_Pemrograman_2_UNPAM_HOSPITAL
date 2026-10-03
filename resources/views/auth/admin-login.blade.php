<x-layouts.auth title="Masuk Panel Admin" subtitle="Khusus staf rumah sakit. Masuk dengan akun staf Anda.">
    <form method="POST" action="{{ route('admin.login.store') }}" novalidate>
        @csrf

        <x-form.input name="email" label="Email" type="email" required autocomplete="username" inputmode="email" />
        <x-form.input name="password" label="Password" type="password" required autocomplete="current-password" />

        <x-loading-button class="w-100 mt-2" loading-text="Memproses…">Masuk</x-loading-button>
    </form>

    <x-slot:footer>
        <p class="mb-0 text-body-secondary">Lupa password? Hubungi Super Admin.</p>
    </x-slot:footer>
</x-layouts.auth>

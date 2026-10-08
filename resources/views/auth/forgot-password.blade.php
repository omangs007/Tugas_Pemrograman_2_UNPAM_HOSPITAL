<x-layouts.auth title="Lupa Password" subtitle="Masukkan email akun Anda. Kami akan mengirim tautan untuk mengatur ulang password jika email terdaftar.">
    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <x-form.input name="email" label="Email" type="email" required autocomplete="username" inputmode="email" :autofocus="! $errors->any()" />

        <x-loading-button class="w-100 mt-2" loading-text="Mengirim…">Kirim Tautan</x-loading-button>
    </form>

    <x-slot:footer>
        <a href="{{ route('login') }}">Kembali ke halaman masuk</a>
    </x-slot:footer>
</x-layouts.auth>

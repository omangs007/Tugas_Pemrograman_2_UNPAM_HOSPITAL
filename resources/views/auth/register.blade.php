<x-layouts.auth title="Daftar Akun Pasien" subtitle="Buat akun untuk mendaftar kunjungan rawat jalan secara online." :wide="true">
    @php
        $consentError = (string) $errors->first('consent');
    @endphp

    <form method="POST" action="{{ route('register.store') }}" novalidate>
        @csrf

        <x-form.input name="full_name" label="Nama lengkap" required autocomplete="name" maxlength="100" />

        <div class="row g-md-3">
            <div class="col-md-6">
                <x-form.input name="birth_date" label="Tanggal lahir" type="date" required autocomplete="bday" :max="now()->subDay()->toDateString()" />
            </div>
            <div class="col-md-6">
                <x-form.radio-group name="gender" legend="Jenis kelamin" :options="\App\Enums\Gender::options()" required inline />
            </div>
        </div>

        <x-form.input name="phone" label="Nomor telepon" type="tel" required autocomplete="tel" inputmode="tel" help="Nomor seluler aktif, mis. 081234567890." />
        <x-form.input name="email" label="Email" type="email" required autocomplete="email" inputmode="email" />
        <x-form.textarea name="address" label="Alamat" required rows="3" maxlength="500" />
        <x-form.input name="password" label="Password" type="password" required autocomplete="new-password" help="Minimal 8 karakter, berisi huruf dan angka." />
        <x-form.input name="password_confirmation" label="Konfirmasi password" type="password" required autocomplete="new-password" />

        <div class="mb-3">
            <div class="form-check">
                <input type="checkbox" id="consent" name="consent" value="1" @checked(old('consent') === '1') aria-required="true" @if ($consentError !== '') aria-invalid="true" aria-describedby="consent-error" @endif @class(['form-check-input', 'is-invalid' => $consentError !== ''])>
                <label class="form-check-label" for="consent">Saya telah membaca dan menyetujui <a href="{{ route('privacy') }}" target="_blank" rel="noopener">Kebijakan Privasi</a><span class="visually-hidden"> (terbuka di tab baru)</span>. <span class="required-mark" aria-hidden="true">*</span></label>
            </div>
            @if ($consentError !== '')
                <div id="consent-error" class="invalid-feedback d-block">{{ $consentError }}</div>
            @endif
        </div>

        <x-loading-button class="w-100 mt-2" loading-text="Memproses…">Daftar</x-loading-button>
    </form>

    <x-slot:footer>
        <p class="mb-0">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
    </x-slot:footer>
</x-layouts.auth>

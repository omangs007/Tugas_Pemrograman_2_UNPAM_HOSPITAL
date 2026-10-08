<x-layouts.patient title="Profil dan Akun">
    <x-slot:breadcrumb>
        <x-breadcrumb home="patient.dashboard" :items="[['label' => 'Profil dan Akun']]" />
    </x-slot:breadcrumb>

    <h1 class="h3 mb-3">Profil dan Akun</h1>

    <x-form.errors-summary />

    <p class="text-body-secondary mb-4">No. pasien: {{ $profile->patient_number ?? '-' }}</p>

    <section class="card mb-4" aria-labelledby="judul-data-profil">
        <div class="card-body p-4">
            <h2 id="judul-data-profil" class="h5 mb-3">Data Profil</h2>

            <form method="POST" action="{{ route('patient.profile.update') }}" novalidate>
                @csrf
                @method('PUT')

                <x-form.input name="full_name" label="Nama lengkap" required autocomplete="name" maxlength="100" :value="$profile->full_name" />

                <div class="row g-md-3">
                    <div class="col-md-6">
                        <x-form.input name="birth_date" label="Tanggal lahir" type="date" required autocomplete="bday" max="{{ now()->subDay()->toDateString() }}" :value="$profile->birth_date->format('Y-m-d')" />
                    </div>
                    <div class="col-md-6">
                        <x-form.radio-group name="gender" legend="Jenis kelamin" :options="\App\Enums\Gender::options()" :selected="$profile->gender" required inline />
                    </div>
                </div>

                <x-form.input name="phone" label="Nomor telepon" type="tel" required autocomplete="tel" inputmode="tel" help="Nomor seluler aktif, mis. 081234567890." :value="$profile->phone" />
                <x-form.input name="email" label="Email" type="email" required autocomplete="email" inputmode="email" help="Email ini juga dipakai untuk masuk ke akun Anda." :value="$user->email" />
                <x-form.textarea name="address" label="Alamat" required rows="3" maxlength="500" :value="$profile->address" />

                <div class="mb-3">
                    @if (filled($profile->masked_bpjs))
                        <p class="mb-2">Nomor BPJS tersimpan: {{ $profile->masked_bpjs }}</p>
                        <x-form.checkbox name="remove_bpjs" label="Hapus nomor BPJS dari profil saya" />
                    @else
                        <p class="text-body-secondary mb-0">Belum ada nomor BPJS tersimpan.</p>
                    @endif
                </div>

                <x-loading-button class="mt-2" loading-text="Menyimpan…">Simpan Profil</x-loading-button>
            </form>
        </div>
    </section>

    <section class="card mb-4" aria-labelledby="judul-ganti-password">
        <div class="card-body p-4">
            <h2 id="judul-ganti-password" class="h5 mb-3">Ganti Password</h2>

            <form method="POST" action="{{ route('patient.password.update') }}" novalidate>
                @csrf
                @method('PUT')

                <input type="text" name="username" value="{{ $user->email }}" autocomplete="username" readonly hidden>

                <x-form.input name="current_password" label="Password saat ini" type="password" required autocomplete="current-password" />
                <x-form.input name="password" label="Password baru" type="password" required autocomplete="new-password" help="Minimal 8 karakter, berisi huruf dan angka." />
                <x-form.input name="password_confirmation" label="Konfirmasi password baru" type="password" required autocomplete="new-password" />

                <x-loading-button class="mt-2" loading-text="Menyimpan…">Simpan Password</x-loading-button>
            </form>
        </div>
    </section>
</x-layouts.patient>

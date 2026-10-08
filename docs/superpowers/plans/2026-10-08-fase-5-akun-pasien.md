# Fase 5 — Portal Pasien (Akun): Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pasien dapat membuat akun, masuk dan keluar, mengatur ulang password lewat email, melihat dashboard, serta mengelola profil dan password sebelum mesin booking dibangun. Cakupannya:
- halaman #23 Registrasi, #24 Login pasien, #25 Permintaan reset password, #26 Form reset password, #27 Dashboard pasien, dan #28 Profil dan Akun;
- `PatientRegistrationService`, `PatientNumberGenerator`, `PatientProfilePolicy`, dan `PatientWelcomeNotification`;
- teks bahasa Indonesia untuk reset password dan email (`lang/id/passwords.php`, `lang/id.json`);
- seeder tujuh akun pasien demo (`PatientSeeder`);
- penghapusan cadangan route F1-R2.

**Architecture:** Arsitektur fase 1–4b tetap berlaku: monolit Laravel 12, controller tipis, Form Request, Policy, pelaku `Actor`, dan `AuditLogger` eksplisit.
- **Satu guard, dua jenis akun.** Pasien dan staf berbagi tabel `users` dan guard `web`; pemisahnya `account_type`, middleware `account:*`/`active`, dan kredensial `Auth::attempt`/password broker yang menyaring `account_type = patient` dan `is_active = true`.
- **Registrasi atomik.** `PatientRegistrationService::register()` membuat `users` + `patient_profiles` + nomor pasien `P` + 6 digit dari `id` + audit dalam satu transaksi. Email selamat datang dikirim sesudah commit, dan kegagalannya hanya dilaporkan.
- **Profil milik sendiri.** Portal tidak pernah menerima ID profil dari URL atau form; profil selalu `$request->user()->patientProfile`. Email profil selalu sama dengan email login (D-07), disinkronkan dalam satu transaksi.
- **Anti-enumerasi.** Login gagal dan permintaan reset selalu memberi respons identik, termasuk saat broker membatasi (`throttled`).
- **Tanpa migration, enum, izin, entri Vite, atau JS baru.** Hanya satu kelas SCSS (`.auth-card-wide`).

**Tech Stack:** sama dengan fase 4b. PHP 8.2.12 (XAMPP `C:\xampp3`), Laravel 12.69, MariaDB 10.4.32, spatie/laravel-permission 6.25, Bootstrap 5.3 + Sass + Vite 7, PHPUnit 11, Pint. **Tanpa paket baru.**

**Spec:** `docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/`. Bagian utama yang dipakai:

| Dokumen | Bagian |
|---|---|
| 00 | C (aturan hapus), D (enum `AccountType`, `Gender`, `BookingChannel`), E.1 (users, patient_profiles, sinkron email), E.7 (`privacy.consent_version`), H, I.2/I.3 (route), J.1/J.6 |
| 14 | §5 Fase 5 |
| 02 | F-AUTH-01–05, F-PAS-01 (sebagian), F-PAS-02, F-PAS-03 |
| 04 | §1, §3 (D1–D8), §4.3–§4.4, §5 (`PatientProfilePolicy`), §6 (visibilitas data pasien), §10 |
| 05 | §3.1–§3.4 (navigasi), §4.2 halaman 23–28, §7 (title), §8 (breadcrumb) |
| 06 | §0.4, §1.1 (registrasi), §1.2 (login/logout), §2 (reset), §19 (profil) |
| 07 | W-09 (auth), W-10 (dashboard), W-01 (header) |
| 09 | §2.1 `users`, §2.2 `patient_profiles`, §2.8–§2.10, §13 |
| 10 | §2.2–§2.3, §3.1 (layout) |
| 11 | §1, §2.1–§2.2, §3.1, §4.2–§4.3, §5.1, §6.7–§6.8, §7.1–§7.2, §8, §9.1–§9.2, §10.1, §11 |
| 12 | BR-56, BR-100, BR-103, BR-144–BR-151, BR-160–BR-162, BR-170, BR-174, BR-181, V-01, V-02, V-04–V-07, §3.1, §5, §7, §8 |
| 13 | §2.1, §3, §10.1, §11 (I15–I17), §12 |
| 15 | AKS-01–AKS-08, AKS-10, SEK-01–SEK-07, SEK-11, SEK-12, SEK-15, SEK-16, NTF-04, NTF-05, NTF-08, PAS-05, PAS-09, VAL-02, EMP-04, ERR-02, ERR-03, SEED-01, SEED-02 |
| 16 | §5.1, §6.1, §7.5, §10 |
| 17 | K-07, K-22 (dikonfirmasi pemilik 2026-10-08: keduanya (a)), D-07, D-08, D-21–D-23, D-25, D-27, D-61 |

Ringkasan kutipan spesifikasi dan kode untuk fase ini ada di `.superpowers/plan-fase-5/digest-{data,rules,http,tests-carry,kode}.md`, beserta kerangka kontrak (`kerangka.md`). Berkas-berkas itu hanya bahan penulisan dan tidak ikut di-commit. Isi kerangka yang mengikat sudah disalin ke rencana ini (bagian Keputusan, Route dan nama, Teks UI, Data seeder, Review Focus).

## Global Constraints

Berlaku untuk setiap tugas (rujukan "kerangka A" di teks tugas).

- **XAMPP dan Prelude P.** XAMPP di `C:\xampp3` (PHP 8.2.12); Apache dan MySQL harus di-Start sebelum tes. Setiap blok PowerShell diawali Prelude P:
  `$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP`
- **Perintah dan paket.** PHP dijalankan `& $PHP …`. Tidak ada paket Composer/npm baru.
- **Suite penuh** ±5200 tes, ±28 menit, melebihi batas 10 menit sebuah perintah tool: implementer menjalankan tes terfilter per tugas; suite penuh dijalankan di latar belakang dengan keluaran ke berkas (pola `Start-Process`/`run_in_background`), lalu ditunggu. Basis master: **5200 tes lulus, 106 route admin, 128 route total (`route:list --except-vendor`)**.
- **Kompatibilitas PHP 8.2–8.4.** Dilarang: typed class constants, `#[\Override]`, `json_validate()`, property hooks, `new` tanpa kurung pada chaining. Berkas baru di `app/Services`, `app/Support`, `app/Rules`, `app/Policies`, `app/Notifications`, `app/View/Components` diawali `declare(strict_types=1);`.
- **Nama.** Tabel, kolom, route, URI, izin, Form Request, Policy, method, view, teks UI harus PERSIS kerangka ini (bagian C dan D). Nama baru hanya yang ada di bagian D (plus nama privat/tes kecil satu tugas).
  - **Enum tidak boleh ditambah/diubah** (`EnumCatalogTest` mengunci 15 enum). Kanal registrasi = `BookingChannel::Online`; jenis kelamin = `Gender`; tipe akun = `AccountType`.
  - **Izin tidak boleh ditambah** (tetap 19). Pasien tidak punya peran/izin Spatie.
  - **Tidak ada migration baru** (skema `users`, `patient_profiles`, `password_reset_tokens`, `sessions`, `notifications` sudah cocok 09). Migration lama tidak boleh diubah.
- **Bahasa.** Identifier Inggris. Teks UI, pesan validasi, flash, deskripsi audit, komentar: Indonesia. Flash = kalimat lengkap berakhir titik.
- **Waktu.** Zona `Asia/Jakarta`; "sekarang" (`consent_at`, `last_login_at`) dari `now()` aplikasi, tidak pernah `NOW()` SQL; tes memakai `$this->travelTo(...)`.
- **Mode ketat.** `Model::shouldBeStrict()` aktif di non-produksi: kolom non-fillable (`users.account_type`, `is_active`, `must_change_password`, `last_login_at`, `remember_token`; `patient_profiles.user_id`, `patient_number`, `bpjs_number_last4`, `created_by_user_id`) diisi lewat `forceFill()` atau penugasan atribut langsung; `fill()` hanya dengan kunci `$fillable`. Relasi yang dipakai view daftar wajib eager-load (fase 5 tidak punya daftar).
- **Database tes.** MariaDB `unpam_hospital_test` + `RefreshDatabase`. Staf lewat `InteractsWithStaff::createStaff()`, pasien lewat `InteractsWithPatients::createPatient()` (T1). Tes notifikasi memakai `Notification::fake()`; tes mail mentah memakai mailer `array` bawaan `phpunit.xml`.
- **Output HTML.** `{!! !!}` tetap HANYA lima baris rich text yang dikunci `RawOutputScanTest` (view fase 5 tidak memakai `{!!` atau `HtmlString`). Tidak ada atribut event inline, tidak ada `onerror`, tidak ada CDN, tidak ada skrip inline. CSP tidak diubah. Tidak ada entri Vite baru dan tidak ada listener `error` kedua. JS baru: TIDAK ADA di fase 5.
- **Route.** Route auth tamu di `routes/web.php` (blok baru); route portal di `routes/patient.php` (BARU) yang dimuat `bootstrap/app.php` `then:`. Tidak ada route wildcard di bawah `/pasien` (`InternalLinkTest`/`BannerButtonTest` mengunci `/pasien/pendaftaran/baru` belum ada). Tidak ada route `patient.appointments.*`/`patient.notifications.*` (fase 6). Total setelah fase 5: **141 route** (`route:list --except-vendor`), **106 admin** (tetap), **13 baru**.
- **Data sensitif.** Daftar `$sensitive` di `bootstrap/app.php` (`password`, `password_confirmation`, `current_password`, `bpjs_number`, `payment_reference`) tidak pernah di-flash; nama field form fase 5 memakai nama itu. Input larik pada field mana pun tidak boleh menyebabkan 500 (aturan proyek).
- **Penulisan berkas.** Tool Write/Edit; akhir baris **LF**; `old` pada Edit cocok tepat satu kali. Bila lewat PowerShell: `[IO.File]::WriteAllText(path, text, (New-Object System.Text.UTF8Encoding $false))`. Jangan menyalin pagar Markdown (```` ``` ````) ke berkas kode. `.env*` tidak pernah di-commit.
- **Cabang dan commit.** Cabang `fase-5-akun-pasien` (sudah dibuat dari `master` `33a6f52`). Pesan commit `tipe(fase-5): ringkasan`, diakhiri `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>` (implementer subagen boleh memakai atribusi harness-nya). Di setiap commit: tes terkait hijau, `vendor\bin\pint --test` bersih; bila menyentuh `resources/scss`/`resources/js`, `npm run build` sukses sebelum tes.
- **TDD.** RED wajib nyata: jalankan tes baru sebelum implementasi dan catat kegagalannya (pesan galat yang diharapkan tertulis di langkah "Expected").
- **Langkah MANUAL** (bertanda **MANUAL (koordinator)**) dijalankan koordinator lewat Playwright di T8; implementer melewatinya.
- **DB pengembangan.** Implementer TIDAK menjalankan `migrate`, `migrate:fresh`, `db:seed` pada `unpam_hospital`. `migrate:fresh --seed` dijalankan koordinator dengan izin eksplisit pemilik di gerbang T8.

---

## Review Focus

Setiap butir punya tes di tugas pemiliknya (ditunjuk dengan →). Rujukan "kerangka G" di teks tugas menunjuk ke sini.

1. **Enumerasi akun.** Login: email tak dikenal vs password salah vs akun staf vs akun nonaktif → status, `Location`, galat identik. Reset: email terdaftar vs tidak vs staf vs nonaktif vs permintaan kedua < 60 detik (broker `throttled`) → flash dan status identik; tidak ada `ResetPassword` untuk staf/nonaktif. → T3, T5.
2. **Input larik dan nilai liar di semua form pasien.** `email[]=x`, `password[]=x`, `full_name[]=x`, `gender=X`, `birth_date=2099-01-01`/`31-12-1990`, `remember=abc`, `remove_bpjs=abc`, `consent[]=1` → galat field Indonesia, tidak pernah 500, tidak ada baris parsial. → T3, T4, T5, T6.
3. **Mass-assignment dan kepemilikan profil.** `PUT /pasien/profil` dengan `user_id`, `patient_number`, `registration_channel=loket`, `is_active=0`, `bpjs_number_last4`, `consent_at`, `consent_version`, `bpjs_number` → hanya enam kolom profil (+ hapus BPJS) berubah; profil pasien lain tidak tersentuh; `users.email/name/phone` sinkron; email bentrok → tanpa perubahan parsial. → T6 (dan T2 untuk service).
4. **Sesi dan pengalihan.** Intended URL `/pasien/profil?contoh=utuh` dipertahankan; intended `/admin/...` diabaikan pada login pasien; pasien login membuka `/masuk`, `/daftar`, `/lupa-password`, `/reset-password/x` → dashboard; staf di `/pasien` → `admin.dashboard`; akun dinonaktifkan saat sesi berjalan → keluar ke `/masuk` dengan flash; setelah reset password semua sesi akun terhapus; setelah ganti password sesi lain terhapus dan sesi kini tetap. → T3, T5, T6.
5. **Atomisitas registrasi dan email.** Kegagalan saat membuat profil (listener `creating` melempar) → tidak ada `users`/`patient_profiles`/`audit_logs` baru, tidak ada notifikasi, flash galat sistem; email ganda beda huruf besar (`Siti@Contoh.test`) ditolak; balapan unik → pesan email, bukan 500; kegagalan kirim email selamat datang tidak membatalkan registrasi. → T2, T4.

---

## Keputusan (rulings) fase 5

Keputusan ini mengikat semua tugas (rujukan "kerangka B" atau "F5-Rn" di teks tugas). Butir yang menyimpang dari blueprint direkonsiliasi ke dokumen di Task 8 (D-140 dan seterusnya).

- **F5-R1 Cakupan.** F-AUTH-01..05, F-AUTH-03 (logout pasien), F-PAS-01 (sebagian: sapaan, nomor pasien, kartu kosong, ringkasan profil), F-PAS-02, F-PAS-03; halaman #23–#28. TIDAK dibangun: wizard/riwayat/notifikasi/banner (`patient.appointments.*`, `patient.notifications.read`, fase 6); admin pasien, walk-in, hubungkan akun, `changeStatus` (fase 7); dropdown header "Akun Saya" (05 §3.1) — header tetap tautan "Akun Saya" + tombol "Keluar" yang sudah ada (dropdown ditunda ke fase 6 saat Riwayat ada); tombol "Tampilkan password" dan hitung mundur throttle (W-09, ditunda fase 9); input nomor BPJS di profil (lihat F5-R19).
- **F5-R2 Skema.** Tanpa migration. Semua kolom sudah ada (digest-data §9).
- **F5-R3 Route (13 baru).** Persis tabel C.1. Blok auth tamu di `routes/web.php` (sesudah route publik, sebelum akhir berkas) dengan komentar `// Auth pasien (#23–#26, 11 §2.1)`; `logout` di luar grup `guest`. `routes/patient.php` hanya 4 route fase 5. `bootstrap/app.php` `then:` memuat grup pasien SEBELUM grup admin, menggantikan komentar `// Grup pasien (routes/patient.php) ditambahkan pada fase 5.`:
  ```php
  // Grup pasien G-PASIEN (11 §1, §3.1): /pasien, nama patient.*.
  Route::middleware(['web', 'auth', 'account:patient', 'active'])
      ->prefix('pasien')
      ->name('patient.')
      ->group(base_path('routes/patient.php'));
  ```
  Route ditambahkan per tugas yang membuat controllernya (T3: login, login.store, logout, patient.dashboard; T4: register, register.store; T5: password.*; T6: patient.profile.*, patient.password.update).
- **F5-R4 Cadangan F1-R2 dihapus (T3).** `bootstrap/app.php`: `redirectGuestsTo` → `/admin*` ke `admin.login`, selain itu `route('login')`; `redirectUsersTo` → staf `admin.dashboard`, selain itu `route('patient.dashboard')`; hapus komentar "Cadangan F1-R2" dan `use` yang tak terpakai. `EnsureAccountIsActive`: `$loginRoute = $isStaff ? 'admin.login' : 'login';`. Guard `Route::has(...)` di view (`layouts.public`, `layouts.patient`, `errors/403`, `registration-guide`, `NavigationMenu`) DIBIARKAN (D-21, aman, dan masih dibutuhkan untuk route fase 6).
- **F5-R5 Staf membuka portal pasien.** Tetap perilaku kode fase 1 / 11 §7.1: staf (termasuk Super Admin) di `/pasien*` → 302 ke `admin.dashboard` (JSON → 403). Teks 04 D2, 12 BR-160, 06 §19, 15 §3.2/AKS-03 ("403") direkonsiliasi di T8 (D-entry). Pasien di `/admin*` → 403 (tidak berubah).
- **F5-R6 Login pasien (T3).** `Auth\LoginRequest::authenticate(): User` → `Auth::attempt(['email' => $email, 'password' => $password, 'account_type' => AccountType::Patient->value, 'is_active' => true], $this->boolean('remember'))`. Gagal (email tak dikenal, password salah, akun staf, akun nonaktif) → `ValidationException::withMessages(['email' => __('auth.failed')])` = "Email atau password salah." (D-22/AKS-08 menang atas teks khusus V-02). Tanpa audit login/logout pasien. `prepareForValidation`: email `Str::lower(trim())` bila string. Sukses (controller): `session()->regenerate()` → `RateLimiter::clear(md5('login'.$request->input('email').'|'.$request->ip()))` (email sudah ternormalisasi) → `forceFill(['last_login_at' => now()])->save()` → redirect ke intended URL (F5-R7). Timebox: `SessionGuard::attempt` Laravel 12 sudah memakai `Timebox` 200 ms (hardening `auth.timebox_duration` tetap carry fase 9; TIDAK membuat hash dummy).
- **F5-R7 Intended URL.** Login dan registrasi pasien: `$intended = $request->session()->pull('url.intended');` dipakai hanya bila string dan path-nya (`parse_url(..., PHP_URL_PATH)`) tidak `/admin` dan tidak diawali `/admin/`; selain itu `route('patient.dashboard')`. Logika ini ada di kelas kecil `App\Support\PatientIntendedUrl` (final, static `pull(Request $request): string`) yang dibuat T3 dan dipakai T3 (login) serta T4 (registrasi).
- **F5-R8 Logout (T3).** `POST /keluar` (`logout`, middleware `auth` saja). `Auth\LoginController@destroy`: `Auth::guard('web')->logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('home')->with('success', 'Anda telah keluar.');`. Staf yang memakai `/keluar` ikut keluar tanpa audit (staf normalnya memakai `admin.logout`).
- **F5-R9 Registrasi (T2 service, T4 HTTP).** `PatientRegistrationService::register(array $validated): User` dalam SATU `DB::transaction`: (1) `User` baru `forceFill(['name' => full_name, 'email', 'phone', 'password', 'account_type' => AccountType::Patient, 'is_active' => true, 'must_change_password' => false])->save()` (`email_verified_at` tetap NULL; cast `hashed` meng-hash; JANGAN `Hash::make`); (2) `PatientProfile` baru: `fill(full_name, birth_date, gender, phone, email, address, registration_channel => BookingChannel::Online, consent_at => now(), consent_version => SettingsService::string('privacy.consent_version', '1.0'), is_active => true)` + `forceFill(['user_id' => $user->id, 'created_by_user_id' => null])->save()`; (3) `forceFill(['patient_number' => PatientNumberGenerator::format($profile->id)])->save()`; (4) audit `patient.registered` (F5-R24). `register()` mengembalikan `User` yang di-refresh dengan relasi `patientProfile` termuat. SETELAH transaksi kembali: kirim `PatientWelcomeNotification` dalam `try { … } catch (Throwable $e) { report($e); }` (kegagalan mail tidak membatalkan registrasi; 06 §18). `RegisterController@store`: setiap `withInput` memakai `$request->except(self::SENSITIVE_INPUT)` (daftar `$sensitive` kerangka A; `back()->withInput()` tanpa argumen TIDAK menerapkan `dontFlash`); tangkap `Illuminate\Database\UniqueConstraintViolationException` → `back()->withInput(...)->withErrors(['email' => 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.'])`; tangkap `Throwable` lain → `report($e)` + `back()->withInput(...)->with('error', 'Registrasi belum berhasil karena gangguan sistem. Silakan coba lagi.')`. Sukses → `Auth::login($user)` → `session()->regenerate()` → `forceFill(['last_login_at' => now()])->save()` → redirect `PatientIntendedUrl::pull($request)` dengan flash `success` "Registrasi berhasil. Selamat datang, {nama lengkap}." (06 §1.1). Tidak ada pencocokan otomatis dengan profil walk-in (WLK-06).
- **F5-R10 Nomor pasien.** `PatientNumberGenerator::format(int $id): string` = `'P'.str_pad((string) $id, 6, '0', STR_PAD_LEFT)` (`P000123`; `P1000000` bila ≥ 1.000.000). Diisi service setelah insert dalam transaksi yang sama (06/09/14) — BUKAN event model (11 §5.1 tidak dipakai; pembeda dicatat D-entry). `PatientProfileFactory` TIDAK diubah (tetap `P######` acak).
- **F5-R11 BPJS last4 dan tampilan tersamar (T1).** `PatientProfile::booted()` mendaftarkan `static::saving(...)`: bila `isDirty('bpjs_number')`, set `bpjs_number_last4` = 4 digit terakhir (string non-kosong) atau `null`. Accessor `maskedBpjs` (`masked_bpjs`) = `'•••••••••'.$this->bpjs_number_last4` atau `null` (11 §5.1, 04 V2). Nilai lengkap tidak pernah dirender.
- **F5-R12 Relasi.** `User::patientProfile(): HasOne` (`PatientProfile::class`, `user_id`). `createdPatientProfiles()` TIDAK ditambah (fase 7). `PatientProfile` tidak mendapat scope/accessor lain di fase 5 (YAGNI).
- **F5-R13 Profil tidak ada.** Akun pasien tanpa profil (hanya mungkin di tes/DB rusak): `DashboardController@index` dan `ProfileController@edit` → `abort(404)`; `UpdatePatientProfileRequest::authorize()` → false (403); `PasswordController` tetap jalan (hanya butuh `User`). Tes memakai `createPatient()`.
- **F5-R14 Policy (T1).** `PatientProfilePolicy` hanya `view` dan `update`: `return $user->can('patients.manage') || $profile->user_id === $user->id;` untuk `update`; `view` memakai `patients.view` || pemilik. (Method lain fase 7.) Penemuan otomatis `App\Policies\PatientProfilePolicy`.
- **F5-R15 Reset password (T5).** `Auth\PasswordResetLinkController@store`: `Password::broker()->sendResetLink(['email' => $email, 'account_type' => AccountType::Patient->value, 'is_active' => true])` dalam `try/catch (Throwable) { report($e); }`; APA PUN status (`RESET_LINK_SENT`, `INVALID_USER`, `RESET_THROTTLED`) → `redirect()->route('password.request')->with('success', __('passwords.sent'))` (`Location` identik tanpa bergantung Referer). Email dari query form reset di-trim dan di-lowercase; nilai larik dianggap kosong. `Auth\NewPasswordController@store`: `Password::broker()->reset([...validated email/password/password_confirmation/token, 'account_type' => 'patient', 'is_active' => true], callback)`; callback (dibungkus `DB::transaction`): `forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save()`, `DB::table('sessions')->where('user_id', $user->id)->delete()`, audit `auth.password_reset`. `PASSWORD_RESET` → `redirect()->route('login')->with('success', __('passwords.reset'))` (tanpa login otomatis). Selain itu → `back()->withInput($request->only('email'))->withErrors(['email' => __('passwords.token')])`. Route `password.email` DAN `password.update` memakai `throttle:password-reset` (11 §2.1; satu bucket email+IP, 3/10 menit — dicatat di Keterbatasan). Tautan reset tidak pernah dirender di halaman/flash/JSON.
- **F5-R16 Teks `lang/id/passwords.php` (T5)** (ganti seluruh nilai; kunci tetap): `reset` = "Password berhasil diubah. Silakan masuk dengan password baru."; `sent` = `user` = `throttled` = "Jika email terdaftar, tautan untuk mengatur ulang password telah dikirim. Periksa kotak masuk Anda."; `token` = "Tautan pengaturan ulang password tidak valid atau sudah kedaluwarsa. Silakan ajukan permintaan baru.". Komentar berkas menjelaskan `throttled` sengaja sama (anti-enumerasi, bawaan fase 0).
- **F5-R17 `lang/id.json` (T2 membuat, T5 menambah).** T2: kunci templat mail bawaan: `"Hello!": "Halo!"`, `"Whoops!": "Maaf!"`, `"Regards,": "Salam,"`, `"If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\ninto your web browser:": "Jika tombol \":actionText\" tidak berfungsi, salin dan tempel URL berikut\nke peramban web Anda:"`, `"All rights reserved.": "Hak cipta dilindungi."`. T5 menambah: `"Reset Password Notification": "Atur Ulang Password"`, `"You are receiving this email because we received a password reset request for your account.": "Anda menerima email ini karena ada permintaan untuk mengatur ulang password akun Anda."`, `"Reset Password": "Atur Ulang Password"`, `"This password reset link will expire in :count minutes.": "Tautan ini berlaku selama :count menit."`, `"If you did not request a password reset, no further action is required.": "Jika Anda tidak meminta pengaturan ulang password, abaikan email ini."`. Berkas JSON berindentasi 4 spasi, kunci terurut sesuai urutan di atas, diakhiri baris baru.
- **F5-R18 Profil (T6).** Satu halaman dua formulir, satu ringkasan galat (`x-form.errors-summary`) di atas keduanya (nama field kedua form tidak bertumpuk). `PUT patient.profile.update` → `PatientRegistrationService::updateProfile($profile, $request->validated(), Actor::patient($user))`; flash "Profil berhasil diperbarui." → `patient.profile.edit`. `UniqueConstraintViolationException` (balapan email) → `back()->withInput()->withErrors(['email' => 'Email ini sudah terdaftar. Gunakan email lain.'])`. Perubahan email TIDAK meminta password saat ini (12 V-06 menang atas ASUMSI 06 §19).
- **F5-R19 BPJS di profil.** Tanpa input nomor BPJS (11/12 V-06; nomor masuk lewat wizard fase 6 `save_bpjs`). Bila `bpjs_number_last4` terisi: tampil "Nomor BPJS tersimpan: •••••••••1234" + checkbox `remove_bpjs` "Hapus nomor BPJS dari profil saya". Bila kosong: teks "Belum ada nomor BPJS tersimpan." `remove_bpjs` benar → `bpjs_number = null` (hook mengosongkan last4).
- **F5-R20 `updateProfile` (T2).** `DB::transaction`: `fill(Arr::only($validated, ['full_name','birth_date','gender','phone','email','address']))`; bila `($validated['remove_bpjs'] ?? false)` truthy → `bpjs_number = null`; perubahan dideteksi dengan `isDirty()` SEBELUM `save()` (bukan `diff()` sesudahnya, karena `getChanges()` dari simpan sebelumnya bisa memicu audit palsu); `save()` profil lebih dulu, lalu bila profil punya `user` → `user->forceFill(['name' => full_name, 'email' => email, 'phone' => phone])->save()` (BR-151, D-07); bila ada perubahan → audit `patient.updated` dengan `old`/`new` dari `diff()` tanpa kunci `bpjs_number_last4` dan `updated_at`. Tanpa perubahan → tanpa audit (flash tetap sukses). Mengembalikan `$profile->refresh()` (instance yang sama). Service tidak menormalkan input; normalisasi (trim, email huruf kecil, `IndonesianPhone::normalize`) ada di `prepareForValidation` Form Request.
- **F5-R21 Ganti password (T6).** `Patient\PasswordController@update(UpdatePasswordRequest $request, AuditLogger $audit)`: `forceFill(['password' => $request->validated('password'), 'remember_token' => Str::random(60)])->save()`; `session()->regenerate()`; `DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete()` (06 §19 [ASUMSI] dipakai); audit `auth.password_changed` (`User`, old/new null); redirect `patient.profile.edit` flash "Password berhasil diganti.". Aturan `current_password` diawali `bail`, `string` (pesan "Password saat ini tidak valid.") karena aturan bawaan `current_password` memanggil `password_verify()` tanpa cek tipe (larik → 500); `Admin\UpdatePasswordRequest` fase 1 ikut ditambal di T6.
- **F5-R31 `x-form.checkbox` dan input larik (T3).** Komponen `x-form.checkbox` memeriksa `is_scalar` sebelum membandingkan nilai lama, sehingga `remember[]=1`/`remove_bpjs[]=1` tidak lagi menyebabkan 500 saat form dirender ulang. Urutan aturan email di semua Form Request fase 5: `bail`, `required`, `string`, `max:255`, `email:rfc` (+ `unique` pada registrasi/profil).
- **F5-R22 Dashboard (T3).** `<x-layouts.patient title="Dashboard Pasien">`, tanpa breadcrumb. `<h1>` "Halo, {full_name}"; `<p>` "No. pasien: {patient_number}". Seksi `<h2>` "Kunjungan Mendatang" berisi `<x-empty-state icon="bi-calendar-x" title="Belum ada pendaftaran" message="Pendaftaran kunjungan Anda akan tampil di sini.">` dengan tombol: `@if (Route::has('patient.appointments.create'))` `<a class="btn btn-primary" href="{{ route('patient.appointments.create') }}">Daftar Kunjungan</a>` `@else` `<a class="btn btn-outline-primary" href="{{ route('schedules.index') }}">Lihat Jadwal Dokter</a>` `@endif`. Kartu `<h2>` "Profil Anda" berisi Email dan Nomor telepon (T3). Tautan "Ubah Profil dan Akun" → `patient.profile.edit` di kartu itu ditambahkan **T6** (route-nya baru ada di T6; T3 tidak menulis tautan atau guard `Route::has` untuknya).
- **F5-R23 Layout auth (T3).** `components/layouts/auth.blade.php`: `@props(['title', 'subtitle' => null, 'heading' => null, 'wide' => false])`; `<h1 class="h3 mb-1">{{ $heading ?? $title }}</h1>`; kartu `@class(['auth-card', 'auth-card-wide' => $wide, 'mx-auto'])` (pertahankan kelas yang ada). SCSS `resources/scss/_shared.scss`: `.auth-card-wide { max-width: 35rem; }` tepat sesudah blok `.auth-card` (T3 menambah keduanya; T4 memakai `wide`). `<title>` tetap dari `$title`.
- **F5-R24 Audit.** Hanya empat aksi: `patient.registered` (T2; `PatientProfile`; old null; new `['patient_number' => …, 'registration_channel' => 'online', 'consent_version' => …]`; deskripsi "Pasien {patient_number} mendaftar akun secara online."; `Actor::patient($user)`), `patient.updated` (T2; deskripsi "Profil pasien {patient_number} diperbarui."), `auth.password_changed` (T6; `User`; deskripsi "Pasien {patient_number} mengganti password akunnya."; `Actor::patient`), `auth.password_reset` (T5; `User`; deskripsi "Password akun pasien {patient_number} diatur ulang lewat tautan email."; `Actor::patient($user)`). Deskripsi tidak memuat nama/email/telepon. Login/logout pasien tidak diaudit. Bila profil tidak ada saat butuh nomor pasien di deskripsi, pakai `'-'`.
- **F5-R25 `PatientWelcomeNotification` (T2).** `App\Notifications\PatientWelcomeNotification extends Notification` (TIDAK `ShouldQueue` — dikirim sinkron sesudah commit; antrean notifikasi dirancang fase 7), `via(): ['mail']`, `toMail(User $notifiable): MailMessage`: subject "Selamat Datang di UNPAM HOSPITAL" — nama situs dari `config('app.name')`; greeting "Halo, {name}!"; line "Akun pasien Anda di {app} telah berhasil dibuat."; line "Masuk ke akun Anda untuk melihat dan memperbarui profil."; line "Pendaftaran kunjungan rawat jalan dapat dilakukan melalui menu Daftar Kunjungan setelah fitur tersebut tersedia, atau langsung di loket pendaftaran."; action "Masuk ke Akun" → `route('login')`; tidak memuat telepon, alamat, tanggal lahir, BPJS, nomor pasien, atau tautan verifikasi (BR-174, NTF-08).
- **F5-R26 Teks dan tampilan auth.** Persis bagian E.
- **F5-R27 Seeder demo (T7).** `Database\Seeders\PatientSeeder` (BARU): tujuh akun pasien berakun + profil online P000001–P000007 (13 §10.1; data persis di bagian F); walk-in (P000008–P000013) dan `AppointmentSeeder` = fase 6/7. `DemoUserSeeder` TIDAK diubah (tetap 3 staf). `DemoSeeder` memanggil `… ContentSeeder, PatientSeeder, ContactMessageSeeder` dan tabel kredensial konsol menambah baris "Anisa Rahmawati · pasien@unpamhospital.test · Pasien · {password}" plus catatan "Enam akun pasien lain (@contoh.test) memakai password yang sama.".
- **F5-R28 Uji akses (T8).** `PatientPageMatrixTest` (#23–#28 × 5 aktor + mutasi `PUT patient.profile.update`, `PUT patient.password.update`, `POST logout`) mengikuti F5-R5 (staf → `admin.dashboard`). AKS-07 fase 5 diuji dengan `/pasien/profil?contoh=utuh` (T3); AKS-07 asli + `/admin/pasien`, `/admin/pengaturan` di AKS-06 menjadi carry fase 6/7/8.
- **F5-R29 Tes lama yang berubah** (tugas pemilik): T3 — `GuestRedirectTest` (hapus 2 tes "…while … does not exist", ganti 2 tes "…once it exists" dengan route asli), `AdminAreaAccessTest` (hapus route palsu `login`), `LayoutsTest` (hapus cabang "belum ada"; tes portal memakai route asli; item fase 6 tetap lewat route palsu `patient.appointments.*`), `AdminPageMatrixTest::dashboardFor` (tanpa cadangan); T5 — `LocalizationTest` (bila mengunci teks `passwords.*`); T3 juga `FrontendManifestTest` (kelas `.auth-card-wide`); T6 — `AdminPasswordChangeTest` (tambalan `current_password` larik); T7 — `SeederProductionGuardTest` dan `FacilitySeedersTest` (angka user 3 → 10), `DemoSeederWiringTest` (urutan), `DemoSeederInvariantsTest` (angka + invarian I15–I17); T8 — `SecurityHeadersTest::htmlPages` (+ `/masuk`, `/daftar`, `/lupa-password`), `RawOutputScanTest` (filter inline-event mencakup `auth/` dan `patient/`). Setiap tugas WAJIB grep tes lain yang memakai nama route/teks yang ia ubah.
- **F5-R30 Dokumen (T8).** Rekonsiliasi blueprint lewat D-140 dst. di 17 (dan pindahkan K-07, K-22 ke §1 "Keputusan yang Sudah Diambil Pemilik"), 00 J.6 nama baru, 15 (catatan AKS-07, §3.2 staf →A), 04/12 (BR-160, D2 → dialihkan), 06 §19 (BPJS/current_password), 13 §2.1 (PatientSeeder), README "Fase 5". CHECKPOINT ditulis koordinator, bukan implementer.

---

## Keterbatasan yang diketahui

Butir berikut disengaja dan tidak dikerjakan di fase 5:
- **Daftar kunjungan belum ada.** Dashboard belum menampilkan kunjungan mendatang, riwayat, atau banner notifikasi. Tombol "Daftar Kunjungan" baru muncul saat route `patient.appointments.create` dibuat di fase 6; sampai saat itu kartu kosong menautkan ke Jadwal Dokter (F5-R22).
- **Tiga fitur antarmuka ditunda.**
  - Header publik tetap menampilkan tautan "Akun Saya" dan tombol "Keluar", bukan dropdown 05 §3.1. Dropdown ditunda ke fase 6, saat subitem Riwayat ada.
  - Tombol "Tampilkan password" ditunda ke fase 9.
  - Hitung mundur throttle di tombol Masuk ditunda ke fase 9.
- **Pesan throttle global.** Teks throttle sama untuk semua form ("Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik.", D-23); tidak ada teks per form seperti di 06.
- **Bucket reset bersama.** `throttle:password-reset` dipakai bersama oleh `password.email` dan `password.update` (satu bucket email+IP, 3 per 10 menit). Pengguna yang meminta tautan dua kali lalu salah mengisi form reset bisa tertahan sampai 10 menit.
- **Timing reset.** Respons permintaan reset untuk email terdaftar sedikit lebih lambat karena email dikirim sinkron. Teks dan statusnya tetap identik dengan email tak terdaftar. Pengiriman lewat antrean dirancang di fase 7.
- **BPJS di profil.** Profil tidak punya input nomor BPJS. Nomor masuk lewat wizard fase 6 (`save_bpjs`); di fase 5 hanya tampilan tersamar dan aksi hapus.
- **Email tanpa verifikasi.** Mengubah email di profil tidak meminta password saat ini (12 V-06), dan email baru tidak diverifikasi (K-22, A-01).
- **Remember-me ikut putus.** Ganti password dan reset password mengganti `remember_token`, sehingga cookie "Ingat saya" di semua perangkat, termasuk perangkat yang dipakai, tidak berlaku lagi setelah sesinya berakhir.
- **Staf di portal pasien.** Staf yang membuka portal pasien dialihkan ke dasbor admin (F5-R5), bukan 403 seperti tertulis di sebagian blueprint.
- **Logout staf lewat `/keluar`.** Staf yang memakai `POST /keluar` ikut keluar tanpa audit `auth.staff_logout`; jalur resmi staf tetap `admin.logout`.
- **Seeder pasien terbatas.** Seeder hanya membuat tujuh akun pasien berakun. Enam profil walk-in dan appointment demo menyusul di fase 6/7.
- **AKS-07 versi pengganti.** AKS-07 diuji dengan URL portal fase 5. Versi aslinya (`/pasien/pendaftaran/baru?sesi=ID`) menyusul di fase 6, sedangkan `/admin/pasien` dan `/admin/pengaturan` pada AKS-06 menyusul di fase 7 dan 8.

## Nama baru yang diperkenalkan fase 5

Hanya nama di bagian ini (ditambah nama privat/tes kecil yang dipakai satu tugas) yang boleh diperkenalkan. Kode lengkapnya ada di tugas pembuatnya.

### Route baru (13; rujukan "kerangka C.1")

| Nama | Method | URI | Middleware (tanpa `web`) | Controller@method | Tugas |
|---|---|---|---|---|---|
| `login` | GET | `/masuk` | `guest` | `Auth\LoginController@create` | T3 |
| `login.store` | POST | `/masuk` | `guest`, `throttle:login` | `Auth\LoginController@store` | T3 |
| `logout` | POST | `/keluar` | `auth` | `Auth\LoginController@destroy` | T3 |
| `patient.dashboard` | GET | `/pasien` | `auth`, `account:patient`, `active` | `Patient\DashboardController@index` | T3 |
| `register` | GET | `/daftar` | `guest` | `Auth\RegisterController@create` | T4 |
| `register.store` | POST | `/daftar` | `guest`, `throttle:register` | `Auth\RegisterController@store` | T4 |
| `password.request` | GET | `/lupa-password` | `guest` | `Auth\PasswordResetLinkController@create` | T5 |
| `password.email` | POST | `/lupa-password` | `guest`, `throttle:password-reset` | `Auth\PasswordResetLinkController@store` | T5 |
| `password.reset` | GET | `/reset-password/{token}` | `guest` | `Auth\NewPasswordController@create` | T5 |
| `password.update` | POST | `/reset-password` | `guest`, `throttle:password-reset` | `Auth\NewPasswordController@store` | T5 |
| `patient.profile.edit` | GET | `/pasien/profil` | `auth`, `account:patient`, `active` | `Patient\ProfileController@edit` | T6 |
| `patient.profile.update` | PUT | `/pasien/profil` | idem | `Patient\ProfileController@update` | T6 |
| `patient.password.update` | PUT | `/pasien/password` | idem | `Patient\PasswordController@update` | T6 |

Urutan middleware route pasien = urutan grup: `web`, `auth`, `account:patient`, `active`. Route di `routes/patient.php` ditulis tanpa prefix (`Route::get('/', …)->name('dashboard')`, `Route::get('/profil', …)->name('profile.edit')`, dst.).

### Kelas, method, dan berkas tes (signature persis; rujukan "kerangka D")

```php
// T1 — app/Models/User.php
public function patientProfile(): HasOne   // $this->hasOne(PatientProfile::class)

// T1 — app/Models/PatientProfile.php
protected static function booted(): void   // static::saving(...) F5-R11
protected function maskedBpjs(): Attribute // get: '•••••••••'.last4 | null

// T1 — app/Services/Patients/PatientNumberGenerator.php
final class PatientNumberGenerator {
    public static function format(int $id): string;   // 'P' + 6 digit
}

// T1 — app/Policies/PatientProfilePolicy.php
final class PatientProfilePolicy {
    public function view(User $user, PatientProfile $profile): bool;
    public function update(User $user, PatientProfile $profile): bool;
}

// T1 — database/factories/PatientProfileFactory.php
public function withBpjs(string $number = '0009900000011'): static   // state: bpjs_number (hook mengisi last4)

// T1 — tests/Support/InteractsWithPatients.php (trait, namespace Tests\Support; gaya proyek: method, bukan konstanta trait)
protected function patientPassword(): string;    // 'Rahasia123'
protected function createPatient(array $userAttributes = [], array $profileAttributes = []): User;
    // argumen pemanggil MENANG atas bawaan ($userAttributes + [bawaan], $profileAttributes + [bawaan]):
    // User::factory()->patient()->create($userAttributes + ['password' => $this->patientPassword()])
    // lalu PatientProfile::factory()->create($profileAttributes + ['user_id' => …, 'full_name' => nama akun, 'email' => $user->email, 'phone' => …, 'registration_channel' => BookingChannel::Online]);
    // telepon akun disinkronkan dari profil bila null; patient_number = format(id) kecuali pemanggil memberi nilainya; mengembalikan $user->fresh() dengan relasi patientProfile ter-load (kode lengkap di Task 1)
protected function registrationPayload(array $overrides = []): array;
    // data valid V-01: full_name 'Siti Rahma', email 'siti@contoh.test', phone '0812-3456-7890', birth_date '1990-03-12', gender 'P', address 'Jl. Merpati No. 5, Pamulang', password 'Rahasia123', password_confirmation 'Rahasia123', consent '1'

// T2 — app/Services/Patients/PatientRegistrationService.php
final class PatientRegistrationService {
    public function __construct(private readonly AuditLogger $audit, private readonly SettingsService $settings) {}
    public function register(array $validated): User;
    public function updateProfile(PatientProfile $profile, array $validated, Actor $actor): PatientProfile;
}

// T2 — app/Notifications/PatientWelcomeNotification.php
final class PatientWelcomeNotification extends Notification {
    public function via(object $notifiable): array;          // ['mail']
    public function toMail(object $notifiable): MailMessage;
}

// T3 — app/Support/PatientIntendedUrl.php
final class PatientIntendedUrl {
    public static function pull(Request $request): string;   // F5-R7
}

// T3 — Auth\LoginRequest
public function authenticate(): User;   // F5-R6

// T4..T6 Form Request: Auth\RegisterRequest, Auth\ForgotPasswordRequest, Auth\ResetPasswordRequest,
//                      Patient\UpdatePatientProfileRequest, Patient\UpdatePasswordRequest
// Controller: Auth\LoginController(create, store, destroy), Auth\RegisterController(create, store),
//             Auth\PasswordResetLinkController(create, store), Auth\NewPasswordController(create, store),
//             Patient\DashboardController(index), Patient\ProfileController(edit, update), Patient\PasswordController(update)
// View: auth.login, auth.register, auth.forgot-password, auth.reset-password, patient.dashboard, patient.profile.edit
// Seeder: Database\Seeders\PatientSeeder (public const ACCOUNTS = [...]; run())
// Tes: PatientNumberGeneratorTest, PatientProfileDomainTest, PatientProfilePolicyTest, PatientRegistrationServiceTest,
//      PatientWelcomeNotificationTest, PatientLoginTest, PatientDashboardTest, PatientRegistrationTest, RegisterRequestTest,
//      PatientPasswordResetTest, PatientProfileTest, PatientPasswordChangeTest, PatientSeederTest, PatientPageMatrixTest,
//      Phase5RoutePermissionTest
```

---

### Teks UI persis (rujukan "kerangka E")

#### E.1 Login (#24) `resources/views/auth/login.blade.php`
`<x-layouts.auth title="Masuk" heading="Masuk ke Akun Pasien" subtitle="Masuk untuk mengelola profil dan pendaftaran kunjungan Anda.">`; form POST `route('login.store')` `novalidate` `@csrf`; `x-form.input name="email" label="Email" type="email" required autocomplete="username" inputmode="email" :autofocus="! $errors->any()"`; `x-form.input name="password" label="Password" type="password" required autocomplete="current-password"`; T5 menambahkan tepat di bawah field password: `<p class="small mb-3"><a href="{{ route('password.request') }}">Lupa password?</a></p>`; `x-form.checkbox name="remember" label="Ingat saya"`; `<x-loading-button class="w-100 mt-2" loading-text="Memproses…">Masuk</x-loading-button>`. Slot footer (T4): `<p class="mb-0">Belum punya akun? <a href="{{ route('register') }}">Daftar</a></p>`.

#### E.2 Registrasi (#23) `auth/register.blade.php`
`<x-layouts.auth title="Daftar Akun Pasien" subtitle="Buat akun untuk mendaftar kunjungan rawat jalan secara online." :wide="true">`; form POST `register.store` `novalidate`. Urutan field (label persis; `*` dari prop `required`):
1. `full_name` "Nama lengkap" `autocomplete="name"` `maxlength=100`
2. baris `row g-md-3`: kolom `col-md-6` `birth_date` "Tanggal lahir" `type="date"` `autocomplete="bday"` `max="{{ now()->subDay()->toDateString() }}"`; kolom `col-md-6` `x-form.radio-group name="gender" legend="Jenis kelamin" :options="\App\Enums\Gender::options()" required inline`
3. `phone` "Nomor telepon" `type="tel"` `autocomplete="tel"` `inputmode="tel"` `help="Nomor seluler aktif, mis. 081234567890."`
4. `email` "Email" `type="email"` `autocomplete="email"` `inputmode="email"`
5. `x-form.textarea name="address" label="Alamat" required rows="3" maxlength="500"`
6. `password` "Password" `type="password"` `autocomplete="new-password"` `help="Minimal 8 karakter, berisi huruf dan angka."`
7. `password_confirmation` "Konfirmasi password" `type="password"` `autocomplete="new-password"`
8. Persetujuan (markup manual pola `public/contact.blade.php` baris 100–110): checkbox `id="consent" name="consent" value="1"`, label `Saya telah membaca dan menyetujui <a href="{{ route('privacy') }}" target="_blank" rel="noopener">Kebijakan Privasi</a><span class="visually-hidden"> (terbuka di tab baru)</span>. <span class="required-mark" aria-hidden="true">*</span>`; galat `#consent-error` + `aria-invalid`/`aria-describedby`.
9. `<x-loading-button class="w-100 mt-2" loading-text="Memproses…">Daftar</x-loading-button>`
Footer: `<p class="mb-0">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>`. Tidak ada field NIK/BPJS.

#### E.3 Lupa password (#25) `auth/forgot-password.blade.php`
`<x-layouts.auth title="Lupa Password" subtitle="Masukkan email akun Anda. Kami akan mengirim tautan untuk mengatur ulang password jika email terdaftar.">`; `email` "Email" (autocomplete `username`, autofocus tanpa galat); tombol "Kirim Tautan"; footer `<a href="{{ route('login') }}">Kembali ke halaman masuk</a>`.

#### E.4 Reset password (#26) `auth/reset-password.blade.php`
`<x-layouts.auth title="Reset Password" heading="Atur Ulang Password" subtitle="Buat password baru untuk akun Anda.">`; hidden `token`; `email` "Email" (value `old('email', $email)`; atribut `readonly` bila `$email` non-kosong; `autocomplete="username"`); `password` "Password baru" (`new-password`, help sama E.2); `password_confirmation` "Konfirmasi password baru"; tombol "Simpan Password Baru"; footer `<a href="{{ route('password.request') }}">Minta tautan baru</a>`.

#### E.5 Dashboard (#27) — F5-R22. Profil (#28) — `<x-layouts.patient title="Profil dan Akun">`, slot breadcrumb `<x-breadcrumb home="patient.dashboard" :items="[['label' => 'Profil dan Akun']]" />` (akar berlabel "Portal Pasien", T6 mengubah komponen: label akar `match ($home) { 'admin.dashboard' => 'Dasbor', 'patient.dashboard' => 'Portal Pasien', default => 'Beranda' }`); `<h1>` "Profil dan Akun"; `<x-form.errors-summary />`; teks "No. pasien: {patient_number}"; `<h2>` "Data Profil": form PUT `patient.profile.update` (field E.2 1–5, nilai dari profil; `birth_date` `->format('Y-m-d')`; BPJS F5-R19), tombol "Simpan Profil"; `<h2>` "Ganti Password": form PUT `patient.password.update` dengan `<input type="text" name="username" value="{{ $user->email }}" autocomplete="username" readonly hidden>`, `current_password` "Password saat ini" (`current-password`), `password` "Password baru" (help E.2), `password_confirmation` "Konfirmasi password baru", tombol "Simpan Password".

#### E.6 Pesan (persis)
- Validasi: 12 V-01, V-02, V-04, V-05, V-06, V-07 (digest-rules §3) di `messages()` tiap Form Request; termasuk `string`/array: "{Label} tidak valid." untuk `*.string` bila dipakai, `remember.boolean` "Pilihan ingat saya tidak valid.", `remove_bpjs.boolean` "Pilihan hapus nomor BPJS tidak valid.".
- V-01 email unik: "Email ini sudah terdaftar. Silakan masuk atau gunakan email lain."; V-06 email unik: "Email ini sudah terdaftar. Gunakan email lain.".
- Flash: login gagal (galat field `email`) "Email atau password salah."; logout "Anda telah keluar."; registrasi "Registrasi berhasil. Selamat datang, {nama}."; registrasi gagal sistem "Registrasi belum berhasil karena gangguan sistem. Silakan coba lagi."; reset diminta `__('passwords.sent')`; reset selesai `__('passwords.reset')`; token salah `__('passwords.token')` (galat field `email`); profil "Profil berhasil diperbarui."; password "Password berhasil diganti."; throttle = handler global "Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik." (D-23; tidak ada teks per form).

---

### Data seeder pasien (13 §10.1; T7; rujukan "kerangka F")

Password `config('app.demo_password')`; telepon akun = telepon profil; alamat "Jl. Contoh No. {n}, Pamulang, Tangerang Selatan"; telepon `08120000000{n}` (pola `0812-0000-00NN` ternormalisasi, 12 digit: `081200000001` … `081200000007`); `consent_at = now()`, `consent_version` = pengaturan `privacy.consent_version`; `registration_channel = online`; `created_by_user_id = null`; `is_active = true`; `patient_number` = `PatientNumberGenerator::format(id)` (P000001… pada DB baru).

| n | Nama | gender | birth_date | Email | BPJS |
|---|---|---|---|---|---|
| 1 | Anisa Rahmawati | P | 1994-05-12 | pasien@unpamhospital.test | 0009900000011 |
| 2 | Budi Hartanto | L | 1985-11-03 | budi.hartanto@contoh.test | 0009900000022 |
| 3 | Dewi Susilowati | P | 1990-07-21 | dewi.susilowati@contoh.test | — |
| 4 | Fitri Handayani | P | 1998-01-30 | fitri.handayani@contoh.test | 0009900000033 |
| 5 | Gilang Saputra | L | 2001-09-09 | gilang.saputra@contoh.test | — |
| 6 | Kartika Dewi Anggraini | P | 1979-12-15 | kartika.anggraini@contoh.test | — |
| 7 | Mulyani Astuti | P | 1958-02-20 | mulyani.astuti@contoh.test | — |

Idempoten per email (akun ada → `warn("Akun pasien demo {email} sudah ada, dilewati.")`); satu `DB::transaction` per pasien; guard `local|testing` (melempar `RuntimeException` seperti `DemoUserSeeder`); tanpa audit, tanpa notifikasi (seeder menulis langsung lewat model, BUKAN `PatientRegistrationService`).

---

## Struktur berkas (peta tanggung jawab)

Dihimpun dari daftar **Files** tiap tugas. "baru" = berkas dibuat, "ubah" = berkas yang sudah ada diubah. Rujukan "kerangka C.2" di teks tugas menunjuk ke sini.

```
README.md                                                                                 (T8 ubah)
app/Http/Controllers/Auth/LoginController.php                                             (T3 baru)
app/Http/Controllers/Auth/NewPasswordController.php                                       (T5 baru)
app/Http/Controllers/Auth/PasswordResetLinkController.php                                 (T5 baru)
app/Http/Controllers/Auth/RegisterController.php                                          (T4 baru)
app/Http/Controllers/Patient/DashboardController.php                                      (T3 baru)
app/Http/Controllers/Patient/PasswordController.php                                       (T6 baru)
app/Http/Controllers/Patient/ProfileController.php                                        (T6 baru)
app/Http/Middleware/EnsureAccountIsActive.php                                             (T3 ubah)
app/Http/Requests/Admin/UpdatePasswordRequest.php                                         (T6 ubah)
app/Http/Requests/Auth/ForgotPasswordRequest.php                                          (T5 baru)
app/Http/Requests/Auth/LoginRequest.php                                                   (T3 baru)
app/Http/Requests/Auth/RegisterRequest.php                                                (T4 baru)
app/Http/Requests/Auth/ResetPasswordRequest.php                                           (T5 baru)
app/Http/Requests/Patient/UpdatePasswordRequest.php                                       (T6 baru)
app/Http/Requests/Patient/UpdatePatientProfileRequest.php                                 (T6 baru)
app/Models/PatientProfile.php                                                             (T1 ubah)
app/Models/User.php                                                                       (T1 ubah)
app/Notifications/PatientWelcomeNotification.php                                          (T2 baru)
app/Policies/PatientProfilePolicy.php                                                     (T1 baru)
app/Services/Patients/PatientNumberGenerator.php                                          (T1 baru)
app/Services/Patients/PatientRegistrationService.php                                      (T2 baru)
app/Support/PatientIntendedUrl.php                                                        (T3 baru)
bootstrap/app.php                                                                         (T3 ubah)
database/factories/PatientProfileFactory.php                                              (T1 ubah)
database/seeders/DemoSeeder.php                                                           (T7 ubah)
database/seeders/PatientSeeder.php                                                        (T7 baru)
docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/00-keputusan-inti.md           (T8 ubah)
docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/04-peran-hak-akses.md          (T8 ubah)
docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/06-alur.md                     (T8 ubah)
docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/11-route-controller-model-service.md(T8 ubah)
docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/12-aturan-bisnis-validasi-keamanan.md(T8 ubah)
docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/13-data-contoh.md              (T8 ubah)
docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/15-pengujian-penerimaan.md     (T8 ubah)
docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/16-instalasi-operasional.md    (T8 ubah)
docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/17-keputusan-konfirmasi.md     (T8 ubah)
lang/id.json                                                                              (T2 baru, T5 ubah)
lang/id/passwords.php                                                                     (T5 ubah)
resources/scss/_shared.scss                                                               (T3 ubah)
resources/views/auth/forgot-password.blade.php                                            (T5 baru)
resources/views/auth/login.blade.php                                                      (T3 baru, T4 ubah, T5 ubah)
resources/views/auth/register.blade.php                                                   (T4 baru)
resources/views/auth/reset-password.blade.php                                             (T5 baru)
resources/views/components/breadcrumb.blade.php                                           (T6 ubah)
resources/views/components/form/checkbox.blade.php                                        (T3 ubah)
resources/views/components/layouts/auth.blade.php                                         (T3 ubah)
resources/views/patient/dashboard.blade.php                                               (T3 baru, T6 ubah)
resources/views/patient/profile/edit.blade.php                                            (T6 baru)
routes/patient.php                                                                        (T3 baru, T6 ubah)
routes/web.php                                                                            (T3 ubah, T4 ubah, T5 ubah)
tests/Feature/Access/AdminPageMatrixTest.php                                              (T3 ubah)
tests/Feature/Access/PatientPageMatrixTest.php                                            (T8 baru)
tests/Feature/Access/Phase5RoutePermissionTest.php                                        (T8 baru)
tests/Feature/Auth/AdminAreaAccessTest.php                                                (T3 ubah)
tests/Feature/Auth/AdminPasswordChangeTest.php                                            (T6 ubah)
tests/Feature/Auth/PatientLoginTest.php                                                   (T3 baru)
tests/Feature/Auth/PatientPasswordResetTest.php                                           (T5 baru)
tests/Feature/Auth/PatientProfilePolicyTest.php                                           (T1 baru)
tests/Feature/Auth/PatientRegistrationTest.php                                            (T4 baru)
tests/Feature/Auth/RegisterRequestTest.php                                                (T4 baru)
tests/Feature/Http/GuestRedirectTest.php                                                  (T3 ubah)
tests/Feature/LocalizationTest.php                                                        (T5 ubah)
tests/Feature/Models/PatientProfileDomainTest.php                                         (T1 baru)
tests/Feature/Patient/PatientDashboardTest.php                                            (T3 baru)
tests/Feature/Patient/PatientPasswordChangeTest.php                                       (T6 baru)
tests/Feature/Patient/PatientProfileTest.php                                              (T6 baru)
tests/Feature/Patient/PatientRegistrationServiceTest.php                                  (T2 baru)
tests/Feature/Patient/PatientWelcomeNotificationTest.php                                  (T2 baru)
tests/Feature/Security/SecurityHeadersTest.php                                            (T8 ubah)
tests/Feature/Seeding/DemoSeederInvariantsTest.php                                        (T7 ubah)
tests/Feature/Seeding/DemoSeederWiringTest.php                                            (T7 ubah)
tests/Feature/Seeding/FacilitySeedersTest.php                                             (T7 ubah)
tests/Feature/Seeding/PatientSeederTest.php                                               (T7 baru)
tests/Feature/Seeding/SeederProductionGuardTest.php                                       (T7 ubah)
tests/Feature/View/LayoutsTest.php                                                        (T3 ubah)
tests/Feature/View/NavigationComponentsTest.php                                           (T6 ubah)
tests/Feature/View/RawOutputScanTest.php                                                  (T8 ubah)
tests/Support/InteractsWithPatients.php                                                   (T1 baru)
tests/Unit/FrontendManifestTest.php                                                       (T3 ubah)
tests/Unit/Services/PatientNumberGeneratorTest.php                                        (T1 baru)
```

---

### Task 1: Domain pasien (relasi, hook last4, nomor pasien, Policy, state factory, trait tes)

**Files:**
- Create: `app/Services/Patients/PatientNumberGenerator.php`
- Create: `app/Policies/PatientProfilePolicy.php`
- Create: `tests/Support/InteractsWithPatients.php`
- Modify: `app/Models/User.php` (relasi `patientProfile()`)
- Modify: `app/Models/PatientProfile.php` (`booted()` hook `saving`, accessor `maskedBpjs`; berkas ditulis ulang utuh)
- Modify: `database/factories/PatientProfileFactory.php` (state `withBpjs()`)
- Test: `tests/Unit/Services/PatientNumberGeneratorTest.php` (baru; direktori `tests/Unit/Services` belum ada)
- Test: `tests/Feature/Models/PatientProfileDomainTest.php` (baru)
- Test: `tests/Feature/Auth/PatientProfilePolicyTest.php` (baru)
- Tidak diubah: seluruh migration, `BookingModelsTest`, `UserAccountTest`, dan tes lama lain (lihat Catatan teknis: tidak ada yang patah).

**Interfaces:**
- Consumes: `App\Models\User`, `App\Models\PatientProfile`, `Database\Factories\PatientProfileFactory` (definisi tetap: `patient_number` acak `P######`, `bpjs_number` dan `bpjs_number_last4` null, kanal `loket`), `App\Enums\BookingChannel`, `Tests\Support\InteractsWithStaff::createStaff()` / `seedRoles()`, `Database\Seeders\RolePermissionSeeder` (izin `patients.view` / `patients.manage`), `Gate::before` Super Admin di `AppServiceProvider`.
- Produces (persis kerangka D):
  - `User::patientProfile(): HasOne` (`PatientProfile`, FK `user_id`).
  - `PatientProfile::booted()`: `saving` mengisi `bpjs_number_last4` setiap `bpjs_number` kotor (F5-R11); accessor `masked_bpjs` (`maskedBpjs()`): `'•••••••••'` (9 titik) + last4, atau `null`.
  - `App\Services\Patients\PatientNumberGenerator::format(int $id): string` (`P` + minimal 6 digit).
  - `App\Policies\PatientProfilePolicy::view()` / `::update()` (F5-R14), ditemukan otomatis.
  - `PatientProfileFactory::withBpjs(string $number = '0009900000011'): static`.
  - Trait `Tests\Support\InteractsWithPatients`: `patientPassword()` (`'Rahasia123'`), `createPatient(array $userAttributes = [], array $profileAttributes = []): User`, `registrationPayload(array $overrides = []): array`.

**Test IDs:** SEK-12 (nilai mentah `bpjs_number` terenkripsi di DB; `$hidden`; tampilan tersamar tidak membuka nomor lengkap), PAS-05 (dasar: kepemilikan profil lewat Policy), F5-R10, F5-R11, F5-R12, F5-R14, K-07 (satu akun satu profil, sisi DB `user_id` UNIQUE), WLK-06 (dasar: profil walk-in tanpa akun tidak dapat dimiliki pasien mana pun).

**Catatan teknis (diverifikasi dari source):**
- **Hook tidak mematahkan tes lama.** `git grep PatientProfile -- tests database app` menemukan pemakai: `BookingModelsTest`, `InteractsWithSchedulesTest`, `ConcurrencySpikeTest`, `AdminPracticeSessionsTest`, `AdminScheduleTemplatesTest`, `MediaPolicyTest`, `tests/Support/InteractsWithSchedules.php`, `AppointmentFactory`. Hanya `BookingModelsTest::test_patient_profile_bpjs_number_is_encrypted_and_hidden` (baris 54) mengisi `bpjs_number` (`'0001234567890'`); hook kini juga mengisi `bpjs_number_last4 = '7890'`, dan tes itu tidak menyentuh kolom tersebut (hanya memeriksa nilai mentah terenkripsi, dekripsi, dan `toArray()`), jadi tetap lulus. `test_patient_profile_system_columns_are_not_mass_assignable` memakai `new PatientProfile(['patient_number' => ...])` dan tidak terpengaruh `booted()`. Tidak ada tes lama yang menulis `bpjs_number_last4` (`git grep -n "bpjs_number_last4" tests` hanya menemukan `ScheduleSchemaTest` yang memeriksa tipe kolom `char(4)` dan daftar kolom). `MediaPolicyTest` hanya memakai nama kelas `PatientProfile::class` sebagai tipe pemilik yang belum dipetakan. Definisi factory tetap `bpjs_number => null`: untuk model baru `isDirty('bpjs_number')` bernilai true (kunci tidak ada di `original`) dan hook menulis `bpjs_number_last4 = null`, yang sama dengan nilai factory.
- **`UserAccountTest` tidak mengunci daftar relasi `User`.** Ia hanya mengunci `$fillable`, `$hidden`, casts, factory, scope, dan helper (`test_fillable_and_hidden_follow_the_blueprint`, `test_casts_follow_the_blueprint`, ...). Satu-satunya tes relasi, `test_lazy_loading_a_relation_on_a_collection_is_rejected`, memakai `notifications`. Menambah `patientProfile()` aman.
- **Hook memakai `isDirty('bpjs_number')`, bukan perbandingan nilai.** Kolom bercast `encrypted` menghasilkan ciphertext berbeda pada setiap penugasan, dan `originalIsEquivalent()` tidak membandingkan nilai terdekripsi untuk cast `encrypted` (cabang `encrypted` hanya relevan bila ada kunci enkripsi lama; selain itu perbandingan jatuh ke pembandingan numerik terakhir yang bernilai false untuk ciphertext), sehingga menugaskan ulang nomor yang sama dianggap kotor. Akibatnya last4 dihitung ulang dari nilai terdekripsi dan hasilnya sama, jadi tidak berbahaya. Menyimpan kolom lain (mis. `full_name`) tidak menyentuh `bpjs_number` dan tidak mengubah ciphertext maupun last4. Hook berjalan di event `saving` setelah semua atribut terisi, sehingga `forceFill(['bpjs_number_last4' => 'XXXX'])` bersama `bpjs_number` kotor tidak dapat memalsukan last4 (diuji).
- **Hook hanya jalan lewat model.** `PatientProfile::factory()->make()`, `insert()` massal, dan `DB::table()` tidak memicunya. Seluruh jalur fase 5 (service, seeder lewat model) memakai `save()`.
- **Isi last4 = empat karakter terakhir** dari nomor yang tersimpan (`mb_substr($number, -4)`), bukan "empat digit terakhir setelah menyaring non-digit": normalisasi (buang spasi dan `-`) adalah tugas Form Request (digest-rules, normalisasi sebelum validasi). Nomor lebih pendek dari empat karakter menghasilkan last4 lebih pendek (kolom `CHAR(4)`).
- **`$hidden = ['bpjs_number']` dan `masked_bpjs`.** Accessor bergaya `Attribute::get()` tidak masuk `toArray()` tanpa `$appends`; `bpjs_number_last4` tidak disembunyikan (bukan rahasia; empat karakter itu memang dirender tersamar di UI).
- **Policy.** `Gate::before` (`AppServiceProvider` baris 51) memberi Super Admin `true` sebelum Policy dipanggil, sehingga Policy tidak perlu mengecek peran. Izin menurut `RolePermissionSeeder::ROLES`: `patients.view` dan `patients.manage` HANYA dimiliki `registration-officer` (dan `super-admin` yang memegang semua izin); `content-admin` tidak memilikinya. Pasien tidak punya peran/izin Spatie. `$user->can('patients.view')` pada pasien (atau pada DB yang belum di-seed) mengembalikan `false` karena Spatie menelan `PermissionDoesNotExist`. Kepemilikan: `user_id` profil tidak null DAN sama dengan `$user->id`; profil walk-in (`user_id` null) tidak dimiliki siapa pun. Penemuan otomatis Laravel (`App\Policies\{Model}Policy`) dipakai oleh seluruh Policy proyek; tidak ada `Gate::policy()` manual.
- **Pola trait.** `InteractsWithStaff` memakai method, bukan konstanta trait (PHP 8.2 melarang konstanta di trait). `createPatient()` memakai `$userAttributes + [default]` dan bukan `[default] + $userAttributes`: operator `+` pada array mempertahankan kunci sisi KIRI, sehingga urutan kerangka D (`['password' => ...] + $userAttributes`) justru akan membuat nilai bawaan menang atas argumen pemanggil. Lihat Catatan penulis.
- **`UserFactory` memberi password `Hash::make('password')`**, tetapi `createPatient()` menimpanya dengan `'Rahasia123'` (cast `hashed` meng-hash sekali). Dengan `BCRYPT_ROUNDS=4` (phpunit.xml) hash berawalan `$2y$04$`.
- **Mode ketat.** `User::fresh('patientProfile')` memuat relasi sehingga tes tidak terkena `LazyLoadingViolationException`; model tunggal hasil `create()` memang tidak dikenai pencegahan lazy loading, hanya koleksi (lihat `UserAccountTest::test_lazy_loading_a_relation_on_a_collection_is_rejected`).
- **DB pengembangan.** Implementer tidak menjalankan `migrate`/`db:seed` pada `unpam_hospital`; tes memakai `unpam_hospital_test` (`RefreshDatabase`). Tidak ada migration baru.
- Jumlah tes baru: 45 (`PatientNumberGeneratorTest` 9, `PatientProfileDomainTest` 25 termasuk 3 kasus provider, `PatientProfilePolicyTest` 11 termasuk 3 kasus provider).

- [ ] **Step 1: Verifikasi prasyarat (cabang dan keadaan awal)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
git grep -n "bpjs_number_last4" -- tests
& $PHP artisan test --filter="BookingModelsTest|UserAccountTest|MediaPolicyTest"
```
Expected: `fase-5-akun-pasien`; `git status` kosong atau hanya `?? .superpowers/`; `git grep` hanya menampilkan `tests/Feature/Database/ScheduleSchemaTest.php` (dua baris: tipe kolom dan daftar kolom); ketiga kelas tes lama PASS (baseline master: 5200 tes lulus). Apache dan MySQL harus sudah di-Start di `C:\xampp3\xampp-control.exe`.

- [ ] **Step 2: Tulis tes `PatientNumberGeneratorTest` (tool Write)**

`tests/Unit/Services/PatientNumberGeneratorTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Services\Patients\PatientNumberGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * F5-R10: nomor pasien = "P" + id profil dengan lebar minimal 6 digit (06/09/14). Murni, tanpa DB.
 */
class PatientNumberGeneratorTest extends TestCase
{
    /** @return array<string, array{0: int, 1: string}> */
    public static function formatCases(): array
    {
        return [
            'id 1' => [1, 'P000001'],
            'id 7' => [7, 'P000007'],
            'id 42' => [42, 'P000042'],
            'id 123' => [123, 'P000123'],
            'id terbesar enam digit' => [999999, 'P999999'],
            'id sejuta melebar menjadi tujuh digit' => [1000000, 'P1000000'],
            'id delapan digit' => [12345678, 'P12345678'],
        ];
    }

    #[DataProvider('formatCases')]
    public function test_format_pads_the_id_to_six_digits(int $id, string $expected): void
    {
        $this->assertSame($expected, PatientNumberGenerator::format($id));
    }

    public function test_numbers_below_one_million_are_always_seven_characters_and_sortable(): void
    {
        $previous = '';

        foreach ([1, 9, 10, 99, 100, 999, 1000, 99999, 100000, 999999] as $id) {
            $number = PatientNumberGenerator::format($id);

            $this->assertSame(7, strlen($number), "Panjang nomor untuk id {$id}");
            $this->assertMatchesRegularExpression('/^P\d{6}$/', $number);
            $this->assertTrue(strcmp($number, $previous) > 0, 'Urutan leksikografis mengikuti urutan id.');

            $previous = $number;
        }
    }

    public function test_the_longest_realistic_number_still_fits_the_ten_character_column(): void
    {
        // patient_profiles.patient_number = VARCHAR(10): "P" + sembilan digit.
        $this->assertSame(10, strlen(PatientNumberGenerator::format(999999999)));
    }
}
```

- [ ] **Step 3: Jalankan tes generator, harus GAGAL (RED)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PatientNumberGeneratorTest
```
Expected: FAIL, 9 tes ERROR dengan `Class "App\Services\Patients\PatientNumberGenerator" not found`.

- [ ] **Step 4: Implementasi `PatientNumberGenerator` (tool Write)**

`app/Services/Patients/PatientNumberGenerator.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Patients;

/**
 * Format nomor pasien (F5-R10; 06, 09, 14): "P" + id profil dengan lebar minimal 6 digit, mis. P000123.
 * Id lebih dari enam digit memperlebar nomor (P1000000), tidak dipotong.
 *
 * Murni dan tanpa DB. Id baru diketahui setelah insert, sehingga PatientRegistrationService mengisi
 * kolom `patient_number` dalam transaksi yang sama (bukan lewat event model).
 */
final class PatientNumberGenerator
{
    public static function format(int $id): string
    {
        return 'P'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
```

- [ ] **Step 5: Jalankan tes generator, harus LULUS (GREEN)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PatientNumberGeneratorTest
```
Expected: PASS, 9 tes.

- [ ] **Step 6: Tulis trait tes `InteractsWithPatients` (tool Write)**

`tests/Support/InteractsWithPatients.php`:

```php
<?php

namespace Tests\Support;

use App\Enums\BookingChannel;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Patients\PatientNumberGenerator;

/**
 * Pembantu tes untuk akun pasien berprofil (fase 5). Dipakai bersama RefreshDatabase.
 */
trait InteractsWithPatients
{
    /** Password bawaan akun pasien uji (pola AdminLoginTest::PASSWORD). */
    protected function patientPassword(): string
    {
        return 'Rahasia123';
    }

    /**
     * Membuat akun pasien aktif beserta profil online yang terhubung: nomor pasien berformat
     * PatientNumberGenerator, email dan nama profil mengikuti akun, telepon akun mengikuti telepon profil.
     * Argumen pemanggil menimpa nilai bawaan. Relasi `patientProfile` sudah termuat pada hasil.
     *
     * @param  array<string, mixed>  $userAttributes
     * @param  array<string, mixed>  $profileAttributes
     */
    protected function createPatient(array $userAttributes = [], array $profileAttributes = []): User
    {
        $user = User::factory()->patient()->create($userAttributes + ['password' => $this->patientPassword()]);

        $profile = PatientProfile::factory()->create($profileAttributes + [
            'user_id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '081234567890',
            'registration_channel' => BookingChannel::Online,
        ]);

        if (! array_key_exists('patient_number', $profileAttributes)) {
            $profile->forceFill(['patient_number' => PatientNumberGenerator::format($profile->id)])->save();
        }

        if ($user->phone === null) {
            $user->forceFill(['phone' => $profile->phone])->save();
        }

        return $user->fresh('patientProfile');
    }

    /**
     * Data registrasi valid menurut 12 V-01 (belum dinormalisasi: telepon masih berformat dengan tanda hubung).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Siti Rahma',
            'email' => 'siti@contoh.test',
            'phone' => '0812-3456-7890',
            'birth_date' => '1990-03-12',
            'gender' => 'P',
            'address' => 'Jl. Merpati No. 5, Pamulang',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
            'consent' => '1',
        ], $overrides);
    }
}
```

- [ ] **Step 7: Tulis tes `PatientProfileDomainTest` (tool Write)**

`tests/Feature/Models/PatientProfileDomainTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Enums\BookingChannel;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Patients\PatientNumberGenerator;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\TestCase;

/**
 * Domain pasien fase 5 (T1): hook last4 BPJS (F5-R11), tampilan tersamar, relasi akun-profil (F5-R12),
 * state factory, dan pembantu tes. SEK-12: nomor BPJS lengkap terenkripsi di DB dan tidak pernah diserialisasi.
 */
class PatientProfileDomainTest extends TestCase
{
    use InteractsWithPatients;
    use RefreshDatabase;

    private const NUMBER = '0009900000011';

    private function storedLast4(PatientProfile $profile): mixed
    {
        return DB::table('patient_profiles')->where('id', $profile->id)->value('bpjs_number_last4');
    }

    private function storedNumber(PatientProfile $profile): mixed
    {
        return DB::table('patient_profiles')->where('id', $profile->id)->value('bpjs_number');
    }

    // --- Hook last4 (F5-R11) ---

    public function test_creating_a_profile_with_a_bpjs_number_fills_last4(): void
    {
        $profile = PatientProfile::factory()->create(['bpjs_number' => self::NUMBER]);

        $this->assertSame('0011', $profile->bpjs_number_last4);
        $this->assertSame('0011', $this->storedLast4($profile));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function last4Cases(): array
    {
        return [
            'nomor 13 digit' => ['0009900000011', '0011'],
            'tepat empat karakter' => ['1234', '1234'],
            'kurang dari empat karakter' => ['987', '987'],
        ];
    }

    #[DataProvider('last4Cases')]
    public function test_last4_takes_the_last_four_characters(string $number, string $expected): void
    {
        $profile = PatientProfile::factory()->create(['bpjs_number' => $number]);

        $this->assertSame($expected, $this->storedLast4($profile));
    }

    public function test_changing_the_number_updates_last4(): void
    {
        $profile = PatientProfile::factory()->withBpjs()->create();

        $profile->bpjs_number = '0009900000022';
        $profile->save();

        $this->assertSame('0022', $profile->bpjs_number_last4);
        $this->assertSame('0022', $this->storedLast4($profile));
        $this->assertSame('0009900000022', PatientProfile::query()->findOrFail($profile->id)->bpjs_number);
    }

    public function test_clearing_the_number_clears_last4(): void
    {
        $profile = PatientProfile::factory()->withBpjs()->create();

        $profile->bpjs_number = null;
        $profile->save();

        $this->assertNull($this->storedNumber($profile));
        $this->assertNull($this->storedLast4($profile));
        $this->assertNull(PatientProfile::query()->findOrFail($profile->id)->masked_bpjs);
    }

    public function test_saving_other_columns_keeps_the_number_and_last4(): void
    {
        $profile = PatientProfile::factory()->withBpjs()->create();
        $ciphertextBefore = $this->storedNumber($profile);

        $profile->fill(['full_name' => 'Nama Baru'])->save();

        $this->assertSame($ciphertextBefore, $this->storedNumber($profile), 'Ciphertext tidak ditulis ulang.');
        $this->assertSame('0011', $this->storedLast4($profile));
    }

    public function test_reassigning_the_same_number_keeps_last4(): void
    {
        $profile = PatientProfile::factory()->withBpjs()->create();

        $profile->bpjs_number = self::NUMBER;
        $profile->save();

        $this->assertSame('0011', $this->storedLast4($profile));
        $this->assertSame(self::NUMBER, PatientProfile::query()->findOrFail($profile->id)->bpjs_number);
    }

    public function test_profile_without_a_number_has_null_last4(): void
    {
        $profile = PatientProfile::factory()->create();

        $this->assertNull($this->storedNumber($profile));
        $this->assertNull($this->storedLast4($profile));
    }

    public function test_last4_cannot_be_forged_with_force_fill(): void
    {
        $profile = PatientProfile::factory()->create();

        $profile->forceFill(['bpjs_number' => '0009900000022', 'bpjs_number_last4' => '9999'])->save();

        $this->assertSame('0022', $this->storedLast4($profile));
    }

    public function test_last4_is_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);

        (new PatientProfile)->fill(['bpjs_number_last4' => '9999']);
    }

    public function test_bpjs_number_is_mass_assignable_and_the_hook_applies(): void
    {
        $profile = PatientProfile::factory()->create();

        $profile->fill(['bpjs_number' => '0009900000033'])->save();

        $this->assertSame('0033', $this->storedLast4($profile));
    }

    // --- Tampilan tersamar ---

    public function test_masked_bpjs_shows_only_the_last_four_digits(): void
    {
        $profile = PatientProfile::factory()->withBpjs()->create();

        $this->assertSame('•••••••••0011', $profile->masked_bpjs);
        $this->assertSame(str_repeat('•', 9).'0011', PatientProfile::query()->findOrFail($profile->id)->masked_bpjs);
        $this->assertStringNotContainsString('00099', $profile->masked_bpjs);
    }

    public function test_masked_bpjs_is_null_without_a_number(): void
    {
        $this->assertNull(PatientProfile::factory()->create()->masked_bpjs);
    }

    public function test_masked_bpjs_is_not_serialized(): void
    {
        $profile = PatientProfile::factory()->withBpjs()->create();
        $fresh = PatientProfile::query()->findOrFail($profile->id);

        $this->assertArrayNotHasKey('masked_bpjs', $fresh->toArray());
        $this->assertStringNotContainsString(self::NUMBER, (string) $fresh->masked_bpjs);
    }

    // --- SEK-12: enkripsi dan $hidden ---

    public function test_bpjs_number_is_stored_encrypted(): void
    {
        $profile = PatientProfile::factory()->withBpjs()->create();

        $raw = (string) $this->storedNumber($profile);

        $this->assertNotSame(self::NUMBER, $raw);
        $this->assertStringNotContainsString(self::NUMBER, $raw);
        $this->assertSame(self::NUMBER, Crypt::decryptString($raw));
        $this->assertSame(self::NUMBER, PatientProfile::query()->findOrFail($profile->id)->bpjs_number);
    }

    public function test_bpjs_number_is_hidden_from_serialization(): void
    {
        $profile = PatientProfile::factory()->withBpjs()->create();
        $fresh = PatientProfile::query()->findOrFail($profile->id);

        $this->assertSame(['bpjs_number'], $fresh->getHidden());
        $this->assertArrayNotHasKey('bpjs_number', $fresh->toArray());
        $this->assertArrayNotHasKey('bpjs_number', $fresh->attributesToArray());
        $this->assertStringNotContainsString(self::NUMBER, $fresh->toJson());
        $this->assertStringNotContainsString(self::NUMBER, (string) json_encode($fresh));
    }

    // --- Factory ---

    public function test_with_bpjs_state_sets_default_and_custom_numbers(): void
    {
        $default = PatientProfile::factory()->withBpjs()->create();
        $custom = PatientProfile::factory()->withBpjs('0009900000044')->create();

        $this->assertSame(self::NUMBER, $default->fresh()->bpjs_number);
        $this->assertSame('0011', $default->fresh()->bpjs_number_last4);
        $this->assertSame('0009900000044', $custom->fresh()->bpjs_number);
        $this->assertSame('0044', $custom->fresh()->bpjs_number_last4);
    }

    // --- Relasi akun-profil (F5-R12, K-07) ---

    public function test_user_has_one_patient_profile(): void
    {
        $user = $this->createPatient();

        $relation = $user->patientProfile();

        $this->assertInstanceOf(HasOne::class, $relation);
        $this->assertSame('user_id', $relation->getForeignKeyName());
        $this->assertInstanceOf(PatientProfile::class, $user->patientProfile);
        $this->assertSame($user->id, $user->patientProfile->user_id);
    }

    public function test_user_without_a_profile_has_a_null_relation(): void
    {
        $staff = User::factory()->staff()->create();
        $bare = User::factory()->patient()->create();

        $this->assertNull($staff->patientProfile);
        $this->assertNull($bare->patientProfile);
    }

    public function test_one_account_has_at_most_one_profile(): void
    {
        $user = $this->createPatient();

        $this->expectException(UniqueConstraintViolationException::class);

        PatientProfile::factory()->create(['user_id' => $user->id]);
    }

    public function test_eager_loading_the_relation_for_many_users_avoids_lazy_loading(): void
    {
        $this->createPatient();
        $this->createPatient();
        $this->createPatient();

        $users = User::query()->patients()->with('patientProfile')->get();

        $this->assertCount(3, $users);

        foreach ($users as $user) {
            $this->assertNotNull($user->patientProfile);
        }
    }

    // --- Pembantu tes ---

    public function test_create_patient_builds_a_consistent_account(): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;

        $this->assertTrue($user->isPatient());
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Rahasia123', $user->password));
        $this->assertSame($this->patientPassword(), 'Rahasia123');
        $this->assertTrue($user->relationLoaded('patientProfile'));
        $this->assertSame($user->id, $profile->user_id);
        $this->assertSame(PatientNumberGenerator::format($profile->id), $profile->patient_number);
        $this->assertSame($user->email, $profile->email);
        $this->assertSame($user->name, $profile->full_name);
        $this->assertSame('081234567890', $profile->phone);
        $this->assertSame($profile->phone, $user->phone);
        $this->assertSame(BookingChannel::Online, $profile->registration_channel);
        $this->assertNull($profile->created_by_user_id);
        $this->assertTrue($profile->is_active);
    }

    public function test_create_patient_accepts_overrides(): void
    {
        $user = $this->createPatient(
            ['name' => 'Budi Hartanto', 'email' => 'budi@contoh.test', 'password' => 'Lain12345'],
            ['phone' => '081200000009', 'bpjs_number' => self::NUMBER, 'is_active' => false],
        );

        $this->assertTrue(Hash::check('Lain12345', $user->password));
        $this->assertFalse(Hash::check('Rahasia123', $user->password));
        $this->assertSame('budi@contoh.test', $user->email);
        $this->assertSame('Budi Hartanto', $user->patientProfile->full_name);
        $this->assertSame('081200000009', $user->patientProfile->phone);
        $this->assertSame('081200000009', $user->phone);
        $this->assertSame('0011', $user->patientProfile->bpjs_number_last4);
        $this->assertFalse($user->patientProfile->is_active);
    }

    public function test_registration_payload_matches_the_v01_example(): void
    {
        $payload = $this->registrationPayload();

        $this->assertSame([
            'full_name' => 'Siti Rahma',
            'email' => 'siti@contoh.test',
            'phone' => '0812-3456-7890',
            'birth_date' => '1990-03-12',
            'gender' => 'P',
            'address' => 'Jl. Merpati No. 5, Pamulang',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
            'consent' => '1',
        ], $payload);

        $overridden = $this->registrationPayload(['email' => 'lain@contoh.test', 'consent' => null]);

        $this->assertSame('lain@contoh.test', $overridden['email']);
        $this->assertNull($overridden['consent']);
        $this->assertSame('Siti Rahma', $overridden['full_name']);
    }
}
```

- [ ] **Step 8: Jalankan tes domain, harus GAGAL (RED)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PatientProfileDomainTest
```
Expected: FAIL. Tes berbasis `createPatient()` ERROR dengan `Illuminate\Database\Eloquent\RelationNotFoundException: Call to undefined relationship [patientProfile] on model [App\Models\User]`; tes `withBpjs` ERROR `BadMethodCallException: Call to undefined method Database\Factories\PatientProfileFactory::withBpjs()`; tes last4 GAGAL `Failed asserting that null is identical to '0011'` (atau `'0022'`). Tes enkripsi/hidden berbasis `withBpjs()` ikut ERROR. Tes yang sudah lulus karena tidak bergantung pada kode baru (wajar): `test_last4_is_not_mass_assignable`, `test_profile_without_a_number_has_null_last4`, `test_masked_bpjs_is_null_without_a_number`, `test_user_without_a_profile_has_a_null_relation` (model yang baru dibuat mengembalikan null untuk atribut tak dikenal), `test_one_account_has_at_most_one_profile`, dan `test_registration_payload_matches_the_v01_example`. Tes `masked_bpjs` pada model hasil `findOrFail()` ERROR `MissingAttributeException: The attribute [masked_bpjs] either does not exist or was not retrieved`.

- [ ] **Step 9: Implementasi relasi `User::patientProfile()` (tool Edit)**

Edit `app/Models/User.php`, import:

old:
```php
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
```
new:
```php
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
```

Edit `app/Models/User.php`, relasi (sebelum `isStaff()`):

old:
```php
    public function isStaff(): bool
```
new:
```php
    /**
     * Profil pasien milik akun ini (satu akun satu profil, K-07); NULL untuk staf. Daftar yang menampilkan
     * profil wajib eager-load relasi ini (mode ketat).
     */
    public function patientProfile(): HasOne
    {
        return $this->hasOne(PatientProfile::class);
    }

    public function isStaff(): bool
```

- [ ] **Step 10: Implementasi hook dan accessor `PatientProfile` (tool Write, berkas utuh)**

`app/Models/PatientProfile.php`:

```php
<?php

namespace App\Models;

use App\Enums\BookingChannel;
use App\Enums\Gender;
use Database\Factories\PatientProfileFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Identitas pasien yang dirujuk booking (09 §2.2). Pembuatan profil dilakukan PatientRegistrationService
 * (registrasi online, fase 5) dan alur walk-in (fase 7); nomor pasien dibuat PatientNumberGenerator.
 * NIK tidak dikumpulkan.
 */
class PatientProfile extends Model
{
    /** @use HasFactory<PatientProfileFactory> */
    use HasFactory;

    /** Penutup tampilan nomor BPJS: sembilan titik diikuti empat karakter terakhir (04 V2, 11 §5.1). */
    private const BPJS_MASK = '•••••••••';

    /**
     * `user_id`, `patient_number`, `bpjs_number_last4`, dan `created_by_user_id` sengaja tidak termasuk:
     * diisi service lewat forceFill() (`bpjs_number_last4` diisi hook `saving`).
     *
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'birth_date',
        'gender',
        'phone',
        'email',
        'address',
        'bpjs_number',
        'registration_channel',
        'consent_at',
        'consent_version',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'bpjs_number',
    ];

    /**
     * Setiap kali `bpjs_number` berubah (diisi, diganti, atau dikosongkan), `bpjs_number_last4` dihitung
     * ulang dari nilai terdekripsi: empat karakter terakhir, atau NULL bila nomor kosong (F5-R11).
     * Hook berjalan setelah seluruh atribut terisi, sehingga nilai last4 yang dipaksakan lewat forceFill()
     * bersamaan dengan perubahan nomor tidak berlaku. Hanya berlaku lewat model (bukan insert massal).
     */
    protected static function booted(): void
    {
        static::saving(function (PatientProfile $profile): void {
            if (! $profile->isDirty('bpjs_number')) {
                return;
            }

            $number = $profile->bpjs_number;

            $profile->bpjs_number_last4 = is_string($number) && $number !== ''
                ? mb_substr($number, -4)
                : null;
        });
    }

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'gender' => Gender::class,
            'bpjs_number' => 'encrypted',
            'registration_channel' => BookingChannel::class,
            'consent_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Nomor BPJS tersamar untuk UI ("•••••••••1234"); NULL bila tidak ada nomor tersimpan. Nomor lengkap
     * tidak pernah dirender. Tidak ikut `toArray()` (tanpa `$appends`).
     *
     * @return Attribute<string|null, never>
     */
    protected function maskedBpjs(): Attribute
    {
        return Attribute::get(function (): ?string {
            $last4 = $this->bpjs_number_last4;

            return $last4 === null || $last4 === '' ? null : self::BPJS_MASK.$last4;
        });
    }

    /** Akun login pemilik profil; NULL untuk pasien walk-in tanpa akun. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Petugas pembuat profil walk-in; NULL untuk registrasi online. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
```

- [ ] **Step 11: Implementasi state `withBpjs()` (tool Edit)**

Edit `database/factories/PatientProfileFactory.php`:

old:
```php
    /** Profil dinonaktifkan (`is_active = false`). */
```
new:
```php
    /**
     * Profil dengan nomor BPJS tersimpan (terenkripsi); `bpjs_number_last4` diisi hook `saving` model
     * saat `create()` (tidak saat `make()`).
     */
    public function withBpjs(string $number = '0009900000011'): static
    {
        return $this->state(fn (array $attributes): array => [
            'bpjs_number' => $number,
        ]);
    }

    /** Profil dinonaktifkan (`is_active = false`). */
```

- [ ] **Step 12: Jalankan tes domain, harus LULUS (GREEN), lalu tes lama yang berdekatan**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PatientProfileDomainTest
& $PHP artisan test --filter="BookingModelsTest|UserAccountTest|InteractsWithSchedulesTest|ScheduleSchemaTest|AuditLoggerTest|ConcurrencySpikeTest|AdminPracticeSessionsTest|AdminScheduleTemplatesTest"
```
Expected: `PatientProfileDomainTest` PASS (25 tes). Kelompok kedua PASS tanpa perubahan apa pun pada tes lama. Bila `test_masked_bpjs_shows_only_the_last_four_digits` gagal pada literal, hitung titiknya: `str_repeat('•', 9)` adalah acuan, dan literal `'•••••••••0011'` harus berisi tepat sembilan karakter U+2022.

- [ ] **Step 13: Tulis tes `PatientProfilePolicyTest` (tool Write)**

`tests/Feature/Auth/PatientProfilePolicyTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\PatientProfile;
use App\Models\User;
use App\Policies\PatientProfilePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * F5-R14 / PAS-05 (dasar): `view` dan `update` profil pasien. Pemilik boleh; pasien lain tidak; staf menurut izin
 * `patients.view` / `patients.manage` (hanya Petugas Pendaftaran di RolePermissionSeeder); Super Admin lewat Gate::before.
 */
class PatientProfilePolicyTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    private function allows(User $user, string $ability, PatientProfile $profile): bool
    {
        return Gate::forUser($user)->allows($ability, $profile);
    }

    public function test_the_owner_can_view_and_update_their_own_profile(): void
    {
        $owner = $this->createPatient();

        $this->assertTrue($this->allows($owner, 'view', $owner->patientProfile));
        $this->assertTrue($this->allows($owner, 'update', $owner->patientProfile));
        $this->assertTrue($owner->can('view', $owner->patientProfile));
        $this->assertTrue($owner->can('update', $owner->patientProfile));
    }

    public function test_another_patient_cannot_view_or_update_the_profile(): void
    {
        $owner = $this->createPatient();
        $other = $this->createPatient();

        $this->assertFalse($this->allows($other, 'view', $owner->patientProfile));
        $this->assertFalse($this->allows($other, 'update', $owner->patientProfile));
        $this->assertFalse($this->allows($owner, 'view', $other->patientProfile));
        $this->assertFalse($this->allows($owner, 'update', $other->patientProfile));
    }

    public function test_a_walk_in_profile_without_an_account_belongs_to_no_patient(): void
    {
        $walkIn = PatientProfile::factory()->create();
        $patient = $this->createPatient();

        $this->assertNull($walkIn->user_id);
        $this->assertFalse($this->allows($patient, 'view', $walkIn));
        $this->assertFalse($this->allows($patient, 'update', $walkIn));
    }

    /** @return array<string, array{0: string, 1: bool, 2: bool}> */
    public static function staffRoles(): array
    {
        return [
            'super-admin (Gate::before)' => ['super-admin', true, true],
            'content-admin tanpa izin pasien' => ['content-admin', false, false],
            'registration-officer memegang patients.view dan patients.manage' => ['registration-officer', true, true],
        ];
    }

    #[DataProvider('staffRoles')]
    public function test_staff_access_follows_the_patient_permissions_of_the_role(string $role, bool $canView, bool $canUpdate): void
    {
        $staff = $this->createStaff($role);
        $profile = $this->createPatient()->patientProfile;

        $this->assertSame($canView, $this->allows($staff, 'view', $profile), "{$role} view");
        $this->assertSame($canUpdate, $this->allows($staff, 'update', $profile), "{$role} update");
    }

    public function test_view_requires_the_view_permission_and_does_not_grant_update(): void
    {
        $staff = $this->createStaff('content-admin');
        $staff->givePermissionTo('patients.view');
        $staff = User::query()->findOrFail($staff->id);
        $profile = $this->createPatient()->patientProfile;

        $this->assertTrue($this->allows($staff, 'view', $profile));
        $this->assertFalse($this->allows($staff, 'update', $profile));
    }

    public function test_update_requires_the_manage_permission_and_does_not_grant_view(): void
    {
        // Izin tidak bertingkat di Policy; peran nyata memegang keduanya (lihat staffRoles).
        $staff = $this->createStaff('content-admin');
        $staff->givePermissionTo('patients.manage');
        $staff = User::query()->findOrFail($staff->id);
        $profile = $this->createPatient()->patientProfile;

        $this->assertTrue($this->allows($staff, 'update', $profile));
        $this->assertFalse($this->allows($staff, 'view', $profile));
    }

    public function test_super_admin_passes_through_gate_before_even_without_any_permission(): void
    {
        $super = $this->createStaff('super-admin');
        Role::findByName('super-admin')->syncPermissions([]);
        $super = User::query()->findOrFail($super->id);
        $profile = $this->createPatient()->patientProfile;

        $this->assertTrue($super->getAllPermissions()->isEmpty());
        $this->assertTrue($this->allows($super, 'view', $profile));
        $this->assertTrue($this->allows($super, 'update', $profile));
    }

    public function test_a_guest_is_denied(): void
    {
        $profile = $this->createPatient()->patientProfile;

        $this->assertFalse(Gate::allows('view', $profile));
        $this->assertFalse(Gate::allows('update', $profile));
    }

    public function test_the_policy_is_discovered_automatically(): void
    {
        $profile = PatientProfile::factory()->create();

        $this->assertInstanceOf(PatientProfilePolicy::class, Gate::getPolicyFor($profile));
    }
}
```

- [ ] **Step 14: Jalankan tes Policy, harus GAGAL (RED)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PatientProfilePolicyTest
```
Expected: FAIL. Tanpa Policy, Gate menolak semuanya kecuali Super Admin: `test_the_owner_can_view_and_update_their_own_profile` gagal `Failed asserting that false is true`; `registration-officer`, `test_view_requires_...`, `test_update_requires_...` gagal; `test_the_policy_is_discovered_automatically` gagal `Failed asserting that null is an instance of class "App\Policies\PatientProfilePolicy"`. Yang sudah lulus: kasus `super-admin`, `content-admin`, pasien lain, walk-in, tamu.

- [ ] **Step 15: Implementasi `PatientProfilePolicy` (tool Write)**

`app/Policies/PatientProfilePolicy.php`:

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PatientProfile;
use App\Models\User;

/**
 * F5-R14 / 11 §2.2: pemilik profil atau staf berizin. Hanya `view` dan `update`; kemampuan lain (daftar, buat
 * walk-in, ubah status) dibangun fase 7. Super Admin lolos lewat Gate::before sebelum sampai ke sini.
 * Pasien tidak memegang peran/izin Spatie, jadi bagi pasien hanya kepemilikan yang menentukan.
 */
final class PatientProfilePolicy
{
    public function view(User $user, PatientProfile $profile): bool
    {
        return $user->can('patients.view') || $this->owns($user, $profile);
    }

    public function update(User $user, PatientProfile $profile): bool
    {
        return $user->can('patients.manage') || $this->owns($user, $profile);
    }

    /** Profil walk-in (`user_id` NULL) tidak dimiliki siapa pun. */
    private function owns(User $user, PatientProfile $profile): bool
    {
        return $profile->user_id !== null && (int) $profile->user_id === (int) $user->getKey();
    }
}
```

- [ ] **Step 16: Jalankan tes Policy, harus LULUS (GREEN)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PatientProfilePolicyTest
```
Expected: PASS, 11 tes.

- [ ] **Step 17: Pint dan seluruh tes tugas**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint app\Models\User.php app\Models\PatientProfile.php app\Services\Patients app\Policies\PatientProfilePolicy.php database\factories\PatientProfileFactory.php tests\Support\InteractsWithPatients.php tests\Unit\Services tests\Feature\Models\PatientProfileDomainTest.php tests\Feature\Auth\PatientProfilePolicyTest.php
& $PHP vendor\bin\pint --test
& $PHP artisan test --filter="PatientNumberGeneratorTest|PatientProfileDomainTest|PatientProfilePolicyTest|BookingModelsTest|UserAccountTest|MediaPolicyTest|GateBeforeTest|RolePermissionSeederTest"
```
Expected: `pint --test` PASS (0 berkas bermasalah); seluruh tes terfilter PASS (45 tes baru; tes lama tidak berubah).

- [ ] **Step 18: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add app/Services/Patients/PatientNumberGenerator.php app/Models/User.php app/Models/PatientProfile.php database/factories/PatientProfileFactory.php tests/Support/InteractsWithPatients.php tests/Unit/Services/PatientNumberGeneratorTest.php tests/Feature/Models/PatientProfileDomainTest.php
git commit -m @'
feat(fase-5): domain pasien (relasi patientProfile, hook last4 BPJS, nomor pasien, state withBpjs, trait tes)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
git add app/Policies/PatientProfilePolicy.php tests/Feature/Auth/PatientProfilePolicyTest.php
git commit -m @'
feat(fase-5): PatientProfilePolicy (view, update) beserta tes

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
git status --short
```
Expected: dua commit terbuat; `git status` bersih (atau hanya `?? .superpowers/`).

#### Catatan penulis (konteks untuk implementer dan reviewer)

1. **Urutan operan `+` pada `createPatient()` (kerangka D).** Kerangka menulis `User::factory()->patient()->create(['password' => $this->patientPassword()] + $userAttributes)` dan `[... 'email' => ..., 'registration_channel' => Online] + $profileAttributes`. Pada array PHP, `+` mempertahankan kunci sisi kiri, sehingga ekspresi persis itu membuat nilai bawaan MENANG atas argumen pemanggil (mis. `createPatient(['password' => 'Lain'])` tetap berpassword `Rahasia123`). Saya membalik urutannya (`$userAttributes + [default]`, `$profileAttributes + [default]`) agar argumen pemanggil menimpa; signature dan nilai bawaan tidak berubah. Tugas lain tidak terdampak (mereka hanya memanggil trait).
2. **Penambahan kecil di `createPatient()` di luar teks kerangka:** (a) profil mendapat `user_id` lewat atribut factory (factory bersifat unguarded) dan `full_name` = nama akun; (b) bila `users.phone` masih null, diisi telepon profil, supaya tes sinkronisasi BR-151 (T2, T6) dan tes login tidak melihat akun bertelepon null padahal profil bertelepon; (c) `patient_number` tidak ditimpa bila pemanggil menyediakannya (`array_key_exists`). Seluruhnya tidak mengubah signature.
3. **`isDirty` vs nilai sama pada kolom `encrypted`.** Menugaskan ulang nomor BPJS yang sama dianggap "kotor" (ciphertext berbeda), sehingga `PatientRegistrationService::updateProfile()` (T2) TIDAK boleh menyetel `bpjs_number` kecuali untuk `remove_bpjs`; ia memang hanya menulisnya saat `remove_bpjs` benar, jadi tidak ada dampak, tetapi T6 jangan menambah input BPJS yang menugaskan ulang nilai yang sama (F5-R19 sudah melarang input BPJS di profil).
4. **last4 = empat karakter terakhir apa adanya.** Bila fase 6 (wizard `save_bpjs`) menerima nomor BPJS, normalisasi spasi dan `-` harus terjadi di Form Request sebelum model menyimpan, supaya last4 tidak memuat tanda hubung.
5. **Direktori `tests/Unit/Services/` baru.** `phpunit.xml` memuat `tests/Unit` secara rekursif, jadi tidak ada konfigurasi tambahan; tes generator memakai `PHPUnit\Framework\TestCase` murni (pola `tests/Unit/Support/SafeRedirectTest.php`).

---

### Task 2: Service registrasi/profil, notifikasi selamat datang, dan terjemahan templat mail

**Files:**
- Create: `app/Services/Patients/PatientRegistrationService.php`
- Create: `app/Notifications/PatientWelcomeNotification.php` (direktori `app/Notifications` belum ada)
- Create: `lang/id.json`
- Test: `tests/Feature/Patient/PatientRegistrationServiceTest.php` (baru; direktori `tests/Feature/Patient` dibuat di sini dan dipakai lagi oleh T3/T6)
- Test: `tests/Feature/Patient/PatientWelcomeNotificationTest.php` (baru)
- Tidak diubah: seluruh migration, `phpunit.xml`, `AuditLogger`, `SettingsService`, `Actor`.

**Interfaces:**
- Consumes (T1): `User::patientProfile()`, hook `saving` `PatientProfile` (last4), `PatientNumberGenerator::format()`, `InteractsWithPatients::createPatient()` / `registrationPayload()` / `patientPassword()`, `PatientProfileFactory::withBpjs()`. Dari kode yang ada: `AuditLogger::log/diff/redact`, `Actor::patient()` / `Actor::staff()`, `SettingsService::string()`, `BookingChannel::Online`, `AccountType::Patient`, `RolePermissionSeeder` (lewat `InteractsWithStaff::createStaff()`).
- Produces:
  - `App\Services\Patients\PatientRegistrationService` (`final`, konstruktor `AuditLogger $audit`, `SettingsService $settings`):
    - `register(array $validated): User` (F5-R9). `$validated` = hasil `RegisterRequest::validated()`: `full_name`, `email`, `phone`, `birth_date`, `gender`, `address`, `password` (kunci lain, termasuk `password_confirmation` dan `consent`, diabaikan). Mengembalikan `User` baru (di-`refresh()`, relasi `patientProfile` termuat). Melempar apa adanya `Illuminate\Database\UniqueConstraintViolationException` bila email sudah ada (balapan atau beda huruf besar; kolasi `utf8mb4_unicode_ci`) dan `Throwable` lain dari transaksi; transaksi di-rollback penuh. Kegagalan kirim email TIDAK dilempar (dilaporkan lewat `report()`).
    - `updateProfile(PatientProfile $profile, array $validated, Actor $actor): PatientProfile` (F5-R20). `$validated` = kunci whitelist `full_name`, `birth_date`, `gender`, `phone`, `email`, `address` ditambah `remove_bpjs` opsional; kunci lain diabaikan. Mengembalikan profil yang sudah di-`refresh()` (instance yang sama). Melempar `UniqueConstraintViolationException` bila email bentrok di `users` (transaksi di-rollback: profil dan akun tidak berubah).
  - `App\Notifications\PatientWelcomeNotification` (`final`, tanpa `ShouldQueue`, `via(): ['mail']`, `toMail(object $notifiable): MailMessage`). **Bergantung pada route bernama `login` yang baru dibuat T3**: pada T2 tes mendaftarkan route pengganti bila belum ada.
  - `lang/id.json` (lima kunci templat mail bawaan Laravel; T5 menambah lima kunci lagi di belakangnya).

**Test IDs:** SEK-01 (sisi service: hash bcrypt `$2y$`, satu kali hash, lolos `Hash::check`), NTF-08 (email selamat datang tanpa PII dan tanpa tautan verifikasi; kegagalan kirim tidak membatalkan registrasi), WLK-06 (registrasi tidak mencocokkan profil walk-in), PAS-05 (sisi service: hanya enam kolom profil + hapus BPJS yang berubah), Review Focus G.3 (mass-assignment, email bentrok tanpa perubahan parsial), G.5 (atomisitas: kegagalan pembuatan profil membatalkan akun, audit, dan notifikasi; email ganda beda huruf besar), F5-R9, F5-R20, F5-R24, F5-R25, F5-R17 (bagian T2).

**Catatan teknis (diverifikasi dari source):**
- **`AuditLogger`** (`app/Services/Audit/AuditLogger.php`): `log(string $action, Model $model, ?array $old, ?array $new, string $description, ?Actor $actor = null): AuditLog`. `redact()` selalu menyamarkan `password`, `bpjs_number`, `token`, dll., dan atribut `$hidden`/`encrypted*` milik model (`bpjs_number` untuk `PatientProfile`); untuk aksi berawalan `patient.` ia juga menyamarkan `address`, `phone`, `email`, `birth_date`, `complaint`, `message` menjadi `[disamarkan]`. `full_name`, `gender`, `patient_number`, `registration_channel`, `consent_version` TIDAK disamarkan. Aksi `auth.*` tidak berawalan PII (dipakai T5/T6). `diff(Model)`: setelah `save()` memakai `getChanges()` + `getPrevious()`, dan membuang `updated_at`; `bpjs_number_last4` TIDAK dibuang oleh `diff()` (service yang membuangnya, F5-R20). Konteks HTTP (`ip_address`, `user_agent`) hanya terisi bila `request()->route()` ada, jadi di tes service keduanya null.
- **Audit `patient.updated` berisi nama kolom berubah.** `birth_date` yang diubah tercatat `[disamarkan]` pada `old` dan `new`; `bpjs_number` (hapus BPJS) tercatat `[disamarkan]` pada kedua sisi (kunci `bpjs_number` sendiri membuka fakta bahwa nomor dihapus tetapi bukan nilainya; `bpjs_number_last4` tidak dicatat).
- **`Actor::patient(User)`** melempar `InvalidArgumentException` bila model belum tersimpan; service memanggilnya setelah `save()`. `Actor::staff(User)` dipakai tes walk-in (fase 7 akan memanggil `updateProfile` dengan aktor staf, jadi service tidak mengunci tipe aktor).
- **`SettingsService::string('privacy.consent_version', '1.0')`** mengembalikan default bila baris pengaturan tidak ada, nilainya kosong, atau tabel belum di-seed (tes tanpa `SettingsSeeder` memperoleh `'1.0'`, sama dengan `PatientProfileFactory`). `SettingsService` terdaftar `scoped`, sehingga `app(PatientRegistrationService::class)` dan `app(SettingsService::class)` memakai instance (dan memo) yang sama; tes yang mengubah pengaturan memanggil `forget()` lebih dulu (pola `PublicContentPagesTest` baris 305-312). `SettingsService::set()` menulis audit `settings.updated`, jadi tes yang memeriksa jumlah baris audit tidak boleh memanggil `set()`.
- **`IndonesianPhone::normalize()`** (buang spasi, `-`, `.`, `(`, `)`) dijalankan `prepareForValidation` Form Request (T4/T6), BUKAN service. Service menyimpan `phone` apa adanya; tes service karenanya memakai nomor yang sudah ternormalisasi (`081234567890`) walau `registrationPayload()` bawaan berisi `0812-3456-7890` (data mentah form). Email juga tidak dinormalisasi di service (Form Request melakukan `Str::lower(trim())`).
- **Mode ketat.** `User` dan `PatientProfile` diisi lewat `forceFill()` (kolom tak-fillable) dan `fill(Arr::only(...))` (hanya kunci `$fillable`); `fill()` dengan kunci non-fillable akan melempar `MassAssignmentException` karena `preventsSilentlyDiscardingAttributes`. Karena itu `updateProfile()` memakai `Arr::only` sebelum `fill()` dan `register()` menyusun array kunci secara eksplisit; kunci liar di `$validated` diabaikan, bukan melempar.
- **Kolom `birth_date` bercast `date`.** Menugaskan `'1990-03-12'` pada profil yang `birth_date`-nya sudah sama tidak membuat model kotor (`originalIsEquivalent` membandingkan keduanya lewat `fromDateTime()`); begitu pula `gender` (nilai mentah `'P'`). Karena itu `updateProfile()` dengan data identik tidak menulis apa pun dan tidak mencatat audit.
- **Deteksi perubahan memakai `isDirty()` SEBELUM `save()`, bukan hanya `getChanges()`.** Spesifikasi F5-R20 menulis "`diff()` tidak kosong setelah save". `getChanges()` adalah properti yang bertahan pada instance: bila profil yang sama baru saja disimpan di request yang sama (mis. di tes atau jalur kode lain), `getChanges()` masih berisi perubahan lama dan menghasilkan audit palsu pada `updateProfile()` tanpa perubahan. Service karenanya mengambil `$changed = $profile->isDirty()` sebelum `save()` dan hanya memanggil `diff()` bila `$changed` benar (diuji di `test_update_with_stale_changes_on_the_instance_writes_no_audit`). Hasil akhir sama dengan spesifikasi pada alur normal.
- **Urutan di `updateProfile()`: simpan profil dulu, lalu sinkron `users`.** Bentrok email baru muncul saat `UPDATE users` (tabel `patient_profiles` tidak punya indeks unik pada `email`); karena profil sudah tersimpan, tes rollback benar-benar membuktikan transaksi (bukan sekadar urutan). Laravel 12 melempar `Illuminate\Database\UniqueConstraintViolationException` untuk errno 1062 pada `MariaDbConnection` (`MySqlConnection::isUniqueConstraintError`, pola pesan `Integrity constraint violation: 1062`). Instance `$profile` milik pemanggil tetap membawa nilai baru di memori setelah rollback; controller T6 langsung mengalihkan dengan galat, jadi tidak memakainya lagi.
- **Notifikasi dan transaksi.** `register()` mengirim notifikasi SESUDAH `DB::transaction()` kembali, di dalam `try/catch (Throwable)` + `report($e)` (06 §18). Notifikasi tidak `ShouldQueue`, sehingga `$user->notify()` berjalan sinkron lewat `NotificationSender::sendNow`. Cara memaksa kegagalan kirim yang benar-benar bekerja di Laravel 12: mendaftarkan listener `NotificationSending` yang melempar (`Event::listen(NotificationSending::class, fn () => throw ...)`). Event itu dikirim lewat `$events->until()` di `NotificationSender::shouldSendNotification()`, di luar `try/catch` pengirim, sehingga pengecualian merambat keluar dari `$user->notify()`; ini jalur kirim sungguhan (mailer `array` dari `phpunit.xml`), bukan mock. `Notification::fake()` TIDAK cocok untuk tes kegagalan karena fake menggantikan pengirim dan tidak pernah memicu event. Pelaporan diverifikasi dengan `Exceptions::fake()` (Laravel 11+; `Exceptions::assertReported()` / `assertReportedCount()`), yang mencegat `report()` tanpa menulis log. Untuk membuktikan notifikasi dikirim di luar transaksi, listener yang sama merekam `DB::transactionLevel()` dan dibandingkan dengan level sebelum pemanggilan (`RefreshDatabase` sudah membungkus tes dalam satu transaksi, jadi level dasar 1; di dalam service 2).
- **Rollback penuh.** Kegagalan dipaksa dengan `PatientProfile::creating(fn () => throw new RuntimeException(...))` (dan `AuditLog::creating(...)` untuk langkah audit). Pendengar model terpasang pada dispatcher aplikasi dan hilang sendiri antar-tes: `DatabaseServiceProvider::boot()` memanggil `Model::clearBootedModels()` dan memasang dispatcher baru pada setiap pembuatan aplikasi tes. Jangan memakai `PatientProfile::flushEventListeners()` (akan ikut menghapus hook `saving` last4 milik model). `DB::listen()` merekam `insert into users` untuk membuktikan baris akun memang sempat ditulis sebelum dibatalkan.
- **Render mail.** `MailMessage::render()` (vendor `Illuminate/Notifications/Messages/MailMessage.php` baris 397) mengembalikan `HtmlString` memakai `Illuminate\Mail\Markdown` dan view `notifications::email`; tidak butuh HTTP maupun mailer. Diperiksa dengan skrip sekali pakai terhadap aplikasi nyata: sapaan kustom di-escape (`&lt;b&gt;`), `@lang('Regards,')` memakai kunci JSON, dan footer `{{ __('All rights reserved.') }}` menjadi "Hak cipta dilindungi." bila kunci JSON dimuat; subcopy tombol membaca "Jika tombol "Masuk ke Akun" tidak berfungsi, salin dan tempel URL berikut\nke peramban web Anda:" (kunci JSON memuat `\n` literal, dan `__()` mencari kunci JSON lebih dulu sebelum menguraikan titik sebagai pemisah grup, sehingga kunci ber-titik seperti `All rights reserved.` aman). Tes mail sungguhan memakai mailer `array` (`phpunit.xml`) dan `app('mailer')->getSymfonyTransport()->messages()` (`Symfony\Component\Mailer\SentMessage::getOriginalMessage()` memberi `Email` dengan `getSubject()`, `getTo()`, `getHtmlBody()`, `getTextBody()`).
- **Locale.** `config('app.locale')` = `id` (env `APP_LOCALE`); tes memanggil `app()->setLocale('id')` agar tidak bergantung pada `.env` lokal. Nama situs diambil dari `config('app.name')` (bawaan `UNPAM HOSPITAL`), dan tes membandingkan dengan nilai config, bukan literal.
- **Tes yang bergantung pada route `login`.** `PatientWelcomeNotification::toMail()` memanggil `route('login')` (F5-R25), yang baru ada setelah T3. Kedua kelas tes mendaftarkan route pengganti `/_uji/masuk` bernama `login` hanya bila `Route::has('login')` salah, lalu `Route::getRoutes()->refreshNameLookups()` (pola `AdminLoginTest`/`GuestRedirectTest`). Setelah T3 route asli ada dan blok itu tidak lagi berjalan; tes tetap membandingkan dengan `route('login')`, bukan literal URI.
- **`lang/id.json`.** `lang_path('id.json')` dimuat otomatis oleh `FileLoader` untuk locale `id`. Tidak ada tes lama yang menyentuh berkas ini (`LocalizationTest` hanya membaca `lang/{en,id}/{auth,pagination,passwords,validation}.php`). Berkas diindentasi 4 spasi, kunci terurut sesuai F5-R17, diakhiri satu baris baru, akhir baris LF (`.gitattributes` `eol=lf`).
- **DB pengembangan.** Implementer tidak menjalankan `migrate`/`db:seed` pada `unpam_hospital`.
- Jumlah tes baru: 37 (`PatientRegistrationServiceTest` 25 termasuk 4 kasus provider, `PatientWelcomeNotificationTest` 12).

- [ ] **Step 1: Verifikasi prasyarat (T1 sudah selesai)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
git log --oneline -3
Test-Path app\Services\Patients\PatientNumberGenerator.php
Test-Path tests\Support\InteractsWithPatients.php
Test-Path app\Notifications
Test-Path lang\id.json
& $PHP artisan test --filter="PatientNumberGeneratorTest|PatientProfileDomainTest|PatientProfilePolicyTest"
```
Expected: `fase-5-akun-pasien`; `git status` kosong (atau hanya `?? .superpowers/`); dua commit T1 teratas; `True`, `True`, `False`, `False`; tes T1 PASS (45 tes). Apache dan MySQL harus sudah di-Start di `C:\xampp3\xampp-control.exe`.

- [ ] **Step 2: Tulis tes notifikasi (tool Write)**

`tests/Feature/Patient/PatientWelcomeNotificationTest.php`:

```php
<?php

namespace Tests\Feature\Patient;

use App\Models\User;
use App\Notifications\PatientWelcomeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithPatients;
use Tests\TestCase;

/**
 * F5-R25 / NTF-08: email selamat datang hanya lewat mail, berbahasa Indonesia, tanpa data pribadi dan tanpa
 * tautan verifikasi (K-22 = a). F5-R17 (bagian T2): terjemahan templat mail bawaan Laravel di lang/id.json.
 */
class PatientWelcomeNotificationTest extends TestCase
{
    use InteractsWithPatients;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('id');
        $this->ensureLoginRoute();
    }

    /** Route `login` baru dibuat T3; sebelum itu dipasang route pengganti agar route('login') dapat dibangun. */
    private function ensureLoginRoute(): void
    {
        if (! Route::has('login')) {
            Route::middleware('web')->get('/_uji/masuk', fn () => 'masuk')->name('login');
            Route::getRoutes()->refreshNameLookups();
        }
    }

    private function recipient(): User
    {
        return $this->createPatient(
            ['name' => 'Siti Rahma', 'email' => 'siti@contoh.test'],
            [
                'birth_date' => '1987-06-15',
                'address' => 'Jl. Rahasia No. 99, Pamulang',
                'phone' => '081234567890',
                'bpjs_number' => '0009900000011',
            ],
        );
    }

    /** @return list<string> */
    private function forbiddenFragments(User $user): array
    {
        return [
            '081234567890',
            'Jl. Rahasia',
            '1987-06-15',
            '15 Juni 1987',
            '0009900000011',
            'BPJS',
            (string) $user->patientProfile->patient_number,
            'verifikasi',
            'Verifikasi',
            'verify',
            'Verify',
        ];
    }

    public function test_it_is_delivered_by_mail_only(): void
    {
        $user = $this->recipient();

        $this->assertSame(['mail'], (new PatientWelcomeNotification)->via($user));
    }

    public function test_it_is_not_queued(): void
    {
        $this->assertNotInstanceOf(ShouldQueue::class, new PatientWelcomeNotification);
    }

    public function test_subject_greeting_lines_and_action_are_indonesian(): void
    {
        $user = $this->recipient();
        $app = (string) config('app.name');

        $mail = (new PatientWelcomeNotification)->toMail($user);

        $this->assertSame("Selamat Datang di {$app}", $mail->subject);
        $this->assertSame('Halo, Siti Rahma!', $mail->greeting);
        $this->assertSame([
            "Akun pasien Anda di {$app} telah berhasil dibuat.",
            'Masuk ke akun Anda untuk melihat dan memperbarui profil.',
            'Pendaftaran kunjungan rawat jalan dapat dilakukan melalui menu Daftar Kunjungan setelah fitur tersebut tersedia, atau langsung di loket pendaftaran.',
        ], $mail->introLines);
        $this->assertSame([], $mail->outroLines);
        $this->assertSame('Masuk ke Akun', $mail->actionText);
    }

    public function test_the_action_points_to_the_login_route(): void
    {
        $mail = (new PatientWelcomeNotification)->toMail($this->recipient());

        $this->assertSame(route('login'), $mail->actionUrl);
    }

    public function test_the_site_name_comes_from_the_config(): void
    {
        config(['app.name' => 'RS Uji Coba']);

        $mail = (new PatientWelcomeNotification)->toMail($this->recipient());
        $html = (string) $mail->render();

        $this->assertSame('Selamat Datang di RS Uji Coba', $mail->subject);
        $this->assertStringContainsString('Akun pasien Anda di RS Uji Coba telah berhasil dibuat.', $html);
    }

    public function test_the_html_mail_uses_indonesian_template_strings(): void
    {
        $html = (string) (new PatientWelcomeNotification)->toMail($this->recipient())->render();

        foreach (['Halo, Siti Rahma!', 'Salam,', 'Hak cipta dilindungi.', 'Jika tombol', 'ke peramban web Anda', 'Masuk ke Akun'] as $expected) {
            $this->assertStringContainsString($expected, $html, "Teks Indonesia hilang: {$expected}");
        }

        foreach (['Hello', 'Regards', 'All rights reserved', "If you're having trouble", 'Whoops'] as $english) {
            $this->assertStringNotContainsString($english, $html, "Teks Inggris bocor: {$english}");
        }
    }

    public function test_the_mail_carries_no_personal_data_or_verification_wording(): void
    {
        $user = $this->recipient();
        $mail = (new PatientWelcomeNotification)->toMail($user);
        $html = (string) $mail->render();
        $plain = implode("\n", [$mail->subject, $mail->greeting, ...$mail->introLines, ...$mail->outroLines, $mail->actionText, $mail->actionUrl]);

        foreach ($this->forbiddenFragments($user) as $fragment) {
            $this->assertStringNotContainsString($fragment, $html, "HTML memuat: {$fragment}");
            $this->assertStringNotContainsString($fragment, $plain, "Baris pesan memuat: {$fragment}");
        }
    }

    public function test_the_recipient_name_is_escaped_in_the_html(): void
    {
        $user = $this->createPatient(['name' => '<script>alert(1)</script> & Co']);

        $html = (string) (new PatientWelcomeNotification)->toMail($user)->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; Co', $html);
    }

    public function test_a_real_delivery_through_the_array_mailer_is_indonesian_and_stays_out_of_the_database(): void
    {
        $user = $this->recipient();

        $user->notify(new PatientWelcomeNotification);

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);

        $email = $messages->first()->getOriginalMessage();
        $this->assertSame('Selamat Datang di '.config('app.name'), $email->getSubject());
        $this->assertSame('siti@contoh.test', $email->getTo()[0]->getAddress());

        foreach (['HTML' => (string) $email->getHtmlBody(), 'teks biasa' => (string) $email->getTextBody()] as $label => $body) {
            $this->assertStringContainsString('Halo, Siti Rahma!', $body, $label);
            $this->assertStringContainsString('Salam,', $body, $label);
            $this->assertStringContainsString('Hak cipta dilindungi.', $body, $label);
            $this->assertStringContainsString(route('login'), $body, $label);

            foreach ($this->forbiddenFragments($user) as $fragment) {
                $this->assertStringNotContainsString($fragment, $body, "{$label} memuat: {$fragment}");
            }
        }

        $this->assertSame(0, DB::table('notifications')->count(), 'Kanal database tidak dipakai.');
    }

    public function test_the_fake_records_a_single_mail_only_delivery(): void
    {
        Notification::fake();
        $user = $this->recipient();

        $user->notify(new PatientWelcomeNotification);

        Notification::assertSentToTimes($user, PatientWelcomeNotification::class, 1);
        Notification::assertSentTo(
            $user,
            PatientWelcomeNotification::class,
            fn (PatientWelcomeNotification $notification, array $channels): bool => $channels === ['mail'],
        );
    }

    // --- lang/id.json (F5-R17, bagian T2) ---

    public function test_the_json_translation_file_has_the_template_keys_in_order(): void
    {
        $raw = (string) file_get_contents(lang_path('id.json'));
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        $expected = [
            'Hello!' => 'Halo!',
            'Whoops!' => 'Maaf!',
            'Regards,' => 'Salam,',
            "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\ninto your web browser:" => "Jika tombol \":actionText\" tidak berfungsi, salin dan tempel URL berikut\nke peramban web Anda:",
            'All rights reserved.' => 'Hak cipta dilindungi.',
        ];

        $this->assertSame(array_keys($expected), array_slice(array_keys($decoded), 0, 5), 'Urutan kunci mengikuti F5-R17.');

        foreach ($expected as $key => $value) {
            $this->assertSame($value, $decoded[$key], 'Terjemahan untuk: '.$key);
        }

        $this->assertStringStartsWith("{\n    \"Hello!\"", $raw, 'Indentasi 4 spasi.');
        $this->assertStringEndsWith("}\n", $raw, 'Diakhiri satu baris baru.');
        $this->assertStringNotContainsString("\r", $raw, 'Akhir baris LF.');
    }

    public function test_the_default_template_strings_are_translated(): void
    {
        $this->assertSame('Halo!', __('Hello!'));
        $this->assertSame('Maaf!', __('Whoops!'));
        $this->assertSame('Salam,', __('Regards,'));
        $this->assertSame('Hak cipta dilindungi.', __('All rights reserved.'));
        $this->assertSame(
            "Jika tombol \"Masuk ke Akun\" tidak berfungsi, salin dan tempel URL berikut\nke peramban web Anda:",
            __(
                "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\ninto your web browser:",
                ['actionText' => 'Masuk ke Akun'],
            ),
        );
    }
}
```

- [ ] **Step 3: Jalankan tes notifikasi, harus GAGAL (RED)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PatientWelcomeNotificationTest
```
Expected: FAIL. Sepuluh tes ERROR `Class "App\Notifications\PatientWelcomeNotification" not found`; `test_the_json_translation_file_has_the_template_keys_in_order` ERROR `file_get_contents(...lang\id.json): Failed to open stream` (diubah menjadi `ErrorException`); `test_the_default_template_strings_are_translated` GAGAL `Failed asserting that 'Hello!' is identical to 'Halo!'`.

- [ ] **Step 4: Implementasi `lang/id.json` (tool Write)**

`lang/id.json`:

```json
{
    "Hello!": "Halo!",
    "Whoops!": "Maaf!",
    "Regards,": "Salam,",
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\ninto your web browser:": "Jika tombol \":actionText\" tidak berfungsi, salin dan tempel URL berikut\nke peramban web Anda:",
    "All rights reserved.": "Hak cipta dilindungi."
}
```

(Berkas diakhiri tepat satu baris baru setelah `}`.)

- [ ] **Step 5: Implementasi `PatientWelcomeNotification` (tool Write)**

`app/Notifications/PatientWelcomeNotification.php`:

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email selamat datang sesudah registrasi pasien (F5-R25, 06 §18, K-22 = a). Hanya kanal mail, sinkron
 * (bukan ShouldQueue; antrean notifikasi dirancang fase 7). Isi sengaja minim: tanpa telepon, alamat, tanggal
 * lahir, BPJS, nomor pasien, dan tanpa tautan verifikasi (BR-174, NTF-08).
 */
final class PatientWelcomeNotification extends Notification
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * @param  User  $notifiable
     */
    public function toMail(object $notifiable): MailMessage
    {
        $app = (string) config('app.name');

        return (new MailMessage)
            ->subject("Selamat Datang di {$app}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line("Akun pasien Anda di {$app} telah berhasil dibuat.")
            ->line('Masuk ke akun Anda untuk melihat dan memperbarui profil.')
            ->line('Pendaftaran kunjungan rawat jalan dapat dilakukan melalui menu Daftar Kunjungan setelah fitur tersebut tersedia, atau langsung di loket pendaftaran.')
            ->action('Masuk ke Akun', route('login'));
    }
}
```

- [ ] **Step 6: Jalankan tes notifikasi, harus LULUS (GREEN)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PatientWelcomeNotificationTest
```
Expected: PASS, 12 tes. Bila `test_the_html_mail_uses_indonesian_template_strings` gagal pada "Hak cipta dilindungi.", pastikan `lang/id.json` valid dan berada di `lang\id.json` (bukan `lang\id\`); bila gagal pada "Jika tombol", periksa kunci panjang di JSON: harus persis sama dengan kunci di `vendor/laravel/framework/src/Illuminate/Notifications/resources/views/email.blade.php` (apostrof lurus `'`, `\n` di dalam string JSON).

- [ ] **Step 7: Tulis tes service (tool Write)**

`tests/Feature/Patient/PatientRegistrationServiceTest.php`:

```php
<?php

namespace Tests\Feature\Patient;

use App\Enums\AccountType;
use App\Enums\ActorType;
use App\Enums\BookingChannel;
use App\Enums\Gender;
use App\Models\AuditLog;
use App\Models\PatientProfile;
use App\Models\User;
use App\Notifications\PatientWelcomeNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Patients\PatientNumberGenerator;
use App\Services\Patients\PatientRegistrationService;
use App\Services\Settings\SettingsService;
use App\Support\Actor;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * F5-R9 (register), F5-R20 (updateProfile), F5-R24 (audit). Transaksi, atomisitas, mass-assignment, dan
 * kegagalan kirim email (Review Focus G.3 dan G.5; SEK-01 sisi service; NTF-08; WLK-06).
 */
class PatientRegistrationServiceTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-08 09:30:00');
        app()->setLocale('id');

        // Route `login` baru dibuat T3; sebelum itu dipasang route pengganti untuk tombol email selamat datang.
        if (! Route::has('login')) {
            Route::middleware('web')->get('/_uji/masuk', fn () => 'masuk')->name('login');
            Route::getRoutes()->refreshNameLookups();
        }
    }

    private function service(): PatientRegistrationService
    {
        return app(PatientRegistrationService::class);
    }

    /**
     * Data registrasi seperti keluaran RegisterRequest::validated(): telepon sudah dinormalisasi.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + $this->registrationPayload(['phone' => '081234567890']);
    }

    private function patientToUpdate(): User
    {
        return $this->createPatient(
            ['name' => 'Siti Rahma', 'email' => 'siti@contoh.test'],
            [
                'full_name' => 'Siti Rahma',
                'birth_date' => '1990-03-12',
                'gender' => 'P',
                'phone' => '081234567890',
                'address' => 'Jl. Merpati No. 5, Pamulang',
            ],
        );
    }

    /**
     * Enam kolom profil yang sama dengan nilai saat ini, ditimpa $overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function samePayload(PatientProfile $profile, array $overrides = []): array
    {
        return $overrides + [
            'full_name' => $profile->full_name,
            'birth_date' => $profile->birth_date->format('Y-m-d'),
            'gender' => $profile->gender->value,
            'phone' => $profile->phone,
            'email' => $profile->email,
            'address' => $profile->address,
        ];
    }

    // --- register: data dan transaksi ---

    public function test_register_creates_the_account_and_profile_in_one_transaction(): void
    {
        $user = $this->service()->register($this->payload());

        $account = User::query()->findOrFail($user->id);
        $this->assertSame('Siti Rahma', $account->name);
        $this->assertSame('siti@contoh.test', $account->email);
        $this->assertSame('081234567890', $account->phone);
        $this->assertSame(AccountType::Patient, $account->account_type);
        $this->assertTrue($account->is_active);
        $this->assertFalse($account->must_change_password);
        $this->assertNull($account->email_verified_at, 'Tanpa verifikasi email wajib (K-22 = a).');
        $this->assertNull($account->last_login_at);

        $profile = PatientProfile::query()->where('user_id', $account->id)->sole();
        $this->assertSame(PatientNumberGenerator::format($profile->id), $profile->patient_number);
        $this->assertMatchesRegularExpression('/^P\d{6,}$/', $profile->patient_number);
        $this->assertSame('Siti Rahma', $profile->full_name);
        $this->assertSame('1990-03-12', $profile->birth_date->toDateString());
        $this->assertSame(Gender::Female, $profile->gender);
        $this->assertSame('081234567890', $profile->phone);
        $this->assertSame('siti@contoh.test', $profile->email);
        $this->assertSame('Jl. Merpati No. 5, Pamulang', $profile->address);
        $this->assertSame(BookingChannel::Online, $profile->registration_channel);
        $this->assertNull($profile->created_by_user_id);
        $this->assertSame('2026-10-08 09:30:00', $profile->consent_at->format('Y-m-d H:i:s'));
        $this->assertSame('1.0', $profile->consent_version);
        $this->assertTrue($profile->is_active);
        $this->assertNull($profile->bpjs_number);
        $this->assertNull($profile->bpjs_number_last4);

        $this->assertTrue($user->relationLoaded('patientProfile'));
        $this->assertSame($profile->id, $user->patientProfile->id);
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, PatientProfile::query()->count());
    }

    /** SEK-01 (sisi service): hash bcrypt, satu kali hash, password mentah tidak tersimpan. */
    public function test_register_hashes_the_password_once(): void
    {
        $user = $this->service()->register($this->payload(['password' => 'RahasiaUji1', 'password_confirmation' => 'RahasiaUji1']));

        $row = DB::table('users')->where('id', $user->id)->first();
        $raw = (string) $row->password;

        $this->assertStringStartsWith('$2y$', $raw);
        $this->assertNotSame('RahasiaUji1', $raw);
        $this->assertSame('bcrypt', Hash::info($raw)['algoName']);
        $this->assertTrue(Hash::check('RahasiaUji1', $raw), 'Hash tunggal: password asli lolos Hash::check.');
        $this->assertTrue(Hash::check('RahasiaUji1', $user->password));
        $this->assertStringNotContainsString('RahasiaUji1', (string) json_encode($row));
    }

    public function test_consent_version_follows_the_privacy_setting(): void
    {
        $this->seed(SettingsSeeder::class);
        $settings = app(SettingsService::class);
        $settings->forget();
        $settings->set('privacy.consent_version', '2.3');

        $user = $this->service()->register($this->payload());

        $this->assertSame('2.3', PatientProfile::query()->where('user_id', $user->id)->sole()->consent_version);
    }

    /** PAS-05 / mass-assignment (sisi register): kunci liar pada $validated tidak berpengaruh. */
    public function test_register_ignores_keys_outside_the_whitelist(): void
    {
        $staff = $this->createStaff('registration-officer');

        $user = $this->service()->register($this->payload([
            'account_type' => 'staff',
            'is_active' => false,
            'must_change_password' => true,
            'email_verified_at' => '2000-01-01 00:00:00',
            'last_login_at' => '2000-01-01 00:00:00',
            'user_id' => 12345,
            'patient_number' => 'P999999',
            'registration_channel' => 'loket',
            'created_by_user_id' => $staff->id,
            'bpjs_number' => '0009900000099',
            'bpjs_number_last4' => '9999',
            'consent_at' => '2000-01-01 00:00:00',
            'consent_version' => '9.9',
        ]));

        $account = User::query()->findOrFail($user->id);
        $this->assertSame(AccountType::Patient, $account->account_type);
        $this->assertTrue($account->is_active);
        $this->assertFalse($account->must_change_password);
        $this->assertNull($account->email_verified_at);
        $this->assertNull($account->last_login_at);
        $this->assertSame([], $account->getRoleNames()->all());

        $profile = PatientProfile::query()->where('user_id', $account->id)->sole();
        $this->assertSame($account->id, $profile->user_id);
        $this->assertSame(PatientNumberGenerator::format($profile->id), $profile->patient_number);
        $this->assertSame(BookingChannel::Online, $profile->registration_channel);
        $this->assertNull($profile->created_by_user_id);
        $this->assertNull($profile->bpjs_number);
        $this->assertNull($profile->bpjs_number_last4);
        $this->assertSame('2026-10-08 09:30:00', $profile->consent_at->format('Y-m-d H:i:s'));
        $this->assertSame('1.0', $profile->consent_version);
    }

    /** WLK-06: tidak ada pencocokan otomatis dengan profil walk-in yang kebetulan sama. */
    public function test_register_does_not_link_a_matching_walk_in_profile(): void
    {
        $walkIn = PatientProfile::factory()->create([
            'full_name' => 'Siti Rahma',
            'birth_date' => '1990-03-12',
            'phone' => '081234567890',
        ]);

        $user = $this->service()->register($this->payload());

        $this->assertSame(2, PatientProfile::query()->count());
        $this->assertNull($walkIn->fresh()->user_id);
        $this->assertNotSame($walkIn->id, $user->patientProfile->id);
    }

    // --- register: audit (F5-R24) ---

    public function test_register_writes_one_audit_row_with_exact_values(): void
    {
        $user = $this->service()->register($this->payload());
        $profile = PatientProfile::query()->where('user_id', $user->id)->sole();

        $log = AuditLog::query()->where('action', 'patient.registered')->sole();

        $this->assertSame(1, AuditLog::query()->count());
        $this->assertSame(ActorType::Patient, $log->actor_type);
        $this->assertSame($user->id, (int) $log->user_id);
        $this->assertSame($profile->getMorphClass(), $log->auditable_type);
        $this->assertSame($profile->id, (int) $log->auditable_id);
        $this->assertSame("Pasien {$profile->patient_number} mendaftar akun secara online.", $log->description);
        $this->assertNull($log->old_values);
        $this->assertEquals([
            'patient_number' => $profile->patient_number,
            'registration_channel' => 'online',
            'consent_version' => '1.0',
        ], $log->new_values);
    }

    public function test_the_registration_audit_row_carries_no_personal_data(): void
    {
        $this->service()->register($this->payload());

        $log = AuditLog::query()->where('action', 'patient.registered')->sole();
        $dump = (string) json_encode([$log->description, $log->old_values, $log->new_values]);

        foreach (['Siti Rahma', 'siti@contoh.test', '081234567890', 'Merpati', '1990-03-12', 'Rahasia123', 'password'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $dump, "Audit memuat: {$fragment}");
        }
    }

    // --- register: notifikasi ---

    public function test_the_welcome_notification_is_sent_once_by_mail_to_the_new_user(): void
    {
        Notification::fake();

        $user = $this->service()->register($this->payload());

        Notification::assertSentToTimes($user, PatientWelcomeNotification::class, 1);
        Notification::assertSentTimes(PatientWelcomeNotification::class, 1);
        Notification::assertSentTo(
            $user,
            PatientWelcomeNotification::class,
            fn (PatientWelcomeNotification $notification, array $channels): bool => $channels === ['mail'],
        );
    }

    public function test_the_welcome_notification_is_sent_after_the_transaction_commits(): void
    {
        $baseLevel = DB::transactionLevel();
        $levels = [];
        Event::listen(NotificationSending::class, function () use (&$levels): void {
            $levels[] = DB::transactionLevel();
        });

        $user = $this->service()->register($this->payload());

        $this->assertSame([$baseLevel], $levels, 'Notifikasi dikirim tepat satu kali, di luar transaksi registrasi.');
        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame($user->email, $messages->first()->getOriginalMessage()->getTo()[0]->getAddress());
    }

    /**
     * NTF-08 / 06 §18: kegagalan kirim email tidak membatalkan registrasi dan dilaporkan.
     * Kegagalan dipaksa lewat listener NotificationSending yang melempar (jalur kirim sungguhan).
     */
    public function test_a_notification_failure_is_reported_and_does_not_roll_back_the_registration(): void
    {
        Event::listen(NotificationSending::class, function (): void {
            throw new RuntimeException('Surel gagal dikirim.');
        });
        Exceptions::fake();

        $user = $this->service()->register($this->payload());

        $this->assertSame(1, User::query()->where('email', 'siti@contoh.test')->count());
        $this->assertSame(1, PatientProfile::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'patient.registered')->count());
        $this->assertNotNull($user->patientProfile->patient_number);
        $this->assertCount(0, app('mailer')->getSymfonyTransport()->messages());

        Exceptions::assertReportedCount(1);
        Exceptions::assertReported(RuntimeException::class);
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Surel gagal dikirim.');
    }

    // --- register: atomisitas (Review Focus G.5) ---

    public function test_a_profile_failure_rolls_back_the_account_the_audit_and_the_notification(): void
    {
        Notification::fake();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        PatientProfile::creating(function (): void {
            throw new RuntimeException('Profil gagal dibuat.');
        });

        try {
            $this->service()->register($this->payload());
            $this->fail('Pembuatan profil yang gagal harus merambat keluar dari service.');
        } catch (RuntimeException $e) {
            $this->assertSame('Profil gagal dibuat.', $e->getMessage());
        }

        $this->assertNotEmpty(
            array_filter($queries, fn (string $sql): bool => str_starts_with($sql, 'insert into `users`')),
            'Baris akun sempat ditulis di dalam transaksi sebelum dibatalkan.',
        );
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, PatientProfile::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
        Notification::assertNothingSent();
    }

    public function test_an_audit_failure_rolls_back_the_account_and_the_profile(): void
    {
        Notification::fake();
        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit gagal ditulis.');
        });

        try {
            $this->service()->register($this->payload());
            $this->fail('Kegagalan audit harus merambat keluar dari service.');
        } catch (RuntimeException $e) {
            $this->assertSame('Audit gagal ditulis.', $e->getMessage());
        }

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, PatientProfile::query()->count());
        Notification::assertNothingSent();
    }

    /** Review Focus G.5: email ganda beda huruf besar ditolak DB (kolasi utf8mb4_unicode_ci) tanpa data parsial. */
    public function test_a_duplicate_email_in_another_case_is_rejected_by_the_database(): void
    {
        Notification::fake();
        $this->service()->register($this->payload());

        try {
            $this->service()->register($this->payload(['full_name' => 'Siti Kedua', 'email' => 'Siti@Contoh.test']));
            $this->fail('Email ganda harus melempar UniqueConstraintViolationException.');
        } catch (UniqueConstraintViolationException $e) {
            $this->assertStringContainsString('users_email_unique', $e->getMessage());
        }

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, PatientProfile::query()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'patient.registered')->count());
        Notification::assertSentTimes(PatientWelcomeNotification::class, 1);
    }

    // --- updateProfile ---

    public function test_update_profile_writes_the_six_columns_and_syncs_the_account(): void
    {
        $user = $this->patientToUpdate();
        $profile = $user->patientProfile;
        $consentAt = $profile->consent_at->format('Y-m-d H:i:s');
        $payload = $this->samePayload($profile, [
            'full_name' => 'Siti Rahma Lestari',
            'birth_date' => '1991-04-13',
            'gender' => 'L',
            'phone' => '081298765432',
            'email' => 'siti.baru@contoh.test',
            'address' => 'Jl. Kenari No. 7, Ciputat',
        ]);

        $result = $this->service()->updateProfile($profile, $payload, Actor::patient($user));

        $this->assertTrue($result->is($profile));
        $this->assertSame('Siti Rahma Lestari', $result->full_name);

        $fresh = PatientProfile::query()->findOrFail($profile->id);
        $this->assertSame('Siti Rahma Lestari', $fresh->full_name);
        $this->assertSame('1991-04-13', $fresh->birth_date->toDateString());
        $this->assertSame(Gender::Male, $fresh->gender);
        $this->assertSame('081298765432', $fresh->phone);
        $this->assertSame('siti.baru@contoh.test', $fresh->email);
        $this->assertSame('Jl. Kenari No. 7, Ciputat', $fresh->address);

        // BR-151 / D-07: akun login ikut berubah.
        $account = User::query()->findOrFail($user->id);
        $this->assertSame('Siti Rahma Lestari', $account->name);
        $this->assertSame('siti.baru@contoh.test', $account->email);
        $this->assertSame('081298765432', $account->phone);
        $this->assertTrue(Hash::check('Rahasia123', $account->password));
        $this->assertSame(AccountType::Patient, $account->account_type);

        // Kolom sistem tidak tersentuh.
        $this->assertSame($profile->patient_number, $fresh->patient_number);
        $this->assertSame($user->id, $fresh->user_id);
        $this->assertSame(BookingChannel::Online, $fresh->registration_channel);
        $this->assertSame($consentAt, $fresh->consent_at->format('Y-m-d H:i:s'));
        $this->assertSame('1.0', $fresh->consent_version);
        $this->assertTrue($fresh->is_active);
    }

    /** Review Focus G.3 (sisi service) / PAS-05: hanya enam kolom whitelist yang berubah. */
    public function test_update_profile_ignores_keys_outside_the_whitelist(): void
    {
        $user = $this->patientToUpdate();
        $other = $this->createPatient();
        $profile = $user->patientProfile;
        $before = PatientProfile::query()->findOrFail($profile->id)->getAttributes();

        $this->service()->updateProfile($profile, $this->samePayload($profile, [
            'full_name' => 'Nama Sah',
            'user_id' => $other->id,
            'patient_number' => 'P999999',
            'registration_channel' => 'loket',
            'is_active' => false,
            'created_by_user_id' => $other->id,
            'bpjs_number' => '0009900000099',
            'bpjs_number_last4' => '9999',
            'consent_at' => '2000-01-01 00:00:00',
            'consent_version' => '9.9',
        ]), Actor::patient($user));

        $after = PatientProfile::query()->findOrFail($profile->id);
        $this->assertSame('Nama Sah', $after->full_name);

        foreach (['user_id', 'patient_number', 'registration_channel', 'is_active', 'created_by_user_id', 'bpjs_number', 'bpjs_number_last4', 'consent_at', 'consent_version'] as $column) {
            $this->assertSame($before[$column], $after->getAttributes()[$column], "Kolom {$column} tidak boleh berubah.");
        }

        $this->assertNull($after->bpjs_number);
        $this->assertSame($other->patientProfile->patient_number, PatientProfile::query()->findOrFail($other->patientProfile->id)->patient_number, 'Profil pasien lain tidak tersentuh.');
        $this->assertSame($other->name, User::query()->findOrFail($other->id)->name);
    }

    public function test_remove_bpjs_clears_the_number_and_last4_and_is_audited_without_values(): void
    {
        $user = $this->createPatient(['name' => 'Siti Rahma', 'email' => 'siti@contoh.test'], ['bpjs_number' => '0009900000011']);
        $profile = $user->patientProfile;
        $this->assertSame('0011', $profile->bpjs_number_last4);

        $this->service()->updateProfile($profile, $this->samePayload($profile, ['remove_bpjs' => true]), Actor::patient($user));

        $row = DB::table('patient_profiles')->where('id', $profile->id)->first();
        $this->assertNull($row->bpjs_number);
        $this->assertNull($row->bpjs_number_last4);
        $this->assertNull(PatientProfile::query()->findOrFail($profile->id)->masked_bpjs);

        $log = AuditLog::query()->where('action', 'patient.updated')->sole();
        $this->assertEquals(['bpjs_number' => AuditLogger::REDACTED], $log->old_values);
        $this->assertEquals(['bpjs_number' => AuditLogger::REDACTED], $log->new_values);
        $dump = (string) json_encode([$log->old_values, $log->new_values]);
        $this->assertStringNotContainsString('0009900000011', $dump);
        $this->assertStringNotContainsString('bpjs_number_last4', $dump);
        $this->assertStringNotContainsString('0011', $dump);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function keepBpjsInputs(): array
    {
        return [
            'remove_bpjs false' => [['remove_bpjs' => false]],
            'remove_bpjs "0"' => [['remove_bpjs' => '0']],
            'remove_bpjs null' => [['remove_bpjs' => null]],
            'tanpa remove_bpjs' => [[]],
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    #[DataProvider('keepBpjsInputs')]
    public function test_the_bpjs_number_is_kept_unless_removal_is_requested(array $extra): void
    {
        $user = $this->createPatient([], ['bpjs_number' => '0009900000011']);
        $profile = $user->patientProfile;

        $this->service()->updateProfile($profile, $this->samePayload($profile, ['full_name' => 'Nama Baru'] + $extra), Actor::patient($user));

        $fresh = PatientProfile::query()->findOrFail($profile->id);
        $this->assertSame('Nama Baru', $fresh->full_name);
        $this->assertSame('0009900000011', $fresh->bpjs_number);
        $this->assertSame('0011', $fresh->bpjs_number_last4);
    }

    public function test_update_without_changes_writes_no_audit_and_no_row_update(): void
    {
        $user = $this->patientToUpdate();
        $profile = $user->patientProfile;
        $updatedAt = DB::table('patient_profiles')->where('id', $profile->id)->value('updated_at');
        $this->travelTo(now()->addHour());

        $result = $this->service()->updateProfile($profile, $this->samePayload($profile), Actor::patient($user));

        $this->assertSame(0, AuditLog::query()->count());
        $this->assertSame($updatedAt, DB::table('patient_profiles')->where('id', $profile->id)->value('updated_at'));
        $this->assertSame('Siti Rahma', $result->full_name);
    }

    public function test_update_with_stale_changes_on_the_instance_writes_no_audit(): void
    {
        $user = $this->patientToUpdate();
        $profile = $user->patientProfile;
        $profile->forceFill(['address' => 'Alamat sementara'])->save();

        $this->service()->updateProfile($profile, $this->samePayload($profile, ['address' => 'Alamat sementara']), Actor::patient($user));

        $this->assertSame(0, AuditLog::query()->where('action', 'patient.updated')->count());
    }

    /** F5-R24 / 12 §8.2: PII disamarkan, tanpa bpjs_number_last4 dan updated_at, aktor pasien. */
    public function test_the_update_audit_redacts_personal_data_and_omits_last4(): void
    {
        $user = $this->createPatient(
            ['name' => 'Siti Rahma', 'email' => 'siti@contoh.test'],
            [
                'full_name' => 'Siti Rahma',
                'birth_date' => '1990-03-12',
                'gender' => 'P',
                'phone' => '081234567890',
                'address' => 'Jl. Merpati No. 5, Pamulang',
                'bpjs_number' => '0009900000011',
            ],
        );
        $profile = $user->patientProfile;

        $this->service()->updateProfile($profile, $this->samePayload($profile, [
            'full_name' => 'Siti Rahma Lestari',
            'birth_date' => '1991-04-13',
            'gender' => 'L',
            'phone' => '081298765432',
            'email' => 'siti.baru@contoh.test',
            'address' => 'Jl. Kenari No. 7, Ciputat',
        ]), Actor::patient($user));

        $log = AuditLog::query()->where('action', 'patient.updated')->sole();

        $this->assertSame(1, AuditLog::query()->count());
        $this->assertSame(ActorType::Patient, $log->actor_type);
        $this->assertSame($user->id, (int) $log->user_id);
        $this->assertSame($profile->getMorphClass(), $log->auditable_type);
        $this->assertSame($profile->id, (int) $log->auditable_id);
        $this->assertSame("Profil pasien {$profile->patient_number} diperbarui.", $log->description);
        $this->assertEquals([
            'full_name' => 'Siti Rahma',
            'birth_date' => AuditLogger::REDACTED,
            'gender' => 'P',
            'phone' => AuditLogger::REDACTED,
            'email' => AuditLogger::REDACTED,
            'address' => AuditLogger::REDACTED,
        ], $log->old_values);
        $this->assertEquals([
            'full_name' => 'Siti Rahma Lestari',
            'birth_date' => AuditLogger::REDACTED,
            'gender' => 'L',
            'phone' => AuditLogger::REDACTED,
            'email' => AuditLogger::REDACTED,
            'address' => AuditLogger::REDACTED,
        ], $log->new_values);
        $this->assertArrayNotHasKey('bpjs_number_last4', $log->old_values);
        $this->assertArrayNotHasKey('bpjs_number_last4', $log->new_values);
        $this->assertArrayNotHasKey('updated_at', $log->new_values);

        $dump = (string) json_encode([$log->description, $log->old_values, $log->new_values]);

        foreach (['siti@contoh.test', 'siti.baru@contoh.test', '081234567890', '081298765432', 'Merpati', 'Kenari', '1990-03-12', '1991-04-13', '0009900000011'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $dump, "Audit memuat: {$fragment}");
        }
    }

    /** Review Focus G.3: email bentrok di users tidak meninggalkan perubahan parsial pada profil maupun akun. */
    public function test_an_email_conflict_leaves_the_profile_and_the_account_unchanged(): void
    {
        $user = $this->patientToUpdate();
        $profile = $user->patientProfile;
        User::factory()->create(['email' => 'dipakai@contoh.test']);

        try {
            $this->service()->updateProfile($profile, $this->samePayload($profile, [
                'full_name' => 'Nama Lain',
                'phone' => '081200001111',
                'address' => 'Jl. Lain No. 1',
                'email' => 'dipakai@contoh.test',
            ]), Actor::patient($user));
            $this->fail('Email bentrok harus melempar UniqueConstraintViolationException.');
        } catch (UniqueConstraintViolationException $e) {
            $this->assertStringContainsString('users_email_unique', $e->getMessage());
        }

        $fresh = PatientProfile::query()->findOrFail($profile->id);
        $this->assertSame('Siti Rahma', $fresh->full_name);
        $this->assertSame('081234567890', $fresh->phone);
        $this->assertSame('Jl. Merpati No. 5, Pamulang', $fresh->address);
        $this->assertSame('siti@contoh.test', $fresh->email);

        $account = User::query()->findOrFail($user->id);
        $this->assertSame('Siti Rahma', $account->name);
        $this->assertSame('siti@contoh.test', $account->email);
        $this->assertSame('081234567890', $account->phone);

        $this->assertSame(0, AuditLog::query()->where('action', 'patient.updated')->count());
    }

    public function test_update_profile_of_a_walk_in_without_an_account_touches_only_the_profile(): void
    {
        $staff = $this->createStaff('registration-officer');
        $walkIn = PatientProfile::factory()->create([
            'full_name' => 'Rudi',
            'birth_date' => '1980-01-01',
            'gender' => 'L',
            'phone' => '081211112222',
            'email' => null,
            'address' => 'Jl. Lama',
        ]);
        $accounts = User::query()->count();

        $this->service()->updateProfile($walkIn, $this->samePayload($walkIn, ['full_name' => 'Rudi Hartono', 'email' => 'rudi@contoh.test']), Actor::staff($staff));

        $fresh = PatientProfile::query()->findOrFail($walkIn->id);
        $this->assertSame('Rudi Hartono', $fresh->full_name);
        $this->assertSame('rudi@contoh.test', $fresh->email);
        $this->assertNull($fresh->user_id);
        $this->assertSame($accounts, User::query()->count());

        $log = AuditLog::query()->where('action', 'patient.updated')->sole();
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($staff->id, (int) $log->user_id);
    }
}
```

- [ ] **Step 8: Jalankan tes service, harus GAGAL (RED)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PatientRegistrationServiceTest
```
Expected: FAIL, 25 tes ERROR dengan `Target class [App\Services\Patients\PatientRegistrationService] does not exist.` (`BindingResolutionException`, karena `app()` mencoba menyelesaikan kelas yang belum ada).

- [ ] **Step 9: Implementasi `PatientRegistrationService` (tool Write)**

`app/Services/Patients/PatientRegistrationService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Patients;

use App\Enums\AccountType;
use App\Enums\BookingChannel;
use App\Models\PatientProfile;
use App\Models\User;
use App\Notifications\PatientWelcomeNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use App\Support\Actor;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pembuatan akun pasien beserta profilnya dan perubahan profil (F5-R9, F5-R20, 11 §6.8). Service tidak membaca
 * request(): validasi, normalisasi (email huruf kecil, telepon tanpa tanda baca), dan penanganan galat HTTP
 * ada di Form Request dan controller. Email unik ditegakkan DB (`users_email_unique`); pelanggarannya dilempar
 * apa adanya sebagai UniqueConstraintViolationException.
 */
final class PatientRegistrationService
{
    /** Kolom profil yang boleh diubah pasien lewat updateProfile() (PAS-05). */
    private const PROFILE_FIELDS = ['full_name', 'birth_date', 'gender', 'phone', 'email', 'address'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Registrasi online: akun pasien + profil + nomor pasien + audit dalam SATU transaksi; email selamat datang
     * dikirim sesudah commit dan kegagalannya tidak membatalkan registrasi (06 §18).
     *
     * @param  array<string, mixed>  $validated  full_name, email, phone, birth_date, gender, address, password
     */
    public function register(array $validated): User
    {
        $user = DB::transaction(function () use ($validated): User {
            $user = new User;
            $user->forceFill([
                'name' => $validated['full_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => $validated['password'],
                'account_type' => AccountType::Patient,
                'is_active' => true,
                'must_change_password' => false,
            ])->save();

            $consentVersion = $this->settings->string('privacy.consent_version', '1.0');

            $profile = new PatientProfile;
            $profile->fill([
                'full_name' => $validated['full_name'],
                'birth_date' => $validated['birth_date'],
                'gender' => $validated['gender'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'address' => $validated['address'],
                'registration_channel' => BookingChannel::Online,
                'consent_at' => now(),
                'consent_version' => $consentVersion,
                'is_active' => true,
            ]);
            $profile->forceFill(['user_id' => $user->getKey(), 'created_by_user_id' => null])->save();

            // Id baru diketahui setelah insert, jadi nomor pasien diisi dalam transaksi yang sama.
            $profile->forceFill(['patient_number' => PatientNumberGenerator::format((int) $profile->getKey())])->save();

            $this->audit->log(
                'patient.registered',
                $profile,
                null,
                [
                    'patient_number' => $profile->patient_number,
                    'registration_channel' => BookingChannel::Online->value,
                    'consent_version' => $consentVersion,
                ],
                "Pasien {$profile->patient_number} mendaftar akun secara online.",
                Actor::patient($user),
            );

            return $user->refresh()->load('patientProfile');
        });

        try {
            $user->notify(new PatientWelcomeNotification);
        } catch (Throwable $e) {
            report($e);
        }

        return $user;
    }

    /**
     * Mengubah enam kolom profil (+ menghapus nomor BPJS bila diminta) dan menyinkronkan nama, email, dan telepon
     * akun login (BR-151) dalam satu transaksi. Audit `patient.updated` hanya bila ada perubahan profil.
     *
     * @param  array<string, mixed>  $validated  full_name, birth_date, gender, phone, email, address, remove_bpjs (opsional)
     */
    public function updateProfile(PatientProfile $profile, array $validated, Actor $actor): PatientProfile
    {
        return DB::transaction(function () use ($profile, $validated, $actor): PatientProfile {
            $profile->fill(Arr::only($validated, self::PROFILE_FIELDS));

            if (filter_var($validated['remove_bpjs'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $profile->bpjs_number = null;
            }

            // Diambil sebelum save(): getChanges() bertahan pada instance dan bisa memuat perubahan lama.
            $changed = $profile->isDirty();
            $profile->save();
            $diff = $changed ? $this->audit->diff($profile) : ['old' => [], 'new' => []];

            $profile->loadMissing('user');
            $account = $profile->user;

            if ($account !== null) {
                $account->forceFill([
                    'name' => $profile->full_name,
                    'email' => $profile->email ?? $account->email,
                    'phone' => $profile->phone,
                ])->save();
            }

            // bpjs_number_last4 turunan dari bpjs_number dan tidak dicatat; updated_at sudah dibuang diff().
            $old = Arr::except($diff['old'], ['bpjs_number_last4']);
            $new = Arr::except($diff['new'], ['bpjs_number_last4']);

            if ($new !== []) {
                $this->audit->log(
                    'patient.updated',
                    $profile,
                    $old,
                    $new,
                    'Profil pasien '.($profile->patient_number ?? '-').' diperbarui.',
                    $actor,
                );
            }

            return $profile->refresh();
        });
    }
}
```

- [ ] **Step 10: Jalankan tes service dan notifikasi, harus LULUS (GREEN)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="PatientRegistrationServiceTest|PatientWelcomeNotificationTest"
```
Expected: PASS, 37 tes (25 + 12). Diagnosis bila gagal:
- `test_a_notification_failure_is_reported_...` gagal `Exceptions::assertReportedCount` bernilai 0: listener `NotificationSending` terdaftar sesudah `Notification::fake()` di tes lain? Tes ini tidak memakai fake; periksa bahwa `Event::listen` dipanggil sebelum `register()` dan bahwa kelas notifikasi tidak `ShouldQueue`.
- `test_an_email_conflict_...` tidak melempar: periksa kolasi `users.email` dan urutan (profil disimpan sebelum akun).
- `insert into `users`` tidak ditemukan di `DB::listen`: pada MariaDB driver SQL ber-backtick; pertahankan awalan persis `insert into \`users\``.

- [ ] **Step 11: Pint, tes terkait, dan pemeriksaan tidak ada tes lama yang patah**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint app\Services\Patients app\Notifications tests\Feature\Patient
& $PHP vendor\bin\pint --test
& $PHP artisan test --filter="PatientRegistrationServiceTest|PatientWelcomeNotificationTest|PatientNumberGeneratorTest|PatientProfileDomainTest|PatientProfilePolicyTest|LocalizationTest|AuditLoggerTest|SettingsServiceTest|ErrorPagesTest|ExceptionRenderingTest"
```
Expected: `pint --test` PASS; seluruh tes terfilter PASS (T1 45 + T2 37 tes baru; tes lama tidak berubah). Berkas `lang/id.json` tidak mengubah hasil tes lama.

- [ ] **Step 12: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add lang/id.json app/Notifications/PatientWelcomeNotification.php tests/Feature/Patient/PatientWelcomeNotificationTest.php
git commit -m @'
feat(fase-5): PatientWelcomeNotification dan terjemahan templat mail (lang/id.json)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
git add app/Services/Patients/PatientRegistrationService.php tests/Feature/Patient/PatientRegistrationServiceTest.php
git commit -m @'
feat(fase-5): PatientRegistrationService (register, updateProfile) beserta tes transaksi dan audit

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
git status --short
```
Expected: dua commit terbuat; `git status` bersih (atau hanya `?? .superpowers/`).

#### Catatan penulis (konteks untuk implementer dan reviewer)

1. **Ketergantungan T2 pada route `login` (milik T3).** F5-R25 mewajibkan tombol email menuju `route('login')`, tetapi urutan tugas menaruh route itu di T3. Agar T2 dapat dieksekusi dan diuji sendiri, kedua kelas tes memasang route pengganti `/_uji/masuk` bila `Route::has('login')` salah (pola yang sama sudah dipakai `GuestRedirectTest` dan `AdminAreaAccessTest`). Setelah T3 blok itu otomatis tidak berjalan. T3 tidak perlu mengubah apa pun pada tes T2; T3 hanya perlu mengingat bahwa `AdminAreaAccessTest`/`GuestRedirectTest` sudah punya route palsu `login` yang harus dihapus (F5-R29), sedangkan tes T2 aman terhadap kedua keadaan.
2. **Deteksi perubahan `updateProfile()` memakai `isDirty()` sebelum `save()`** (bukan semata-mata `diff()` tidak kosong setelah save seperti teks F5-R20), supaya `getChanges()` basi pada instance yang sama tidak menghasilkan audit palsu. Perilaku pada alur HTTP normal identik dengan spesifikasi. T6 tidak terdampak, tetapi bila fase 7 memanggil `updateProfile()` pada instance yang baru saja disimpan, perlindungan ini berlaku.
3. **`updateProfile()` mengembalikan `$profile->refresh()` (instance yang sama)**, bukan `fresh()` (instance baru) seperti kalimat F5-R20. Nilai kembalian setara untuk pemanggil; keuntungannya, `$profile` milik pemanggil ikut tersegarkan. Relasi `user` yang sudah dimuat juga disegarkan oleh `refresh()`.
4. **`register()` mengembalikan `User` yang sudah di-`refresh()` dengan relasi `patientProfile` termuat**, sehingga `RegisterController` (T4) dapat memakai `$user->patientProfile->full_name`/`->patient_number` tanpa lazy load. Nama untuk flash "Registrasi berhasil. Selamat datang, {nama lengkap}." dapat diambil dari `$user->name` (sama dengan `full_name`).
5. **Telepon dan email tidak dinormalisasi di service.** `RegisterRequest`/`UpdatePatientProfileRequest` (T4/T6) wajib menormalisasi lebih dulu (`prepareForValidation`: `Str::lower(trim())` untuk email, `IndonesianPhone::normalize()` untuk telepon) karena service menyimpan nilai apa adanya; email berbeda huruf besar tetap ditolak DB berkat kolasi, tetapi profil/akun akan menyimpan huruf besar bila Request tidak menurunkannya.
6. **Review Focus G.5 "balapan unik → pesan email, bukan 500"** dipenuhi pada lapisan service dengan melempar `UniqueConstraintViolationException` yang sudah memicu rollback; penangkapan dan pesan "Email ini sudah terdaftar. Silakan masuk atau gunakan email lain." adalah tugas `RegisterController@store` (T4) dan T6 (pesan versi V-06).
7. **Hitungan tes** (37) dan struktur direktori `tests/Feature/Patient/` mengikuti kerangka C.2; tidak ada nama baru di luar kerangka selain pembantu privat tes (`ensureLoginRoute`, `recipient`, `forbiddenFragments`, `payload`, `patientToUpdate`, `samePayload`).

---

### Task 3: Routing pasien, login/logout, dashboard (#24, #27) dan layout auth `heading`/`wide`

**Files:**
- Create: `routes/patient.php`
- Create: `app/Support/PatientIntendedUrl.php`
- Create: `app/Http/Requests/Auth/LoginRequest.php`
- Create: `app/Http/Controllers/Auth/LoginController.php`
- Create: `app/Http/Controllers/Patient/DashboardController.php`
- Create: `resources/views/auth/login.blade.php`
- Create: `resources/views/patient/dashboard.blade.php`
- Modify: `bootstrap/app.php` (muat `routes/patient.php` di `then:`; hapus cadangan F1-R2 pada `redirectGuestsTo` dan `redirectUsersTo`)
- Modify: `app/Http/Middleware/EnsureAccountIsActive.php` (hapus cadangan F1-R2)
- Modify: `routes/web.php` (impor `LoginController`; blok "Auth pasien" dengan `login`, `login.store`, `logout`)
- Modify: `resources/views/components/layouts/auth.blade.php` (props `heading` dan `wide`)
- Modify: `resources/views/components/form/checkbox.blade.php` (nilai lama non-skalar tidak boleh memicu galat konversi; ditemukan lewat uji `remember[]=1`)
- Modify: `resources/scss/_shared.scss` (kelas `.auth-card-wide`)
- Modify (tes lama): `tests/Feature/Http/GuestRedirectTest.php` (ditulis ulang), `tests/Feature/Auth/AdminAreaAccessTest.php`, `tests/Feature/View/LayoutsTest.php`, `tests/Feature/Access/AdminPageMatrixTest.php`, `tests/Unit/FrontendManifestTest.php`
- Test (baru): `tests/Feature/Auth/PatientLoginTest.php`, `tests/Feature/Patient/PatientDashboardTest.php`
- Tidak diubah: `app/Http/Middleware/EnsureAccountType.php` (perilaku staf di `/pasien` tetap: redirect ke `admin.dashboard`, JSON 403; F5-R5), `AppServiceProvider` (limiter `login` sudah ada), `config/navigation.php`, `layouts/public.blade.php`, `layouts/patient.blade.php`, `errors/403.blade.php`, `registration-guide.blade.php` (semua guard `Route::has(...)` DIBIARKAN, F5-R4/D-21), `AuthPolishTest` (hanya memeriksa halaman admin), seluruh migration, enum, izin.

**Interfaces:**
- Consumes:
  - Task 1: `User::patientProfile(): HasOne`, `Tests\Support\InteractsWithPatients` (`createPatient(array $userAttributes = [], array $profileAttributes = []): User`, `patientPassword(): string` = `Rahasia123`).
  - Fase 1: limiter `login` (`Limit::perMinute(5)->by(emailKey)`), middleware alias `auth`, `guest`, `account`, `active`; handler 419 dan 429 di `bootstrap/app.php`; `lang/id/auth.php` (`failed` = "Email atau password salah."); komponen `x-layouts.auth`, `x-layouts.patient`, `x-form.input`, `x-form.checkbox`, `x-loading-button`, `x-empty-state`, `x-flash-alert`, `x-form.errors-summary`; `App\Support\NavigationMenu`; `InteractsWithStaff::createStaff()`.
  - Task 2 TIDAK dipakai di tugas ini (registrasi ada di Task 4).
- Produces:
  - Route (4 dari 13): `login` (GET `/masuk`, `guest`), `login.store` (POST `/masuk`, `guest`, `throttle:login`), `logout` (POST `/keluar`, `auth`), `patient.dashboard` (GET `/pasien`, `web`, `auth`, `account:patient`, `active`).
  - `App\Support\PatientIntendedUrl` (`final`; `public static function pull(Request $request): string`), dipakai ulang Task 4.
  - `App\Http\Requests\Auth\LoginRequest` (`authorize()`, `prepareForValidation()`, `rules()`, `messages()`, `authenticate(): User`).
  - `App\Http\Controllers\Auth\LoginController` (`create(): View`, `store(LoginRequest): RedirectResponse`, `destroy(Request): RedirectResponse`) dan `App\Http\Controllers\Patient\DashboardController` (`index(Request): View`).
  - View `auth.login` dan `patient.dashboard`; layout `x-layouts.auth` dengan props `heading` (default = `title`) dan `wide` (default `false`); kelas SCSS `.auth-card-wide` (`max-width: 35rem`).
  - Penunjuk untuk tugas lain: `routes/web.php` blok "Auth pasien" (Task 4 menambah `register`/`register.store` ke grup `guest`; Task 5 menambah `password.*`), `routes/patient.php` (Task 6 menambah `profile.edit`, `profile.update`, `password.update`).

**Test IDs:** F-AUTH-02, F-AUTH-03 (logout pasien), F-PAS-01 (sebagian: sapaan, nomor pasien, kartu kosong), EMP-04, SEK-02 (sisi pasien: regenerasi sesi, invalidate, token CSRF baru), SEK-03 (throttle login), SEK-06 (CSRF 419 pada login), SEK-15 (nama `<script>` ter-escape di dashboard), SEK-16 (pesan generik, status identik), AKS-07 (intended URL; versi fase 5 memakai `/pasien?contoh=utuh`), AKS-08 (akun nonaktif: generik saat login, diputus saat sesi berjalan), AKS-10 (staf di `/masuk`), ERR-02 (sisi pasien: tautan "Ke Dasbor" menuju `patient.dashboard`), ERR-03 (419 pada login pasien), Review Focus G.1 (login), G.2 (login: `email[]`, `password[]`, `remember=abc`, `remember[]=1`), G.4 (intended, redirect guest/user, akun nonaktif saat sesi berjalan), F5-R3, F5-R4, F5-R5, F5-R6, F5-R7, F5-R8, F5-R13, F5-R22, F5-R23, F5-R29 (bagian T3).

**Catatan teknis (diverifikasi dari source):**
1. **Cadangan F1-R2 yang dihapus.** `bootstrap/app.php` baris 56-75 berisi `Route::has('login') ? route('login') : route('home')` dan `Route::has('patient.dashboard') ? ... : route('home')`; `EnsureAccountIsActive` baris 31-32 berisi `(Route::has('login') ? 'login' : 'home')`. Ketiganya diganti pemanggilan langsung. Guard `Route::has(...)` di `layouts/public.blade.php` (`login`, `logout`, `patient.dashboard`, `patient.appointments.create`), `layouts/patient.blade.php` (`logout`), `errors/403.blade.php` (`patient.dashboard`), `registration-guide.blade.php` (`register`, `patient.appointments.create`) dan `NavigationMenu::resolveLink` TIDAK diubah: sebagian masih dibutuhkan untuk route fase 6 dan sisanya aman (D-21). Karena `login` sekarang ada, header publik otomatis menampilkan tombol "Masuk" bagi tamu dan "Akun Saya" + "Keluar" bagi pasien; tombol "Daftar Akun" di panduan pendaftaran muncul otomatis di Task 4.
2. **Perilaku staf di area pasien (F5-R5).** `EnsureAccountType` untuk `account:patient` tidak diubah: staf (HTML) dialihkan ke `admin.dashboard`, JSON mendapat 403. Pasien di `/admin*` tetap 403 (`AdminAreaAccessTest`). Tes baru mengunci perilaku ini pada `/pasien` dan `/masuk`.
3. **Urutan middleware.** Grup pasien `['web', 'auth', 'account:patient', 'active']` didaftarkan SEBELUM grup admin di `then:` (tidak ada tabrakan URI: `/pasien` vs `/admin`). Route auth tamu ada di `routes/web.php` (mendapat `web` otomatis). `login.store` memakai `guest` lalu `throttle:login`: pengguna yang sudah login tidak menghabiskan jatah throttle. Limiter `login` memakai kunci `md5('login'.<email dinormalkan>.'|'.<ip>)` (`AppServiceProvider::emailKey()`, nilai bukan string dianggap kosong), sehingga `RateLimiter::clear(...)` di controller memakai email yang sudah ternormalisasi (pola `AdminLoginController`).
4. **Autentikasi pasien (F5-R6).** `Auth::attempt(['email', 'password', 'account_type' => 'patient', 'is_active' => true], $this->boolean('remember'))`. Email tak dikenal, password salah, akun staf, dan akun nonaktif semuanya melempar `ValidationException::withMessages(['email' => __('auth.failed')])` (D-22/AKS-08 menang atas teks khusus V-02: tidak ada pesan "Akun Anda tidak aktif" di form login). Tidak ada audit login/logout pasien (katalog 12 §8.2). `SessionGuard::attempt` Laravel 12 sudah memakai `Timebox` 200 ms; tidak ada hash dummy (hardening `auth.timebox_duration` tetap carry fase 9).
5. **Intended URL (F5-R7).** `Authenticate` Laravel menyimpan `fullUrl()` GET ke sesi (`url.intended`) saat tamu dialihkan. `PatientIntendedUrl::pull()` memakai `session()->pull('url.intended')` (selalu menghapus kunci), menerimanya hanya bila `string` dan path-nya (`parse_url(..., PHP_URL_PATH)`) bukan `/admin` dan tidak diawali `/admin/`; selain itu `route('patient.dashboard')`. Path kosong/null (mis. `http://localhost`) dan nilai bukan string jatuh ke dashboard. AKS-07 asli memakai `/pasien/pendaftaran/baru?sesi=ID` (route fase 6, tidak ada di fase 5; `InternalLinkTest` dan `BannerButtonTest` mengunci bahwa route itu belum ada). Route `patient.profile.edit` baru ada di Task 6, jadi tes fase 5 memakai URL terlindungi yang sudah ada, `/pasien?contoh=utuh` (dashboard dengan query), dan membuktikan bahwa query dipertahankan. Tes memakai `/pasien?contoh=utuh` hingga fase 6 memasang kembali tes AKS-07 asli.
6. **Input larik dan nilai liar (Review Focus 2).** `email[]=x` menghasilkan "Format email tidak valid." (aturan `email:rfc` menolak non-string; `prepareForValidation` hanya menormalkan string), `password[]=x` menghasilkan "Password tidak valid." (aturan `string`), `remember=abc` menghasilkan "Pilihan ingat saya tidak valid." (`boolean`). **Temuan:** `components/form/checkbox.blade.php` baris 38-40 melakukan `(string) old($key, ...)`; untuk `remember[]=1` nilai lama berupa larik, sehingga render ulang `/masuk` melempar "Array to string conversion" (500). Tugas ini memperbaiki komponen (nilai lama non-skalar dianggap tidak tercentang), dikunci tes `test_array_remember_value_does_not_break_the_form_when_it_is_rendered_again`. Komponen `x-form.input` sudah menyaring nilai lama non-skalar (`is_scalar`).
7. **Throttle POST.** Handler 429 di `bootstrap/app.php` mengubah `ThrottleRequestsException` pada POST non-JSON menjadi redirect 302 ke `SafeRedirect::back()` (`url()->previous()`), `withInput` tanpa field sensitif, flash `error` "Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik." dan header `Retry-After` (D-23). Tes memakai `from(route('login'))` agar `previous()` = form login.
8. **Dashboard (F5-R22).** `<x-layouts.patient title="Dashboard Pasien">` tidak menulis `<h1>` sendiri (layout publik tidak punya h1); view menulis satu `<h1>` "Halo, {full_name}". `layouts/public.blade.php` sudah menampilkan flash (`x-flash-alert`) di `<main>`, sehingga view dashboard tidak perlu memanggilnya. Tombol di kartu kosong memakai guard `Route::has('patient.appointments.create')` (fase 6 menambahkan route; tombol "Daftar Kunjungan" lalu muncul tanpa mengubah view). Tautan "Ubah Profil dan Akun" TIDAK ditulis di sini (route baru ada di Task 6, yang menambahkannya). Kartu dan bagian diberi `aria-labelledby` (`kunjungan-judul`, `profil-judul`) agar tes mengisolasi isi bagian (tombol "Daftar Kunjungan" juga muncul di header dan sidebar). Akun pasien tanpa profil → `abort(404)` (F5-R13; tidak ada dalam operasi normal).
9. **Layout auth (F5-R23).** `<h1 class="h3 mb-1">{{ $heading ?? $title }}</h1>`; `<title>` tetap memakai `$title`. Kartu: `@class(['auth-card', 'auth-card-wide' => $wide, 'mx-auto'])`, yang menghasilkan `class="auth-card mx-auto"` (tanpa `wide`, identik dengan sebelumnya) atau `class="auth-card auth-card-wide mx-auto"`. `AuthPolishTest::test_auth_pages_have_a_single_card_surface` menghitung substring `auth-card` pada halaman admin (bukan `wide`), jadi tetap 1; **halaman `wide` (Task 4) memuat substring itu dua kali**, sehingga tes halaman registrasi menghitung `class="auth-card` (satu kali). `FrontendManifestTest::test_shared_and_admin_styles_define_the_classes_components_rely_on` mengunci kelas SCSS; tugas ini menambahkan `.auth-card-wide` ke daftar. Perubahan SCSS memerlukan `npm run build` sebelum tes (kontrak `FrontendManifestTest`); tidak ada entri Vite atau JS baru.
10. **Tes lama yang berubah (F5-R29, bagian T3).** Hasil grep atas `tests/`, `app/`, `resources/`, `config/` untuk route palsu bernama `login`, `logout`, `patient.dashboard`, `register`, dan `Route::has(...)` terkait: `GuestRedirectTest` (4 tempat, ditulis ulang), `AdminAreaAccessTest` baris 62 (route palsu `login`), `LayoutsTest` baris 134-146, 215-222, 225-228 (route palsu `login`, `patient.dashboard`, `logout`; cabang "belum ada"), `AdminPageMatrixTest` baris 526 (`dashboardFor`). `ErrorPagesTest` dan `ExceptionRenderingTest` tidak menyentuh route ini (tidak diubah). `PublicContentPagesTest::test_registration_guide_shows_buttons_only_for_routes_that_exist` memakai `Route::has` secara dinamis sehingga tetap lulus; ia berubah perilaku di Task 4 (`register` ada). Tes lain yang mendaftarkan route palsu `patient.appointments.create` tidak terpengaruh (route itu fase 6). `SecurityHeadersTest` sudah memuat `/masuk` pada daftar `sensitivePaths` dan tetap lulus; penambahan `htmlPages` di T8.
11. **Mode ketat dan lazy loading.** `$request->user()->patientProfile` memicu satu query lazy pada model tunggal (pencegahan lazy loading hanya berlaku untuk koleksi > 1 model). Controller memakai `loadMissing('patientProfile')` agar tidak bergantung pada hal itu; `createPatient()` (Task 1) sudah memuat relasinya.
12. **DB pengembangan.** Implementer tidak menjalankan `migrate`/`db:seed` pada `unpam_hospital`.
13. Jumlah tes baru ±45 (`PatientLoginTest` 35 termasuk provider, `PatientDashboardTest` 10, `LayoutsTest` +5 −1, `GuestRedirectTest` 7 menggantikan 6).

---

#### Siklus 1: layout auth (`heading`, `wide`) dan `.auth-card-wide`

- [ ] **Step 1: Verifikasi prasyarat (cabang, Task 1 dan Task 2 sudah ada)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
Test-Path tests\Support\InteractsWithPatients.php
Test-Path app\Services\Patients\PatientRegistrationService.php
Test-Path routes\patient.php
(& $PHP artisan route:list --except-vendor --json | ConvertFrom-Json).Count
```
Expected: `fase-5-akun-pasien`; `git status` kosong atau hanya `?? .superpowers/`; `True`; `True`; `False`; `128`. Apache dan MySQL harus sudah di-Start di `C:\xampp3\xampp-control.exe`.

- [ ] **Step 2: Tulis tes yang gagal untuk layout auth (tool Edit) `tests/Feature/View/LayoutsTest.php`**

Tambahkan tiga tes tepat sebelum `test_layouts_read_site_settings_through_the_view_composer`.

old:
```php
    public function test_layouts_read_site_settings_through_the_view_composer(): void
```
new:
```php
    public function test_auth_layout_heading_prop_sets_the_h1_but_not_the_document_title(): void
    {
        $this->page('/_uji/auth', '<x-layouts.auth title="Masuk" heading="Masuk ke Akun Pasien"><p>Isi</p></x-layouts.auth>');

        $response = $this->get('/_uji/auth')->assertOk();

        $response->assertSee('<title>Masuk — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="h3 mb-1">Masuk ke Akun Pasien</h1>', false);
        $this->assertSame(1, $this->h1Count($response->getContent()));
    }

    public function test_auth_layout_without_heading_uses_the_title_for_the_h1(): void
    {
        $this->page('/_uji/auth', '<x-layouts.auth title="Masuk Uji"><p>Isi</p></x-layouts.auth>');

        $this->get('/_uji/auth')->assertOk()->assertSee('<h1 class="h3 mb-1">Masuk Uji</h1>', false);
    }

    public function test_auth_layout_wide_prop_adds_the_wide_card_class_and_the_default_does_not(): void
    {
        $this->page('/_uji/auth-lebar', '<x-layouts.auth title="Daftar Uji" :wide="true"><p>Isi</p></x-layouts.auth>');
        $this->page('/_uji/auth-biasa', '<x-layouts.auth title="Masuk Uji"><p>Isi</p></x-layouts.auth>');

        $this->get('/_uji/auth-lebar')->assertOk()->assertSee('class="auth-card auth-card-wide mx-auto"', false);
        $this->get('/_uji/auth-biasa')->assertOk()
            ->assertSee('class="auth-card mx-auto"', false)
            ->assertDontSee('auth-card-wide', false);
    }

    public function test_layouts_read_site_settings_through_the_view_composer(): void
```

- [ ] **Step 3: Tulis tes kunci SCSS (tool Edit) `tests/Unit/FrontendManifestTest.php`**

old:
```php
        foreach (['.table-cards', 'td[data-label]::before', '.skip-link', ':focus-visible', '2.75rem', '.auth-card', 'prefers-reduced-motion'] as $needle) {
```
new:
```php
        foreach (['.table-cards', 'td[data-label]::before', '.skip-link', ':focus-visible', '2.75rem', '.auth-card', '.auth-card-wide', '35rem', 'prefers-reduced-motion'] as $needle) {
```

- [ ] **Step 4: Jalankan tes, pastikan GAGAL (RED nyata)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="LayoutsTest|FrontendManifestTest"
```
Expected: FAIL pada `test_auth_layout_heading_prop_sets_the_h1_but_not_the_document_title` (h1 masih `Masuk`, bukan `Masuk ke Akun Pasien`), `test_auth_layout_wide_prop_adds_the_wide_card_class_and_the_default_does_not` (kelas `auth-card-wide` tidak ada), dan `test_shared_and_admin_styles_define_the_classes_components_rely_on` (kelas `.auth-card-wide` tidak ada di `_shared.scss`). `test_auth_layout_without_heading_uses_the_title_for_the_h1` sudah lulus.

- [ ] **Step 5: Ubah layout auth (tool Edit) `resources/views/components/layouts/auth.blade.php`**

Tiga penggantian terpisah.

old:
```blade
@props(['title', 'subtitle' => null])
```
new:
```blade
@props(['title', 'subtitle' => null, 'heading' => null, 'wide' => false])
```

old:
```blade
        <div class="auth-card mx-auto">
```
new:
```blade
        <div @class(['auth-card', 'auth-card-wide' => $wide, 'mx-auto'])>
```

old:
```blade
                <h1 class="h3 mb-1">{{ $title }}</h1>
```
new:
```blade
                <h1 class="h3 mb-1">{{ $heading ?? $title }}</h1>
```

- [ ] **Step 6: Tambah `.auth-card-wide` (tool Edit) `resources/scss/_shared.scss`**

old:
```scss
// ---------------------------------------------------------------- Gambar
.auth-brand {
```
new:
```scss
// Variasi lebar untuk formulir panjang, mis. registrasi pasien (F5-R23).
.auth-card-wide {
  max-width: 35rem;
}

// ---------------------------------------------------------------- Gambar
.auth-brand {
```

- [ ] **Step 7: Build aset dan jalankan tes, pastikan LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
npm run build
& $PHP artisan test --filter="LayoutsTest|FrontendManifestTest|AuthPolishTest"
```
Expected: `npm run build` sukses (baris `built in ...`, tanpa galat Sass); tes PASS semuanya (`AuthPolishTest` tetap lulus karena halaman admin tidak memakai `wide`).

- [ ] **Step 8: Commit siklus 1**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add resources/views/components/layouts/auth.blade.php resources/scss/_shared.scss tests/Feature/View/LayoutsTest.php tests/Unit/FrontendManifestTest.php
git commit -m @'
feat(fase-5): layout auth mendukung props heading dan wide (kelas auth-card-wide)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: pint bersih (`PASS`), satu commit.

---

#### Siklus 2: routing pasien, login/logout, dashboard

- [ ] **Step 9: Tulis tes (tool Write) `tests/Feature/Auth/PatientLoginTest.php`**

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Login dan logout pasien (#24, F5-R6 sampai F5-R8): AKS-07, AKS-08, AKS-10, SEK-02, SEK-03, SEK-06 (419),
 * SEK-16 dan Review Focus G.1, G.2, G.4. Pola mengikuti AdminLoginTest.
 */
class PatientLoginTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const WRONG = 'SalahSekali1';

    private function postLogin(string $email, ?string $password = null, array $extra = []): TestResponse
    {
        return $this->from(route('login'))
            ->post(route('login.store'), ['email' => $email, 'password' => $password ?? $this->patientPassword()] + $extra);
    }

    /** Pasien berprofil dengan email tetap `siti@contoh.test` dan password `Rahasia123`. */
    private function patientAccount(array $attributes = []): User
    {
        return $this->createPatient($attributes + ['email' => 'siti@contoh.test']);
    }

    /** @return array{0: int, 1: ?string, 2: array<int, string>} */
    private function outcome(string $email, ?string $password = null): array
    {
        $response = $this->postLogin($email, $password);

        return [$response->getStatusCode(), $response->headers->get('Location'), session('errors')->get('email')];
    }

    // --- tampilan form -----------------------------------------------------------------------

    public function test_login_page_renders_the_form_for_guests(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertSee('<title>Masuk — '.config('app.name').'</title>', false)
            ->assertSee('Masuk ke Akun Pasien')
            ->assertSee('Masuk untuk mengelola profil dan pendaftaran kunjungan Anda.')
            ->assertSee('action="'.route('login.store').'"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="remember"', false)
            ->assertSee('Ingat saya')
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $this->assertSame(1, substr_count($response->getContent(), 'class="auth-card'), 'Satu permukaan kartu, tanpa kelas lebar.');
        $this->assertStringNotContainsString('auth-card-wide', $response->getContent());
    }

    public function test_login_email_is_autofocused_unless_there_are_errors(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*name="email"[^>]*\sautofocus/', $html);
        $this->assertSame(1, preg_match_all('/\sautofocus(?=[\s=>])/', $html), 'Hanya satu atribut autofocus.');

        $failed = $this->from(route('login'))->followingRedirects()
            ->post(route('login.store'), ['email' => 'tidak.ada@contoh.test', 'password' => self::WRONG])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-errors-summary', $failed);
        $this->assertStringContainsString('Email atau password salah.', $failed);
        $this->assertStringNotContainsString('autofocus', $failed);
    }

    // --- login sukses ------------------------------------------------------------------------

    public function test_patient_can_log_in_and_lands_on_the_dashboard_without_any_audit_row(): void
    {
        $this->travelTo('2026-10-08 09:30:00');
        $patient = $this->patientAccount();
        $auditBefore = AuditLog::query()->count();

        $this->postLogin('siti@contoh.test')->assertRedirect(route('patient.dashboard'));

        $this->assertAuthenticatedAs($patient);
        $this->assertSame('2026-10-08 09:30:00', $patient->fresh()->last_login_at->format('Y-m-d H:i:s'));
        $this->assertSame($auditBefore, AuditLog::query()->count(), 'Login pasien tidak diaudit (F5-R6).');
        $this->get(route('patient.dashboard'))->assertOk();
    }

    /** SEK-02: ID sesi berubah setelah login. */
    public function test_session_id_changes_after_login(): void
    {
        $this->patientAccount();

        $this->get(route('login'));
        $before = session()->getId();

        $this->postLogin('siti@contoh.test')->assertRedirect(route('patient.dashboard'));

        $this->assertNotSame($before, session()->getId());
    }

    public function test_remember_me_sets_the_recaller_cookie_only_when_requested(): void
    {
        $this->patientAccount();
        $recaller = $this->app['auth']->guard('web')->getRecallerName();

        $this->postLogin('siti@contoh.test')->assertCookieMissing($recaller);

        $this->app['auth']->guard('web')->logout();

        $this->postLogin('siti@contoh.test', null, ['remember' => '1'])
            ->assertRedirect(route('patient.dashboard'))
            ->assertCookie($recaller);
    }

    public function test_email_with_spaces_and_uppercase_is_normalised_before_authentication(): void
    {
        $patient = $this->patientAccount();

        $this->postLogin('  Siti@Contoh.TEST ')->assertRedirect(route('patient.dashboard'));

        $this->assertAuthenticatedAs($patient);
    }

    // --- kegagalan: pesan generik (SEK-16, AKS-08, AKS-10) -----------------------------------

    /** SEK-16 dan Review Focus G.1: email tak dikenal, password salah, akun staf, dan akun nonaktif tak dapat dibedakan. */
    public function test_unknown_email_wrong_password_staff_and_inactive_accounts_give_identical_generic_responses(): void
    {
        $this->patientAccount();
        $this->createPatient(['email' => 'nonaktif@contoh.test', 'is_active' => false]);
        $this->createStaff('content-admin', ['email' => 'staf@contoh.test', 'password' => $this->patientPassword()]);

        $unknown = $this->outcome('tidak-ada@contoh.test', self::WRONG);
        $wrongPassword = $this->outcome('siti@contoh.test', self::WRONG);
        $staff = $this->outcome('staf@contoh.test');
        $inactive = $this->outcome('nonaktif@contoh.test');

        $this->assertSame([302, route('login'), ['Email atau password salah.']], $unknown);
        $this->assertSame($unknown, $wrongPassword);
        $this->assertSame($unknown, $staff);
        $this->assertSame($unknown, $inactive);
        $this->assertGuest();
    }

    /** AKS-10: kredensial staf yang benar di `/masuk` gagal generik dan tidak membentuk sesi. */
    public function test_staff_credentials_on_the_patient_form_fail_generically_without_a_session(): void
    {
        $staff = $this->createStaff('content-admin', ['email' => 'staf@contoh.test', 'password' => $this->patientPassword()]);

        $this->postLogin('staf@contoh.test')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

        $this->assertGuest();
        $this->assertNull($staff->fresh()->last_login_at);
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    /** AKS-08: pasien nonaktif ditolak dengan pesan generik, bukan pesan "tidak aktif". */
    public function test_inactive_patient_gets_the_generic_message_instead_of_an_inactive_notice(): void
    {
        $patient = $this->createPatient(['email' => 'nonaktif@contoh.test', 'is_active' => false]);

        $html = $this->from(route('login'))->followingRedirects()
            ->post(route('login.store'), ['email' => 'nonaktif@contoh.test', 'password' => $this->patientPassword()])
            ->assertOk()
            ->getContent();

        $this->assertGuest();
        $this->assertNull($patient->fresh()->last_login_at);
        $this->assertStringContainsString('Email atau password salah.', $html);
        $this->assertStringNotContainsString('Akun Anda tidak aktif', $html);
        $this->assertStringNotContainsString('dinonaktifkan', $html);
    }

    /** AKS-08 dan Review Focus G.4: akun dinonaktifkan saat sesi berjalan, diputus dengan flash dari middleware `active`. */
    public function test_account_deactivated_during_a_running_session_is_sent_to_login_with_the_middleware_flash(): void
    {
        $this->patientAccount();
        $this->postLogin('siti@contoh.test')->assertRedirect(route('patient.dashboard'));
        $this->get(route('patient.dashboard'))->assertOk();

        // Ubah instance yang dipegang guard (guard menyimpan user antar-permintaan di tes), bukan instance lain.
        $this->app['auth']->guard('web')->user()->forceFill(['is_active' => false])->save();

        $this->get(route('patient.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Akun Anda dinonaktifkan. Silakan hubungi administrator rumah sakit.');
        $this->assertGuest();
    }

    public function test_required_fields_and_email_format_are_validated_with_indonesian_messages(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), [])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Email wajib diisi.', 'password' => 'Password wajib diisi.']);

        $this->postLogin('bukan-email')->assertSessionHasErrors(['email' => 'Format email tidak valid.']);
        $this->postLogin(str_repeat('a', 250).'@contoh.test')->assertSessionHasErrors(['email' => 'Email maksimal 255 karakter.']);
        $this->postLogin('siti@contoh.test', str_repeat('a', 256))->assertSessionHasErrors(['password' => 'Password maksimal 255 karakter.']);
        $this->assertGuest();
    }

    /** Review Focus G.2: larik dan nilai liar menghasilkan galat field, bukan 500; halaman dirender ulang dengan wajar. */
    public function test_array_and_wild_values_give_field_errors_without_a_500(): void
    {
        $this->patientAccount();

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => ['siti@contoh.test'], 'password' => ['Rahasia123'], 'remember' => 'abc'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'Format email tidak valid.',
                'password' => 'Password tidak valid.',
                'remember' => 'Pilihan ingat saya tidak valid.',
            ]);

        $html = $this->from(route('login'))->followingRedirects()
            ->post(route('login.store'), ['email' => ['x'], 'password' => ['y'], 'remember' => 'abc'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Pilihan ingat saya tidak valid.', $html);
        $this->assertGuest();
    }

    /** Temuan fase 5: nilai lama larik pada checkbox `remember` memicu "Array to string conversion" saat form dirender ulang. */
    public function test_array_remember_value_does_not_break_the_form_when_it_is_rendered_again(): void
    {
        $this->patientAccount();

        $html = $this->from(route('login'))->followingRedirects()
            ->post(route('login.store'), ['email' => 'siti@contoh.test', 'password' => $this->patientPassword(), 'remember' => ['1']])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Pilihan ingat saya tidak valid.', $html);
        $this->assertGuest();
    }

    public function test_remember_checkbox_keeps_its_checked_state_after_a_failed_attempt(): void
    {
        $html = $this->from(route('login'))->followingRedirects()
            ->post(route('login.store'), ['email' => 'tidak.ada@contoh.test', 'password' => self::WRONG, 'remember' => '1'])
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*name="remember"[^>]*\schecked/', $html);
    }

    public function test_password_is_not_flashed_back_into_the_form(): void
    {
        $this->postLogin('siti@contoh.test', self::WRONG);

        $this->assertNull(session('_old_input.password'));
        $this->assertSame('siti@contoh.test', session('_old_input.email'));
    }

    // --- CSRF (SEK-06, ERR-03) ---------------------------------------------------------------

    public function test_missing_csrf_token_on_login_redirects_back_with_a_session_expired_message(): void
    {
        $this->app['env'] = 'local'; // mengaktifkan kembali ValidateCsrfToken (dilewati saat env `testing`)

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => 'siti@contoh.test', 'password' => 'RahasiaUji1'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Sesi formulir Anda telah berakhir. Silakan periksa isian Anda lalu kirim ulang.')
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest();
    }

    public function test_a_valid_csrf_token_passes_the_csrf_check(): void
    {
        $this->app['env'] = 'local';

        $this->withSession(['_token' => 'token-uji'])
            ->from(route('login'))
            ->post(route('login.store'), ['_token' => 'token-uji', 'email' => '', 'password' => ''])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email', 'password'])
            ->assertSessionMissing('error');
    }

    // --- throttle (SEK-03) -------------------------------------------------------------------

    public function test_sixth_failed_attempt_is_throttled_with_a_flash_message_and_retry_after_header(): void
    {
        $this->patientAccount();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postLogin('siti@contoh.test', self::WRONG)
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('email');
        }

        $this->postLogin('siti@contoh.test', self::WRONG)
            ->assertStatus(302)
            ->assertRedirect(route('login'))
            ->assertHeader('Retry-After')
            ->assertSessionHas('error', fn ($message) => preg_match('/^Terlalu banyak percobaan\. Silakan coba lagi dalam \d+ detik\.$/', $message) === 1)
            ->assertSessionMissing('_old_input.password');

        // Email lain tidak terkena batas email yang sama.
        $this->postLogin('lain@contoh.test', self::WRONG)->assertSessionHasErrors('email');
    }

    public function test_throttle_key_is_shared_across_email_case_and_spacing(): void
    {
        $this->patientAccount();

        foreach (['  Siti@Contoh.TEST ', 'siti@contoh.test', 'SITI@contoh.test ', 'siti@CONTOH.test', 'Siti@contoh.test'] as $variant) {
            $this->postLogin($variant, self::WRONG)->assertSessionHasErrors('email');
        }

        $this->postLogin('siti@contoh.test', self::WRONG)
            ->assertHeader('Retry-After')
            ->assertSessionHas('error');
    }

    public function test_successful_login_clears_the_login_throttle_counter(): void
    {
        $this->patientAccount();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postLogin('siti@contoh.test', self::WRONG)->assertSessionHasErrors('email');
        }

        // Permintaan ke-5 (sukses) ikut dihitung middleware; setelah itu penghitung harus kembali nol.
        $this->postLogin('  Siti@Contoh.TEST ')->assertRedirect(route('patient.dashboard'));
        $this->app['auth']->guard('web')->logout();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postLogin('siti@contoh.test', self::WRONG)
                ->assertSessionHasErrors('email')
                ->assertHeaderMissing('Retry-After');
        }

        $this->postLogin('siti@contoh.test', self::WRONG)->assertHeader('Retry-After');
    }

    // --- intended URL (AKS-07, Review Focus G.4) ---------------------------------------------

    /**
     * AKS-07 (versi fase 5). Route `patient.profile.edit` baru ada di Task 6 dan `/pasien/pendaftaran/baru` di fase 6,
     * jadi URL terlindungi yang membawa query diwakili dashboard `/pasien?contoh=utuh`. Tes AKS-07 asli (sesi bookable)
     * dipasang kembali di fase 6.
     */
    public function test_login_returns_to_the_intended_protected_url_with_its_query_intact(): void
    {
        $this->patientAccount();

        $this->get('/pasien?contoh=utuh')->assertRedirect(route('login'));
        $this->postLogin('siti@contoh.test')
            ->assertRedirect(url('/pasien?contoh=utuh'))
            ->assertSessionMissing('url.intended');

        $this->get('/pasien?contoh=utuh')->assertOk();
    }

    public function test_intended_admin_url_is_ignored_and_the_key_is_consumed(): void
    {
        $this->patientAccount();

        $this->get('/admin/dokter')->assertRedirect(route('admin.login'));
        $this->postLogin('siti@contoh.test')
            ->assertRedirect(route('patient.dashboard'))
            ->assertSessionMissing('url.intended');
    }

    #[DataProvider('unsafeIntendedValues')]
    public function test_unsafe_or_non_string_intended_values_fall_back_to_the_dashboard(mixed $intended): void
    {
        $this->patientAccount();

        $this->withSession(['url.intended' => $intended]);

        $this->postLogin('siti@contoh.test')->assertRedirect(route('patient.dashboard'));
    }

    /** @return array<string, array{0: mixed}> */
    public static function unsafeIntendedValues(): array
    {
        return [
            'admin tepat' => ['http://localhost/admin'],
            'admin dengan subpath dan query' => ['http://localhost/admin/dokter?q=a'],
            'bukan string (larik)' => [['http://localhost/pasien']],
            'angka' => [123],
            'kosong' => [''],
            'tanpa path' => ['http://localhost'],
        ];
    }

    // --- logout (F5-R8, SEK-02) --------------------------------------------------------------

    public function test_logout_invalidates_the_session_issues_a_new_csrf_token_and_is_not_audited(): void
    {
        $this->patientAccount();
        $this->postLogin('siti@contoh.test');
        $idBefore = session()->getId();
        $tokenBefore = session()->token();
        $auditBefore = AuditLog::query()->count();

        $this->post(route('logout'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', 'Anda telah keluar.');

        $this->assertGuest();
        $this->assertNotSame($idBefore, session()->getId());
        $this->assertNotSame($tokenBefore, session()->token());
        $this->assertSame($auditBefore, AuditLog::query()->count());
        $this->get(route('patient.dashboard'))->assertRedirect(route('login'));
    }

    public function test_guest_logout_goes_to_the_patient_login(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_staff_can_also_use_the_shared_logout_route(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    // --- pengalihan akun yang sudah login ----------------------------------------------------

    public function test_logged_in_patient_opening_the_login_page_goes_to_the_dashboard(): void
    {
        $this->actingAs($this->patientAccount())->get(route('login'))->assertRedirect(route('patient.dashboard'));
    }

    public function test_logged_in_staff_opening_the_login_page_or_the_patient_area_goes_to_the_admin_dashboard(): void
    {
        $staff = $this->createStaff('content-admin');

        $this->actingAs($staff)->get(route('login'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($staff)->get('/pasien')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($staff)->getJson('/pasien')->assertForbidden();
    }

    public function test_guest_on_the_patient_area_is_sent_to_the_patient_login(): void
    {
        $this->get('/pasien')->assertRedirect(route('login'));
    }

    // --- route -------------------------------------------------------------------------------

    /** F5-R3: URI, method, dan middleware empat route Task 3. */
    public function test_task_3_routes_have_the_expected_uri_method_and_middleware(): void
    {
        $expected = [
            'login' => ['GET', 'masuk', ['guest']],
            'login.store' => ['POST', 'masuk', ['guest', 'throttle:login']],
            'logout' => ['POST', 'keluar', ['auth']],
            'patient.dashboard' => ['GET', 'pasien', ['auth', 'account:patient', 'active']],
        ];

        foreach ($expected as $name => [$method, $uri, $middleware]) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} belum terdaftar.");
            $this->assertContains($method, $route->methods(), $name);
            $this->assertSame($uri, $route->uri(), $name);
            $this->assertSame($middleware, array_values(array_diff(array_filter($route->gatherMiddleware(), 'is_string'), ['web'])), $name);
        }
    }
}
```

- [ ] **Step 10: Tulis tes (tool Write) `tests/Feature/Patient/PatientDashboardTest.php`**

```php
<?php

namespace Tests\Feature\Patient;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Dashboard pasien (#27, F5-R22): EMP-04, SEK-15, F5-R13, ERR-02 (sisi pasien). Tombol kunjungan mengikuti
 * keberadaan route fase 6 (`patient.appointments.create`).
 */
class PatientDashboardTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    /** Isi `<section aria-labelledby="{id}">` (header dan sidebar juga memuat teks "Daftar Kunjungan"). */
    private function section(string $html, string $id): string
    {
        $this->assertSame(
            1,
            preg_match('#<section[^>]*aria-labelledby="'.preg_quote($id, '#').'"[^>]*>(.*?)</section>#s', $html, $matches),
            "Bagian {$id} tidak ditemukan.",
        );

        return $matches[1];
    }

    public function test_guest_is_redirected_to_the_patient_login(): void
    {
        $this->get(route('patient.dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_greets_the_patient_and_shows_the_patient_number_and_contact_summary(): void
    {
        $patient = $this->createPatient(['email' => 'siti@contoh.test'], ['full_name' => 'Siti Rahma', 'phone' => '081355550001']);
        $number = $patient->patientProfile->patient_number;

        $response = $this->actingAs($patient)->get(route('patient.dashboard'));

        $response->assertOk()
            ->assertSee('<title>Dashboard Pasien — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSee('Halo, Siti Rahma')
            ->assertSee('No. pasien: '.$number);
        $this->assertMatchesRegularExpression('/^P\d{6,}$/', $number);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));

        $profile = $this->section($response->getContent(), 'profil-judul');
        $this->assertStringContainsString('Profil Anda', $response->getContent());
        $this->assertStringContainsString('siti@contoh.test', $profile);
        $this->assertStringContainsString('081355550001', $profile);
    }

    /** EMP-04: kartu kosong; tombol "Daftar Kunjungan" belum ada karena route fase 6 belum ada. */
    public function test_empty_state_offers_the_doctor_schedule_while_the_booking_route_does_not_exist(): void
    {
        $this->assertFalse(Route::has('patient.appointments.create'));

        $response = $this->actingAs($this->createPatient())->get(route('patient.dashboard'))->assertOk();

        $visits = $this->section($response->getContent(), 'kunjungan-judul');
        $this->assertStringContainsString('Kunjungan Mendatang', $response->getContent());
        $this->assertStringContainsString('Belum ada pendaftaran', $visits);
        $this->assertStringContainsString('Pendaftaran kunjungan Anda akan tampil di sini.', $visits);
        $this->assertStringContainsString('<a class="btn btn-outline-primary" href="'.route('schedules.index').'">Lihat Jadwal Dokter</a>', $visits);
        $this->assertStringNotContainsString('Daftar Kunjungan', $visits);
    }

    /** EMP-04: tombol "Daftar Kunjungan" muncul begitu route fase 6 terdaftar (route palsu di tes ini). */
    public function test_empty_state_offers_the_booking_button_once_the_booking_route_exists(): void
    {
        Route::middleware('web')->get('/_uji/pasien/pendaftaran/baru', fn () => 'x')->name('patient.appointments.create');
        Route::getRoutes()->refreshNameLookups();

        $response = $this->actingAs($this->createPatient())->get(route('patient.dashboard'))->assertOk();

        $visits = $this->section($response->getContent(), 'kunjungan-judul');
        $this->assertStringContainsString('Belum ada pendaftaran', $visits);
        $this->assertStringContainsString('<a class="btn btn-primary" href="'.route('patient.appointments.create').'">Daftar Kunjungan</a>', $visits);
        $this->assertStringNotContainsString('Lihat Jadwal Dokter', $visits);
    }

    /** SEK-15: nama pasien berisi tag HTML tampil sebagai teks. */
    public function test_a_script_tag_in_the_patient_name_is_escaped(): void
    {
        $patient = $this->createPatient([], ['full_name' => '<script>alert(1)</script>']);

        $this->actingAs($patient)->get(route('patient.dashboard'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Halo, &lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    /** F5-R13: akun pasien tanpa profil (hanya mungkin pada data rusak) mendapat 404. */
    public function test_a_patient_account_without_a_profile_gets_a_404(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get(route('patient.dashboard'))
            ->assertNotFound();
    }

    public function test_staff_cannot_open_the_patient_dashboard(): void
    {
        $staff = $this->createStaff('registration-officer');

        $this->actingAs($staff)->get(route('patient.dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($staff)->getJson(route('patient.dashboard'))->assertForbidden();
    }

    public function test_portal_menu_and_logout_form_are_rendered_for_the_patient(): void
    {
        $response = $this->actingAs($this->createPatient())->get(route('patient.dashboard'))->assertOk();

        $response->assertSee('aria-label="Menu portal pasien"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('Akun Saya')
            ->assertDontSee('>Masuk<', false);
    }

    /** ERR-02 (sisi pasien): 403 di URL admin menautkan kembali ke dashboard pasien. */
    public function test_patient_403_page_links_back_to_the_patient_dashboard(): void
    {
        $this->actingAs($this->createPatient())->get('/admin')
            ->assertForbidden()
            ->assertSee('Ke Dasbor')
            ->assertSee('href="'.route('patient.dashboard').'"', false);
    }
}
```

- [ ] **Step 11: Ubah tes lama (tool Write) `tests/Feature/Http/GuestRedirectTest.php` — tulis ulang seluruh berkas**

Dua tes "…while … does not exist" dihapus (cadangan F1-R2); dua tes "…once it exists" diganti pemeriksaan route asli.

```php
<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class GuestRedirectTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private function protectedRoute(): void
    {
        Route::middleware(['web', 'auth'])->get('/_uji/terlindungi', fn () => 'terlindungi')->name('uji.terlindungi');
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_guest_on_the_admin_area_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_guest_on_a_protected_non_admin_route_goes_to_the_patient_login(): void
    {
        $this->protectedRoute();

        $this->get('/_uji/terlindungi')->assertRedirect(route('login'));
    }

    public function test_guest_on_the_patient_area_goes_to_the_patient_login(): void
    {
        $this->get('/pasien')->assertRedirect(route('login'));
    }

    public function test_staff_on_the_guest_only_admin_login_page_goes_to_the_admin_dashboard(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/login')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_patient_on_the_guest_only_admin_login_page_goes_to_the_patient_dashboard(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get('/admin/login')
            ->assertRedirect(route('patient.dashboard'));
    }

    public function test_patient_on_the_guest_only_patient_login_page_goes_to_the_patient_dashboard(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get('/masuk')
            ->assertRedirect(route('patient.dashboard'));
    }

    public function test_staff_on_the_guest_only_patient_login_page_goes_to_the_admin_dashboard(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/masuk')
            ->assertRedirect(route('admin.dashboard'));
    }
}
```

- [ ] **Step 12: Ubah tes lama (tool Edit) `tests/Feature/Auth/AdminAreaAccessTest.php`**

Hapus pendaftaran route palsu `login` (kini route asli). old:
```php
        Route::middleware('web')->get('/_uji/masuk', fn () => 'masuk')->name('login');
        Route::getRoutes()->refreshNameLookups();
        $patient = User::factory()->patient()->create();
```
new:
```php
        $patient = User::factory()->patient()->create();
```

- [ ] **Step 13: Ubah tes lama (tool Edit) `tests/Feature/Access/AdminPageMatrixTest.php`**

Dua penggantian. old:
```php
        if ($actor === 'patient') {
            return Route::has('patient.dashboard') ? route('patient.dashboard') : route('home');
        }
```
new:
```php
        if ($actor === 'patient') {
            return route('patient.dashboard');
        }
```
old:
```php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
```
new:
```php
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
```
(`Route` tidak dipakai lagi di berkas itu: baris 526 satu-satunya pemakaian.)

- [ ] **Step 14: Ubah tes lama (tool Edit) `tests/Feature/View/LayoutsTest.php`**

Lima penggantian terpisah.

(a) Impor dan trait. old:
```php
use App\Models\User;
use App\Services\Settings\SettingsService;
```
new:
```php
use App\Services\Settings\SettingsService;
```
old:
```php
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class LayoutsTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;
```
new:
```php
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class LayoutsTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;
```

(b) Layout publik: `login` kini asli, hanya route fase 6 yang masih palsu. old:
```php
        // Cadangan F1-R2: berlaku selama route fase berikutnya belum ada (dihapus bersama cadangan di fase 5).
        if (! Route::has('login') && ! Route::has('patient.appointments.create')) {
            $this->get('/_uji/publik')
                ->assertDontSee('>Masuk<', false)
                ->assertDontSee('Daftar Kunjungan');
        }

        Route::middleware('web')->get('/_uji/masuk', fn () => 'masuk')->name('login');
        Route::middleware('web')->get('/_uji/daftar-kunjungan', fn () => 'daftar')->name('patient.appointments.create');
```
new:
```php
        // `login` kini route asli (fase 5): "Masuk" selalu tampil bagi tamu; "Daftar Kunjungan" menunggu route fase 6.
        $this->get('/_uji/publik')
            ->assertSee('>Masuk<', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertDontSee('Daftar Kunjungan');

        Route::middleware('web')->get('/_uji/daftar-kunjungan', fn () => 'daftar')->name('patient.appointments.create');
```

(c) Layout pasien: ganti dua tes terakhir (cabang "belum ada" dihapus; tes portal memakai route asli; item fase 6 lewat route palsu). old:
```php
    public function test_patient_layout_omits_portal_navigation_until_patient_routes_exist(): void
    {
        $this->page('/_uji/pasien', '<x-layouts.patient title="Portal Uji"><p>Isi portal</p></x-layouts.patient>');

        // Berlaku selama route portal pasien belum ada (fase 5 menghapus cabang ini).
        if (! Route::has('patient.dashboard') && ! Route::has('logout')) {
            $this->get('/_uji/pasien')
                ->assertOk()
                ->assertDontSee('aria-label="Menu portal pasien"', false)
                ->assertDontSee('Riwayat Pendaftaran');
        }
    }

    public function test_patient_layout_shows_portal_navigation_with_current_item_and_logout_when_routes_exist(): void
    {
        Route::middleware('web')->get('/_uji/pasien-dashboard', fn () => Blade::render('<x-layouts.patient title="Dashboard Uji"><p>Dasbor</p></x-layouts.patient>'))->name('patient.dashboard');
        Route::middleware('web')->get('/_uji/pasien-riwayat', fn () => 'riwayat')->name('patient.appointments.index');
        Route::middleware('web')->get('/_uji/pasien-baru', fn () => 'baru')->name('patient.appointments.create');
        Route::middleware('web')->post('/_uji/keluar', fn () => 'keluar')->name('logout');
        Route::getRoutes()->refreshNameLookups();

        $this->get('/_uji/pasien-dashboard')
            ->assertOk()
            ->assertSee('aria-label="Menu portal pasien"', false)
            ->assertSee('Dashboard')
            ->assertSee('Riwayat Pendaftaran')
            ->assertSee('Daftar Kunjungan')
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('action="'.route('logout').'"', false);

        $this->actingAs(User::factory()->patient()->create())
            ->get('/_uji/pasien-dashboard')
            ->assertSee('action="'.route('logout').'"', false);
    }
```
new:
```php
    public function test_patient_layout_shows_no_logout_form_to_guests(): void
    {
        $this->page('/_uji/pasien', '<x-layouts.patient title="Portal Uji"><p>Isi portal</p></x-layouts.patient>');

        $this->get('/_uji/pasien')
            ->assertOk()
            ->assertSee('aria-label="Menu portal pasien"', false)
            ->assertDontSee('action="'.route('logout').'"', false);
    }

    public function test_patient_layout_shows_portal_navigation_with_current_item_and_logout_on_the_real_dashboard(): void
    {
        $this->actingAs($this->createPatient())
            ->get(route('patient.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Menu portal pasien"', false)
            ->assertSee('Dashboard')
            ->assertSee('aria-current="page"', false)
            ->assertSee('action="'.route('logout').'"', false)
            ->assertDontSee('Riwayat Pendaftaran')
            ->assertDontSee('Daftar Kunjungan');
    }

    public function test_patient_layout_adds_the_phase_6_menu_items_once_their_routes_exist(): void
    {
        Route::middleware('web')->get('/_uji/pasien-riwayat', fn () => 'riwayat')->name('patient.appointments.index');
        Route::middleware('web')->get('/_uji/pasien-baru', fn () => 'baru')->name('patient.appointments.create');
        Route::getRoutes()->refreshNameLookups();

        $this->actingAs($this->createPatient())
            ->get(route('patient.dashboard'))
            ->assertOk()
            ->assertSee('Riwayat Pendaftaran')
            ->assertSee('Daftar Kunjungan');
    }
```
(`Blade` masih dipakai oleh pembantu `page()`.)

- [ ] **Step 15: Jalankan tes, pastikan GAGAL (RED nyata)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="PatientLoginTest|PatientDashboardTest|GuestRedirectTest|AdminAreaAccessTest|LayoutsTest|AdminPageMatrixTest"
```
Expected: FAIL. Sebagian besar kegagalan berupa `Symfony\Component\Routing\Exception\RouteNotFoundException: Route [login] not defined.` (atau `Route [patient.dashboard] not defined.`) pada `PatientLoginTest`, `PatientDashboardTest`, `GuestRedirectTest` (kecuali `test_guest_on_the_admin_area_...` dan `test_staff_on_the_guest_only_admin_login_page_...`), `AdminAreaAccessTest::test_patient_deactivated_during_a_running_session_is_logged_out_and_sent_to_login` (setelah route palsu dihapus: `Route [login] not defined`), tes layout pasien (`Route [patient.dashboard] not defined`) dan `AdminPageMatrixTest` kolom pasien 'D' (`route('patient.dashboard')` belum terdaftar). Catat jumlah gagal di laporan.

- [ ] **Step 16: Buat route pasien (tool Write) `routes/patient.php`**

```php
<?php

use App\Http\Controllers\Patient\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route portal pasien
|--------------------------------------------------------------------------
|
| Dimuat dari bootstrap/app.php dengan awalan URL /pasien, awalan nama `patient.`, dan middleware
| `web`, `auth`, `account:patient`, `active` (G-PASIEN, 11 §1, §3.1). Tidak ada route wildcard di bawah /pasien:
| /pasien/pendaftaran/* dan /pasien/notifikasi/* baru ada di fase 6 (InternalLinkTest dan BannerButtonTest
| mengunci bahwa /pasien/pendaftaran/baru belum ada).
|
*/

// Dashboard pasien (#27): sapaan, nomor pasien, kunjungan mendatang (kosong sampai fase 6).
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
```

- [ ] **Step 17: Muat grup pasien dan hapus cadangan F1-R2 (tool Edit) `bootstrap/app.php`**

(a) old:
```php
            // Grup pasien (routes/patient.php) ditambahkan pada fase 5.

```
new:
```php
            // Grup pasien G-PASIEN (11 §1, §3.1): /pasien, nama patient.*.
            Route::middleware(['web', 'auth', 'account:patient', 'active'])
                ->prefix('pasien')
                ->name('patient.')
                ->group(base_path('routes/patient.php'));

```

(b) old:
```php
        // Tamu yang membuka halaman ber-`auth`: /admin* ⇒ login admin; selain itu ⇒ login pasien.
        // Cadangan F1-R2: `login` belum ada sampai fase 5, jadi arahkan ke beranda.
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('admin', 'admin/*')) {
                return route('admin.login');
            }

            return Route::has('login') ? route('login') : route('home');
        });

        // Pengguna login yang membuka halaman ber-`guest`: arahkan sesuai tipe akun (cadangan F1-R2).
        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();

            if ($user instanceof User && $user->isStaff()) {
                return route('admin.dashboard');
            }

            return Route::has('patient.dashboard') ? route('patient.dashboard') : route('home');
        });
```
new:
```php
        // Tamu yang membuka halaman ber-`auth`: /admin* ⇒ login admin; selain itu ⇒ login pasien.
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('admin', 'admin/*')) {
                return route('admin.login');
            }

            return route('login');
        });

        // Pengguna login yang membuka halaman ber-`guest`: arahkan sesuai tipe akun.
        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();

            if ($user instanceof User && $user->isStaff()) {
                return route('admin.dashboard');
            }

            return route('patient.dashboard');
        });
```
(`Route` facade masih dipakai di `then:`; semua `use` lain tetap terpakai.)

- [ ] **Step 18: Hapus cadangan di middleware (tool Edit) `app/Http/Middleware/EnsureAccountIsActive.php`**

old:
```php
        // Cadangan F1-R2: `login` belum ada sampai fase 5.
        $loginRoute = $isStaff ? 'admin.login' : (Route::has('login') ? 'login' : 'home');
```
new:
```php
        $loginRoute = $isStaff ? 'admin.login' : 'login';
```
old:
```php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
```
new:
```php
use Illuminate\Support\Facades\Auth;
```

- [ ] **Step 19: Tambah route auth (tool Edit) `routes/web.php`**

old:
```php
use App\Http\Controllers\Public\ArticleController;
```
new:
```php
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Public\ArticleController;
```
old:
```php
Route::get('/kebijakan-privasi', [PageController::class, 'privacy'])->name('privacy');
```
new:
```php
Route::get('/kebijakan-privasi', [PageController::class, 'privacy'])->name('privacy');

// Auth pasien (#23–#26, 11 §2.1)
Route::middleware('guest')->group(function (): void {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});
// Logout di luar grup `guest` supaya akun yang sedang login bisa keluar (F5-R8).
Route::post('/keluar', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
```

- [ ] **Step 20: Buat kelas pengalihan intended (tool Write) `app/Support/PatientIntendedUrl.php`**

```php
<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Tujuan setelah login atau registrasi pasien (F5-R7). URL tujuan semula (`url.intended`, diisi middleware `auth`
 * saat tamu membuka halaman terlindungi lewat GET) dipakai bila berupa string dan path-nya bukan area admin;
 * selain itu dashboard pasien. Kunci sesi selalu dihapus (`pull`).
 */
final class PatientIntendedUrl
{
    public static function pull(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && self::isAllowed($intended)) {
            return $intended;
        }

        return route('patient.dashboard');
    }

    private static function isAllowed(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return false;
        }

        return $path !== '/admin' && ! str_starts_with($path, '/admin/');
    }
}
```

- [ ] **Step 21: Buat Form Request login (tool Write) `app/Http/Requests/Auth/LoginRequest.php`**

```php
<?php

namespace App\Http\Requests\Auth;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * V-02 (F5-R6): login pasien. Autentikasi hanya untuk akun pasien aktif; semua kegagalan (email tak dikenal,
 * password salah, akun staf, akun nonaktif) memakai pesan generik yang sama (D-22, AKS-08, SEK-16).
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Email dinormalkan (trim + huruf kecil) sebelum validasi dan autentikasi; nilai bukan string dibiarkan. */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'password.required' => 'Password wajib diisi.',
            'password.string' => 'Password tidak valid.',
            'password.max' => 'Password maksimal 255 karakter.',
            'remember.boolean' => 'Pilihan ingat saya tidak valid.',
        ];
    }

    public function authenticate(): User
    {
        $credentials = [
            'email' => (string) $this->validated('email'),
            'password' => (string) $this->validated('password'),
            'account_type' => AccountType::Patient->value,
            'is_active' => true,
        ];

        if (Auth::attempt($credentials, $this->boolean('remember'))) {
            /** @var User $user */
            $user = Auth::user();

            return $user;
        }

        throw ValidationException::withMessages(['email' => __('auth.failed')]);
    }
}
```

- [ ] **Step 22: Buat controller login (tool Write) `app/Http/Controllers/Auth/LoginController.php`**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\PatientIntendedUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();

        $request->session()->regenerate();

        // Penghitung limiter `login` (email + IP) dibersihkan setelah login sukses. Kunci sama dengan ThrottleRequests:
        // md5(nama limiter . kunci Limit); email sudah ternormalisasi oleh LoginRequest (lihat AppServiceProvider::emailKey()).
        RateLimiter::clear(md5('login'.$request->validated('email').'|'.$request->ip()));

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->to(PatientIntendedUrl::pull($request));
    }

    /** Logout bersama (F5-R8): `auth` saja; staf yang memakainya ikut keluar tanpa audit. */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Anda telah keluar.');
    }
}
```

- [ ] **Step 23: Buat controller dashboard (tool Write) `app/Http/Controllers/Patient/DashboardController.php`**

```php
<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Dashboard pasien (F5-R22). Akun pasien tanpa profil (data rusak) menjawab 404 (F5-R13). */
    public function index(Request $request): View
    {
        $user = $request->user();
        $profile = $user->loadMissing('patientProfile')->patientProfile;

        abort_if($profile === null, 404);

        return view('patient.dashboard', ['user' => $user, 'profile' => $profile]);
    }
}
```

- [ ] **Step 24: Buat view login (tool Write) `resources/views/auth/login.blade.php`**

```blade
<x-layouts.auth title="Masuk" heading="Masuk ke Akun Pasien" subtitle="Masuk untuk mengelola profil dan pendaftaran kunjungan Anda.">
    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf

        <x-form.input name="email" label="Email" type="email" required autocomplete="username" inputmode="email" :autofocus="! $errors->any()" />
        <x-form.input name="password" label="Password" type="password" required autocomplete="current-password" />
        <x-form.checkbox name="remember" label="Ingat saya" />

        <x-loading-button class="w-100 mt-2" loading-text="Memproses…">Masuk</x-loading-button>
    </form>
</x-layouts.auth>
```

- [ ] **Step 25: Buat view dashboard (tool Write) `resources/views/patient/dashboard.blade.php`**

```blade
<x-layouts.patient title="Dashboard Pasien">
    <h1 class="h2 mb-1">Halo, {{ $profile->full_name }}</h1>
    <p class="text-body-secondary mb-4">No. pasien: {{ $profile->patient_number ?? '-' }}</p>

    <div class="row g-4">
        <section class="col-lg-7" aria-labelledby="kunjungan-judul">
            <h2 class="h4 mb-3" id="kunjungan-judul">Kunjungan Mendatang</h2>
            <x-empty-state icon="bi-calendar-x" title="Belum ada pendaftaran" message="Pendaftaran kunjungan Anda akan tampil di sini.">
                @if (Route::has('patient.appointments.create'))
                    <a class="btn btn-primary" href="{{ route('patient.appointments.create') }}">Daftar Kunjungan</a>
                @else
                    <a class="btn btn-outline-primary" href="{{ route('schedules.index') }}">Lihat Jadwal Dokter</a>
                @endif
            </x-empty-state>
        </section>

        <section class="col-lg-5" aria-labelledby="profil-judul">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5 card-title mb-3" id="profil-judul">Profil Anda</h2>
                    <dl class="mb-0">
                        <dt class="small text-body-secondary">Email</dt>
                        <dd>{{ $profile->email ?? '-' }}</dd>
                        <dt class="small text-body-secondary">Nomor telepon</dt>
                        <dd class="mb-0">{{ $profile->phone ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
        </section>
    </div>
</x-layouts.patient>
```

- [ ] **Step 26: Perbaiki komponen checkbox (tool Edit) `resources/views/components/form/checkbox.blade.php`**

old:
```php
        $isChecked = $hasOldInput
            ? (string) old($key, $withHidden ? '0' : null) === (string) $value
            : (bool) $checked;
```
new:
```php
        // Nilai lama non-skalar (mis. `remember[]=1`) dianggap tidak tercentang; konversi larik ke string memicu galat.
        $oldValue = old($key, $withHidden ? '0' : null);
        $isChecked = $hasOldInput
            ? is_scalar($oldValue) && (string) $oldValue === (string) $value
            : (bool) $checked;
```

- [ ] **Step 27: Jalankan tes terfilter, pastikan LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="PatientLoginTest|PatientDashboardTest|GuestRedirectTest|AdminAreaAccessTest|LayoutsTest|AdminPageMatrixTest|AdminLoginTest|AuthPolishTest|FormCheckboxArrayTest|FormComponentsTest|FormChoiceComponentsTest"
```
Expected: PASS semuanya (±45 tes baru; `AdminPageMatrixTest` dan `LayoutsTest` hijau). Bila `test_array_remember_value_does_not_break_the_form_when_it_is_rendered_again` masih gagal dengan "Array to string conversion", periksa Step 26. Bila sebuah tes pada `PatientLoginTest`/`PatientDashboardTest` gagal karena override `createPatient()` tidak berlaku (email/`full_name` tidak sesuai), lihat Catatan penulis butir 1.

- [ ] **Step 28: Konfirmasi route dan regresi terarah**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
(& $PHP artisan route:list --except-vendor --json | ConvertFrom-Json).Count
& $PHP artisan route:list --except-vendor --name=login
& $PHP artisan route:list --except-vendor --path=pasien
& $PHP artisan test --filter="Phase4bRoutePermissionTest|AdminRouteMiddlewareTest|InternalLinkTest|BannerButtonTest|SecurityHeadersTest|ExceptionRenderingTest|ErrorPagesTest|NavigationMenuTest|PublicContentPagesTest"
```
Expected: jumlah route `132` (128 + 4); `route:list` menampilkan `login` (GET `masuk`), `login.store` (POST `masuk`), `logout` (POST `keluar`) dan `patient.dashboard` (GET `pasien`); tes yang dikunci hijau (106 route admin tetap; `/pasien/pendaftaran/baru` tetap tidak ada).

Selanjutnya regresi lebih luas di latar belakang (header publik kini menampilkan "Masuk"/"Akun Saya"/"Keluar", sehingga tes halaman publik ikut diperiksa). Jalankan dengan `run_in_background` atau tunggu selesai (±15 menit):

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$out = "$env:TEMP\fase5-t3-regresi.txt"
Start-Process -FilePath $PHP -ArgumentList 'artisan','test','--filter="Tests.Feature.(Access|Auth|Errors|Http|Security|Support|View|Public|Content|Patient|Admin.AdminLayoutTest)"' -WorkingDirectory $APP -RedirectStandardOutput $out -RedirectStandardError "$out.err" -NoNewWindow -Wait
Get-Content $out -Tail 25
```
Expected: ringkasan `Tests: ... passed` tanpa kegagalan. Bila ada tes lama yang gagal karena teks "Masuk"/"Akun Saya"/"Keluar" baru di header publik, perbaiki tes itu (bukan view) dan catat di laporan.

- [ ] **Step 29: Pint, build, dan commit siklus 2**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git status --short
git add routes/patient.php routes/web.php bootstrap/app.php app/Http/Middleware/EnsureAccountIsActive.php app/Support/PatientIntendedUrl.php app/Http/Requests/Auth/LoginRequest.php app/Http/Controllers/Auth/LoginController.php app/Http/Controllers/Patient/DashboardController.php resources/views/auth/login.blade.php resources/views/patient/dashboard.blade.php resources/views/components/form/checkbox.blade.php tests/Feature/Auth/PatientLoginTest.php tests/Feature/Patient/PatientDashboardTest.php tests/Feature/Http/GuestRedirectTest.php tests/Feature/Auth/AdminAreaAccessTest.php tests/Feature/View/LayoutsTest.php tests/Feature/Access/AdminPageMatrixTest.php
git commit -m @'
feat(fase-5): routing pasien, login dan logout, dashboard pasien; hapus cadangan F1-R2

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: pint `PASS`; `git status --short` hanya memuat berkas Task 3 (tanpa `public/build`, yang di-ignore); satu commit. Perubahan `FrontendManifestTest`, `auth.blade.php`, dan `_shared.scss` sudah masuk di commit siklus 1.

---

#### Catatan penulis (konteks untuk implementer dan reviewer)

1. **Semantik override `createPatient()` (Task 1).** Tes Task 3 dan 4 mengandalkan bahwa `createPatient($userAttributes, $profileAttributes)` memakai nilai argumen sebagai penimpa (mis. `['email' => 'siti@contoh.test']`, `['is_active' => false]`, profil `['full_name' => '<script>alert(1)</script>']`, `['phone' => '081355550001']`). Kerangka D menulis `['password' => ...] + $userAttributes`, yang dalam PHP berarti kunci KIRI menang (override tidak berlaku). Task 1 harus mengimplementasikan `$userAttributes + [default...]` (argumen di kiri). Tes juga mengandalkan `patient_number` hasil `createPatient()` = `PatientNumberGenerator::format($profile->id)` (tidak bisa ditimpa; tes membaca nilainya dari profil).
2. **Perbaikan di luar daftar kerangka: `components/form/checkbox.blade.php`.** Temuan nyata: `remember[]=1` pada `/masuk` membuat render ulang form melempar "Array to string conversion" (500), melanggar aturan proyek "input larik tidak boleh 500". Perbaikan kecil (`is_scalar`) dan tes di `PatientLoginTest`. Tidak mengubah perilaku komponen untuk nilai skalar; `FormCheckboxArrayTest`/`FormComponentsTest` dijalankan di Step 27. Dampak ke tugas lain: Task 4 (form registrasi memakai markup checkbox manual yang sudah aman karena `old('consent') === '1'`), Task 6 (checkbox `remove_bpjs` memakai komponen ini dan ikut terlindungi).
3. **Atribut `aria-labelledby` pada bagian dashboard** (`kunjungan-judul`, `profil-judul`) ditambahkan agar tes dapat mengisolasi isi bagian; Task 6 yang menambahkan tautan "Ubah Profil dan Akun" ke bagian "Profil Anda" mempertahankan atribut itu. Tes T3 sengaja TIDAK menegaskan ketiadaan tautan profil (agar Task 6 tidak harus mengubah tes ini).
4. **Halaman `wide` dan `AuthPolishTest`.** Kartu lebar memuat substring `auth-card` dua kali (`auth-card auth-card-wide`). `AuthPolishTest::test_auth_pages_have_a_single_card_surface` hanya memeriksa halaman admin sehingga tidak terpengaruh; tes halaman registrasi (Task 4) menghitung `class="auth-card`.
5. **AKS-07 asli ditunda.** Versi fase 5 memakai `/pasien?contoh=utuh` (route `patient.profile.edit` baru ada di Task 6, route wizard fase 6). Tes AKS-07 asli (sesi bookable) menjadi carry fase 6 (T8 mencatat di D-entry dan 15). Carry fase 6 yang sama: tes `PatientDashboardTest::test_empty_state_offers_the_doctor_schedule_while_the_booking_route_does_not_exist` (menegaskan `assertFalse(Route::has('patient.appointments.create'))`) diubah di fase 6 saat `patient.appointments.create` ada.
6. **Teks "Akun Anda tidak aktif" (V-02) tidak dipakai di form login** (D-22/AKS-08; kerangka F5-R6). Hanya flash middleware `active` (sesi berjalan) yang memakai teks "Akun Anda dinonaktifkan. Silakan hubungi administrator rumah sakit.".
7. **Header publik berubah di semua halaman publik** karena route `login`/`logout`/`patient.dashboard` kini ada (guard `Route::has` dari fase 1). Step 28 menjalankan regresi terarah untuk menangkap tes publik yang mengunci isi header; hasilnya dicatat implementer.

---

### Task 4: Registrasi pasien (#23): `RegisterRequest`, `RegisterController`, view `auth/register`, route `register`

**Files:**
- Create: `app/Http/Requests/Auth/RegisterRequest.php`
- Create: `app/Http/Controllers/Auth/RegisterController.php`
- Create: `resources/views/auth/register.blade.php`
- Modify: `resources/views/auth/login.blade.php` (footer "Belum punya akun? Daftar")
- Modify: `routes/web.php` (impor `RegisterController`; route `register` dan `register.store` di grup `guest` blok "Auth pasien")
- Test (baru): `tests/Feature/Auth/RegisterRequestTest.php`, `tests/Feature/Auth/PatientRegistrationTest.php`
- Tidak diubah: `PatientRegistrationService` dan `PatientWelcomeNotification` (Task 2), `LoginController`, `PatientIntendedUrl` dan layout auth (Task 3), limiter `register` (`Limit::perHour(5)->by(IP)` sudah ada di `AppServiceProvider`), handler 419 dan 429 di `bootstrap/app.php`, `layouts/*`, `x-form.*`, `registration-guide.blade.php` (tombol "Daftar Akun" sudah dijaga `Route::has('register')` dan muncul otomatis), `PublicContentPagesTest` (tes registration guide memakai `Route::has` dinamis, tetap lulus), `lang/*`, migration, enum, izin.

**Interfaces:**
- Consumes:
  - Task 1: `Tests\Support\InteractsWithPatients` (`createPatient()`, `registrationPayload(array $overrides = []): array`, `patientPassword()`), `PatientProfile::factory()`.
  - Task 2: `App\Services\Patients\PatientRegistrationService::register(array $validated): User` (satu transaksi `users` + `patient_profiles` + nomor pasien + audit `patient.registered`; notifikasi selamat datang SESUDAH commit dalam `try/catch (Throwable)`); `App\Notifications\PatientWelcomeNotification`.
  - Task 3: route `login`, `patient.dashboard`; `App\Support\PatientIntendedUrl::pull(Request): string`; layout `x-layouts.auth` dengan prop `wide`; `.auth-card-wide`; komponen `x-form.*` yang aman terhadap nilai lama non-skalar.
  - Fase 1-4b: `App\Rules\PasswordRules::default()`, `App\Rules\PersonName`, `App\Rules\IndonesianPhone` (`normalize()`, `MESSAGE`), `App\Enums\Gender`, `Tests\Support\InteractsWithFormRequests::validationErrors()`/`makeFormRequest()`, limiter `register`, route `privacy`.
- Produces:
  - `App\Http\Requests\Auth\RegisterRequest` (`authorize()` = true, `prepareForValidation()`, `rules()` V-01, `messages()`, `attributes()`).
  - `App\Http\Controllers\Auth\RegisterController` (`create(): View`, `store(RegisterRequest, PatientRegistrationService): RedirectResponse`).
  - Route (2 dari 13): `register` (GET `/daftar`, `guest`), `register.store` (POST `/daftar`, `guest`, `throttle:register`). Total route setelah tugas: 134.
  - View `auth.register` (halaman #23, kartu `wide`) dan footer "Belum punya akun? Daftar" pada `auth.login`.

**Test IDs:** F-AUTH-01, VAL-02 (pesan validasi registrasi, tanpa baris parsial), SEK-01 (password ber-hash `$2y$`, tidak pernah muncul di respons, flash, old input, atau audit), SEK-02 (regenerasi sesi setelah registrasi), SEK-05 (registrasi ke-6 per jam per IP), SEK-06/ERR-03 (419 pada registrasi), NTF-08 (di level HTTP: notifikasi terkirim setelah commit, tidak terkirim bila gagal, kegagalan kirim tidak membatalkan registrasi), WLK-06 (tidak ada pencocokan otomatis dengan profil walk-in), K-07 (satu akun satu profil), K-22 (tanpa verifikasi wajib), AKS-01/AKS-02 (halaman #23: tamu 200, pasien dan staf diarahkan ke dashboard masing-masing), Review Focus G.2 (larik dan nilai liar: `full_name[]`, `email[]`, `password[]`, `gender=X`, `birth_date=2099-01-01`/`31-12-1990`, `consent[]=1`), G.4 (intended, redirect akun yang sudah login), G.5 (atomisitas dan email ganda beda huruf besar, balapan unik, kegagalan email), F5-R9, F5-R23 (pemakai `wide`), F5-R26 (teks E.2).

**Catatan teknis (diverifikasi dari source):**
1. **Aturan V-01 dan urutannya.** `full_name`: `bail required string min:3 max:100 PersonName`; `email`: `bail required string max:255 email:rfc unique:users,email` (tanpa `dns`; `max` mendahului `email:rfc` supaya email 257 karakter menjawab "Email maksimal 255 karakter." dan bukan "Format email tidak valid.", pola `StoreContactMessageRequest`; `string` mendahului aturan lain agar `email[]=x` menjawab galat biasa); `phone`: `bail required IndonesianPhone` (objek aturan menolak non-string dengan pesan `IndonesianPhone::MESSAGE`); `birth_date`: `bail required date_format:Y-m-d before:today after_or_equal:-120 years`; `gender`: `bail required Rule::enum(Gender::class)` (pesan kunci `gender.enum`); `address`: `bail required string min:10 max:500`; `password`: `PasswordRules::default()`; `password_confirmation`: `required`; `consent`: `accepted`. Pesan password memakai kunci `password.min`, `password.password.letters`, `password.password.numbers` (kunci validator bersarang aturan `Password`; pola `Admin\UpdatePasswordRequest`).
2. **`prepareForValidation` (12 §1 butir 4).** Hanya bila nilainya `string`: `full_name`, `birth_date`, `gender`, `address` di-`trim`; `email` di-`trim` dan `Str::lower`; `phone` di-`trim` lalu `IndonesianPhone::normalize()` (membuang spasi, `-`, `.`, `(`, `)`; tanda `+` dipertahankan). Password TIDAK pernah diubah. Nilai bukan string dibiarkan agar divalidasi sebagai galat (tidak ada `TypeError`). Karena itu `0812-3456-7890` tersimpan `081234567890` dan `+62 812 3456 7890` tersimpan `+6281234567890`.
3. **Tanggal lahir.** `before:today` menolak hari ini dan besok (Carbon `today` = tengah malam). `after_or_equal:-120 years` dibandingkan dengan "sekarang minus 120 tahun" lengkap dengan jam (`strtotime`), sehingga tepat pada batas hari ke-120 tahun bisa ditolak sesuai jam hari ini; tes hanya memakai nilai yang tegas (121 tahun ditolak, 119 tahun diterima). Provider tes statis tidak memakai `now()` (provider dievaluasi sebelum aplikasi dibuat; zona waktu PHP belum diatur ke `Asia/Jakarta`); kasus bertanggal dihitung di dalam metode tes.
4. **Helper `InteractsWithFormRequests`.** `validationErrors()` memakai pengguna bawaan staf bila argumen `null`; registrasi adalah form tamu, jadi tes melewatkan akun pasien yang belum disimpan (`User::factory()->patient()->make()`) dan tidak membuat staf. `authorize()` selalu `true`.
5. **Layanan tidak dapat di-mock.** `PatientRegistrationService`, `AuditLogger` dan notifikasi bersifat `final` (kerangka D, `AuditLogger` fase 1); Mockery tidak dapat meniru kelas `final`. Tes galat sistem dan balapan unik memicu galat lewat listener model (`PatientProfile::creating(...)` melempar `RuntimeException`; `User::creating(...)` melempar `UniqueConstraintViolationException`), yang sekaligus membuktikan rollback atomik. Listener hidup per aplikasi tes (dibuang bersama aplikasi) sehingga tidak bocor antar tes. Kegagalan email selamat datang disimulasikan lewat listener `NotificationSending` yang melempar.
6. **Controller (F5-R9).** `register($request->validated())` (sembilan kunci hasil validasi; service mengambil yang diperlukan). `UniqueConstraintViolationException` (balapan sesudah validasi `unique`) → `back()->withInput(...)->withErrors(['email' => 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.'])`; `Throwable` lain → `report($e)` + `back()->withInput(...)->with('error', 'Registrasi belum berhasil karena gangguan sistem. Silakan coba lagi.')`. **`back()->withInput()` tanpa argumen TIDAK menyaring `dontFlash`** (penyaringan hanya terjadi pada `ValidationException` lewat handler), sehingga controller menyaring sendiri: `$request->except(['password', 'password_confirmation'])` (SEK-01). Sukses: `Auth::login($user)` → `session()->regenerate()` → `forceFill(['last_login_at' => now()])->save()` → `redirect()->to(PatientIntendedUrl::pull($request))` dengan flash `success` "Registrasi berhasil. Selamat datang, {nama}.". Tanpa "ingat saya". Notifikasi selamat datang dikirim service SESUDAH commit.
7. **Throttle (SEK-05, D-23).** `throttle:register` = `Limit::perHour(5)->by(IP)`; middleware berjalan sesudah `guest` dan SEBELUM validasi, sehingga kiriman yang gagal validasi ikut dihitung. Pada POST non-JSON kiriman ke-6 menjadi redirect 302 ke `SafeRedirect::back()` dengan flash `error` "Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik." dan header `Retry-After`, bukan status 429 (pola `bootstrap/app.php`, D-23; teks SEK-05 "ke-6 → 429" dibaca sesuai D-23). Tes memakai lima kiriman tak valid lalu satu kiriman valid, agar akun yang berhasil terdaftar tidak membuat klien tes berstatus login (middleware `guest` akan mengalihkan).
8. **View (E.2).** `<x-layouts.auth title="Daftar Akun Pasien" subtitle="…" :wide="true">`; `max` tanggal lahir `now()->subDay()->toDateString()` lewat atribut terikat `:max` (komponen `x-form.input` meneruskan atribut tambahan ke `<input>`); persetujuan memakai markup manual (pola `public/contact.blade.php` baris 100-110) dengan perbandingan ketat `old('consent') === '1'` (aman untuk `consent[]=1`), tautan `route('privacy')` `target="_blank" rel="noopener"` dan teks tersembunyi "(terbuka di tab baru)". Kartu `wide` memuat substring `auth-card` dua kali, sehingga tes menghitung `class="auth-card` (satu kali), bukan `auth-card`. Tidak ada field NIK/BPJS (BR-100, BR-162). Tidak ada `autofocus` pada form registrasi (kerangka E.2 tidak menyebutnya); ringkasan galat `x-form.errors-summary` dirender layout auth.
9. **Footer login.** `<x-slot:footer>` dipasang di `auth/login.blade.php` (E.1). Task 5 menambahkan tautan "Lupa password?" tepat di bawah field password pada berkas yang sama; kedua penggantian memakai jangkar berbeda.
10. **Dampak ke tes lain.** Dengan route `register` ada, tombol "Daftar Akun" di `registration-guide` tampil (tes `PublicContentPagesTest` memakai `Route::has` dan `fakeRoute()` hanya bila route belum ada, jadi tetap hijau). Tes ini tidak menyentuh `RawOutputScanTest` (view baru tanpa `{!!`), `InternalLinkTest` (`/pasien/pendaftaran/baru` tetap tidak ada), maupun jumlah route admin (106).
11. **DB pengembangan.** Implementer tidak menjalankan `migrate`/`db:seed` pada `unpam_hospital`.
12. Jumlah tes baru ±95: `RegisterRequestTest` ±60 (provider ±50) dan `PatientRegistrationTest` ±30.

---

#### Siklus A: `RegisterRequest` (V-01)

- [ ] **Step 1: Verifikasi prasyarat (Task 1 sampai 3 sudah di-commit)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
git log --oneline -6
Test-Path tests\Support\InteractsWithPatients.php
Test-Path app\Services\Patients\PatientRegistrationService.php
Test-Path app\Notifications\PatientWelcomeNotification.php
Test-Path app\Support\PatientIntendedUrl.php
Test-Path app\Http\Controllers\Auth\LoginController.php
(& $PHP artisan route:list --except-vendor --json | ConvertFrom-Json).Count
```
Expected: `fase-5-akun-pasien`; `git status` kosong atau hanya `?? .superpowers/`; log memuat commit Task 1 sampai 3; lima `True`; `132`. Apache dan MySQL harus sudah di-Start.

- [ ] **Step 2: Tulis tes (tool Write) `tests/Feature/Auth/RegisterRequestTest.php`**

```php
<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Rules\IndonesianPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithFormRequests;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * V-01 (12 §4): validasi registrasi pasien, tanpa HTTP (VAL-02, Review Focus G.2 dan G.5). Setiap aturan diuji dengan
 * pesan persis. Form tamu: pengguna yang dilewatkan ke helper hanyalah akun pasien yang belum disimpan.
 */
class RegisterRequestTest extends TestCase
{
    use InteractsWithFormRequests;
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const CONSENT_MESSAGE = 'Anda harus menyetujui Kebijakan Privasi untuk melanjutkan.';

    private const EMAIL_TAKEN = 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.';

    private function guest(): User
    {
        return User::factory()->patient()->make();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, list<string>>
     */
    private function errors(array $overrides = []): array
    {
        return $this->validationErrors(RegisterRequest::class, $this->registrationPayload($overrides), $this->guest());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validated(array $overrides = []): array
    {
        $request = $this->makeFormRequest(RegisterRequest::class, $this->registrationPayload($overrides), $this->guest());
        $request->validateResolved();

        return $request->validated();
    }

    // --- data valid --------------------------------------------------------------------------

    public function test_the_helper_payload_is_valid_and_the_form_is_open_to_guests(): void
    {
        $this->assertSame([], $this->errors());
        $this->assertTrue($this->makeFormRequest(RegisterRequest::class, $this->registrationPayload(), $this->guest())->authorize());
    }

    public function test_only_the_nine_registration_fields_are_validated_and_extra_fields_are_dropped(): void
    {
        $validated = $this->validated([
            'user_id' => 99,
            'patient_number' => 'P000001',
            'registration_channel' => 'loket',
            'is_active' => '0',
            'account_type' => 'staff',
            'nik' => '3173000000000001',
            'bpjs_number' => '0009900000011',
        ]);

        $this->assertEqualsCanonicalizing(
            ['full_name', 'email', 'phone', 'birth_date', 'gender', 'address', 'password', 'password_confirmation', 'consent'],
            array_keys($validated),
        );
    }

    // --- normalisasi (prepareForValidation) --------------------------------------------------

    public function test_text_fields_are_trimmed_email_is_lowercased_and_phone_is_normalised(): void
    {
        $validated = $this->validated([
            'full_name' => '  Siti Rahma  ',
            'email' => '  SITI@Contoh.TEST ',
            'phone' => ' 0812-3456-7890 ',
            'birth_date' => ' 1990-03-12 ',
            'gender' => ' P ',
            'address' => '  Jl. Merpati No. 5, Pamulang  ',
        ]);

        $this->assertSame('Siti Rahma', $validated['full_name']);
        $this->assertSame('siti@contoh.test', $validated['email']);
        $this->assertSame('081234567890', $validated['phone']);
        $this->assertSame('1990-03-12', $validated['birth_date']);
        $this->assertSame('P', $validated['gender']);
        $this->assertSame('Jl. Merpati No. 5, Pamulang', $validated['address']);
    }

    public function test_the_password_is_never_trimmed(): void
    {
        $validated = $this->validated(['password' => ' Rahasia123 ', 'password_confirmation' => ' Rahasia123 ']);

        $this->assertSame(' Rahasia123 ', $validated['password']);
    }

    #[DataProvider('acceptedPhones')]
    public function test_indonesian_mobile_numbers_are_accepted_and_stored_normalised(string $input, string $stored): void
    {
        $this->assertSame([], $this->errors(['phone' => $input]));
        $this->assertSame($stored, $this->validated(['phone' => $input])['phone']);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function acceptedPhones(): array
    {
        return [
            'dengan tanda hubung' => ['0812-3456-7890', '081234567890'],
            'kode negara dengan spasi' => ['+62 812 3456 7890', '+6281234567890'],
            'kurung dan titik' => ['(0812) 3456.7890', '081234567890'],
            'tanpa plus' => ['6281234567890', '6281234567890'],
            'tanpa pemisah' => ['081234567890', '081234567890'],
        ];
    }

    // --- galat per aturan, pesan persis ------------------------------------------------------

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidCases')]
    public function test_each_rule_reports_its_exact_indonesian_message(array $overrides, string $field, ?string $message): void
    {
        $errors = $this->errors($overrides);

        $this->assertArrayHasKey($field, $errors, "Galat untuk {$field} tidak muncul.");

        if ($message !== null) {
            $this->assertContains($message, $errors[$field]);
        }
    }

    /**
     * Pesan null = hanya memastikan ada galat pada field (nilai larik: isi pesan bukan inti pengujian).
     *
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: ?string}>
     */
    public static function invalidCases(): array
    {
        $personName = 'Nama lengkap hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.';

        return [
            // nama lengkap
            'nama kosong' => [['full_name' => ''], 'full_name', 'Nama lengkap wajib diisi.'],
            'nama terlalu pendek' => [['full_name' => 'Ab'], 'full_name', 'Nama lengkap minimal 3 karakter.'],
            'nama terlalu panjang' => [['full_name' => str_repeat('a', 101)], 'full_name', 'Nama lengkap maksimal 100 karakter.'],
            'nama berisi angka' => [['full_name' => 'Siti 123'], 'full_name', $personName],
            'nama berisi tag' => [['full_name' => '<b>Siti</b>'], 'full_name', $personName],
            'nama larik' => [['full_name' => ['Siti']], 'full_name', 'Nama lengkap tidak valid.'],
            // email
            'email kosong' => [['email' => ''], 'email', 'Email wajib diisi.'],
            'email bukan email' => [['email' => 'bukan-email'], 'email', 'Format email tidak valid.'],
            'email tanpa domain' => [['email' => 'siti@'], 'email', 'Format email tidak valid.'],
            'email terlalu panjang' => [['email' => str_repeat('a', 250).'@x.test'], 'email', 'Email maksimal 255 karakter.'],
            'email larik' => [['email' => ['siti@contoh.test']], 'email', 'Format email tidak valid.'],
            // telepon
            'telepon kosong' => [['phone' => ''], 'phone', 'Nomor telepon wajib diisi.'],
            'telepon terlalu pendek' => [['phone' => '12345'], 'phone', IndonesianPhone::MESSAGE],
            'telepon rumah' => [['phone' => '021555'], 'phone', IndonesianPhone::MESSAGE],
            'telepon bukan seluler' => [['phone' => '0712345678'], 'phone', IndonesianPhone::MESSAGE],
            'telepon huruf' => [['phone' => 'abc'], 'phone', IndonesianPhone::MESSAGE],
            'telepon larik' => [['phone' => ['0812']], 'phone', IndonesianPhone::MESSAGE],
            // tanggal lahir (kasus tanggal relatif ada di metode tes terpisah)
            'tanggal kosong' => [['birth_date' => ''], 'birth_date', 'Tanggal lahir wajib diisi.'],
            'tanggal format hari-bulan-tahun' => [['birth_date' => '31-12-1990'], 'birth_date', 'Format tanggal lahir tidak valid.'],
            'tanggal format garis miring' => [['birth_date' => '1990/03/12'], 'birth_date', 'Format tanggal lahir tidak valid.'],
            'tanggal tidak ada di kalender' => [['birth_date' => '1990-02-30'], 'birth_date', 'Format tanggal lahir tidak valid.'],
            'tanggal bukan tanggal' => [['birth_date' => 'bukan tanggal'], 'birth_date', 'Format tanggal lahir tidak valid.'],
            'tanggal larik' => [['birth_date' => ['1990-03-12']], 'birth_date', 'Format tanggal lahir tidak valid.'],
            'tanggal masa depan jauh' => [['birth_date' => '2099-01-01'], 'birth_date', 'Tanggal lahir harus sebelum hari ini.'],
            'tanggal terlalu lampau' => [['birth_date' => '1500-01-01'], 'birth_date', 'Tanggal lahir tidak boleh lebih dari 120 tahun yang lalu.'],
            // jenis kelamin
            'gender kosong' => [['gender' => ''], 'gender', 'Jenis kelamin wajib dipilih.'],
            'gender null' => [['gender' => null], 'gender', 'Jenis kelamin wajib dipilih.'],
            'gender liar' => [['gender' => 'X'], 'gender', 'Jenis kelamin tidak valid.'],
            'gender huruf kecil' => [['gender' => 'l'], 'gender', 'Jenis kelamin tidak valid.'],
            'gender larik' => [['gender' => ['P']], 'gender', 'Jenis kelamin tidak valid.'],
            // alamat
            'alamat kosong' => [['address' => ''], 'address', 'Alamat wajib diisi.'],
            'alamat terlalu pendek' => [['address' => 'Jl. Satu'], 'address', 'Alamat minimal 10 karakter.'],
            'alamat terlalu panjang' => [['address' => str_repeat('a', 501)], 'address', 'Alamat maksimal 500 karakter.'],
            'alamat larik' => [['address' => ['Jl. Merpati No. 5']], 'address', 'Alamat tidak valid.'],
            // password
            'password kosong' => [['password' => '', 'password_confirmation' => ''], 'password', 'Password wajib diisi.'],
            'password terlalu pendek' => [['password' => 'Ab1', 'password_confirmation' => 'Ab1'], 'password', 'Password minimal 8 karakter.'],
            'password tanpa angka' => [['password' => 'Abcdefghij', 'password_confirmation' => 'Abcdefghij'], 'password', 'Password harus mengandung angka.'],
            'password tanpa huruf' => [['password' => '1234567890', 'password_confirmation' => '1234567890'], 'password', 'Password harus mengandung huruf.'],
            'password terlalu panjang' => [['password' => str_repeat('Ab1', 22), 'password_confirmation' => str_repeat('Ab1', 22)], 'password', 'Password maksimal 64 karakter.'],
            'konfirmasi tidak cocok' => [['password' => 'Rahasia123', 'password_confirmation' => 'Rahasia124'], 'password', 'Konfirmasi password tidak cocok.'],
            'password larik' => [['password' => ['Rahasia123'], 'password_confirmation' => ['Rahasia123']], 'password', null],
            'konfirmasi kosong' => [['password_confirmation' => ''], 'password_confirmation', 'Konfirmasi password wajib diisi.'],
            // persetujuan
            'persetujuan tidak dicentang' => [['consent' => null], 'consent', self::CONSENT_MESSAGE],
            'persetujuan kosong' => [['consent' => ''], 'consent', self::CONSENT_MESSAGE],
            'persetujuan nol' => [['consent' => '0'], 'consent', self::CONSENT_MESSAGE],
            'persetujuan teks bebas' => [['consent' => 'tidak'], 'consent', self::CONSENT_MESSAGE],
            'persetujuan larik' => [['consent' => ['1']], 'consent', self::CONSENT_MESSAGE],
        ];
    }

    public function test_an_empty_submission_reports_every_required_field(): void
    {
        $errors = $this->validationErrors(RegisterRequest::class, [], $this->guest());

        $this->assertEqualsCanonicalizing(
            ['full_name', 'email', 'phone', 'birth_date', 'gender', 'address', 'password', 'password_confirmation', 'consent'],
            array_keys($errors),
        );
        $this->assertSame(['Nama lengkap wajib diisi.'], $errors['full_name']);
        $this->assertSame(['Email wajib diisi.'], $errors['email']);
        $this->assertSame(['Nomor telepon wajib diisi.'], $errors['phone']);
        $this->assertSame(['Tanggal lahir wajib diisi.'], $errors['birth_date']);
        $this->assertSame(['Jenis kelamin wajib dipilih.'], $errors['gender']);
        $this->assertSame(['Alamat wajib diisi.'], $errors['address']);
        $this->assertSame(['Password wajib diisi.'], $errors['password']);
        $this->assertSame(['Konfirmasi password wajib diisi.'], $errors['password_confirmation']);
        $this->assertSame([self::CONSENT_MESSAGE], $errors['consent']);
    }

    public function test_a_checked_consent_value_of_one_passes(): void
    {
        $this->assertArrayNotHasKey('consent', $this->errors(['consent' => '1']));
    }

    // --- tanggal lahir relatif ---------------------------------------------------------------

    public function test_a_birth_date_of_today_or_tomorrow_is_rejected(): void
    {
        $this->assertContains('Tanggal lahir harus sebelum hari ini.', $this->errors(['birth_date' => now()->toDateString()])['birth_date']);
        $this->assertContains('Tanggal lahir harus sebelum hari ini.', $this->errors(['birth_date' => now()->addDay()->toDateString()])['birth_date']);
    }

    public function test_a_birth_date_of_yesterday_is_accepted(): void
    {
        $this->assertArrayNotHasKey('birth_date', $this->errors(['birth_date' => now()->subDay()->toDateString()]));
    }

    public function test_a_birth_date_over_one_hundred_and_twenty_years_ago_is_rejected_and_one_hundred_and_nineteen_is_accepted(): void
    {
        $this->assertContains(
            'Tanggal lahir tidak boleh lebih dari 120 tahun yang lalu.',
            $this->errors(['birth_date' => now()->subYears(121)->toDateString()])['birth_date'],
        );
        $this->assertArrayNotHasKey('birth_date', $this->errors(['birth_date' => now()->subYears(119)->toDateString()]));
    }

    // --- email unik (Review Focus G.5) -------------------------------------------------------

    public function test_a_taken_email_is_rejected_for_patient_and_staff_accounts(): void
    {
        $this->createPatient(['email' => 'siti@contoh.test']);
        $this->createStaff('content-admin', ['email' => 'staf@contoh.test']);

        $this->assertContains(self::EMAIL_TAKEN, $this->errors(['email' => 'siti@contoh.test'])['email']);
        $this->assertContains(self::EMAIL_TAKEN, $this->errors(['email' => 'staf@contoh.test'])['email']);
        $this->assertArrayNotHasKey('email', $this->errors(['email' => 'lain@contoh.test']));
    }

    public function test_a_taken_email_is_rejected_regardless_of_letter_case_and_spacing(): void
    {
        $this->createPatient(['email' => 'siti@contoh.test']);

        foreach (['Siti@Contoh.test', 'SITI@CONTOH.TEST', '  siti@contoh.test  '] as $variant) {
            $this->assertContains(self::EMAIL_TAKEN, $this->errors(['email' => $variant])['email'], $variant);
        }
    }
}
```

- [ ] **Step 3: Jalankan tes, pastikan GAGAL (RED nyata)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=RegisterRequestTest
```
Expected: FAIL pada setiap tes dengan `Error: Class "App\Http\Requests\Auth\RegisterRequest" not found`.

- [ ] **Step 4: Buat Form Request (tool Write) `app/Http/Requests/Auth/RegisterRequest.php`**

```php
<?php

namespace App\Http\Requests\Auth;

use App\Enums\Gender;
use App\Rules\IndonesianPhone;
use App\Rules\PasswordRules;
use App\Rules\PersonName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * V-01 (12 §4, F5-R9): registrasi pasien, form tamu. Tanpa NIK dan tanpa field sistem (`user_id`, `patient_number`,
 * `registration_channel`): hanya sembilan field di `rules()` yang dipakai lewat `validated()`. Nilai larik atau
 * nilai liar menghasilkan galat berbahasa Indonesia, bukan 500.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalisasi sebelum validasi (12 §1 butir 4): trim, email huruf kecil, telepon tanpa spasi/tanda pemisah.
     * Hanya untuk nilai string; nilai lain dibiarkan agar divalidasi sebagai galat. Password tidak pernah diubah.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['full_name', 'birth_date', 'gender', 'address'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $normalized[$field] = trim($value);
            }
        }

        $email = $this->input('email');

        if (is_string($email)) {
            $normalized['email'] = Str::lower(trim($email));
        }

        $phone = $this->input('phone');

        if (is_string($phone)) {
            $normalized['phone'] = IndonesianPhone::normalize(trim($phone));
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['bail', 'required', 'string', 'min:3', 'max:100', new PersonName],
            'email' => ['bail', 'required', 'string', 'max:255', 'email:rfc', 'unique:users,email'],
            'phone' => ['bail', 'required', new IndonesianPhone],
            'birth_date' => ['bail', 'required', 'date_format:Y-m-d', 'before:today', 'after_or_equal:-120 years'],
            'gender' => ['bail', 'required', Rule::enum(Gender::class)],
            'address' => ['bail', 'required', 'string', 'min:10', 'max:500'],
            'password' => PasswordRules::default(),
            'password_confirmation' => ['required'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * Pesan V-01. Kunci `password.password.letters`/`numbers` dipakai oleh validator bersarang aturan Password.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Nama lengkap wajib diisi.',
            'full_name.string' => 'Nama lengkap tidak valid.',
            'full_name.min' => 'Nama lengkap minimal 3 karakter.',
            'full_name.max' => 'Nama lengkap maksimal 100 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.string' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'birth_date.required' => 'Tanggal lahir wajib diisi.',
            'birth_date.date_format' => 'Format tanggal lahir tidak valid.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'birth_date.after_or_equal' => 'Tanggal lahir tidak boleh lebih dari 120 tahun yang lalu.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.enum' => 'Jenis kelamin tidak valid.',
            'address.required' => 'Alamat wajib diisi.',
            'address.string' => 'Alamat tidak valid.',
            'address.min' => 'Alamat minimal 10 karakter.',
            'address.max' => 'Alamat maksimal 500 karakter.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.max' => 'Password maksimal 64 karakter.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.string' => 'Password tidak valid.',
            'password.password.letters' => 'Password harus mengandung huruf.',
            'password.password.numbers' => 'Password harus mengandung angka.',
            'password_confirmation.required' => 'Konfirmasi password wajib diisi.',
            'consent.accepted' => 'Anda harus menyetujui Kebijakan Privasi untuk melanjutkan.',
        ];
    }

    /**
     * Nama bidang untuk pesan aturan kustom (`PersonName` memakai `:Attribute`).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => 'Nama lengkap',
            'email' => 'Email',
            'phone' => 'Nomor telepon',
            'birth_date' => 'Tanggal lahir',
            'gender' => 'Jenis kelamin',
            'address' => 'Alamat',
            'password' => 'Password',
            'password_confirmation' => 'Konfirmasi password',
            'consent' => 'Persetujuan',
        ];
    }
}
```

- [ ] **Step 5: Jalankan tes, pastikan LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=RegisterRequestTest
```
Expected: PASS (±60 tes). Bila `password larik` melaporkan galat tak terduga atau `gender` huruf kecil lolos, periksa urutan aturan di Step 4 (`bail` di awal, `string` sebelum `min`/`max`).

- [ ] **Step 6: Pint dan commit siklus A**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Http/Requests/Auth/RegisterRequest.php tests/Feature/Auth/RegisterRequestTest.php
git commit -m @'
feat(fase-5): RegisterRequest (V-01) beserta tes aturan dan normalisasi

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: pint `PASS`; satu commit.

---

#### Siklus B: controller, view, route, dan tes alur registrasi

- [ ] **Step 7: Tulis tes (tool Write) `tests/Feature/Auth/PatientRegistrationTest.php`**

```php
<?php

namespace Tests\Feature\Auth;

use App\Enums\BookingChannel;
use App\Enums\Gender;
use App\Models\AuditLog;
use App\Models\PatientProfile;
use App\Models\User;
use App\Notifications\PatientWelcomeNotification;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Exception;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Registrasi pasien (#23, F5-R9): VAL-02, SEK-01, SEK-02, SEK-05, SEK-06, NTF-08 (HTTP), WLK-06 dan Review Focus
 * G.2, G.4, G.5. Layanan bersifat final sehingga galat dipicu lewat listener model/notifikasi (catatan teknis 5).
 */
class PatientRegistrationTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const PASSWORD = 'Rahasia123';

    private const EMAIL_TAKEN = 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.';

    private const SYSTEM_ERROR = 'Registrasi belum berhasil karena gangguan sistem. Silakan coba lagi.';

    /** @param  array<string, mixed>  $overrides */
    private function postRegister(array $overrides = []): TestResponse
    {
        return $this->from(route('register'))->post(route('register.store'), $this->registrationPayload($overrides));
    }

    private function registeredUser(): User
    {
        return User::query()->where('email', 'siti@contoh.test')->sole();
    }

    // --- tampilan form -----------------------------------------------------------------------

    public function test_register_page_renders_the_complete_form_for_guests(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertSee('<title>Daftar Akun Pasien — '.config('app.name').'</title>', false)
            ->assertSee('Daftar Akun Pasien')
            ->assertSee('Buat akun untuk mendaftar kunjungan rawat jalan secara online.')
            ->assertSee('action="'.route('register.store').'"', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSeeInOrder([
                'name="full_name"', 'name="birth_date"', 'name="gender"', 'name="phone"', 'name="email"',
                'name="address"', 'name="password"', 'name="password_confirmation"', 'name="consent"',
            ], false)
            ->assertSee('>Nama lengkap <span', false)
            ->assertSee('>Tanggal lahir <span', false)
            ->assertSee('Jenis kelamin')
            ->assertSee('>Nomor telepon <span', false)
            ->assertSee('>Email <span', false)
            ->assertSee('>Alamat <span', false)
            ->assertSee('>Password <span', false)
            ->assertSee('>Konfirmasi password <span', false)
            ->assertSee('Nomor seluler aktif, mis. 081234567890.')
            ->assertSee('Minimal 8 karakter, berisi huruf dan angka.')
            ->assertSee('autocomplete="name"', false)
            ->assertSee('autocomplete="bday"', false)
            ->assertSee('autocomplete="tel"', false)
            ->assertSee('autocomplete="email"', false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertSee('max="'.now()->subDay()->toDateString().'"', false)
            ->assertSee('name="gender" value="L"', false)
            ->assertSee('name="gender" value="P"', false)
            ->assertSee('Laki-laki')
            ->assertSee('Perempuan');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $this->assertSame(1, substr_count($response->getContent(), 'class="auth-card'), 'Satu permukaan kartu.');
        $response->assertSee('class="auth-card auth-card-wide mx-auto"', false);
    }

    public function test_register_page_has_the_privacy_consent_link_in_a_new_tab_and_no_identity_or_bpjs_fields(): void
    {
        $response = $this->get(route('register'))->assertOk();

        $response->assertSee('Saya telah membaca dan menyetujui')
            ->assertSee('<a href="'.route('privacy').'" target="_blank" rel="noopener">Kebijakan Privasi</a>', false)
            ->assertSee('(terbuka di tab baru)')
            ->assertSee('id="consent" name="consent" value="1"', false)
            ->assertDontSee('name="nik"', false)
            ->assertDontSee('name="bpjs_number"', false)
            ->assertDontSee('NIK')
            ->assertDontSee('BPJS');
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*type="password"[^>]*\svalue=/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="consent"[^>]*\schecked/', $response->getContent());
    }

    public function test_register_and_login_pages_link_to_each_other(): void
    {
        $this->get(route('register'))
            ->assertSee('Sudah punya akun?')
            ->assertSee('<a href="'.route('login').'">Masuk</a>', false);

        $this->get(route('login'))
            ->assertSee('Belum punya akun?')
            ->assertSee('<a href="'.route('register').'">Daftar</a>', false);
    }

    public function test_registration_guide_now_offers_the_account_button(): void
    {
        $this->get(route('registration-guide'))
            ->assertOk()
            ->assertSee('href="'.route('register').'"', false)
            ->assertSee('>Daftar Akun</a>', false);
    }

    // --- registrasi sukses -------------------------------------------------------------------

    public function test_successful_registration_creates_the_account_and_profile_logs_in_and_redirects_to_the_dashboard(): void
    {
        Notification::fake();
        $this->travelTo('2026-10-08 09:30:00');

        $this->postRegister()
            ->assertRedirect(route('patient.dashboard'))
            ->assertSessionHas('success', 'Registrasi berhasil. Selamat datang, Siti Rahma.');

        $user = $this->registeredUser();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Siti Rahma', $user->name);
        $this->assertSame('081234567890', $user->phone);
        $this->assertTrue($user->isPatient());
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->must_change_password);
        $this->assertNull($user->email_verified_at, 'K-22: tanpa verifikasi wajib.');
        $this->assertSame('2026-10-08 09:30:00', $user->last_login_at->format('Y-m-d H:i:s'));

        $profile = PatientProfile::query()->where('user_id', $user->id)->sole();
        $this->assertSame('Siti Rahma', $profile->full_name);
        $this->assertSame('1990-03-12', $profile->birth_date->format('Y-m-d'));
        $this->assertSame(Gender::Female, $profile->gender);
        $this->assertSame('081234567890', $profile->phone);
        $this->assertSame('siti@contoh.test', $profile->email);
        $this->assertSame('Jl. Merpati No. 5, Pamulang', $profile->address);
        $this->assertSame(BookingChannel::Online, $profile->registration_channel);
        $this->assertTrue($profile->is_active);
        $this->assertNull($profile->created_by_user_id);
        $this->assertSame('1.0', $profile->consent_version);
        $this->assertSame('2026-10-08 09:30:00', $profile->consent_at->format('Y-m-d H:i:s'));
        $this->assertSame('P'.str_pad((string) $profile->id, 6, '0', STR_PAD_LEFT), $profile->patient_number);
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, PatientProfile::query()->count());
    }

    public function test_after_registration_the_dashboard_greets_the_new_patient_with_the_flash_message(): void
    {
        Notification::fake();

        $html = $this->from(route('register'))->followingRedirects()
            ->post(route('register.store'), $this->registrationPayload())
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Registrasi berhasil. Selamat datang, Siti Rahma.', $html);
        $this->assertStringContainsString('Halo, Siti Rahma', $html);
        $this->assertStringContainsString('Belum ada pendaftaran', $html);
    }

    /** SEK-02: ID sesi berubah setelah registrasi. */
    public function test_session_id_changes_after_registration(): void
    {
        Notification::fake();
        $this->get(route('register'));
        $before = session()->getId();

        $this->postRegister()->assertRedirect(route('patient.dashboard'));

        $this->assertNotSame($before, session()->getId());
    }

    public function test_registration_returns_to_the_intended_protected_url_with_its_query_intact(): void
    {
        Notification::fake();

        $this->get('/pasien?contoh=utuh')->assertRedirect(route('login'));
        $this->postRegister()->assertRedirect(url('/pasien?contoh=utuh'));
    }

    public function test_registration_ignores_an_intended_admin_url(): void
    {
        Notification::fake();

        $this->get('/admin/dokter')->assertRedirect(route('admin.login'));
        $this->postRegister()->assertRedirect(route('patient.dashboard'));
    }

    public function test_consent_version_comes_from_the_privacy_setting(): void
    {
        Notification::fake();
        $this->seed(SettingsSeeder::class);
        $settings = app(SettingsService::class);
        $settings->forget();
        $settings->set('privacy.consent_version', '2.3');

        $this->postRegister()->assertRedirect(route('patient.dashboard'));

        $this->assertSame('2.3', PatientProfile::query()->sole()->consent_version);
    }

    public function test_normalised_values_are_what_gets_stored(): void
    {
        Notification::fake();

        $this->postRegister([
            'full_name' => '  Siti Rahma ',
            'email' => '  Siti@Contoh.TEST ',
            'phone' => '+62 812 3456 7890',
        ])->assertRedirect(route('patient.dashboard'));

        $user = $this->registeredUser();
        $this->assertSame('Siti Rahma', $user->name);
        $this->assertSame('+6281234567890', $user->phone);
        $this->assertSame('+6281234567890', PatientProfile::query()->sole()->phone);
        $this->assertSame('siti@contoh.test', PatientProfile::query()->sole()->email);
    }

    /** WLK-06: tidak ada pencocokan otomatis dengan profil walk-in bernama dan bertanggal lahir sama. */
    public function test_registration_never_merges_with_an_existing_walk_in_profile(): void
    {
        Notification::fake();
        $walkIn = PatientProfile::factory()->create(['full_name' => 'Siti Rahma', 'birth_date' => '1990-03-12', 'phone' => '081234567890']);

        $this->postRegister()->assertRedirect(route('patient.dashboard'));

        $this->assertNull($walkIn->fresh()->user_id);
        $this->assertSame(2, PatientProfile::query()->count());
        $this->assertSame($this->registeredUser()->id, PatientProfile::query()->whereKeyNot($walkIn->id)->sole()->user_id);
    }

    // --- SEK-01: password ------------------------------------------------------------------

    public function test_the_password_is_stored_as_a_bcrypt_hash_and_never_appears_anywhere(): void
    {
        Notification::fake();

        $html = $this->from(route('register'))->followingRedirects()
            ->post(route('register.store'), $this->registrationPayload())
            ->assertOk()
            ->getContent();

        $user = $this->registeredUser();
        $this->assertNotSame(self::PASSWORD, $user->getRawOriginal('password'));
        $this->assertStringStartsWith('$2y$', $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));

        $this->assertStringNotContainsString(self::PASSWORD, $html);
        $this->assertStringNotContainsString(self::PASSWORD, json_encode(session()->all(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString(self::PASSWORD, json_encode(DB::table('audit_logs')->get(), JSON_THROW_ON_ERROR));
        $this->assertGreaterThan(0, AuditLog::query()->where('action', 'patient.registered')->count(), 'Audit patient.registered dari Task 2 harus ada.');
    }

    public function test_a_failed_submission_never_flashes_the_password_back(): void
    {
        $html = $this->from(route('register'))->followingRedirects()
            ->post(route('register.store'), $this->registrationPayload(['consent' => null]))
            ->assertOk()
            ->getContent();

        $this->assertNull(session('_old_input.password'));
        $this->assertNull(session('_old_input.password_confirmation'));
        $this->assertStringNotContainsString(self::PASSWORD, $html);
        $this->assertStringNotContainsString(self::PASSWORD, json_encode(session()->all(), JSON_THROW_ON_ERROR));
    }

    // --- VAL-02: validasi di tingkat HTTP, tanpa baris parsial -------------------------------

    public function test_invalid_registration_shows_specific_messages_keeps_the_input_and_creates_no_rows(): void
    {
        Notification::fake();
        $this->createPatient(['email' => 'siti@contoh.test']);
        $users = User::query()->count();
        $profiles = PatientProfile::query()->count();

        $this->postRegister([
            'password' => 'abc',
            'password_confirmation' => 'abc',
            'birth_date' => now()->addDay()->toDateString(),
            'consent' => null,
        ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors([
                'email' => self::EMAIL_TAKEN,
                'password' => 'Password minimal 8 karakter.',
                'birth_date' => 'Tanggal lahir harus sebelum hari ini.',
                'consent' => 'Anda harus menyetujui Kebijakan Privasi untuk melanjutkan.',
            ]);

        $this->assertSame($users, User::query()->count());
        $this->assertSame($profiles, PatientProfile::query()->count());
        $this->assertSame('Siti Rahma', session('_old_input.full_name'));
        $this->assertNull(session('_old_input.password'));
        Notification::assertNothingSent();
        $this->assertGuest();
    }

    public function test_the_form_shows_field_errors_and_keeps_choices_after_a_failed_submission(): void
    {
        $html = $this->from(route('register'))->followingRedirects()
            ->post(route('register.store'), $this->registrationPayload(['consent' => null]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-errors-summary', $html);
        $this->assertStringContainsString('id="consent-error"', $html);
        $this->assertStringContainsString('Anda harus menyetujui Kebijakan Privasi untuk melanjutkan.', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="consent"[^>]*aria-invalid="true"/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="gender"[^>]*value="P"[^>]*\schecked/', $html);
        $this->assertStringContainsString('value="siti@contoh.test"', $html);
        $this->assertStringContainsString('value="0812-3456-7890"', $html); // old input = ketikan mentah, bukan hasil normalisasi
        $this->assertStringNotContainsString('autofocus', $html);
    }

    /** Review Focus G.5: email ganda beda huruf besar ditolak. */
    public function test_an_email_that_differs_only_by_letter_case_is_rejected(): void
    {
        $this->createPatient(['email' => 'siti@contoh.test']);

        $this->postRegister(['email' => 'Siti@Contoh.test'])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['email' => self::EMAIL_TAKEN]);

        $this->assertSame(1, User::query()->count());
    }

    /** Review Focus G.2: larik dan nilai liar menghasilkan galat field, bukan 500, dan halaman dirender ulang. */
    public function test_array_and_wild_values_give_field_errors_without_a_500_or_partial_rows(): void
    {
        $arrays = [
            'full_name' => ['Siti'], 'email' => ['siti@contoh.test'], 'phone' => ['0812'], 'birth_date' => ['1990-03-12'],
            'gender' => ['P'], 'address' => ['Jl. Merpati No. 5'], 'password' => ['Rahasia123'],
            'password_confirmation' => ['Rahasia123'], 'consent' => ['1'],
        ];

        $html = $this->from(route('register'))->followingRedirects()
            ->post(route('register.store'), $arrays)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-errors-summary', $html);

        $this->postRegister(['gender' => 'X', 'birth_date' => '2099-01-01'])
            ->assertSessionHasErrors(['gender' => 'Jenis kelamin tidak valid.', 'birth_date' => 'Tanggal lahir harus sebelum hari ini.']);
        $this->postRegister(['birth_date' => '31-12-1990'])
            ->assertSessionHasErrors(['birth_date' => 'Format tanggal lahir tidak valid.']);

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, PatientProfile::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    // --- atomisitas dan galat sistem (Review Focus G.5) --------------------------------------

    public function test_a_system_error_while_creating_the_profile_rolls_everything_back_and_keeps_the_input(): void
    {
        Notification::fake();
        PatientProfile::creating(function (): void {
            throw new RuntimeException('Gangguan uji.');
        });

        $this->postRegister()
            ->assertRedirect(route('register'))
            ->assertSessionHas('error', self::SYSTEM_ERROR)
            ->assertSessionHas('_old_input.email', 'siti@contoh.test')
            ->assertSessionHas('_old_input.full_name', 'Siti Rahma')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');

        $this->assertSame(0, User::query()->count(), 'Akun harus ikut di-rollback.');
        $this->assertSame(0, PatientProfile::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
        Notification::assertNothingSent();
        $this->assertGuest();
    }

    public function test_a_unique_constraint_race_shows_the_email_message_instead_of_a_500(): void
    {
        Notification::fake();
        User::creating(function (): void {
            throw new UniqueConstraintViolationException('mariadb', 'insert into `users` (`email`) values (?)', ['siti@contoh.test'], new Exception('Duplicate entry'));
        });

        $this->postRegister()
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['email' => self::EMAIL_TAKEN])
            ->assertSessionHas('_old_input.email', 'siti@contoh.test')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('error');

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, PatientProfile::query()->count());
        Notification::assertNothingSent();
        $this->assertGuest();
    }

    // --- NTF-08 di tingkat HTTP ---------------------------------------------------------------

    public function test_the_welcome_notification_is_sent_once_by_mail_after_a_successful_registration(): void
    {
        Notification::fake();

        $this->postRegister()->assertRedirect(route('patient.dashboard'));

        Notification::assertSentToTimes($this->registeredUser(), PatientWelcomeNotification::class, 1);
        Notification::assertSentTo(
            $this->registeredUser(),
            PatientWelcomeNotification::class,
            fn (PatientWelcomeNotification $notification, array $channels): bool => $channels === ['mail'],
        );
        Notification::assertCount(1);
    }

    public function test_no_notification_is_sent_when_validation_fails(): void
    {
        Notification::fake();

        $this->postRegister(['consent' => null])->assertSessionHasErrors('consent');

        Notification::assertNothingSent();
    }

    public function test_a_failing_welcome_email_does_not_cancel_the_registration(): void
    {
        Event::listen(NotificationSending::class, function (): void {
            throw new RuntimeException('Server email tidak tersedia.');
        });

        $this->postRegister()
            ->assertRedirect(route('patient.dashboard'))
            ->assertSessionHas('success', 'Registrasi berhasil. Selamat datang, Siti Rahma.');

        $user = $this->registeredUser();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, PatientProfile::query()->where('user_id', $user->id)->count());
    }

    // --- throttle dan CSRF -------------------------------------------------------------------

    /** SEK-05: registrasi ke-6 per jam per IP ditolak (D-23: 302 + flash + Retry-After untuk POST). */
    public function test_sixth_registration_attempt_per_hour_is_throttled_and_creates_nothing(): void
    {
        Notification::fake();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from(route('register'))
                ->post(route('register.store'), [])
                ->assertRedirect(route('register'))
                ->assertSessionHasErrors('email');
        }

        $this->postRegister()
            ->assertStatus(302)
            ->assertRedirect(route('register'))
            ->assertHeader('Retry-After')
            ->assertSessionHas('error', fn ($message) => preg_match('/^Terlalu banyak percobaan\. Silakan coba lagi dalam \d+ detik\.$/', $message) === 1)
            ->assertSessionMissing('_old_input.password');

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, PatientProfile::query()->count());
        $this->assertGuest();
        Notification::assertNothingSent();
    }

    public function test_a_json_request_over_the_limit_gets_status_429(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson(route('register.store'), [])->assertStatus(422);
        }

        $this->postJson(route('register.store'), $this->registrationPayload())
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        $this->assertSame(0, User::query()->count());
    }

    /** SEK-06/ERR-03: tanpa token CSRF, pasien kembali ke form dengan pesan sesi berakhir dan tanpa baris. */
    public function test_missing_csrf_token_redirects_back_with_a_session_expired_message_and_creates_nothing(): void
    {
        $this->app['env'] = 'local'; // mengaktifkan kembali ValidateCsrfToken (dilewati saat env `testing`)

        $this->from(route('register'))
            ->post(route('register.store'), $this->registrationPayload())
            ->assertRedirect(route('register'))
            ->assertSessionHas('error', 'Sesi formulir Anda telah berakhir. Silakan periksa isian Anda lalu kirim ulang.')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');

        $this->assertSame(0, User::query()->count());
        $this->assertGuest();
    }

    // --- akun yang sudah login (AKS-02) ------------------------------------------------------

    public function test_logged_in_accounts_opening_the_registration_page_go_to_their_dashboard(): void
    {
        $this->actingAs($this->createPatient())->get(route('register'))->assertRedirect(route('patient.dashboard'));
        $this->actingAs($this->createStaff('content-admin'))->get(route('register'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_logged_in_patient_posting_the_registration_form_is_redirected_and_nothing_is_created(): void
    {
        $this->actingAs($this->createPatient());
        $users = User::query()->count();

        $this->postRegister(['email' => 'lain@contoh.test'])->assertRedirect(route('patient.dashboard'));

        $this->assertSame($users, User::query()->count());
    }

    // --- route -------------------------------------------------------------------------------

    public function test_registration_routes_have_the_expected_uri_method_and_middleware(): void
    {
        $expected = [
            'register' => ['GET', 'daftar', ['guest']],
            'register.store' => ['POST', 'daftar', ['guest', 'throttle:register']],
        ];

        foreach ($expected as $name => [$method, $uri, $middleware]) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} belum terdaftar.");
            $this->assertContains($method, $route->methods(), $name);
            $this->assertSame($uri, $route->uri(), $name);
            $this->assertSame($middleware, array_values(array_diff(array_filter($route->gatherMiddleware(), 'is_string'), ['web'])), $name);
        }
    }
}
```

- [ ] **Step 8: Jalankan tes, pastikan GAGAL (RED nyata)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PatientRegistrationTest
```
Expected: FAIL; hampir semua tes dengan `Symfony\Component\Routing\Exception\RouteNotFoundException: Route [register] not defined.` (`test_the_welcome_...` dan lainnya yang memanggil `route('register.store')` atau `route('register')`); `test_registration_routes_have_the_expected_uri_method_and_middleware` gagal dengan "Route register belum terdaftar.".

- [ ] **Step 9: Tambah route (tool Edit) `routes/web.php`**

old:
```php
use App\Http\Controllers\Auth\LoginController;
```
new:
```php
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
```
old:
```php
    Route::post('/masuk', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
```
new:
```php
    Route::post('/masuk', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/daftar', [RegisterController::class, 'create'])->name('register');
    Route::post('/daftar', [RegisterController::class, 'store'])->middleware('throttle:register')->name('register.store');
```

- [ ] **Step 10: Buat controller (tool Write) `app/Http/Controllers/Auth/RegisterController.php`**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Patients\PatientRegistrationService;
use App\Support\PatientIntendedUrl;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class RegisterController extends Controller
{
    /**
     * Field yang tidak pernah dikembalikan ke form. `back()->withInput()` tanpa argumen tidak menyaring daftar
     * `dontFlash` (hanya ValidationException yang disaring handler), sehingga controller menyaringnya sendiri.
     */
    private const SENSITIVE_INPUT = ['password', 'password_confirmation', 'current_password', 'bpjs_number', 'payment_reference'];

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, PatientRegistrationService $registration): RedirectResponse
    {
        try {
            $user = $registration->register($request->validated());
        } catch (UniqueConstraintViolationException) {
            // Balapan: email yang sama terdaftar di antara validasi `unique` dan insert.
            return back()
                ->withInput($request->except(self::SENSITIVE_INPUT))
                ->withErrors(['email' => 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.']);
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withInput($request->except(self::SENSITIVE_INPUT))
                ->with('error', 'Registrasi belum berhasil karena gangguan sistem. Silakan coba lagi.');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->to(PatientIntendedUrl::pull($request))
            ->with('success', "Registrasi berhasil. Selamat datang, {$user->name}.");
    }
}
```

- [ ] **Step 11: Buat view registrasi (tool Write) `resources/views/auth/register.blade.php`**

```blade
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
```

- [ ] **Step 12: Tambah footer di view login (tool Edit) `resources/views/auth/login.blade.php`**

old:
```blade
    </form>
</x-layouts.auth>
```
new:
```blade
    </form>

    <x-slot:footer>
        <p class="mb-0">Belum punya akun? <a href="{{ route('register') }}">Daftar</a></p>
    </x-slot:footer>
</x-layouts.auth>
```

- [ ] **Step 13: Jalankan tes terfilter, pastikan LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="PatientRegistrationTest|RegisterRequestTest|PatientLoginTest|PatientDashboardTest|AuthPolishTest|LayoutsTest|PublicContentPagesTest"
```
Expected: PASS semuanya (±35 tes `PatientRegistrationTest`). Bila `test_the_password_is_stored_as_a_bcrypt_hash...` gagal pada asersi audit, Task 2 belum menulis `patient.registered` (kembalikan ke Task 2). Bila `test_a_system_error_...` mendapat flash `error` kosong, pastikan listener `PatientProfile::creating` dipasang sebelum POST dan bahwa service tidak menelan `RuntimeException`. Bila `test_a_unique_constraint_race_...` mendapat 500, pastikan `catch (UniqueConstraintViolationException)` mendahului `catch (Throwable)`.

- [ ] **Step 14: Konfirmasi route dan regresi terarah**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
(& $PHP artisan route:list --except-vendor --json | ConvertFrom-Json).Count
& $PHP artisan route:list --except-vendor --path=daftar
& $PHP artisan test --filter="Phase4bRoutePermissionTest|AdminRouteMiddlewareTest|InternalLinkTest|BannerButtonTest|SecurityHeadersTest|RawOutputScanTest|GuestRedirectTest|AdminPageMatrixTest|ExceptionRenderingTest"
```
Expected: jumlah route `134`; `register` (GET `daftar`) dan `register.store` (POST `daftar`) tercantum; tes yang dikunci hijau (106 route admin tetap; `RawOutputScanTest` hijau karena view baru tanpa `{!!` atau `HtmlString`).

- [ ] **Step 15: Pint dan commit siklus B**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git status --short
git add routes/web.php app/Http/Controllers/Auth/RegisterController.php resources/views/auth/register.blade.php resources/views/auth/login.blade.php tests/Feature/Auth/PatientRegistrationTest.php
git commit -m @'
feat(fase-5): registrasi pasien (#23): RegisterController, view, route register, footer login

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: pint `PASS`; `git status --short` hanya memuat berkas Task 4 sebelum `git add`; satu commit.

---

#### Catatan penulis (konteks untuk implementer dan reviewer)

1. **`RegisterRequest::rules()` menyimpang tipis dari digest V-01 untuk `email`:** urutan `bail required string max:255 email:rfc unique:users,email` (digest: `required email:rfc max:255 unique`). Alasan: `max` harus mendahului `email:rfc` agar pesan "Email maksimal 255 karakter." dapat tercapai (RFC menolak local-part > 64 karakter lebih dulu), dan `string` mencegah `email[]=x` mencapai `unique`. Pesan dan hasil akhirnya sama dengan V-01. `UpdatePatientProfileRequest` (Task 6) sebaiknya memakai urutan yang sama untuk email.
2. **Layanan `final` tidak dapat di-mock.** Brief meminta "mock service melempar `RuntimeException`/`UniqueConstraintViolationException`"; `PatientRegistrationService` (kerangka D) dan `AuditLogger` adalah `final`, sehingga Mockery gagal. Tes memakai listener model (`PatientProfile::creating`, `User::creating`) dan listener `NotificationSending`, yang menguji jalur kode yang sama (controller menangkap pengecualian dari service) sekaligus rollback nyata. Dampak ke Task 2: tidak ada yang perlu diubah; Task 2 WAJIB mempertahankan notifikasi di luar transaksi dalam `try/catch (Throwable)` dan memanggil `$user->notify(...)`/`Notification::send(...)` (keduanya melewati `NotificationSending`).
3. **`back()->withInput()` tidak menyaring `dontFlash`.** Spesifikasi F5-R9 menulis `back()->withInput()`; controller memakai `withInput($request->except(self::SENSITIVE_INPUT))` (daftar `password`, `password_confirmation`, `current_password`, `bpjs_number`, `payment_reference`) agar password tidak masuk ke sesi (SEK-01). Task 5 (reset) dan Task 6 (profil, ganti password) yang menulis `back()->withInput(...)` manual perlu menyaring dengan cara yang sama (reset: `$request->only('email')` sudah aman).
4. **Tes bergantung pada audit `patient.registered`** (Task 2): `test_the_password_is_stored_as_a_bcrypt_hash_and_never_appears_anywhere` menegaskan setidaknya satu baris audit tersebut (dan tidak ada password di seluruh `audit_logs`). Bila Task 2 menunda audit, ubah asersi itu, jangan melemahkan pemeriksaan password.
5. **Semantik `registrationPayload()` (Task 1).** Tes mengandalkan `registrationPayload($overrides)` memakai nilai `$overrides` sebagai penimpa (termasuk `null` dan larik) atas data valid kerangka D (`array_merge($defaults, $overrides)` atau `$overrides + $defaults`). Nilai `null` pada `consent` dan `gender` berarti "tidak diisi".
6. **Rekonsiliasi dokumen (T8).** Tes `test_registration_never_merges_with_an_existing_walk_in_profile` mengunci WLK-06 sisi registrasi; teks 12 BR-103/05 §2 yang menyebut pencocokan "hubungkan akun" tetap fase 7.

---

### Task 5: Reset password (#25, #26)

**Files:**
- Create: `app/Http/Requests/Auth/ForgotPasswordRequest.php`
- Create: `app/Http/Requests/Auth/ResetPasswordRequest.php`
- Create: `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- Create: `app/Http/Controllers/Auth/NewPasswordController.php`
- Create: `resources/views/auth/forgot-password.blade.php`
- Create: `resources/views/auth/reset-password.blade.php`
- Modify: `resources/views/auth/login.blade.php` (tautan "Lupa password?", E.1)
- Modify: `routes/web.php` (4 route tamu + 2 `use`)
- Modify: `lang/id/passwords.php` (ganti seluruh nilai, F5-R16)
- Modify: `lang/id.json` (tambah 5 kunci notifikasi reset, F5-R17 bagian T5)
- Modify (tes): `tests/Feature/LocalizationTest.php`
- Test: `tests/Feature/Auth/PatientPasswordResetTest.php`
- Tidak diubah: `app/Providers/AppServiceProvider.php` (limiter `password-reset` sudah ada), `config/auth.php` (broker `users`: `expire` 60, `throttle` 60), `bootstrap/app.php` (handler 429 sudah ada), `app/Models/User.php` (tanpa override `sendPasswordResetNotification`; notifikasi bawaan Laravel dipakai apa adanya), migration (tabel `password_reset_tokens` dan `sessions` sudah ada).

**Interfaces:**
- Consumes (dari tugas sebelumnya, nama persis kerangka):
  - T1: `Tests\Support\InteractsWithPatients` (`createPatient(array $userAttributes = [], array $profileAttributes = []): User`, `patientPassword(): string` = `Rahasia123`), `User::patientProfile()`.
  - T2: `lang/id.json` dengan lima kunci templat mail bawaan (`Hello!`, `Whoops!`, `Regards,`, kunci subcopy tombol, `All rights reserved.`), `PatientRegistrationService` (tidak dipakai langsung).
  - T3: route `login`, `login.store`, `patient.dashboard`; view `auth/login.blade.php` (E.1); `layouts.auth` dengan prop `heading`/`wide`; redirect pengguna login dari halaman `guest` ke `patient.dashboard` (`bootstrap/app.php` `redirectUsersTo`, tanpa cadangan).
  - T4: footer login "Belum punya akun? Daftar" (tidak disentuh di sini).
  - Fase 1: `App\Rules\PasswordRules::default()`, `App\Services\Audit\AuditLogger::log()`, `App\Support\Actor::patient()`, `InteractsWithStaff::createStaff()`, limiter `password-reset` (3 per 10 menit, kunci email+IP), handler 429 (POST ber-throttle = redirect 302 + flash `error` + `Retry-After`), `x-flash-alert` (kunci `success`/`error`/`warning`), `x-form.input`, `x-loading-button`.
- Produces:
  - Route: `password.request` (GET `/lupa-password`), `password.email` (POST `/lupa-password`, `throttle:password-reset`), `password.reset` (GET `/reset-password/{token}`), `password.update` (POST `/reset-password`, `throttle:password-reset`), semuanya `guest`.
  - `App\Http\Requests\Auth\ForgotPasswordRequest` (V-04), `App\Http\Requests\Auth\ResetPasswordRequest` (V-05).
  - `App\Http\Controllers\Auth\PasswordResetLinkController` (`create`, `store`), `App\Http\Controllers\Auth\NewPasswordController` (`create`, `store`).
  - View `auth.forgot-password`, `auth.reset-password`; tautan "Lupa password?" di `auth.login`.
  - Audit `auth.password_reset` (F5-R24) dan teks `passwords.*` baru (F5-R16); lima kunci `lang/id.json` baru (F5-R17).
  - Kontrak untuk T8: `/lupa-password` ditambahkan ke `SecurityHeadersTest::htmlPages` oleh T8; matriks akses #25/#26 oleh T8.

**Test IDs:** NTF-04 (otomatis: `Notification::fake()`, token sekali pakai, kedaluwarsa), NTF-05 dan SEK-16 (sisi reset: respons identik terdaftar/tak terdaftar/staf/nonaktif/throttle broker), SEK-04 (permintaan ke-4 dalam 10 menit), SEK-11 (sisi reset: audit `auth.password_reset` tanpa token/password), SEK-06 (CSRF `password.email`), SEK-02 (sebagian: sesi akun dihapus), AKS-01/AKS-02 (sebagian: halaman tamu, pengguna login dialihkan; matriks 5 aktor penuh = T8), VAL (V-04, V-05), Review Focus G.1 (enumerasi akun, sisi reset), G.2 (input larik tanpa 500), G.4 (setelah reset semua sesi akun terhapus). Manual (NTF-04 bagian log) dijalankan koordinator di T8 mengikuti 16 §10.

**Catatan teknis (diverifikasi dari source):**
- **Broker Laravel 12.69 (`vendor/laravel/framework/src/Illuminate/Auth/Passwords/PasswordBroker.php`).** `sendResetLink(array $credentials)` dan `reset(array $credentials, Closure $callback)` keduanya membungkus kerja dalam `Timebox` (200 ms). `getUser()` membuang kunci `token`, lalu `EloquentUserProvider::retrieveByCredentials()` membuang setiap kunci yang mengandung `password`, dan menjadikan SISANYA klausa `where` (`->where('email', …)->where('account_type', 'patient')->where('is_active', true)`). Jadi `account_type`/`is_active` pada array kredensial menyaring staf dan akun nonaktif: hasilnya `INVALID_USER` (sama seperti email tak terdaftar), tidak ada token dibuat, tidak ada notifikasi. Nilai `account_type` harus string `'patient'` (`AccountType::Patient->value`), bukan enum.
- **Konstanta status** (`Illuminate\Contracts\Auth\PasswordBroker`): `RESET_LINK_SENT = 'passwords.sent'`, `PASSWORD_RESET = 'passwords.reset'`, `INVALID_USER = 'passwords.user'`, `INVALID_TOKEN = 'passwords.token'`, `RESET_THROTTLED = 'passwords.throttled'`. Controller memakai konstanta `Password::PASSWORD_RESET`, tidak membandingkan string.
- **Throttle 60 detik.** `sendResetLink` memanggil `$this->tokens->recentlyCreatedToken($user)` SEBELUM membuat token; bila baris `password_reset_tokens` milik email itu berumur < 60 detik (`config('auth.passwords.users.throttle')`, `Carbon::parse(created_at)->addSeconds(60)->isFuture()`), hasilnya `RESET_THROTTLED` dan TIDAK ada notifikasi baru (token lama tetap). Hanya terjadi untuk akun yang ditemukan, sehingga bila status ini dipetakan ke pesan berbeda, ia membocorkan "email terdaftar". Itu sebabnya controller mengabaikan status dan `passwords.throttled` diberi teks sama dengan `passwords.sent` (F5-R16).
- **Kedaluwarsa.** `DatabaseTokenRepository::exists()` memeriksa `! tokenExpired(created_at)` (`Carbon::parse($createdAt)->addSeconds(expire=3600)->isPast()`) dan `Hash::check($token, $record['token'])`. Token disimpan ter-hash; tes `travel(61)->minutes()` melewati batas, `travel(59)->minutes()` belum. Setelah `reset()` sukses `tokens->delete($user)` menghapus baris (token sekali pakai).
- **`ResetPassword` (`Illuminate\Auth\Notifications\ResetPassword`).** `via` = `['mail']`; URL = `url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false))`, jadi route bernama `password.reset` WAJIB ada sebelum notifikasi dikirim. Teks memakai `Lang::get(...)` dengan lima kunci Inggris yang diterjemahkan lewat `lang/id.json`: subjek "Reset Password Notification", baris "You are receiving this email because we received a password reset request for your account.", tombol "Reset Password", "This password reset link will expire in :count minutes." (`:count` = `config('auth.passwords.users.expire')` = 60), "If you did not request a password reset, no further action is required.". Properti publik `$token` dipakai tes untuk mengambil token dari `Notification::fake()`. Salam "Hello!"/"Regards,"/footer "All rights reserved." berasal dari templat markdown bawaan dan diterjemahkan oleh kunci T2.
- **Limiter.** `password-reset` = `Limit::perMinutes(10, 3)->by(emailKey)`; `emailKey` = `Str::lower(trim($email)).'|'.ip` (nilai non-string = kosong). `password.email` dan `password.update` memakai nama limiter yang sama sehingga kunci cache sama (`md5('password-reset'.$key)`): satu bucket (dicatat sebagai Keterbatasan di README T8). Permintaan ke-4 dalam 10 menit ditolak middleware sebelum controller: handler 429 di `bootstrap/app.php` mengubahnya menjadi `redirect()->to(SafeRedirect::back($request))` + flash `error` "Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik." + header `Retry-After`. Email dinormalisasi oleh `emailKey`, jadi `' PASIEN@Contoh.test '` memakai bucket yang sama.
- **Flash.** `x-flash-alert` hanya membaca `success`/`error`/`warning`; kunci `status` bawaan Laravel TIDAK ditampilkan. Maka sukses permintaan reset memakai `->with('success', __('passwords.sent'))`.
- **Tes lama yang menyentuh teks `passwords.*`.** Hanya `tests/Feature/LocalizationTest.php` (`test_auth_and_password_messages_are_generic_indonesian`, baris 33-37, mengunci `__('passwords.sent') === __('passwords.user')`; tetap benar). Tes itu diperluas di task ini untuk mengunci `throttled` = `sent`, teks F5-R16, dan isi `id.json`. Tes `languageFiles` hanya membandingkan KUNCI `lang/en` vs `lang/id` (kunci `passwords.*` tidak berubah). `grep` tidak menemukan tes lain yang mengunci teks lama ("Password Anda telah diatur ulang", "Mohon tunggu sebelum mencoba lagi").
- **Tes di sini memakai mailer `array` (phpunit.xml) dan `SESSION_DRIVER=array`/`CACHE_STORE=array`.** Atribut sesi array tetap hidup antar permintaan dalam satu tes (`Store::loadSession()` meng-`array_merge` atribut yang ada), sehingga flash/old input dari permintaan sebelumnya terbaca di permintaan berikutnya (pola `AdminPasswordChangeTest`). Baris tabel `sessions` TIDAK ditulis otomatis oleh driver `array`; tes menyisipkan baris palsu lewat `DB::table('sessions')` (kolom wajib: `id`, `payload`, `last_activity`; `user_id`, `ip_address`, `user_agent` nullable).
- **Input larik.** `email:rfc` pada larik gagal tanpa galat PHP; urutan aturan `['bail','required','string','max:255','email:rfc']` (pola `StoreContactMessageRequest`) memberi "Format email tidak valid." untuk larik dan "Email maksimal 255 karakter." untuk email 257 karakter (urutan `max` sebelum `email:rfc` menghindari ambiguitas batas panjang bagian lokal). Larik pada `password` ditolak aturan `Password` (`string`) tanpa 500. `GET /reset-password/{token}?email[]=x`: `$request->query('email')` mengembalikan larik; controller memakai `is_string()` sebelum `trim`, jadi tidak ada konversi larik ke string.
- **Sesi.** Setelah reset sukses, `DB::table('sessions')->where('user_id', $user->id)->delete()` menghapus SEMUA baris akun (BR-148); tidak ada login otomatis (K-11): redirect ke `login` dengan flash sukses.
- **Audit.** `AuditLogger::log('auth.password_reset', $user, null, null, "Password akun pasien {nomor} diatur ulang lewat tautan email.", Actor::patient($user))`; `old_values`/`new_values` NULL sehingga tidak mungkin memuat token/password; nomor pasien dari `patientProfile?->patient_number ?? '-'`. Aksi `auth.*` tidak meredaksi PII, tetapi deskripsi memang tidak memuat nama/email.
- **Hak akses halaman.** Route berada di grup `web` + `guest` saja (bukan grup pasien). Pengguna login (pasien atau staf) membuka `/lupa-password` atau `/reset-password/{token}` dialihkan oleh `RedirectIfAuthenticated` ke `redirectUsersTo` (pasien: `patient.dashboard`, staf: `admin.dashboard`). `SecurityHeaders` sudah menandai `lupa-password` dan `reset-password/*` sebagai `Cache-Control: no-store, private`.
- **Transaksi callback reset.** Callback broker dibungkus `DB::transaction` agar password + hapus sesi + audit atomik; token baru dihapus broker sesudah callback (di luar transaksi). Bila audit melempar, password tidak berubah dan token tetap berlaku.
- Jumlah tes ±55 (provider V-04 4, provider V-05 14, sisanya perilaku).

- [ ] **Step 1: Verifikasi prasyarat (cabang, T1 sampai T4 sudah ada)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
Test-Path tests\Support\InteractsWithPatients.php, routes\patient.php, app\Http\Controllers\Auth\LoginController.php, app\Http\Controllers\Auth\RegisterController.php, resources\views\auth\login.blade.php, lang\id.json
(& $PHP artisan route:list --except-vendor --json | ConvertFrom-Json).Count
& $PHP artisan test --filter=PatientLoginTest
```
Expected: `fase-5-akun-pasien`; `git status` kosong (atau hanya `?? .superpowers/`); enam nilai `True`; jumlah route `134` (128 dasar + 4 T3 + 2 T4); `PatientLoginTest` PASS. Bila ada `False`/jumlah lain, T1 sampai T4 belum lengkap: hentikan dan laporkan. Apache dan MySQL harus sudah di-Start di `C:\xampp3\xampp-control.exe`.

- [ ] **Step 2: Tulis tes yang gagal `tests/Feature/Auth/PatientPasswordResetTest.php` (tool Write)**

```php
<?php

namespace Tests\Feature\Auth;

use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Reset password pasien (#25, #26): V-04, V-05, F5-R15 sampai F5-R17, BR-148. Hanya akun pasien aktif yang menerima
 * tautan; respons permintaan selalu identik; tautan tidak pernah tampil di halaman; token sekali pakai, berlaku 60 menit.
 */
class PatientPasswordResetTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const EMAIL = 'pasien@contoh.test';

    private const NEW_PASSWORD = 'PasswordBaru123';

    private const TOKEN_ERROR = 'Tautan pengaturan ulang password tidak valid atau sudah kedaluwarsa. Silakan ajukan permintaan baru.';

    private const THROTTLE_PATTERN = '/^Terlalu banyak percobaan\. Silakan coba lagi dalam \d+ detik\.$/';

    // --- pembantu ----------------------------------------------------------------------------

    /** @param  array<string, mixed>  $attributes */
    private function patient(array $attributes = []): User
    {
        return $this->createPatient($attributes + ['email' => self::EMAIL]);
    }

    private function postForgot(string $email): TestResponse
    {
        return $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $email]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function postReset(string $token, array $overrides = []): TestResponse
    {
        return $this->from($this->resetUrl($token))
            ->post(route('password.update'), $overrides + [
                'token' => $token,
                'email' => self::EMAIL,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ]);
    }

    private function resetUrl(string $token): string
    {
        return route('password.reset', ['token' => $token, 'email' => self::EMAIL]);
    }

    /** Meminta tautan dengan notifikasi palsu lalu mengembalikan token dari notifikasi (menghitung 1 permintaan limiter). */
    private function requestToken(User $user): string
    {
        Notification::fake();
        $this->postForgot($user->email)->assertRedirect(route('password.request'));

        return $this->capturedToken($user);
    }

    private function capturedToken(User $user): string
    {
        $token = null;

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->assertIsString($token);

        return $token;
    }

    // --- halaman ----------------------------------------------------------------------------

    public function test_forgot_password_page_renders_the_form_for_guests(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk()
            ->assertSee('Lupa Password')
            ->assertSee('Masukkan email akun Anda. Kami akan mengirim tautan untuk mengatur ulang password jika email terdaftar.')
            ->assertSee('action="'.route('password.email').'"', false)
            ->assertSee('name="email"', false)
            ->assertSee('autocomplete="username"', false)
            ->assertSee('Kirim Tautan')
            ->assertSee('<a href="'.route('login').'">Kembali ke halaman masuk</a>', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertDontSee('name="password"', false);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_login_page_links_to_the_forgot_password_page(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<a href="'.route('password.request').'">Lupa password?</a>', false);
    }

    public function test_reset_form_prefills_a_readonly_email_and_hides_the_token(): void
    {
        $response = $this->get(route('password.reset', ['token' => 'abc123', 'email' => ' Pasien@Contoh.TEST ']));

        $response->assertOk()
            ->assertSee('<title>Reset Password', false)
            ->assertSee('<h1 class="h3 mb-1">Atur Ulang Password</h1>', false)
            ->assertSee('Buat password baru untuk akun Anda.')
            ->assertSee('action="'.route('password.update').'"', false)
            ->assertSee('<input type="hidden" name="token" value="abc123">', false)
            ->assertSee('value="pasien@contoh.test"', false)
            ->assertSee('Password baru')
            ->assertSee('Konfirmasi password baru')
            ->assertSee('Simpan Password Baru')
            ->assertSee('<a href="'.route('password.request').'">Minta tautan baru</a>', false);
        $this->assertMatchesRegularExpression('/<input[^>]*name="email"[^>]*\sreadonly[="\s>]/', $response->getContent());
        $this->assertMatchesRegularExpression('/<input[^>]*name="email"[^>]*autocomplete="username"/', $response->getContent());
        $this->assertSame(2, substr_count($response->getContent(), 'autocomplete="new-password"'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_reset_form_without_a_usable_email_leaves_the_field_editable(): void
    {
        foreach (['/reset-password/abc123', '/reset-password/abc123?email[]=x', '/reset-password/abc123?email='] as $url) {
            $response = $this->get($url);

            $response->assertOk();
            $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="email"[^>]*\sreadonly/', $response->getContent());
        }
    }

    public function test_the_token_in_the_reset_form_is_escaped(): void
    {
        $this->get(route('password.reset', ['token' => 'a"b']))
            ->assertOk()
            ->assertSee('<input type="hidden" name="token" value="a&quot;b">', false);
    }

    public function test_logged_in_accounts_are_redirected_away_from_the_reset_pages(): void
    {
        $patient = $this->patient();
        $staff = $this->createStaff('content-admin');

        foreach ([route('password.request'), route('password.reset', ['token' => 'x'])] as $url) {
            $this->actingAs($patient)->get($url)->assertRedirect(route('patient.dashboard'));
            $this->actingAs($staff)->get($url)->assertRedirect(route('admin.dashboard'));
        }

        Notification::fake();
        $this->actingAs($patient)->post(route('password.email'), ['email' => self::EMAIL])
            ->assertRedirect(route('patient.dashboard'));
        Notification::assertNothingSent();
    }

    public function test_routes_have_the_documented_uris_methods_and_middleware(): void
    {
        $expected = [
            'password.request' => ['GET', 'lupa-password', false],
            'password.email' => ['POST', 'lupa-password', true],
            'password.reset' => ['GET', 'reset-password/{token}', false],
            'password.update' => ['POST', 'reset-password', true],
        ];

        foreach ($expected as $name => [$method, $uri, $throttled]) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} belum ada.");
            $this->assertContains($method, $route->methods());
            $this->assertSame($uri, $route->uri());
            $this->assertContains('guest', $route->gatherMiddleware(), "{$name} harus memakai guest.");
            $this->assertSame($throttled, in_array('throttle:password-reset', $route->gatherMiddleware(), true), "{$name}: throttle password-reset.");
        }
    }

    public function test_broker_configuration_matches_the_documented_values(): void
    {
        $this->assertSame(60, config('auth.passwords.users.expire'));
        $this->assertSame(60, config('auth.passwords.users.throttle'));
        $this->assertSame('password_reset_tokens', config('auth.passwords.users.table'));
    }

    // --- V-04: permintaan tautan ------------------------------------------------------------

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidForgotPayloads(): array
    {
        return [
            'email kosong' => [['email' => ''], 'Email wajib diisi.'],
            'email tidak valid' => [['email' => 'bukan-email'], 'Format email tidak valid.'],
            'email terlalu panjang' => [['email' => str_repeat('a', 250).'@x.test'], 'Email maksimal 255 karakter.'],
            'email larik' => [['email' => ['x@contoh.test']], 'Format email tidak valid.'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    #[DataProvider('invalidForgotPayloads')]
    public function test_forgot_request_validates_the_email_with_the_documented_messages(array $payload, string $message): void
    {
        Notification::fake();
        $this->patient();

        $this->from(route('password.request'))
            ->post(route('password.email'), $payload)
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors(['email' => $message]);

        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    // --- NTF-05 / SEK-16: respons generik ---------------------------------------------------

    public function test_registered_unknown_staff_and_inactive_emails_get_identical_responses(): void
    {
        Notification::fake();
        $patient = $this->patient();
        $inactive = $this->createPatient(['email' => 'nonaktif@contoh.test', 'is_active' => false]);
        $staff = $this->createStaff('registration-officer', ['email' => 'petugas@contoh.test']);

        $snapshots = [];

        foreach ([self::EMAIL, 'tidak-ada@contoh.test', 'petugas@contoh.test', 'nonaktif@contoh.test'] as $email) {
            $response = $this->postForgot($email);

            $response->assertRedirect(route('password.request'))
                ->assertSessionHas('success', 'Jika email terdaftar, tautan untuk mengatur ulang password telah dikirim. Periksa kotak masuk Anda.')
                ->assertSessionHasNoErrors();
            $snapshots[$email] = [$response->getStatusCode(), $response->headers->get('Location')];
        }

        $this->assertCount(1, array_unique(array_map('serialize', $snapshots)), 'Status dan Location harus identik untuk keempat email.');

        // Hanya pasien aktif yang menerima tautan dan memiliki baris token.
        Notification::assertSentTo($patient, ResetPassword::class);
        Notification::assertNotSentTo($staff, ResetPassword::class);
        Notification::assertNotSentTo($inactive, ResetPassword::class);
        Notification::assertSentTimes(ResetPassword::class, 1);
        $this->assertSame([self::EMAIL], DB::table('password_reset_tokens')->pluck('email')->all());
    }

    public function test_a_second_request_within_sixty_seconds_looks_identical_and_sends_nothing_new(): void
    {
        Notification::fake();
        $patient = $this->patient();

        $first = $this->postForgot(self::EMAIL);
        $firstToken = DB::table('password_reset_tokens')->value('token');

        // Broker mengembalikan RESET_THROTTLED: tidak ada notifikasi baru, token lama tidak diganti.
        $second = $this->postForgot(self::EMAIL);

        $second->assertRedirect(route('password.request'))
            ->assertSessionHas('success', __('passwords.sent'))
            ->assertSessionHasNoErrors();
        $this->assertSame($first->getStatusCode(), $second->getStatusCode());
        $this->assertSame($first->headers->get('Location'), $second->headers->get('Location'));
        Notification::assertSentTimes(ResetPassword::class, 1);
        $this->assertSame($firstToken, DB::table('password_reset_tokens')->value('token'));

        // Sesudah 60 detik permintaan baru diproses lagi (email diketik dengan huruf besar dan spasi).
        $this->travel(61)->seconds();
        $this->postForgot('  PASIEN@Contoh.test ')
            ->assertSessionHas('success', __('passwords.sent'));
        Notification::assertSentTimes(ResetPassword::class, 2);
        Notification::assertSentTo($patient, ResetPassword::class);
    }

    public function test_staff_and_inactive_patients_cannot_complete_a_reset_even_with_a_valid_token(): void
    {
        $staff = $this->createStaff('content-admin', ['email' => 'petugas@contoh.test']);
        $inactive = $this->createPatient(['email' => 'nonaktif@contoh.test', 'is_active' => false]);

        foreach ([$staff, $inactive] as $account) {
            $originalHash = $account->password;
            $token = Password::broker()->createToken($account);

            $this->postReset($token, ['email' => $account->email])
                ->assertSessionHasErrors(['email' => self::TOKEN_ERROR]);

            $this->assertSame($originalHash, $account->fresh()->password, 'Password akun yang tidak berhak tidak boleh berubah.');
        }

        $this->assertSame(0, AuditLog::query()->where('action', 'auth.password_reset')->count());
    }

    public function test_the_reset_link_never_appears_in_a_page_or_in_the_flash(): void
    {
        $patient = $this->patient();
        $token = $this->requestToken($patient);

        $redirect = $this->postForgot(self::EMAIL);
        $page = $this->get(route('password.request'));

        foreach ([$redirect->getContent(), $page->getContent(), (string) json_encode(session()->all())] as $haystack) {
            $this->assertStringNotContainsString($token, $haystack);
            $this->assertStringNotContainsString('/reset-password/', $haystack);
        }

        // Token tersimpan ter-hash.
        $this->assertNotSame($token, DB::table('password_reset_tokens')->value('token'));
    }

    // --- SEK-04: throttle ------------------------------------------------------------------

    public function test_the_fourth_request_within_ten_minutes_is_throttled_with_a_flash_and_retry_after(): void
    {
        Notification::fake();
        $this->patient();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postForgot(self::EMAIL)
                ->assertRedirect(route('password.request'))
                ->assertSessionHas('success', __('passwords.sent'))
                ->assertHeaderMissing('Retry-After');
        }

        $this->postForgot(self::EMAIL)
            ->assertRedirect(route('password.request'))
            ->assertHeader('Retry-After')
            ->assertSessionHas('error', fn ($message) => preg_match(self::THROTTLE_PATTERN, $message) === 1)
            ->assertSessionMissing('success');

        // Kunci tidak membedakan huruf besar/kecil dan spasi; email lain tidak terkena.
        $this->postForgot('  PASIEN@Contoh.TEST ')->assertHeader('Retry-After');
        $this->postForgot('lain@contoh.test')
            ->assertHeaderMissing('Retry-After')
            ->assertSessionHas('success', __('passwords.sent'));
    }

    public function test_password_update_shares_the_throttle_bucket_with_the_reset_request(): void
    {
        Notification::fake();
        $this->patient();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postForgot(self::EMAIL)->assertHeaderMissing('Retry-After');
        }

        $this->postReset('token-apa-saja')
            ->assertRedirect($this->resetUrl('token-apa-saja'))
            ->assertHeader('Retry-After')
            ->assertSessionHas('error', fn ($message) => preg_match(self::THROTTLE_PATTERN, $message) === 1);
    }

    public function test_a_missing_csrf_token_on_the_reset_request_redirects_back_with_a_session_expired_message(): void
    {
        $this->app['env'] = 'local'; // mengaktifkan kembali ValidateCsrfToken (dilewati saat env `testing`)

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => self::EMAIL])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('error', 'Sesi formulir Anda telah berakhir. Silakan periksa isian Anda lalu kirim ulang.');
    }

    // --- NTF-04: alur reset penuh -----------------------------------------------------------

    public function test_a_patient_can_reset_the_password_with_the_emailed_token_and_the_token_is_single_use(): void
    {
        $patient = $this->patient();
        $token = $this->requestToken($patient);

        $this->get($this->resetUrl($token))->assertOk();

        $this->postReset($token)
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Password berhasil diubah. Silakan masuk dengan password baru.');
        $this->assertGuest();

        $fresh = $patient->fresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));
        $this->assertFalse(Hash::check($this->patientPassword(), $fresh->password));
        $this->assertStringStartsWith('$2y$', DB::table('users')->where('id', $patient->id)->value('password'));
        $this->assertSame(0, DB::table('password_reset_tokens')->count(), 'Token dihapus setelah dipakai.');

        // Pemakaian ulang token yang sama gagal dengan pesan token (galat pada field email).
        $this->postReset($token, ['password' => 'LainLagi12345', 'password_confirmation' => 'LainLagi12345'])
            ->assertRedirect($this->resetUrl($token))
            ->assertSessionHasErrors(['email' => self::TOKEN_ERROR]);
        $this->assertFalse(Hash::check('LainLagi12345', $patient->fresh()->password));

        // Password baru berlaku untuk login, password lama tidak.
        $this->post(route('login.store'), ['email' => self::EMAIL, 'password' => self::NEW_PASSWORD])
            ->assertRedirect(route('patient.dashboard'));
        $this->assertAuthenticatedAs($patient);
    }

    public function test_the_old_password_no_longer_logs_in_after_a_reset(): void
    {
        $patient = $this->patient();
        $token = $this->requestToken($patient);
        $this->postReset($token)->assertRedirect(route('login'));

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => self::EMAIL, 'password' => $this->patientPassword()])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);
        $this->assertGuest();
    }

    public function test_the_token_expires_after_sixty_minutes(): void
    {
        $patient = $this->patient();
        $token = $this->requestToken($patient);
        $originalHash = $patient->password;

        $this->travel(61)->minutes();

        $this->postReset($token)
            ->assertRedirect($this->resetUrl($token))
            ->assertSessionHasErrors(['email' => self::TOKEN_ERROR]);
        $this->assertSame($originalHash, $patient->fresh()->password);
        $this->assertSame(0, AuditLog::query()->where('action', 'auth.password_reset')->count());
    }

    public function test_the_token_is_still_valid_at_minute_fifty_nine(): void
    {
        $patient = $this->patient();
        $token = $this->requestToken($patient);

        $this->travel(59)->minutes();

        $this->postReset($token)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $patient->fresh()->password));
    }

    public function test_a_wrong_token_or_a_foreign_email_gives_the_same_error_and_changes_nothing(): void
    {
        $patient = $this->patient();
        $other = $this->createPatient(['email' => 'lain@contoh.test']);
        $token = $this->requestToken($patient);
        $originalHash = $patient->password;

        $this->postReset('token-salah')
            ->assertSessionHasErrors(['email' => self::TOKEN_ERROR]);

        // Token milik pasien lain dipakai dengan email pasien lain: tidak cocok.
        $this->postReset($token, ['email' => 'lain@contoh.test'])
            ->assertSessionHasErrors(['email' => self::TOKEN_ERROR]);

        $this->assertSame($originalHash, $patient->fresh()->password);
        $this->assertSame($other->password, $other->fresh()->password);
        $this->assertSame(1, DB::table('password_reset_tokens')->count(), 'Token yang sah tetap berlaku.');
    }

    public function test_the_email_is_normalised_before_the_reset_is_checked(): void
    {
        $patient = $this->patient();
        $token = $this->requestToken($patient);

        $this->postReset($token, ['email' => '  PASIEN@Contoh.TEST '])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $patient->fresh()->password));
    }

    // --- V-05: validasi form reset ----------------------------------------------------------

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidResetPayloads(): array
    {
        $ok = ['token' => 'tok', 'email' => self::EMAIL, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD];
        $tokenMessage = 'Tautan pengaturan ulang password tidak valid. Silakan ajukan permintaan baru.';

        return [
            'token kosong' => [['token' => ''] + $ok, 'token', $tokenMessage],
            'token larik' => [['token' => ['x']] + $ok, 'token', $tokenMessage],
            'email kosong' => [['email' => ''] + $ok, 'email', 'Email wajib diisi.'],
            'email tidak valid' => [['email' => 'bukan-email'] + $ok, 'email', 'Format email tidak valid.'],
            'email larik' => [['email' => ['x']] + $ok, 'email', 'Format email tidak valid.'],
            'email terlalu panjang' => [['email' => str_repeat('a', 250).'@x.test'] + $ok, 'email', 'Email maksimal 255 karakter.'],
            'password kosong' => [['password' => ''] + $ok, 'password', 'Password wajib diisi.'],
            'password terlalu pendek' => [['password' => 'Ab1', 'password_confirmation' => 'Ab1'] + $ok, 'password', 'Password minimal 8 karakter.'],
            'password tanpa angka' => [['password' => 'Abcdefghij', 'password_confirmation' => 'Abcdefghij'] + $ok, 'password', 'Password harus mengandung angka.'],
            'password tanpa huruf' => [['password' => '1234567890', 'password_confirmation' => '1234567890'] + $ok, 'password', 'Password harus mengandung huruf.'],
            'password terlalu panjang' => [['password' => str_repeat('Aa1', 21).'AB', 'password_confirmation' => str_repeat('Aa1', 21).'AB'] + $ok, 'password', 'Password maksimal 64 karakter.'],
            'konfirmasi tidak cocok' => [['password_confirmation' => 'Lain12345'] + $ok, 'password', 'Konfirmasi password tidak cocok.'],
            'konfirmasi kosong' => [['password_confirmation' => ''] + $ok, 'password_confirmation', 'Konfirmasi password wajib diisi.'],
            'password larik' => [['password' => ['x'], 'password_confirmation' => ['x']] + $ok, 'password', 'Password tidak valid.'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    #[DataProvider('invalidResetPayloads')]
    public function test_reset_form_rejects_invalid_input_with_the_documented_messages_and_never_a_server_error(array $payload, string $field, string $message): void
    {
        $patient = $this->patient();
        $originalHash = $patient->password;

        $this->from($this->resetUrl('tok'))
            ->post(route('password.update'), $payload)
            ->assertRedirect($this->resetUrl('tok'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame($originalHash, $patient->fresh()->password);
        $this->assertSame(0, AuditLog::query()->where('action', 'auth.password_reset')->count());
    }

    public function test_the_email_is_kept_in_the_form_after_a_failed_reset_but_passwords_are_not(): void
    {
        $patient = $this->patient();
        $token = $this->requestToken($patient);

        $this->postReset($token, ['password' => 'Ab1', 'password_confirmation' => 'Ab1'])
            ->assertSessionHasErrors('password');

        $this->assertNull(session('_old_input.password'));
        $this->assertNull(session('_old_input.password_confirmation'));

        $this->get($this->resetUrl($token))
            ->assertOk()
            ->assertSee('Password minimal 8 karakter.')
            ->assertSee('value="pasien@contoh.test"', false);
    }

    // --- efek reset: sesi, remember_token, audit (SEK-11, G.4) ------------------------------

    public function test_a_reset_deletes_every_session_of_the_account_and_rotates_the_remember_token(): void
    {
        $patient = $this->patient();
        $other = $this->createPatient(['email' => 'lain@contoh.test']);
        $rememberBefore = $patient->remember_token;

        $row = fn (string $id, ?int $userId): array => [
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'uji',
            'payload' => 'x',
            'last_activity' => time(),
        ];
        DB::table('sessions')->insert([
            $row('sesi-pasien-1', $patient->id),
            $row('sesi-pasien-2', $patient->id),
            $row('sesi-akun-lain', $other->id),
            $row('sesi-tamu', null),
        ]);

        $token = $this->requestToken($patient);
        $this->postReset($token)->assertRedirect(route('login'));

        $this->assertSame(0, DB::table('sessions')->where('user_id', $patient->id)->count());
        $this->assertEqualsCanonicalizing(['sesi-akun-lain', 'sesi-tamu'], DB::table('sessions')->pluck('id')->all());

        $rememberAfter = $patient->fresh()->remember_token;
        $this->assertNotSame($rememberBefore, $rememberAfter);
        $this->assertSame(60, strlen((string) $rememberAfter));
    }

    public function test_the_reset_is_audited_without_the_token_or_any_password(): void
    {
        $patient = $this->patient();
        $token = $this->requestToken($patient);

        $this->postReset($token)->assertRedirect(route('login'));

        $log = AuditLog::query()->where('action', 'auth.password_reset')->sole();
        $this->assertSame(ActorType::Patient, $log->actor_type);
        $this->assertEquals($patient->id, $log->user_id);
        $this->assertEquals($patient->id, $log->auditable_id);
        $this->assertSame(User::class, $log->auditable_type);
        $this->assertNull($log->old_values);
        $this->assertNull($log->new_values);
        $this->assertSame(
            'Password akun pasien '.$patient->patientProfile->patient_number.' diatur ulang lewat tautan email.',
            $log->description,
        );

        foreach ([$token, self::NEW_PASSWORD, $this->patientPassword(), self::EMAIL] as $secret) {
            $this->assertSame(0, DB::table('audit_logs')
                ->where('old_values', 'like', "%{$secret}%")
                ->orWhere('new_values', 'like', "%{$secret}%")
                ->orWhere('description', 'like', "%{$secret}%")
                ->count(), 'Token, password, dan email tidak boleh ada di audit_logs.');
        }
    }

    // --- email reset berbahasa Indonesia (F5-R17) ------------------------------------------

    public function test_the_reset_mail_is_sent_in_indonesian_with_a_link_to_the_reset_form(): void
    {
        $this->patient(['name' => 'Siti Rahma']);

        $this->postForgot(self::EMAIL)->assertSessionHas('success', __('passwords.sent'));

        $messages = app('mail.manager')->mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);

        $email = $messages->first()->getOriginalMessage();
        $this->assertSame('Atur Ulang Password', $email->getSubject());
        $this->assertSame(self::EMAIL, $email->getTo()[0]->getAddress());

        $html = (string) $email->getHtmlBody();

        foreach ([
            'Halo!',
            'Anda menerima email ini karena ada permintaan untuk mengatur ulang password akun Anda.',
            'Atur Ulang Password',
            'Tautan ini berlaku selama 60 menit.',
            'Jika Anda tidak meminta pengaturan ulang password, abaikan email ini.',
            'Salam,',
            '/reset-password/',
            'email=pasien%40contoh.test',
        ] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }

        foreach (['Hello!', 'Regards,', 'Reset Password', 'You are receiving this email', 'Whoops!'] as $english) {
            $this->assertStringNotContainsString($english, $html, "Teks Inggris '{$english}' tidak boleh muncul di email.");
        }
    }

    public function test_the_notification_url_points_at_the_reset_form_with_token_and_email(): void
    {
        $patient = $this->patient();
        $url = (new ResetPassword('tok123'))->toMail($patient)->actionUrl;

        $this->assertSame(url('/reset-password/tok123?email=pasien%40contoh.test'), $url);
    }
}
```

- [ ] **Step 3: Perluas `LocalizationTest` (tool Edit pada `tests/Feature/LocalizationTest.php`)**

`old_string`:

```php
    public function test_auth_and_password_messages_are_generic_indonesian(): void
    {
        $this->assertSame('Email atau password salah.', __('auth.failed'));
        $this->assertSame(__('passwords.sent'), __('passwords.user'));
    }
```

`new_string`:

```php
    public function test_auth_and_password_messages_are_generic_indonesian(): void
    {
        $this->assertSame('Email atau password salah.', __('auth.failed'));
        $this->assertSame(__('passwords.sent'), __('passwords.user'));

        // F5-R16: throttle broker (60 detik) sengaja sama dengan `sent` agar tidak membocorkan email terdaftar.
        $this->assertSame(__('passwords.sent'), __('passwords.throttled'));
        $this->assertSame('Jika email terdaftar, tautan untuk mengatur ulang password telah dikirim. Periksa kotak masuk Anda.', __('passwords.sent'));
        $this->assertSame('Password berhasil diubah. Silakan masuk dengan password baru.', __('passwords.reset'));
        $this->assertSame('Tautan pengaturan ulang password tidak valid atau sudah kedaluwarsa. Silakan ajukan permintaan baru.', __('passwords.token'));
    }

    public function test_json_translations_cover_the_password_reset_notification_and_the_mail_template(): void
    {
        $raw = (string) file_get_contents(lang_path('id.json'));
        $this->assertStringEndsWith("}\n", $raw);

        /** @var array<string, string> $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        // F5-R17: T2 menerjemahkan templat mail bawaan, T5 menambah teks notifikasi reset password.
        $expected = [
            'Hello!' => 'Halo!',
            'Whoops!' => 'Maaf!',
            'Regards,' => 'Salam,',
            'All rights reserved.' => 'Hak cipta dilindungi.',
            'Reset Password Notification' => 'Atur Ulang Password',
            'You are receiving this email because we received a password reset request for your account.' => 'Anda menerima email ini karena ada permintaan untuk mengatur ulang password akun Anda.',
            'Reset Password' => 'Atur Ulang Password',
            'This password reset link will expire in :count minutes.' => 'Tautan ini berlaku selama :count menit.',
            'If you did not request a password reset, no further action is required.' => 'Jika Anda tidak meminta pengaturan ulang password, abaikan email ini.',
        ];

        foreach ($expected as $key => $value) {
            $this->assertSame($value, $decoded[$key] ?? null, "Kunci '{$key}' di lang/id.json.");
            $this->assertSame(str_replace(':count', '60', $value), __($key, ['count' => 60]));
        }
    }
```

- [ ] **Step 4: Jalankan tes, pastikan GAGAL (RED)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter='PatientPasswordResetTest|LocalizationTest'
```
Expected: FAIL/ERROR. `PatientPasswordResetTest`: hampir semua tes ERROR dengan `Symfony\Component\Routing\Exception\RouteNotFoundException: Route [password.request] not defined.` (atau `Route [password.email] not defined.`); `test_routes_have_the_documented_...` gagal dengan "Route password.request belum ada."; `test_broker_configuration_...` PASS. `LocalizationTest::test_auth_and_password_messages_are_generic_indonesian` gagal pada `assertSame(__('passwords.sent'), __('passwords.throttled'))` ("Mohon tunggu sebelum mencoba lagi." vs teks `sent`), dan `test_json_translations_cover_...` gagal pada kunci `Reset Password Notification` (bernilai `null`).

- [ ] **Step 5: Buat Form Request V-04 `app/Http/Requests/Auth/ForgotPasswordRequest.php` (tool Write)**

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * V-04: permintaan tautan reset password pasien. Hanya format email yang divalidasi; email tak terdaftar, akun staf,
 * dan akun nonaktif TIDAK menghasilkan galat (respons selalu generik, BR-148). Email dinormalisasi (trim + huruf kecil)
 * sebelum validasi; nilai larik dibiarkan agar ditolak sebagai "Format email tidak valid." (bukan 500).
 */
class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['bail', 'required', 'string', 'max:255', 'email:rfc'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.string' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.email' => 'Format email tidak valid.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'Email',
        ];
    }
}
```

- [ ] **Step 6: Buat Form Request V-05 `app/Http/Requests/Auth/ResetPasswordRequest.php` (tool Write)**

```php
<?php

namespace App\Http\Requests\Auth;

use App\Rules\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * V-05: pengaturan ulang password dengan token. Kegagalan token/email tidak cocok BUKAN galat validasi: ia diputuskan
 * broker dan dijawab dengan satu pesan (`passwords.token`) oleh controller. Nilai larik pada field mana pun menghasilkan
 * galat berbahasa Indonesia, bukan 500.
 */
class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'token' => ['bail', 'required', 'string'],
            'email' => ['bail', 'required', 'string', 'max:255', 'email:rfc'],
            'password' => PasswordRules::default(),
            'password_confirmation' => ['required'],
        ];
    }

    /**
     * Kunci `password.password.letters`/`numbers` dipakai oleh validator bersarang aturan Password (pola Admin\UpdatePasswordRequest).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Tautan pengaturan ulang password tidak valid. Silakan ajukan permintaan baru.',
            'token.string' => 'Tautan pengaturan ulang password tidak valid. Silakan ajukan permintaan baru.',
            'email.required' => 'Email wajib diisi.',
            'email.string' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.max' => 'Password maksimal 64 karakter.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.string' => 'Password tidak valid.',
            'password.password.letters' => 'Password harus mengandung huruf.',
            'password.password.numbers' => 'Password harus mengandung angka.',
            'password_confirmation.required' => 'Konfirmasi password wajib diisi.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'token' => 'Token',
            'email' => 'Email',
            'password' => 'Password',
            'password_confirmation' => 'Konfirmasi password',
        ];
    }
}
```

- [ ] **Step 7: Buat controller permintaan tautan `app/Http/Controllers/Auth/PasswordResetLinkController.php` (tool Write)**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

/**
 * Permintaan tautan reset password pasien (#25, F5-R15). Kredensial tambahan `account_type`/`is_active` diteruskan ke
 * `EloquentUserProvider::retrieveByCredentials()` sebagai klausa `where`, sehingga staf dan akun nonaktif diperlakukan
 * sama dengan email tak terdaftar. Status broker DIABAIKAN: apa pun hasilnya (`RESET_LINK_SENT`, `INVALID_USER`,
 * `RESET_THROTTLED`) jawabannya identik (anti-enumerasi, NTF-05, SEK-16). Tautan hanya dikirim lewat email.
 */
class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        try {
            Password::broker()->sendResetLink([
                'email' => $request->validated('email'),
                'account_type' => AccountType::Patient->value,
                'is_active' => true,
            ]);
        } catch (Throwable $e) {
            // Kegagalan kirim email tidak boleh membedakan respons (dan tidak boleh menjadi 500).
            report($e);
        }

        return redirect()->route('password.request')->with('success', __('passwords.sent'));
    }
}
```

- [ ] **Step 8: Buat controller form reset `app/Http/Controllers/Auth/NewPasswordController.php` (tool Write)**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Form dan pemrosesan reset password pasien (#26, F5-R15). Callback broker (dalam satu transaksi): menyimpan password
 * (cast `hashed` pada User meng-hash; JANGAN `Hash::make`), mengganti `remember_token`, menghapus SEMUA baris `sessions`
 * milik akun (BR-148), dan mencatat audit `auth.password_reset` tanpa token/password. Tidak ada login otomatis (K-11).
 */
class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        $email = $request->query('email');

        // `?email[]=x` menghasilkan larik: dianggap kosong (bukan 500).
        $email = is_string($email) ? Str::lower(trim($email)) : '';

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function store(ResetPasswordRequest $request, AuditLogger $audit): RedirectResponse
    {
        $status = Password::broker()->reset(
            [
                'email' => $request->validated('email'),
                'token' => $request->validated('token'),
                'password' => $request->validated('password'),
                'password_confirmation' => $request->validated('password_confirmation'),
                'account_type' => AccountType::Patient->value,
                'is_active' => true,
            ],
            function (User $user, string $password) use ($audit): void {
                DB::transaction(function () use ($user, $password, $audit): void {
                    $user->forceFill([
                        'password' => $password,
                        'remember_token' => Str::random(60),
                    ])->save();

                    DB::table('sessions')->where('user_id', $user->id)->delete();

                    $number = $user->patientProfile?->patient_number ?? '-';

                    $audit->log(
                        'auth.password_reset',
                        $user,
                        null,
                        null,
                        "Password akun pasien {$number} diatur ulang lewat tautan email.",
                        Actor::patient($user),
                    );
                });
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', __('passwords.reset'));
        }

        // Token salah/kedaluwarsa, email tidak cocok, staf, atau nonaktif: satu pesan yang sama (tanpa membedakan).
        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __('passwords.token')]);
    }
}
```

- [ ] **Step 9: Buat view `resources/views/auth/forgot-password.blade.php` (tool Write)**

```blade
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
```

- [ ] **Step 10: Buat view `resources/views/auth/reset-password.blade.php` (tool Write)**

Field `username` tidak diperlukan: field email sudah ber-`autocomplete="username"` sehingga pengelola password peramban memasangkannya dengan password baru.

```blade
<x-layouts.auth title="Reset Password" heading="Atur Ulang Password" subtitle="Buat password baru untuk akun Anda.">
    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <x-form.input name="email" label="Email" type="email" required autocomplete="username" inputmode="email" :value="$email" :readonly="$email !== ''" :autofocus="$email === '' && ! $errors->any()" />
        <x-form.input name="password" label="Password baru" type="password" required autocomplete="new-password" help="Minimal 8 karakter, berisi huruf dan angka." :autofocus="$email !== '' && ! $errors->any()" />
        <x-form.input name="password_confirmation" label="Konfirmasi password baru" type="password" required autocomplete="new-password" />

        <x-loading-button class="w-100 mt-2" loading-text="Menyimpan…">Simpan Password Baru</x-loading-button>
    </form>

    <x-slot:footer>
        <a href="{{ route('password.request') }}">Minta tautan baru</a>
    </x-slot:footer>
</x-layouts.auth>
```

- [ ] **Step 11: Tambahkan tautan "Lupa password?" di login (tool Edit pada `resources/views/auth/login.blade.php`)**

`old_string` (substring unik; indentasi di depannya dibiarkan):

```blade
<x-form.checkbox name="remember"
```

`new_string`:

```blade
<p class="small mb-3"><a href="{{ route('password.request') }}">Lupa password?</a></p>

        <x-form.checkbox name="remember"
```

Hasil akhir yang diharapkan di berkas: tepat di bawah `<x-form.input name="password" … />` dan di atas checkbox "Ingat saya":

```blade
        <x-form.input name="password" label="Password" type="password" required autocomplete="current-password" />
        <p class="small mb-3"><a href="{{ route('password.request') }}">Lupa password?</a></p>

        <x-form.checkbox name="remember" label="Ingat saya" />
```

Baca berkas sesudah Edit. Bila di antara baris password dan checkbox ada baris kosong lain dari T3/T4, rapikan agar hasilnya sesuai blok di atas (satu baris kosong sebelum `<p>` tidak wajib; markup `<p>` dan posisinya adalah yang dikunci).

- [ ] **Step 12: Daftarkan route (tool Edit pada `routes/web.php`)**

Dua pengeditan. Anchor memakai route `logout` yang menurut F5-R3 berada di luar grup `guest` (tingkat atas berkas), jadi blok baru sendiri berdiri di tingkat atas dengan grup `guest` miliknya.

1. Import. `old_string`:

```php
use App\Http\Controllers\Auth\LoginController;
```

`new_string`:

```php
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
```

2. Route. `old_string` (substring tunggal; ada tepat satu di `routes/web.php`):

```php
->name('logout');
```

`new_string`:

```php
->name('logout');

// Reset password pasien (#25, #26): permintaan tautan dan form reset. `password.email` dan `password.update` berbagi
// limiter `password-reset` (3 per 10 menit per email+IP; F5-R15, D-23). Tautan hanya dikirim lewat email.
Route::middleware('guest')->group(function (): void {
    Route::get('/lupa-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/lupa-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.update');
});
```

Bila `old_string` tidak cocok tepat satu kali (mis. T3 menulis route `logout` dengan nama berbeda), baca `routes/web.php`, letakkan blok yang sama di tingkat atas SESUDAH route `logout` dan tambahkan dua `use`; jangan mengubah route T3/T4. Pint (Step 14) merapikan urutan `use`.

- [ ] **Step 13: Ganti teks `lang/id/passwords.php` (tool Write, berkas utuh)**

```php
<?php

// Teks reset password pasien (F5-R16, 12 V-04/V-05).
// 'sent', 'user', dan 'throttled' SENGAJA sama: respons permintaan tautan tidak boleh membedakan email terdaftar,
// tidak terdaftar, staf, nonaktif, atau permintaan kedua dalam 60 detik (throttle broker `config('auth.passwords.users.throttle')`).
// Controller juga mengabaikan status broker; baris ini adalah lapis kedua (anti-enumerasi, bawaan fase 0).

return [

    'reset' => 'Password berhasil diubah. Silakan masuk dengan password baru.',
    'sent' => 'Jika email terdaftar, tautan untuk mengatur ulang password telah dikirim. Periksa kotak masuk Anda.',
    'throttled' => 'Jika email terdaftar, tautan untuk mengatur ulang password telah dikirim. Periksa kotak masuk Anda.',
    'token' => 'Tautan pengaturan ulang password tidak valid atau sudah kedaluwarsa. Silakan ajukan permintaan baru.',
    'user' => 'Jika email terdaftar, tautan untuk mengatur ulang password telah dikirim. Periksa kotak masuk Anda.',

];
```

- [ ] **Step 14: Tambah kunci notifikasi reset ke `lang/id.json` (tool Edit)**

`old_string` (kunci terakhir dari T2; tanpa koma di akhir):

```json
"All rights reserved.": "Hak cipta dilindungi."
```

`new_string` (indentasi 4 spasi, urutan sesuai F5-R17, kunci terakhir tanpa koma):

```json
"All rights reserved.": "Hak cipta dilindungi.",
    "Reset Password Notification": "Atur Ulang Password",
    "You are receiving this email because we received a password reset request for your account.": "Anda menerima email ini karena ada permintaan untuk mengatur ulang password akun Anda.",
    "Reset Password": "Atur Ulang Password",
    "This password reset link will expire in :count minutes.": "Tautan ini berlaku selama :count menit.",
    "If you did not request a password reset, no further action is required.": "Jika Anda tidak meminta pengaturan ulang password, abaikan email ini."
```

Baca `lang/id.json` sesudahnya: harus JSON valid, indentasi 4 spasi, sepuluh kunci, diakhiri `}` dan satu baris baru (tes memeriksa `}\n`). Verifikasi sintaks:

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP -r "var_dump(count(json_decode(file_get_contents('lang/id.json'), true, 512, JSON_THROW_ON_ERROR)));"
```
Expected: `int(10)`.

- [ ] **Step 15: Jalankan tes, pastikan LULUS (GREEN)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter='PatientPasswordResetTest|LocalizationTest'
```
Expected: PASS semua (±55 tes: `PatientPasswordResetTest` ±47, `LocalizationTest` 9 + provider). Bila `test_the_reset_mail_is_sent_in_indonesian...` gagal pada teks Inggris yang tersisa ("Hello!", "Regards,"), kunci templat T2 di `lang/id.json` belum benar; periksa T2, bukan task ini. Bila satu tes gagal karena jumlah permintaan limiter (3 per 10 menit) terlampaui, hitung ulang permintaan dalam tes itu: tiap `postForgot`/`postReset`/`requestToken` = 1.

- [ ] **Step 16: Tes terkait dan hitungan route**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter='PatientLoginTest|PatientRegistrationTest|AdminLoginTest|AdminPasswordChangeTest|SecurityHeadersTest|LayoutsTest|GuestRedirectTest|RawOutputScanTest'
(& $PHP artisan route:list --except-vendor --json | ConvertFrom-Json).Count
git grep -n "passwords\.\|password\.request\|password\.reset\|password\.email" -- tests app resources routes
```
Expected: semua PASS; jumlah route `138` (134 + 4); `git grep` hanya menampilkan pemakai baru (controller, view `login`/`forgot-password`/`reset-password`, tes ini, `LocalizationTest`, `routes/web.php`) dan pemakai lama `admin.password.*` yang tidak bersangkutan. Bila tes lama memakai teks `passwords.*` lama, perbarui sesuai teks F5-R16 (tidak diharapkan ada).

- [ ] **Step 17: Pint**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint app\Http\Requests\Auth app\Http\Controllers\Auth routes\web.php lang\id\passwords.php tests\Feature\Auth\PatientPasswordResetTest.php tests\Feature\LocalizationTest.php
& $PHP vendor\bin\pint --test
```
Expected: pint pertama merapikan bila perlu (urutan `use`); `pint --test` kedua: `PASS`/tanpa temuan. Bila pint mengubah berkas, jalankan ulang Step 15 sebelum commit.

- [ ] **Step 18: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add app/Http/Requests/Auth/ForgotPasswordRequest.php app/Http/Requests/Auth/ResetPasswordRequest.php app/Http/Controllers/Auth/PasswordResetLinkController.php app/Http/Controllers/Auth/NewPasswordController.php resources/views/auth/forgot-password.blade.php resources/views/auth/reset-password.blade.php resources/views/auth/login.blade.php routes/web.php lang/id/passwords.php lang/id.json tests/Feature/Auth/PatientPasswordResetTest.php tests/Feature/LocalizationTest.php
git commit -m @'
feat(fase-5): reset password pasien (#25, #26), token sekali pakai, respons generik, email berbahasa Indonesia

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
git status --short
```
Expected: satu commit; `git status` bersih (selain `?? .superpowers/`).

#### Catatan penulis (konteks untuk implementer dan reviewer)

- **Anchor pengeditan berkas tugas lain.** Karena T3/T4 ditulis paralel, Step 11 dan 12 memakai anchor yang diturunkan dari kerangka: `<x-form.checkbox name="remember"` (E.1) di `auth/login.blade.php`, `use App\Http\Controllers\Auth\LoginController;` dan `->name('logout');` di `routes/web.php`. Bila penulis T3 menamai/menata berbeda, anchor itu perlu disesuaikan (langkah sudah memuat cadangan: baca berkas, letakkan blok yang sama). Saran koordinator: pastikan task-03 menulis `use App\Http\Controllers\Auth\LoginController;` persis dan route `logout` bernama `logout` pada satu pernyataan tingkat atas.
- **`back()` vs redirect eksplisit pada permintaan tautan.** Kerangka F5-R15 menulis `back()->with(...)`. Controller memakai `redirect()->route('password.request')` (Location identik apa pun asal permintaan; tidak bergantung pada `Referer`). Perilaku untuk peramban sama (form ada di `password.request`); tes SEK-16 membandingkan `Location`.
- **Urutan aturan email `bail, required, string, max:255, email:rfc`** (bukan `required, email:rfc, max:255` seperti urutan di tabel V-04/V-05): pesan dan hasil tetap sama untuk kasus biasa dan menghindari ketergantungan pada perilaku batas panjang bagian lokal `email:rfc`. Pola sama dengan `StoreContactMessageRequest`. T4 dan T6 memakai urutan yang sama untuk field email.
- **Email dari query string dinormalisasi huruf kecil dan di-trim** di `NewPasswordController::create` (kerangka E.4 hanya menyebut "email terisi readonly dari query"). Nilai larik pada `?email[]=` dianggap kosong agar tidak 500.
- **Callback reset dibungkus `DB::transaction`** (tidak disebut kerangka) agar password, penghapusan sesi, dan audit atomik.
- **Dependensi T2:** tes `test_the_reset_mail_is_sent_in_indonesian_...` dan `LocalizationTest::test_json_translations_...` bergantung pada lima kunci T2 di `lang/id.json` (`Hello!`, `Whoops!`, `Regards,`, kunci subcopy, `All rights reserved.`). Tes JSON hanya memeriksa empat kunci T2 yang bernilai tunggal baris (bukan kunci subcopy bermakna `\n`).
- **Kerentanan nyata yang ditemukan (dipakai T6):** `current_password` pada larik menyebabkan `password_verify()` menerima larik (TypeError, 500) karena aturan bawaan tidak memeriksa tipe. `Admin\UpdatePasswordRequest` (fase 1) memiliki celah yang sama (`current_password[]=x`). T6 menutupnya di `Patient\UpdatePasswordRequest` dengan `bail` + `string`; versi staf ditambal di T6 Step 7A.
- **README T8:** keterbatasan "`password.email` dan `password.update` berbagi satu bucket" dan "Timebox 200 ms pada broker tidak menyamakan waktu respons saat pengiriman email sinkron lambat" (hardening fase 9) perlu dicatat.

---

### Task 6: Profil dan ganti password pasien (#28)

**Files:**
- Create: `app/Http/Requests/Patient/UpdatePatientProfileRequest.php`
- Create: `app/Http/Requests/Patient/UpdatePasswordRequest.php`
- Create: `app/Http/Controllers/Patient/ProfileController.php`
- Create: `app/Http/Controllers/Patient/PasswordController.php`
- Create: `resources/views/patient/profile/edit.blade.php`
- Modify: `routes/patient.php` (3 route + 2 `use`)
- Modify: `resources/views/patient/dashboard.blade.php` (tautan "Ubah Profil dan Akun" di kartu "Profil Anda", F5-R22)
- Modify: `resources/views/components/breadcrumb.blade.php` (akar "Portal Pasien" untuk `home="patient.dashboard"`)
- Modify: `app/Http/Requests/Admin/UpdatePasswordRequest.php` (tambalan `current_password` larik, Step 7A)
- Modify (tes): `tests/Feature/Auth/AdminPasswordChangeTest.php` (satu kasus provider, Step 7A)
- Modify (tes): `tests/Feature/View/NavigationComponentsTest.php` (satu tes breadcrumb baru)
- Test: `tests/Feature/Patient/PatientProfileTest.php`
- Test: `tests/Feature/Patient/PatientPasswordChangeTest.php`
- Tidak diubah: `app/Models/*`, `app/Services/Patients/PatientRegistrationService.php` (T2: `updateProfile()` dipakai apa adanya), `config/navigation.php` (menu portal sudah memuat "Profil dan Akun" → `patient.profile.edit`, aktif untuk `patient.profile.*`; item muncul otomatis begitu route ada), `bootstrap/app.php`, `app/Policies/PatientProfilePolicy.php` (T1).

**Interfaces:**
- Consumes:
  - T1: `User::patientProfile()`, `PatientProfile::masked_bpjs` (accessor `maskedBpjs`), hook BPJS last4 (mengosongkan `bpjs_number_last4` bila `bpjs_number` = null), `PatientProfilePolicy` (`view`, `update`: pemilik atau `patients.manage`), `Tests\Support\InteractsWithPatients` (`createPatient`, `patientPassword()` = `Rahasia123`).
  - T2: `App\Services\Patients\PatientRegistrationService::updateProfile(PatientProfile $profile, array $validated, Actor $actor): PatientProfile` (mengisi hanya `full_name`, `birth_date`, `gender`, `phone`, `email`, `address`, opsional `remove_bpjs`; menyinkronkan `users.name/email/phone`; audit `patient.updated` hanya bila ada perubahan).
  - T3: `routes/patient.php` (grup `patient.`), route `patient.dashboard`, `login`, `logout`; `layouts.patient` dengan slot `breadcrumb`; dashboard dengan kartu "Profil Anda"; redirect tamu ke `login`.
  - Fase 1 dan sebelumnya: `App\Rules\PasswordRules`, `PersonName`, `IndonesianPhone`, `AuditLogger`, `Actor::patient()`, `x-form.*`, `x-breadcrumb`, `x-form.errors-summary`.
- Produces:
  - Route: `patient.profile.edit` (GET `/pasien/profil`), `patient.profile.update` (PUT `/pasien/profil`), `patient.password.update` (PUT `/pasien/password`); semuanya di grup G-PASIEN (`auth`, `account:patient`, `active`).
  - `App\Http\Requests\Patient\UpdatePatientProfileRequest` (V-06), `App\Http\Requests\Patient\UpdatePasswordRequest` (V-07).
  - `App\Http\Controllers\Patient\ProfileController` (`edit`, `update`), `App\Http\Controllers\Patient\PasswordController` (`update`).
  - View `patient.profile.edit`; label akar breadcrumb "Portal Pasien".
  - Audit `auth.password_changed` (F5-R24). Audit `patient.updated` diproduksi service T2; di sini dibuktikan lewat HTTP.
  - Kontrak untuk T8: halaman #28 dan mutasi `PUT patient.profile.update`, `PUT patient.password.update` masuk `PatientPageMatrixTest`.

**Test IDs:** PAS-05 (mass-assignment dan profil pasien lain), PAS-09 (sinkron `users`, email bentrok tanpa perubahan parsial), VAL (V-06, V-07), SEK-11 (sisi ubah profil ber-BPJS: nilai tidak tersimpan mentah; sisi ganti password), SEK-12 (BPJS tersamar di halaman, terenkripsi di DB, nilai lengkap tidak pernah dirender), SEK-02 (regenerasi sesi setelah ganti password), SEK-15 (nama pasien di-escape pada halaman profil, sebagian), SEK-01 (password baru tersimpan `$2y$`), EMP-04/F-PAS-02 (tautan dashboard ke profil), AKS-02 (sebagian; matriks penuh = T8), Review Focus G.2 (input larik dan nilai liar tanpa 500, tanpa baris parsial), G.3 (mass-assignment, kepemilikan profil, sinkron email, tanpa perubahan parsial), G.4 (setelah ganti password sesi lain terhapus dan sesi kini tetap).

**Catatan teknis (diverifikasi dari source):**
- **Pola yang ditiru:** `app/Http/Requests/Admin/UpdatePasswordRequest.php` (aturan `current_password` + `PasswordRules::default()` + `different:current_password`, kunci pesan bersarang `password.password.letters`/`numbers`), `app/Http/Controllers/Auth/AdminPasswordController.php` (`forceFill(['password' => …])`, `regenerate()`, audit; cast `hashed` meng-hash, JANGAN `Hash::make`), `tests/Feature/Auth/AdminPasswordChangeTest.php` (provider `invalidPayloads`, audit, `session()->getId()` sebelum/sesudah, `_old_input` tanpa password) dan `StoreContactMessageRequest` (urutan `bail`/`string` di depan agar larik menghasilkan galat Indonesia).
- **BUG yang ditutup di sini (nyata):** aturan `current_password` bawaan memanggil `$hasher->check($value, …)` → `password_verify($value, …)` dan TIDAK memeriksa tipe; `current_password[]=x` menghasilkan `TypeError` (500). Karena itu `Patient\UpdatePasswordRequest` memakai `['bail', 'required', 'string', 'current_password']`. `Admin\UpdatePasswordRequest` (fase 1) memiliki celah yang sama dan ditambal di Step 7A (`bail` + `string`, pesan "Password saat ini tidak valid.", satu kasus provider di `AdminPasswordChangeTest`). Pesan larik seragam di admin dan pasien: "Password saat ini tidak valid." (kunci `current_password.string`).
- **Mode ketat dan relasi.** `Model::shouldBeStrict()` melarang lazy loading hanya pada koleksi hasil `get()`; `$request->user()->patientProfile` pada satu model tidak melanggar (T3 dashboard memakai pola sama). `updateProfile()` T2 menyimpan profil lalu `users` DALAM satu `DB::transaction`; pengecualian unik dari DB melepaskan transaksi sehingga tidak ada perubahan parsial.
- **`UniqueConstraintViolationException`.** `Illuminate\Database\UniqueConstraintViolationException extends QueryException`; konstruktor `($connectionName, $sql, array $bindings, Throwable $previous, array $connectionDetails = [], $readWriteType = null)`. Tes balapan memasang listener `User::saving` yang melempar pengecualian itu: `saving` ditembakkan pada `save()` walau atribut tidak kotor, dan service menyimpan profil SEBELUM `users`, sehingga tes membuktikan rollback profil.
- **`redirect()->withInput()` tidak menyaring `dontFlash`** (hanya handler validasi yang menyaring). Karena itu controller memanggil `withInput($request->only([...tujuh field form profil...]))` secara eksplisit.
- **Audit.** `AuditLogger` meredaksi `address`, `phone`, `email`, `birth_date` untuk aksi berawalan `patient.` (`PII_ACTION_PREFIXES`), dan `bpjs_number` (atribut `$hidden` + cast `encrypted`) untuk semua aksi → `[disamarkan]`; `full_name` dan `gender` boleh bernilai. `diff()` memakai `getChanges()`/`getPrevious()` dan membuang `updated_at`; service T2 membuang `bpjs_number_last4`. Aksi `auth.*` tidak meredaksi PII, tetapi deskripsi dan `old/new` ganti password NULL.
- **Sesi saat ganti password.** Urutan di controller: `forceFill` → `session()->regenerate()` → hapus baris `sessions` milik akun DENGAN `id != session()->getId()` (ID BARU hasil regenerasi). `regenerate()` tanpa argumen tidak menghapus baris lama di tabel `sessions`, sehingga baris sesi lama ikut terhapus oleh query itu, sedangkan baris sesi sekarang (ditulis `StartSession` pada akhir permintaan) tidak. Driver tes `array` tidak menulis baris `sessions`; tes "sesi kini tetap" (`test_the_session_cleanup_spares_the_current_session_id`) memakai `config(['session.driver' => 'database'])` sehingga `StartSession` menulis baris sesi sekarang, lalu memeriksa isi tabel (tanpa ketergantungan bentuk SQL). Catatan cadangan: bila `config(['session.driver' => 'database'])` ternyata tidak berlaku di tes (baris sesi sekarang tidak tertulis), gunakan versi log query: `DB::connection()->enableQueryLog()`, jalankan `putPassword`, saring `DB::getQueryLog()` dengan `str_starts_with(strtolower($entry['query']), 'delete from `sessions`')`, lalu tegaskan satu penghapusan, `assertStringContainsString('`id` != ?', ...)` dan `assertEquals([$user->id, session()->getId()], $bindings)` (rapuh terhadap bentuk SQL grammar; sesuaikan di Step 14 bila perlu). Jangan simpan keduanya sebagai tes.
- **Sesi array antar permintaan.** Atribut sesi array bertahan antar permintaan dalam satu tes (`Store::loadSession` meng-`array_merge`), sehingga old input/flash dari permintaan sebelumnya terbaca (mis. nilai form tetap setelah galat).
- **`x-form.*`.** `x-form.input` mengisi `old($key, $value)` (password tidak pernah diisi ulang), meneruskan atribut sisa (`max`, `autofocus`) ke `<input>`; `x-form.radio-group` menerima `selected` enum dan mencetak `value="P" checked`; `x-form.checkbox` berlabel teks polos (cukup untuk `remove_bpjs`); `x-form.errors-summary` satu kali di atas kedua form (nama field kedua form tidak bertumpuk: `full_name`, `birth_date`, `gender`, `phone`, `email`, `address`, `remove_bpjs` vs `current_password`, `password`, `password_confirmation`). `layouts.patient` TIDAK menyertakan `errors-summary`; `layouts.public` menyertakan `x-flash-alert` (kunci `success`).
- **Breadcrumb.** Komponen sekarang `$isAdmin ? 'Dasbor' : 'Beranda'`; diubah menjadi `match ($home)` dengan akar `patient.dashboard` → "Portal Pasien". `NavigationComponentsTest` (3 tes breadcrumb lama) memakai `home` default atau `admin.dashboard`, jadi tetap lulus. Semua pemakai admin memakai `home="admin.dashboard"` (tidak berubah); halaman publik memakai `home` default.
- **Menu portal.** `config/navigation.php` sudah memuat item "Profil dan Akun" (`patient.profile.edit`, aktif untuk `patient.profile.*`); begitu route ada, item itu muncul di sidebar `layouts.patient` (dan menjadi `aria-current="page"` di halaman profil). Tes lama yang mengunci ketiadaan item itu (bila ada) harus disesuaikan; `git grep` di langkah 14 memeriksanya.
- **Tes lain yang menyentuh area ini.** `SecurityHeaders` sudah menandai `pasien/*` sebagai `no-store, private`. `RawOutputScanTest` mengunci `{!!`: view baru tidak memakainya. `PatientDashboardTest` (T3) tidak menyentuh tautan profil.
- **Anchor T3.** Langkah 11 memakai anchor pasti `</dl>` (tepat satu kali di `resources/views/patient/dashboard.blade.php` hasil T3) dan menyisipkan satu tautan sebagai anak terakhir `card-body` kartu "Profil Anda".
- Jumlah tes ±100 (`PatientProfileTest` ±65 termasuk provider, `PatientPasswordChangeTest` ±30, `NavigationComponentsTest` +1).

- [ ] **Step 1: Verifikasi prasyarat (cabang; T1 sampai T5 sudah ada)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
Test-Path routes\patient.php, resources\views\patient\dashboard.blade.php, app\Services\Patients\PatientRegistrationService.php, app\Policies\PatientProfilePolicy.php, tests\Support\InteractsWithPatients.php, app\Http\Controllers\Auth\NewPasswordController.php
(& $PHP artisan route:list --except-vendor --json | ConvertFrom-Json).Count
git grep -n "Profil dan Akun\|patient.profile\|Portal Pasien" -- tests app resources config
```
Expected: `fase-5-akun-pasien`; `git status` bersih (atau hanya `?? .superpowers/`); enam nilai `True`; jumlah route `138`; `git grep` hanya menampilkan `config/navigation.php` (item menu) dan, bila ada, tes T3 yang menyebut item "Profil dan Akun" (catat hasilnya untuk Step 14). Bila ada `False`/jumlah lain, T1 sampai T5 belum lengkap: hentikan dan laporkan.

- [ ] **Step 2: Tulis tes profil yang gagal `tests/Feature/Patient/PatientProfileTest.php` (tool Write)**

```php
<?php

namespace Tests\Feature\Patient;

use App\Enums\ActorType;
use App\Enums\BookingChannel;
use App\Enums\Gender;
use App\Models\AuditLog;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\TestCase;

/**
 * Halaman profil pasien (#28, F5-R18/R19/R20): V-06, BR-151, BR-161, PAS-05, PAS-09, SEK-11, SEK-12. Profil selalu
 * `$request->user()->patientProfile`; tidak ada id profil di URL maupun form.
 */
class PatientProfileTest extends TestCase
{
    use InteractsWithPatients;
    use RefreshDatabase;

    private const BPJS = '0009900000011';

    // --- pembantu ----------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $user
     * @param  array<string, mixed>  $profile
     */
    private function patient(array $user = [], array $profile = []): User
    {
        return $this->createPatient(
            $user + ['name' => 'Siti Rahma', 'email' => 'siti@contoh.test'],
            $profile + [
                'full_name' => 'Siti Rahma',
                'birth_date' => '1990-03-12',
                'gender' => 'P',
                'phone' => '081234567890',
                'address' => 'Jl. Merpati No. 5, Pamulang',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return $overrides + [
            'full_name' => 'Siti Rahma Baru',
            'birth_date' => '1991-02-03',
            'gender' => 'L',
            'phone' => '0812-9999-8888',
            'email' => 'siti.baru@contoh.test',
            'address' => 'Jl. Kenari No. 9, Ciputat, Tangerang Selatan',
        ];
    }

    /** Payload yang sama persis dengan isi profil saat ini (tanpa perubahan). @return array<string, string> */
    private function currentPayload(User $user): array
    {
        $profile = $user->patientProfile;

        return [
            'full_name' => $profile->full_name,
            'birth_date' => $profile->birth_date->format('Y-m-d'),
            'gender' => $profile->gender->value,
            'phone' => $profile->phone,
            'email' => $user->email,
            'address' => $profile->address,
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function putProfile(array $data): TestResponse
    {
        return $this->from(route('patient.profile.edit'))->put(route('patient.profile.update'), $data);
    }

    private function profileRow(PatientProfile $profile): ?object
    {
        return DB::table('patient_profiles')->where('id', $profile->id)->first();
    }

    private function userRow(User $user): ?object
    {
        return DB::table('users')->where('id', $user->id)->first();
    }

    // --- tampilan ---------------------------------------------------------------------------

    public function test_profile_page_renders_the_values_number_breadcrumb_and_both_forms(): void
    {
        $user = $this->patient();
        $profile = $user->patientProfile;

        $response = $this->actingAs($user)->get(route('patient.profile.edit'));

        $response->assertOk()
            ->assertSee('Profil dan Akun')
            ->assertSee('No. pasien: '.$profile->patient_number)
            ->assertSee('Data Profil')
            ->assertSee('Ganti Password')
            ->assertSee('<li class="breadcrumb-item"><a href="'.route('patient.dashboard').'">Portal Pasien</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Profil dan Akun</li>', false)
            ->assertSee('action="'.route('patient.profile.update').'"', false)
            ->assertSee('action="'.route('patient.password.update').'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('value="Siti Rahma"', false)
            ->assertSee('value="1990-03-12"', false)
            ->assertSee('value="P" checked', false)
            ->assertDontSee('value="L" checked', false)
            ->assertSee('value="081234567890"', false)
            ->assertSee('value="siti@contoh.test"', false)
            ->assertSee('Jl. Merpati No. 5, Pamulang')
            ->assertSee('Simpan Profil')
            ->assertSee('Simpan Password')
            ->assertSee('Password saat ini')
            ->assertSee('Password baru')
            ->assertSee('Konfirmasi password baru');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame(1, substr_count($response->getContent(), 'autocomplete="current-password"'));
        $this->assertSame(2, substr_count($response->getContent(), 'autocomplete="new-password"'));
    }

    public function test_the_page_escapes_the_patient_name_and_has_no_errors_summary_without_errors(): void
    {
        $user = $this->patient(['name' => 'Siti'], ['full_name' => '<script>alert(1)</script>']);

        $response = $this->actingAs($user)->get(route('patient.profile.edit'));

        $response->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $this->assertStringNotContainsString('data-errors-summary', $response->getContent());
    }

    public function test_the_bpjs_number_is_masked_on_the_page_and_never_rendered_in_full(): void
    {
        $user = $this->patient([], ['bpjs_number' => self::BPJS]);

        $response = $this->actingAs($user)->get(route('patient.profile.edit'));

        $response->assertOk()
            ->assertSee('Nomor BPJS tersimpan: •••••••••0011')
            ->assertSee('name="remove_bpjs"', false)
            ->assertSee('Hapus nomor BPJS dari profil saya')
            ->assertDontSee(self::BPJS)
            ->assertDontSee('name="bpjs_number"', false)
            ->assertDontSee('Belum ada nomor BPJS tersimpan.');

        // Di database nilai terenkripsi, bukan teks asli; last4 terisi.
        $row = $this->profileRow($user->patientProfile);
        $this->assertNotSame(self::BPJS, $row->bpjs_number);
        $this->assertStringNotContainsString(self::BPJS, (string) $row->bpjs_number);
        $this->assertSame('0011', $row->bpjs_number_last4);
    }

    public function test_without_a_stored_bpjs_number_the_page_says_so_and_offers_no_removal(): void
    {
        $user = $this->patient();

        $this->actingAs($user)->get(route('patient.profile.edit'))
            ->assertOk()
            ->assertSee('Belum ada nomor BPJS tersimpan.')
            ->assertDontSee('remove_bpjs', false)
            ->assertDontSee('Hapus nomor BPJS dari profil saya')
            ->assertDontSee('name="bpjs_number"', false);
    }

    public function test_the_dashboard_links_to_the_profile_page(): void
    {
        $user = $this->patient();

        $response = $this->actingAs($user)->get(route('patient.dashboard'));

        $response->assertOk()->assertSee('Ubah Profil dan Akun');
        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="'.preg_quote(route('patient.profile.edit'), '#').'"[^>]*>\s*Ubah Profil dan Akun\s*</a>#',
            $response->getContent(),
        );
    }

    public function test_the_portal_menu_marks_the_profile_item_as_current(): void
    {
        $this->actingAs($this->patient())->get(route('patient.profile.edit'))
            ->assertOk()
            ->assertSee('aria-label="Menu portal pasien"', false)
            ->assertSee('aria-current="page"', false);
    }

    // --- akses ------------------------------------------------------------------------------

    public function test_guests_are_sent_to_the_login_page_and_nothing_changes(): void
    {
        $user = $this->patient();
        $before = $this->profileRow($user->patientProfile);

        $this->get(route('patient.profile.edit'))->assertRedirect(route('login'));
        $this->put(route('patient.profile.update'), $this->validPayload())->assertRedirect(route('login'));

        $this->assertEquals($before, $this->profileRow($user->patientProfile));
    }

    public function test_an_account_without_a_profile_gets_404_on_the_page_and_403_on_the_update(): void
    {
        $account = User::factory()->patient()->create(['email' => 'tanpa-profil@contoh.test']);

        $this->actingAs($account)->get(route('patient.profile.edit'))->assertNotFound();
        $this->actingAs($account)->put(route('patient.profile.update'), $this->validPayload())->assertForbidden();
        $this->assertSame(0, PatientProfile::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'patient.updated')->count());
    }

    // --- pembaruan sukses (PAS-09) -----------------------------------------------------------

    public function test_a_successful_update_changes_the_six_columns_and_syncs_the_account(): void
    {
        $user = $this->patient();
        $profile = $user->patientProfile;
        $number = $profile->patient_number;
        $passwordHash = $user->password;

        $this->actingAs($user);
        $this->putProfile($this->validPayload())
            ->assertRedirect(route('patient.profile.edit'))
            ->assertSessionHas('success', 'Profil berhasil diperbarui.')
            ->assertSessionHasNoErrors();

        $fresh = PatientProfile::query()->findOrFail($profile->id);
        $this->assertSame('Siti Rahma Baru', $fresh->full_name);
        $this->assertSame('1991-02-03', $fresh->birth_date->toDateString());
        $this->assertSame(Gender::Male, $fresh->gender);
        $this->assertSame('081299998888', $fresh->phone, 'Telepon disimpan dalam bentuk ternormalisasi.');
        $this->assertSame('siti.baru@contoh.test', $fresh->email);
        $this->assertSame('Jl. Kenari No. 9, Ciputat, Tangerang Selatan', $fresh->address);
        $this->assertSame($number, $fresh->patient_number);
        $this->assertEquals($user->id, $fresh->user_id);
        $this->assertSame(BookingChannel::Online, $fresh->registration_channel);

        // BR-151/D-07: users.name, users.email, users.phone mengikuti profil; kolom akun lain tidak berubah.
        $account = $user->fresh();
        $this->assertSame('Siti Rahma Baru', $account->name);
        $this->assertSame('siti.baru@contoh.test', $account->email);
        $this->assertSame('081299998888', $account->phone);
        $this->assertSame($fresh->email, $account->email, 'patient_profiles.email selalu sama dengan users.email.');
        $this->assertSame($passwordHash, $account->password);
        $this->assertTrue($account->isPatient());
        $this->assertTrue($account->is_active);
        $this->assertFalse($account->must_change_password);

        // Flash tampil di halaman profil berikutnya.
        $this->get(route('patient.profile.edit'))
            ->assertOk()
            ->assertSee('Profil berhasil diperbarui.')
            ->assertSee('value="Siti Rahma Baru"', false);
    }

    public function test_keeping_the_own_email_is_not_a_conflict(): void
    {
        $user = $this->patient();

        $this->actingAs($user);
        $this->putProfile($this->validPayload(['email' => 'siti@contoh.test']))
            ->assertRedirect(route('patient.profile.edit'))
            ->assertSessionHas('success', 'Profil berhasil diperbarui.')
            ->assertSessionHasNoErrors();

        $this->assertSame('siti@contoh.test', $user->fresh()->email);
        $this->assertSame('siti@contoh.test', $user->patientProfile->fresh()->email);
    }

    public function test_the_email_is_normalised_to_lowercase_before_it_is_stored(): void
    {
        $user = $this->patient();

        $this->actingAs($user);
        $this->putProfile($this->validPayload(['email' => '  Siti.Baru@Contoh.TEST ']))
            ->assertSessionHas('success');

        $this->assertSame('siti.baru@contoh.test', $user->fresh()->email);
        $this->assertSame('siti.baru@contoh.test', $user->patientProfile->fresh()->email);
    }

    // --- PAS-05 / Review Focus G.3 -----------------------------------------------------------

    public function test_extra_fields_are_ignored_and_other_patients_are_untouched(): void
    {
        $owner = $this->patient([], ['bpjs_number' => self::BPJS]);
        $other = $this->createPatient(['email' => 'lain@contoh.test'], ['bpjs_number' => '0001234567890']);
        $ownerProfile = $owner->patientProfile;
        $otherProfile = $other->patientProfile;

        $ownerBefore = $this->profileRow($ownerProfile);
        $otherBefore = $this->profileRow($otherProfile);
        $otherUserBefore = $this->userRow($other);

        $this->actingAs($owner);
        $this->putProfile($this->validPayload([
            'user_id' => $other->id,
            'patient_number' => 'P999999',
            'registration_channel' => 'loket',
            'is_active' => '0',
            'bpjs_number_last4' => '9999',
            'bpjs_number' => '0007777777777',
            'consent_at' => '2000-01-01 00:00:00',
            'consent_version' => '9.9',
            'created_by_user_id' => $other->id,
            'id' => $otherProfile->id,
            'patient_profile_id' => $otherProfile->id,
        ]))
            ->assertRedirect(route('patient.profile.edit'))
            ->assertSessionHas('success', 'Profil berhasil diperbarui.');

        $ownerAfter = $this->profileRow($ownerProfile);

        foreach (['id', 'user_id', 'patient_number', 'registration_channel', 'is_active', 'bpjs_number', 'bpjs_number_last4', 'consent_at', 'consent_version', 'created_by_user_id'] as $column) {
            $this->assertEquals($ownerBefore->{$column}, $ownerAfter->{$column}, "Kolom {$column} milik pasien tidak boleh berubah lewat form.");
        }

        // Enam kolom yang diizinkan berubah.
        $this->assertSame('Siti Rahma Baru', $ownerAfter->full_name);
        $this->assertSame('081299998888', $ownerAfter->phone);

        // Profil dan akun pasien lain tidak tersentuh sama sekali.
        $this->assertEquals($otherBefore, $this->profileRow($otherProfile));
        $this->assertEquals($otherUserBefore, $this->userRow($other));
        $this->assertSame(2, PatientProfile::query()->count());
    }

    // --- email bentrok (PAS-09, G.3) ---------------------------------------------------------

    public function test_a_taken_email_is_rejected_without_any_partial_change(): void
    {
        $owner = $this->patient();
        $this->createPatient(['email' => 'dipakai@contoh.test']);
        $profileBefore = $this->profileRow($owner->patientProfile);
        $userBefore = $this->userRow($owner);

        $this->actingAs($owner);

        foreach (['dipakai@contoh.test', ' Dipakai@Contoh.TEST '] as $email) {
            $this->putProfile($this->validPayload(['email' => $email, 'full_name' => 'Nama Tidak Boleh Tersimpan']))
                ->assertRedirect(route('patient.profile.edit'))
                ->assertSessionHasErrors(['email' => 'Email ini sudah terdaftar. Gunakan email lain.']);
        }

        $this->assertEquals($profileBefore, $this->profileRow($owner->patientProfile));
        $this->assertEquals($userBefore, $this->userRow($owner));
        $this->assertSame(0, AuditLog::query()->where('action', 'patient.updated')->count());
    }

    public function test_a_unique_constraint_race_becomes_an_email_error_and_rolls_the_profile_back(): void
    {
        $owner = $this->patient();
        $profileBefore = $this->profileRow($owner->patientProfile);

        // `users` disimpan SESUDAH profil di dalam transaksi service; pengecualian di sini membuktikan rollback profil.
        User::saving(function (): void {
            throw new UniqueConstraintViolationException(
                'mysql',
                'update `users` set `email` = ? where `id` = ?',
                [],
                new PDOException('Duplicate entry'),
            );
        });

        $this->actingAs($owner);
        $this->putProfile($this->validPayload())
            ->assertRedirect(route('patient.profile.edit'))
            ->assertSessionHasErrors(['email' => 'Email ini sudah terdaftar. Gunakan email lain.']);

        $this->assertSame('siti.baru@contoh.test', session()->getOldInput('email'));
        $this->assertEquals($profileBefore, $this->profileRow($owner->patientProfile));
        $this->assertSame(0, AuditLog::query()->where('action', 'patient.updated')->count());
    }

    // --- hapus BPJS (F5-R19) -----------------------------------------------------------------

    public function test_remove_bpjs_clears_the_number_and_last4_and_the_page_reflects_it(): void
    {
        $user = $this->patient([], ['bpjs_number' => self::BPJS]);
        $this->actingAs($user);

        $this->putProfile($this->currentPayload($user) + ['remove_bpjs' => '1'])
            ->assertRedirect(route('patient.profile.edit'))
            ->assertSessionHas('success', 'Profil berhasil diperbarui.');

        $row = $this->profileRow($user->patientProfile);
        $this->assertNull($row->bpjs_number);
        $this->assertNull($row->bpjs_number_last4);

        $this->get(route('patient.profile.edit'))
            ->assertSee('Belum ada nomor BPJS tersimpan.')
            ->assertDontSee('•••••••••0011');

        // Audit memuat penanda `[disamarkan]` untuk BPJS dan tidak pernah nilai mentah (SEK-11).
        $log = AuditLog::query()->where('action', 'patient.updated')->sole();
        $this->assertSame('[disamarkan]', $log->new_values['bpjs_number'] ?? null);
        $this->assertArrayNotHasKey('bpjs_number_last4', $log->new_values);
        $this->assertSame(0, DB::table('audit_logs')
            ->where('old_values', 'like', '%'.self::BPJS.'%')
            ->orWhere('new_values', 'like', '%'.self::BPJS.'%')
            ->orWhere('description', 'like', '%'.self::BPJS.'%')
            ->count());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function nonRemovalValues(): array
    {
        return [
            'tanpa field remove_bpjs' => [[]],
            'remove_bpjs 0' => [['remove_bpjs' => '0']],
        ];
    }

    /** @param  array<string, mixed>  $extra */
    #[DataProvider('nonRemovalValues')]
    public function test_the_bpjs_number_is_kept_unless_removal_is_explicitly_requested(array $extra): void
    {
        $user = $this->patient([], ['bpjs_number' => self::BPJS]);
        $before = $this->profileRow($user->patientProfile);
        $this->actingAs($user);

        $this->putProfile($this->validPayload($extra))->assertSessionHas('success');

        $after = $this->profileRow($user->patientProfile);
        $this->assertSame($before->bpjs_number, $after->bpjs_number);
        $this->assertSame('0011', $after->bpjs_number_last4);
        $this->assertSame(self::BPJS, $user->patientProfile->fresh()->bpjs_number);
    }

    // --- audit (SEK-11) ----------------------------------------------------------------------

    public function test_the_update_is_audited_by_field_name_without_raw_personal_data(): void
    {
        $user = $this->patient();
        $number = $user->patientProfile->patient_number;

        $this->actingAs($user);
        $this->putProfile($this->validPayload())->assertSessionHas('success');

        $log = AuditLog::query()->where('action', 'patient.updated')->sole();
        $this->assertSame(ActorType::Patient, $log->actor_type);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertSame(PatientProfile::class, $log->auditable_type);
        $this->assertEquals($user->patientProfile->id, $log->auditable_id);
        $this->assertSame("Profil pasien {$number} diperbarui.", $log->description);

        $keys = array_keys($log->new_values);
        sort($keys);
        $this->assertSame(['address', 'birth_date', 'email', 'full_name', 'gender', 'phone'], $keys);
        $this->assertSame('Siti Rahma', $log->old_values['full_name']);
        $this->assertSame('Siti Rahma Baru', $log->new_values['full_name']);
        $this->assertSame('P', $log->old_values['gender']);
        $this->assertSame('L', $log->new_values['gender']);

        foreach (['address', 'birth_date', 'email', 'phone'] as $redacted) {
            $this->assertSame('[disamarkan]', $log->old_values[$redacted]);
            $this->assertSame('[disamarkan]', $log->new_values[$redacted]);
        }

        foreach (['081234567890', '081299998888', '0812-9999-8888', 'Jl. Merpati No. 5', 'Jl. Kenari No. 9', 'siti@contoh.test', 'siti.baru@contoh.test', '1990-03-12', '1991-02-03'] as $secret) {
            $this->assertSame(0, DB::table('audit_logs')
                ->where('old_values', 'like', "%{$secret}%")
                ->orWhere('new_values', 'like', "%{$secret}%")
                ->orWhere('description', 'like', "%{$secret}%")
                ->count(), "Nilai '{$secret}' tidak boleh tersimpan mentah di audit_logs.");
        }
    }

    public function test_an_update_without_changes_is_not_audited(): void
    {
        $user = $this->patient();

        $this->actingAs($user);
        $this->putProfile($this->currentPayload($user))
            ->assertRedirect(route('patient.profile.edit'))
            ->assertSessionHas('success', 'Profil berhasil diperbarui.');

        $this->assertSame(0, AuditLog::query()->where('action', 'patient.updated')->count());
    }

    // --- validasi V-06 dan input liar (G.2) --------------------------------------------------

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        $personName = 'Nama lengkap hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.';

        return [
            'nama kosong' => [['full_name' => ''], 'full_name', 'Nama lengkap wajib diisi.'],
            'nama larik' => [['full_name' => ['x']], 'full_name', 'Nama lengkap harus berupa teks.'],
            'nama terlalu pendek' => [['full_name' => 'Ab'], 'full_name', 'Nama lengkap minimal 3 karakter.'],
            'nama terlalu panjang' => [['full_name' => str_repeat('a', 101)], 'full_name', 'Nama lengkap maksimal 100 karakter.'],
            'nama berangka' => [['full_name' => 'Siti123'], 'full_name', $personName],
            'tanggal kosong' => [['birth_date' => ''], 'birth_date', 'Tanggal lahir wajib diisi.'],
            'tanggal larik' => [['birth_date' => ['x']], 'birth_date', 'Format tanggal lahir tidak valid.'],
            'tanggal format salah' => [['birth_date' => '31-12-1990'], 'birth_date', 'Format tanggal lahir tidak valid.'],
            'tanggal masa depan' => [['birth_date' => '2099-01-01'], 'birth_date', 'Tanggal lahir harus sebelum hari ini.'],
            'tanggal lebih dari 120 tahun' => [['birth_date' => '1800-01-01'], 'birth_date', 'Tanggal lahir tidak boleh lebih dari 120 tahun yang lalu.'],
            'jenis kelamin kosong' => [['gender' => ''], 'gender', 'Jenis kelamin wajib dipilih.'],
            'jenis kelamin X' => [['gender' => 'X'], 'gender', 'Jenis kelamin tidak valid.'],
            'jenis kelamin larik' => [['gender' => ['L']], 'gender', 'Jenis kelamin tidak valid.'],
            'telepon kosong' => [['phone' => ''], 'phone', 'Nomor telepon wajib diisi.'],
            'telepon larik' => [['phone' => ['0812']], 'phone', 'Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.'],
            'telepon bukan seluler' => [['phone' => '12345'], 'phone', 'Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.'],
            'email kosong' => [['email' => ''], 'email', 'Email wajib diisi.'],
            'email larik' => [['email' => ['x@contoh.test']], 'email', 'Format email tidak valid.'],
            'email tidak valid' => [['email' => 'bukan-email'], 'email', 'Format email tidak valid.'],
            'email terlalu panjang' => [['email' => str_repeat('a', 250).'@x.test'], 'email', 'Email maksimal 255 karakter.'],
            'alamat kosong' => [['address' => ''], 'address', 'Alamat wajib diisi.'],
            'alamat larik' => [['address' => ['x']], 'address', 'Alamat harus berupa teks.'],
            'alamat terlalu pendek' => [['address' => 'pendek'], 'address', 'Alamat minimal 10 karakter.'],
            'alamat terlalu panjang' => [['address' => str_repeat('a', 501)], 'address', 'Alamat maksimal 500 karakter.'],
            'remove_bpjs abc' => [['remove_bpjs' => 'abc'], 'remove_bpjs', 'Pilihan hapus nomor BPJS tidak valid.'],
            'remove_bpjs larik' => [['remove_bpjs' => ['1']], 'remove_bpjs', 'Pilihan hapus nomor BPJS tidak valid.'],
        ];
    }

    /** @param  array<string, mixed>  $override */
    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_with_the_documented_message_and_changes_nothing(array $override, string $field, string $message): void
    {
        $user = $this->patient([], ['bpjs_number' => self::BPJS]);
        $profileBefore = $this->profileRow($user->patientProfile);
        $userBefore = $this->userRow($user);

        $this->actingAs($user);
        $this->putProfile($this->validPayload($override))
            ->assertRedirect(route('patient.profile.edit'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertEquals($profileBefore, $this->profileRow($user->patientProfile));
        $this->assertEquals($userBefore, $this->userRow($user));
        $this->assertSame(0, AuditLog::query()->where('action', 'patient.updated')->count());
    }

    public function test_after_a_validation_error_the_page_keeps_the_typed_values_and_shows_the_summary(): void
    {
        $user = $this->patient();

        $this->actingAs($user);
        $this->putProfile($this->validPayload(['phone' => '12345']))
            ->assertSessionHasErrors('phone');

        $response = $this->get(route('patient.profile.edit'))
            ->assertOk()
            ->assertSee('Periksa kembali isian Anda')
            ->assertSee('Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.')
            ->assertSee('value="Siti Rahma Baru"', false)
            ->assertSee('value="siti.baru@contoh.test"', false)
            ->assertSee('value="12345"', false);
        // Satu ringkasan galat di atas kedua form.
        $this->assertSame(1, substr_count($response->getContent(), 'data-errors-summary'));
    }

    public function test_array_input_does_not_break_the_form_when_it_is_rendered_again(): void
    {
        $user = $this->patient([], ['bpjs_number' => self::BPJS]);

        $this->actingAs($user);
        $this->putProfile($this->validPayload([
            'full_name' => ['x'],
            'birth_date' => ['x'],
            'gender' => ['L'],
            'phone' => ['0812'],
            'email' => ['x@contoh.test'],
            'address' => ['x'],
            'remove_bpjs' => ['1'],
        ]))->assertSessionHasErrors(['full_name', 'birth_date', 'gender', 'phone', 'email', 'address', 'remove_bpjs']);

        $this->get(route('patient.profile.edit'))
            ->assertOk()
            ->assertSee('Periksa kembali isian Anda');
    }

    public function test_the_phone_number_is_stored_normalised(): void
    {
        $user = $this->patient();

        $this->actingAs($user);
        $this->putProfile($this->validPayload(['phone' => ' (0812) 9999.8888 ']))->assertSessionHas('success');

        $this->assertSame('081299998888', $user->patientProfile->fresh()->phone);
        $this->assertSame('081299998888', $user->fresh()->phone);
    }
}
```

- [ ] **Step 3: Tulis tes ganti password yang gagal `tests/Feature/Patient/PatientPasswordChangeTest.php` (tool Write)**

```php
<?php

namespace Tests\Feature\Patient;

use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\TestCase;

/**
 * Ganti password pasien (#28, F5-R21): V-07, BR-145, SEK-01, SEK-02, SEK-11. Pola `AdminPasswordChangeTest`; berbeda:
 * sesi lain milik akun dihapus (06 §19), audit berpelaku pasien, redirect ke halaman profil.
 */
class PatientPasswordChangeTest extends TestCase
{
    use InteractsWithPatients;
    use RefreshDatabase;

    private const CURRENT = 'Rahasia123';

    private const NEW_PASSWORD = 'PasswordBaru123';

    private function patient(): User
    {
        return $this->createPatient(['email' => 'pasien@contoh.test']);
    }

    /** @param  array<string, mixed>  $data */
    private function putPassword(array $data): TestResponse
    {
        return $this->from(route('patient.profile.edit'))->put(route('patient.password.update'), $data);
    }

    /** @return array<string, string> */
    private function validPayload(): array
    {
        return [
            'current_password' => self::CURRENT,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionRow(string $id, ?int $userId): array
    {
        return [
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'uji',
            'payload' => 'x',
            'last_activity' => time(),
        ];
    }

    public function test_the_helper_password_matches_the_documented_constant(): void
    {
        $this->assertSame(self::CURRENT, $this->patientPassword());
    }

    // --- sukses ------------------------------------------------------------------------------

    public function test_a_patient_can_change_the_password_and_stays_logged_in(): void
    {
        $user = $this->patient();

        $this->actingAs($user);
        $this->putPassword($this->validPayload())
            ->assertRedirect(route('patient.profile.edit'))
            ->assertSessionHas('success', 'Password berhasil diganti.')
            ->assertSessionHasNoErrors();

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));
        $this->assertFalse(Hash::check(self::CURRENT, $fresh->password));
        $this->assertStringStartsWith('$2y$', DB::table('users')->where('id', $user->id)->value('password'));

        // Sesi sekarang tetap login: halaman terlindungi tetap terbuka dan flash tampil.
        $this->assertAuthenticatedAs($user);
        $this->get(route('patient.profile.edit'))->assertOk()->assertSee('Password berhasil diganti.');
        $this->get(route('patient.dashboard'))->assertOk();
    }

    public function test_the_session_id_is_regenerated_and_the_remember_token_rotates(): void
    {
        $user = $this->patient();
        $rememberBefore = $user->remember_token;

        $this->actingAs($user);
        $this->get(route('patient.profile.edit'));
        $before = session()->getId();

        $this->putPassword($this->validPayload())->assertRedirect(route('patient.profile.edit'));

        $this->assertNotSame($before, session()->getId());
        $rememberAfter = $user->fresh()->remember_token;
        $this->assertNotSame($rememberBefore, $rememberAfter);
        $this->assertSame(60, strlen((string) $rememberAfter));
    }

    public function test_other_sessions_of_the_account_are_deleted_and_other_accounts_are_untouched(): void
    {
        $user = $this->patient();
        $other = $this->createPatient(['email' => 'lain@contoh.test']);

        DB::table('sessions')->insert([
            $this->sessionRow('sesi-lain-1', $user->id),
            $this->sessionRow('sesi-lain-2', $user->id),
            $this->sessionRow('sesi-akun-lain', $other->id),
            $this->sessionRow('sesi-tamu', null),
        ]);

        $this->actingAs($user);
        $this->putPassword($this->validPayload())->assertRedirect(route('patient.profile.edit'));

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->whereIn('id', ['sesi-lain-1', 'sesi-lain-2'])->count());
        $this->assertEqualsCanonicalizing(['sesi-akun-lain', 'sesi-tamu'], DB::table('sessions')->pluck('id')->all());
    }

    public function test_the_session_cleanup_spares_the_current_session_id(): void
    {
        // Driver `database`: baris sesi sekarang ditulis di akhir permintaan, sehingga dapat dibedakan dari baris palsu.
        // `config()` dipanggil sebelum `actingAs` memulai sesi.
        config(['session.driver' => 'database']);

        $user = $this->patient();
        DB::table('sessions')->insert([
            $this->sessionRow('sesi-lain-1', $user->id),
            $this->sessionRow('sesi-lain-2', $user->id),
        ]);

        $this->actingAs($user);
        $this->putPassword($this->validPayload())->assertRedirect(route('patient.profile.edit'));

        // Hanya baris dengan ID sesi SEKARANG (hasil regenerasi) yang tersisa untuk akun ini.
        $this->assertSame([session()->getId()], DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all());
    }

    public function test_a_password_change_is_audited_without_any_password(): void
    {
        $user = $this->patient();
        $number = $user->patientProfile->patient_number;

        $this->actingAs($user);
        $this->putPassword($this->validPayload())->assertRedirect(route('patient.profile.edit'));

        $log = AuditLog::query()->where('action', 'auth.password_changed')->sole();
        $this->assertSame(ActorType::Patient, $log->actor_type);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertEquals($user->id, $log->auditable_id);
        $this->assertSame(User::class, $log->auditable_type);
        $this->assertNull($log->old_values);
        $this->assertNull($log->new_values);
        $this->assertSame("Pasien {$number} mengganti password akunnya.", $log->description);

        foreach ([self::CURRENT, self::NEW_PASSWORD, 'pasien@contoh.test'] as $secret) {
            $this->assertSame(0, DB::table('audit_logs')
                ->where('old_values', 'like', "%{$secret}%")
                ->orWhere('new_values', 'like', "%{$secret}%")
                ->orWhere('description', 'like', "%{$secret}%")
                ->count(), 'Password dan email tidak boleh ada di audit_logs.');
        }
    }

    public function test_an_account_without_a_profile_can_still_change_the_password(): void
    {
        $account = User::factory()->patient()->create(['email' => 'tanpa-profil@contoh.test', 'password' => self::CURRENT]);

        $this->actingAs($account);
        $this->putPassword($this->validPayload())
            ->assertRedirect(route('patient.profile.edit'))
            ->assertSessionHas('success', 'Password berhasil diganti.');

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $account->fresh()->password));
        $log = AuditLog::query()->where('action', 'auth.password_changed')->sole();
        $this->assertSame('Pasien - mengganti password akunnya.', $log->description);
    }

    // --- validasi V-07 -----------------------------------------------------------------------

    /**
     * @return array<string, array{array<string, mixed>, string, ?string}>
     */
    public static function invalidPayloads(): array
    {
        $ok = ['current_password' => self::CURRENT, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD];

        return [
            'password saat ini salah' => [['current_password' => 'SalahSekali1'] + $ok, 'current_password', 'Password saat ini tidak sesuai.'],
            'password saat ini kosong' => [['current_password' => ''] + $ok, 'current_password', 'Password saat ini wajib diisi.'],
            'password saat ini larik' => [['current_password' => ['x']] + $ok, 'current_password', 'Password saat ini tidak valid.'],
            'sama dengan password lama' => [['password' => self::CURRENT, 'password_confirmation' => self::CURRENT] + $ok, 'password', 'Password baru harus berbeda dari password saat ini.'],
            'tanpa angka' => [['password' => 'Abcdefghij', 'password_confirmation' => 'Abcdefghij'] + $ok, 'password', 'Password harus mengandung angka.'],
            'tanpa huruf' => [['password' => '1234567890', 'password_confirmation' => '1234567890'] + $ok, 'password', 'Password harus mengandung huruf.'],
            'terlalu pendek' => [['password' => 'Ab1', 'password_confirmation' => 'Ab1'] + $ok, 'password', 'Password minimal 8 karakter.'],
            'terlalu panjang' => [['password' => str_repeat('Aa1', 21).'AB', 'password_confirmation' => str_repeat('Aa1', 21).'AB'] + $ok, 'password', 'Password maksimal 64 karakter.'],
            'konfirmasi tidak cocok' => [['password_confirmation' => 'Lain12345'] + $ok, 'password', 'Konfirmasi password tidak cocok.'],
            'password baru kosong' => [['password' => ''] + $ok, 'password', 'Password baru wajib diisi.'],
            'password baru larik' => [['password' => ['x'], 'password_confirmation' => ['x']] + $ok, 'password', null],
            'konfirmasi kosong' => [['password_confirmation' => ''] + $ok, 'password_confirmation', 'Konfirmasi password wajib diisi.'],
            'konfirmasi larik' => [['password_confirmation' => ['x']] + $ok, 'password', null],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_input_is_rejected_with_the_documented_messages_and_changes_nothing(array $payload, string $field, ?string $message): void
    {
        $user = $this->patient();
        $hash = $user->password;
        $remember = $user->remember_token;
        DB::table('sessions')->insert($this->sessionRow('sesi-lain', $user->id));

        $this->actingAs($user);
        $response = $this->putPassword($payload)->assertRedirect(route('patient.profile.edit'));

        if ($message === null) {
            $response->assertSessionHasErrors($field);
        } else {
            $response->assertSessionHasErrors([$field => $message]);
        }

        $fresh = $user->fresh();
        $this->assertSame($hash, $fresh->password);
        $this->assertSame($remember, $fresh->remember_token);
        $this->assertSame(1, DB::table('sessions')->where('id', 'sesi-lain')->count(), 'Sesi lain tidak boleh terhapus bila gagal.');
        $this->assertSame(0, AuditLog::query()->where('action', 'auth.password_changed')->count());
    }

    public function test_password_fields_are_never_flashed_back_into_the_form(): void
    {
        $user = $this->patient();

        $this->actingAs($user);
        $this->putPassword(['current_password' => 'SalahSekali1', 'password' => 'Ab1', 'password_confirmation' => 'Ab1']);

        foreach (['current_password', 'password', 'password_confirmation'] as $field) {
            $this->assertNull(session("_old_input.{$field}"), "{$field} tidak boleh di-flash.");
        }

        $this->get(route('patient.profile.edit'))
            ->assertOk()
            ->assertDontSee('SalahSekali1');
    }

    // --- form dan akses ----------------------------------------------------------------------

    public function test_the_form_carries_a_hidden_username_field_for_password_managers(): void
    {
        $user = $this->patient();

        $this->actingAs($user)->get(route('patient.profile.edit'))
            ->assertOk()
            ->assertSee('<input type="text" name="username" value="pasien@contoh.test" autocomplete="username" readonly hidden>', false)
            ->assertSee('name="current_password"', false)
            ->assertSee('name="password_confirmation"', false);
    }

    public function test_guests_cannot_change_a_password(): void
    {
        $user = $this->patient();
        $hash = $user->password;

        $this->put(route('patient.password.update'), $this->validPayload())->assertRedirect(route('login'));

        $this->assertSame($hash, $user->fresh()->password);
    }
}
```

- [ ] **Step 4: Tambah tes breadcrumb baru (tool Edit pada `tests/Feature/View/NavigationComponentsTest.php`)**

`old_string`:

```php
    public function test_breadcrumb_without_items_shows_home_as_the_current_page(): void
```

`new_string`:

```php
    public function test_breadcrumb_for_the_patient_portal_starts_with_portal_pasien(): void
    {
        $this->blade('<x-breadcrumb home="patient.dashboard" :items="$items" />', [
            'items' => [['label' => 'Profil dan Akun']],
        ])
            ->assertSee('<li class="breadcrumb-item"><a href="'.route('patient.dashboard').'">Portal Pasien</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Profil dan Akun</li>', false)
            ->assertDontSee('Beranda')
            ->assertDontSee('Dasbor');
    }

    public function test_breadcrumb_without_items_shows_home_as_the_current_page(): void
```

- [ ] **Step 5: Jalankan tes, pastikan GAGAL (RED)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter='PatientProfileTest|PatientPasswordChangeTest|NavigationComponentsTest'
```
Expected: FAIL/ERROR. `PatientProfileTest` dan `PatientPasswordChangeTest`: hampir semua tes ERROR dengan `Symfony\Component\Routing\Exception\RouteNotFoundException: Route [patient.profile.edit] not defined.` (atau `Route [patient.password.update] not defined.`); `test_the_helper_password_matches_the_documented_constant` PASS; `test_guests_are_sent_to_the_login_page_and_nothing_changes` ERROR pada `route('patient.profile.edit')`. `NavigationComponentsTest::test_breadcrumb_for_the_patient_portal_starts_with_portal_pasien` FAIL: kalimat "Portal Pasien" tidak ditemukan (akar masih "Beranda"); tiga tes breadcrumb lama PASS.

- [ ] **Step 6: Buat Form Request V-06 `app/Http/Requests/Patient/UpdatePatientProfileRequest.php` (tool Write)**

```php
<?php

namespace App\Http\Requests\Patient;

use App\Enums\Gender;
use App\Models\User;
use App\Rules\IndonesianPhone;
use App\Rules\PersonName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * V-06: ubah profil pasien (#28). Daftar field TERTUTUP (BR-161): `user_id`, `patient_number`, `registration_channel`,
 * `is_active`, `consent_*`, `bpjs_number`, `bpjs_number_last4`, `created_by_user_id` tidak ada di `rules()` sehingga
 * tidak pernah muncul di `validated()`. Profil yang diubah SELALU `$user->patientProfile` (tanpa id di URL/form, BR-150).
 * Email login = email profil (D-07): unik di seluruh `users` kecuali akun sendiri. Nilai larik pada field mana pun
 * menghasilkan galat Indonesia (aturan `string`/`enum`/`boolean` mendahului), bukan 500.
 */
class UpdatePatientProfileRequest extends FormRequest
{
    /** F5-R13/F5-R14: butuh akun pasien yang punya profil dan boleh mengubahnya (PatientProfilePolicy::update). */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->isPatient()) {
            return false;
        }

        $profile = $user->patientProfile;

        return $profile !== null && $user->can('update', $profile);
    }

    /** Normalisasi (12 §1 butir 4): trim teks, email huruf kecil, telepon tanpa spasi/tanda hubung/titik/kurung. */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['full_name', 'address'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $normalized[$field] = trim($value);
            }
        }

        $email = $this->input('email');

        if (is_string($email)) {
            $normalized['email'] = Str::lower(trim($email));
        }

        $phone = $this->input('phone');

        if (is_string($phone)) {
            $normalized['phone'] = IndonesianPhone::normalize(trim($phone));
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $userId = $this->user()?->getKey();

        return [
            'full_name' => ['bail', 'required', 'string', 'min:3', 'max:100', new PersonName],
            'birth_date' => ['bail', 'required', 'date_format:Y-m-d', 'before:today', 'after_or_equal:-120 years'],
            'gender' => ['bail', 'required', Rule::enum(Gender::class)],
            'phone' => ['bail', 'required', new IndonesianPhone],
            'email' => ['bail', 'required', 'string', 'max:255', 'email:rfc', Rule::unique('users', 'email')->ignore($userId)],
            'address' => ['bail', 'required', 'string', 'min:10', 'max:500'],
            'remove_bpjs' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Nama lengkap wajib diisi.',
            'full_name.string' => 'Nama lengkap harus berupa teks.',
            'full_name.min' => 'Nama lengkap minimal 3 karakter.',
            'full_name.max' => 'Nama lengkap maksimal 100 karakter.',
            'birth_date.required' => 'Tanggal lahir wajib diisi.',
            'birth_date.date_format' => 'Format tanggal lahir tidak valid.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'birth_date.after_or_equal' => 'Tanggal lahir tidak boleh lebih dari 120 tahun yang lalu.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.enum' => 'Jenis kelamin tidak valid.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.string' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar. Gunakan email lain.',
            'address.required' => 'Alamat wajib diisi.',
            'address.string' => 'Alamat harus berupa teks.',
            'address.min' => 'Alamat minimal 10 karakter.',
            'address.max' => 'Alamat maksimal 500 karakter.',
            'remove_bpjs.boolean' => 'Pilihan hapus nomor BPJS tidak valid.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => 'Nama lengkap',
            'birth_date' => 'Tanggal lahir',
            'gender' => 'Jenis kelamin',
            'phone' => 'Nomor telepon',
            'email' => 'Email',
            'address' => 'Alamat',
            'remove_bpjs' => 'Hapus nomor BPJS',
        ];
    }
}
```

- [ ] **Step 7: Buat Form Request V-07 `app/Http/Requests/Patient/UpdatePasswordRequest.php` (tool Write)**

```php
<?php

namespace App\Http\Requests\Patient;

use App\Rules\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V-07: ganti password pasien (#28). Aturan dan pesan sama dengan Admin\UpdatePasswordRequest; berbeda pada authorize()
 * (akun pasien) dan pada `current_password`: `bail` + `string` mendahului aturan `current_password` karena aturan bawaan
 * meneruskan nilai ke `password_verify()` tanpa memeriksa tipe (`current_password[]=x` akan menjadi 500).
 */
class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isPatient();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['bail', 'required', 'string', 'current_password'],
            'password' => [...PasswordRules::default(), 'different:current_password'],
            'password_confirmation' => ['required'],
        ];
    }

    /**
     * Kunci `password.password.letters`/`numbers` dipakai oleh validator bersarang aturan Password.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'current_password.string' => 'Password saat ini tidak valid.',
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.max' => 'Password maksimal 64 karakter.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.string' => 'Password tidak valid.',
            'password.password.letters' => 'Password harus mengandung huruf.',
            'password.password.numbers' => 'Password harus mengandung angka.',
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
            'password_confirmation.required' => 'Konfirmasi password wajib diisi.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => 'Password saat ini',
            'password' => 'Password baru',
            'password_confirmation' => 'Konfirmasi password baru',
        ];
    }
}
```

- [ ] **Step 7A: Tambal celah `current_password[]=x` pada `Admin\UpdatePasswordRequest` (fase 1)**

Aturan bawaan `current_password` meneruskan nilai ke `password_verify()` tanpa memeriksa tipe, sehingga `current_password[]=x` pada `PUT /admin/password` menghasilkan `TypeError` (500) (aturan proyek: input larik tidak boleh 500). Tes dulu, lalu perbaikan.

(a) Tes. Tiga penggantian pada `tests/Feature/Auth/AdminPasswordChangeTest.php` (tool Edit).

old:
```php
    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function invalidPayloads(): array
```
new:
```php
    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidPayloads(): array
```
old:
```php
            'password saat ini kosong' => [['current_password' => ''] + $ok, 'current_password', 'Password saat ini wajib diisi.'],
```
new:
```php
            'password saat ini kosong' => [['current_password' => ''] + $ok, 'current_password', 'Password saat ini wajib diisi.'],
            'password saat ini larik' => [['current_password' => ['x']] + $ok, 'current_password', 'Password saat ini tidak valid.'],
```
old:
```php
    /** @param  array<string, string>  $payload */
    #[DataProvider('invalidPayloads')]
```
new:
```php
    /** @param  array<string, mixed>  $payload */
    #[DataProvider('invalidPayloads')]
```

(b) RED.

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminPasswordChangeTest
```
Expected: FAIL tepat satu tes, `test_invalid_input_is_rejected_with_the_documented_messages_and_changes_nothing with data set "password saat ini larik"`: `Expected response status code [201, 301, 302, 303, 307, 308] but received 500.` (penyebab: `TypeError: password_verify(): Argument #1 ($password) must be of type string, array given`). Tes lain tetap PASS.

(c) Perbaikan. Dua penggantian pada `app/Http/Requests/Admin/UpdatePasswordRequest.php` (tool Edit).

old:
```php
            'current_password' => ['required', 'current_password'],
```
new:
```php
            // `bail` + `string` mendahului `current_password`: aturan bawaan memanggil password_verify() tanpa memeriksa
            // tipe, sehingga `current_password[]=x` akan menjadi 500.
            'current_password' => ['bail', 'required', 'string', 'current_password'],
```
old:
```php
            'current_password.required' => 'Password saat ini wajib diisi.',
```
new:
```php
            'current_password.required' => 'Password saat ini wajib diisi.',
            'current_password.string' => 'Password saat ini tidak valid.',
```

(d) GREEN.

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminPasswordChangeTest
```
Expected: PASS semua (tes lama tetap hijau; provider bertambah satu kasus).

- [ ] **Step 8: Buat controller profil `app/Http/Controllers/Patient/ProfileController.php` (tool Write)**

```php
<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\UpdatePatientProfileRequest;
use App\Models\User;
use App\Services\Patients\PatientRegistrationService;
use App\Support\Actor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Profil dan akun pasien (#28, F5-R18): satu halaman dua formulir. Profil SELALU `$request->user()->patientProfile`
 * (tanpa id di URL/form, BR-150). Akun tanpa profil (hanya mungkin di tes/DB rusak): halaman 404, pembaruan 403
 * lewat `UpdatePatientProfileRequest::authorize()` (F5-R13).
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->patientProfile;

        abort_if($profile === null, 404);

        $this->authorize('view', $profile);

        return view('patient.profile.edit', [
            'user' => $user,
            'profile' => $profile,
        ]);
    }

    public function update(UpdatePatientProfileRequest $request, PatientRegistrationService $service): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->patientProfile; // authorize() menjamin profil ada

        try {
            $service->updateProfile($profile, $request->validated(), Actor::patient($user));
        } catch (UniqueConstraintViolationException) {
            // Balapan: email diambil akun lain di antara validasi dan simpan. Transaksi service sudah dibatalkan.
            return redirect()->route('patient.profile.edit')
                ->withInput($request->only(['full_name', 'birth_date', 'gender', 'phone', 'email', 'address', 'remove_bpjs']))
                ->withErrors(['email' => 'Email ini sudah terdaftar. Gunakan email lain.']);
        }

        return redirect()->route('patient.profile.edit')->with('success', 'Profil berhasil diperbarui.');
    }
}
```

- [ ] **Step 9: Buat controller ganti password `app/Http/Controllers/Patient/PasswordController.php` (tool Write)**

```php
<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\UpdatePasswordRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ganti password pasien (#28, F5-R21). Hanya butuh `User` (akun tanpa profil tetap bisa mengganti password).
 * Urutan penting: regenerasi sesi DULU, lalu hapus baris `sessions` akun selain ID sesi baru (sesi ini tetap hidup).
 */
class PasswordController extends Controller
{
    public function update(UpdatePasswordRequest $request, AuditLogger $audit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Cast `hashed` pada User meng-hash password; jangan memanggil Hash::make() lagi.
        $user->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        $number = $user->patientProfile?->patient_number ?? '-';

        $audit->log(
            'auth.password_changed',
            $user,
            null,
            null,
            "Pasien {$number} mengganti password akunnya.",
            Actor::patient($user),
        );

        return redirect()->route('patient.profile.edit')->with('success', 'Password berhasil diganti.');
    }
}
```

- [ ] **Step 10: Buat view profil `resources/views/patient/profile/edit.blade.php` (tool Write)**

```blade
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
```

- [ ] **Step 11: Tautan "Ubah Profil dan Akun" di dashboard (tool Read lalu Edit pada `resources/views/patient/dashboard.blade.php`)**

Baca berkas dulu (tool Read), lalu satu Edit pasti. `</dl>` ada TEPAT SATU KALI di `resources/views/patient/dashboard.blade.php` hasil T3 (penutup daftar kartu "Profil Anda", F5-R22), sehingga tautan jatuh sebagai ANAK TERAKHIR `card-body` kartu itu.

old_string (indentasi 20 spasi, sesuai berkas):

```blade
                    </dl>
```

new_string:

```blade
                    </dl>
                    <a class="btn btn-outline-primary btn-sm mt-3" href="{{ route('patient.profile.edit') }}">Ubah Profil dan Akun</a>
```

Bila indentasi berkas berbeda, cocokkan indentasi yang ada (boleh `old_string` hanya `</dl>`). T3 tidak menulis `Route::has` untuk tautan ini; JANGAN menambahkannya (route sudah ada dari langkah 12). Tautan harus berada di dalam kartu "Profil Anda" dan hanya satu kali di berkas. Hasil dikunci oleh `PatientProfileTest::test_the_dashboard_links_to_the_profile_page` (regex `<a ... href=".../pasien/profil" ...>Ubah Profil dan Akun</a>`).

- [ ] **Step 12: Daftarkan route pasien (tool Edit pada `routes/patient.php`)**

Dua pengeditan.

1. Import. `old_string`:

```php
use App\Http\Controllers\Patient\DashboardController;
```

`new_string`:

```php
use App\Http\Controllers\Patient\DashboardController;
use App\Http\Controllers\Patient\PasswordController;
use App\Http\Controllers\Patient\ProfileController;
```

2. Route. `old_string` (substring tunggal di berkas):

```php
->name('dashboard');
```

`new_string`:

```php
->name('dashboard');

// Profil dan akun (#28): satu halaman, dua formulir (F5-R18, F5-R21). Tanpa id profil di URL (BR-150); nama
// `patient.profile.*` menandai item menu "Profil dan Akun" sebagai aktif, `patient.password.update` tidak.
Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
Route::put('/password', [PasswordController::class, 'update'])->name('password.update');
```

Bila `old_string` tidak cocok tepat satu kali (T3 menulis route dashboard berbeda), baca `routes/patient.php`, letakkan tiga route di tingkat atas berkas sesudah route dashboard, tambahkan dua `use`, dan jangan mengubah route T3. Pint (Step 15) merapikan urutan `use`.

- [ ] **Step 13: Ubah label akar breadcrumb (tool Edit pada `resources/views/components/breadcrumb.blade.php`)**

`old_string`:

```blade
    $trail = array_merge([['label' => $isAdmin ? 'Dasbor' : 'Beranda', 'url' => $homeUrl]], array_values($items));
```

`new_string`:

```blade
    $homeLabel = match ($home) {
        'admin.dashboard' => 'Dasbor',
        'patient.dashboard' => 'Portal Pasien',
        default => 'Beranda',
    };
    $trail = array_merge([['label' => $homeLabel, 'url' => $homeUrl]], array_values($items));
```

`$isAdmin` tetap dipakai untuk `$homeUrl` (cadangan `/admin`); jangan dihapus.

- [ ] **Step 14: Jalankan tes, pastikan LULUS (GREEN), lalu tes terkait**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter='PatientProfileTest|PatientPasswordChangeTest|NavigationComponentsTest|AdminPasswordChangeTest'
& $PHP artisan test --filter='PatientDashboardTest|PatientLoginTest|LayoutsTest|SecurityHeadersTest|RawOutputScanTest|InternalLinkTest|BannerButtonTest|PatientPasswordResetTest'
(& $PHP artisan route:list --except-vendor --json | ConvertFrom-Json).Count
(& $PHP artisan route:list --except-vendor --json | ConvertFrom-Json | Where-Object { $_.name -like 'admin.*' }).Count
git grep -n "Profil dan Akun" -- tests
```
Expected: semua PASS (±100 tes baru, perkiraan dan bukan gerbang: `PatientProfileTest` ±66, `PatientPasswordChangeTest` ±30, `NavigationComponentsTest` 1 tambahan, `AdminPasswordChangeTest` 1 kasus provider); total route `141`, route admin `106`; `git grep` hanya menampilkan tes baru di atas dan (bila ada) tes T3. Bila tes T3/`LayoutsTest` yang lama mengunci KETIADAAN item "Profil dan Akun" di sidebar (karena route belum ada), ubah assert-nya menjadi ada (itu perilaku baru yang disengaja); jangan mengubah tes lain. Bila `test_the_session_cleanup_spares_the_current_session_id` gagal karena `config(['session.driver' => 'database'])` tidak berlaku (baris sesi sekarang tidak tertulis), pakai versi log query dari Catatan teknis (butir "Sesi saat ganti password"); maknanya (hanya baris sesi sekarang yang tersisa) tidak boleh dilonggarkan.

- [ ] **Step 15: Pint**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint app\Http\Requests\Patient app\Http\Controllers\Patient routes\patient.php tests\Feature\Patient tests\Feature\View\NavigationComponentsTest.php app\Http\Requests\Admin\UpdatePasswordRequest.php tests\Feature\Auth\AdminPasswordChangeTest.php
& $PHP vendor\bin\pint --test
```
Expected: pint pertama merapikan bila perlu; `pint --test` kedua bersih. Bila pint mengubah berkas, jalankan ulang Step 14 (dua perintah tes) sebelum commit.

- [ ] **Step 16: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add app/Http/Requests/Patient/UpdatePatientProfileRequest.php app/Http/Requests/Patient/UpdatePasswordRequest.php app/Http/Controllers/Patient/ProfileController.php app/Http/Controllers/Patient/PasswordController.php resources/views/patient/profile/edit.blade.php resources/views/patient/dashboard.blade.php resources/views/components/breadcrumb.blade.php routes/patient.php tests/Feature/Patient/PatientProfileTest.php tests/Feature/Patient/PatientPasswordChangeTest.php tests/Feature/View/NavigationComponentsTest.php app/Http/Requests/Admin/UpdatePasswordRequest.php tests/Feature/Auth/AdminPasswordChangeTest.php
git commit -m @'
feat(fase-5): profil dan ganti password pasien (#28), sinkron akun, BPJS tersamar, sesi lain diputus; tambal current_password larik (admin)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
git status --short
```
Expected: satu commit; `git status` bersih (selain `?? .superpowers/`). Bila Step 14 memaksa mengubah tes T3 (`LayoutsTest`/`PatientDashboardTest`), tambahkan berkas itu ke `git add` dan sebut di pesan commit.

#### Catatan penulis (konteks untuk implementer dan reviewer)

- **Anchor dashboard (T3).** Langkah 11 memakai anchor `</dl>` (tepat satu kali di dashboard hasil T3, di kartu "Profil Anda"); tidak ada penanda komentar.
- **Anchor `routes/patient.php`.** Langkah 12 mengasumsikan T3 menulis route dashboard sebagai satu pernyataan yang berakhir `->name('dashboard');` dan `use App\Http\Controllers\Patient\DashboardController;` (kerangka C.1 dan F5-R3). Cadangan ditulis di langkah.
- **Penyimpangan kecil dari kerangka.** (1) `UpdatePasswordRequest` pasien: `current_password` diberi `bail` + `string` (kerangka: "pola pesan Admin\UpdatePasswordRequest") karena aturan bawaan menyebabkan 500 untuk `current_password[]` (diverifikasi di `ValidatesAttributes::validateCurrentPassword` → `password_verify`). Pesan baru `current_password.string` = "Password saat ini tidak valid." (seragam dengan admin). `Admin\UpdatePasswordRequest` punya celah sama, ditambal di Step 7A atas keputusan koordinator (kerangka C.2 peta T6 belum mencantumkan dua berkas Admin ini; catat di Dokumen T8). (2) Email pada form profil diisi dari `$user->email` (email login), bukan `$profile->email`, agar tidak kosong bila profil lama tidak punya email. (3) `x-form.input` email di profil diberi `help` "Email ini juga dipakai untuk masuk ke akun Anda." (teks tambahan, tidak mengubah kontrak).
- **Teks input email di tes:** urutan aturan `bail, required, string, max:255, email:rfc` sama dengan T5 (bukan urutan tabel V-06); pesan identik.
- **Menu portal.** Setelah T6, sidebar `layouts.patient` menampilkan item "Profil dan Akun" (config/navigation.php sudah ada). Tes T3 yang mengunci ketiadaannya (bila ada) harus disesuaikan (Step 14); T8 `PatientPageMatrixTest` sebaiknya tidak mengasumsikan sidebar tanpa item itu.
- **Hitungan route:** setelah T6 = 141 total, 106 admin (dikunci Step 14); sama dengan target kerangka.
- **Tes `test_a_unique_constraint_race...`** memakai listener `User::saving` yang mengandalkan urutan service T2 (profil disimpan sebelum `users`). Bila T2 membalik urutan, tes tetap lulus pada bagian pesan tetapi pernyataan rollback profil menjadi sepele; urutan T2 diambil dari F5-R20.

---

### Task 7: Seeder demo pasien (`PatientSeeder`), `DemoSeeder`, dan penyesuaian tes seeder

**Files:**
- Create: `database/seeders/PatientSeeder.php`
- Modify: `database/seeders/DemoSeeder.php` (urutan `… ContentSeeder, PatientSeeder, ContactMessageSeeder`, komentar, baris pasien pada tabel kredensial konsol, catatan "Enam akun pasien lain")
- Create (tes): `tests/Feature/Seeding/PatientSeederTest.php`
- Modify (tes): `tests/Feature/Seeding/DemoSeederWiringTest.php`, `tests/Feature/Seeding/SeederProductionGuardTest.php`, `tests/Feature/Seeding/DemoSeederInvariantsTest.php`, `tests/Feature/Seeding/FacilitySeedersTest.php`
- Tidak diubah (hanya dijalankan): `database/seeders/DemoUserSeeder.php` (tetap tiga staf, F5-R27), `database/seeders/DatabaseSeeder.php`, `tests/Feature/Seeding/DemoUserSeederTest.php` (hanya memanggil `DemoUserSeeder`, tetap 3 pengguna), `tests/Feature/Seeding/ContentSeederTest.php` (memanggil `DemoUserSeeder` + `ContentSeeder`, tetap 3), `tests/Feature/Seeding/PageSeederTest.php` (produksi, tetap 0), `tests/Feature/Settings/DatabaseSeederWiringTest.php` (`DatabaseSeeder` tidak berubah), `tests/Feature/Seeding/ScheduleSeederTest.php`, `tests/Feature/Auth/RolePermissionSeederTest.php`, `database/factories/*` (tidak ada factory yang diubah).

**Interfaces:**
- Consumes (Task 1 dan Task 2, tidak diubah di sini):
  - `App\Services\Patients\PatientNumberGenerator::format(int $id): string` (`P` + 6 digit).
  - `App\Models\PatientProfile` dengan hook `saving` yang mengisi `bpjs_number_last4` dan accessor `masked_bpjs` (F5-R11); `User::patientProfile()`.
  - `App\Services\Settings\SettingsService::string()` (fase 1) untuk `privacy.consent_version`.
  - `App\Enums\AccountType::Patient`, `BookingChannel::Online`, `Gender`, `App\Rules\IndonesianPhone::PATTERN`/`::normalize()` (fase 4b).
  - `config('app.demo_password')` (fase 1, bawaan `Demo12345`).
- Produces:
  - `Database\Seeders\PatientSeeder` dengan `public const ACCOUNTS` (tujuh baris, urutan = P000001..P000007 pada DB baru) dan `run(): void`. Guard `local|testing` (melempar `RuntimeException` "PatientSeeder DILARANG di APP_ENV=…"), idempoten per email, satu `DB::transaction` per pasien, tanpa audit dan tanpa notifikasi.
  - `DemoSeeder` memanggil `PatientSeeder` di antara `ContentSeeder` dan `ContactMessageSeeder`; tabel kredensial konsol memuat baris "Anisa Rahmawati · pasien@unpamhospital.test · Pasien · {password}" dan catatan "Enam akun pasien lain (@contoh.test) memakai password yang sama.".
  - Angka acuan baru setelah `DatabaseSeeder` di `testing`: `users` **10** (3 staf + 7 pasien), `patient_profiles` **7**, `users` berakhiran `.test` **10**, `model_has_roles` tetap 3 (pasien tanpa peran).

**Test IDs:** SEED-01 (bagian fase 5: invarian I15 `patient_number` = `P` + `id` 6 digit, I16 email profil = email akun, I17 `is_active` profil = `is_active` akun, angka acuan 13 §11.2), SEED-02 (bagian fase 5: `PatientSeeder` menolak produksi dan `staging`, tidak ada pengguna `.test` di produksi), SEED-03 (idempoten, "sudah ada, dilewati"), PAS-09 (email profil = email akun, dasar seeder), SEK-01 (password demo tersimpan sebagai hash bcrypt `$2y$` dan lolos `Hash::check`), NTF-08 (seeder tidak mengirim email selamat datang dan tidak menulis `notifications`), dan Review Focus 5 (atomisitas: kegagalan saat membuat profil membatalkan akun pasien yang sama, per pasien).

**Catatan teknis (diverifikasi dari source):**
- **Pola seeder.** `DemoUserSeeder` memakai guard `app()->environment(['local', 'testing'])` dan melempar `RuntimeException('DemoUserSeeder DILARANG di APP_ENV='.app()->environment().'. Akun demo berpassword umum.')`; idempoten dengan `User::query()->where('email', …)->exists()` lalu `$this->command?->warn("Akun demo {$email} sudah ada, dilewati.")`; akun dibuat lewat `new User` + `forceFill([...'password' => $password...])->save()` (cast `hashed` meng-hash; JANGAN `Hash::make`). `PatientSeeder` meniru pola itu persis dan memakai `$this->command?->warn("Akun pasien demo {$email} sudah ada, dilewati.")` (kerangka F). `ContactMessageSeeder` dan seeder lain memakai namespace `Database\Seeders` tanpa `declare(strict_types=1);` (aturan `declare` hanya untuk `app/*`), jadi berkas seeder baru juga tanpa `declare`.
- **Skema.** `patient_profiles.patient_number` NULLable dan UNIQUE (migration `create_patient_profiles_table`), `user_id` UNIQUE NULLable. Karena itu urutan penyimpanan = (1) simpan profil tanpa nomor, (2) `forceFill(['patient_number' => PatientNumberGenerator::format($profile->id)])->save()` dalam transaksi yang sama (F5-R10); `PatientProfile::$fillable` memuat `full_name`, `birth_date`, `gender`, `phone`, `email`, `address`, `bpjs_number`, `registration_channel`, `consent_at`, `consent_version`, `is_active`, sedangkan `user_id`, `patient_number`, `bpjs_number_last4`, `created_by_user_id` hanya lewat `forceFill()` (mode ketat). Hook `saving` T1 mengisi `bpjs_number_last4` dari `bpjs_number`; seeder TIDAK menulis `bpjs_number_last4` sendiri.
- **Pengaturan.** `SettingsService` terdaftar `scoped` (`AppServiceProvider`) dan `string($key, $default)` mengembalikan `$default` bila kunci tak ada atau nilai kosong. Seeder membaca `privacy.consent_version` lewat `app(SettingsService::class)->string('privacy.consent_version', '1.0')`. Di `DemoSeeder` pengaturan sudah ada (`DatabaseSeeder` menjalankan `SettingsSeeder` lebih dulu dan `SettingsSeeder::run()` memanggil `forget()`); di tes yang menjalankan `PatientSeeder` sendirian, tabel `website_settings` kosong sehingga nilai bawaan `1.0` dipakai. Tes yang mengubah pengaturan memanggil `app(SettingsService::class)->forget()` setelah `update` langsung agar memo dan cache bersih.
- **Telepon.** Pola 13: `0812-0000-00NN`, ternormalisasi `08120000000{n}` (12 digit: `081200000001` sampai `081200000007`); lolos `IndonesianPhone::PATTERN` (`^(\+62|62|0)8[0-9]{7,12}$`). Telepon akun (`users.phone`) = telepon profil.
- **Nomor pasien di tes.** `RefreshDatabase` membungkus tiap tes dalam transaksi; `AUTO_INCREMENT` MariaDB tidak mundur pada rollback, sehingga `id` profil tidak selalu mulai dari 1. Tes TIDAK menegaskan `P000001`..`P000007` literal, melainkan `patient_number` = `PatientNumberGenerator::format(id)` (I15) dan pola `^P[0-9]{6,}$`. Urutan relatif (akun demo = profil pertama) ditegaskan lewat `id` yang naik mengikuti `ACCOUNTS`.
- **Tabel konsol.** Ditegaskan lewat `Artisan::call('db:seed', [...], $buffer)` dengan `BufferedOutput` (bukan `expectsOutputToContain`), karena `Command::table()` menulis ke buffer yang sama dan lebih stabil daripada mock `PendingCommand`.
- **DB pengembangan.** Implementer TIDAK menjalankan `migrate:fresh`/`db:seed` pada `unpam_hospital`; hanya tes (`unpam_hospital_test`). `migrate:fresh --seed` dijalankan koordinator di gerbang Task 8 (Step 13 di sana).
- **Tes lama yang berubah (hasil grep seluruh `tests/`):** `SeederProductionGuardTest` (dua angka `3` → `10`, daftar tabel produksi, satu tes baru), `DemoSeederWiringTest` (dua daftar), `DemoSeederInvariantsTest` (impor, daftar `each_master_seeder`, tes baru I15–I17), `FacilitySeedersTest` (satu angka `3` → `10`). **Tidak berubah** walau disebut di digest: `DemoUserSeederTest` (hanya `DemoUserSeeder`, tetap 3), `ContentSeederTest`, `Settings/DatabaseSeederWiringTest`, `PageSeederTest`, `ScheduleSeederTest`, `RolePermissionSeederTest`, `CreateSuperAdminTest` (tidak memanggil `DemoSeeder`). `DemoSeederInvariantsTest::test_demo_seeder_is_idempotent_through_its_guard` dan `…test_each_master_seeder_skips…` tetap benar untuk angka master (13, 17, 18, 20, 83).
- Jumlah tes baru ±20 (`PatientSeederTest` 18, `SeederProductionGuardTest` 1, `DemoSeederInvariantsTest` 1).

- [ ] **Step 1: Verifikasi prasyarat (cabang, Task 1 sampai 6 sudah ada)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
git log --oneline -8
Test-Path app\Services\Patients\PatientNumberGenerator.php
Test-Path app\Services\Patients\PatientRegistrationService.php
Test-Path tests\Support\InteractsWithPatients.php
Test-Path database\seeders\PatientSeeder.php
& $PHP artisan test --filter='DemoUserSeederTest|DemoSeederWiringTest|SeederProductionGuardTest|DemoSeederInvariantsTest|FacilitySeedersTest'
```
Expected: `fase-5-akun-pasien`; `git status` bersih (kecuali `.superpowers/` tidak terlacak); commit Task 1 sampai 6 terlihat; `True`, `True`, `True`, lalu `False` (seeder belum ada); semua tes seeder lama PASS (basis sebelum perubahan). Apache dan MySQL di `C:\xampp3` harus sudah di-Start.

- [ ] **Step 2: Tulis tes baru (tool Write) `tests/Feature/Seeding/PatientSeederTest.php`**

`tests/Feature/Seeding/PatientSeederTest.php`:

```php
<?php

namespace Tests\Feature\Seeding;

use App\Enums\AccountType;
use App\Enums\BookingChannel;
use App\Enums\Gender;
use App\Models\AuditLog;
use App\Models\PatientProfile;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Rules\IndonesianPhone;
use App\Services\Patients\PatientNumberGenerator;
use App\Services\Settings\SettingsService;
use Closure;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PatientSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Seeder demo pasien fase 5 (13 §10.1, F5-R27): tujuh akun pasien berakun beserta profil online. SEED-01 (I15 sampai
 * I17 dan angka acuan), SEED-02 (guard produksi), SEED-03 (idempoten), SEK-01, NTF-08 dan Review Focus 5 (atomisitas).
 * Walk-in (P000008 sampai P000013) dan appointment menyusul di fase 6 dan 7.
 */
class PatientSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Salinan mandiri data 13 §10.1 (nama, email, jenis kelamin, tanggal lahir, nomor BPJS fiktif), urut P000001 sampai
     * P000007. Sengaja tidak membaca `PatientSeeder::ACCOUNTS` agar perubahan data seeder terdeteksi.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: ?string}>
     */
    private const EXPECTED = [
        ['Anisa Rahmawati', 'pasien@unpamhospital.test', 'P', '1994-05-12', '0009900000011'],
        ['Budi Hartanto', 'budi.hartanto@contoh.test', 'L', '1985-11-03', '0009900000022'],
        ['Dewi Susilowati', 'dewi.susilowati@contoh.test', 'P', '1990-07-21', null],
        ['Fitri Handayani', 'fitri.handayani@contoh.test', 'P', '1998-01-30', '0009900000033'],
        ['Gilang Saputra', 'gilang.saputra@contoh.test', 'L', '2001-09-09', null],
        ['Kartika Dewi Anggraini', 'kartika.anggraini@contoh.test', 'P', '1979-12-15', null],
        ['Mulyani Astuti', 'mulyani.astuti@contoh.test', 'P', '1958-02-20', null],
    ];

    private function seedPatients(): void
    {
        $this->seed(PatientSeeder::class);
    }

    private function profileFor(string $email): PatientProfile
    {
        return PatientProfile::query()->where('email', $email)->firstOrFail();
    }

    public function test_it_creates_seven_active_patient_accounts_without_roles(): void
    {
        $this->seedPatients();

        $this->assertSame(7, User::query()->count());
        $this->assertSame(7, User::query()->patients()->count());
        $this->assertSame(7, PatientProfile::query()->count());

        foreach (self::EXPECTED as [$name, $email]) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertSame($name, $user->name, $email);
            $this->assertSame(AccountType::Patient, $user->account_type, $email);
            $this->assertTrue($user->is_active, $email);
            $this->assertFalse($user->must_change_password, $email);
            $this->assertNull($user->last_login_at, $email);
            $this->assertSame(0, $user->roles()->count(), "{$email}: pasien tidak memegang peran Spatie.");
        }
    }

    public function test_demo_password_is_stored_as_a_bcrypt_hash_and_the_accounts_can_authenticate_as_patients(): void
    {
        $this->seedPatients();

        foreach (self::EXPECTED as [, $email]) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $stored = (string) DB::table('users')->where('id', $user->id)->value('password');

            $this->assertNotSame('Demo12345', $stored, $email);
            $this->assertStringStartsWith('$2y$', $stored, $email);
            $this->assertTrue(Hash::check('Demo12345', $user->password), "{$email} harus memakai password demo bawaan.");
            $this->assertTrue(
                Auth::guard('web')->validate(['email' => $email, 'password' => 'Demo12345', 'account_type' => AccountType::Patient->value, 'is_active' => true]),
                "{$email} harus dapat masuk sebagai pasien aktif.",
            );
        }

        $this->assertFalse(Auth::guard('web')->validate(['email' => 'pasien@unpamhospital.test', 'password' => 'Salah12345', 'account_type' => AccountType::Patient->value, 'is_active' => true]));
    }

    public function test_the_configured_demo_password_is_used(): void
    {
        config(['app.demo_password' => 'Rahasia987']);

        $this->seedPatients();

        $user = User::query()->where('email', 'pasien@unpamhospital.test')->firstOrFail();
        $this->assertTrue(Hash::check('Rahasia987', $user->password));
        $this->assertFalse(Hash::check('Demo12345', $user->password));
    }

    public function test_profiles_follow_the_13_section_10_1_data(): void
    {
        $this->seedPatients();

        foreach (self::EXPECTED as $index => [$name, $email, $gender, $birthDate]) {
            $number = $index + 1;
            $profile = $this->profileFor($email);

            $this->assertSame($name, $profile->full_name, $email);
            $this->assertSame(Gender::from($gender), $profile->gender, $email);
            $this->assertSame($birthDate, $profile->birth_date->toDateString(), $email);
            $this->assertSame("Jl. Contoh No. {$number}, Pamulang, Tangerang Selatan", $profile->address, $email);
            $this->assertSame('08120000000'.$number, $profile->phone, $email);
        }

        $ids = array_map(fn (array $row): int => $this->profileFor($row[1])->id, self::EXPECTED);
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids, 'Urutan penyimpanan mengikuti P000001 sampai P000007; akun demo adalah profil pertama.');
    }

    public function test_profiles_are_online_registrations_linked_to_their_accounts(): void
    {
        $this->seedPatients();

        foreach (self::EXPECTED as [, $email]) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $profile = $this->profileFor($email);

            $this->assertSame($user->id, $profile->user_id, $email);
            $this->assertSame(BookingChannel::Online, $profile->registration_channel, $email);
            $this->assertNull($profile->created_by_user_id, $email);
            $this->assertTrue($profile->is_active, $email);
            $this->assertSame($profile->id, $user->patientProfile()->value('id'), "{$email}: relasi User::patientProfile().");
        }

        $this->assertSame(7, PatientProfile::query()->whereNotNull('user_id')->count());
        $this->assertSame(0, PatientProfile::query()->where('registration_channel', BookingChannel::Loket->value)->count(), 'Walk-in menyusul di fase 6/7.');
    }

    /** SEED-01 I15: `patient_number` = `P` + `id` 6 digit (nilai literal P000001..P000007 berlaku pada DB baru). */
    public function test_patient_number_is_formatted_from_the_profile_id(): void
    {
        $this->seedPatients();

        foreach (PatientProfile::query()->get() as $profile) {
            $this->assertSame(PatientNumberGenerator::format($profile->id), $profile->patient_number);
            $this->assertSame(1, preg_match('/^P[0-9]{6,}$/', (string) $profile->patient_number));
        }

        $this->assertSame([], DB::select("SELECT id FROM patient_profiles WHERE patient_number IS NULL OR patient_number <> CONCAT('P', LPAD(id, 6, '0'))"));
    }

    /** SEED-01 I16 dan I17 (13 §11.1) serta PAS-09. */
    public function test_profile_email_and_active_flag_match_the_account(): void
    {
        $this->seedPatients();

        $this->assertSame([], DB::select('SELECT p.id FROM patient_profiles p JOIN users u ON u.id = p.user_id WHERE p.email IS NULL OR p.email <> u.email'));
        $this->assertSame([], DB::select('SELECT p.id FROM patient_profiles p JOIN users u ON u.id = p.user_id WHERE p.is_active <> u.is_active'));

        foreach (self::EXPECTED as [, $email]) {
            $this->assertSame($email, User::query()->whereKey($this->profileFor($email)->user_id)->value('email'));
        }
    }

    public function test_consent_version_comes_from_the_settings(): void
    {
        $this->seed(SettingsSeeder::class);
        WebsiteSetting::query()->where('key', 'privacy.consent_version')->update(['value' => '2.0']);
        app(SettingsService::class)->forget();

        $this->seedPatients();

        $this->assertSame(7, PatientProfile::query()->where('consent_version', '2.0')->count());
    }

    public function test_consent_version_falls_back_to_one_point_zero_without_the_setting(): void
    {
        $this->seedPatients();

        $this->assertSame(7, PatientProfile::query()->where('consent_version', '1.0')->count());
    }

    public function test_consent_is_given_at_the_application_time(): void
    {
        $this->travelTo('2026-10-08 09:30:00');

        $this->seedPatients();

        foreach (PatientProfile::query()->get() as $profile) {
            $this->assertSame('2026-10-08 09:30:00', $profile->consent_at->format('Y-m-d H:i:s'));
        }
    }

    public function test_bpjs_is_encrypted_with_last4_for_accounts_one_two_and_four_and_null_for_the_rest(): void
    {
        $this->seedPatients();

        foreach (self::EXPECTED as [, $email, , , $bpjs]) {
            $profile = $this->profileFor($email);
            $raw = DB::table('patient_profiles')->where('id', $profile->id)->first();

            if ($bpjs === null) {
                $this->assertNull($raw->bpjs_number, $email);
                $this->assertNull($raw->bpjs_number_last4, $email);
                $this->assertNull($profile->bpjs_number, $email);
                $this->assertNull($profile->masked_bpjs, $email);

                continue;
            }

            $this->assertNotNull($raw->bpjs_number, $email);
            $this->assertStringNotContainsString($bpjs, (string) $raw->bpjs_number, "{$email}: nomor BPJS tidak boleh tersimpan polos.");
            $this->assertSame($bpjs, $profile->bpjs_number, $email);
            $this->assertSame(substr($bpjs, -4), $raw->bpjs_number_last4, $email);
            $this->assertSame(str_repeat('•', 9).substr($bpjs, -4), $profile->masked_bpjs, $email);
        }

        $this->assertSame(['0011', '0022', '0033'], PatientProfile::query()->whereNotNull('bpjs_number_last4')->orderBy('id')->pluck('bpjs_number_last4')->all());
    }

    public function test_phone_numbers_are_normalised_and_match_the_indonesian_pattern(): void
    {
        $this->seedPatients();

        foreach (self::EXPECTED as [, $email]) {
            $profile = $this->profileFor($email);
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertSame(IndonesianPhone::normalize($profile->phone), $profile->phone, "{$email}: telepon sudah ternormalisasi.");
            $this->assertSame(1, preg_match(IndonesianPhone::PATTERN, $profile->phone), "{$email}: {$profile->phone}");
            $this->assertSame(12, strlen($profile->phone), $email);
            $this->assertSame($profile->phone, $user->phone, "{$email}: telepon akun = telepon profil.");
        }
    }

    public function test_running_twice_is_idempotent_and_warns_about_existing_accounts(): void
    {
        $this->seedPatients();

        $pending = $this->artisan('db:seed', ['--class' => PatientSeeder::class, '--no-interaction' => true]);

        foreach (self::EXPECTED as [, $email]) {
            $pending->expectsOutputToContain("Akun pasien demo {$email} sudah ada, dilewati.");
        }

        $pending->assertExitCode(0);

        $this->assertSame(7, User::query()->count());
        $this->assertSame(7, PatientProfile::query()->count());
    }

    public function test_an_existing_account_is_skipped_without_being_touched_and_gets_no_profile(): void
    {
        User::factory()->patient()->create(['email' => 'budi.hartanto@contoh.test', 'name' => 'Budi Lama', 'password' => 'Rahasia123']);

        $this->artisan('db:seed', ['--class' => PatientSeeder::class, '--no-interaction' => true])
            ->expectsOutputToContain('Akun pasien demo budi.hartanto@contoh.test sudah ada, dilewati.')
            ->assertExitCode(0);

        $budi = User::query()->where('email', 'budi.hartanto@contoh.test')->firstOrFail();
        $this->assertSame('Budi Lama', $budi->name);
        $this->assertTrue(Hash::check('Rahasia123', $budi->password));
        $this->assertSame(0, $budi->patientProfile()->count());
        $this->assertSame(7, User::query()->count());
        $this->assertSame(6, PatientProfile::query()->count());
    }

    /** Review Focus 5 (bagian seeder): setiap pasien dibuat dalam satu transaksi sendiri. */
    public function test_each_patient_is_created_in_its_own_transaction(): void
    {
        PatientProfile::creating(function (PatientProfile $profile): void {
            if ($profile->full_name === 'Budi Hartanto') {
                throw new RuntimeException('Gagal uji transaksi.');
            }
        });

        $thrown = null;

        try {
            $this->app->make(PatientSeeder::class)->run();
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertSame('Gagal uji transaksi.', $thrown->getMessage());
        $this->assertTrue(User::query()->where('email', 'pasien@unpamhospital.test')->exists(), 'Pasien pertama sudah tersimpan.');
        $this->assertSame(1, PatientProfile::query()->count());
        $this->assertFalse(User::query()->where('email', 'budi.hartanto@contoh.test')->exists(), 'Akun pasien yang gagal ikut dibatalkan.');
        $this->assertSame(1, User::query()->count());
    }

    /** SEED-02 (bagian fase 5): guard lingkungan seperti `DemoUserSeeder`. */
    public function test_it_refuses_to_run_outside_the_local_and_testing_environments(): void
    {
        foreach (['production', 'staging'] as $environment) {
            $thrown = null;

            $this->inEnvironment($environment, function () use (&$thrown): void {
                try {
                    $this->app->make(PatientSeeder::class)->run();
                } catch (RuntimeException $e) {
                    $thrown = $e;
                }
            });

            $this->assertInstanceOf(RuntimeException::class, $thrown, $environment);
            $this->assertStringContainsString("PatientSeeder DILARANG di APP_ENV={$environment}", $thrown->getMessage());
            $this->assertSame(0, User::query()->count(), $environment);
            $this->assertSame(0, PatientProfile::query()->count(), $environment);
        }
    }

    /** NTF-08 dan F5-R27: seeder menulis lewat model, bukan lewat service registrasi (tanpa audit, tanpa email). */
    public function test_it_writes_no_audit_and_sends_no_notification(): void
    {
        Notification::fake();
        Mail::fake();

        $this->seedPatients();

        Notification::assertNothingSent();
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_demo_seeder_runs_the_patient_seeder_and_prints_the_patient_credentials(): void
    {
        Storage::fake('public');
        $output = new BufferedOutput;

        $exit = Artisan::call('db:seed', ['--class' => DemoSeeder::class, '--no-interaction' => true], $output);
        $text = $output->fetch();

        $this->assertSame(0, $exit);
        $this->assertSame(10, User::query()->count());
        $this->assertSame(7, PatientProfile::query()->count());
        $this->assertStringContainsString('Anisa Rahmawati', $text);
        $this->assertStringContainsString('pasien@unpamhospital.test', $text);
        $this->assertStringContainsString('Pasien', $text);
        $this->assertStringContainsString('Demo12345', $text);
        $this->assertStringContainsString('Enam akun pasien lain (@contoh.test) memakai password yang sama.', $text);
        $this->assertStringNotContainsString('budi.hartanto@contoh.test', $text, 'Hanya akun pasien demo yang masuk tabel kredensial.');
    }

    private function inEnvironment(string $environment, Closure $callback): void
    {
        $original = $this->app['env'];
        $this->app['env'] = $environment;

        try {
            $callback();
        } finally {
            $this->app['env'] = $original;
        }
    }
}
```

- [ ] **Step 3: Ubah tes lama (tool Edit); setiap `old_string` cocok tepat satu kali**

**(a) `tests/Feature/Seeding/DemoSeederWiringTest.php`**

old_string:

```php
            ['DemoUserSeeder', 'SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder', 'DoctorScheduleSeeder', 'ScheduleExceptionSeeder', 'RoomCategorySeeder', 'FacilitySeeder', 'ContentSeeder', 'ContactMessageSeeder'],
```

new_string:

```php
            ['DemoUserSeeder', 'SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder', 'DoctorScheduleSeeder', 'ScheduleExceptionSeeder', 'RoomCategorySeeder', 'FacilitySeeder', 'ContentSeeder', 'PatientSeeder', 'ContactMessageSeeder'],
```

old_string:

```php
        foreach (['SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder', 'DoctorScheduleSeeder', 'ScheduleExceptionSeeder', 'RoomCategorySeeder', 'FacilitySeeder', 'ContentSeeder', 'ContactMessageSeeder'] as $class) {
```

new_string:

```php
        foreach (['SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder', 'DoctorScheduleSeeder', 'ScheduleExceptionSeeder', 'RoomCategorySeeder', 'FacilitySeeder', 'ContentSeeder', 'PatientSeeder', 'ContactMessageSeeder'] as $class) {
```

**(b) `tests/Feature/Seeding/SeederProductionGuardTest.php`**

old_string:

```php
use App\Models\User;
use App\Models\WebsiteSetting;
```

new_string:

```php
use App\Models\PatientProfile;
use App\Models\User;
use App\Models\WebsiteSetting;
```

old_string:

```php
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
```

new_string:

```php
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\PatientSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
```

old_string:

```php
        foreach (['article_categories', 'articles', 'banners', 'galleries', 'faqs', 'insurance_partners', 'room_categories', 'facilities', 'contact_messages'] as $table) {
```

new_string:

```php
        foreach (['article_categories', 'articles', 'banners', 'galleries', 'faqs', 'insurance_partners', 'room_categories', 'facilities', 'contact_messages', 'patient_profiles'] as $table) {
```

old_string:

```php
        $this->assertSame(3, User::query()->count());
        $this->assertTrue(User::query()->where('email', 'superadmin@unpamhospital.test')->exists());
        $this->assertSame(3, Role::query()->count());
```

new_string:

```php
        $this->assertSame(10, User::query()->count()); // 3 staf + 7 akun pasien demo (fase 5)
        $this->assertSame(7, PatientProfile::query()->count());
        $this->assertTrue(User::query()->where('email', 'superadmin@unpamhospital.test')->exists());
        $this->assertTrue(User::query()->where('email', 'pasien@unpamhospital.test')->exists());
        $this->assertSame(3, Role::query()->count());
```

old_string:

```php
            ->assertExitCode(0);

        $this->assertSame(3, User::query()->count());
    }

    public function test_demo_user_seeder_throws_in_production_and_creates_no_users(): void
```

new_string:

```php
            ->assertExitCode(0);

        $this->assertSame(10, User::query()->count());
        $this->assertSame(7, PatientProfile::query()->count());
    }

    public function test_demo_user_seeder_throws_in_production_and_creates_no_users(): void
```

old_string:

```php
    public function test_demo_user_seeder_is_rejected_outside_the_local_and_testing_whitelist(): void
    {
        $this->assertDemoSeederRejected(DemoUserSeeder::class, 'staging');
    }
```

new_string:

```php
    public function test_demo_user_seeder_is_rejected_outside_the_local_and_testing_whitelist(): void
    {
        $this->assertDemoSeederRejected(DemoUserSeeder::class, 'staging');
    }

    /** SEED-02 (bagian fase 5): akun pasien demo berpassword umum tidak boleh ada di produksi atau staging. */
    public function test_patient_seeder_throws_in_production_and_staging_and_creates_no_accounts(): void
    {
        $this->assertDemoSeederRejected(PatientSeeder::class, 'production');
        $this->assertDemoSeederRejected(PatientSeeder::class, 'staging');
        $this->assertSame(0, PatientProfile::query()->count());
    }
```

**(c) `tests/Feature/Seeding/FacilitySeedersTest.php`**

old_string:

```php
        $this->assertSame(3, DB::table('users')->count());
```

new_string:

```php
        $this->assertSame(10, DB::table('users')->count()); // 3 staf + 7 akun pasien demo (fase 5)
```

**(d) `tests/Feature/Seeding/DemoSeederInvariantsTest.php`**

old_string:

```php
use App\Models\Media;
use App\Models\RoomCategory;
use App\Models\Specialty;
use Database\Seeders\DatabaseSeeder;
```

new_string:

```php
use App\Models\Media;
use App\Models\PatientProfile;
use App\Models\RoomCategory;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
```

old_string:

```php
 * SEED-01 (bagian master data, fase 2), SEED-03. Kueri K-5/K-23 dari 09 §12; invarian lengkap
```

new_string:

```php
 * SEED-01 (bagian master data fase 2 dan invarian I15 sampai I17 fase 5), SEED-03. Kueri K-5/K-23 dari 09 §12; invarian lengkap
```

old_string:

```php
    public function test_every_doctor_has_exactly_one_primary_specialty_and_archive_state_is_consistent(): void
```

new_string:

```php
    /** SEED-01 fase 5: angka acuan 13 §11.2 untuk pasien berakun dan invarian I15, I16, I17 (13 §11.1) sebagai kueri. */
    public function test_phase_5_counts_and_patient_invariants_match_the_reference_numbers(): void
    {
        $this->seedDemo();

        $this->assertSame(10, User::query()->count());
        $this->assertSame(3, User::query()->where('account_type', 'staff')->count());
        $this->assertSame(7, User::query()->where('account_type', 'patient')->count());
        $this->assertSame(10, User::query()->where('email', 'like', '%.test')->count());
        $this->assertSame(3, DB::table('model_has_roles')->count(), 'Akun pasien tidak memegang peran.');
        $this->assertSame(7, PatientProfile::query()->count());
        $this->assertSame(7, PatientProfile::query()->where('registration_channel', 'online')->whereNotNull('user_id')->count());
        $this->assertSame(0, PatientProfile::query()->where('registration_channel', 'loket')->count(), 'Walk-in menyusul di fase 6/7.');
        $this->assertSame(3, PatientProfile::query()->whereNotNull('bpjs_number_last4')->count());
        $this->assertSame('Anisa Rahmawati', PatientProfile::query()->where('email', 'pasien@unpamhospital.test')->value('full_name'));

        // I15, I16, I17 (13 §11.1); I15 diperketat dengan IS NULL karena kolom `patient_number` NULLable.
        $this->assertSame([], DB::select("SELECT id FROM patient_profiles WHERE patient_number IS NULL OR patient_number <> CONCAT('P', LPAD(id, 6, '0'))"));
        $this->assertSame([], DB::select('SELECT p.id FROM patient_profiles p JOIN users u ON u.id = p.user_id WHERE p.email IS NULL OR p.email <> u.email'));
        $this->assertSame([], DB::select('SELECT p.id FROM patient_profiles p JOIN users u ON u.id = p.user_id WHERE p.is_active <> u.is_active'));
    }

    public function test_every_doctor_has_exactly_one_primary_specialty_and_archive_state_is_consistent(): void
```

old_string:

```php
        foreach (['SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder', 'DoctorScheduleSeeder', 'ScheduleExceptionSeeder', 'RoomCategorySeeder', 'FacilitySeeder', 'ContentSeeder', 'ContactMessageSeeder'] as $seeder) {
            $this->artisan('db:seed', ['--class' => "Database\\Seeders\\{$seeder}", '--no-interaction' => true])->assertExitCode(0);
        }
```

new_string:

```php
        foreach (['SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder', 'DoctorScheduleSeeder', 'ScheduleExceptionSeeder', 'RoomCategorySeeder', 'FacilitySeeder', 'ContentSeeder', 'PatientSeeder', 'ContactMessageSeeder'] as $seeder) {
            $this->artisan('db:seed', ['--class' => "Database\\Seeders\\{$seeder}", '--no-interaction' => true])->assertExitCode(0);
        }

        $this->assertSame([10, 7], [User::query()->count(), PatientProfile::query()->count()]);
```

- [ ] **Step 4: Jalankan tes, harus GAGAL (RED)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter='PatientSeederTest|DemoSeederWiringTest|SeederProductionGuardTest|DemoSeederInvariantsTest|FacilitySeedersTest'
```
Expected: FAIL. `PatientSeederTest` semua gagal dengan `Target class [Database\Seeders\PatientSeeder] does not exist.` atau `Class "Database\Seeders\PatientSeeder" does not exist` (seeder belum ada). `DemoSeederWiringTest::test_demo_seeder_runs_master_seeders_in_the_13_section_2_1_order` gagal pada `assertNotContains(false, $positions)` (`PatientSeeder::class` belum ada di `DemoSeeder.php`). `SeederProductionGuardTest::test_database_seeder_runs_the_demo_seeder_in_testing_environment` dan `…warns_and_skips…` gagal dengan `Failed asserting that 3 is identical to 10`, serta tes baru `…patient_seeder_throws…` gagal (kelas tidak ada). `DemoSeederInvariantsTest::test_phase_5_counts…` dan `…each_master_seeder_skips…` gagal (`3` bukan `10`; seeder tidak ada). `FacilitySeedersTest::test_the_database_seeder_runs_all_three…` gagal dengan `3 is identical to 10`.

- [ ] **Step 5: Implementasi `PatientSeeder` (tool Write) `database/seeders/PatientSeeder.php`**

`database/seeders/PatientSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\BookingChannel;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Patients\PatientNumberGenerator;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Data demo pasien berakun (13 §10.1, F5-R27). Hanya `local`/`testing`; idempoten per email; satu transaksi per pasien.
 *
 * Tujuh akun pasien aktif (tanpa peran) beserta profil kanal `online` P000001 sampai P000007 pada DB baru. Seeder menulis
 * langsung lewat model (BUKAN `PatientRegistrationService`), sehingga tidak ada audit dan tidak ada email selamat datang.
 * Pasien walk-in (P000008 sampai P000013) dan appointment menyusul di fase 6/7.
 *
 * Telepon `0812-0000-00NN` ternormalisasi menjadi `08120000000{n}`; alamat "Jl. Contoh No. {n}, Pamulang, Tangerang
 * Selatan"; `consent_version` dari pengaturan `privacy.consent_version`. Nomor BPJS fiktif 13 digit disimpan terenkripsi
 * dan `bpjs_number_last4` diisi hook `PatientProfile::saving`.
 */
class PatientSeeder extends Seeder
{
    /**
     * Urutan = nomor n (1..7); `bpjs` null bila pasien tidak punya nomor BPJS. Akun pertama adalah akun demo
     * (`DemoSeeder` menampilkannya pada tabel kredensial).
     *
     * @var list<array{name: string, email: string, gender: string, birth_date: string, bpjs: ?string}>
     */
    public const ACCOUNTS = [
        ['name' => 'Anisa Rahmawati', 'email' => 'pasien@unpamhospital.test', 'gender' => 'P', 'birth_date' => '1994-05-12', 'bpjs' => '0009900000011'],
        ['name' => 'Budi Hartanto', 'email' => 'budi.hartanto@contoh.test', 'gender' => 'L', 'birth_date' => '1985-11-03', 'bpjs' => '0009900000022'],
        ['name' => 'Dewi Susilowati', 'email' => 'dewi.susilowati@contoh.test', 'gender' => 'P', 'birth_date' => '1990-07-21', 'bpjs' => null],
        ['name' => 'Fitri Handayani', 'email' => 'fitri.handayani@contoh.test', 'gender' => 'P', 'birth_date' => '1998-01-30', 'bpjs' => '0009900000033'],
        ['name' => 'Gilang Saputra', 'email' => 'gilang.saputra@contoh.test', 'gender' => 'L', 'birth_date' => '2001-09-09', 'bpjs' => null],
        ['name' => 'Kartika Dewi Anggraini', 'email' => 'kartika.anggraini@contoh.test', 'gender' => 'P', 'birth_date' => '1979-12-15', 'bpjs' => null],
        ['name' => 'Mulyani Astuti', 'email' => 'mulyani.astuti@contoh.test', 'gender' => 'P', 'birth_date' => '1958-02-20', 'bpjs' => null],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('PatientSeeder DILARANG di APP_ENV='.app()->environment().'. Akun pasien contoh berpassword umum.');
        }

        $password = (string) config('app.demo_password');
        $consentVersion = app(SettingsService::class)->string('privacy.consent_version', '1.0');

        foreach (self::ACCOUNTS as $index => $account) {
            if (User::query()->where('email', $account['email'])->exists()) {
                $this->command?->warn("Akun pasien demo {$account['email']} sudah ada, dilewati.");

                continue;
            }

            $number = $index + 1;
            $phone = '08120000000'.$number;
            $address = "Jl. Contoh No. {$number}, Pamulang, Tangerang Selatan";

            DB::transaction(function () use ($account, $password, $consentVersion, $phone, $address): void {
                $user = new User;
                $user->forceFill([
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'phone' => $phone,
                    'password' => $password,
                    'account_type' => AccountType::Patient,
                    'is_active' => true,
                    'must_change_password' => false,
                ])->save();

                $profile = new PatientProfile;
                $profile->fill([
                    'full_name' => $account['name'],
                    'birth_date' => $account['birth_date'],
                    'gender' => $account['gender'],
                    'phone' => $phone,
                    'email' => $account['email'],
                    'address' => $address,
                    'bpjs_number' => $account['bpjs'],
                    'registration_channel' => BookingChannel::Online,
                    'consent_at' => now(),
                    'consent_version' => $consentVersion,
                    'is_active' => true,
                ]);
                $profile->forceFill(['user_id' => $user->id, 'created_by_user_id' => null])->save();

                // Nomor pasien dari id profil, dalam transaksi yang sama (F5-R10).
                $profile->forceFill(['patient_number' => PatientNumberGenerator::format($profile->id)])->save();
            });
        }
    }
}
```

- [ ] **Step 6: Perbarui `DemoSeeder` (baca berkas dulu dengan tool Read, lalu tool Write; berkas kecil, isi utuh baru)**

`database/seeders/DemoSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoSeeder DILARANG di APP_ENV='.app()->environment().'. Data demo memuat akun berpassword umum.');
        }

        if (User::query()->where('email', 'superadmin@unpamhospital.test')->exists()) {
            $this->command?->warn('Data demo sudah ada. Jalankan: php artisan migrate:fresh --seed');

            return;
        }

        $this->command?->warn('MODE DEMO: akun & data contoh dibuat. Jangan gunakan di produksi.');
        config(['mail.default' => 'array']); // surat notifikasi seeding dibuang, bukan ditumpuk di log

        // Seeder data master (fase 2 dst.) ditambahkan ke daftar ini mengikuti urutan 13 §2.1.
        // PatientSeeder (fase 5: 7 akun pasien berakun) berada di antara ContentSeeder dan ContactMessageSeeder;
        // AppointmentSeeder (fase 6/7) disisipkan tepat sesudah PatientSeeder.
        $this->call([
            DemoUserSeeder::class,
            SpecialtySeeder::class,
            ClinicSeeder::class,
            DoctorSeeder::class,
            DoctorScheduleSeeder::class,
            ScheduleExceptionSeeder::class,
            RoomCategorySeeder::class,
            FacilitySeeder::class,
            ContentSeeder::class,
            PatientSeeder::class,
            ContactMessageSeeder::class,
        ]);

        $password = (string) config('app.demo_password');

        $rows = array_map(
            fn (array $account): array => [$account['name'], $account['email'], $account['role'], $password],
            DemoUserSeeder::ACCOUNTS,
        );

        // Satu akun pasien demo pada tabel kredensial; enam akun pasien lain memakai password yang sama (F5-R27).
        $demoPatient = PatientSeeder::ACCOUNTS[0];
        $rows[] = [$demoPatient['name'], $demoPatient['email'], 'Pasien', $password];

        $this->command?->table(['Nama', 'Email', 'Peran', 'Password'], $rows);
        $this->command?->line('Enam akun pasien lain (@contoh.test) memakai password yang sama.');
    }
}
```

- [ ] **Step 7: Jalankan tes terfilter, harus lulus (GREEN)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter='PatientSeederTest|DemoSeederWiringTest|SeederProductionGuardTest|DemoSeederInvariantsTest|FacilitySeedersTest|DemoUserSeederTest|ContentSeederTest|ScheduleSeederTest|PageSeederTest|DatabaseSeederWiringTest|RolePermissionSeederTest|SettingsSeederTest'
```
Expected: seluruh tes PASS (`PatientSeederTest` 18 tes; `SeederProductionGuardTest` +1; `DemoSeederInvariantsTest` +1). Bila `test_demo_seeder_runs_the_patient_seeder_and_prints_the_patient_credentials` gagal pada asersi tabel, cetak `$text` sementara untuk melihat bentuk tabel (judul kolom `Nama`, `Email`, `Peran`, `Password`) dan sesuaikan HANYA asersi substringnya, bukan isi seeder. Waktu `DemoSeederInvariantsTest`/`FacilitySeedersTest`/`ScheduleSeederTest` panjang (menjalankan `DemoSeeder` penuh dengan 83 gambar).

- [ ] **Step 8: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint database\seeders\PatientSeeder.php database\seeders\DemoSeeder.php tests\Feature\Seeding\PatientSeederTest.php tests\Feature\Seeding\DemoSeederWiringTest.php tests\Feature\Seeding\SeederProductionGuardTest.php tests\Feature\Seeding\DemoSeederInvariantsTest.php tests\Feature\Seeding\FacilitySeedersTest.php
& $PHP vendor\bin\pint --test
git status --short
git add database/seeders/PatientSeeder.php database/seeders/DemoSeeder.php tests/Feature/Seeding/PatientSeederTest.php tests/Feature/Seeding/DemoSeederWiringTest.php tests/Feature/Seeding/SeederProductionGuardTest.php tests/Feature/Seeding/DemoSeederInvariantsTest.php tests/Feature/Seeding/FacilitySeedersTest.php
git commit -m @'
feat(fase-5): PatientSeeder (tujuh akun pasien demo), DemoSeeder memanggilnya, dan tes seeder diselaraskan

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: Pint `{"result":"passed"}` pada `--test`; `git status --short` hanya memuat tujuh berkas di atas (dan `.superpowers/` tidak terlacak); commit dibuat.

#### Catatan penulis (konteks untuk implementer dan reviewer)

1. **Digest 8.9 menyarankan mengubah `DemoUserSeederTest` dan menambah konstanta `PATIENT_ACCOUNTS` di `DemoUserSeeder`; tidak dilakukan.** Kerangka F5-R27 mengikat: `DemoUserSeeder` tidak diubah (tetap 3 staf) dan akun pasien dibuat `PatientSeeder`. `DemoUserSeederTest` hanya memanggil `DemoUserSeeder`, jadi angka `3` di sana tetap benar.
2. **13 §2.2 baris 4.1** ("`DemoUserSeeder` … 3 staf + 7 akun pasien") bertentangan dengan F5-R27; direkonsiliasi di Task 8 (D-entry seeder fase 5).
3. **`consent_version`.** 13 §10.1 menulis `consent_version = 1.0` tetap; kerangka F meminta nilai dari pengaturan `privacy.consent_version` (bawaan `1.0`). Task 8 mengoreksi kalimat 13 §10.1.
4. **I15 diperketat** dengan `patient_number IS NULL OR …` pada kueri tes (kolom NULLable), berbeda dari SQL 13 §11.1 yang hanya `<>`. `scripts/sql/seed-invariants.sql` tetap menyusul di fase 9 (digest 11 butir 7: cukup kueri di `DemoSeederInvariantsTest`).
5. **Tabel konsol** diuji lewat `Artisan::call(..., $buffer)`; bila implementer menemukan bentuk keluaran tabel berbeda dari asumsi (mis. kolom dipotong), hanya asersi substring yang disesuaikan.
6. **`RolePermissionSeederTest::test_database_seeder_runs_the_role_permission_seeder`** menjalankan `DatabaseSeeder` penuh dan hanya menegaskan 3 peran/19 izin, sehingga tidak berubah.

---

### Task 8: Matriks akses fase 5, `Phase5RoutePermissionTest`, tes keamanan, rekonsiliasi dokumen, README "Fase 5", dan gerbang fase 5

**Files:**
- Create: `tests/Feature/Access/PatientPageMatrixTest.php`
- Create: `tests/Feature/Access/Phase5RoutePermissionTest.php`
- Modify (tes): `tests/Feature/Security/SecurityHeadersTest.php` (`htmlPages()` +3 halaman), `tests/Feature/View/RawOutputScanTest.php` (filter atribut event inline mencakup `auth/`, `patient/`, dan `components/layouts/`)
- Modify (dokumen): `docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/{00-keputusan-inti,04-peran-hak-akses,06-alur,11-route-controller-model-service,12-aturan-bisnis-validasi-keamanan,13-data-contoh,15-pengujian-penerimaan,16-instalasi-operasional,17-keputusan-konfirmasi}.md`
- Modify: `README.md` (bagian "Fase 5" dan satu kalimat di bagian Fase 1)
- Tidak diubah: `tests/Feature/Access/AdminPageMatrixTest.php` (perubahan `dashboardFor` milik Task 3, F5-R29), `AdminRouteMiddlewareTest.php`, `Phase2RoutePermissionTest.php`, `Phase3RoutePermissionTest.php`, `Phase4aRoutePermissionTest.php`, `Phase4bRoutePermissionTest.php` (tes total 106 route admin tetap benar), `docs/superpowers/CHECKPOINT.md` dan "Catatan Eksekusi" rencana ini (ditulis koordinator setelah final review).

**Interfaces:**
- Consumes: semua Task 1 sampai 7.
  - 13 route baru (kerangka C.1): `login`, `login.store`, `logout`, `register`, `register.store`, `password.request`, `password.email`, `password.reset`, `password.update`, `patient.dashboard`, `patient.profile.edit`, `patient.profile.update`, `patient.password.update`.
  - `Tests\Support\InteractsWithPatients` (`patientPassword()`, `createPatient()`, `registrationPayload()`), `InteractsWithStaff::createStaff()`.
  - `PatientSeeder` dan tujuh akun pasien demo (Task 7), dipakai langkah MANUAL.
- Produces:
  - Matriks 15 §3.2 baris #23 sampai #28 × lima aktor (`guest`, `patient`, `content-admin`, `registration-officer`, `super-admin`) dengan F5-R5 (staf di #27 dan #28 = `→A`, redirect ke `admin.dashboard`), mutasi `PUT patient.profile.update`, `PUT patient.password.update`, `POST logout`, serta AKS-02 dan AKS-06 yang dijalankan ulang dengan pasien dari `createPatient()` pada URL admin yang sudah ada (termasuk `POST /admin/dokter`).
  - `Phase5RoutePermissionTest`: 13 route (nama, URI, method, middleware, action), tidak ada route `patient.appointments.*` dan `patient.notifications.*`, tepat empat route di bawah `/pasien`, route admin tetap 106, total 141, izin tetap 19.
  - Rekonsiliasi blueprint: baris 17 §4 D-140 sampai D-151, K-07 dan K-22 dipindahkan ke 17 §1, serta penyesuaian 00 J.6, 04, 06, 11, 12, 13, 15, 16.
  - README bagian "Fase 5" dan gerbang fase 5 (Pint, `npm run build`, `route:list` 141/106, suite penuh di latar belakang, langkah MANUAL koordinator).

**Test IDs:** AKS-01 sampai AKS-05 (#23 sampai #28), AKS-02 dan AKS-06 (re-run dengan pasien nyata, kriteria keluar fase 5), AKS-03 (staf pada #27/#28 = `→A`, D-142), KNS-01 (bagian fase 5: 13 nama route, tidak ada route fase 6), KNS-02 (otomatis, route `patient.*` memakai `auth`, `account:patient`, `active`), KNS-03 (izin tetap 19), SEK-15 (statis: tanpa atribut event inline di `auth/` dan `patient/`), SEC-header (halaman `/masuk`, `/daftar`, `/lupa-password`), dan Review Focus 4 (staf di `/pasien` ke `admin.dashboard`, pasien membuka halaman tamu ke dashboard, mutasi per aktor).

**Catatan teknis (diverifikasi dari source):**
1. **Pola matriks.** `AdminPageMatrixTest` memakai `ACTORS`, konstanta hasil per baris, tiga data provider, dan `userFor()`/`dashboardFor()`/`parameters()`. `PatientPageMatrixTest` meniru polanya tetapi aktor pasien dibuat dengan `createPatient()` (pasien tanpa profil akan 404 di dashboard dan profil, F5-R13). Hasil: `200` tampil, `L` redirect ke `route('login')` (BUKAN `admin.login`), `D` redirect ke dashboard sesuai tipe akun (pasien `patient.dashboard`, staf `admin.dashboard`), `A` redirect ke `admin.dashboard` (staf di portal pasien, F5-R5 dan `EnsureAccountType`: non-JSON redirect, JSON 403), `G` tamu ditolak (redirect `login`), `V` lolos otorisasi lalu ditolak validasi (302 dengan galat sesi), `X` keluar (redirect `home` dan tamu).
2. **Penyimpangan dari 15 §3.2 (dicatat sebagai D-142).** 15 §3.2 menulis `403` untuk staf pada #27 sampai #30 dan AKS-03 menulis "area pasien 403". Kode fase 1 (`EnsureAccountType`, 11 §7.1) mengalihkan staf non-JSON ke `admin.dashboard`; hanya permintaan JSON mendapat 403. Matriks mengikuti kode (F5-R5); teks 15 disesuaikan di Step 11.
3. **`POST /keluar` oleh staf.** F5-R8: route `logout` hanya `auth`; staf yang memakainya ikut keluar (redirect ke `home`, tanpa audit). Matriks menegaskan itu untuk ketiga peran staf.
4. **Hitungan route.** `route:list --except-vendor` = 128 sebelum fase 5 (106 admin) dan `route:list` penuh = 131 (tiga route vendor: `storage.local`, `storage.local.upload`, `up`, semuanya `Closure`). Tes menghitung route yang `getActionName()`-nya berawalan `App\` (setara `--except-vendor`): 128 + 13 = **141**; route admin dihitung dari URI (`admin` atau `admin/*`) = 106. `Phase4bRoutePermissionTest::test_the_admin_area_has_one_hundred_and_six_routes_after_phase_4b` tetap benar dan tidak diubah.
5. **Middleware.** `gatherMiddleware()` mengembalikan `['web', 'guest', 'throttle:login']` untuk `login.store` (grup `web` dari `withRouting(web:)`) dan `['web', 'auth', 'account:patient', 'active']` untuk route portal (grup di `bootstrap/app.php` `then:`). Tes membandingkan larik string persis (`array_values(array_filter(..., 'is_string'))`), sehingga urutan `web, auth, account:patient, active` terkunci (kerangka C.1).
6. **AKS-06 dan route yang belum ada.** `/admin/pasien` (fase 7) dan `/admin/pengaturan` (fase 8) belum terdaftar sehingga menjawab 404, bukan 403 (digest 10 butir 9). Tes menegaskan 404 sebagai carry yang wajib diubah menjadi 403 di fase 7 dan 8; URL admin yang sudah ada diuji 403.
7. **`SecurityHeadersTest::sensitivePaths()`** sudah memuat `/masuk`, `/daftar`, `/lupa-password`, `/reset-password/abc123`, `/pasien`, `/pasien/pendaftaran/baru` (tidak diubah); `htmlPages()` ditambah tiga halaman tamu fase 5 (semuanya 200 tanpa login).
8. **`RawOutputScanTest`.** Tes `test_raw_blade_output_is_only_used_for_the_rich_text_columns` sudah memindai SEMUA view dan mengunci tepat lima baris `{!!`, jadi view `auth/*` dan `patient/*` otomatis tercakup. Filter atribut event inline hanya mencakup `public/` dan beberapa komponen; F5-R29 memperluasnya. Pencarian `\son[a-z]{3,}\s*=\s*["']` pada `components/layouts/*.blade.php` saat ini tidak menemukan apa pun (diverifikasi).
9. **Dokumen.** Semua jangkar Edit di bawah dicocokkan dengan berkas nyata (masing-masing tepat satu kali) pada keadaan sebelum fase 5. Nomor D terakhir di 17 adalah **D-139**; baris baru dimulai dari **D-140** dan berakhir di **D-151** (12 baris), sehingga 17 memuat 139 baris D sebelum dan 151 sesudah tugas ini.
10. **Tes karakterisasi.** `PatientPageMatrixTest` dan `Phase5RoutePermissionTest` mengunci perilaku yang dibangun Task 3 sampai 6, sehingga lulus sejak pertama dijalankan; bukti bahwa tes tidak kosong ada pada Step 5 (langkah sanity: satu perubahan sementara yang harus membuat tes gagal, lalu dikembalikan).
11. **DB pengembangan.** Implementer TIDAK menjalankan `migrate:fresh --seed`; Step 17 dan 18 dijalankan koordinator setelah pemilik menulis persis "Ya, jalankan migrate:fresh --seed".
12. Jumlah tes baru: `PatientPageMatrixTest` 50 kasus (provider `matrix` 30, `mutations` 15, plus 5 tes lain), `Phase5RoutePermissionTest` 27 kasus (13 kasus provider plus 14 tes), plus 3 kasus `SecurityHeadersTest` (provider) dan 0 tes baru di `RawOutputScanTest`.

- [ ] **Step 1: Verifikasi prasyarat**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
git log --oneline -10
$all = & $PHP artisan route:list --except-vendor --json | ConvertFrom-Json
"route total: $($all.Count)"
"route admin: $(($all | Where-Object { $_.uri -eq 'admin' -or $_.uri -like 'admin/*' }).Count)"
$all | Where-Object { $_.uri -eq 'pasien' -or $_.uri -like 'pasien/*' } | ForEach-Object { "$($_.method) $($_.uri) $($_.name)" }
$all | Where-Object { $_.name -in 'login', 'login.store', 'logout', 'register', 'register.store', 'password.request', 'password.email', 'password.reset', 'password.update' } | ForEach-Object { "$($_.method) $($_.uri) $($_.name)" }
Test-Path tests\Support\InteractsWithPatients.php
```
Expected: `fase-5-akun-pasien`; `git status` bersih (kecuali `.superpowers/`); commit Task 1 sampai 7 terlihat; `route total: 141`; `route admin: 106`; empat baris `pasien` (`GET|HEAD pasien patient.dashboard`, `GET|HEAD pasien/profil patient.profile.edit`, `PUT pasien/profil patient.profile.update`, `PUT pasien/password patient.password.update`); sembilan baris auth; `True`. Bila `route total` bukan 141, hentikan dan laporkan ke koordinator (Task 3 sampai 6 belum lengkap).

**(a) Tes akses**

- [ ] **Step 2: Tulis tes (tool Write) `tests/Feature/Access/PatientPageMatrixTest.php`**

`tests/Feature/Access/PatientPageMatrixTest.php`:

```php
<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 15 §3.2 baris #23 sampai #28 × lima aktor (AKS-01 sampai AKS-05) dengan F5-R5, ditambah matriks mutasi non-GET
 * (15 §3 catatan) untuk `patient.profile.update`, `patient.password.update`, dan `logout`, serta AKS-02 dan AKS-06
 * dijalankan ulang dengan pasien nyata (`createPatient()`). Baris #29 dan #30 (`patient.appointments.*`) menyusul di fase 6.
 *
 * Hasil: 200 = tampil, L = redirect ke `login` (BUKAN `admin.login`), D = redirect ke dashboard sesuai tipe akun,
 * A = staf di portal pasien dialihkan ke `admin.dashboard` (F5-R5, D-142; menggantikan "403" di 15 §3.2),
 * G = tamu ditolak (redirect `login`), V = lolos otorisasi lalu ditolak validasi (302 dengan galat), X = keluar.
 */
class PatientPageMatrixTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const ACTORS = ['guest', 'patient', 'content-admin', 'registration-officer', 'super-admin'];

    /** #23 sampai #26 (`guest`): Tamu 200; semua akun yang login diarahkan ke dashboard sesuai tipe. */
    private const GUEST_ONLY = ['200', 'D', 'D', 'D', 'D'];

    /** #27 dan #28: Tamu →L, Pasien 200, staf →A (F5-R5). */
    private const PORTAL = ['L', '200', 'A', 'A', 'A'];

    /** Mutasi profil dan password: Tamu G, Pasien V (lolos otorisasi, galat validasi), staf A. */
    private const PATIENT_MUTATION = ['G', 'V', 'A', 'A', 'A'];

    /** `POST logout` (hanya `auth`): Tamu G; Pasien dan semua staf X (keluar, F5-R8). */
    private const LOGOUT = ['G', 'X', 'X', 'X', 'X'];

    /** @return array<string, array{string, ?string, string, string}> */
    public static function matrix(): array
    {
        // Urutan hasil mengikuti ACTORS: Tamu, Pasien, Admin Konten, Petugas, Super Admin.
        $rows = [
            '23 Registrasi' => ['register', null, self::GUEST_ONLY],
            '24 Login pasien' => ['login', null, self::GUEST_ONLY],
            '25 Permintaan reset' => ['password.request', null, self::GUEST_ONLY],
            '26 Form reset' => ['password.reset', 'token', self::GUEST_ONLY],
            '27 Dashboard pasien' => ['patient.dashboard', null, self::PORTAL],
            '28 Profil dan akun' => ['patient.profile.edit', null, self::PORTAL],
        ];

        $cases = [];

        foreach ($rows as $label => [$routeName, $kind, $expected]) {
            foreach (self::ACTORS as $index => $actor) {
                $cases["{$label} / {$actor}"] = [$routeName, $kind, $actor, $expected[$index]];
            }
        }

        return $cases;
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function mutations(): array
    {
        $rows = [
            'PUT ubah profil' => ['put', 'patient.profile.update', self::PATIENT_MUTATION],
            'PUT ganti password' => ['put', 'patient.password.update', self::PATIENT_MUTATION],
            'POST keluar' => ['post', 'logout', self::LOGOUT],
        ];

        $cases = [];

        foreach ($rows as $label => [$method, $routeName, $expected]) {
            foreach (self::ACTORS as $index => $actor) {
                $cases["{$label} / {$actor}"] = [$method, $routeName, $actor, $expected[$index]];
            }
        }

        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_patient_page_matrix(string $routeName, ?string $kind, string $actor, string $expected): void
    {
        $user = $this->userFor($actor);

        if ($user !== null) {
            $this->actingAs($user);
        }

        $response = $this->get(route($routeName, $this->parameters($kind)));

        match ($expected) {
            '200' => $response->assertOk(),
            'L' => $response->assertRedirect(route('login')),
            'D' => $response->assertRedirect($this->dashboardFor($actor)),
            'A' => $response->assertRedirect(route('admin.dashboard')),
        };
    }

    #[DataProvider('mutations')]
    public function test_mutation_matrix_per_actor(string $method, string $routeName, string $actor, string $expected): void
    {
        $user = $this->userFor($actor);

        if ($user !== null) {
            $this->actingAs($user);
        }

        $response = $this->{$method}(route($routeName));

        match ($expected) {
            'G' => $response->assertRedirect(route('login')),
            'A' => $response->assertRedirect(route('admin.dashboard')),
            'V' => $this->assertPassedAuthorizationButFailedValidation($response),
            'X' => $this->assertLoggedOut($response),
        };
    }

    /** Staf tidak pernah mencapai controller portal pasien: JSON mendapat 403 (F5-R5), bukan redirect. */
    public function test_staff_get_403_on_the_patient_area_for_json_requests(): void
    {
        foreach (['content-admin', 'registration-officer', 'super-admin'] as $role) {
            $staff = $this->createStaff($role);

            $this->actingAs($staff)->getJson('/pasien')->assertForbidden();
            $this->actingAs($staff)->getJson('/pasien/profil')->assertForbidden();
            $this->actingAs($staff)->putJson('/pasien/profil', [])->assertForbidden();
            $this->actingAs($staff)->putJson('/pasien/password', [])->assertForbidden();
        }
    }

    /** AKS-02: Pasien aktif; publik 200, portal pasien 200, halaman admin 403, halaman khusus tamu →D. */
    public function test_aks_02_patient_sees_public_and_portal_pages_but_not_admin_pages(): void
    {
        $this->actingAs($this->createPatient());

        foreach (['home', 'about', 'vision-mission', 'clinics.index', 'doctors.index', 'schedules.index', 'facilities.index', 'rooms.index', 'insurance', 'registration-guide', 'articles.index', 'galleries.index', 'faq', 'contact', 'privacy'] as $name) {
            $this->get(route($name))->assertOk();
        }

        foreach (['patient.dashboard', 'patient.profile.edit'] as $name) {
            $this->get(route($name))->assertOk();
        }

        foreach (['admin.dashboard', 'admin.password.edit', 'admin.doctors.index', 'admin.clinics.index', 'admin.specialties.index', 'admin.schedules.index', 'admin.sessions.index', 'admin.exceptions.index', 'admin.pages.index', 'admin.articles.index', 'admin.facilities.index', 'admin.room-categories.index', 'admin.messages.index'] as $name) {
            $this->get(route($name))->assertForbidden();
        }

        foreach (['register', 'login', 'password.request', 'admin.login'] as $name) {
            $this->get(route($name))->assertRedirect(route('patient.dashboard'));
        }

        $this->get(route('password.reset', ['token' => 'token-contoh']))->assertRedirect(route('patient.dashboard'));
    }

    /** AKS-03 (bagian fase 5): Super Admin membuka halaman admin 200 dan portal pasien →A (bukan 403, D-142). */
    public function test_aks_03_super_admin_is_redirected_away_from_the_patient_area(): void
    {
        $admin = $this->createStaff('super-admin');

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('patient.dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin)->get(route('patient.profile.edit'))->assertRedirect(route('admin.dashboard'));
    }

    /** AKS-06: URL admin yang ada ditolak 403 untuk pasien, termasuk POST `/admin/dokter`, tanpa konten admin. */
    public function test_aks_06_patient_is_forbidden_from_existing_admin_urls_without_admin_content(): void
    {
        $patient = $this->createPatient();

        foreach (['/admin', '/admin/dokter', '/admin/jadwal', '/admin/konten/halaman', '/admin/fasilitas', '/admin/kamar', '/admin/pesan'] as $url) {
            $this->actingAs($patient)
                ->get($url)
                ->assertForbidden()
                ->assertDontSee('admin-sidebar', false)
                ->assertDontSee('Menu admin');
        }

        foreach (['/admin/dokter', '/admin/fasilitas', '/admin/kamar'] as $url) {
            $this->actingAs($patient)->post($url)->assertForbidden();
        }
    }

    /**
     * AKS-06 (carry): `/admin/pasien` dan `/admin/pengaturan` belum punya route sampai fase 7 dan 8, sehingga menjawab 404.
     * Fase 7 dan 8 mengubah tes ini menjadi 403 (15 AKS-06).
     */
    public function test_aks_06_urls_that_do_not_exist_yet_answer_404_until_phases_7_and_8(): void
    {
        $patient = $this->createPatient();

        $this->actingAs($patient)->get('/admin/pasien')->assertNotFound();
        $this->actingAs($patient)->get('/admin/pengaturan')->assertNotFound();
        $this->actingAs($patient)->put('/admin/pengaturan')->assertNotFound();
    }

    private function assertPassedAuthorizationButFailedValidation(TestResponse $response): void
    {
        $response->assertStatus(302);
        $response->assertSessionHasErrors();

        $this->assertNotSame(route('login'), $response->headers->get('Location'));
        $this->assertNotSame(route('admin.dashboard'), $response->headers->get('Location'));
    }

    private function assertLoggedOut(TestResponse $response): void
    {
        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }

    /** @return array<string, string> */
    private function parameters(?string $kind): array
    {
        return match ($kind) {
            null => [],
            'token' => ['token' => 'token-contoh'],
        };
    }

    private function userFor(string $actor): ?User
    {
        return match ($actor) {
            'guest' => null,
            'patient' => $this->createPatient(),
            default => $this->createStaff($actor),
        };
    }

    private function dashboardFor(string $actor): string
    {
        return $actor === 'patient' ? route('patient.dashboard') : route('admin.dashboard');
    }
}
```

- [ ] **Step 3: Tulis tes (tool Write) `tests/Feature/Access/Phase5RoutePermissionTest.php`**

`tests/Feature/Access/Phase5RoutePermissionTest.php`:

```php
<?php

namespace Tests\Feature\Access;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * KNS-01 (bagian fase 5), KNS-02 (route `patient.*` berpagar), dan KNS-03 (izin tetap 19): 13 route baru persis
 * kerangka C.1 (nama, URI, method, middleware, action), tidak ada route fase 6 (`patient.appointments.*`,
 * `patient.notifications.*`), tepat empat route di bawah `/pasien`, route admin tetap 106, dan total 141.
 */
class Phase5RoutePermissionTest extends TestCase
{
    use RefreshDatabase;

    private const AUTH_NAMESPACE = 'App\\Http\\Controllers\\Auth\\';

    private const PATIENT_NAMESPACE = 'App\\Http\\Controllers\\Patient\\';

    /** @return array<string, array{string, string, list<string>, list<string>, string}> */
    public static function routes(): array
    {
        $guest = ['web', 'guest'];
        $portal = ['web', 'auth', 'account:patient', 'active'];
        $get = ['GET', 'HEAD'];

        $rows = [
            'login' => ['masuk', $get, $guest, self::AUTH_NAMESPACE.'LoginController@create'],
            'login.store' => ['masuk', ['POST'], ['web', 'guest', 'throttle:login'], self::AUTH_NAMESPACE.'LoginController@store'],
            'logout' => ['keluar', ['POST'], ['web', 'auth'], self::AUTH_NAMESPACE.'LoginController@destroy'],
            'register' => ['daftar', $get, $guest, self::AUTH_NAMESPACE.'RegisterController@create'],
            'register.store' => ['daftar', ['POST'], ['web', 'guest', 'throttle:register'], self::AUTH_NAMESPACE.'RegisterController@store'],
            'password.request' => ['lupa-password', $get, $guest, self::AUTH_NAMESPACE.'PasswordResetLinkController@create'],
            'password.email' => ['lupa-password', ['POST'], ['web', 'guest', 'throttle:password-reset'], self::AUTH_NAMESPACE.'PasswordResetLinkController@store'],
            'password.reset' => ['reset-password/{token}', $get, $guest, self::AUTH_NAMESPACE.'NewPasswordController@create'],
            'password.update' => ['reset-password', ['POST'], ['web', 'guest', 'throttle:password-reset'], self::AUTH_NAMESPACE.'NewPasswordController@store'],
            'patient.dashboard' => ['pasien', $get, $portal, self::PATIENT_NAMESPACE.'DashboardController@index'],
            'patient.profile.edit' => ['pasien/profil', $get, $portal, self::PATIENT_NAMESPACE.'ProfileController@edit'],
            'patient.profile.update' => ['pasien/profil', ['PUT'], $portal, self::PATIENT_NAMESPACE.'ProfileController@update'],
            'patient.password.update' => ['pasien/password', ['PUT'], $portal, self::PATIENT_NAMESPACE.'PasswordController@update'],
        ];

        $cases = [];

        foreach ($rows as $name => [$uri, $methods, $middleware, $action]) {
            $cases[$name] = [$name, $uri, $methods, $middleware, $action];
        }

        return $cases;
    }

    /**
     * @param  list<string>  $methods
     * @param  list<string>  $middleware
     */
    #[DataProvider('routes')]
    public function test_route_exists_with_the_documented_uri_methods_middleware_and_action(string $name, string $uri, array $methods, array $middleware, string $action): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "Route {$name} belum terdaftar.");
        $this->assertSame($uri, $route->uri(), $name);
        $this->assertSame($methods, $route->methods(), $name);
        $this->assertSame($middleware, array_values(array_filter($route->gatherMiddleware(), 'is_string')), "Middleware {$name} harus persis dan berurutan.");
        $this->assertSame($action, $route->getActionName(), $name);
    }

    public function test_the_phase_5_route_names_are_the_thirteen_documented_ones_and_are_not_duplicated(): void
    {
        $this->assertCount(13, self::routes());

        foreach (array_keys(self::routes()) as $name) {
            $count = collect(Route::getRoutes()->getRoutes())->filter(fn ($route): bool => $route->getName() === $name)->count();

            $this->assertSame(1, $count, "Nama route {$name} harus tepat satu.");
        }
    }

    public function test_there_are_exactly_four_routes_under_pasien_and_they_are_the_portal_routes(): void
    {
        $found = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === 'pasien' || str_starts_with($route->uri(), 'pasien/')) {
                $found[] = (string) $route->getName();
            }
        }

        sort($found);

        $this->assertSame(['patient.dashboard', 'patient.password.update', 'patient.profile.edit', 'patient.profile.update'], $found);
    }

    /** Route fase 6 sengaja belum ada (F5-R1); `InternalLinkTest` dan `BannerButtonTest` mengunci `/pasien/pendaftaran/baru`. */
    public function test_phase_6_routes_do_not_exist_yet(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();

            $this->assertFalse(str_starts_with($name, 'patient.appointments.'), "{$name} adalah route fase 6.");
            $this->assertFalse(str_starts_with($name, 'patient.notifications.'), "{$name} adalah route fase 6.");
        }

        foreach (['patient.appointments.create', 'patient.appointments.index', 'patient.appointments.store', 'patient.notifications.read'] as $name) {
            $this->assertFalse(Route::has($name), $name);
        }
    }

    public function test_there_is_no_wildcard_route_under_pasien(): void
    {
        foreach (['/pasien/pendaftaran/baru', '/pasien/pendaftaran', '/pasien/riwayat', '/pasien/apa-saja', '/pasien/notifikasi/1/dibaca'] as $uri) {
            foreach (['GET', 'POST'] as $method) {
                try {
                    Route::getRoutes()->match(Request::create($uri, $method));

                    $this->fail("{$method} {$uri} tidak boleh cocok dengan route mana pun.");
                } catch (NotFoundHttpException) {
                    $this->addToAssertionCount(1);
                }
            }
        }
    }

    public function test_the_admin_area_still_has_one_hundred_and_six_routes_after_phase_5(): void
    {
        $count = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/')) {
                $count++;
            }
        }

        // Fase 5 tidak menambah route admin (admin pasien = fase 7, pengaturan = fase 8).
        $this->assertSame(106, $count);
    }

    /**
     * Setara `route:list --except-vendor`: route milik aplikasi (aksi `App\…`). Tiga route vendor
     * (`storage.local`, `storage.local.upload`, `up`) berupa Closure dan tidak dihitung. 128 sebelum fase 5 + 13 = 141.
     */
    public function test_the_application_has_one_hundred_and_forty_one_routes_after_phase_5(): void
    {
        $count = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->getActionName(), 'App\\')) {
                $count++;
            }
        }

        $this->assertSame(141, $count);
    }

    public function test_uris_use_indonesian_segments(): void
    {
        $this->assertSame('/masuk', route('login', [], false));
        $this->assertSame('/masuk', route('login.store', [], false));
        $this->assertSame('/keluar', route('logout', [], false));
        $this->assertSame('/daftar', route('register', [], false));
        $this->assertSame('/daftar', route('register.store', [], false));
        $this->assertSame('/lupa-password', route('password.request', [], false));
        $this->assertSame('/lupa-password', route('password.email', [], false));
        $this->assertSame('/reset-password/abc123', route('password.reset', 'abc123', false));
        $this->assertSame('/reset-password', route('password.update', [], false));
        $this->assertSame('/pasien', route('patient.dashboard', [], false));
        $this->assertSame('/pasien/profil', route('patient.profile.edit', [], false));
        $this->assertSame('/pasien/profil', route('patient.profile.update', [], false));
        $this->assertSame('/pasien/password', route('patient.password.update', [], false));
    }

    /** Nama `password.reset` dan `password.request` wajib persis: `ResetPassword` memanggil `route('password.reset', …)`. */
    public function test_the_password_reset_link_matches_what_laravel_generates(): void
    {
        $route = Route::getRoutes()->getByName('password.reset');

        $this->assertSame(['token'], $route->parameterNames());
        $this->assertSame(
            url('/reset-password/abc123?email=siti%40contoh.test'),
            route('password.reset', ['token' => 'abc123', 'email' => 'siti@contoh.test']),
        );
    }

    public function test_only_the_four_documented_post_routes_are_throttled(): void
    {
        $expected = [
            'login.store' => 'throttle:login',
            'register.store' => 'throttle:register',
            'password.email' => 'throttle:password-reset',
            'password.update' => 'throttle:password-reset',
        ];

        foreach (array_keys(self::routes()) as $name) {
            $throttles = array_values(array_filter(
                Route::getRoutes()->getByName($name)->gatherMiddleware(),
                fn ($item): bool => is_string($item) && str_starts_with($item, 'throttle:'),
            ));

            $this->assertSame(isset($expected[$name]) ? [$expected[$name]] : [], $throttles, $name);
        }
    }

    /** Route tamu memakai `guest` saja; `logout` hanya `auth` (F5-R3, F5-R8). */
    public function test_guest_routes_never_carry_auth_account_or_permission_middleware(): void
    {
        foreach (['login', 'login.store', 'register', 'register.store', 'password.request', 'password.email', 'password.reset', 'password.update'] as $name) {
            $middleware = array_filter(Route::getRoutes()->getByName($name)->gatherMiddleware(), 'is_string');

            $this->assertContains('guest', $middleware, $name);

            foreach ($middleware as $item) {
                $this->assertStringNotContainsString('permission:', $item, "{$name}: {$item}");
                $this->assertStringNotContainsString('account:', $item, "{$name}: {$item}");
                $this->assertNotSame('auth', $item, "{$name}: {$item}");
            }
        }

        $logout = array_filter(Route::getRoutes()->getByName('logout')->gatherMiddleware(), 'is_string');

        $this->assertContains('auth', $logout);
        $this->assertNotContains('guest', $logout);
        $this->assertNotContains('account:patient', $logout, 'Staf yang memakai /keluar ikut keluar (F5-R8).');
    }

    /** KNS-02 (sisi pasien): setiap route `patient.*` berpagar `auth`, `account:patient`, dan `active`, tanpa izin Spatie. */
    public function test_every_patient_route_is_guarded_by_auth_account_and_active_without_permissions(): void
    {
        $checked = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with((string) $route->getName(), 'patient.')) {
                continue;
            }

            $middleware = array_filter($route->gatherMiddleware(), 'is_string');

            foreach (['auth', 'account:patient', 'active'] as $required) {
                $this->assertContains($required, $middleware, "{$route->getName()}: tanpa middleware {$required}");
            }

            foreach ($middleware as $item) {
                $this->assertStringNotContainsString('permission:', $item, "{$route->getName()}: pasien tidak memegang izin.");
            }

            $checked++;
        }

        $this->assertSame(4, $checked);
    }

    /** F5-R3: grup pasien dimuat sebelum grup admin di `bootstrap/app.php` dan komentar penanda fase 5 sudah diganti. */
    public function test_the_patient_group_is_loaded_before_the_admin_group_in_bootstrap(): void
    {
        $source = (string) file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringContainsString("->prefix('pasien')", $source);
        $this->assertStringContainsString("->name('patient.')", $source);
        $this->assertStringContainsString("base_path('routes/patient.php')", $source);
        $this->assertLessThan(strpos($source, "base_path('routes/admin.php')"), strpos($source, "base_path('routes/patient.php')"));
        $this->assertStringNotContainsString('ditambahkan pada fase 5', $source);
        $this->assertStringNotContainsString('Cadangan F1-R2', $source);
    }

    /** KNS-03: fase 5 tidak menambah izin dan pasien tidak punya peran. */
    public function test_phase_5_adds_no_permission_to_the_catalogue(): void
    {
        $this->assertCount(19, RolePermissionSeeder::PERMISSIONS);
    }

    public function test_get_on_post_only_or_put_only_phase_5_urls_answers_405(): void
    {
        $this->get('/keluar')->assertStatus(405);
        $this->get('/pasien/password')->assertStatus(405);
        $this->get('/reset-password')->assertStatus(405);
    }
}
```

- [ ] **Step 4: Ubah tes keamanan (tool Edit)**

`tests/Feature/Security/SecurityHeadersTest.php`:

old_string:

```php
            'login admin' => ['/admin/login', 200],
            'halaman 404' => ['/tidak-ada', 404],
        ];
```

new_string:

```php
            'login admin' => ['/admin/login', 200],
            'login pasien' => ['/masuk', 200],
            'registrasi pasien' => ['/daftar', 200],
            'lupa password' => ['/lupa-password', 200],
            'halaman 404' => ['/tidak-ada', 404],
        ];
```

`tests/Feature/View/RawOutputScanTest.php`:

old_string:

```php
        $files = array_filter($this->viewFiles(), fn (string $file): bool => str_contains($this->relative($file), 'public/')
            || str_contains($this->relative($file), 'components/doctor-card')
```

new_string:

```php
        $files = array_filter($this->viewFiles(), fn (string $file): bool => str_contains($this->relative($file), 'public/')
            || str_contains($this->relative($file), 'auth/')
            || str_contains($this->relative($file), 'patient/')
            || str_contains($this->relative($file), 'components/layouts/')
            || str_contains($this->relative($file), 'components/doctor-card')
```

- [ ] **Step 5: Jalankan tes, harus lulus; lalu langkah sanity**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter='PatientPageMatrixTest|Phase5RoutePermissionTest|SecurityHeadersTest|RawOutputScanTest|AdminRouteMiddlewareTest|Phase4bRoutePermissionTest|AdminPageMatrixTest'
```
Expected: seluruh tes PASS (`PatientPageMatrixTest` 50 kasus: 30 halaman + 15 mutasi + 5 tes lain; `Phase5RoutePermissionTest` 27 kasus: 13 kasus provider + 14 tes; `SecurityHeadersTest` +3 kasus). Bila `PatientPageMatrixTest` gagal pada satu sel, JANGAN mengubah hasil yang diharapkan: bandingkan dengan kerangka F5-R5 dan laporkan ketidaksesuaian kode (kemungkinan di Task 3 sampai 6) ke koordinator.

Sanity (bukti tes tidak kosong). Ubah SEMENTARA satu baris, jalankan, lalu kembalikan:

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
[IO.File]::WriteAllText("$APP\routes\patient.php", ((Get-Content routes\patient.php -Raw) -replace "->name\('dashboard'\)", "->name('beranda')"), (New-Object System.Text.UTF8Encoding $false))
& $PHP artisan test --filter='Phase5RoutePermissionTest'
git checkout -- routes/patient.php
git status --short
```
Expected: tes FAIL dengan pesan `Route patient.dashboard belum terdaftar.` dan hitungan 4 route; setelah `git checkout`, `git status --short` tidak memuat `routes/patient.php`. Bila `Get-Content`/`Set-Content` tidak mengubah berkas (pola nama berbeda), cukup baca isi `routes/patient.php` dan lewati sanity ini.

- [ ] **Step 6: Pint dan commit tes**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint tests\Feature\Access\PatientPageMatrixTest.php tests\Feature\Access\Phase5RoutePermissionTest.php tests\Feature\Security\SecurityHeadersTest.php tests\Feature\View\RawOutputScanTest.php
& $PHP vendor\bin\pint --test
git status --short
git add tests/Feature/Access/PatientPageMatrixTest.php tests/Feature/Access/Phase5RoutePermissionTest.php tests/Feature/Security/SecurityHeadersTest.php tests/Feature/View/RawOutputScanTest.php
git commit -m @'
test(fase-5): matriks akses #23-#28, Phase5RoutePermissionTest, header halaman auth, dan filter event inline

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: Pint `{"result":"passed"}` pada `--test`; hanya empat berkas tes pada `git status`; commit dibuat.

**(b) Rekonsiliasi dokumen (F5-R30)**

Semua berkas ada di `docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/` (tool Edit; setiap `old_string` cocok tepat satu kali pada keadaan berkas saat langkah dijalankan).

- [ ] **Step 7: 17 §1 dan §2 (pindahkan K-07 dan K-22)**

`17-keputusan-konfirmasi.md`:

old_string:

```markdown
| K3 | Desain akses, mesin booking/antrean, dan model data | Disetujui saat brainstorming 2026-10-02 | 00 |
```

new_string:

```markdown
| K3 | Desain akses, mesin booking/antrean, dan model data | Disetujui saat brainstorming 2026-10-02 | 00 |
| K-07 | Jumlah profil per akun pasien | (a) Satu akun satu profil (`patient_profiles.user_id` UNIQUE); diputuskan pemilik 2026-10-08 | 09 §2.2, 14 §5 Fase 5, D-145 (F5-R12) |
| K-22 | Email konfirmasi setelah pasien mendaftar akun | (a) Ya: email selamat datang tanpa verifikasi wajib; diputuskan pemilik 2026-10-08 | 06 §1, 12 BR-174, D-149 (F5-R25) |
```

old_string:

```markdown
| K-07 | Perlukah satu akun pasien memiliki banyak profil (mis. anggota keluarga)? | (a) Satu akun satu profil; (b) satu akun banyak profil | (a) — `patient_profiles.user_id` UNIQUE | Mengubah constraint, scoping kepemilikan, dan langkah "untuk siapa" pada wizard (F-NXT-15) | Fase 5 (portal pasien) |
| K-10 |
```

new_string:

```markdown
| K-10 |
```

old_string:

```markdown
| K-22 | Email konfirmasi setelah pasien mendaftar akun | (a) Ya (selamat datang, tanpa verifikasi wajib); (b) tidak | (a) | Satu kelas notifikasi | Fase 5 |
| K-04 |
```

new_string:

```markdown
| K-04 |
```

- [ ] **Step 8: 17 §4 (baris D-140 sampai D-151)**

`17-keputusan-konfirmasi.md`:

old_string:

```markdown
| 13 §2.1, §8, §9.2, §11.2 (F4B-R36) |
```

new_string:

```markdown
| 13 §2.1, §8, §9.2, §11.2 (F4B-R36) |
| D-140 | Cakupan fase 5 | F-AUTH-01 sampai F-AUTH-05 (termasuk logout pasien), F-PAS-01 (sebagian: sapaan, nomor pasien, keadaan kosong "Kunjungan Mendatang", ringkasan profil), F-PAS-02, dan F-PAS-03; halaman #23–#28 dan 13 route baru; tanpa migration baru. Tidak dibangun: wizard, riwayat, notifikasi, banner (`patient.appointments.*`, `patient.notifications.read`, fase 6); admin pasien, walk-in, hubungkan akun, ubah status (fase 7); dropdown header "Akun Saya" (ditunda ke fase 6, header tetap tautan "Akun Saya" dan tombol "Keluar"); tombol "Tampilkan password" dan hitung mundur throttle (W-09, fase 9); input nomor BPJS di profil (D-147). | 14 §5, 05 §3.1, 07 W-09 (F5-R1, R2) |
| D-141 | Route fase 5 dan grup pasien | 13 route baru: 9 route auth tamu di `routes/web.php` (`logout` di luar grup `guest`) dan 4 route portal di `routes/patient.php` (grup `web`, `auth`, `account:patient`, `active`, prefiks `pasien`, nama `patient.`), dimuat sebelum grup admin tanpa route wildcard di bawah `/pasien`; total 141 route (106 admin). Cadangan F1-R2 (D-21) dihapus: tamu di area non-admin ke `login`, pengguna login di halaman tamu ke dashboard sesuai tipe, dan `EnsureAccountIsActive` mengarahkan pasien nonaktif ke `login`. Guard `Route::has()` di view tetap untuk route fase 6. | 11 §1, §2.1, D-21 (F5-R3, R4) |
| D-142 | Staf membuka portal pasien | Mengikuti kode fase 1 dan 11 §7.1: staf (termasuk Super Admin) yang membuka `/pasien*` dialihkan 302 ke `admin.dashboard`, permintaan JSON mendapat 403; pasien di `/admin*` tetap 403. Teks 04 (D2, PAT, OWN, §4.4), 12 BR-160, 06 §19, dan 15 (§3.2 baris #27–#30, AKS-03) disesuaikan dari "403" menjadi "dialihkan ke dashboard admin". | 04 §3, 12 BR-160, 15 §3.2 (F5-R5) |
| D-143 | Login, intended URL, dan logout pasien | `Auth::attempt` dengan kredensial `account_type = patient` dan `is_active = true`; semua kegagalan (email tak dikenal, password salah, akun staf, akun nonaktif) memberi galat field `email` "Email atau password salah." (D-22 dan AKS-08 menang atas teks khusus 12 V-02); login dan logout pasien tidak diaudit. Sukses: regenerasi sesi, limiter login dibersihkan, `last_login_at` diisi. URL tujuan (`PatientIntendedUrl::pull`) dipakai bila bukan `/admin` atau `/admin/…`, selain itu dashboard. `POST /keluar` (middleware `auth` saja) mengeluarkan akun mana pun, membatalkan sesi dan token CSRF, lalu ke beranda dengan flash "Anda telah keluar.". Pengerasan `auth.timebox_duration` tetap bawaan fase 9. | 06 §2, 12 V-02, D-22 (F5-R6, R7, R8) |
| D-144 | Registrasi pasien | `PatientRegistrationService::register()` dalam satu transaksi: akun (`email_verified_at` NULL, tanpa verifikasi wajib), profil kanal `online` dengan `consent_at = now()` dan `consent_version` dari `privacy.consent_version`, nomor pasien, dan audit `patient.registered`; email selamat datang dikirim sesudah commit dan kegagalannya hanya dilaporkan, tidak membatalkan registrasi (06 §18). Email ganda (termasuk beda huruf besar dan balapan unik) menjadi galat field `email`; kegagalan sistem menjadi flash galat tanpa baris parsial. Tanpa pencocokan otomatis dengan profil walk-in (WLK-06). Sukses: login otomatis dan flash "Registrasi berhasil. Selamat datang, {nama}.". | 06 §1.1, 12 V-01, BR-174 (F5-R9) |
| D-145 | Nomor pasien, BPJS, relasi akun-profil, dan policy | `patient_number` = `PatientNumberGenerator::format(id)` diisi service setelah insert dalam transaksi yang sama; TIDAK memakai event `creating`/`created` dengan nomor sementara seperti 11 §5.1. Hook `saving` mengisi `bpjs_number_last4` (NULL bila kosong) dan accessor `masked_bpjs` menampilkan sembilan titik dan empat digit terakhir. K-07 (a): satu akun satu profil (`User::patientProfile()` HasOne); akun pasien tanpa profil (hanya di tes) menjawab 404 di dashboard dan profil. `PatientProfilePolicy` hanya `view` dan `update` (pemilik atau izin `patients.*`); method lain fase 7. `PatientProfileFactory` tidak diubah. | 11 §5.1, 09 §2.2 (F5-R10 sampai R14) |
| D-146 | Reset password | `Password::broker()->sendResetLink` dengan kredensial `account_type = patient` dan `is_active = true`; apa pun hasilnya (terkirim, email tak dikenal, akun staf, nonaktif, throttle broker) memberi redirect dan flash identik, dan kegagalan kirim hanya dilaporkan. Reset sukses: password baru, `remember_token` baru, seluruh sesi akun dihapus, audit `auth.password_reset`, lalu ke `login` tanpa login otomatis. `password.email` dan `password.update` sama-sama `throttle:password-reset` (satu bucket email dan IP, 3 per 10 menit; dicatat sebagai keterbatasan). Permintaan tautan selalu `redirect()->route('password.request')` sehingga `Location` identik tanpa bergantung pada Referer. Timing respons tidak disamakan: email terdaftar sedikit lebih lambat (keterbatasan, mitigasi throttle). Tautan reset tidak pernah dirender di halaman atau flash. Teks `lang/id/passwords.php` diganti dan `lang/id.json` memuat terjemahan templat email bawaan. | 06 §3, 11 §2.1, 12 V-04, V-05, 16 §10 (F5-R15, R16, R17) |
| D-147 | Profil dan ganti password | Satu halaman dua formulir dengan satu ringkasan galat. Ubah profil memperbarui enam kolom (nama lengkap, tanggal lahir, jenis kelamin, telepon, email, alamat) dan menyinkronkan `users.name`, `users.email`, `users.phone` (D-07); mengubah email TIDAK meminta password saat ini (12 V-06 menang atas [ASUMSI] 06 §19); balapan email menjadi galat field; audit `patient.updated` (old/new tanpa `bpjs_number_last4` dan `updated_at`), tanpa perubahan tanpa audit. Tanpa input nomor BPJS (nomor masuk lewat wizard fase 6): bila tersimpan, tampil tersamar dengan kotak "Hapus nomor BPJS dari profil saya" (`remove_bpjs`). Ganti password menghapus sesi lain milik akun dan mempertahankan sesi kini (06 §19 [ASUMSI] dipakai), dengan audit `auth.password_changed`. `current_password` berupa larik tidak lagi 500 di form ganti password pasien dan staf (`bail` + `string`, pesan "Password saat ini tidak valid."); `Admin\UpdatePasswordRequest` fase 1 ikut ditambal di fase 5. Penyimpangan penulis: `updateProfile()` mendeteksi perubahan dengan `isDirty()` sebelum simpan (bukan `diff()` sesudahnya), `register()` mengembalikan User ter-refresh dengan `patientProfile` termuat, dan aturan email memakai urutan `bail, required, string, max:255, email:rfc`. | 06 §19, 12 V-06, V-07 (F5-R18 sampai R21) |
| D-148 | Dashboard dan tampilan auth | Dashboard #27: "Halo, {nama lengkap}", "No. pasien: …", seksi "Kunjungan Mendatang" dengan keadaan kosong (tombol "Daftar Kunjungan" bila route fase 6 ada, selain itu "Lihat Jadwal Dokter"), dan kartu "Profil Anda" dengan tautan "Ubah Profil dan Akun". `x-layouts.auth` mendapat prop `heading` dan `wide` (`.auth-card-wide`, lebar 35rem); breadcrumb berakar "Portal Pasien" untuk `patient.dashboard`. Komponen `x-form.checkbox` menerima nilai larik tanpa galat (`is_scalar`; `remember[]` dan `remove_bpjs[]` tidak lagi 500). Tidak ada JavaScript baru dan CSP tidak berubah. | 05, 07, F-PAS-01 (F5-R22, R23) |
| D-149 | Audit dan email fase 5 | Empat aksi audit: `patient.registered`, `patient.updated`, `auth.password_changed`, dan `auth.password_reset` (deskripsi tanpa nama, email, atau telepon; nomor pasien `-` bila profil tidak ada); login dan logout pasien tidak diaudit. `PatientWelcomeNotification` hanya via mail dan tanpa antrean, tidak memuat telepon, alamat, tanggal lahir, BPJS, nomor pasien, atau tautan verifikasi (K-22 (a), BR-174, NTF-08). | 12 BR-174, 15 NTF-08 (F5-R24, R25) |
| D-150 | Seeder demo fase 5 | `PatientSeeder` membuat tujuh akun pasien aktif tanpa peran beserta profil online P000001–P000007 (13 §10.1): hanya `local`/`testing` (melempar di luar itu), idempoten per email, satu transaksi per pasien, ditulis lewat model (tanpa audit dan tanpa email), `consent_version` dari pengaturan. `DemoUserSeeder` tetap tiga staf; `DemoSeeder` memanggil `… ContentSeeder, PatientSeeder, ContactMessageSeeder` dan tabel kredensial konsol menambah "Anisa Rahmawati · pasien@unpamhospital.test · Pasien" dengan catatan enam akun `@contoh.test` berpassword sama. Walk-in (P000008–P000013) dan `AppointmentSeeder` menyusul di fase 6/7. Setelah `DatabaseSeeder` di `testing`: `users` 10 dan `patient_profiles` 7. | 13 §2.1, §10.1, §11 (F5-R27) |
| D-151 | Uji akses dan carry fase 5 | `PatientPageMatrixTest` menguji #23–#28 × lima aktor dan mutasi non-GET dengan staf →`admin.dashboard` (D-142); AKS-07 (intended URL) diuji dengan `/pasien/profil?contoh=utuh` karena `patient.appointments.create` milik fase 6 (tes asli dipasang di fase 6); AKS-06 memakai URL admin yang sudah ada dan `/admin/pasien` serta `/admin/pengaturan` masih 404 sampai fase 7 dan 8; `Phase5RoutePermissionTest` mengunci 13 route, 106 route admin, dan total 141. Carry fase 6: `PatientDashboardTest::test_empty_state_offers_the_doctor_schedule_while_the_booking_route_does_not_exist` dan teks 05/06 tentang staf → 403 pada wizard (06 §3.E "Akun staf membuka wizard | 403" dan simpul diagramnya) diubah saat `patient.appointments.create` dibuat. | 15 §3.2, AKS-02, AKS-06, AKS-07 (F5-R28, R29) |
```

Verifikasi:

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; Set-Location $APP
$SPEC = "docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"
Select-String -Path "$SPEC\17-keputusan-konfirmasi.md" -Pattern '^\| D-(140|151) \|' | ForEach-Object { $_.Line.Substring(0, 40) }
(Select-String -Path "$SPEC\17-keputusan-konfirmasi.md" -Pattern '^\| D-\d+ \|').Count
Select-String -Path "$SPEC\17-keputusan-konfirmasi.md" -Pattern '^\| K-(07|22) \|' | ForEach-Object { "$($_.LineNumber): $($_.Line.Substring(0, 30))" }
Select-String -Path "$SPEC\17-keputusan-konfirmasi.md" -Pattern '^## 2\. ' | ForEach-Object { "bagian 2 di baris $($_.LineNumber)" }
```
Expected: dua baris (`| D-140 |` dan `| D-151 |`); jumlah baris D-nn = `151`; tepat dua baris K (`K-07` dan `K-22`) dengan nomor baris LEBIH KECIL daripada baris `## 2. Dibutuhkan Sebelum atau Selama Implementasi` (keduanya kini di §1).

- [ ] **Step 9: 04 dan 12 (staf di portal pasien dialihkan, bukan 403)**

`04-peran-hak-akses.md`:

old_string:

```markdown
| **D2** | Akun login, tetapi tipe akun salah atau izin tidak dimiliki | **403** (halaman galat berbahasa Indonesia; permintaan JSON mendapat JSON 403) |
```

new_string:

```markdown
| **D2** | Akun login, tetapi tipe akun salah atau izin tidak dimiliki | **403** (halaman galat berbahasa Indonesia; permintaan JSON mendapat JSON 403). Pengecualian: staf yang membuka portal pasien dialihkan **302** ke `admin.dashboard` (permintaan JSON tetap 403; D-142) |
```

old_string:

```markdown
| **PAT** | Portal pasien (27–30) | D1 | Akses | D2 (termasuk Super Admin) |
```

new_string:

```markdown
| **PAT** | Portal pasien (27–30) | D1 | Akses | 302 ke `admin.dashboard` (termasuk Super Admin; JSON 403; D-142) |
```

old_string:

```markdown
D2 (portal pasien tertutup bagi staf; staf memakai
```

new_string:

```markdown
302 ke `admin.dashboard` (portal pasien tertutup bagi staf, D-142; staf memakai
```

old_string:

```markdown
**tidak** dapat membuka portal pasien (D2): untuk melihat
```

new_string:

```markdown
**tidak** dapat membuka portal pasien (dialihkan ke `admin.dashboard`, D-142): untuk melihat
```

old_string:

```markdown
| Pasien login: `GET /masuk` | 302 → `patient.dashboard` (D3) |
```

new_string:

```markdown
| Pasien login: `GET /masuk` | 302 → `patient.dashboard` (D3) |
| Staf login: `GET /pasien` atau `GET /pasien/profil` | 302 → `admin.dashboard` (D-142); permintaan JSON 403 |
```

old_string:

```markdown
| Super Admin: `GET /pasien` | 403 (D2); `account:patient` tidak dilewati `Gate::before` |
```

new_string:

```markdown
| Super Admin: `GET /pasien` | 302 → `admin.dashboard` (D-142; permintaan JSON 403); `account:patient` tidak dilewati `Gate::before` |
```

`12-aturan-bisnis-validasi-keamanan.md`:

old_string:

```markdown
staf yang membuka rute portal pasien mendapat 403, dan pasien yang membuka rute admin mendapat 403.
```

new_string:

```markdown
staf yang membuka rute portal pasien dialihkan ke `admin.dashboard` (permintaan JSON mendapat 403; D-142), dan pasien yang membuka rute admin mendapat 403.
```

- [ ] **Step 10: 06 §19 (profil dan password pasien)**

`06-alur.md`:

old_string:

```markdown
    D -->|"Ya"| E{"Password saat ini benar dan email unik di users?"}
```

new_string:

```markdown
    D -->|"Ya"| E{"Email unik di users?"}
```

old_string:

```markdown
**Profil** (nama lengkap, tanggal lahir, jenis kelamin, telepon, email, alamat, nomor BPJS opsional) dan **Ganti password**. Nomor BPJS yang tersimpan hanya tampil 4 digit terakhir (`bpjs_number_last4`); kolom dikosongkan berarti tidak berubah. NIK tidak ada.
```

new_string:

```markdown
**Profil** (nama lengkap, tanggal lahir, jenis kelamin, telepon, email, alamat) dan **Ganti password**. Nomor BPJS tidak diisi di halaman ini (nomor masuk lewat wizard pendaftaran, fase 6); bila tersimpan, hanya tampil tersamar dengan 4 digit terakhir (`bpjs_number_last4`) beserta kotak centang "Hapus nomor BPJS dari profil saya" (D-147). NIK tidak ada.
```

old_string:

```markdown
divalidasi unik di `users`, dan meminta password saat ini **[ASUMSI]**.
```

new_string:

```markdown
dan divalidasi unik di `users`; mengubah email tidak meminta password saat ini (12 V-06 menang atas asumsi sebelumnya; D-147).
```

old_string:

```markdown
baris `sessions` lain milik akun dihapus **[ASUMSI]**, audit `auth.password_changed`
```

new_string:

```markdown
baris `sessions` lain milik akun dihapus (diputuskan, D-147), audit `auth.password_changed`
```

old_string:

```markdown
| Password saat ini salah (profil dengan perubahan email, atau ganti password) |
```

new_string:

```markdown
| Password saat ini salah (ganti password) |
```

old_string:

```markdown
| Nomor BPJS bukan 13 digit | "Nomor BPJS harus terdiri dari 13 digit angka." |
```

new_string:

```markdown
| Pilihan hapus nomor BPJS tidak valid (`remove_bpjs` bukan boolean) | "Pilihan hapus nomor BPJS tidak valid." |
```

old_string:

```markdown
| Akun staf membuka halaman ini | 403 "Anda tidak memiliki izin untuk mengakses halaman ini." |
```

new_string:

```markdown
| Akun staf membuka halaman ini | Dialihkan 302 ke `admin.dashboard` (permintaan JSON: 403; D-142) |
```

- [ ] **Step 11: 11, 13, 15, 16, dan 00 J.6**

`11-route-controller-model-service.md`:

old_string:

```markdown
- Event `booted()`: `creating` → `patient_number` sementara
```

new_string:

```markdown
- **Diganti pada fase 5 (D-145):** `patient_number` TIDAK diisi lewat event model; `PatientRegistrationService` mengisinya sesudah insert dalam transaksi yang sama (kolom NULLable), dan `booted()` hanya memuat hook `saving` untuk `bpjs_number_last4`. Teks berikut adalah rancangan awal. Event `booted()`: `creating` → `patient_number` sementara
```

`13-data-contoh.md`:

old_string:

```markdown
| 4.1 | `DemoUserSeeder` | demo | `users`, `model_has_roles` | 3 staf + 7 akun pasien |
```

new_string:

```markdown
| 4.1 | `DemoUserSeeder` | demo | `users`, `model_has_roles` | 3 staf (tujuh akun pasien dibuat `PatientSeeder`, D-150) |
```

old_string:

```markdown
| 4.10 | `PatientSeeder` | demo | `patient_profiles` | 13 (7 berakun, 6 walk-in) |
```

new_string:

```markdown
| 4.10 | `PatientSeeder` | demo | `users`, `patient_profiles` | fase 5: 7 akun pasien + 7 profil online; 6 walk-in menyusul di fase 6/7 sehingga total 13 (D-150) |
```

old_string:

```markdown
`consent_at` = waktu pembuatan, `consent_version = 1.0`. Walk-in:
```

new_string:

```markdown
`consent_at` = waktu pembuatan, `consent_version` = pengaturan `privacy.consent_version` (bawaan `1.0`). Walk-in:
```

old_string:

```markdown
SELECT COUNT(*) FROM users WHERE email LIKE '%.test';               -- 10 (hanya di local/testing)
```

new_string:

```markdown
SELECT COUNT(*) FROM users WHERE email LIKE '%.test';               -- 10 (hanya di local/testing)
SELECT COUNT(*) FROM patient_profiles;                              -- 7 setelah fase 5 (online, berakun); 13 setelah walk-in fase 6/7
```

old_string:

```markdown
guard §2.3 + `DemoUserSeeder` memeriksa ulang
```

new_string:

```markdown
guard §2.3 + `DemoUserSeeder` dan `PatientSeeder` memeriksa ulang
```

`15-pengujian-penerimaan.md`:

old_string:

```markdown
**→D** = redirect ke dashboard sesuai tipe akun (middleware `guest`); **403** = ditolak (`account:*` atau `permission:*`).
```

new_string:

```markdown
**→D** = redirect ke dashboard sesuai tipe akun (middleware `guest`); **→A** = staf di portal pasien dialihkan ke `admin.dashboard` (`account:patient`, D-142; permintaan JSON mendapat 403); **403** = ditolak (`account:staff` atau `permission:*`).
```

old_string:

```markdown
| 27 | Dashboard pasien | `patient.dashboard` | →L | 200 | 403 | 403 | 403 |
```

new_string:

```markdown
| 27 | Dashboard pasien | `patient.dashboard` | →L | 200 | →A | →A | →A |
```

old_string:

```markdown
| 28 | Profil & akun | `patient.profile.edit` | →L | 200 | 403 | 403 | 403 |
```

new_string:

```markdown
| 28 | Profil & akun | `patient.profile.edit` | →L | 200 | →A | →A | →A |
```

old_string:

```markdown
| 29 | Pendaftaran kunjungan | `patient.appointments.create` | →L | 200 | 403 | 403 | 403 |
```

new_string:

```markdown
| 29 | Pendaftaran kunjungan | `patient.appointments.create` | →L | 200 | →A | →A | →A |
```

old_string:

```markdown
| 30 | Riwayat pendaftaran | `patient.appointments.index` | →L | 200 | 403 | 403 | 403 |
```

new_string:

```markdown
| 30 | Riwayat pendaftaran | `patient.appointments.index` | →L | 200 | →A | →A | →A |
```

old_string:

```markdown
Semua halaman admin 200; area pasien 403 | otomatis | K1 |
```

new_string:

```markdown
Semua halaman admin 200; area pasien →A (302 ke `admin.dashboard`, JSON 403; D-142) | otomatis | K1 |
```

old_string:

```markdown
| Semua **403**; tidak ada konten admin pada respons | otomatis | K6 |
```

new_string:

```markdown
| Semua **403**; tidak ada konten admin pada respons. Fase 5: `/admin/pasien` dan `/admin/pengaturan` belum punya route (404) sampai fase 7 dan 8 | otomatis | K6 |
```

old_string:

```markdown
| Redirect ke `/masuk`; setelah login kembali ke URL semula dengan `sesi` utuh | otomatis | K1 |
```

new_string:

```markdown
| Redirect ke `/masuk`; setelah login kembali ke URL semula dengan `sesi` utuh. Fase 5 menguji dengan `/pasien/profil?contoh=utuh`; URL pendaftaran asli dipasang di fase 6 (D-151) | otomatis | K1 |
```

`16-instalasi-operasional.md`:

old_string:

```markdown
Halaman menjawab generik ("Jika email terdaftar, tautan telah dikirim").
```

new_string:

```markdown
Halaman menjawab generik ("Jika email terdaftar, tautan untuk mengatur ulang password telah dikirim. Periksa kotak masuk Anda."), apa pun alamat yang diisi.
```

`00-keputusan-inti.md` (J.6, sisipkan butir fase 5 sebelum butir "Pengujian"):

old_string:

```markdown
- **Pengujian:** folder `tests/Feature/Access`, `tests/Feature/Seeding`, `tests/Concurrency`,
```

new_string:

```markdown
- **Nama tambahan fase 5:** `Services\Patients\PatientNumberGenerator::format(int $id)`, `Services\Patients\PatientRegistrationService` (`register()`, `updateProfile()`), `Notifications\PatientWelcomeNotification`, `Policies\PatientProfilePolicy` (`view`, `update`), `Support\PatientIntendedUrl::pull()`; `User::patientProfile()`, `PatientProfile::booted()` (hook `saving` untuk `bpjs_number_last4`) dan accessor `masked_bpjs`, state `PatientProfileFactory::withBpjs()`; Form Request `Auth\LoginRequest`, `Auth\RegisterRequest`, `Auth\ForgotPasswordRequest`, `Auth\ResetPasswordRequest`, `Patient\UpdatePatientProfileRequest`, `Patient\UpdatePasswordRequest`; controller `Auth\LoginController` (`create`, `store`, `destroy`), `Auth\RegisterController`, `Auth\PasswordResetLinkController`, `Auth\NewPasswordController`, `Patient\DashboardController`, `Patient\ProfileController`, `Patient\PasswordController`; view `auth.login`, `auth.register`, `auth.forgot-password`, `auth.reset-password`, `patient.dashboard`, `patient.profile.edit`; prop `heading` dan `wide` pada `x-layouts.auth` (`.auth-card-wide` di `_shared.scss`); `routes/patient.php`; `lang/id.json` (templat email bawaan) dan teks baru `lang/id/passwords.php`; `Database\Seeders\PatientSeeder` (`ACCOUNTS`); aksi audit `patient.registered`, `patient.updated`, `auth.password_changed`, `auth.password_reset`; pembantu tes `Tests\Support\InteractsWithPatients` (`patientPassword()`, `createPatient()`, `registrationPayload()`); kelas tes `PatientNumberGeneratorTest`, `PatientProfileDomainTest`, `PatientProfilePolicyTest`, `PatientRegistrationServiceTest`, `PatientWelcomeNotificationTest`, `PatientLoginTest`, `PatientDashboardTest`, `PatientRegistrationTest`, `RegisterRequestTest`, `PatientPasswordResetTest`, `PatientProfileTest`, `PatientPasswordChangeTest`, `PatientSeederTest`, `PatientPageMatrixTest`, `Phase5RoutePermissionTest`.
- **Pengujian:** folder `tests/Feature/Access`, `tests/Feature/Seeding`, `tests/Concurrency`,
```

Verifikasi dan commit:

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$SPEC = "docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"
Select-String -Path "$SPEC\15-pengujian-penerimaan.md" -Pattern '^\| (27|28|29|30) \|' | ForEach-Object { $_.Line }
Select-String -Path "$SPEC\16-instalasi-operasional.md" -Pattern 'tautan untuk mengatur ulang password telah dikirim' | ForEach-Object { "16 baris $($_.LineNumber)" }
Select-String -Path "$SPEC\00-keputusan-inti.md" -Pattern 'Nama tambahan fase 5' | ForEach-Object { "00 baris $($_.LineNumber)" }
Select-String -Path "$SPEC\04-peran-hak-akses.md", "$SPEC\12-aturan-bisnis-validasi-keamanan.md", "$SPEC\06-alur.md" -Pattern 'D-14[27]' | ForEach-Object { "$($_.Filename):$($_.LineNumber)" }
git status --short
git add docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/00-keputusan-inti.md docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/04-peran-hak-akses.md docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/06-alur.md docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/11-route-controller-model-service.md docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/12-aturan-bisnis-validasi-keamanan.md docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/13-data-contoh.md docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/15-pengujian-penerimaan.md docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/16-instalasi-operasional.md docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/17-keputusan-konfirmasi.md
git commit -m @'
docs(fase-5): rekonsiliasi blueprint (D-140 sampai D-151, K-07 dan K-22 ke keputusan pemilik, staf di portal pasien dialihkan, seeder pasien, nama baru fase 5)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: empat baris 15 (`→A` pada #27 sampai #30); satu baris 16, satu baris 00, dan beberapa baris 04/12/06 yang memuat `D-142`/`D-147` (termasuk baris "Super Admin: `GET /pasien`" di 04); `git status` hanya memuat sembilan berkas dokumen; commit dibuat. Tidak ada tes yang membaca berkas dokumen (hanya `RepositoryHygieneTest` memeriksa `.env*`), tetapi jalankan `& $PHP artisan test --filter=RepositoryHygieneTest` bila ragu.

**(c) README**

- [ ] **Step 12: Tambahkan bagian Fase 5 ke `README.md` (tool Edit) dan commit**

`README.md` (setiap `old_string` cocok tepat satu kali). Edit pertama, kalimat di bagian Fase 1:

old_string:

```markdown
Akun pasien demo menyusul di fase 5.
```

new_string:

```markdown
Akun pasien demo ada sejak fase 5 (lihat bagian "Fase 5").
```

Edit kedua, tambahkan bagian baru di akhir berkas:

old_string:

````markdown
npm run build                            # peta tersemat (initMapEmbeds) dan gaya fasilitas/kamar ikut dibundel
```
````

new_string:

````markdown
npm run build                            # peta tersemat (initMapEmbeds) dan gaya fasilitas/kamar ikut dibundel
```

## Fase 5 — Akun pasien (daftar, masuk, lupa password, dashboard, dan profil)

Yang tersedia:
- **Daftar akun** di <http://unpam-hospital.test/daftar> (nama, tanggal lahir, jenis kelamin, telepon, email, alamat, password, dan persetujuan Kebijakan Privasi). Setelah daftar pasien langsung masuk ke dashboard dan menerima email selamat datang; verifikasi email tidak diwajibkan. Nomor pasien dibuat otomatis (`P000123`).
- **Masuk dan keluar** di `/masuk` (dengan "Ingat saya") dan tombol "Keluar" di header portal. Pesan galat login selalu "Email atau password salah.", apa pun penyebabnya.
- **Lupa password** di `/lupa-password`: tautan atur ulang dikirim lewat email, berlaku 60 menit, sekali pakai. Halaman selalu menjawab sama, terdaftar atau tidak. Setelah reset semua sesi akun berakhir dan pasien masuk lagi dengan password baru.
- **Dashboard pasien** di `/pasien` (sapaan, nomor pasien, keadaan kosong "Kunjungan Mendatang", ringkasan profil) dan **Profil dan Akun** di `/pasien/profil` (ubah profil, hapus nomor BPJS tersimpan, ganti password; sesi di perangkat lain berakhir saat password diganti).
- Belum ada (menyusul): pendaftaran kunjungan, riwayat, dan notifikasi (fase 6); pengelolaan pasien oleh petugas (fase 7). Tombol "Daftar Kunjungan" di beranda dan dashboard tampil otomatis setelah fase 6.

### Akun pasien demo

Seeder `PatientSeeder` hanya berjalan di lokal dan membuat tujuh akun pasien aktif (tanpa peran) beserta profilnya, semuanya berpassword sama dengan akun staf demo (`Demo12345`, atau nilai `DEMO_PASSWORD`):

| Nama | Email | No. pasien |
|---|---|---|
| Anisa Rahmawati (akun demo) | `pasien@unpamhospital.test` | P000001 |
| Budi Hartanto | `budi.hartanto@contoh.test` | P000002 |
| Dewi Susilowati | `dewi.susilowati@contoh.test` | P000003 |
| Fitri Handayani | `fitri.handayani@contoh.test` | P000004 |
| Gilang Saputra | `gilang.saputra@contoh.test` | P000005 |
| Kartika Dewi Anggraini | `kartika.anggraini@contoh.test` | P000006 |
| Mulyani Astuti | `mulyani.astuti@contoh.test` | P000007 |

```powershell
& $PHP artisan migrate:fresh --seed   # LOKAL SAJA: menghapus semua tabel dan isi database unpam_hospital
```

Tiga pasien (nomor 1, 2, dan 4) memiliki nomor BPJS fiktif yang tersimpan terenkripsi dan hanya tampil tersamar. Di produksi tidak ada akun demo: seeder menolak berjalan di luar `local` dan `testing`.

### Mencoba lupa password di lokal

Dengan `MAIL_MAILER=log` (bawaan) email tidak benar-benar terkirim dan tautannya tidak pernah ditampilkan di halaman. Ambil tautan dari log harian setelah mengirim permintaan di `/lupa-password`:

```powershell
$log  = "storage\logs\laravel-$(Get-Date -Format yyyy-MM-dd).log"
$text = (Get-Content $log -Raw) -replace '=\r?\n', '' -replace '=3D', '='
[regex]::Matches($text, 'http://unpam-hospital\.test/reset-password/[A-Za-z0-9]+\?email=[^\s"<>]+') | Select-Object -Last 1 | ForEach-Object Value
```

Rincian lengkap (termasuk Mailpit) ada di blueprint 16 §10.

### Keterbatasan fase 5

- Permintaan tautan reset dan pemakaian tautan reset berbagi satu bucket throttle (`password-reset`, 3 per 10 menit per email dan IP), sehingga permintaan ulang dapat menghabiskan jatah pemakaian tautan.
- Waktu respons permintaan reset untuk email terdaftar sedikit lebih lambat daripada email tak dikenal (pengiriman email); isi respons identik.
- Profil tidak menyediakan input nomor BPJS (nomor masuk lewat wizard fase 6); pasien hanya dapat menghapus nomor yang tersimpan.

### Aturan keamanan yang berlaku

Percobaan masuk dibatasi 5 per menit per email dan alamat IP, pendaftaran akun 5 per jam per IP, dan permintaan atau pemakaian tautan reset 3 per 10 menit per email dan IP (satu hitungan bersama; ini keterbatasan yang dicatat). Setiap batas yang terlewati memberi pesan "Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik." Staf yang membuka `/pasien` dialihkan ke dashboard admin, dan pasien yang membuka `/admin` mendapat 403. Pasien hanya dapat mengubah profilnya sendiri; nomor pasien, kanal pendaftaran, dan persetujuan privasi tidak dapat diubah dari formulir. Login dan logout pasien tidak dicatat di audit log; pendaftaran, perubahan profil, ganti password, dan reset password dicatat tanpa data pribadi.

### Pemeriksaan cepat (fase 5)

```powershell
& $PHP artisan route:list --except-vendor --path=pasien   # 4 route portal (dashboard, profil, ubah profil, ganti password)
& $PHP artisan route:list --except-vendor --name=password # password.request, .email, .reset, .update (+ patient.password.update)
& $PHP artisan route:list --path=admin                    # tetap 106 route admin
& $PHP artisan test                                       # seluruh suite
npm run build                                             # gaya kartu auth lebar (.auth-card-wide) ikut dibundel
```
````

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
Select-String -Path README.md -Pattern '^## Fase' | ForEach-Object { $_.Line }
Select-String -Path README.md -Pattern 'menyusul di fase 5' | ForEach-Object { "masih ada: $($_.Line)" }
git add README.md
git commit -m @'
docs(fase-5): README bagian Fase 5 (akun pasien, akun demo, uji lupa password lokal, aturan keamanan, pemeriksaan cepat)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: enam judul bagian (`## Fase 1 …`, `## Fase 2 …`, `## Fase 3 …`, `## Fase 4a …`, `## Fase 4b …`, `## Fase 5 — Akun pasien …`); tidak ada baris "masih ada"; commit dibuat.

**(d) Gerbang fase 5**

- [ ] **Step 13: Pint, build, dan hitungan route**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
npm run build
$all = & $PHP artisan route:list --except-vendor --json | ConvertFrom-Json
"route total: $($all.Count)"
"route admin: $(($all | Where-Object { $_.uri -eq 'admin' -or $_.uri -like 'admin/*' }).Count)"
"route baru fase 5: $(($all | Where-Object { $_.name -in 'login', 'login.store', 'logout', 'register', 'register.store', 'password.request', 'password.email', 'password.reset', 'password.update', 'patient.dashboard', 'patient.profile.edit', 'patient.profile.update', 'patient.password.update' }).Count)"
"route fase 6 (harus 0): $(($all | Where-Object { $_.name -like 'patient.appointments.*' -or $_.name -like 'patient.notifications.*' }).Count)"
```
Expected: Pint `{"result":"passed"}`; `npm run build` sukses tanpa entri Vite baru dan tanpa galat (gaya `.auth-card-wide` ikut dibundel); `route total: 141`; `route admin: 106`; `route baru fase 5: 13`; `route fase 6 (harus 0): 0`.

- [ ] **Step 14: Suite penuh di latar belakang**

Suite penuh ±5200 tes dasar ditambah tes fase 5 (±28 menit) dan melebihi batas 10 menit sebuah perintah tool, sehingga dijalankan di latar belakang dengan keluaran ke berkas. Jalankan perintah berikut dengan tool PowerShell `run_in_background` (atau `Start-Process`), lalu tunggu notifikasi selesai; jangan memotongnya dengan `Wait-Job` di dalam satu perintah.

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
Remove-Item "$APP\storage\logs\suite-gate-5.txt" -ErrorAction SilentlyContinue
& $PHP artisan test *> "$APP\storage\logs\suite-gate-5.txt"
"selesai dengan kode $LASTEXITCODE"
```

Setelah selesai:

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
Get-Content "$APP\storage\logs\suite-gate-5.txt" -Tail 15
git status --short
git log --oneline -25
```
Expected: ringkasan akhir `Tests: … passed` tanpa gagal dan tanpa galat (jumlah = 5200 + seluruh tes baru fase 5; catat angkanya), kode keluar 0; `git status` bersih (kecuali `.superpowers/`); log memuat commit Task 1 sampai 8 fase 5 (plus commit rencana dari koordinator). Tes yang di-*skip* hanya yang bersyarat pada route fase 6 (`patient.appointments.*`). Bila ada tes gagal: jangan commit perbaikan di luar cakupan; salin nama tes dan pesan galat ke laporan koordinator.

**Implementer berhenti di sini.** `migrate:fresh --seed` menghapus seluruh isi database pengembangan dan memerlukan izin pemilik, sehingga dua langkah berikut dijalankan koordinator.

- [ ] **Step 15 (MANUAL, koordinator): seeding ulang dan hitungan data**

Daftar periksa; implementer melewatinya. Jalankan HANYA setelah pemilik menulis persis "Ya, jalankan migrate:fresh --seed". Dari PowerShell (Prelude P):

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan migrate:fresh --seed
& $PHP artisan media:prune --dry-run
& $PHP artisan tinker --execute="echo json_encode(['users' => App\Models\User::count(), 'pasien' => App\Models\User::patients()->count(), 'profil' => App\Models\PatientProfile::count(), 'bpjs_last4' => App\Models\PatientProfile::whereNotNull('bpjs_number_last4')->count(), 'kanal_online' => App\Models\PatientProfile::where('registration_channel','online')->count(), 'notifications' => Illuminate\Support\Facades\DB::table('notifications')->count()]);"
```
Expected: tidak ada migration baru fase 5 (tidak ada tabel baru); seeding selesai dan menampilkan tabel akun demo: tiga staf (peran `super-admin`, `content-admin`, `registration-officer`) PLUS baris "Anisa Rahmawati · pasien@unpamhospital.test · Pasien · Demo12345" dan catatan "Enam akun pasien lain (@contoh.test) memakai password yang sama."; `media:prune --dry-run` melaporkan 0 baris dan 0 berkas yatim. Hitungan pada `unpam_hospital`: `users` **10** (staf 3, pasien 7), `patient_profiles` **7** (semua kanal `online`, nomor `P000001` sampai `P000007`, tiga dengan `bpjs_number_last4` terisi: `0011`, `0022`, `0033`), `notifications` 0, `roles` 3, `permissions` 19, `website_settings` 19, `pages` 15, `media` 83, `room_categories` 4, `facilities` 10, `contact_messages` 5, `articles` 8, `model_has_roles` 3 (pasien tanpa peran). Bila `tinker --execute` tidak tersedia atau kutipan PowerShell bermasalah, hitung lewat phpMyAdmin atau `& "$XAMPP\mysql\bin\mysql.exe" -u unpam_app -p unpam_hospital -e "SELECT COUNT(*) FROM users"`. Periksa juga `public\storage` (junction `storage:link`). Pemilik perlu login ulang karena sesi terhapus.

- [ ] **Step 16 (MANUAL, koordinator): uji manual Playwright dan Lighthouse**

Server lokal hidup (`http://unpam-hospital.test`), data demo baru dari Step 15. Catat hasil pada "Catatan Eksekusi" rencana ini. Mailer `log` (bawaan); tautan reset diambil dari log (blueprint 16 §10.1; lihat README bagian "Mencoba lupa password di lokal").

1. **Daftar, dashboard, keluar.** Desktop 1280 px, tamu. Buka `/daftar`: satu `<h1>` "Daftar Akun Pasien", tanpa field NIK dan BPJS, kartu lebar. Kirim kosong (galat per field, ringkasan galat, fokus pindah ke ringkasan); password `abc` ditolak; tanggal lahir masa depan ditolak; persetujuan tidak dicentang ditolak. Daftar dengan data valid (nama "Siti Rahma", email `Siti@Contoh.test`, telepon `0812-3456-7890`): redirect ke `/pasien` dengan flash "Registrasi berhasil. Selamat datang, Siti Rahma."; dashboard menampilkan "Halo, Siti Rahma", "No. pasien: P000008", teks "Kunjungan Mendatang", "Profil Anda", Email, dan Nomor telepon, "Belum ada pendaftaran", dan tombol "Lihat Jadwal Dokter" (BUKAN "Daftar Kunjungan" karena route fase 6 belum ada). Cek log: email selamat datang ada dan tanpa telepon, alamat, tanggal lahir, BPJS, atau nomor pasien. Klik "Keluar": flash "Anda telah keluar." di beranda; `/pasien` kini mengalihkan ke `/masuk`. Lalu coba daftar lagi dengan `siti@contoh.test` (huruf berbeda): ditolak "Email ini sudah terdaftar. Silakan masuk atau gunakan email lain."
2. **Masuk salah dan throttle.** `/masuk` dengan email demo tetapi password salah: pesan "Email atau password salah." dan SAMA PERSIS untuk email yang tidak terdaftar, untuk akun staf (`superadmin@unpamhospital.test`), dan untuk pasien yang dinonaktifkan (nonaktifkan lewat phpMyAdmin lalu aktifkan kembali). Percobaan ke-6 dalam semenit: flash "Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik." dengan header `Retry-After`; email lain tidak terkena. Password tidak tertampil kembali di form. Masuk dengan `pasien@unpamhospital.test` / `Demo12345` dan "Ingat saya": ke `/pasien` ("Halo, Anisa Rahmawati", "No. pasien: P000001").
3. **Lupa password.** `/lupa-password`: kirim email demo, lalu email yang tidak ada, lalu email staf: ketiganya menghasilkan pesan dan status identik ("Jika email terdaftar, tautan untuk mengatur ulang password telah dikirim. Periksa kotak masuk Anda."); tautan tidak tampil di halaman. Ambil tautan dari log (skrip README); buka di jendela lain: `/reset-password/{token}` memperlihatkan email terkunci (readonly) dan dua field password; isi password baru `Rahasia456` dan konfirmasi: redirect ke `/masuk` dengan flash "Password berhasil diubah. Silakan masuk dengan password baru."; tautan yang sama dipakai lagi memberi "Tautan pengaturan ulang password tidak valid atau sudah kedaluwarsa. Silakan ajukan permintaan baru."; sesi lama pasien itu sudah berakhir; masuk dengan password baru berhasil. Permintaan ke-4 dalam 10 menit: pesan throttle. Kembalikan password demo bila perlu (`migrate:fresh --seed` atau reset ulang).
4. **Profil.** `/pasien/profil` (breadcrumb "Portal Pasien › Profil dan Akun"; satu `<h1>`; ringkasan galat di atas dua formulir). Ubah nama, alamat, dan telepon: flash "Profil berhasil diperbarui."; header dan dashboard menampilkan nama baru. Ubah email menjadi email baru: berhasil tanpa meminta password; masuk ulang memakai email baru. Ubah email menjadi `budi.hartanto@contoh.test`: galat "Email ini sudah terdaftar. Gunakan email lain." dan tidak ada perubahan lain tersimpan. Pasien demo (BPJS tersimpan): tampil "Nomor BPJS tersimpan: •••••••••0011" dan kotak "Hapus nomor BPJS dari profil saya"; centang lalu simpan: tampil "Belum ada nomor BPJS tersimpan." dan kolom `bpjs_number_last4` di DB menjadi NULL. Pastikan tidak ada input nomor BPJS dan tidak ada tombol "Tampilkan password".
5. **Ganti password.** Salah password saat ini: galat pada field itu saja; password baru lemah atau konfirmasi tidak cocok: galat per field; benar: flash "Password berhasil diganti."; sesi di jendela lain (peramban kedua) berakhir pada permintaan berikutnya dan sesi kini tetap. Periksa tabel `audit_logs` lewat phpMyAdmin atau `tinker` (`SELECT action, description, old_values, new_values FROM audit_logs ORDER BY id DESC LIMIT 10`): aksi `patient.registered`, `patient.updated`, `auth.password_changed`, dan `auth.password_reset` tanpa nama, email, telepon, atau password pada `description`/`old_values`/`new_values`.
6. **Staf di portal pasien dan pasien di admin.** Login sebagai Admin Konten (`/admin/login`): buka `/pasien` dan `/pasien/profil` mengalihkan ke `/admin`; `POST /keluar` tidak dapat diuji dari UI. Sebagai pasien: `/admin`, `/admin/dokter` menjawab 403 tanpa konten admin; `/masuk`, `/daftar`, `/lupa-password`, `/reset-password/x` mengalihkan ke `/pasien`; `/pasien/pendaftaran/baru` 404.
7. **Responsif 360 px.** `/masuk`, `/daftar`, `/lupa-password`, `/reset-password/{token}`, `/pasien`, dan `/pasien/profil` tanpa gulir horizontal; kartu auth lebar `/daftar` bertumpuk satu kolom (tanggal lahir dan jenis kelamin); target sentuh tombol ≥ 44 px; label, galat, dan flash terbaca.
8. **Konsol dan jaringan.** Tidak ada galat JavaScript, pelanggaran CSP, atribut event inline, atau permintaan ke domain eksternal; tetap satu pendengar `error` (milik fallback gambar); tidak ada entri Vite baru.
9. **Aksesibilitas (Lighthouse, desktop dan mobile).** Audit `/masuk`, `/daftar`, dan `/pasien` (login dulu): aksesibilitas ≥ 90 (15 A11-05), target 100 seperti fase 3 sampai 4b; periksa urutan fokus keyboard `/daftar` (skip link → logo → field berurutan → persetujuan → "Daftar" → tautan "Masuk"), `aria-describedby` dan `aria-invalid` pada field bergalat, dan label tombol "Memproses…" saat kirim.
10. **Laporan.** Simpan laporan bergambar ke `C:\Users\admin\Documents\UNPAM-Uji-Manual-<tanggal>-fase-5\index.html` (tangkapan layar tiap kelompok 1–9, skor Lighthouse tiga halaman, daftar temuan dengan tingkat Critical/Important/Minor).

Hasil dan temuan dicatat koordinator; tidak ada commit pada langkah ini.

#### Catatan penulis (konteks untuk implementer dan reviewer)

1. **Staf di #27 sampai #30: `→A`, bukan `403`.** Kerangka F5-R5 mengikuti kode; 15 §3.2 diubah di Step 11 (juga baris #29 dan #30 yang route-nya milik fase 6, supaya tabel konsisten) dan dicatat sebagai D-142. Bila pemilik ingin `403` penuh, itu perubahan middleware `EnsureAccountType` plus tes `AdminAreaAccessTest` (di luar fase 5).
2. **Tambahan di luar daftar brief: 11 §5.1.** Teks 11 baris "Event `booted()`: `creating` → `patient_number` sementara …" bertentangan dengan F5-R10; Step 11 menambahkan catatan "Diganti pada fase 5 (D-145)" di depannya (satu edit kecil, tanpa menghapus rancangan awal).
3. **Jumlah route total di tes** dihitung dari aksi berawalan `App\` (setara `route:list --except-vendor`). Bila fase berikutnya menambah route berbasis Closure milik aplikasi, tes ini perlu disesuaikan; tidak berdampak sekarang (semua route aplikasi memakai controller).
4. **`PatientPageMatrixTest` mengandalkan Task 1 sampai 6**: `createPatient()` (Task 1) dan semua route/perilaku redirect (Task 3 sampai 6). Sel `A` pada mutasi bergantung pada `EnsureAccountType` yang mengalihkan sebelum validasi; sel `V` bergantung pada `UpdatePatientProfileRequest::authorize()` bernilai true untuk pasien berprofil dan validasi memberi 302 dengan galat sesi (tanpa header JSON).
5. **Langkah sanity (Step 5)** menulis ulang (`[IO.File]::WriteAllText`, UTF-8 tanpa BOM) pada `routes/patient.php`; pola `->name('dashboard')` harus cocok dengan cara Task 3 menulis route. Bila penulis Task 3 memakai kutip ganda atau urutan berbeda, implementer cukup menyesuaikan pola atau melewati langkah itu (bukan syarat lulus).
6. **Tes AKS-07** (intended URL dengan `?sesi=ID`) tidak ada di T8: kerangka F5-R28 menaruhnya di Task 3 (`PatientLoginTest`, `/pasien/profil?contoh=utuh`).

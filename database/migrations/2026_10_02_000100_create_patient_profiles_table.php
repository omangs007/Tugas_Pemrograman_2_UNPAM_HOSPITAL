<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 09 §2.2. Dibuat lebih awal dari migration master data (F3-R2): hanya merujuk `users`.
     * `patient_number` NULLable karena diisi setelah insert (id baru diketahui sesudahnya, fase 5).
     */
    public function up(): void
    {
        Schema::create('patient_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('patient_number', 10)->nullable()->unique();
            $table->string('full_name', 150);
            $table->date('birth_date');
            $table->char('gender', 1);
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('address', 500);
            $table->text('bpjs_number')->nullable();
            $table->char('bpjs_number_last4', 4)->nullable();
            $table->string('registration_channel', 10);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('consent_at');
            $table->string('consent_version', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('full_name');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_profiles');
    }
};

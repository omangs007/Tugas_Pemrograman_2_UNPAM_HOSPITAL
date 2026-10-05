<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 09 §3.7 dan §11.2. Baris inilah yang dikunci (FOR UPDATE) saat booking. UNIQUE
     * (doctor_schedule_id, session_date) menerima banyak NULL (sesi tambahan manual) dan membuat
     * insertOrIgnore pembangkit sesi idempoten. Kolom JSON `pending_change` otomatis mendapat
     * CHECK json_valid (nama = nama kolom) di MariaDB 10.4.
     */
    public function up(): void
    {
        Schema::create('practice_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->restrictOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->foreignId('doctor_schedule_id')->nullable()->constrained('doctor_schedules')->nullOnDelete();
            $table->date('session_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('quota');
            $table->unsignedSmallInteger('booked_count')->default(0);
            $table->unsignedSmallInteger('last_queue_number')->default(0);
            $table->string('status', 20)->default('open');
            $table->string('status_reason')->nullable();
            $table->foreignId('schedule_exception_id')->nullable()->constrained('schedule_exceptions')->nullOnDelete();
            $table->json('pending_change')->nullable();
            $table->boolean('is_manually_modified')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['doctor_schedule_id', 'session_date']);
            $table->index(['session_date', 'status']);
            $table->index(['doctor_id', 'session_date']);
            $table->index(['clinic_id', 'session_date']);
        });

        DB::statement('ALTER TABLE practice_sessions
            ADD CONSTRAINT chk_sessions_quota CHECK (booked_count >= 0 AND booked_count <= quota),
            ADD CONSTRAINT chk_sessions_time CHECK (end_time > start_time),
            ADD CONSTRAINT chk_sessions_quota_range CHECK (quota BETWEEN 1 AND 200),
            ADD CONSTRAINT chk_sessions_queue CHECK (last_queue_number >= booked_count)');
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_sessions');
    }
};

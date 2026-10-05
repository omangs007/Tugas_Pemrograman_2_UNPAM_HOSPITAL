<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 09 §3.5 dan §11.3. Larangan irisan jam tidak bisa dinyatakan sebagai constraint MariaDB 10.4,
     * sehingga dijaga aturan validasi aplikasi (NoOverlappingSchedule, Task 9).
     */
    public function up(): void
    {
        Schema::create('doctor_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->restrictOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('quota');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['doctor_id', 'day_of_week', 'is_active']);
            $table->index(['is_active', 'valid_from', 'valid_until']);
        });

        DB::statement('ALTER TABLE doctor_schedules
            ADD CONSTRAINT chk_schedules_time CHECK (end_time > start_time),
            ADD CONSTRAINT chk_schedules_day CHECK (day_of_week BETWEEN 1 AND 7),
            ADD CONSTRAINT chk_schedules_quota CHECK (quota BETWEEN 1 AND 200),
            ADD CONSTRAINT chk_schedules_validity CHECK (valid_until IS NULL OR valid_until >= valid_from)');
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_schedules');
    }
};

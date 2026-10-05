<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 09 §3.6 dan §11.3. `doctor_id` NULL = berlaku untuk seluruh dokter/RS (00 E.2). CHECK scope hanya
     * melarang kombinasi salah dan tidak membatasi daftar nilai `type` (konvensi 09 §1.2 butir 5).
     */
    public function up(): void
    {
        Schema::create('schedule_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->restrictOnDelete();
            $table->string('type', 20);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
            $table->index(['doctor_id', 'start_date']);
        });

        DB::statement("ALTER TABLE schedule_exceptions
            ADD CONSTRAINT chk_exceptions_dates CHECK (end_date >= start_date),
            ADD CONSTRAINT chk_schedule_exceptions_scope CHECK (
                (type <> 'doctor_leave' OR doctor_id IS NOT NULL)
                AND (type NOT IN ('hospital_holiday','national_holiday') OR doctor_id IS NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_exceptions');
    }
};

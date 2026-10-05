<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 09 §4.1 dan §11.1. `active_flag` adalah kolom generated STORED (1 untuk pending/confirmed/checked_in,
     * selain itu NULL) yang tidak pernah ditulis aplikasi. UNIQUE (patient_profile_id, practice_session_id,
     * active_flag) menggantikan partial unique index; namanya wajib eksplisit karena nama bawaan 70 karakter
     * melewati batas 64.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->string('booking_code', 13)->unique();
            $table->foreignId('patient_profile_id')->constrained('patient_profiles')->restrictOnDelete();
            $table->foreignId('practice_session_id')->constrained('practice_sessions')->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors')->restrictOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->date('session_date');
            $table->unsignedSmallInteger('queue_number');
            $table->string('status', 20);
            $table->string('channel', 10);
            $table->string('payment_type', 10);
            $table->foreignId('insurance_partner_id')->nullable()->constrained('insurance_partners')->restrictOnDelete();
            $table->text('payment_reference')->nullable();
            $table->char('payment_reference_last4', 4)->nullable();
            $table->string('complaint', 500)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('no_show_at')->nullable();
            $table->string('cancelled_by_type', 10)->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->dateTime('schedule_changed_at')->nullable();
            $table->tinyInteger('active_flag')->nullable()
                ->storedAs("CASE WHEN status IN ('pending','confirmed','checked_in') THEN 1 ELSE NULL END");
            $table->timestamps();

            $table->unique(['practice_session_id', 'queue_number']);
            $table->unique(['patient_profile_id', 'practice_session_id', 'active_flag'], 'appointments_patient_session_active_unique');
            $table->index(['session_date', 'status']);
            $table->index(['patient_profile_id', 'session_date']);
            $table->index(['doctor_id', 'session_date']);
            $table->index('status');
            $table->index('created_at');
        });

        DB::statement('ALTER TABLE appointments ADD CONSTRAINT chk_appointments_queue CHECK (queue_number >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};

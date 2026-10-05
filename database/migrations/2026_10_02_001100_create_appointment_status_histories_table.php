<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 09 §4.2. Tabel append-only: hanya `created_at` (model memakai UPDATED_AT = null). Baris appointment
     * tidak pernah dihapus, sehingga CASCADE hanya jaring pengaman.
     */
    public function up(): void
    {
        Schema::create('appointment_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('actor_type', 10);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['appointment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_status_histories');
    }
};

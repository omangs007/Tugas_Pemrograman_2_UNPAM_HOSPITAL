<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel append-only (09 §7.3): hanya `created_at`. Kolom `json` MariaDB = LONGTEXT
     * dengan CHECK json_valid otomatis.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 10);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 100);
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_type_auditable_id_index');
            $table->index(['user_id', 'created_at'], 'audit_logs_user_id_created_at_index');
            $table->index(['action', 'created_at'], 'audit_logs_action_created_at_index');
            $table->index('created_at', 'audit_logs_created_at_index');

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Kolom akun 09 §2.1. `account_type` sengaja tanpa default agar tidak ada akun
     * yang menjadi staf/pasien secara tidak sengaja.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('account_type', 10)->after('password');
            $table->boolean('is_active')->default(true)->after('account_type');
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->dateTime('last_login_at')->nullable()->after('must_change_password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'account_type', 'is_active', 'must_change_password', 'last_login_at']);
        });
    }
};

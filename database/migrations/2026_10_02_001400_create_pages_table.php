<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 09 §6.1. Lima belas baris halaman sistem (`pages.key`) dibuat PageSeeder di semua lingkungan; tidak ada
     * route tambah/hapus. `body` MEDIUMTEXT berisi rich text yang disanitasi (hanya key ber-body yang boleh
     * mengisinya, ditegakkan Form Request). Tanpa CHECK.
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('title', 150);
            $table->string('subtitle')->nullable();
            $table->mediumText('body')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->boolean('is_system')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};

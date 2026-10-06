<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 09 §6.8. Pesan dari formulir kontak publik. IP mentah tidak disimpan: `ip_hash` berisi SHA-256 heksadesimal
     * (64 karakter) dari IP dan APP_KEY. `status` bernilai `ContactMessageStatus` (`new` bawaan), `handled_by` dan
     * `handled_at` diisi staf saat pesan dibalas atau diarsipkan. Tidak ada route hapus (00 C); pesan hanya diarsipkan.
     */
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('subject', 150);
            $table->text('message');
            $table->string('status', 10)->default('new');
            $table->dateTime('consent_at');
            $table->char('ip_hash', 64);
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('handled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};

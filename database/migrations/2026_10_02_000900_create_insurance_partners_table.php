<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 09 §6.7. Dibuat di fase 3 hanya karena `appointments.insurance_partner_id` wajib FK RESTRICT
     * (00 E.3, F3-R3). Model, factory, seeder, dan halaman admin-nya menyusul di fase 4. Tanpa CHECK.
     */
    public function up(): void
    {
        Schema::create('insurance_partners', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->string('type', 20);
            $table->string('description', 500)->nullable();
            $table->string('website_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'type', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_partners');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 09 §5.2. `category` bernilai `FacilityCategory` (`medis`, `penunjang`, `umum`) tanpa CHECK nilai
     * (konvensi 09 §1.2 butir 5); daftar nilai dijaga enum dan FacilityRequest. Tanpa FK: fasilitas tidak
     * dirujuk tabel lain dan tidak merujuk tabel lain.
     */
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->string('category', 20);
            $table->string('short_description', 300);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'category', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};

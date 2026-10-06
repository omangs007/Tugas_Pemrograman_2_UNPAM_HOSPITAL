<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 09 §5.1 dan §11. Model kapasitas agregat per kategori (00 E.4); ketersediaan diperbarui manual oleh staf.
     * `amenities` bertipe JSON (array teks datar) sehingga MariaDB membuat CHECK `json_valid` otomatis dengan nama
     * kolom. CHECK `chk_room_categories_beds` menjadi pagar terakhir setelah Form Request. Pada DB yang sudah
     * memigrasikan fase 1 sampai 4a, migration ini berjalan sebagai migration tertunda (F4B-R2).
     */
    public function up(): void
    {
        Schema::create('room_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->string('short_description', 300);
            $table->text('description')->nullable();
            $table->json('amenities')->nullable();
            $table->unsignedSmallInteger('bed_capacity')->default(0);
            $table->unsignedSmallInteger('beds_available')->default(0);
            $table->decimal('sample_rate', 12, 2)->nullable();
            $table->string('rate_unit', 30)->default('per malam');
            $table->string('rate_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->dateTime('availability_updated_at')->nullable();
            $table->foreignId('availability_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        DB::statement('ALTER TABLE room_categories ADD CONSTRAINT chk_room_categories_beds CHECK (beds_available >= 0 AND beds_available <= bed_capacity)');
    }

    public function down(): void
    {
        Schema::dropIfExists('room_categories');
    }
};

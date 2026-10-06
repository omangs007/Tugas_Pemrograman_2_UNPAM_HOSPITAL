<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 09 §6.4 dan §11.3. `starts_at`/`ends_at` NULL berarti tanpa batas; CHECK hanya melarang
     * `ends_at` yang tidak lebih besar dari `starts_at` (pagar terakhir setelah BannerRequest).
     */
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->string('subtitle')->nullable();
            $table->string('button_label', 50)->nullable();
            $table->string('button_url')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        DB::statement('ALTER TABLE banners ADD CONSTRAINT chk_banners_period CHECK (starts_at IS NULL OR ends_at IS NULL OR ends_at > starts_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};

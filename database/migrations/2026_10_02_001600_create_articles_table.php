<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 09 §6.3. `slug` UNIQUE mencakup baris soft delete (slug artikel terhapus tidak dipakai ulang). `published_at`
     * bertipe DATETIME (waktu dinding aplikasi); `published` wajib ber-`published_at` dijaga ArticleRequest
     * (invarian I-24), bukan CHECK. Kategori dan penulis yang dihapus membuat kolomnya NULL.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_category_id')->nullable()->constrained('article_categories')->nullOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->string('excerpt', 300);
            $table->longText('body');
            $table->string('status', 10)->default('draft');
            $table->dateTime('published_at')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};

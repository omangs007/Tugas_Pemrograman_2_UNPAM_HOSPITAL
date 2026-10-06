<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 09 §6.6. `category` tanpa CHECK nilai (konvensi 09 §1.2 butir 5): daftar `pendaftaran`, `bpjs`, `layanan`,
     * `umum` dijaga `Faq::CATEGORIES` dan FaqRequest. `answer` berupa teks biasa yang dirender dengan `{{ }}`.
     */
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 50);
            $table->string('question');
            $table->text('answer');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['is_published', 'category', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};

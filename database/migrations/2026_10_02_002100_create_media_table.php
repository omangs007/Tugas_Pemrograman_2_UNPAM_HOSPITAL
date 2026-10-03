<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('collection', 40);
            $table->string('disk', 10)->default('public');
            $table->string('directory');
            $table->string('file_name', 100)->unique();
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->string('extension', 10);
            $table->unsignedInteger('size_bytes');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->string('thumb_path')->nullable();
            $table->string('alt_text');
            $table->string('caption')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['model_type', 'model_id', 'collection', 'sort_order']);
        });

        DB::statement('ALTER TABLE media ADD CONSTRAINT chk_media_owner CHECK ((model_type IS NULL AND model_id IS NULL) OR (model_type IS NOT NULL AND model_id IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};

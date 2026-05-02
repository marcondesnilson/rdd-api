<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('publications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('post_type', 24)->index(); // timeline | publication
            $table->string('content_type', 24)->default('text')->index(); // text | image | video | link
            $table->string('slug')->nullable()->unique();
            $table->string('title', 180)->nullable();
            $table->string('excerpt', 500)->nullable();
            $table->longText('content')->nullable();
            $table->text('body')->nullable();
            $table->string('tag', 120)->nullable();
            $table->string('cover_url')->nullable();
            $table->string('media_url')->nullable();
            $table->string('status', 24)->default('published')->index();
            $table->boolean('search_engine_index')->default(true);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'post_type', 'status']);
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('publication_tag', function (Blueprint $table): void {
            $table->foreignUlid('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->foreignUlid('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->softDeletes();

            $table->primary(['publication_id', 'tag_id']);
        });

        Schema::create('publication_files', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->foreignUlid('file_id')->constrained('files')->cascadeOnDelete();
            $table->string('kind', 24)->default('attachment')->index(); // image | video | cover | attachment
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['publication_id', 'file_id']);
        });

        Schema::create('publication_comments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('parent_id')->nullable()->constrained('publication_comments')->nullOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['publication_id', 'created_at']);
        });

        Schema::create('publication_likes', function (Blueprint $table): void {
            $table->foreignUlid('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->softDeletes();

            $table->primary(['publication_id', 'user_id']);
        });

        Schema::create('publication_saves', function (Blueprint $table): void {
            $table->foreignUlid('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->softDeletes();

            $table->primary(['publication_id', 'user_id']);
        });

        Schema::create('publication_views', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('viewed_at')->useCurrent()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['publication_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publication_views');
        Schema::dropIfExists('publication_saves');
        Schema::dropIfExists('publication_likes');
        Schema::dropIfExists('publication_comments');
        Schema::dropIfExists('publication_files');
        Schema::dropIfExists('publication_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('publications');
    }
};

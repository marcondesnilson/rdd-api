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
        Schema::create('files', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->boolean('success')->default(true);
            $table->char('external_file_id', 26)->unique();
            $table->string('original_filename');
            $table->string('public_url');
            $table->string('mime_type', 191);
            $table->unsignedBigInteger('size');
            $table->boolean('is_public')->default(true);
            $table->boolean('is_converted')->default(true);
            $table->timestamps();

            $table->index('mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};

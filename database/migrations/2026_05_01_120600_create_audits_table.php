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
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $tableName = config('audit.drivers.database.table', 'audits');

        Schema::connection($connection)->create($tableName, function (Blueprint $table): void {
            $morphPrefix = config('audit.user.morph_prefix', 'user');

            $table->id();
            $table->string($morphPrefix.'_type')->nullable();
            $table->char($morphPrefix.'_id', 26)->nullable();
            $table->string('event');
            $table->string('auditable_type');
            $table->char('auditable_id', 26);
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->text('url')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1023)->nullable();
            $table->string('tags')->nullable();
            $table->timestamps();

            $table->index([$morphPrefix.'_id', $morphPrefix.'_type']);
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $tableName = config('audit.drivers.database.table', 'audits');

        Schema::connection($connection)->dropIfExists($tableName);
    }
};

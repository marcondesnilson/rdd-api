<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_mfa', function (Blueprint $table): void {
            $table->text('totp_secret')->nullable()->after('enabled');
            $table->string('credential_id', 255)->nullable()->after('totp_secret');
            $table->timestamp('last_used_at')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_mfa', function (Blueprint $table): void {
            $table->dropColumn([
                'totp_secret',
                'credential_id',
                'last_used_at',
            ]);
        });
    }
};

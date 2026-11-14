<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The personal MCP token was replaced by OAuth (Passport) with per-client permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['mcp_token_hash']);
            $table->dropColumn(['mcp_token_hash', 'mcp_token_created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mcp_token_hash', 64)->nullable()->unique()->after('remember_token');
            $table->timestamp('mcp_token_created_at')->nullable()->after('mcp_token_hash');
        });
    }
};

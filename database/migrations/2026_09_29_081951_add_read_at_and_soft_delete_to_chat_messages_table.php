<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {

            if (!Schema::hasColumn('chat_messages', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('is_read');
            }

            if (!Schema::hasColumn('chat_messages', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {

            if (Schema::hasColumn('chat_messages', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }

            // Note: read_at is owned by the earlier migration
            // (2026_09_29_081157_add_read_at_to_chat_messages_table),
            // so we do NOT drop it here.
        });
    }
};
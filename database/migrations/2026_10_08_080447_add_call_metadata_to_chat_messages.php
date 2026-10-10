<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_messages', 'call_direction')) {
                $table->enum('call_direction', ['incoming', 'outgoing'])
                    ->nullable()
                    ->after('sender_type');
            }

            if (!Schema::hasColumn('chat_messages', 'call_duration')) {
                $table->integer('call_duration')
                    ->nullable()
                    ->after('call_direction');
            }

            if (!Schema::hasColumn('chat_messages', 'call_type')) {
                $table->enum('call_type', ['audio', 'video'])
                    ->nullable()
                    ->after('call_duration');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['call_direction', 'call_duration', 'call_type']);
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige o tipo de user_id nas tabelas de conversas do agente AI.
 * O pacote Laravel\Ai usou foreignId() (bigint) mas os IDs de usuário
 * neste projeto são ULIDs (varchar 26).
 */
return new class extends Migration
{
    public function up(): void
    {
        // agent_conversations
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->dropIndex('agent_conversations_user_id_updated_at_index');
        });

        DB::statement('ALTER TABLE agent_conversations ALTER COLUMN user_id TYPE varchar(26) USING user_id::varchar');

        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->index(['user_id', 'updated_at'], 'agent_conversations_user_id_updated_at_index');
        });

        // agent_conversation_messages
        Schema::table('agent_conversation_messages', function (Blueprint $table) {
            $table->dropIndex('agent_conversation_messages_user_id_index');
            $table->dropIndex('conversation_index');
        });

        DB::statement('ALTER TABLE agent_conversation_messages ALTER COLUMN user_id TYPE varchar(26) USING user_id::varchar');

        Schema::table('agent_conversation_messages', function (Blueprint $table) {
            $table->index('user_id', 'agent_conversation_messages_user_id_index');
            $table->index(['conversation_id', 'user_id', 'updated_at'], 'conversation_index');
        });
    }

    public function down(): void
    {
        // agent_conversation_messages
        Schema::table('agent_conversation_messages', function (Blueprint $table) {
            $table->dropIndex('agent_conversation_messages_user_id_index');
            $table->dropIndex('conversation_index');
        });

        DB::statement('ALTER TABLE agent_conversation_messages ALTER COLUMN user_id TYPE bigint USING user_id::bigint');

        Schema::table('agent_conversation_messages', function (Blueprint $table) {
            $table->index('user_id', 'agent_conversation_messages_user_id_index');
            $table->index(['conversation_id', 'user_id', 'updated_at'], 'conversation_index');
        });

        // agent_conversations
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->dropIndex('agent_conversations_user_id_updated_at_index');
        });

        DB::statement('ALTER TABLE agent_conversations ALTER COLUMN user_id TYPE bigint USING user_id::bigint');

        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->index(['user_id', 'updated_at'], 'agent_conversations_user_id_updated_at_index');
        });
    }
};

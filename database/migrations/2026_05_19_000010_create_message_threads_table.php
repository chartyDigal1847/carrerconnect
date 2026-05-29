<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->foreignId('creator_id')->constrained('faculty_users')->cascadeOnDelete();
            $table->json('participants'); // array of user IDs
            $table->integer('messages_count')->default(0);
            $table->datetime('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('creator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_threads');
    }
};

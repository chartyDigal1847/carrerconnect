<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('faculty_users')->cascadeOnDelete();
            $table->string('action'); // created, updated, deleted, posted, commented
            $table->string('entity_type'); // announcement, post, resource, thread
            $table->unsignedBigInteger('entity_id');
            $table->json('changes')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('action');
            $table->index('entity_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_boards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('creator_id')->constrained('faculty_users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->enum('visibility', ['all', 'department', 'role'])->default('all');
            $table->json('allowed_roles')->nullable();
            $table->boolean('is_moderated')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('posts_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('creator_id');
            $table->index('department_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_boards');
    }
};

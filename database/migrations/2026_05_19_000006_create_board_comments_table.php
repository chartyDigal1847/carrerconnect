<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('board_posts')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('faculty_users')->cascadeOnDelete();
            $table->longText('content');
            $table->foreignId('parent_id')->nullable()->constrained('board_comments')->cascadeOnDelete();
            $table->boolean('is_moderated')->default(false);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('post_id');
            $table->index('author_id');
            $table->index('parent_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_comments');
    }
};

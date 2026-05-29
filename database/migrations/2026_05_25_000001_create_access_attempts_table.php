<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_user_id')->nullable()->constrained('faculty_users')->nullOnDelete();
            $table->string('sso_id')->nullable();
            $table->string('role_attempted')->nullable();
            $table->string('reason');
            $table->string('endpoint');
            $table->string('method', 10);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->boolean('was_blocked')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index('was_blocked');
            $table->index('role_attempted');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_attempts');
    }
};

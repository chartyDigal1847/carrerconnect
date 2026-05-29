<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculty_users', function (Blueprint $table) {
            $table->id();
            $table->string('sso_id')->unique();
            $table->string('email')->unique();
            $table->string('name');
            $table->string('role'); // instructor, cashier, librarian, admission_officer, career_officer, admin
            $table->string('department')->nullable();
            $table->json('permissions')->nullable();
            $table->string('profile_picture')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('bio')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('role');
            $table->index('is_active');
            $table->index('department');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_users');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('career_jobs')) {
            Schema::create('career_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('company_name');
                $table->string('location');
                $table->string('job_type');
                $table->string('work_setup')->default('hybrid');
                $table->text('description')->nullable();
                $table->enum('status', ['open', 'closed'])->default('open');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('internships')) {
            Schema::create('internships', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('company_name');
                $table->string('location');
                $table->string('work_setup')->default('hybrid');
                $table->unsignedSmallInteger('duration_weeks')->default(12);
                $table->text('description')->nullable();
                $table->enum('status', ['open', 'closed'])->default('open');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('applications')) {
            Schema::create('applications', function (Blueprint $table) {
                $table->id();
                $table->string('student_name');
                $table->string('email');
                $table->string('phone')->nullable();
                $table->string('resume')->nullable();
                $table->foreignId('job_id')->nullable()->constrained('career_jobs')->nullOnDelete();
                $table->foreignId('internship_id')->nullable()->constrained('internships')->nullOnDelete();
                $table->string('position');
                $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
                $table->date('date_applied');
                $table->enum('submitted_by', ['student', 'admin'])->default('student');
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->index('email');
            });
        }

        if (! Schema::hasTable('recruitment_activity_logs')) {
            Schema::create('recruitment_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->string('message');
                $table->string('type')->default('gray');
                $table->timestamp('logged_at')->useCurrent();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
        Schema::dropIfExists('internships');
        Schema::dropIfExists('career_jobs');
        Schema::dropIfExists('recruitment_activity_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_outboxes', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('event_name');
            $table->string('source_service')->default('careerconnect');
            $table->json('payload');
            $table->string('schema_version')->default('1.0');
            $table->string('correlation_id')->nullable();
            $table->boolean('is_published')->default(false);
            $table->integer('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->datetime('published_at')->nullable();
            $table->timestamps();
            
            $table->index('event_name');
            $table->index('is_published');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_outboxes');
    }
};

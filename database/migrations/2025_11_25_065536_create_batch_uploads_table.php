<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('batch_uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->unique();
            $table->integer('total_files')->default(0);
            $table->integer('successful_files')->default(0);
            $table->integer('failed_files')->default(0);
            $table->integer('pending_files')->default(0);
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->json('metadata')->nullable(); // Store user info, IP, etc.
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            $table->index('batch_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_uploads');
    }
};

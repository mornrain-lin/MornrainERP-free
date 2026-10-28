<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_key', 64)->index();
            $table->string('category', 32)->index();
            $table->string('title', 191);
            $table->foreignId('applicant_id')->nullable()->index();
            $table->json('data')->nullable();
            $table->string('status', 16)->default('pending')->index(); // pending|approved|rejected|canceled
            $table->unsignedSmallInteger('current_node')->default(0);
            $table->unsignedSmallInteger('node_count')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_instance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('workflow_instances')->cascadeOnDelete();
            $table->unsignedSmallInteger('node')->default(0);
            $table->string('action', 16); // submit|approve|reject|cancel
            $table->foreignId('actor_id')->nullable();
            $table->string('actor_name', 60)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instance_logs');
        Schema::dropIfExists('workflow_instances');
    }
};

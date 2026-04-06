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
        Schema::create('pipeline_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->timestamp('occurred_at');
            $table->string('from_stage')->nullable();
            $table->string('to_stage')->nullable();
            $table->string('from_closing_result')->nullable();
            $table->string('to_closing_result')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'occurred_at']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipeline_activities');
    }
};

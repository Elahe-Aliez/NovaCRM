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
        Schema::create('meeting_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->timestamp('occurred_at');
            $table->timestamp('from_scheduled_at')->nullable();
            $table->timestamp('to_scheduled_at')->nullable();
            $table->string('from_result')->nullable();
            $table->string('to_result')->nullable();
            $table->timestamps();

            $table->index(['meeting_id', 'occurred_at']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_activities');
    }
};

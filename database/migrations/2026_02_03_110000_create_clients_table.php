<?php

use App\Enums\PipelineStage;
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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('business_name');
            $table->string('address')->nullable();
            $table->string('pipeline_stage')->default(PipelineStage::Lead->value);
            $table->string('closing_result')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'pipeline_stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};

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
        Schema::create('investment_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portfolio_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_provider_configuration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_used', 50);
            $table->string('model_used', 100);
            $table->decimal('available_amount', 14, 2);
            $table->jsonb('financial_snapshot')->nullable();
            $table->jsonb('system_diagnosis_snapshot')->nullable();
            $table->text('executive_summary')->nullable();
            $table->jsonb('priorities')->nullable();
            $table->jsonb('sources')->nullable();
            $table->jsonb('limitations')->nullable();
            $table->text('raw_prompt')->nullable();
            $table->jsonb('raw_response')->nullable();
            $table->string('status', 30)->default('completed'); // completed, failed
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('analysis_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_analysis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asset_class', 50);
            $table->string('ticker_suggested', 30)->nullable();
            $table->decimal('suggested_amount', 14, 2);
            $table->decimal('share_percentage', 5, 2);
            $table->text('reasoning');
            $table->text('risks')->nullable();
            $table->text('discarded_alternatives')->nullable();
            $table->string('status', 30)->default('pending'); // pending, accepted, partial, rejected, deferred
            $table->text('user_decision_notes')->nullable();
            $table->timestamps();

            $table->index(['investment_analysis_id', 'asset_class']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_allocations');
        Schema::dropIfExists('investment_analyses');
    }
};

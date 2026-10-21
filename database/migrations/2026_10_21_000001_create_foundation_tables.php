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
        Schema::create('financial_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('target_amount', 14, 2);
            $table->decimal('current_amount', 14, 2)->default(0.00);
            $table->date('target_date')->nullable();
            $table->string('priority', 20)->default('medium'); // low, medium, high
            $table->string('liquidity_need', 20)->default('medium'); // low, medium, high
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'priority']);
        });

        Schema::create('investor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('risk_profile', 30)->default('moderate'); // conservative, moderate, bold, aggressive
            $table->unsignedSmallInteger('horizon_years')->default(5);
            $table->string('investment_focus', 30)->default('balanced'); // growth, dividends, capital_preservation, balanced
            $table->jsonb('allowed_asset_classes')->nullable();
            $table->jsonb('sector_restrictions')->nullable();
            $table->decimal('liquidity_reserve_target', 14, 2)->default(0.00);
            $table->decimal('max_asset_concentration_percentage', 5, 2)->default(20.00);
            $table->decimal('max_sector_concentration_percentage', 5, 2)->default(40.00);
            $table->jsonb('criteria')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('allocation_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('asset_class', 50); // acoes_br, fiis, tesouro_direto, etfs_internacionais, etc.
            $table->decimal('target_percentage', 5, 2);
            $table->decimal('min_percentage', 5, 2)->default(0.00);
            $table->decimal('max_percentage', 5, 2)->default(100.00);
            $table->decimal('tolerance_percentage', 5, 2)->default(5.00);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'asset_class']);
        });

        Schema::create('ai_provider_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('provider_slug', 50); // gemini, openai, anthropic
            $table->string('model_id', 100); // gemini-1.5-pro, etc.
            $table->text('api_key'); // encrypted
            $table->string('endpoint')->nullable();
            $table->jsonb('parameters')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['provider_slug', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_provider_configurations');
        Schema::dropIfExists('allocation_targets');
        Schema::dropIfExists('investor_profiles');
        Schema::dropIfExists('financial_goals');
    }
};

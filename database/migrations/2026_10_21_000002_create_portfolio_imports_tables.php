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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 30)->unique();
            $table->string('name');
            $table->string('asset_class', 50); // acoes_br, fiis, tesouro_direto, etfs_internacionais, etc.
            $table->string('currency', 3)->default('BRL');
            $table->string('sector')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['asset_class']);
        });

        Schema::create('portfolio_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('reference_date');
            $table->string('file_path');
            $table->char('file_hash', 64); // SHA-256
            $table->string('original_filename');
            $table->string('status', 30)->default('pending'); // pending, completed, failed, cancelled
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('accepted_rows')->default(0);
            $table->unsignedInteger('rejected_rows')->default(0);
            $table->jsonb('validation_errors')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'reference_date']);
            $table->index('file_hash');
        });

        Schema::create('portfolio_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->decimal('average_price', 14, 4)->nullable();
            $table->decimal('current_price', 14, 4)->nullable();
            $table->decimal('total_amount', 14, 2);
            $table->decimal('weight_percentage', 5, 2)->nullable();
            $table->jsonb('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['portfolio_import_id', 'asset_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_positions');
        Schema::dropIfExists('portfolio_imports');
        Schema::dropIfExists('assets');
    }
};

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
        Schema::create('executed_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_analysis_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('analysis_allocation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->decimal('quantity', 14, 4)->nullable();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->date('execution_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'execution_date']);
            $table->index('investment_analysis_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('executed_contributions');
    }
};

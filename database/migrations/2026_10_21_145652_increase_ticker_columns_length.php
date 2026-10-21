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
        Schema::table('assets', function (Blueprint $table) {
            $table->string('ticker', 100)->change();
        });

        Schema::table('analysis_allocations', function (Blueprint $table) {
            $table->string('ticker_suggested', 100)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('ticker', 30)->change();
        });

        Schema::table('analysis_allocations', function (Blueprint $table) {
            $table->string('ticker_suggested', 30)->nullable()->change();
        });
    }
};

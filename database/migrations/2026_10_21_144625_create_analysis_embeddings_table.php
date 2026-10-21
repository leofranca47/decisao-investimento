<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $hasVector = false;

        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('CREATE EXTENSION IF NOT EXISTS vector;');
                $hasVector = true;
            } catch (\Throwable $e) {
                Log::warning('pgvector extension could not be installed: ' . $e->getMessage());
            }
        }

        if ($hasVector) {
            Schema::create('analysis_embeddings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('investment_analysis_id')
                    ->constrained('investment_analyses')
                    ->cascadeOnDelete();
                $table->string('chunk_type', 50)->default('full');
                $table->text('chunk_text');
                $table->jsonb('metadata')->nullable();
                $table->timestamps();
            });

            DB::statement('ALTER TABLE analysis_embeddings ADD COLUMN embedding vector(768) NULL;');
            DB::statement('CREATE INDEX IF NOT EXISTS analysis_embeddings_vector_idx ON analysis_embeddings USING hnsw (embedding vector_cosine_ops);');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_embeddings');
    }
};

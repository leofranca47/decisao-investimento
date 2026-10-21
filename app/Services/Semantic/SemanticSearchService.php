<?php

namespace App\Services\Semantic;

use App\Models\AiProviderConfiguration;
use App\Models\AnalysisEmbedding;
use App\Models\InvestmentAnalysis;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SemanticSearchService
{
    public const EMBEDDING_DIMENSION = 768;

    /**
     * Verifica se a extensão pgvector e a tabela de embeddings estão disponíveis.
     */
    public function isAvailable(): bool
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return false;
        }

        try {
            $hasExtension = DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'vector'");
            if (!$hasExtension) {
                return false;
            }

            return Schema::hasTable('analysis_embeddings');
        } catch (\Throwable $e) {
            Log::warning('Erro ao verificar disponibilidade do pgvector: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Gera embedding para um texto usando Gemini text-embedding-004 ou configuração ativa.
     */
    public function generateEmbedding(string $text, ?AiProviderConfiguration $config = null): array
    {
        $config = $config ?? AiProviderConfiguration::where('is_active', true)
            ->where('provider_slug', 'gemini')
            ->first();

        if (!$config || empty($config->api_key)) {
            throw new RuntimeException("Nenhuma configuração ativa do Google Gemini com chave de API foi encontrada para gerar embeddings.");
        }

        $baseUrl = !empty($config->endpoint) ? rtrim($config->endpoint, '/') : 'https://generativelanguage.googleapis.com/v1beta';
        $url = "{$baseUrl}/models/text-embedding-004:embedContent";

        $response = Http::withHeaders([
            'x-goog-api-key' => $config->api_key,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($url, [
            'model' => 'models/text-embedding-004',
            'content' => [
                'parts' => [
                    ['text' => $text],
                ],
            ],
        ]);

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->json();
            $msg = $body['error']['message'] ?? 'Erro desconhecido na API de embeddings.';
            throw new RuntimeException("Falha ao gerar embedding via Gemini (Status {$status}): {$msg}");
        }

        $values = $response->json('embedding.values');
        if (!is_array($values) || count($values) !== self::EMBEDDING_DIMENSION) {
            throw new RuntimeException("A API retornou vetor de dimensão inesperada ou inválido.");
        }

        return $values;
    }

    /**
     * Indexa uma análise de investimento criando chunks e embeddings.
     * Permite passar embeddings pré-computados (útil em testes ou pipelines assíncronos).
     */
    public function indexAnalysis(InvestmentAnalysis $analysis, ?array $precomputedEmbeddings = null): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        // Chunks semânticos da análise
        $chunks = [];

        // Chunk 1: Resumo Executivo e Prioridades
        $summaryText = "Resumo da recomendação de investimentos: " . ($analysis->executive_summary ?? '');
        if (!empty($analysis->priorities)) {
            $summaryText .= " | Prioridades: " . implode(', ', $analysis->priorities);
        }
        $chunks['summary'] = trim($summaryText);

        // Chunk 2: Racional das Alocações
        $analysis->loadMissing('allocations.asset');
        $allocationsText = "Alocações planejadas:";
        foreach ($analysis->allocations as $alloc) {
            $ticker = $alloc->asset ? $alloc->asset->ticker : 'Ativo';
            $allocationsText .= " [{$ticker} - R$ {$alloc->suggested_amount} ({$alloc->suggested_percentage}%): {$alloc->rational}]";
        }
        if ($analysis->allocations->isNotEmpty()) {
            $chunks['allocations'] = trim($allocationsText);
        }

        // Remove embeddings anteriores desta análise
        AnalysisEmbedding::where('investment_analysis_id', $analysis->id)->delete();

        $indexedCount = 0;

        foreach ($chunks as $type => $text) {
            if (empty($text)) {
                continue;
            }

            $vector = $precomputedEmbeddings[$type] ?? null;

            if ($vector === null) {
                try {
                    $vector = $this->generateEmbedding($text);
                } catch (\Throwable $e) {
                    Log::warning("Não foi possível gerar embedding para análise {$analysis->id} chunk {$type}: " . $e->getMessage());
                    continue;
                }
            }

            $vectorString = '[' . implode(',', array_map(fn($v) => (float) $v, $vector)) . ']';

            DB::statement("
                INSERT INTO analysis_embeddings (
                    investment_analysis_id,
                    chunk_type,
                    chunk_text,
                    metadata,
                    embedding,
                    created_at,
                    updated_at
                ) VALUES (
                    ?, ?, ?, ?::jsonb, ?::vector, NOW(), NOW()
                )
            ", [
                $analysis->id,
                $type,
                $text,
                json_encode(['chunk_length' => mb_strlen($text)]),
                $vectorString,
            ]);

            $indexedCount++;
        }

        return $indexedCount;
    }

    /**
     * Busca análises similares por distância de cosseno no pgvector.
     *
     * @param User $user
     * @param string|array $queryOrVector Texto de consulta ou vetor de 768 floats
     * @param int $limit
     * @param float $maxDistance Distância máxima de corte (1.0 = corte normal, 0 = idêntico)
     * @return Collection
     */
    public function searchSimilar(
        User $user,
        string|array $queryOrVector,
        int $limit = 5,
        float $maxDistance = 0.8
    ): Collection {
        if (!$this->isAvailable()) {
            return collect();
        }

        if (is_string($queryOrVector)) {
            $vector = $this->generateEmbedding($queryOrVector);
        } else {
            $vector = $queryOrVector;
        }

        if (!is_array($vector) || count($vector) !== self::EMBEDDING_DIMENSION) {
            throw new RuntimeException("O vetor de consulta deve conter exatamente " . self::EMBEDDING_DIMENSION . " dimensões.");
        }

        $vectorString = '[' . implode(',', array_map(fn($v) => (float) $v, $vector)) . ']';

        $results = DB::select("
            SELECT
                ae.id AS embedding_id,
                ae.investment_analysis_id,
                ae.chunk_type,
                ae.chunk_text,
                (ae.embedding <=> ?::vector) AS distance,
                (1 - (ae.embedding <=> ?::vector)) AS similarity_score,
                ia.available_amount,
                ia.executive_summary,
                ia.created_at AS analysis_date
            FROM analysis_embeddings ae
            JOIN investment_analyses ia ON ia.id = ae.investment_analysis_id
            WHERE ia.user_id = ?
              AND (ae.embedding <=> ?::vector) <= ?
            ORDER BY distance ASC
            LIMIT ?
        ", [
            $vectorString,
            $vectorString,
            $user->id,
            $vectorString,
            $maxDistance,
            $limit,
        ]);

        return collect($results)->map(function ($row) {
            return (object) [
                'embedding_id' => $row->embedding_id,
                'investment_analysis_id' => $row->investment_analysis_id,
                'chunk_type' => $row->chunk_type,
                'chunk_text' => $row->chunk_text,
                'distance' => (float) $row->distance,
                'similarity_score' => round((float) $row->similarity_score * 100, 2),
                'available_amount' => (float) $row->available_amount,
                'executive_summary' => $row->executive_summary,
                'analysis_date' => $row->analysis_date,
            ];
        });
    }
}

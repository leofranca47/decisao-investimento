<?php

namespace Tests\Feature;

use App\Filament\Pages\SemanticSearchPage;
use App\Models\AnalysisEmbedding;
use App\Models\InvestmentAnalysis;
use App\Models\PortfolioImport;
use App\Models\User;
use App\Services\Semantic\SemanticSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SemanticSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected PortfolioImport $import;
    protected InvestmentAnalysis $analysis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'semantic_user@example.com',
        ]);

        $this->import = PortfolioImport::create([
            'user_id' => $this->user->id,
            'reference_date' => '2026-03-31',
            'original_filename' => 'carteira.xlsx',
            'file_path' => 'imports/test.xlsx',
            'file_hash' => 'hash_semantic_test',
            'file_format' => 'xlsx',
            'status' => 'completed',
            'total_positions' => 1,
            'total_equity' => 10000.00,
        ]);

        $this->analysis = InvestmentAnalysis::create([
            'user_id' => $this->user->id,
            'portfolio_import_id' => $this->import->id,
            'provider_used' => 'gemini',
            'model_used' => 'gemini-1.5-pro',
            'available_amount' => 1500.00,
            'executive_summary' => 'Foco em equilibrar renda fixa pós-fixada e fundos imobiliários com foco em dividendos.',
            'priorities' => ['Aporte em Tesouro Selic', 'FIIs de Tijolo'],
            'status' => 'completed',
        ]);
    }

    public function test_semantic_search_service_reports_availability(): void
    {
        $service = app(SemanticSearchService::class);
        $this->assertTrue($service->isAvailable());
    }

    public function test_can_index_analysis_with_precomputed_vector(): void
    {
        $service = app(SemanticSearchService::class);

        // Vetor 768 dimensões com valores normalizados
        $vector = array_fill(0, 768, 0.0);
        $vector[0] = 1.0; // unitário na dimensão 0

        $indexedCount = $service->indexAnalysis($this->analysis, [
            'summary' => $vector,
        ]);

        $this->assertGreaterThanOrEqual(1, $indexedCount);
        $this->assertDatabaseHas('analysis_embeddings', [
            'investment_analysis_id' => $this->analysis->id,
            'chunk_type' => 'summary',
        ]);
    }

    public function test_can_perform_cosine_similarity_search(): void
    {
        $service = app(SemanticSearchService::class);

        // Vetor A: unitário na dimensão 0
        $vectorA = array_fill(0, 768, 0.0);
        $vectorA[0] = 1.0;

        $service->indexAnalysis($this->analysis, [
            'summary' => $vectorA,
        ]);

        // Consulta com o mesmo vetor (distância de cosseno deve ser 0, similaridade 100%)
        $results = $service->searchSimilar($this->user, $vectorA, limit: 5);

        $this->assertNotEmpty($results);
        $first = $results->first();
        $this->assertEquals($this->analysis->id, $first->investment_analysis_id);
        $this->assertEqualsWithDelta(0.0, $first->distance, 0.001);
        $this->assertEqualsWithDelta(100.0, $first->similarity_score, 0.1);
    }

    public function test_search_respects_user_isolation(): void
    {
        $service = app(SemanticSearchService::class);

        $otherUser = User::factory()->create([
            'email' => 'other_user@example.com',
        ]);

        $vector = array_fill(0, 768, 0.0);
        $vector[0] = 1.0;

        $service->indexAnalysis($this->analysis, [
            'summary' => $vector,
        ]);

        // Outro usuário busca com o mesmo vetor exato
        $results = $service->searchSimilar($otherUser, $vector, limit: 5);

        // Deve retornar vazio pois a análise pertence a $this->user
        $this->assertEmpty($results);
    }

    public function test_semantic_search_page_renders_successfully(): void
    {
        $this->actingAs($this->user);

        $response = $this->get('/admin/semantic-search-page');
        $response->assertSuccessful();
        $response->assertSee('pgvector Habilitado');
    }

    public function test_semantic_search_page_livewire_interaction(): void
    {
        $this->actingAs($this->user);

        // Preenche embedding
        $service = app(SemanticSearchService::class);
        $vector = array_fill(0, 768, 0.0);
        $vector[0] = 1.0;
        $service->indexAnalysis($this->analysis, ['summary' => $vector]);

        Livewire::test(SemanticSearchPage::class)
            ->assertSet('isAvailable', true)
            ->assertSet('totalEmbeddings', 1)
            ->set('searchQuery', 'ab')
            ->call('search')
            ->assertHasErrors(['searchQuery' => 'min']);
    }
}

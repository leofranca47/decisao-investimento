<?php

namespace Tests\Feature;

use App\Models\AiProviderConfiguration;
use App\Models\AllocationTarget;
use App\Models\Asset;
use App\Models\InvestmentAnalysis;
use App\Models\InvestorProfile;
use App\Models\PortfolioImport;
use App\Models\PortfolioPosition;
use App\Models\User;
use App\Services\Analysis\InvestmentAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class InvestmentAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private function createSetup(): array
    {
        $user = User::factory()->create();

        InvestorProfile::create([
            'user_id' => $user->id,
            'risk_profile' => 'moderate',
            'horizon_years' => 10,
            'liquidity_reserve_target' => 30000.00,
            'max_asset_concentration_percentage' => 20.00,
        ]);

        AllocationTarget::create([
            'user_id' => $user->id,
            'asset_class' => 'acoes_br',
            'target_percentage' => 50.00,
        ]);
        AllocationTarget::create([
            'user_id' => $user->id,
            'asset_class' => 'fiis',
            'target_percentage' => 50.00,
        ]);

        $import = PortfolioImport::create([
            'user_id' => $user->id,
            'reference_date' => '2026-10-01',
            'file_path' => 'imports/test.csv',
            'file_hash' => hash('sha256', 'test_fase5'),
            'original_filename' => 'test.csv',
            'status' => 'completed',
        ]);

        $itub = Asset::create(['ticker' => 'ITUB4', 'name' => 'Itaú Unibanco', 'asset_class' => 'acoes_br']);
        $hglg = Asset::create(['ticker' => 'HGLG11', 'name' => 'CSHG Logística', 'asset_class' => 'fiis']);

        PortfolioPosition::create([
            'portfolio_import_id' => $import->id,
            'asset_id' => $itub->id,
            'quantity' => 100,
            'total_amount' => 3500.00,
        ]);
        PortfolioPosition::create([
            'portfolio_import_id' => $import->id,
            'asset_id' => $hglg->id,
            'quantity' => 20,
            'total_amount' => 3200.00,
        ]);

        $aiConfig = AiProviderConfiguration::create([
            'name' => 'Gemini Test',
            'provider_slug' => 'gemini',
            'model_id' => 'gemini-1.5-flash',
            'api_key' => 'AIzaSySecretTestKey123',
            'is_active' => true,
            'is_default' => true,
        ]);

        return [$user, $import, $aiConfig];
    }

    public function test_can_run_investment_analysis_with_ai_and_persist_structured_allocations(): void
    {
        [$user, $import, $aiConfig] = $this->createSetup();

        $aiProposalJson = json_encode([
            'executive_summary' => 'Aporte focado em rebalancear a classe de Ações BR mantendo exposição equilibrada a proventos.',
            'priorities' => ['Ações Brasileiras', 'Equilíbrio da carteira'],
            'allocations' => [
                [
                    'asset_class' => 'acoes_br',
                    'ticker' => 'ITUB4',
                    'suggested_amount' => 3000.00,
                    'share_percentage' => 60.00,
                    'reasoning' => 'Ativo sólido, excelente histórico de geração de caixa e dividendos consistentes.',
                    'risks' => 'Risco de crédito macroeconômico e oscilação de taxa Selic.',
                    'discarded_alternatives' => 'PETR4 foi descartada devido a maior volatilidade estatal.',
                ],
                [
                    'asset_class' => 'fiis',
                    'ticker' => 'HGLG11',
                    'suggested_amount' => 2000.00,
                    'share_percentage' => 40.00,
                    'reasoning' => 'Fundo de galpões de alta qualidade com vacância controlada.',
                    'risks' => 'Inadimplência de locatários e risco de renegociação contratual.',
                    'discarded_alternatives' => 'XPLG11 descartado por maior alavancagem.',
                ],
            ],
            'sources' => ['Relatórios Trimestrais 2026', 'Investidor10', 'B3'],
            'limitations' => ['Sem acesso a fatos relevantes das últimas 24 horas.'],
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $aiProposalJson],
                            ],
                            'role' => 'model',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(InvestmentAnalysisService::class);

        $analysis = $service->runAnalysis(
            user: $user,
            import: $import,
            aiConfig: $aiConfig,
            availableAmount: 5000.00,
            financialData: [
                'emergency_reserve_amount' => 35000.00,
                'has_debts' => false,
                'upcoming_expenses' => 1000.00,
                'monthly_notes' => 'Aporte regular do mês.',
            ]
        );

        $this->assertInstanceOf(InvestmentAnalysis::class, $analysis);
        $this->assertEquals('completed', $analysis->status);
        $this->assertEquals('5000.00', (string) $analysis->available_amount);
        $this->assertEquals('gemini-1.5-flash', $analysis->model_used);
        $this->assertNotEmpty($analysis->executive_summary);

        // Valida as alocações gravadas
        $this->assertEquals(2, $analysis->allocations()->count());

        $itubAllocation = $analysis->allocations()->where('ticker_suggested', 'ITUB4')->first();
        $this->assertNotNull($itubAllocation);
        $this->assertEquals('3000.00', (string) $itubAllocation->suggested_amount);
        $this->assertEquals('60.00', (string) $itubAllocation->share_percentage);
        $this->assertEquals('pending', $itubAllocation->status);

        // Valida que o snapshot determinístico foi gravado
        $this->assertIsArray($analysis->system_diagnosis_snapshot);
        $this->assertEquals(6700.00, $analysis->system_diagnosis_snapshot['totalEquity']);
    }

    public function test_fails_when_ai_returns_invalid_json(): void
    {
        [$user, $import, $aiConfig] = $this->createSetup();

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Não foi possível gerar um JSON válido.'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(InvestmentAnalysisService::class);

        $this->expectException(InvalidArgumentException::class);

        $service->runAnalysis(
            user: $user,
            import: $import,
            aiConfig: $aiConfig,
            availableAmount: 5000.00,
            financialData: []
        );
    }

    public function test_can_run_analysis_with_minimax_provider(): void
    {
        [$user, $import] = $this->createSetup();

        $minimaxConfig = AiProviderConfiguration::create([
            'name' => 'MiniMax Text 01 Production',
            'provider_slug' => 'minimax',
            'model_id' => 'MiniMax-Text-01',
            'api_key' => 'minimax_key_test_123',
            'is_active' => true,
        ]);

        $aiProposalJson = json_encode([
            'executive_summary' => 'Análise realizada com sucesso via MiniMax Text 01.',
            'priorities' => ['Rebalanceamento com FIIs'],
            'allocations' => [
                [
                    'asset_class' => 'fiis',
                    'ticker' => 'HGLG11',
                    'suggested_amount' => 2000.00,
                    'share_percentage' => 100.00,
                    'reasoning' => 'Aumento de posição em galpões logísticos de primeira linha.',
                ],
            ],
            'sources' => ['Relatório de Fundos Imobiliários'],
            'limitations' => [],
        ]);

        Http::fake([
            'https://api.minimax.chat/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => $aiProposalJson,
                        ],
                    ],
                ],
                'usage' => [
                    'total_tokens' => 120,
                ],
            ], 200),
        ]);

        $service = app(InvestmentAnalysisService::class);

        $analysis = $service->runAnalysis(
            user: $user,
            import: $import,
            aiConfig: $minimaxConfig,
            availableAmount: 2000.00
        );

        $this->assertInstanceOf(InvestmentAnalysis::class, $analysis);
        $this->assertEquals('minimax', $analysis->provider_used);
        $this->assertEquals('MiniMax-Text-01', $analysis->model_used);
        $this->assertEquals(1, $analysis->allocations()->count());
        $this->assertEquals('HGLG11', $analysis->allocations()->first()->ticker_suggested);
    }
}

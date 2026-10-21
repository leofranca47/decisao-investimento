<?php

namespace Tests\Feature;

use App\Filament\Resources\InvestmentAnalysisResource\Pages\ViewInvestmentAnalysis;
use App\Filament\Resources\InvestmentAnalysisResource\RelationManagers\AllocationsRelationManager;
use App\Models\AnalysisAllocation;
use App\Models\Asset;
use App\Models\ExecutedContribution;
use App\Models\InvestmentAnalysis;
use App\Models\PortfolioImport;
use App\Models\PortfolioPosition;
use App\Models\User;
use App\Services\Evolution\MonthlyEvolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExecutedContributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_record_executed_contribution_linked_to_analysis(): void
    {
        $user = User::factory()->create();
        $asset = Asset::create(['ticker' => 'ITUB4', 'name' => 'Itaú Unibanco', 'asset_class' => 'acoes_br']);

        $import = PortfolioImport::create([
            'user_id' => $user->id,
            'reference_date' => '2026-10-01',
            'file_path' => 'imports/test.csv',
            'file_hash' => hash('sha256', 'test_exec'),
            'original_filename' => 'test.csv',
            'status' => 'completed',
        ]);

        $analysis = InvestmentAnalysis::create([
            'user_id' => $user->id,
            'portfolio_import_id' => $import->id,
            'provider_used' => 'gemini',
            'model_used' => 'gemini-1.5-flash',
            'available_amount' => 5000.00,
            'status' => 'completed',
        ]);

        $allocation = AnalysisAllocation::create([
            'investment_analysis_id' => $analysis->id,
            'asset_id' => $asset->id,
            'asset_class' => 'acoes_br',
            'ticker_suggested' => 'ITUB4',
            'suggested_amount' => 3000.00,
            'share_percentage' => 60.00,
            'reasoning' => 'Ativo de qualidade',
            'status' => 'accepted',
        ]);

        $contribution = ExecutedContribution::create([
            'user_id' => $user->id,
            'investment_analysis_id' => $analysis->id,
            'analysis_allocation_id' => $allocation->id,
            'asset_id' => $asset->id,
            'amount' => 3000.00,
            'quantity' => 100.0000,
            'unit_price' => 30.0000,
            'execution_date' => '2026-10-05',
            'notes' => 'Compra executada a mercado conforme planejado.',
        ]);

        $this->assertDatabaseHas('executed_contributions', [
            'id' => $contribution->id,
            'asset_id' => $asset->id,
            'investment_analysis_id' => $analysis->id,
        ]);

        $this->assertEquals('3000.00', (string) $contribution->fresh()->amount);
        $this->assertEquals('100.0000', (string) $contribution->fresh()->quantity);
        $this->assertTrue($contribution->asset->is($asset));
        $this->assertTrue($contribution->investmentAnalysis->is($analysis));
    }

    public function test_calculates_analysis_compliance_and_variance(): void
    {
        $user = User::factory()->create();
        $asset1 = Asset::create(['ticker' => 'ITUB4', 'name' => 'Itaú', 'asset_class' => 'acoes_br']);
        $asset2 = Asset::create(['ticker' => 'HGLG11', 'name' => 'CSHG', 'asset_class' => 'fiis']);

        $import = PortfolioImport::create([
            'user_id' => $user->id,
            'reference_date' => '2026-10-01',
            'file_path' => 'imports/test.csv',
            'file_hash' => hash('sha256', 'test_compliance'),
            'original_filename' => 'test.csv',
            'status' => 'completed',
        ]);

        $analysis = InvestmentAnalysis::create([
            'user_id' => $user->id,
            'portfolio_import_id' => $import->id,
            'provider_used' => 'gemini',
            'model_used' => 'gemini-1.5-flash',
            'available_amount' => 5000.00,
            'status' => 'completed',
        ]);

        // Usuário executa 3.000 em ITUB4 e 1.500 em HGLG11 (total 4.500 de 5.000 sugeridos)
        ExecutedContribution::create([
            'user_id' => $user->id,
            'investment_analysis_id' => $analysis->id,
            'asset_id' => $asset1->id,
            'amount' => 3000.00,
            'execution_date' => '2026-10-05',
        ]);

        ExecutedContribution::create([
            'user_id' => $user->id,
            'investment_analysis_id' => $analysis->id,
            'asset_id' => $asset2->id,
            'amount' => 1500.00,
            'execution_date' => '2026-10-05',
        ]);

        $service = app(MonthlyEvolutionService::class);
        $compliance = $service->getAnalysisCompliance($analysis);

        $this->assertEquals(5000.00, $compliance->totalSuggested);
        $this->assertEquals(4500.00, $compliance->totalExecuted);
        $this->assertEquals(-500.00, $compliance->varianceAmount);
        $this->assertEquals(90.00, $compliance->compliancePercentage);
    }

    public function test_calculates_monthly_equity_evolution_series(): void
    {
        $user = User::factory()->create();
        $asset = Asset::create(['ticker' => 'ITUB4', 'name' => 'Itaú', 'asset_class' => 'acoes_br']);

        // Mês 1: R$ 50.000
        $import1 = PortfolioImport::create([
            'user_id' => $user->id,
            'reference_date' => '2026-09-01',
            'file_path' => 'imports/m1.csv',
            'file_hash' => hash('sha256', 'm1'),
            'original_filename' => 'm1.csv',
            'status' => 'completed',
        ]);
        PortfolioPosition::create([
            'portfolio_import_id' => $import1->id,
            'asset_id' => $asset->id,
            'quantity' => 1000,
            'total_amount' => 50000.00,
        ]);

        // Aporte no período: R$ 5.000
        ExecutedContribution::create([
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'amount' => 5000.00,
            'execution_date' => '2026-09-15',
        ]);

        // Mês 2: R$ 57.000 (R$ 5.000 aporte + R$ 2.000 valorização)
        $import2 = PortfolioImport::create([
            'user_id' => $user->id,
            'reference_date' => '2026-10-01',
            'file_path' => 'imports/m2.csv',
            'file_hash' => hash('sha256', 'm2'),
            'original_filename' => 'm2.csv',
            'status' => 'completed',
        ]);
        PortfolioPosition::create([
            'portfolio_import_id' => $import2->id,
            'asset_id' => $asset->id,
            'quantity' => 1100,
            'total_amount' => 57000.00,
        ]);

        $service = app(MonthlyEvolutionService::class);
        $timeline = $service->getEvolutionTimeline($user);

        $this->assertCount(2, $timeline);
        $firstMonth = $timeline[0];
        $secondMonth = $timeline[1];

        $this->assertEquals(50000.00, $firstMonth['total_equity']);
        $this->assertEquals(57000.00, $secondMonth['total_equity']);
        $this->assertEquals(7000.00, $secondMonth['equity_change']); // Variação total
        $this->assertEquals(5000.00, $secondMonth['contributions_amount']); // Aportes
        $this->assertEquals(2000.00, $secondMonth['market_gain_amount']); // Ganho de mercado
    }

    public function test_can_execute_contribution_action_from_relation_manager(): void
    {
        $user = User::factory()->create();
        $asset = Asset::create(['ticker' => 'ITSA3', 'name' => 'Itaúsa', 'asset_class' => 'acoes_br']);

        $import = PortfolioImport::create([
            'user_id' => $user->id,
            'reference_date' => '2026-10-01',
            'file_path' => 'imports/test.csv',
            'file_hash' => hash('sha256', 'test_rm_action'),
            'original_filename' => 'test.csv',
            'status' => 'completed',
        ]);

        $analysis = InvestmentAnalysis::create([
            'user_id' => $user->id,
            'portfolio_import_id' => $import->id,
            'provider_used' => 'gemini',
            'model_used' => 'gemini-3.5-flash',
            'available_amount' => 700.00,
            'status' => 'completed',
        ]);

        $allocation = AnalysisAllocation::create([
            'investment_analysis_id' => $analysis->id,
            'asset_id' => $asset->id,
            'asset_class' => 'acoes_br',
            'ticker_suggested' => 'ITSA3',
            'suggested_amount' => 250.00,
            'share_percentage' => 35.71,
            'reasoning' => 'Gap negativo na carteira',
            'status' => 'pending',
        ]);

        $this->actingAs($user);

        Livewire::test(AllocationsRelationManager::class, [
            'ownerRecord' => $analysis,
            'pageClass' => ViewInvestmentAnalysis::class,
        ])
        ->mountTableAction('executeContribution', $allocation)
        ->setTableActionData([
            'amount' => 260.50,
            'execution_date' => '2026-10-21',
            'quantity' => 25,
            'unit_price' => 10.42,
            'notes' => 'Comprado pelo home broker.',
        ])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('executed_contributions', [
            'user_id' => $user->id,
            'analysis_allocation_id' => $allocation->id,
            'amount' => 260.50,
            'quantity' => 25.0000,
        ]);

        $this->assertEquals('accepted', $allocation->fresh()->status);
    }
}

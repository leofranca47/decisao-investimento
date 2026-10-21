<?php

namespace Tests\Feature;

use App\Models\AllocationTarget;
use App\Models\Asset;
use App\Models\InvestorProfile;
use App\Models\PortfolioImport;
use App\Models\PortfolioPosition;
use App\Models\User;
use App\Services\Diagnosis\PortfolioDiagnosticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_comprehensive_portfolio_diagnostics(): void
    {
        $user = User::factory()->create();

        // Perfil: limite máximo de 20% em um único ativo
        InvestorProfile::create([
            'user_id' => $user->id,
            'risk_profile' => 'moderate',
            'max_asset_concentration_percentage' => 20.00,
        ]);

        // Metas: Ações 50%, FIIs 30%, Tesouro Direto 20%
        AllocationTarget::create([
            'user_id' => $user->id,
            'asset_class' => 'acoes_br',
            'target_percentage' => 50.00,
        ]);
        AllocationTarget::create([
            'user_id' => $user->id,
            'asset_class' => 'fiis',
            'target_percentage' => 30.00,
        ]);
        AllocationTarget::create([
            'user_id' => $user->id,
            'asset_class' => 'tesouro_direto',
            'target_percentage' => 20.00,
        ]);

        // Importação
        $import = PortfolioImport::create([
            'user_id' => $user->id,
            'reference_date' => '2026-10-01',
            'file_path' => 'imports/test.csv',
            'file_hash' => hash('sha256', 'test'),
            'original_filename' => 'test.csv',
            'status' => 'completed',
        ]);

        $itub = Asset::create(['ticker' => 'ITUB4', 'name' => 'Itaú Unibanco', 'asset_class' => 'acoes_br']);
        $hglg = Asset::create(['ticker' => 'HGLG11', 'name' => 'CSHG Logística', 'asset_class' => 'fiis']);
        $knri = Asset::create(['ticker' => 'KNRI11', 'name' => 'Kinea Renda', 'asset_class' => 'fiis']);
        $tesouro = Asset::create(['ticker' => 'TDIPCA2035', 'name' => 'Tesouro IPCA+ 2035', 'asset_class' => 'tesouro_direto']);

        // Total: 30k + 25k + 15k + 30k = 100k
        PortfolioPosition::create([
            'portfolio_import_id' => $import->id,
            'asset_id' => $itub->id,
            'quantity' => 1000,
            'total_amount' => 30000.00,
        ]);
        PortfolioPosition::create([
            'portfolio_import_id' => $import->id,
            'asset_id' => $hglg->id,
            'quantity' => 150,
            'total_amount' => 25000.00, // 25% -> excede o limite de 20%
        ]);
        PortfolioPosition::create([
            'portfolio_import_id' => $import->id,
            'asset_id' => $knri->id,
            'quantity' => 100,
            'total_amount' => 15000.00,
        ]);
        PortfolioPosition::create([
            'portfolio_import_id' => $import->id,
            'asset_id' => $tesouro->id,
            'quantity' => 10,
            'total_amount' => 30000.00,
        ]);

        $service = app(PortfolioDiagnosticsService::class);
        $diagnosis = $service->diagnose($import);

        // 1. Patrimônio total
        $this->assertEquals(100000.00, $diagnosis->totalEquity);
        $this->assertEquals(4, $diagnosis->positionsCount);

        // 2. Diagnóstico por classe
        $classes = $diagnosis->classAllocations;
        $this->assertArrayHasKey('acoes_br', $classes);
        $this->assertArrayHasKey('fiis', $classes);
        $this->assertArrayHasKey('tesouro_direto', $classes);

        // Ações BR: 30k (30%), Meta: 50%, Gap: -20% (-R$ 20.000)
        $acoes = $classes['acoes_br'];
        $this->assertEquals(30000.00, $acoes->currentAmount);
        $this->assertEquals(30.00, $acoes->currentPercentage);
        $this->assertEquals(50.00, $acoes->targetPercentage);
        $this->assertEquals(-20.00, $acoes->gapPercentage);
        $this->assertEquals(-20000.00, $acoes->gapAmount);
        $this->assertEquals('under', $acoes->status);

        // FIIs: 40k (40%), Meta: 30%, Gap: +10% (+R$ 10.000)
        $fiis = $classes['fiis'];
        $this->assertEquals(40000.00, $fiis->currentAmount);
        $this->assertEquals(40.00, $fiis->currentPercentage);
        $this->assertEquals(30.00, $fiis->targetPercentage);
        $this->assertEquals(10.00, $fiis->gapPercentage);
        $this->assertEquals(10000.00, $fiis->gapAmount);
        $this->assertEquals('over', $fiis->status);

        // 3. Concentração de Ativos
        $this->assertCount(4, $diagnosis->topAssets);
        $topAsset = $diagnosis->topAssets[0]; // ITUB4 e TDIPCA empatados em 30k
        $this->assertTrue($topAsset->totalAmount >= 30000.00);

        // HGLG11 está com 25% e deve ter alerta de concentração (limite 20%)
        $hglgConcentration = collect($diagnosis->topAssets)->firstWhere('ticker', 'HGLG11');
        $this->assertNotNull($hglgConcentration);
        $this->assertEquals(25.00, $hglgConcentration->weightPercentage);
        $this->assertTrue($hglgConcentration->isExceedingLimit);

        // Alertas de risco gerados
        $this->assertNotEmpty($diagnosis->riskAlerts);
        $this->assertTrue(collect($diagnosis->riskAlerts)->contains(fn ($alert) => str_contains($alert, 'HGLG11')));
    }
}

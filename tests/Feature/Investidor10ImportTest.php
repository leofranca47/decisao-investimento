<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\PortfolioImport;
use App\Models\User;
use App\Services\Import\PortfolioImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Investidor10ImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function createSampleCsvContent(): string
    {
        return implode("\n", [
            'TICKER;NOME;TIPO;QUANTIDADE;PRECO_MEDIO;PRECO_ATUAL;TOTAL;PERCENTUAL',
            'ITUB4;ITAU UNIBANCO;Ações;100;30,50;35,00;3.500,00;35,00%',
            'HGLG11;CSHG LOGISTICA;FIIs;20;150,00;160,00;3.200,00;32,00%',
            'IVVB11;ISHARES S&P 500;ETFs Internacionais;10;280,00;330,00;3.300,00;33,00%',
        ]);
    }

    public function test_can_preview_and_import_valid_csv(): void
    {
        $user = User::factory()->create();
        $csvContent = $this->createSampleCsvContent();
        $file = UploadedFile::fake()->createWithContent('carteira_investidor10.csv', $csvContent);

        $service = app(PortfolioImportService::class);

        // 1. Pré-visualização
        $preview = $service->preview($file);

        $this->assertEquals(3, $preview->totalRows);
        $this->assertEquals(3, $preview->acceptedRowsCount);
        $this->assertEquals(0, $preview->rejectedRowsCount);
        $this->assertCount(3, $preview->positions);

        // 2. Confirmação e importação definitiva
        $import = $service->confirmAndStore($user, $file, '2026-10-01', $preview);

        $this->assertInstanceOf(PortfolioImport::class, $import);
        $this->assertEquals('completed', $import->status);
        $this->assertEquals(3, $import->accepted_rows);
        $this->assertEquals('2026-10-01', $import->reference_date->format('Y-m-d'));

        // Valida se os ativos foram cadastrados
        $this->assertDatabaseHas('assets', ['ticker' => 'ITUB4', 'asset_class' => 'acoes_br']);
        $this->assertDatabaseHas('assets', ['ticker' => 'HGLG11', 'asset_class' => 'fiis']);
        $this->assertDatabaseHas('assets', ['ticker' => 'IVVB11', 'asset_class' => 'etfs_internacionais']);

        // Valida se as posições foram gravadas
        $this->assertEquals(3, $import->positions()->count());

        $itub = $import->positions()->whereHas('asset', fn ($q) => $q->where('ticker', 'ITUB4'))->first();
        $this->assertEquals('100.0000', (string) $itub->quantity);
        $this->assertEquals('3500.00', (string) $itub->total_amount);
        $this->assertEquals('35.0000', (string) $itub->current_price);
    }

    public function test_detects_duplicate_import_by_file_hash(): void
    {
        $user = User::factory()->create();
        $csvContent = $this->createSampleCsvContent();
        $file1 = UploadedFile::fake()->createWithContent('carteira_outubro.csv', $csvContent);
        $file2 = UploadedFile::fake()->createWithContent('carteira_copia.csv', $csvContent);

        $service = app(PortfolioImportService::class);

        $preview = $service->preview($file1);
        $service->confirmAndStore($user, $file1, '2026-10-01', $preview);

        // Segunda tentativa com mesmo conteúdo
        $this->assertTrue($service->isDuplicateHash($file2));
    }
}

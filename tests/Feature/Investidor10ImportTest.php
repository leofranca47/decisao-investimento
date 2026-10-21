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

    public function test_can_import_real_investidor10_csv_format_with_tipo_de_ativo_and_multiple_cdbs(): void
    {
        $user = User::factory()->create();

        $realCsv = implode("\n", [
            'Tipo de ativo;Ativo;Quant.;Preço Médio;Preço Atual;Variação;Rentabilidade;Saldo;Nota;% Carteira;% Ideal;Comprar ?;Tipo',
            'Ações;BBAS3;83;R$ 20,51;R$ 24,01;17,09%;22,92%;R$ 1.992,83;10;7,50%;5,00%;Não;',
            'FIIs;HGLG11;12;R$ 156,58;R$ 156,15;-0,27%;10,84%;R$ 1.873,80;10;7,03%;4,17%;Não;',
            'Renda Fixa;CDB - C6 - Pós-Fixado - 118.5% CDI;;;;57,93%;58,11%;R$ 1.579,31;10;5,92%;0,00%;Não;',
            'Renda Fixa;CDB - C6 - Pós-Fixado - 118.5% CDI;;;;55,84%;56,02%;R$ 1.558,38;10;5,84%;0,00%;Não;',
            'Tesouro Direto;Tesouro IPCA+ 2032;1,61;;R$ 3.247,68;10,22%;10,23%;R$ 5.228,76;10;19,61%;25,00%;Sim;',
        ]);

        $file = UploadedFile::fake()->createWithContent('investidor10_real.csv', $realCsv);
        $service = app(PortfolioImportService::class);

        $preview = $service->preview($file);

        // Deve consolidar os 2 CDBs idênticos em 1 posição de CDB
        $this->assertEquals(5, $preview->totalRows);
        $this->assertEquals(4, $preview->acceptedRowsCount);
        $this->assertEquals(0, $preview->rejectedRowsCount);

        // Verifica se tickers foram extraídos corretamente e não como o tipo
        $tickers = collect($preview->positions)->pluck('ticker')->toArray();
        $this->assertContains('BBAS3', $tickers);
        $this->assertContains('HGLG11', $tickers);
        $this->assertNotContains('AÇÕES', $tickers);
        $this->assertNotContains('FIIS', $tickers);

        $import = $service->confirmAndStore($user, $file, '2026-10-21', $preview);

        $this->assertEquals(4, $import->positions()->count());

        $cdb = $import->positions()->whereHas('asset', fn ($q) => $q->where('ticker', 'like', '%CDB - C6%'))->first();
        $this->assertNotNull($cdb);
        $this->assertEquals(3137.69, (float) $cdb->total_amount); // 1579.31 + 1558.38
    }
}

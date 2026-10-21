<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Import\PortfolioImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class Investidor10ExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_preview_and_import_valid_xlsx(): void
    {
        $user = User::factory()->create();

        // Cria uma planilha temporária com OpenSpout
        $tempPath = tempnam(sys_get_temp_dir(), 'inv10_') . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($tempPath);

        $writer->addRow(Row::fromValues([
            'TICKER', 'NOME', 'TIPO', 'QUANTIDADE', 'PRECO_MEDIO', 'PRECO_ATUAL', 'TOTAL', 'PERCENTUAL',
        ]));
        $writer->addRow(Row::fromValues([
            'VALE3', 'VALE ON', 'Ações', '50', '65,00', '70,00', '3.500,00', '50,00%',
        ]));
        $writer->addRow(Row::fromValues([
            'KNRI11', 'KINEA RENDA', 'FIIs', '25', '140,00', '140,00', '3.500,00', '50,00%',
        ]));
        $writer->close();

        $uploadedFile = new UploadedFile(
            path: $tempPath,
            originalName: 'carteira_investidor10.xlsx',
            mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            error: null,
            test: true
        );

        $service = app(PortfolioImportService::class);
        $preview = $service->preview($uploadedFile);

        $this->assertEquals(2, $preview->totalRows);
        $this->assertEquals(2, $preview->acceptedRowsCount);
        $this->assertEquals(7000.00, $preview->totalEquity);

        $import = $service->confirmAndStore($user, $uploadedFile, '2026-10-15', $preview);

        $this->assertEquals(2, $import->positions()->count());
        $this->assertDatabaseHas('assets', ['ticker' => 'VALE3', 'asset_class' => 'acoes_br']);
        $this->assertDatabaseHas('assets', ['ticker' => 'KNRI11', 'asset_class' => 'fiis']);

        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }
    }
}

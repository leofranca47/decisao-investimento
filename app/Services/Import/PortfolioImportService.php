<?php

namespace App\Services\Import;

use App\Models\Asset;
use App\Models\PortfolioImport;
use App\Models\PortfolioPosition;
use App\Models\User;
use App\Services\Import\Contracts\PortfolioImporterInterface;
use App\Services\Import\Dtos\ImportPreviewDto;
use App\Services\Import\Parsers\Investidor10CsvImporter;
use App\Services\Import\Parsers\Investidor10ExcelImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class PortfolioImportService
{
    /**
     * @var array<PortfolioImporterInterface>
     */
    protected array $importers;

    public function __construct()
    {
        $this->importers = [
            new Investidor10CsvImporter(),
            new Investidor10ExcelImporter(),
        ];
    }

    public function registerImporter(PortfolioImporterInterface $importer): void
    {
        $this->importers[] = $importer;
    }

    public function calculateHash(UploadedFile|string $file): string
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        return hash_file('sha256', $path);
    }

    public function isDuplicateHash(UploadedFile|string $file): bool
    {
        $hash = $this->calculateHash($file);
        return PortfolioImport::where('file_hash', $hash)->exists();
    }

    public function preview(UploadedFile $file): ImportPreviewDto
    {
        $importer = $this->resolveImporter($file);
        return $importer->parse($file->getRealPath());
    }

    public function confirmAndStore(
        User $user,
        UploadedFile $file,
        string $referenceDate,
        ImportPreviewDto $preview
    ): PortfolioImport {
        $hash = $this->calculateHash($file);

        // Armazenamento privado do arquivo original para auditoria e imutabilidade
        $storedPath = $file->store('imports', 'local');

        return DB::transaction(function () use ($user, $file, $referenceDate, $hash, $storedPath, $preview) {
            $import = PortfolioImport::create([
                'user_id' => $user->id,
                'reference_date' => $referenceDate,
                'file_path' => $storedPath,
                'file_hash' => $hash,
                'original_filename' => $file->getClientOriginalName(),
                'status' => 'completed',
                'total_rows' => $preview->totalRows,
                'accepted_rows' => $preview->acceptedRowsCount,
                'rejected_rows' => $preview->rejectedRowsCount,
                'validation_errors' => !empty($preview->errors) ? $preview->errors : null,
            ]);

            foreach ($preview->positions as $pos) {
                $asset = Asset::firstOrCreate(
                    ['ticker' => $pos->ticker],
                    [
                        'name' => $pos->name,
                        'asset_class' => $pos->assetClass,
                        'sector' => $pos->sector,
                    ]
                );

                // Atualiza a classe ou setor se antes eram desconhecidos
                if ($asset->asset_class === 'outros' && $pos->assetClass !== 'outros') {
                    $asset->update(['asset_class' => $pos->assetClass]);
                }

                PortfolioPosition::create([
                    'portfolio_import_id' => $import->id,
                    'asset_id' => $asset->id,
                    'quantity' => $pos->quantity,
                    'average_price' => $pos->averagePrice,
                    'current_price' => $pos->currentPrice,
                    'total_amount' => $pos->totalAmount,
                    'weight_percentage' => $pos->weightPercentage,
                    'target_percentage' => $pos->targetPercentage,
                    'raw_data' => $pos->rawData,
                ]);
            }

            return $import;
        });
    }

    protected function resolveImporter(UploadedFile $file): PortfolioImporterInterface
    {
        $ext = $file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
        $path = $file->getRealPath();

        foreach ($this->importers as $importer) {
            if ($importer->canHandle($path, $ext)) {
                return $importer;
            }
        }

        throw new InvalidArgumentException("Formato de arquivo não suportado (.{$ext}). Formatos suportados: CSV, XLSX.");
    }
}

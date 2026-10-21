<?php

namespace App\Services\Import\Parsers;

use App\Services\Import\Contracts\PortfolioImporterInterface;
use App\Services\Import\Dtos\ImportedPositionDto;
use App\Services\Import\Dtos\ImportPreviewDto;
use App\Services\Import\Helpers\NumberHelper;
use App\Services\Import\Normalizers\AssetClassNormalizer;
use OpenSpout\Reader\XLSX\Reader;

class Investidor10ExcelImporter implements PortfolioImporterInterface
{
    public function canHandle(string $filePath, string $originalExtension): bool
    {
        return in_array(strtolower($originalExtension), ['xlsx', 'xls']);
    }

    public function parse(string $filePath): ImportPreviewDto
    {
        $reader = new Reader();
        try {
            $reader->open($filePath);
        } catch (\Throwable $e) {
            return new ImportPreviewDto([], 0, 0, 0, 0.0, ["Falha ao abrir planilha Excel: {$e->getMessage()}"]);
        }

        $headers = [];
        $positions = [];
        $errors = [];
        $warnings = [];
        $totalRows = 0;
        $totalEquity = 0.0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                $cells = $row->toArray();
                if ($rowIndex === 1) {
                    $headers = array_map(fn ($h) => $this->normalizeHeaderKey((string) $h), $cells);
                    continue;
                }

                $totalRows++;
                if (empty(array_filter($cells))) {
                    continue; // Pula linhas vazias
                }

                $mappedRow = [];
                foreach ($headers as $colIdx => $colKey) {
                    $mappedRow[$colKey] = isset($cells[$colIdx]) ? (string) $cells[$colIdx] : '';
                }

                $ticker = $this->extractField($mappedRow, ['ticker', 'codigo', 'ativo', 'papel']);
                $name = $this->extractField($mappedRow, ['nome', 'descricao', 'empresa', 'fundo']) ?: $ticker;
                $rawClass = $this->extractField($mappedRow, ['tipo', 'classe', 'categoria', 'tipo_do_ativo']);
                $quantity = NumberHelper::parseDecimal($this->extractField($mappedRow, ['quantidade', 'qtd', 'cotas']));
                $avgPrice = NumberHelper::parseDecimal($this->extractField($mappedRow, ['preco_medio', 'custo_medio', 'pm']));
                $curPrice = NumberHelper::parseDecimal($this->extractField($mappedRow, ['preco_atual', 'cotacao', 'valor_atual']));
                $total = NumberHelper::parseDecimal($this->extractField($mappedRow, ['total', 'valor_total', 'saldo', 'saldo_bruto', 'posicao']));
                $percentage = NumberHelper::parseDecimal($this->extractField($mappedRow, ['percentual', 'carteira', 'participacao', 'peso']));
                $sector = $this->extractField($mappedRow, ['setor', 'segmento', 'instituicao']);

                if (empty($ticker)) {
                    $errors[] = "Linha {$rowIndex}: Código/Ticker do ativo ausente.";
                    continue;
                }

                if ($total <= 0 && $quantity > 0 && $curPrice > 0) {
                    $total = round($quantity * $curPrice, 2);
                }

                $normalizedClass = AssetClassNormalizer::normalize($rawClass);

                $positions[] = new ImportedPositionDto(
                    ticker: strtoupper($ticker),
                    name: $name,
                    assetClass: $normalizedClass,
                    quantity: $quantity,
                    averagePrice: $avgPrice > 0 ? $avgPrice : null,
                    currentPrice: $curPrice > 0 ? $curPrice : null,
                    totalAmount: $total,
                    weightPercentage: $percentage > 0 ? $percentage : null,
                    sector: !empty($sector) ? $sector : null,
                    rawData: $mappedRow
                );

                $totalEquity += $total;
            }
            break; // Processa apenas a primeira aba
        }

        $reader->close();

        return new ImportPreviewDto(
            positions: $positions,
            totalRows: $totalRows,
            acceptedRowsCount: count($positions),
            rejectedRowsCount: count($errors),
            totalEquity: round($totalEquity, 2),
            errors: $errors,
            warnings: $warnings
        );
    }

    private function normalizeHeaderKey(string $key): string
    {
        $key = mb_strtolower(trim($key));
        $key = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $key) ?: $key;
        $key = preg_replace('/[^a-z0-9_]/', '_', $key);
        return trim((string) preg_replace('/_+/', '_', (string) $key), '_');
    }

    private function extractField(array $row, array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            foreach ($row as $col => $val) {
                if (str_contains($col, $key) && filled($val)) {
                    return $val;
                }
            }
        }
        return null;
    }
}

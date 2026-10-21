<?php

namespace App\Services\Import\Parsers;

use App\Services\Import\Contracts\PortfolioImporterInterface;
use App\Services\Import\Dtos\ImportedPositionDto;
use App\Services\Import\Dtos\ImportPreviewDto;
use App\Services\Import\Helpers\NumberHelper;
use App\Services\Import\Normalizers\AssetClassNormalizer;
use League\Csv\Reader;

class Investidor10CsvImporter implements PortfolioImporterInterface
{
    public function canHandle(string $filePath, string $originalExtension): bool
    {
        return in_array(strtolower($originalExtension), ['csv', 'txt']);
    }

    public function parse(string $filePath): ImportPreviewDto
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return new ImportPreviewDto([], 0, 0, 0, 0.0, ['Não foi possível ler o arquivo.']);
        }

        // Converter para UTF-8 se estiver em ISO-8859-1 / Windows-1252
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }

        $reader = Reader::createFromString($content);

        // Detectar delimitador (; ou , ou \t)
        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = ';';
        if (substr_count($firstLine, ',') > substr_count($firstLine, ';')) {
            $delimiter = ',';
        } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ';')) {
            $delimiter = "\t";
        }
        $reader->setDelimiter($delimiter);
        $reader->setHeaderOffset(0);

        $headers = array_map(fn ($h) => $this->normalizeHeaderKey((string) $h), $reader->getHeader());

        $positions = [];
        $errors = [];
        $warnings = [];
        $totalRows = 0;
        $totalEquity = 0.0;

        foreach ($reader->getRecords() as $index => $row) {
            $totalRows++;
            $cleanRow = [];
            foreach ($row as $k => $v) {
                $cleanRow[$this->normalizeHeaderKey((string) $k)] = trim((string) $v);
            }

            $ticker = $this->extractField($cleanRow, ['ticker', 'codigo', 'ativo', 'papel']);
            $name = $this->extractField($cleanRow, ['nome', 'descricao', 'empresa', 'fundo']) ?: $ticker;
            $rawClass = $this->extractField($cleanRow, ['tipo', 'classe', 'categoria', 'tipo_do_ativo']);
            $quantity = NumberHelper::parseDecimal($this->extractField($cleanRow, ['quantidade', 'qtd', 'cotas']));
            $avgPrice = NumberHelper::parseDecimal($this->extractField($cleanRow, ['preco_medio', 'custo_medio', 'pm']));
            $curPrice = NumberHelper::parseDecimal($this->extractField($cleanRow, ['preco_atual', 'cotacao', 'valor_atual']));
            $total = NumberHelper::parseDecimal($this->extractField($cleanRow, ['total', 'valor_total', 'saldo', 'saldo_bruto', 'posicao']));
            $percentage = NumberHelper::parseDecimal($this->extractField($cleanRow, ['percentual', 'carteira', 'participacao', 'peso']));
            $sector = $this->extractField($cleanRow, ['setor', 'segmento', 'instituicao']);

            if (empty($ticker)) {
                $errors[] = "Linha " . ($index + 1) . ": Código/Ticker do ativo ausente.";
                continue;
            }

            // Se o total estiver zerado ou ausente mas tiver quantidade e preço atual, recalcula
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
                rawData: $row
            );

            $totalEquity += $total;
        }

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

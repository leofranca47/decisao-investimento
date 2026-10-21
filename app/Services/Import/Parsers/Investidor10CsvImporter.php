<?php

namespace App\Services\Import\Parsers;

use App\Services\Import\Contracts\PortfolioImporterInterface;
use App\Services\Import\Dtos\ImportedPositionDto;
use App\Services\Import\Dtos\ImportPreviewDto;
use App\Services\Import\Helpers\NumberHelper;
use App\Services\Import\Normalizers\AssetClassNormalizer;
use Illuminate\Support\Str;
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

        /** @var array<string, ImportedPositionDto> $consolidatedPositions */
        $consolidatedPositions = [];
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

            $ticker = $this->extractField($cleanRow, ['ativo', 'ticker', 'codigo', 'papel'], ['tipo', 'classe', 'categoria']);
            $name = $this->extractField($cleanRow, ['nome', 'descricao', 'empresa', 'fundo']) ?: $ticker;
            $rawClass = $this->extractField($cleanRow, ['tipo_de_ativo', 'tipo_do_ativo', 'tipo', 'classe', 'categoria']);
            $quantity = NumberHelper::parseDecimal($this->extractField($cleanRow, ['quant', 'quantidade', 'qtd', 'cotas']));
            $avgPrice = NumberHelper::parseDecimal($this->extractField($cleanRow, ['preco_medio', 'custo_medio', 'pm']));
            $curPrice = NumberHelper::parseDecimal($this->extractField($cleanRow, ['preco_atual', 'cotacao', 'valor_atual']));
            $total = NumberHelper::parseDecimal($this->extractField($cleanRow, ['saldo', 'total', 'valor_total', 'saldo_bruto', 'posicao']));
            $percentage = NumberHelper::parseDecimal($this->extractField($cleanRow, ['carteira', 'percentual', 'participacao', 'peso', 'peso_atual']));
            $targetPercentage = NumberHelper::parseDecimal($this->extractField($cleanRow, ['ideal', 'percentual_ideal', 'meta', 'peso_ideal', 'target']));
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
            $normalizedTicker = strtoupper(trim($ticker));

            // Consolidação por ativo caso o arquivo traga múltiplas linhas do mesmo título (ex: CDBs ou compras parciais)
            if (isset($consolidatedPositions[$normalizedTicker])) {
                $existing = $consolidatedPositions[$normalizedTicker];
                $newQuantity = $existing->quantity + $quantity;
                $newTotal = round($existing->totalAmount + $total, 2);
                $newWeight = ($existing->weightPercentage ?? 0.0) + ($percentage > 0 ? $percentage : 0.0);
                $newTarget = $targetPercentage > 0 ? $targetPercentage : $existing->targetPercentage;

                $consolidatedPositions[$normalizedTicker] = new ImportedPositionDto(
                    ticker: $normalizedTicker,
                    name: $existing->name ?: $name,
                    assetClass: $existing->assetClass !== 'outros' ? $existing->assetClass : $normalizedClass,
                    quantity: $newQuantity,
                    averagePrice: $existing->averagePrice ?: $avgPrice,
                    currentPrice: $curPrice > 0 ? $curPrice : $existing->currentPrice,
                    totalAmount: $newTotal,
                    weightPercentage: $newWeight > 0 ? round($newWeight, 2) : null,
                    targetPercentage: $newTarget > 0 ? round($newTarget, 2) : null,
                    sector: $existing->sector ?: (!empty($sector) ? $sector : null),
                    rawData: array_merge((array) $existing->rawData, ['additional_rows' => $row])
                );
            } else {
                $consolidatedPositions[$normalizedTicker] = new ImportedPositionDto(
                    ticker: $normalizedTicker,
                    name: $name,
                    assetClass: $normalizedClass,
                    quantity: $quantity,
                    averagePrice: $avgPrice > 0 ? $avgPrice : null,
                    currentPrice: $curPrice > 0 ? $curPrice : null,
                    totalAmount: $total,
                    weightPercentage: $percentage > 0 ? $percentage : null,
                    targetPercentage: $targetPercentage > 0 ? $targetPercentage : null,
                    sector: !empty($sector) ? $sector : null,
                    rawData: $row
                );
            }

            $totalEquity += $total;
        }

        // Garante que todo ativo tenha seu weightPercentage calculado com base no total equity se estiver ausente
        foreach ($consolidatedPositions as $ticker => $pos) {
            $weight = $pos->weightPercentage;
            if ($weight === null || $weight <= 0) {
                $weight = $totalEquity > 0 ? round(($pos->totalAmount / $totalEquity) * 100, 2) : 0.0;
            }

            $consolidatedPositions[$ticker] = new ImportedPositionDto(
                ticker: $pos->ticker,
                name: $pos->name,
                assetClass: $pos->assetClass,
                quantity: $pos->quantity,
                averagePrice: $pos->averagePrice,
                currentPrice: $pos->currentPrice,
                totalAmount: $pos->totalAmount,
                weightPercentage: $weight,
                targetPercentage: $pos->targetPercentage,
                sector: $pos->sector,
                rawData: $pos->rawData
            );
        }

        $finalPositions = array_values($consolidatedPositions);

        return new ImportPreviewDto(
            positions: $finalPositions,
            totalRows: $totalRows,
            acceptedRowsCount: count($finalPositions),
            rejectedRowsCount: count($errors),
            totalEquity: round($totalEquity, 2),
            errors: $errors,
            warnings: $warnings
        );
    }

    private function normalizeHeaderKey(string $key): string
    {
        $key = mb_strtolower(trim($key));
        $key = Str::ascii($key);
        $key = preg_replace('/[^a-z0-9_]/', '_', $key);
        return trim((string) preg_replace('/_+/', '_', (string) $key), '_');
    }

    private function extractField(array $row, array $possibleKeys, array $excludeIfContains = []): ?string
    {
        // 1. Tenta correspondência exata primeiro
        foreach ($possibleKeys as $key) {
            if (isset($row[$key]) && filled($row[$key])) {
                return trim((string) $row[$key]);
            }
        }

        // 2. Tenta correspondência parcial respeitando exclusões
        foreach ($possibleKeys as $key) {
            foreach ($row as $col => $val) {
                if (!filled($val)) {
                    continue;
                }

                $shouldExclude = false;
                foreach ($excludeIfContains as $exclude) {
                    if (str_contains($col, $exclude)) {
                        $shouldExclude = true;
                        break;
                    }
                }

                if ($shouldExclude) {
                    continue;
                }

                if (str_contains($col, $key)) {
                    return trim((string) $val);
                }
            }
        }

        return null;
    }
}

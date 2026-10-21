<?php

namespace App\Services\Import\Dtos;

class ImportPreviewDto
{
    /**
     * @param array<ImportedPositionDto> $positions
     * @param array<string> $errors
     * @param array<string> $warnings
     */
    public function __construct(
        public array $positions,
        public int $totalRows,
        public int $acceptedRowsCount,
        public int $rejectedRowsCount,
        public float $totalEquity,
        public array $errors = [],
        public array $warnings = []
    ) {}
}

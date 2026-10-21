<?php

namespace App\Services\Import\Dtos;

class ImportedPositionDto
{
    public function __construct(
        public string $ticker,
        public string $name,
        public string $assetClass,
        public float $quantity,
        public ?float $averagePrice,
        public ?float $currentPrice,
        public float $totalAmount,
        public ?float $weightPercentage = null,
        public ?float $targetPercentage = null,
        public ?string $sector = null,
        public array $rawData = []
    ) {}
}

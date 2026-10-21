<?php

namespace App\Services\Evolution\Dtos;

class AnalysisComplianceDto
{
    public function __construct(
        public float $totalSuggested,
        public float $totalExecuted,
        public float $varianceAmount,
        public float $compliancePercentage
    ) {}
}

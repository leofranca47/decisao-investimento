<?php

namespace App\Filament\Resources\InvestmentAnalysisResource\Pages;

use App\Filament\Resources\InvestmentAnalysisResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvestmentAnalyses extends ListRecords
{
    protected static string $resource = InvestmentAnalysisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

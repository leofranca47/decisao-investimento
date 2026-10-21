<?php

namespace App\Filament\Resources\InvestmentAnalysisResource\Pages;

use App\Filament\Resources\InvestmentAnalysisResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvestmentAnalysis extends EditRecord
{
    protected static string $resource = InvestmentAnalysisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

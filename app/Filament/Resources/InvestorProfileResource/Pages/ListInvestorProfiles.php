<?php

namespace App\Filament\Resources\InvestorProfileResource\Pages;

use App\Filament\Resources\InvestorProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvestorProfiles extends ListRecords
{
    protected static string $resource = InvestorProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

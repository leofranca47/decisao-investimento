<?php

namespace App\Filament\Resources\ExecutedContributionResource\Pages;

use App\Filament\Resources\ExecutedContributionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExecutedContributions extends ListRecords
{
    protected static string $resource = ExecutedContributionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

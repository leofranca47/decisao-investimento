<?php

namespace App\Filament\Resources\AllocationTargetResource\Pages;

use App\Filament\Resources\AllocationTargetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAllocationTargets extends ListRecords
{
    protected static string $resource = AllocationTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

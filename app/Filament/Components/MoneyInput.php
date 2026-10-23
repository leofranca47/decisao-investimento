<?php

namespace App\Filament\Components;

use App\Services\Import\Helpers\NumberHelper;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;

class MoneyInput extends TextInput
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->prefix('R$')
            ->mask(RawJs::make('$money($input, ".", ",", 2)'))
            ->formatStateUsing(fn ($state) => $this->formatValue($state))
            ->dehydrateStateUsing(fn ($state) => $this->parseValue($state));
    }

    public function formatValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? number_format((float) $value, 2, ',', '.') : (string) $value;
    }

    public function parseValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return NumberHelper::parseDecimal($value);
    }
}

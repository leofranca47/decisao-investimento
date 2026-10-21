<?php

namespace App\Filament\Resources\PortfolioImportResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PositionsRelationManager extends RelationManager
{
    protected static string $relationship = 'positions';

    protected static ?string $title = 'Posições Importadas';

    protected static ?string $modelLabel = 'Posição';

    protected static ?string $pluralModelLabel = 'Posições';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('asset.ticker')
                    ->label('Ticker')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('asset.ticker')
            ->columns([
                Tables\Columns\TextColumn::make('asset.ticker')
                    ->label('Ticker')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Ativo')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('asset.asset_class')
                    ->label('Classe')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'acoes_br' => 'Ações Brasileiras',
                        'fiis' => 'Fundos Imobiliários (FIIs)',
                        'tesouro_direto' => 'Tesouro Direto',
                        'etfs_internacionais' => 'ETFs Internacionais',
                        'renda_fixa_privada' => 'Renda Fixa Privada',
                        'cripto' => 'Criptoativos',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'acoes_br' => 'info',
                        'fiis' => 'success',
                        'tesouro_direto' => 'warning',
                        'etfs_internacionais' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantidade')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                Tables\Columns\TextColumn::make('average_price')
                    ->label('Preço Médio')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_price')
                    ->label('Cotação Atual')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Valor Total')
                    ->money('BRL')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('weight_percentage')
                    ->label('% Carteira')
                    ->suffix('%')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('asset.asset_class')
                    ->label('Classe de Ativo')
                    ->relationship('asset', 'asset_class')
                    ->options([
                        'acoes_br' => 'Ações Brasileiras',
                        'fiis' => 'Fundos Imobiliários',
                        'tesouro_direto' => 'Tesouro Direto',
                        'etfs_internacionais' => 'ETFs Internacionais',
                        'renda_fixa_privada' => 'Renda Fixa',
                        'cripto' => 'Cripto',
                        'outros' => 'Outros',
                    ]),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}

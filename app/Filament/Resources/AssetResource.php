<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetResource\Pages;
use App\Models\Asset;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Carteira';

    protected static ?string $modelLabel = 'Ativo';

    protected static ?string $pluralModelLabel = 'Catálogo de Ativos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('ticker')
                    ->label('Ticker / Código')
                    ->required()
                    ->maxLength(30),

                Forms\Components\TextInput::make('name')
                    ->label('Nome do Ativo')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('asset_class')
                    ->label('Classe de Ativo')
                    ->options([
                        'acoes_br' => 'Ações Brasileiras',
                        'fiis' => 'Fundos Imobiliários',
                        'tesouro_direto' => 'Tesouro Direto',
                        'etfs_internacionais' => 'ETFs Internacionais',
                        'renda_fixa_privada' => 'Renda Fixa Privada',
                        'cripto' => 'Criptoativos',
                        'outros' => 'Outros',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('currency')
                    ->label('Moeda')
                    ->default('BRL')
                    ->maxLength(3),

                Forms\Components\TextInput::make('sector')
                    ->label('Setor / Segmento')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ticker')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),

                Tables\Columns\TextColumn::make('asset_class')
                    ->label('Classe')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'acoes_br' => 'Ações Brasileiras',
                        'fiis' => 'Fundos Imobiliários',
                        'tesouro_direto' => 'Tesouro Direto',
                        'etfs_internacionais' => 'ETFs Internacionais',
                        'renda_fixa_privada' => 'Renda Fixa',
                        'cripto' => 'Cripto',
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

                Tables\Columns\TextColumn::make('sector')
                    ->label('Setor')
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('asset_class')
                    ->label('Classe')
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
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'edit' => Pages\EditAsset::route('/{record}/edit'),
        ];
    }
}

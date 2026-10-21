<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AllocationTargetResource\Pages;
use App\Models\AllocationTarget;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AllocationTargetResource extends Resource
{
    protected static ?string $model = AllocationTarget::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationGroup = 'Planejamento';

    protected static ?string $modelLabel = 'Meta de Alocação';

    protected static ?string $pluralModelLabel = 'Metas de Alocação';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('user_id')
                    ->default(fn () => Auth::id() ?? 1),

                Forms\Components\Section::make('Meta por Classe de Ativo')
                    ->schema([
                        Forms\Components\Select::make('asset_class')
                            ->label('Classe de Ativo')
                            ->options([
                                'acoes_br' => 'Ações Brasileiras',
                                'fiis' => 'Fundos Imobiliários (FIIs)',
                                'tesouro_direto' => 'Tesouro Direto',
                                'etfs_internacionais' => 'ETFs Internacionais',
                                'renda_fixa_privada' => 'Renda Fixa Privada (CDB, LCI/LCA, Debêntures)',
                                'cripto' => 'Criptoativos',
                                'outros' => 'Outras Classes',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('target_percentage')
                            ->label('Meta Percentual (%)')
                            ->numeric()
                            ->suffix('%')
                            ->required(),

                        Forms\Components\TextInput::make('min_percentage')
                            ->label('Limite Mínimo (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(0.00),

                        Forms\Components\TextInput::make('max_percentage')
                            ->label('Limite Máximo (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(100.00),

                        Forms\Components\TextInput::make('tolerance_percentage')
                            ->label('Tolerância de Desvio (+/- %)')
                            ->numeric()
                            ->suffix('%')
                            ->default(5.00),

                        Forms\Components\Textarea::make('notes')
                            ->label('Critérios e Observações')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('asset_class')
                    ->label('Classe de Ativo')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'acoes_br' => 'Ações Brasileiras',
                        'fiis' => 'Fundos Imobiliários (FIIs)',
                        'tesouro_direto' => 'Tesouro Direto',
                        'etfs_internacionais' => 'ETFs Internacionais',
                        'renda_fixa_privada' => 'Renda Fixa Privada',
                        'cripto' => 'Criptoativos',
                        default => $state,
                    })
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('target_percentage')
                    ->label('Meta (%)')
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('min_percentage')
                    ->label('Mínimo (%)')
                    ->suffix('%'),

                Tables\Columns\TextColumn::make('max_percentage')
                    ->label('Máximo (%)')
                    ->suffix('%'),

                Tables\Columns\TextColumn::make('tolerance_percentage')
                    ->label('Tolerância (+/-)')
                    ->suffix('%'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListAllocationTargets::route('/'),
            'create' => Pages\CreateAllocationTarget::route('/create'),
            'edit' => Pages\EditAllocationTarget::route('/{record}/edit'),
        ];
    }
}

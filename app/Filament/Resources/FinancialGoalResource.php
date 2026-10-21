<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinancialGoalResource\Pages;
use App\Models\FinancialGoal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class FinancialGoalResource extends Resource
{
    protected static ?string $model = FinancialGoal::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'Planejamento';

    protected static ?string $modelLabel = 'Objetivo Financeiro';

    protected static ?string $pluralModelLabel = 'Objetivos Financeiros';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('user_id')
                    ->default(fn () => Auth::id() ?? 1),

                Forms\Components\Section::make('Definição do Objetivo')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome do Objetivo')
                            ->placeholder('Ex: Aposentadoria, Compra de Imóvel, Renda Passiva')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\DatePicker::make('target_date')
                            ->label('Data / Horizonte Estimado')
                            ->displayFormat('d/m/Y'),

                        Forms\Components\TextInput::make('target_amount')
                            ->label('Valor Alvo (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->required(),

                        Forms\Components\TextInput::make('current_amount')
                            ->label('Valor Já Acumulado (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0.00),

                        Forms\Components\Select::make('priority')
                            ->label('Prioridade')
                            ->options([
                                'low' => 'Baixa',
                                'medium' => 'Média',
                                'high' => 'Alta / Essencial',
                            ])
                            ->default('medium')
                            ->required(),

                        Forms\Components\Select::make('liquidity_need')
                            ->label('Necessidade de Liquidez')
                            ->options([
                                'low' => 'Baixa (Longo Prazo)',
                                'medium' => 'Média',
                                'high' => 'Alta (Curto Prazo)',
                            ])
                            ->default('low')
                            ->required(),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrição e Finalidade')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações e Restrições')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Objetivo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('target_amount')
                    ->label('Valor Alvo')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_amount')
                    ->label('Acumulado')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('target_date')
                    ->label('Prazo')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioridade')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'high' => 'Alta',
                        'medium' => 'Média',
                        'low' => 'Baixa',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'high' => 'danger',
                        'medium' => 'warning',
                        'low' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('liquidity_need')
                    ->label('Liquidez')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'high' => 'Alta',
                        'medium' => 'Média',
                        'low' => 'Baixa',
                        default => $state,
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridade')
                    ->options([
                        'high' => 'Alta',
                        'medium' => 'Média',
                        'low' => 'Baixa',
                    ]),
            ])
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
            'index' => Pages\ListFinancialGoals::route('/'),
            'create' => Pages\CreateFinancialGoal::route('/create'),
            'edit' => Pages\EditFinancialGoal::route('/{record}/edit'),
        ];
    }
}

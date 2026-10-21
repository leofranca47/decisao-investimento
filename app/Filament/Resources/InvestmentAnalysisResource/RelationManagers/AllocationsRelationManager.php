<?php

namespace App\Filament\Resources\InvestmentAnalysisResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AllocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocations';

    protected static ?string $title = 'Proposta de Distribuição do Aporte';

    protected static ?string $modelLabel = 'Alocação Sugerida';

    protected static ?string $pluralModelLabel = 'Alocações Sugeridas';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('ticker_suggested')
                    ->label('Ticker')
                    ->disabled(),
                Forms\Components\TextInput::make('suggested_amount')
                    ->label('Valor Sugerido')
                    ->disabled(),
                Forms\Components\Select::make('status')
                    ->label('Decisão do Investidor')
                    ->options([
                        'pending' => 'Pendente de Decisão',
                        'accepted' => 'Aceito Integralmente',
                        'partial' => 'Aceito Parcialmente',
                        'rejected' => 'Rejeitado',
                        'deferred' => 'Adiado para o Próximo Mês',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('user_decision_notes')
                    ->label('Justificativa da Decisão')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ticker_suggested')
            ->columns([
                Tables\Columns\TextColumn::make('ticker_suggested')
                    ->label('Ativo')
                    ->placeholder('Diversificado')
                    ->weight('bold')
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
                    }),

                Tables\Columns\TextColumn::make('suggested_amount')
                    ->label('Valor Sugerido')
                    ->money('BRL')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('share_percentage')
                    ->label('% do Aporte')
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendente',
                        'accepted' => 'Aceito',
                        'partial' => 'Parcial',
                        'rejected' => 'Rejeitado',
                        'deferred' => 'Adiado',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'accepted' => 'success',
                        'partial' => 'warning',
                        'rejected' => 'danger',
                        'deferred' => 'gray',
                        default => 'info',
                    }),

                Tables\Columns\TextColumn::make('reasoning')
                    ->label('Justificativa da IA')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->reasoning),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\EditAction::make()->label('Decidir'),
            ])
            ->bulkActions([]);
    }
}

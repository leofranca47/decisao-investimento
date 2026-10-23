<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvestmentAnalysisResource\Pages;
use App\Filament\Resources\InvestmentAnalysisResource\RelationManagers\AllocationsRelationManager;
use App\Filament\Components\MoneyInput;
use App\Models\AiProviderConfiguration;
use App\Models\InvestmentAnalysis;
use App\Models\PortfolioImport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InvestmentAnalysisResource extends Resource
{
    protected static ?string $model = InvestmentAnalysis::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Análise Mensal';

    protected static ?string $modelLabel = 'Análise de Investimentos';

    protected static ?string $pluralModelLabel = 'Análises de Investimentos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('1. Parâmetros da Análise e Modelo de IA')
                    ->description('Selecione a carteira de referência e a inteligência artificial para o diagnóstico.')
                    ->schema([
                        Forms\Components\Select::make('portfolio_import_id')
                            ->label('Retrato da Carteira (Investidor10)')
                            ->options(
                                PortfolioImport::query()
                                    ->latest('reference_date')
                                    ->get()
                                    ->mapWithKeys(fn ($item) => [
                                        $item->id => "Carteira de " . $item->reference_date->format('d/m/Y') . " (R$ " . number_format($item->total_equity, 2, ',', '.') . " - " . $item->original_filename . ")",
                                    ])
                            )
                            ->default(fn () => PortfolioImport::latest('reference_date')->first()?->id)
                            ->required(),

                        Forms\Components\Select::make('ai_provider_configuration_id')
                            ->label('Provedor e Modelo de IA')
                            ->options(
                                AiProviderConfiguration::where('is_active', true)
                                    ->get()
                                    ->mapWithKeys(fn ($item) => [
                                        $item->id => "{$item->name} ({$item->model_id})" . ($item->is_default ? ' [Padrão]' : ''),
                                    ])
                            )
                            ->default(fn () => AiProviderConfiguration::where('is_default', true)->first()?->id ?? AiProviderConfiguration::first()?->id)
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('2. Situação Financeira do Mês e Aporte')
                    ->description('Informe as condições financeiras reais para que a IA calibre a necessidade de liquidez e risco.')
                    ->schema([
                        MoneyInput::make('available_amount')
                            ->label('Valor Disponível para Aportar (R$)')
                            ->required()
                            ->helperText('A IA distribuirá exatamente este montante entre as classes e ativos prioritários.'),

                        MoneyInput::make('emergency_reserve_amount')
                            ->label('Reserva de Emergência Atual (R$)')
                            ->helperText('Se estiver abaixo da meta, a IA alertará a priorização de liquidez.'),

                        MoneyInput::make('upcoming_expenses')
                            ->label('Despesas Previstas de Curto Prazo (R$)'),

                        Forms\Components\Toggle::make('has_debts')
                            ->label('Possui Dívidas Ativas no Momento?')
                            ->reactive(),

                        Forms\Components\TextInput::make('debts_details')
                            ->label('Condições das Dívidas (Taxa de juros, saldo, prazo)')
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('has_debts'))
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('monthly_notes')
                            ->label('Observações Adicionais para a Análise deste Mês')
                            ->placeholder('Ex: Recebi 13º salário, pretendo fazer viagem daqui a 6 meses, etc.')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data da Análise')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('portfolioImport.reference_date')
                    ->label('Ref. Carteira')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('model_used')
                    ->label('Modelo de IA')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                Tables\Columns\TextColumn::make('available_amount')
                    ->label('Aporte Disponível')
                    ->money('BRL')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'info',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'completed' => 'Concluída',
                        'failed' => 'Falhou',
                        default => $state,
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Resumo Executivo da IA')
                    ->schema([
                        Infolists\Components\TextEntry::make('executive_summary')
                            ->label('Visão Estratégica')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('model_used')
                            ->label('Modelo Utilizado')
                            ->badge()
                            ->color('primary'),

                        Infolists\Components\TextEntry::make('available_amount')
                            ->label('Aporte Analisado')
                            ->money('BRL'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Data da Execução')
                            ->dateTime('d/m/Y H:i'),
                    ])->columns(3),

                Infolists\Components\Section::make('Prioridades e Diagnóstico')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('priorities')
                            ->label('Prioridades de Alocação Identificadas')
                            ->schema([
                                Infolists\Components\TextEntry::make('')
                                    ->formatStateUsing(fn ($state) => "• " . (is_string($state) ? $state : json_encode($state))),
                            ])
                            ->columnSpan(1),

                        Infolists\Components\RepeatableEntry::make('limitations')
                            ->label('Limitações e Dados Ausentes')
                            ->schema([
                                Infolists\Components\TextEntry::make('')
                                    ->formatStateUsing(fn ($state) => "⚠ " . (is_string($state) ? $state : json_encode($state))),
                            ])
                            ->columnSpan(1),
                    ])->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AllocationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvestmentAnalyses::route('/'),
            'create' => Pages\CreateInvestmentAnalysis::route('/create'),
            'view' => Pages\ViewInvestmentAnalysis::route('/{record}'),
            'edit' => Pages\EditInvestmentAnalysis::route('/{record}/edit'),
        ];
    }
}

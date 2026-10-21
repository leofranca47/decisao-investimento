<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PortfolioImportResource\Pages;
use App\Filament\Resources\PortfolioImportResource\RelationManagers\PositionsRelationManager;
use App\Models\PortfolioImport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PortfolioImportResource extends Resource
{
    protected static ?string $model = PortfolioImport::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Carteira';

    protected static ?string $modelLabel = 'Importação de Carteira';

    protected static ?string $pluralModelLabel = 'Importações de Carteira';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Upload do Arquivo do Investidor10')
                    ->description('Selecione a exportação CSV ou Excel gerada no Investidor10 para criar um retrato imutável da sua carteira.')
                    ->schema([
                        Forms\Components\DatePicker::make('reference_date')
                            ->label('Data de Referência da Carteira')
                            ->default(now())
                            ->required(),

                        Forms\Components\FileUpload::make('file_upload')
                            ->label('Arquivo da Carteira (CSV ou XLSX)')
                            ->disk('local')
                            ->directory('temp_uploads')
                            ->acceptedFileTypes([
                                'text/csv',
                                'text/plain',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ])
                            ->maxSize(20480) // 20 MB
                            ->required()
                            ->columnSpanFull(),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_date')
                    ->label('Data de Referência')
                    ->date('d/m/Y')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('original_filename')
                    ->label('Arquivo')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('accepted_rows')
                    ->label('Ativos Importados')
                    ->numeric()
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_equity')
                    ->label('Patrimônio Total')
                    ->money('BRL')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        'cancelled' => 'warning',
                        default => 'info',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'completed' => 'Concluído',
                        'failed' => 'Falhou',
                        'pending' => 'Pendente',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Importado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('reference_date', 'desc')
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

    public static function getRelations(): array
    {
        return [
            PositionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPortfolioImports::route('/'),
            'create' => Pages\CreatePortfolioImport::route('/create'),
            'view' => Pages\ViewPortfolioImport::route('/{record}'),
            'edit' => Pages\EditPortfolioImport::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiProviderConfigurationResource\Pages;
use App\Models\AiProviderConfiguration;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AiProviderConfigurationResource extends Resource
{
    protected static ?string $model = AiProviderConfiguration::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?string $modelLabel = 'Provedor de IA';

    protected static ?string $pluralModelLabel = 'Provedores de IA';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação do Provedor')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome Amigável')
                            ->placeholder('Ex: Google Gemini 1.5 Pro')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\Select::make('provider_slug')
                            ->label('Provedor')
                            ->options([
                                'gemini' => 'Google Gemini (Oficial)',
                                'minimax' => 'MiniMax (Oficial / OpenAI Compatible)',
                                'opencode' => 'OpenCode / OpenAI-Compatible (Local ou Remoto)',
                            ])
                            ->default('gemini')
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('model_id')
                            ->label('Identificador do Modelo')
                            ->placeholder(fn (Forms\Get $get): string => match ($get('provider_slug')) {
                                'minimax' => 'Ex: MiniMax-M2.7-highspeed ou MiniMax-M3.1-Flash',
                                'opencode' => 'Ex: qwen2.5-coder:14b ou deepseek-coder',
                                default => 'Ex: gemini-3.5-flash ou gemini-3.7-flash',
                            })
                            ->helperText(fn (Forms\Get $get): ?string => match ($get('provider_slug')) {
                                'gemini' => 'Modelos recomendados com melhor disponibilidade: gemini-3.5-flash ou gemini-3.7-flash.',
                                'minimax' => 'Modelos recomendados: MiniMax-M2.7-highspeed (rápido) ou MiniMax-M3.1-Flash.',
                                default => null,
                            })
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('endpoint')
                            ->label('Endpoint Customizado (Opcional)')
                            ->placeholder(fn (Forms\Get $get): string => match ($get('provider_slug')) {
                                'minimax' => 'https://api.minimax.io/v1',
                                'opencode' => 'http://host.docker.internal:11434/v1',
                                default => 'https://generativelanguage.googleapis.com/v1beta',
                            })
                            ->helperText(fn (Forms\Get $get): ?string => match ($get('provider_slug')) {
                                'minimax' => 'Deixe em branco para o endpoint oficial global (https://api.minimax.io/v1). Se sua conta for da plataforma chinesa, informe https://api.minimax.chat/v1.',
                                'opencode' => 'Para serviços rodando no computador host (ex: Ollama), utilize http://host.docker.internal:11434/v1.',
                                default => null,
                            })
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Credenciais e Parâmetros')
                    ->schema([
                        Forms\Components\TextInput::make('api_key')
                            ->label('Chave de API (Criptografada em Repouso)')
                            ->password()
                            ->revealable()
                            ->helperText(fn (Forms\Get $get): string => match ($get('provider_slug')) {
                                'opencode' => 'Chave de autenticação Bearer. Se o servidor local não exigir senha, você pode preencher com qualquer texto.',
                                default => 'A chave é criptografada no PostgreSQL e nunca exposta em logs ou respostas públicas.',
                            })
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn ($state) => filled($state)),

                        Forms\Components\KeyValue::make('parameters')
                            ->label('Parâmetros Adicionais (temperatura, max_tokens, etc.)')
                            ->keyLabel('Parâmetro')
                            ->valueLabel('Valor'),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Toggle::make('is_active')
                                ->label('Ativo para novas análises')
                                ->default(true)
                                ->required(),

                            Forms\Components\Toggle::make('is_default')
                                ->label('Definir como Provedor Padrão')
                                ->default(false)
                                ->required(),
                        ]),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações Internas')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('provider_slug')
                    ->label('Provedor')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'gemini' => 'info',
                        'minimax' => 'warning',
                        'opencode' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'gemini' => 'Google Gemini',
                        'minimax' => 'MiniMax',
                        'opencode' => 'OpenCode',
                        default => ucfirst($state),
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('model_id')
                    ->label('Modelo')
                    ->searchable(),

                Tables\Columns\TextColumn::make('masked_api_key')
                    ->label('Chave Mascarada'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Padrão')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Ativos'),
            ])
            ->actions([
                Tables\Actions\Action::make('testConnection')
                    ->label('Testar Conexão')
                    ->icon('heroicon-o-signal')
                    ->color('info')
                    ->action(function (AiProviderConfiguration $record) {
                        $manager = app(\App\Services\Ai\AiProviderManager::class);
                        $result = $manager->testConnection($record);

                        if ($result->isSuccessful) {
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Conexão bem-sucedida!')
                                ->body($result->message)
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Falha na conexão com a IA')
                                ->body($result->message)
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('setDefault')
                    ->label('Tornar Padrão')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn (AiProviderConfiguration $record): bool => !$record->is_default)
                    ->action(function (AiProviderConfiguration $record) {
                        AiProviderConfiguration::where('id', '!=', $record->id)->update(['is_default' => false]);
                        $record->update(['is_default' => true]);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Provedor definido como padrão!')
                            ->body("'{$record->name}' agora é o modelo padrão para novas análises.")
                            ->send();
                    }),

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
            'index' => Pages\ListAiProviderConfigurations::route('/'),
            'create' => Pages\CreateAiProviderConfiguration::route('/create'),
            'edit' => Pages\EditAiProviderConfiguration::route('/{record}/edit'),
        ];
    }
}

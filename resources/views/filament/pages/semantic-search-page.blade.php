<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Status do pgvector --}}
        <x-filament::section>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center space-x-3">
                    @if($isAvailable)
                        <div class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></div>
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                <span>pgvector Habilitado</span>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                                    PostgreSQL Vector Ativo
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Dimensão: 768 floats (HNSW cosine) • Total de trechos indexados: <strong>{{ $totalEmbeddings }}</strong>
                            </div>
                        </div>
                    @else
                        <div class="w-3 h-3 rounded-full bg-amber-500"></div>
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">
                                pgvector Não Instalado / Desabilitado
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                A aplicação funciona normalmente no modo relacional padrão. A busca semântica está desativada.
                            </div>
                        </div>
                    @endif
                </div>

                @if($isAvailable)
                    <div>
                        <x-filament::button
                            wire:click="reindexAll"
                            color="gray"
                            size="sm"
                            icon="heroicon-o-arrow-path"
                        >
                            Reindexar Análises
                        </x-filament::button>
                    </div>
                @endif
            </div>
        </x-filament::section>

        {{-- Barra de Busca --}}
        @if($isAvailable)
            <x-filament::section>
                <x-slot name="heading">
                    Pesquisa Semântica por Perguntas ou Ideias
                </x-slot>
                <x-slot name="description">
                    Busca por significado vetorial nas recomendações, prioridades e justificativas de análises passadas, mesmo sem correspondência exata de palavras.
                </x-slot>

                <form wire:submit.prevent="search" class="space-y-4">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="flex-1">
                            <input
                                type="text"
                                wire:model="searchQuery"
                                placeholder="Ex: 'recomendações focadas em dividendos', 'por que aumentar caixa?', 'riscos em fundos imobiliários'..."
                                class="w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:border-primary-500 focus:ring-primary-500 shadow-sm"
                            />
                            @error('searchQuery')
                                <p class="text-xs text-danger-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
                            Buscar
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>

            {{-- Resultados da Busca --}}
            @if($hasSearched)
                <x-filament::section>
                    <x-slot name="heading">
                        Resultados Semânticos Encontrados ({{ count($searchResults) }})
                    </x-slot>

                    @if(empty($searchResults))
                        <div class="text-center py-8 text-gray-500">
                            Nenhum resultado similar encontrado para esta consulta. Tente termos diferentes ou certifique-se de que as análises foram indexadas.
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($searchResults as $result)
                                <div class="p-4 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-white/5 space-y-2">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-semibold uppercase px-2 py-0.5 rounded bg-primary-100 text-primary-800 dark:bg-primary-900/50 dark:text-primary-300">
                                                {{ $result['chunk_type'] === 'summary' ? 'Resumo & Estratégia' : 'Alocações & Racional' }}
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                Análise de {{ \Carbon\Carbon::parse($result['analysis_date'])->format('d/m/Y H:i') }}
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                (Aporte: R$ {{ number_format($result['available_amount'], 2, ',', '.') }})
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-3">
                                            <div class="text-xs font-mono font-bold px-2.5 py-1 rounded-full {{ $result['similarity_score'] >= 80 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300' }}">
                                                {{ $result['similarity_score'] }}% de similaridade
                                            </div>
                                            <a
                                                href="{{ $result['view_url'] }}"
                                                class="text-xs font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400 underline"
                                                target="_blank"
                                            >
                                                Ver Análise &rarr;
                                            </a>
                                        </div>
                                    </div>

                                    <div class="text-sm text-gray-800 dark:text-gray-200 font-sans leading-relaxed">
                                        {{ $result['chunk_text'] }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-filament::section>
            @endif
        @endif
    </div>
</x-filament-panels::page>

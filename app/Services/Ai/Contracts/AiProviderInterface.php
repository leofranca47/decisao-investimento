<?php

namespace App\Services\Ai\Contracts;

use App\Models\AiProviderConfiguration;
use App\Services\Ai\Dtos\AiConnectionTestResult;
use App\Services\Ai\Dtos\AiRawResponse;

interface AiProviderInterface
{
    /**
     * Retorna o slug identificador do provedor (ex: 'gemini', 'openai', 'anthropic').
     */
    public function getProviderSlug(): string;

    /**
     * Executa um teste leve de conexão/autenticação com o modelo configurado.
     */
    public function testConnection(AiProviderConfiguration $config): AiConnectionTestResult;

    /**
     * Executa uma chamada de geração de conteúdo para o provedor.
     */
    public function generate(
        AiProviderConfiguration $config,
        string $systemPrompt,
        string $userPrompt,
        array $options = []
    ): AiRawResponse;
}

<?php

namespace App\Services\Ai\Providers;

use App\Models\AiProviderConfiguration;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\Dtos\AiConnectionTestResult;
use App\Services\Ai\Dtos\AiRawResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenCodeProvider implements AiProviderInterface
{
    protected string $defaultBaseUrl = 'http://host.docker.internal:11434/v1';

    public function getProviderSlug(): string
    {
        return 'opencode';
    }

    public function testConnection(AiProviderConfiguration $config): AiConnectionTestResult
    {
        $startTime = microtime(true);

        try {
            $url = $this->buildChatUrl($config);
            $headers = ['Content-Type' => 'application/json'];

            if (!empty($config->api_key)) {
                $headers['Authorization'] = "Bearer {$config->api_key}";
            }

            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->post($url, [
                    'model' => $config->model_id,
                    'messages' => [
                        ['role' => 'user', 'content' => 'ping'],
                    ],
                    'max_tokens' => 5,
                ]);

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                return AiConnectionTestResult::success(
                    latencyMs: $latencyMs,
                    message: "Conexão realizada com sucesso com o servidor OpenCode/OpenAI-Compatible '{$config->model_id}' ({$latencyMs}ms)."
                );
            }

            $status = $response->status();
            $body = $response->json();
            $errorMsg = $body['error']['message'] ?? $body['message'] ?? '';

            if ($status === 401 || $status === 403) {
                return AiConnectionTestResult::failure(
                    message: "Autenticação rejeitada pelo endpoint OpenCode/OpenAI-Compatible.",
                    errorCode: 'INVALID_API_KEY'
                );
            }

            if ($status === 404) {
                return AiConnectionTestResult::failure(
                    message: "Modelo ou rota não encontrada no endpoint '{$url}'.",
                    errorCode: 'NOT_FOUND'
                );
            }

            return AiConnectionTestResult::failure(
                message: "Falha na resposta do servidor OpenCode (Status {$status})." . ($errorMsg ? " Detalhes: {$errorMsg}" : ''),
                errorCode: 'HTTP_ERROR'
            );
        } catch (ConnectionException $e) {
            return AiConnectionTestResult::failure(
                message: "Não foi possível conectar ao endpoint. Se estiver rodando no host local, use 'http://host.docker.internal:...'.",
                errorCode: 'TIMEOUT'
            );
        } catch (\Throwable $e) {
            return AiConnectionTestResult::failure(
                message: "Erro inesperado ao conectar ao OpenCode: {$e->getMessage()}",
                errorCode: 'UNEXPECTED_ERROR'
            );
        }
    }

    public function generate(
        AiProviderConfiguration $config,
        string $systemPrompt,
        string $userPrompt,
        array $options = []
    ): AiRawResponse {
        $url = $this->buildChatUrl($config);
        $headers = ['Content-Type' => 'application/json'];

        if (!empty($config->api_key)) {
            $headers['Authorization'] = "Bearer {$config->api_key}";
        }

        $messages = [];
        if (!empty($systemPrompt)) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $userPrompt];

        $payload = [
            'model' => $config->model_id,
            'messages' => $messages,
            'temperature' => (float) ($options['temperature'] ?? 0.2),
        ];

        if (!empty($options['json_mode'])) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        if (!empty($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }

        $response = Http::withHeaders($headers)
            ->timeout((int) ($options['timeout'] ?? 90))
            ->post($url, $payload);

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->json();
            $errorMsg = $body['error']['message'] ?? $body['message'] ?? 'Erro retornado pelo servidor OpenCode/OpenAI-Compatible.';
            throw new RuntimeException("Falha na geração do OpenCode (Status {$status}): {$errorMsg}");
        }

        $data = $response->json();
        $choices = $data['choices'] ?? [];
        if (empty($choices)) {
            throw new RuntimeException("O endpoint OpenCode não retornou escolhas de resposta válidas.");
        }

        $content = $choices[0]['message']['content'] ?? '';
        $usage = $data['usage'] ?? [];

        return new AiRawResponse(
            content: $content,
            modelUsed: $config->model_id,
            tokensUsed: [
                'prompt' => $usage['prompt_tokens'] ?? null,
                'candidates' => $usage['completion_tokens'] ?? null,
                'total' => $usage['total_tokens'] ?? null,
            ],
            rawMetadata: [
                'finishReason' => $choices[0]['finish_reason'] ?? null,
            ]
        );
    }

    protected function buildChatUrl(AiProviderConfiguration $config): string
    {
        $baseUrl = !empty($config->endpoint) ? rtrim($config->endpoint, '/') : $this->defaultBaseUrl;
        if (str_ends_with($baseUrl, '/chat/completions')) {
            return $baseUrl;
        }
        return "{$baseUrl}/chat/completions";
    }
}

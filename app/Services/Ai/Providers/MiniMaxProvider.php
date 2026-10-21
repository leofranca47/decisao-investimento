<?php

namespace App\Services\Ai\Providers;

use App\Models\AiProviderConfiguration;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\Dtos\AiConnectionTestResult;
use App\Services\Ai\Dtos\AiRawResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MiniMaxProvider implements AiProviderInterface
{
    protected string $defaultBaseUrl = 'https://api.minimax.io/v1';

    public function getProviderSlug(): string
    {
        return 'minimax';
    }

    public function testConnection(AiProviderConfiguration $config): AiConnectionTestResult
    {
        $startTime = microtime(true);

        try {
            $url = $this->buildChatUrl($config);
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$config->api_key}",
                'Content-Type' => 'application/json',
            ])->timeout(10)->post($url, [
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
                    message: "Conexão realizada com sucesso com o modelo MiniMax '{$config->model_id}' ({$latencyMs}ms)."
                );
            }

            $status = $response->status();
            $body = $response->json();
            $errorMsg = $body['error']['message'] ?? $body['base_resp']['status_msg'] ?? '';

            if ($status === 401 || str_contains($errorMsg, 'invalid api key') || str_contains($errorMsg, 'auth')) {
                return AiConnectionTestResult::failure(
                    message: "Chave de API inválida ou não autorizada para o MiniMax.",
                    errorCode: 'INVALID_API_KEY'
                );
            }

            if ($status === 404 || str_contains($errorMsg, 'model') || str_contains($errorMsg, 'not found')) {
                return AiConnectionTestResult::failure(
                    message: "Modelo MiniMax '{$config->model_id}' não encontrado ou indisponível.",
                    errorCode: 'MODEL_NOT_FOUND'
                );
            }

            if ($status === 429) {
                return AiConnectionTestResult::failure(
                    message: "Limite de requisições do MiniMax excedido.",
                    errorCode: 'RATE_LIMIT_EXCEEDED'
                );
            }

            return AiConnectionTestResult::failure(
                message: "Falha na comunicação com a API do MiniMax (Status {$status})." . ($errorMsg ? " Detalhes: {$errorMsg}" : ''),
                errorCode: 'HTTP_ERROR'
            );
        } catch (ConnectionException $e) {
            return AiConnectionTestResult::failure(
                message: "Tempo limite de conexão esgotado (Timeout) ao contatar o MiniMax.",
                errorCode: 'TIMEOUT'
            );
        } catch (\Throwable $e) {
            return AiConnectionTestResult::failure(
                message: "Erro inesperado ao conectar ao MiniMax: {$e->getMessage()}",
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

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$config->api_key}",
            'Content-Type' => 'application/json',
        ])->timeout((int) ($options['timeout'] ?? 120))->post($url, $payload);

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->json();
            $errorMsg = $body['error']['message'] ?? $body['base_resp']['status_msg'] ?? 'Erro retornado pela API MiniMax.';
            throw new RuntimeException("Falha na geração do MiniMax (Status {$status}): {$errorMsg}");
        }

        $data = $response->json();
        $choices = $data['choices'] ?? [];
        if (empty($choices)) {
            throw new RuntimeException("A API do MiniMax não retornou opções de resposta.");
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

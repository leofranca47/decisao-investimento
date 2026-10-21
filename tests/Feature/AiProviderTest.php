<?php

namespace Tests\Feature;

use App\Models\AiProviderConfiguration;
use App\Models\User;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Providers\GeminiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    use RefreshDatabase;

    private function createGeminiConfig(array $overrides = []): AiProviderConfiguration
    {
        return AiProviderConfiguration::create(array_merge([
            'name' => 'Google Gemini 1.5 Flash',
            'provider_slug' => 'gemini',
            'model_id' => 'gemini-1.5-flash',
            'api_key' => 'AIzaSyExampleValidKeyForTestOnly12345',
            'is_active' => true,
            'is_default' => true,
        ], $overrides));
    }

    public function test_gemini_connection_test_success(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'pong'],
                            ],
                            'role' => 'model',
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 1,
                    'candidatesTokenCount' => 1,
                    'totalTokenCount' => 2,
                ],
            ], 200),
        ]);

        $config = $this->createGeminiConfig();
        $provider = app(GeminiProvider::class);

        $result = $provider->testConnection($config);

        $this->assertTrue($result->isSuccessful);
        $this->assertStringContainsString('Conexão realizada com sucesso', $result->message);
        $this->assertGreaterThanOrEqual(0, $result->latencyMs);
    }

    public function test_gemini_connection_test_fails_with_invalid_api_key(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent*' => Http::response([
                'error' => [
                    'code' => 400,
                    'message' => 'API key not valid. Please pass a valid API key.',
                    'status' => 'INVALID_ARGUMENT',
                ],
            ], 400),
        ]);

        $config = $this->createGeminiConfig(['api_key' => 'SecretInvalidKeyXYZ999']);
        $provider = app(GeminiProvider::class);

        $result = $provider->testConnection($config);

        $this->assertFalse($result->isSuccessful);
        $this->assertStringContainsString('Chave de API inválida', $result->message);
        // Garante que a chave real não vaza na mensagem de erro
        $this->assertStringNotContainsString('SecretInvalidKeyXYZ999', $result->message);
    }

    public function test_gemini_connection_test_fails_with_model_not_found(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/modelo-inexistente:generateContent*' => Http::response([
                'error' => [
                    'code' => 404,
                    'message' => 'models/modelo-inexistente is not found for API version v1beta',
                    'status' => 'NOT_FOUND',
                ],
            ], 404),
        ]);

        $config = $this->createGeminiConfig(['model_id' => 'modelo-inexistente']);
        $provider = app(GeminiProvider::class);

        $result = $provider->testConnection($config);

        $this->assertFalse($result->isSuccessful);
        $this->assertStringContainsStringIgnoringCase('não encontrado', $result->message);
    }

    public function test_gemini_generate_content_returns_raw_response(): void
    {
        $expectedJson = json_encode(['greeting' => 'hello world']);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $expectedJson],
                            ],
                            'role' => 'model',
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 10,
                    'candidatesTokenCount' => 8,
                    'totalTokenCount' => 18,
                ],
            ], 200),
        ]);

        $config = $this->createGeminiConfig();
        $provider = app(GeminiProvider::class);

        $response = $provider->generate(
            config: $config,
            systemPrompt: 'Você é um assistente financeiro.',
            userPrompt: 'Diga olá em formato JSON.',
            options: ['json_mode' => true]
        );

        $this->assertEquals($expectedJson, $response->content);
        $this->assertEquals('gemini-1.5-flash', $response->modelUsed);
        $this->assertEquals(18, $response->tokensUsed['total'] ?? null);
    }

    public function test_ai_provider_manager_resolves_gemini_driver(): void
    {
        $manager = app(AiProviderManager::class);
        $provider = $manager->getProvider('gemini');

        $this->assertInstanceOf(GeminiProvider::class, $provider);
    }

    public function test_ai_provider_manager_resolves_minimax_and_opencode_drivers(): void
    {
        $manager = app(AiProviderManager::class);

        $minimax = $manager->getProvider('minimax');
        $this->assertEquals('minimax', $minimax->getProviderSlug());

        $opencode = $manager->getProvider('opencode');
        $this->assertEquals('opencode', $opencode->getProviderSlug());
    }

    public function test_minimax_connection_test_success(): void
    {
        Http::fake([
            'https://api.minimax.io/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'pong',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 2,
                    'completion_tokens' => 1,
                    'total_tokens' => 3,
                ],
            ], 200),
        ]);

        $config = AiProviderConfiguration::create([
            'name' => 'MiniMax Text 01',
            'provider_slug' => 'minimax',
            'model_id' => 'MiniMax-Text-01',
            'api_key' => 'minimax_secret_key_123',
            'is_active' => true,
        ]);

        $manager = app(AiProviderManager::class);
        $result = $manager->testConnection($config);

        $this->assertTrue($result->isSuccessful);
        $this->assertStringContainsString('MiniMax-Text-01', $result->message);
    }

    public function test_minimax_generate_content_success(): void
    {
        $expectedJson = json_encode(['executive_summary' => 'Resumo MiniMax']);

        Http::fake([
            'https://api.minimax.io/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => $expectedJson,
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 20,
                    'completion_tokens' => 15,
                    'total_tokens' => 35,
                ],
            ], 200),
        ]);

        $config = AiProviderConfiguration::create([
            'name' => 'MiniMax Text 01',
            'provider_slug' => 'minimax',
            'model_id' => 'MiniMax-Text-01',
            'api_key' => 'minimax_secret_key_123',
            'is_active' => true,
        ]);

        $manager = app(AiProviderManager::class);
        $provider = $manager->getProvider('minimax');

        $response = $provider->generate(
            config: $config,
            systemPrompt: 'System prompt',
            userPrompt: 'User prompt',
            options: ['json_mode' => true]
        );

        $this->assertEquals($expectedJson, $response->content);
        $this->assertEquals('MiniMax-Text-01', $response->modelUsed);
        $this->assertEquals(35, $response->tokensUsed['total']);
    }

    public function test_opencode_connection_test_and_generate_success(): void
    {
        $customEndpoint = 'http://host.docker.internal:11434/v1';

        Http::fake([
            $customEndpoint . '/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode(['executive_summary' => 'Resumo OpenCode']),
                        ],
                    ],
                ],
                'usage' => [
                    'total_tokens' => 42,
                ],
            ], 200),
        ]);

        $config = AiProviderConfiguration::create([
            'name' => 'OpenCode Local Qwen 2.5',
            'provider_slug' => 'opencode',
            'model_id' => 'qwen2.5-coder:14b',
            'endpoint' => $customEndpoint,
            'api_key' => 'dummy_or_local_key',
            'is_active' => true,
        ]);

        $manager = app(AiProviderManager::class);
        $result = $manager->testConnection($config);

        $this->assertTrue($result->isSuccessful);

        $provider = $manager->getProvider('opencode');
        $response = $provider->generate(
            config: $config,
            systemPrompt: 'System',
            userPrompt: 'User'
        );

        $this->assertStringContainsString('Resumo OpenCode', $response->content);
        $this->assertEquals('qwen2.5-coder:14b', $response->modelUsed);
    }
}

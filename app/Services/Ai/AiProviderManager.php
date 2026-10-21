<?php

namespace App\Services\Ai;

use App\Models\AiProviderConfiguration;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\Dtos\AiConnectionTestResult;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\MiniMaxProvider;
use App\Services\Ai\Providers\OpenCodeProvider;
use InvalidArgumentException;

class AiProviderManager
{
    /**
     * @var array<string, AiProviderInterface>
     */
    protected array $providers = [];

    public function __construct(
        GeminiProvider $geminiProvider,
        MiniMaxProvider $miniMaxProvider,
        OpenCodeProvider $openCodeProvider
    ) {
        $this->registerProvider($geminiProvider);
        $this->registerProvider($miniMaxProvider);
        $this->registerProvider($openCodeProvider);
    }

    public function registerProvider(AiProviderInterface $provider): void
    {
        $this->providers[$provider->getProviderSlug()] = $provider;
    }

    public function getProvider(string $slug): AiProviderInterface
    {
        if (!isset($this->providers[$slug])) {
            throw new InvalidArgumentException("Provedor de IA '{$slug}' não está registrado no sistema.");
        }

        return $this->providers[$slug];
    }

    public function testConnection(AiProviderConfiguration $config): AiConnectionTestResult
    {
        $provider = $this->getProvider($config->provider_slug);
        return $provider->testConnection($config);
    }

    public function getDefaultConfiguration(): ?AiProviderConfiguration
    {
        return AiProviderConfiguration::where('is_active', true)
            ->where('is_default', true)
            ->first()
            ?? AiProviderConfiguration::where('is_active', true)->first();
    }
}

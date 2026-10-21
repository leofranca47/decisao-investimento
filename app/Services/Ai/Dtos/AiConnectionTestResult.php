<?php

namespace App\Services\Ai\Dtos;

class AiConnectionTestResult
{
    public function __construct(
        public bool $isSuccessful,
        public string $message,
        public int $latencyMs = 0,
        public ?string $errorCode = null,
        public array $rawMetadata = []
    ) {}

    public static function success(int $latencyMs, string $message = 'Conexão realizada com sucesso!'): self
    {
        return new self(
            isSuccessful: true,
            message: $message,
            latencyMs: $latencyMs
        );
    }

    public static function failure(string $message, ?string $errorCode = null, array $rawMetadata = []): self
    {
        return new self(
            isSuccessful: false,
            message: $message,
            errorCode: $errorCode,
            rawMetadata: $rawMetadata
        );
    }
}

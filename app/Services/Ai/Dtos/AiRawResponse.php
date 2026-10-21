<?php

namespace App\Services\Ai\Dtos;

class AiRawResponse
{
    public function __construct(
        public string $content,
        public string $modelUsed,
        public array $tokensUsed = [],
        public array $rawMetadata = []
    ) {}
}

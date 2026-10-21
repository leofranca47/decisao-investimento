<?php

namespace App\Services\Import\Contracts;

use App\Services\Import\Dtos\ImportPreviewDto;

interface PortfolioImporterInterface
{
    /**
     * Determina se este importador é capaz de processar o arquivo fornecido.
     */
    public function canHandle(string $filePath, string $originalExtension): bool;

    /**
     * Processa o arquivo e retorna o DTO de pré-visualização.
     */
    public function parse(string $filePath): ImportPreviewDto;
}

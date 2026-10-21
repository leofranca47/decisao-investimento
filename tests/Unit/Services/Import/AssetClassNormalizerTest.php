<?php

namespace Tests\Unit\Services\Import;

use App\Services\Import\Normalizers\AssetClassNormalizer;
use PHPUnit\Framework\TestCase;

class AssetClassNormalizerTest extends TestCase
{
    public function test_normalizes_various_asset_class_strings(): void
    {
        $this->assertEquals('acoes_br', AssetClassNormalizer::normalize('Ações'));
        $this->assertEquals('acoes_br', AssetClassNormalizer::normalize('Acoes'));
        $this->assertEquals('acoes_br', AssetClassNormalizer::normalize('AÇÃO'));
        $this->assertEquals('acoes_br', AssetClassNormalizer::normalize('Acao'));
        $this->assertEquals('acoes_br', AssetClassNormalizer::normalize('STOCK'));

        $this->assertEquals('fiis', AssetClassNormalizer::normalize('FIIs'));
        $this->assertEquals('fiis', AssetClassNormalizer::normalize('Fundo Imobiliário'));
        $this->assertEquals('fiis', AssetClassNormalizer::normalize('Fundos Imobiliários'));
        $this->assertEquals('fiis', AssetClassNormalizer::normalize('FII'));

        $this->assertEquals('tesouro_direto', AssetClassNormalizer::normalize('Tesouro Direto'));
        $this->assertEquals('tesouro_direto', AssetClassNormalizer::normalize('Tesouro'));
        $this->assertEquals('tesouro_direto', AssetClassNormalizer::normalize('TD'));

        $this->assertEquals('etfs_internacionais', AssetClassNormalizer::normalize('ETFs Internacionais'));
        $this->assertEquals('etfs_internacionais', AssetClassNormalizer::normalize('ETF Internacional'));
        $this->assertEquals('etfs_internacionais', AssetClassNormalizer::normalize('BDR'));
        $this->assertEquals('etfs_internacionais', AssetClassNormalizer::normalize('BDRs'));

        $this->assertEquals('renda_fixa_privada', AssetClassNormalizer::normalize('Renda Fixa'));
        $this->assertEquals('renda_fixa_privada', AssetClassNormalizer::normalize('CDB'));
        $this->assertEquals('renda_fixa_privada', AssetClassNormalizer::normalize('LCI'));

        $this->assertEquals('cripto', AssetClassNormalizer::normalize('Cripto'));
        $this->assertEquals('cripto', AssetClassNormalizer::normalize('Criptomoeda'));

        $this->assertEquals('outros', AssetClassNormalizer::normalize('Desconhecido'));
        $this->assertEquals('outros', AssetClassNormalizer::normalize(''));
    }
}

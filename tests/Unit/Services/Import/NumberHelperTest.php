<?php

namespace Tests\Unit\Services\Import;

use App\Services\Import\Helpers\NumberHelper;
use PHPUnit\Framework\TestCase;

class NumberHelperTest extends TestCase
{
    public function test_parses_brazilian_currency_and_numbers(): void
    {
        $this->assertEquals(1250.50, NumberHelper::parseDecimal('1.250,50'));
        $this->assertEquals(1250.50, NumberHelper::parseDecimal('R$ 1.250,50'));
        $this->assertEquals(0.00, NumberHelper::parseDecimal('0,00'));
        $this->assertEquals(15.75, NumberHelper::parseDecimal('15,75%'));
        $this->assertEquals(100.0, NumberHelper::parseDecimal('100'));
        $this->assertEquals(45.1234, NumberHelper::parseDecimal('45,1234'));
        $this->assertEquals(0.0, NumberHelper::parseDecimal('-'));
        $this->assertEquals(0.0, NumberHelper::parseDecimal(null));
    }
}

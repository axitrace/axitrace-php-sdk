<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Model;

use AxiTrace\Model\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function testCreate(): void
    {
        $money = new Money(99.99, 'USD');

        $this->assertEquals(99.99, $money->getAmount());
        $this->assertEquals('USD', $money->getCurrency());
    }

    public function testCurrencyUppercased(): void
    {
        $money = new Money(50.00, 'eur');

        $this->assertEquals('EUR', $money->getCurrency());
    }

    public function testToArray(): void
    {
        $money = new Money(149.99, 'GBP');
        $array = $money->toArray();

        $this->assertEquals([
            'amount' => 149.99,
            'currency' => 'GBP',
        ], $array);
    }

    public function testFromArray(): void
    {
        $money = Money::fromArray([
            'amount' => 200.50,
            'currency' => 'CAD',
        ]);

        $this->assertEquals(200.50, $money->getAmount());
        $this->assertEquals('CAD', $money->getCurrency());
    }

    public function testFromArrayDefaults(): void
    {
        $money = Money::fromArray([]);

        $this->assertEquals(0, $money->getAmount());
        $this->assertEquals('USD', $money->getCurrency());
    }

    public function testToString(): void
    {
        $money = new Money(99.99, 'USD');

        $this->assertEquals('99.99 USD', (string) $money);
    }

    public function testToStringWithZeroDecimals(): void
    {
        $money = new Money(100.00, 'EUR');

        $this->assertEquals('100.00 EUR', (string) $money);
    }

    public function testToStringWithOneDecimal(): void
    {
        $money = new Money(50.50, 'GBP');

        $this->assertEquals('50.50 GBP', (string) $money);
    }
}

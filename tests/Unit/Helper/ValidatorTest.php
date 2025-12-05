<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Helper;

use AxiTrace\Helper\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    public function testIsValidEmail(): void
    {
        $this->assertTrue(Validator::isValidEmail('test@example.com'));
        $this->assertTrue(Validator::isValidEmail('user.name@domain.co.uk'));
        $this->assertTrue(Validator::isValidEmail('user+tag@example.org'));

        $this->assertFalse(Validator::isValidEmail(''));
        $this->assertFalse(Validator::isValidEmail('not-an-email'));
        $this->assertFalse(Validator::isValidEmail('@example.com'));
        $this->assertFalse(Validator::isValidEmail('user@'));
        $this->assertFalse(Validator::isValidEmail('user@.com'));
    }

    public function testIsValidCurrency(): void
    {
        $this->assertTrue(Validator::isValidCurrency('USD'));
        $this->assertTrue(Validator::isValidCurrency('EUR'));
        $this->assertTrue(Validator::isValidCurrency('GBP'));
        $this->assertTrue(Validator::isValidCurrency('usd')); // Lowercase should work
        $this->assertTrue(Validator::isValidCurrency('XYZ')); // Any 3-letter code

        $this->assertFalse(Validator::isValidCurrency('US'));
        $this->assertFalse(Validator::isValidCurrency('USDD'));
        $this->assertFalse(Validator::isValidCurrency('123'));
        $this->assertFalse(Validator::isValidCurrency(''));
    }

    public function testIsCommonCurrency(): void
    {
        $this->assertTrue(Validator::isCommonCurrency('USD'));
        $this->assertTrue(Validator::isCommonCurrency('EUR'));
        $this->assertTrue(Validator::isCommonCurrency('GBP'));
        $this->assertTrue(Validator::isCommonCurrency('JPY'));
        $this->assertTrue(Validator::isCommonCurrency('PLN'));

        $this->assertFalse(Validator::isCommonCurrency('XYZ'));
        $this->assertFalse(Validator::isCommonCurrency('ABC'));
    }

    public function testIsValidUrl(): void
    {
        $this->assertTrue(Validator::isValidUrl('https://example.com'));
        $this->assertTrue(Validator::isValidUrl('http://example.com/path'));
        $this->assertTrue(Validator::isValidUrl('https://example.com/path?query=value'));
        $this->assertTrue(Validator::isValidUrl('https://subdomain.example.com'));
        $this->assertTrue(Validator::isValidUrl('ftp://example.com')); // Valid URL scheme

        $this->assertFalse(Validator::isValidUrl(''));
        $this->assertFalse(Validator::isValidUrl('not-a-url'));
        $this->assertFalse(Validator::isValidUrl('example.com'));
    }

    public function testIsPositive(): void
    {
        $this->assertTrue(Validator::isPositive(1));
        $this->assertTrue(Validator::isPositive(0.01));
        $this->assertTrue(Validator::isPositive(100.50));

        $this->assertFalse(Validator::isPositive(0));
        $this->assertFalse(Validator::isPositive(-1));
        $this->assertFalse(Validator::isPositive(-0.01));
    }

    public function testIsNonNegative(): void
    {
        $this->assertTrue(Validator::isNonNegative(0));
        $this->assertTrue(Validator::isNonNegative(1));
        $this->assertTrue(Validator::isNonNegative(100.50));

        $this->assertFalse(Validator::isNonNegative(-1));
        $this->assertFalse(Validator::isNonNegative(-0.01));
    }

    public function testIsNotEmpty(): void
    {
        $this->assertTrue(Validator::isNotEmpty('hello'));
        $this->assertTrue(Validator::isNotEmpty(['item']));
        $this->assertTrue(Validator::isNotEmpty(0)); // 0 is not empty
        $this->assertTrue(Validator::isNotEmpty(false)); // false is not empty

        $this->assertFalse(Validator::isNotEmpty(''));
        $this->assertFalse(Validator::isNotEmpty('   ')); // Whitespace only
        $this->assertFalse(Validator::isNotEmpty(null));
        $this->assertFalse(Validator::isNotEmpty([]));
    }

    public function testSanitizeString(): void
    {
        $this->assertEquals('hello', Validator::sanitizeString('hello'));
        $this->assertEquals('hello', Validator::sanitizeString('  hello  '));
        $this->assertEquals('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', Validator::sanitizeString('<script>alert("xss")</script>'));
    }

    public function testIsValidPhoneE164(): void
    {
        $this->assertTrue(Validator::isValidPhoneE164('+14155551234'));
        $this->assertTrue(Validator::isValidPhoneE164('+48123456789'));
        $this->assertTrue(Validator::isValidPhoneE164('+442071234567'));

        $this->assertFalse(Validator::isValidPhoneE164('14155551234')); // Missing +
        $this->assertFalse(Validator::isValidPhoneE164('+0123456789')); // Starts with 0
        $this->assertFalse(Validator::isValidPhoneE164('+1')); // Too short
        $this->assertFalse(Validator::isValidPhoneE164('+12345678901234567')); // Too long (16 digits)
        $this->assertFalse(Validator::isValidPhoneE164('+441onal234567890')); // Contains letters
    }

    public function testIsValidCountryCode(): void
    {
        $this->assertTrue(Validator::isValidCountryCode('US'));
        $this->assertTrue(Validator::isValidCountryCode('GB'));
        $this->assertTrue(Validator::isValidCountryCode('PL'));
        $this->assertTrue(Validator::isValidCountryCode('us')); // Lowercase

        $this->assertFalse(Validator::isValidCountryCode('USA'));
        $this->assertFalse(Validator::isValidCountryCode('U'));
        $this->assertFalse(Validator::isValidCountryCode('12'));
        $this->assertFalse(Validator::isValidCountryCode(''));
    }
}

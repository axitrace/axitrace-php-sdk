<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit;

use AxiTrace\Config;
use AxiTrace\Exception\ConfigurationException;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testValidSecretKeyLive(): void
    {
        $config = new Config('sk_live_test_key_12345');

        $this->assertEquals('sk_live_test_key_12345', $config->getSecretKey());
        $this->assertFalse($config->isTestMode());
    }

    public function testValidSecretKeyTest(): void
    {
        $config = new Config('sk_test_test_key_12345');

        $this->assertEquals('sk_test_test_key_12345', $config->getSecretKey());
        $this->assertTrue($config->isTestMode());
    }

    public function testDefaultBaseUrl(): void
    {
        $config = new Config('sk_test_key');

        $this->assertEquals('https://stat.axitrace.com', $config->getBaseUrl());
    }

    public function testCustomBaseUrl(): void
    {
        $config = new Config('sk_test_key', ['base_url' => 'https://custom.axitrace.com']);

        $this->assertEquals('https://custom.axitrace.com', $config->getBaseUrl());
    }

    public function testBaseUrlTrailingSlashRemoved(): void
    {
        $config = new Config('sk_test_key', ['base_url' => 'https://custom.axitrace.com/']);

        $this->assertEquals('https://custom.axitrace.com', $config->getBaseUrl());
    }

    public function testDefaultTimeout(): void
    {
        $config = new Config('sk_test_key');

        $this->assertEquals(30, $config->getTimeout());
    }

    public function testCustomTimeout(): void
    {
        $config = new Config('sk_test_key', ['timeout' => 60]);

        $this->assertEquals(60, $config->getTimeout());
    }

    public function testDefaultVerifySsl(): void
    {
        $config = new Config('sk_test_key');

        $this->assertTrue($config->shouldVerifySsl());
    }

    public function testDisableVerifySsl(): void
    {
        $config = new Config('sk_test_key', ['verify_ssl' => false]);

        $this->assertFalse($config->shouldVerifySsl());
    }

    public function testDefaultDebug(): void
    {
        $config = new Config('sk_test_key');

        $this->assertFalse($config->isDebug());
    }

    public function testEnableDebug(): void
    {
        $config = new Config('sk_test_key', ['debug' => true]);

        $this->assertTrue($config->isDebug());
    }

    public function testUserAgent(): void
    {
        $config = new Config('sk_test_key');
        $userAgent = $config->getUserAgent();

        $this->assertStringContainsString('AxiTrace-PHP-SDK/', $userAgent);
        $this->assertStringContainsString('PHP/', $userAgent);
    }

    public function testEmptySecretKeyThrowsException(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Secret key is required');

        new Config('');
    }

    public function testInvalidSecretKeyPrefixThrowsException(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid secret key format');

        new Config('invalid_key_12345');
    }

    public function testInvalidBaseUrlThrowsException(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid base URL');

        new Config('sk_test_key', ['base_url' => 'not-a-url']);
    }

    public function testInvalidTimeoutThrowsException(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid timeout value');

        new Config('sk_test_key', ['timeout' => -1]);
    }

    public function testZeroTimeoutThrowsException(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid timeout value');

        new Config('sk_test_key', ['timeout' => 0]);
    }
}

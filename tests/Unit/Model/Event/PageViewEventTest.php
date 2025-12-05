<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\Event\PageViewEvent;
use PHPUnit\Framework\TestCase;

class PageViewEventTest extends TestCase
{
    public function testGetEndpoint(): void
    {
        $event = new PageViewEvent('https://example.com');

        $this->assertEquals('/v1/page/view', $event->getEndpoint());
    }

    public function testGetAction(): void
    {
        $event = new PageViewEvent('https://example.com');

        $this->assertEquals('page_view', $event->getAction());
    }

    public function testToArray(): void
    {
        $event = (new PageViewEvent('https://example.com/page'))
            ->setClientId('visitor-123')
            ->setSessionId('session-456')
            ->setTitle('Test Page')
            ->setReferrer('https://google.com');

        $array = $event->toArray();

        // API-compatible format with nested client object
        $this->assertEquals('page_view', $array['label']);
        $this->assertArrayHasKey('client', $array);
        $this->assertEquals('visitor-123', $array['client']['customId']);
        $this->assertEquals('session-456', $array['sessionId']);
        $this->assertArrayHasKey('params', $array);
        $this->assertEquals('https://example.com/page', $array['params']['url']);
        $this->assertEquals('Test Page', $array['params']['title']);
        $this->assertEquals('https://google.com', $array['params']['referrer']);
    }

    public function testToArrayMinimal(): void
    {
        $event = (new PageViewEvent('https://example.com'))
            ->setClientId('visitor-123');

        $array = $event->toArray();

        // API-compatible format
        $this->assertEquals('page_view', $array['label']);
        $this->assertArrayHasKey('client', $array);
        $this->assertArrayHasKey('params', $array);
        $this->assertArrayHasKey('url', $array['params']);
        $this->assertArrayNotHasKey('title', $array['params']);
        $this->assertArrayNotHasKey('referrer', $array['params']);
    }

    public function testValidateSuccess(): void
    {
        $event = (new PageViewEvent('https://example.com'))
            ->setClientId('visitor-123');

        // Should not throw
        $event->validate();
        $this->assertTrue(true);
    }

    public function testValidateWithUserId(): void
    {
        $event = (new PageViewEvent('https://example.com'))
            ->setUserId('user-456');

        // Should not throw
        $event->validate();
        $this->assertTrue(true);
    }

    public function testValidateWithSessionId(): void
    {
        $event = (new PageViewEvent('https://example.com'))
            ->setSessionId('session-789');

        // Should not throw
        $event->validate();
        $this->assertTrue(true);
    }

    public function testValidateMissingUserIdentifier(): void
    {
        $event = new PageViewEvent('https://example.com');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('client_id, user_id, or session_id is required');

        $event->validate();
    }

    public function testValidateEmptyUrl(): void
    {
        $event = (new PageViewEvent(''))
            ->setClientId('visitor-123');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('url');

        $event->validate();
    }

    public function testWithParams(): void
    {
        $event = (new PageViewEvent('https://example.com'))
            ->setClientId('visitor-123')
            ->setParams(['custom_param' => 'value']);

        $array = $event->toArray();

        $this->assertArrayHasKey('params', $array);
        $this->assertEquals('value', $array['params']['custom_param']);
    }

    public function testWithFacebookParams(): void
    {
        $event = (new PageViewEvent('https://example.com'))
            ->setClientId('visitor-123')
            ->setFbp('fb.1.123456789.987654321')
            ->setFbc('fb.1.123456789.AbCdEfGh');

        $array = $event->toArray();

        $this->assertArrayHasKey('params', $array);
        $this->assertEquals('fb.1.123456789.987654321', $array['params']['fbp']);
        $this->assertEquals('fb.1.123456789.AbCdEfGh', $array['params']['fbc']);
    }
}

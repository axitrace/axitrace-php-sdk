<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\Event\FormSubmitEvent;
use PHPUnit\Framework\TestCase;

class FormSubmitEventTest extends TestCase
{
    public function testGetEndpoint(): void
    {
        $event = new FormSubmitEvent('contact-form');

        $this->assertEquals('/v1/form/submit', $event->getEndpoint());
    }

    public function testGetAction(): void
    {
        $event = new FormSubmitEvent('contact-form');

        $this->assertEquals('form.submit', $event->getAction());
    }

    public function testToArray(): void
    {
        $event = (new FormSubmitEvent('contact-form'))
            ->setClientCustomId('visitor-123')
            ->setClientEmail('user@example.com')
            ->setSessionId('session-456')
            ->setFormParams([
                'email' => 'user@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'message' => 'Hello!',
            ]);

        $array = $event->toArray();

        $this->assertEquals('contact-form', $array['label']);
        $this->assertEquals('visitor-123', $array['client']['customId']);
        $this->assertEquals('user@example.com', $array['client']['email']);
        $this->assertEquals('session-456', $array['sessionId']);
        $this->assertArrayHasKey('params', $array);
        $this->assertEquals('user@example.com', $array['params']['email']);
        $this->assertEquals('John', $array['params']['first_name']);
        $this->assertEquals('Doe', $array['params']['last_name']);
    }

    public function testValidateSuccess(): void
    {
        $event = (new FormSubmitEvent('contact-form'))
            ->setClientCustomId('visitor-123')
            ->setFormParams(['email' => 'user@example.com']);

        $event->validate();
        $this->assertTrue(true);
    }

    public function testValidateMissingClientIdentifier(): void
    {
        $event = (new FormSubmitEvent('contact-form'))
            ->setFormParams(['email' => 'user@example.com']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('client_id, user_id, or session_id is required');

        $event->validate();
    }

    public function testValidateMissingLabel(): void
    {
        $event = (new FormSubmitEvent(''))
            ->setClientCustomId('visitor-123')
            ->setFormParams(['email' => 'user@example.com']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('label');

        $event->validate();
    }

    public function testValidateMissingParams(): void
    {
        $event = (new FormSubmitEvent('contact-form'))
            ->setClientCustomId('visitor-123');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('params');

        $event->validate();
    }

    public function testValidateInvalidEmail(): void
    {
        $event = (new FormSubmitEvent('contact-form'))
            ->setClientCustomId('visitor-123')
            ->setFormParams(['email' => 'invalid-email']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid email');

        $event->validate();
    }

    public function testAddFormParam(): void
    {
        $event = (new FormSubmitEvent('contact-form'))
            ->setClientCustomId('visitor-123')
            ->addFormParam('email', 'user@example.com')
            ->addFormParam('name', 'John Doe');

        $array = $event->toArray();

        $this->assertEquals('user@example.com', $array['params']['email']);
        $this->assertEquals('John Doe', $array['params']['name']);
    }

    public function testWithEventSalt(): void
    {
        $event = (new FormSubmitEvent('contact-form'))
            ->setClientCustomId('visitor-123')
            ->setFormParams(['email' => 'user@example.com'])
            ->setEventSalt('unique-form-123');

        $array = $event->toArray();

        $this->assertEquals('unique-form-123', $array['eventSalt']);
    }
}

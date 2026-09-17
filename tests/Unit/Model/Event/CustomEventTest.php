<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\Event\CustomEvent;
use PHPUnit\Framework\TestCase;

/**
 * The PHP SDK's CustomEvent (Task 15).
 *
 * @covers \AxiTrace\Model\Event\CustomEvent
 */
final class CustomEventTest extends TestCase
{
    public function testEndpointIsTheDedupedCustomEndpoint(): void
    {
        $event = new CustomEvent('quote_requested');

        self::assertSame('/v1/custom-events', $event->getEndpoint());
        self::assertSame('custom', $event->getAction());
    }

    public function testSerializedPayloadMatchesTheV1Contract(): void
    {
        $event = new CustomEvent(
            'quote_requested',
            ['menu_name' => 'Salad', 'seats' => 5],
            149.5,
            'PLN',
            'tx-1',
            'event-uuid-1'
        );
        $event->setClientId('visitor-1');
        $event->setSessionId('session-1');

        $payload = $event->toArray();

        self::assertSame('quote_requested', $payload['event_name']);
        self::assertSame(['menu_name' => 'Salad', 'seats' => 5], $payload['properties']);
        self::assertSame('event-uuid-1', $payload['event_id'], 'the caller-supplied event_id drives the ingestion dedup');
        self::assertSame(149.5, $payload['value']);
        self::assertSame('PLN', $payload['currency']);
        self::assertSame('tx-1', $payload['transaction_id']);
        self::assertSame('visitor-1', $payload['client_id'], 'B4: the identity rides in the body');
        self::assertSame('session-1', $payload['session_id']);
    }

    public function testANullEventIdGeneratesAUuid(): void
    {
        $event = new CustomEvent('quote_requested');

        $payload = $event->toArray();
        $eventId = $payload['event_id'];

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $eventId,
            'the SDK generates the dedup event_id when the caller does not supply one (B4)'
        );
        self::assertSame($eventId, $payload['event_id'], 'stable across calls');
    }

    public function testAnInvalidKeyIsRejected(): void
    {
        $event = new CustomEvent('9-Bad Key');

        $this->expectException(\AxiTrace\Exception\ValidationException::class);
        $this->expectExceptionMessage('must start with a lowercase letter');

        $event->validate();
    }

    public function testAValidKeyPassesValidation(): void
    {
        $event = new CustomEvent('quote_requested');
        $event->validate();

        self::expectNotToPerformAssertions();
    }

    public function testMoreThanThirtyPropertiesAreRejected(): void
    {
        $properties = [];
        for ($i = 0; $i < 31; $i++) {
            $properties['prop_' . $i] = $i;
        }

        $event = new CustomEvent('quote_requested', $properties);

        $this->expectException(\AxiTrace\Exception\ValidationException::class);
        $this->expectExceptionMessage('at most 30');

        $event->validate();
    }

    public function testAValueBelowZeroIsRejected(): void
    {
        $event = new CustomEvent('quote_requested', [], -1.0, 'USD');

        $this->expectException(\AxiTrace\Exception\ValidationException::class);
        $this->expectExceptionMessage('at least 0');

        $event->validate();
    }

    public function testANonIsoCurrencyIsRejected(): void
    {
        $event = new CustomEvent('quote_requested', [], 10.0, 'pln');

        $this->expectException(\AxiTrace\Exception\ValidationException::class);
        $this->expectExceptionMessage('ISO 4217');

        $event->validate();
    }

    public function testALongTransactionIdIsRejected(): void
    {
        $event = new CustomEvent('quote_requested', [], 10.0, 'USD', str_repeat('a', 101));

        $this->expectException(\AxiTrace\Exception\ValidationException::class);
        $this->expectExceptionMessage('at most 100 characters');

        $event->validate();
    }
}

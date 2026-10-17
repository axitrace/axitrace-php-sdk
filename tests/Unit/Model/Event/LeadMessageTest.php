<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Model\Event;

use AxiTrace\Model\Event\CustomEvent;
use AxiTrace\Model\Event\EventInterface;
use AxiTrace\Model\Event\FormSubmitEvent;
use AxiTrace\Model\Event\StartTrialEvent;
use AxiTrace\Model\Event\SubscribeEvent;
use PHPUnit\Framework\TestCase;

/**
 * setMessage() on the four lead events: a root `message`, normalized, plain text.
 *
 * @covers \AxiTrace\Model\Event\LeadMessageTrait
 * @covers \AxiTrace\Model\Event\FormSubmitEvent
 * @covers \AxiTrace\Model\Event\SubscribeEvent
 * @covers \AxiTrace\Model\Event\StartTrialEvent
 * @covers \AxiTrace\Model\Event\CustomEvent
 */
final class LeadMessageTest extends TestCase
{
    /**
     * @return array<string, array{0: EventInterface}>
     */
    public function events(): array
    {
        return [
            'form submit' => [(new FormSubmitEvent('inquiry'))->setClientEmail('a@example.com')->setEmail('a@example.com')],
            'subscribe' => [(new SubscribeEvent('a@example.com'))->setClientId('c-1')],
            'start trial' => [(new StartTrialEvent('Pro'))->setClientId('c-1')],
            'custom' => [new CustomEvent('quote_requested', [], null, null, null, 'evt-1')],
        ];
    }

    /**
     * @dataProvider events
     */
    public function testMessageIsSentAtTheRootAsPlainText(EventInterface $event): void
    {
        /** @var FormSubmitEvent|SubscribeEvent|StartTrialEvent|CustomEvent $event */
        $event->setMessage("  Is it pink?\r\n<script>alert(1)</script>\u{202E}  ");

        $payload = $event->toArray();

        self::assertSame("Is it pink?\n<script>alert(1)</script>", $payload['message']);
        self::assertArrayNotHasKey('message', $payload['params'] ?? []);
        self::assertArrayNotHasKey('message', $payload['properties'] ?? []);
    }

    /**
     * @dataProvider events
     */
    public function testWithoutMessageNoKeyIsSent(EventInterface $event): void
    {
        self::assertArrayNotHasKey('message', $event->toArray());
    }

    /**
     * @dataProvider events
     */
    public function testWhitespaceOnlyMessageIsNotSent(EventInterface $event): void
    {
        /** @var FormSubmitEvent|SubscribeEvent|StartTrialEvent|CustomEvent $event */
        $event->setMessage(" \n\t ");

        self::assertArrayNotHasKey('message', $event->toArray());
        self::assertNull($event->getMessage());
    }
}

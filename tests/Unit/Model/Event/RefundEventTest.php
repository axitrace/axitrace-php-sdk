<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\Event\RefundEvent;
use PHPUnit\Framework\TestCase;

class RefundEventTest extends TestCase
{
    public function testEndpointAndAction(): void
    {
        $event = new RefundEvent('ORDER-1', 'RF-1', 10.0, 'EUR');

        $this->assertSame('/v1/refund', $event->getEndpoint());
        $this->assertSame('refund', $event->getAction());
    }

    public function testPayloadMatchesTheContract(): void
    {
        $event = new RefundEvent(
            'ORDER-1',
            'RF-1',
            59.98,
            'eur',
            new \DateTimeImmutable('2026-10-02T14:30:15.250+02:00')
        );
        $event->setCancellation()
            ->addLine(1, 49.99, 'SKU-1', 'woocommerce:1234')
            ->addLine(2, 9.99, null, 'woocommerce:55');

        $this->assertSame([
            'orderId' => 'ORDER-1',
            'refundId' => 'RF-1',
            'refundedAt' => '2026-10-02T12:30:15.250Z',
            'amount' => 59.98,
            'currency' => 'EUR',
            'isCancellation' => true,
            'lines' => [
                ['sku' => 'SKU-1', 'externalId' => 'woocommerce:1234', 'quantity' => 1, 'amount' => 49.99],
                ['externalId' => 'woocommerce:55', 'quantity' => 2, 'amount' => 9.99],
            ],
        ], $event->toArray());
        $event->validate();
    }

    public function testRefundWithoutLinesSendsAnEmptyListAndIsNotACancellation(): void
    {
        $event = new RefundEvent('ORDER-1', 'RF-2', 5.0, 'PLN');

        $data = $event->toArray();

        $this->assertFalse($data['isCancellation']);
        $this->assertSame('[]', json_encode($data['lines']));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $data['refundedAt']);
        $event->validate();
    }

    public function testSetLinesAcceptsArraysWithNumericStrings(): void
    {
        $event = new RefundEvent('ORDER-1', 'RF-3', 20.0, 'EUR');
        $event->setLines([['sku' => 'SKU-1', 'external_id' => 'shopware:x', 'quantity' => '2', 'amount' => '20']]);

        $this->assertSame(
            [['sku' => 'SKU-1', 'externalId' => 'shopware:x', 'quantity' => 2, 'amount' => 20.0]],
            $event->toArray()['lines']
        );
    }

    /**
     * @return array<string, array{0: RefundEvent, 1: string}>
     */
    public function invalidRefundProvider(): array
    {
        return [
            'empty order id' => [new RefundEvent(' ', 'RF', 1.0, 'EUR'), 'orderId'],
            'empty refund id' => [new RefundEvent('O', '', 1.0, 'EUR'), 'refundId'],
            'bad currency' => [new RefundEvent('O', 'RF', 1.0, 'EURO'), 'ISO 4217'],
            'negative amount' => [new RefundEvent('O', 'RF', -1.0, 'EUR'), 'amount'],
            'line without product id' => [(new RefundEvent('O', 'RF', 1.0, 'EUR'))->addLine(1, 1.0), 'sku'],
            'negative line quantity' => [(new RefundEvent('O', 'RF', 1.0, 'EUR'))->addLine(-1, 1.0, 'S'), 'quantity'],
            'negative line amount' => [(new RefundEvent('O', 'RF', 1.0, 'EUR'))->addLine(1, -1.0, 'S'), 'lines[0].amount'],
        ];
    }

    /**
     * @dataProvider invalidRefundProvider
     */
    public function testInvalidRefundFailsValidation(RefundEvent $event, string $message): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($message);

        $event->validate();
    }

    public function testSetLinesRejectsANonWholeQuantity(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('lines[0].quantity');

        (new RefundEvent('O', 'RF', 1.0, 'EUR'))->setLines([['sku' => 'S', 'quantity' => 1.5, 'amount' => 1]]);
    }
}

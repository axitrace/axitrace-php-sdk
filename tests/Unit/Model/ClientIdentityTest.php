<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Model;

use AxiTrace\Model\ClientIdentity;
use PHPUnit\Framework\TestCase;

class ClientIdentityTest extends TestCase
{
    public function testSetPhoneIsIncludedInToArray(): void
    {
        $client = (new ClientIdentity())->setPhone('+15551234567');

        $this->assertEquals(['phone' => '+15551234567'], $client->toArray());
    }

    public function testPhoneAloneSatisfiesHasIdentifier(): void
    {
        $client = (new ClientIdentity())->setPhone('+15551234567');

        $this->assertTrue($client->hasIdentifier());
    }

    public function testFromArrayReadsPhone(): void
    {
        $client = ClientIdentity::fromArray(['phone' => '+15551234567', 'customId' => 'visitor-123']);

        $this->assertEquals('+15551234567', $client->getPhone());
        $this->assertEquals('visitor-123', $client->getCustomId());
    }

    public function testAddressMatchKeysRoundTripThroughToArray(): void
    {
        $client = (new ClientIdentity())
            ->setFirstName('Ada')
            ->setLastName('Lovelace')
            ->setCity('Zurich')
            ->setState('ZH')
            ->setZip('8001')
            ->setCountry('CH');

        $this->assertEquals([
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'city' => 'Zurich',
            'state' => 'ZH',
            'zip' => '8001',
            'country' => 'CH',
        ], $client->toArray());
    }

    public function testFromArrayReadsAddressMatchKeys(): void
    {
        $client = ClientIdentity::fromArray([
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'city' => 'Zurich',
            'state' => 'ZH',
            'zip' => '8001',
            'country' => 'CH',
        ]);

        $this->assertEquals('Ada', $client->getFirstName());
        $this->assertEquals('Lovelace', $client->getLastName());
        $this->assertEquals('Zurich', $client->getCity());
        $this->assertEquals('ZH', $client->getState());
        $this->assertEquals('8001', $client->getZip());
        $this->assertEquals('CH', $client->getCountry());
    }

    /**
     * An address is demographic data, not an identifier: on its own Meta rejects such a
     * combination as "so broad that matching is not possible". It must never let an
     * otherwise anonymous client through the identifier check.
     */
    public function testAddressAloneDoesNotSatisfyHasIdentifier(): void
    {
        $client = (new ClientIdentity())
            ->setFirstName('Ada')
            ->setCity('Zurich')
            ->setCountry('CH');

        $this->assertFalse($client->hasIdentifier());
    }

    public function testWithoutAnyIdentifierHasIdentifierIsFalse(): void
    {
        $client = new ClientIdentity();

        $this->assertFalse($client->hasIdentifier());
        $this->assertNull($client->getPhone());
    }
}

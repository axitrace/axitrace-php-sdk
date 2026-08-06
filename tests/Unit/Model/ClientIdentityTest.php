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

    public function testWithoutAnyIdentifierHasIdentifierIsFalse(): void
    {
        $client = new ClientIdentity();

        $this->assertFalse($client->hasIdentifier());
        $this->assertNull($client->getPhone());
    }
}

<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\Event\SearchEvent;
use PHPUnit\Framework\TestCase;

class SearchEventTest extends TestCase
{
    public function testGetEndpoint(): void
    {
        $event = new SearchEvent('test query');

        $this->assertEquals('/v1/search', $event->getEndpoint());
    }

    public function testGetAction(): void
    {
        $event = new SearchEvent('test query');

        $this->assertEquals('search', $event->getAction());
    }

    public function testToArrayMinimal(): void
    {
        $event = (new SearchEvent('blue shoes'))
            ->setClientId('visitor-123');

        $array = $event->toArray();

        $this->assertEquals('visitor-123', $array['client_id']);
        $this->assertEquals('blue shoes', $array['search_term']);
    }

    public function testToArrayFull(): void
    {
        $event = (new SearchEvent('wireless headphones'))
            ->setClientId('visitor-123')
            ->setResultsCount(42)
            ->setCategory('Electronics')
            ->setFilters(['brand' => 'Sony', 'price_min' => 50, 'price_max' => 200])
            ->setSortBy('price_asc')
            ->setPage(2);

        $array = $event->toArray();

        $this->assertEquals('wireless headphones', $array['search_term']);
        $this->assertArrayHasKey('params', $array);
        $this->assertEquals(42, $array['params']['results_count']);
        $this->assertEquals('Electronics', $array['params']['category']);
        $this->assertEquals('Sony', $array['params']['filters']['brand']);
        $this->assertEquals('price_asc', $array['params']['sort_by']);
        $this->assertEquals(2, $array['params']['page']);
    }

    public function testValidateSuccess(): void
    {
        $event = (new SearchEvent('test query'))
            ->setClientId('visitor-123');

        $event->validate();
        $this->assertTrue(true);
    }

    public function testValidateMissingUserIdentifier(): void
    {
        $event = new SearchEvent('test query');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('client_id, user_id, or session_id is required');

        $event->validate();
    }

    public function testValidateEmptySearchTerm(): void
    {
        $event = (new SearchEvent(''))
            ->setClientId('visitor-123');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('search_term');

        $event->validate();
    }

    public function testWithFacebookParams(): void
    {
        $event = (new SearchEvent('test query'))
            ->setClientId('visitor-123')
            ->setFbp('fb.1.123456789.987654321');

        $array = $event->toArray();

        $this->assertArrayHasKey('params', $array);
        $this->assertEquals('fb.1.123456789.987654321', $array['params']['fbp']);
    }
}

<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Api;

use AxiTrace\Api\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testFromJsonSuccess(): void
    {
        $json = '{"success": true, "eventId": "evt-123", "action": "page_view"}';
        $response = Response::fromJson($json, 200);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('evt-123', $response->getEventId());
        $this->assertEquals('page_view', $response->getAction());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull($response->getError());
    }

    public function testFromJsonError(): void
    {
        $json = '{"success": false, "error": "Invalid request"}';
        $response = Response::fromJson($json, 400);

        $this->assertFalse($response->isSuccess());
        $this->assertEquals('Invalid request', $response->getError());
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testFromJsonInvalidJson(): void
    {
        $response = Response::fromJson('not json', 200);

        // Should handle gracefully
        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->getEventId());
    }

    public function testSuccess(): void
    {
        $response = Response::success('evt-456', 'transaction');

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('evt-456', $response->getEventId());
        $this->assertEquals('transaction', $response->getAction());
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testError(): void
    {
        $response = Response::error('Something went wrong', 500);

        $this->assertFalse($response->isSuccess());
        $this->assertEquals('Something went wrong', $response->getError());
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testGet(): void
    {
        $json = '{"success": true, "eventId": "evt-789", "custom_field": "custom_value"}';
        $response = Response::fromJson($json, 200);

        $this->assertEquals('custom_value', $response->get('custom_field'));
        $this->assertEquals('default', $response->get('non_existent', 'default'));
    }

    public function testGetRawData(): void
    {
        $json = '{"success": true, "eventId": "evt-101", "action": "search"}';
        $response = Response::fromJson($json, 200);

        $rawData = $response->getRawData();

        $this->assertIsArray($rawData);
        $this->assertTrue($rawData['success']);
        $this->assertEquals('evt-101', $rawData['eventId']);
    }

    public function testToArray(): void
    {
        $response = Response::success('evt-202', 'subscribe');
        $array = $response->toArray();

        $this->assertArrayHasKey('success', $array);
        $this->assertArrayHasKey('status_code', $array);
        $this->assertArrayHasKey('event_id', $array);
        $this->assertArrayHasKey('action', $array);
        $this->assertArrayHasKey('error', $array);

        $this->assertTrue($array['success']);
        $this->assertEquals(200, $array['status_code']);
        $this->assertEquals('evt-202', $array['event_id']);
        $this->assertEquals('subscribe', $array['action']);
        $this->assertNull($array['error']);
    }

    public function testStatusCodeDeterminesSuccess(): void
    {
        // 2xx should be success
        $this->assertTrue(Response::fromJson('{}', 200)->isSuccess());
        $this->assertTrue(Response::fromJson('{}', 201)->isSuccess());
        $this->assertTrue(Response::fromJson('{}', 299)->isSuccess());

        // 4xx should be failure
        $this->assertFalse(Response::fromJson('{}', 400)->isSuccess());
        $this->assertFalse(Response::fromJson('{}', 401)->isSuccess());
        $this->assertFalse(Response::fromJson('{}', 404)->isSuccess());

        // 5xx should be failure
        $this->assertFalse(Response::fromJson('{}', 500)->isSuccess());
        $this->assertFalse(Response::fromJson('{}', 503)->isSuccess());
    }
}

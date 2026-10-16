<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Model\Event;

use PHPUnit\Framework\TestCase;

/**
 * Reads the locked lead wire fixtures of the AxiTrace monorepo
 * (ingestion-api/testdata/leads/server/, contract in docs/claude/leads.md).
 *
 * The SDK is a submodule of that monorepo; in a standalone checkout the fixtures are
 * absent and the fixture comparisons are skipped with a message saying so.
 */
trait LeadWireFixtures
{
    /**
     * @return array<string, mixed>
     */
    private static function leadFixture(string $name): array
    {
        $path = dirname(__DIR__, 5) . '/ingestion-api/testdata/leads/server/' . $name . '.json';
        if (!is_file($path)) {
            TestCase::markTestSkipped('Lead wire fixture not found (standalone SDK checkout): ' . $path);
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        TestCase::assertIsArray($decoded, 'Unreadable lead wire fixture: ' . $path);

        return $decoded;
    }

    /**
     * Compare two payloads independently of associative key order, strictly on values and types.
     *
     * @param array<mixed> $expected
     * @param array<mixed> $actual
     */
    private static function assertSamePayload(array $expected, array $actual, string $message = ''): void
    {
        TestCase::assertSame(self::sortKeys($expected), self::sortKeys($actual), $message);
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private static function sortKeys(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sortKeys($value);
            }
        }

        if (array_keys($data) !== range(0, count($data) - 1)) {
            ksort($data);
        }

        return $data;
    }
}

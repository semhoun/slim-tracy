<?php

declare(strict_types=1);

namespace Tests\SlimTracy\Profiling;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SlimTracy\Profiling\BrowserTelemetry;

/** Exercises the transport-independent telemetry allowlist and numeric bounds. */
final class BrowserTelemetryTest extends TestCase
{
    /** Keep only supported browser declarations and remove URL secrets. */
    public function testKeepsOnlyBoundedAllowlistedFields(): void
    {
        $profileId = str_repeat('a', 32);
        $body = json_encode([
            'id' => $profileId,
            'schemaVersion' => 1,
            'durationMs' => 12.5,
            'outcome' => 'complete',
            'method' => 'POST',
            'path' => 'https://api.example.test/items?token=secret#fragment',
            'status' => 201,
            'authorization' => 'secret',
            'body' => ['password' => 'secret'],
        ], JSON_THROW_ON_ERROR);

        self::assertSame([
            'id' => $profileId,
            'browser' => [
                'durationMs' => 12.5,
                'outcome' => 'complete',
                'method' => 'POST',
                'path' => '/items',
                'status' => 201,
            ],
        ], (new BrowserTelemetry())->parse($body));
    }

    /** Invalid optional declarations must not expand the exported schema. */
    public function testOmitsInvalidOptionalFields(): void
    {
        $body = json_encode([
            'id' => str_repeat('b', 32),
            'durationMs' => 0,
            'outcome' => '<script>',
            'method' => [],
            'path' => [],
            'status' => 600,
        ], JSON_THROW_ON_ERROR);

        self::assertSame([
            'id' => str_repeat('b', 32),
            'browser' => ['durationMs' => 0],
        ], (new BrowserTelemetry())->parse($body));
    }

    /** The maximum supported duration is inclusive and a missing status stays unknown. */
    public function testAcceptsMaximumDurationWithoutInventingStatus(): void
    {
        $body = json_encode(['id' => str_repeat('c', 32), 'durationMs' => 86400000], JSON_THROW_ON_ERROR);
        self::assertSame([
            'id' => str_repeat('c', 32),
            'browser' => ['durationMs' => 86400000, 'status' => null],
        ], (new BrowserTelemetry())->parse($body));
    }

    /**
     * Invalid correlation, schema or timing data must never reach storage.
     *
     * @param string $body Invalid JSON declaration to reject.
     */
    #[DataProvider('invalidBodies')]
    public function testRejectsInvalidTelemetry(string $body): void
    {
        self::assertNull((new BrowserTelemetry())->parse($body));
    }

    /**
     * Cover malformed JSON, invalid identities and non-finite or out-of-range durations.
     *
     * @return array<string, array{string}> Invalid declarations with descriptive dataset names.
     */
    public static function invalidBodies(): array
    {
        $identity = '"id":"' . str_repeat('a', 32) . '"';
        return [
            'invalid JSON' => ['{'],
            'scalar JSON' => ['null'],
            'missing identity' => ['{"durationMs":1}'],
            'invalid identity' => ['{"id":"../profile","durationMs":1}'],
            'missing duration' => ['{' . $identity . '}'],
            'string duration' => ['{' . $identity . ',"durationMs":"1"}'],
            'negative duration' => ['{' . $identity . ',"durationMs":-0.1}'],
            'excessive duration' => ['{' . $identity . ',"durationMs":86400001}'],
            'non-finite duration' => ['{' . $identity . ',"durationMs":1e999}'],
            'unsupported schema' => ['{' . $identity . ',"durationMs":1,"schemaVersion":2}'],
        ];
    }
}

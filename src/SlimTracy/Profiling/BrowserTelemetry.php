<?php

declare(strict_types=1);

namespace SlimTracy\Profiling;

/** Decodes the bounded browser schema independently of HTTP routing and storage. */
final class BrowserTelemetry
{
    /**
     * Read only explicitly supported correlation and browser measurement fields.
     *
     * @param string $body Already size-limited JSON telemetry.
     *
     * @return array{id: string, browser: array<string, mixed>}|null Validated declaration.
     */
    public function parse(string $body): ?array
    {
        $data = json_decode($body, true, 8);
        if (! $this->valid($data)) {
            return null;
        }

        return ['id' => $data['id'], 'browser' => $this->fields($data)];
    }

    /** Validate the schema and server-generated correlation identity. */
    private function valid(mixed $data): bool
    {
        return is_array($data) && is_string($data['id'] ?? null)
            && preg_match('/^[a-f0-9]{32}$/D', $data['id']) === 1
            && $this->validDuration($data['durationMs'] ?? null)
            && (! isset($data['schemaVersion']) || $data['schemaVersion'] === 1);
    }

    /** Accept only finite, non-negative elapsed milliseconds within one day. */
    private function validDuration(mixed $duration): bool
    {
        return (is_int($duration) || is_float($duration)) && is_finite((float) $duration)
            && $duration >= 0 && $duration <= 86400000;
    }

    /**
     * Allowlist browser fields and use the shared, stateless text sanitizer.
     *
     * @param array<string, mixed> $data Validated telemetry envelope.
     *
     * @return array<string, mixed> Bounded browser declarations without arbitrary fields.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function fields(array $data): array
    {
        $browser = ['durationMs' => $data['durationMs']];
        if (in_array($data['outcome'] ?? null, ['complete', 'network-error', 'aborted'], true)) {
            $browser['outcome'] = $data['outcome'];
        }

        if (is_string($data['method'] ?? null)) {
            $browser['method'] = StructuredExporter::text($data['method'], 32);
        }

        if (is_string($data['path'] ?? null)) {
            $path = parse_url($data['path'], PHP_URL_PATH);
            $browser['path'] = StructuredExporter::text(is_string($path) ? $path : '', 1024);
        }

        $status = $data['status'] ?? null;
        if ($status === null || (is_int($status) && $status >= 100 && $status <= 599)) {
            $browser['status'] = $status;
        }

        return $browser;
    }
}

<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Support;

use Illuminate\Contracts\Config\Repository;

/**
 * Typed reads of the `laranail.pdf.*` block.
 *
 * `Repository::get()` returns `mixed`, and the alternative to this class is a
 * `(string)` cast at every call site — which is not a check, it is a way of
 * turning a misconfigured array into the string "Array" and carrying on. Each
 * method here falls back to the default when the value is not the type the
 * caller needs.
 */
final readonly class PdfConfig
{
    public function __construct(private Repository $config) {}

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        return is_bool($value) ? $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->get($key);

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function array(string $key): array
    {
        $value = $this->get($key);

        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @return list<string>
     */
    public function strings(string $key): array
    {
        $value = $this->get($key);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    /**
     * A path that exists, or null.
     *
     * The existence check is here rather than at the call site because the one
     * consumer — the Gotenberg CA bundle — must fall back to the system trust
     * store when the configured path is wrong, never to no verification at all.
     */
    public function existingPath(string $key): ?string
    {
        $path = $this->string($key);

        return $path !== '' && is_file($path) ? $path : null;
    }

    private function get(string $key): mixed
    {
        return $this->config->get('laranail.pdf.'.$key);
    }
}

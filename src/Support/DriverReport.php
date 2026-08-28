<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Support;

use Simtabi\Laranail\Pdf\Enums\Capability;
use Illuminate\Contracts\Support\Arrayable;

/**
 * What the doctor command found out about one driver.
 *
 * A type rather than a nested array, because the alternative was every consumer
 * re-establishing what `$row['probe']['ok']` might be — `null` for "not probed",
 * `false` for "probed and failed", `true` for fine. Three states in an untyped
 * array is exactly where a `!$probe['ok']` conflates the first two.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class DriverReport implements Arrayable
{
    /**
     * @param list<Capability> $capabilities
     */
    public function __construct(
        public string $name,
        public bool $available,
        public ?string $reason = null,
        public array $capabilities = [],
        public ?bool $probeOk = null,
        public int $probeBytes = 0,
        public int $probeMs = 0,
        public ?string $probeError = null,
    ) {}

    public static function unresolvable(string $name, string $reason): self
    {
        return new self(name: $name, available: false, reason: $reason);
    }

    public function withProbe(bool $ok, int $bytes = 0, int $ms = 0, ?string $error = null): self
    {
        return new self(
            name: $this->name,
            available: $this->available,
            reason: $this->reason,
            capabilities: $this->capabilities,
            probeOk: $ok,
            probeBytes: $bytes,
            probeMs: $ms,
            probeError: $error,
        );
    }

    public function wasProbed(): bool
    {
        return $this->probeOk !== null;
    }

    /**
     * @return list<string>
     */
    public function capabilityValues(): array
    {
        return array_map(static fn (Capability $c): string => $c->value, $this->capabilities);
    }

    public function describeCapabilities(): string
    {
        $values = $this->capabilityValues();

        return $values === [] ? '—' : implode(', ', $values);
    }

    public function describeProbe(): string
    {
        return match ($this->probeOk) {
            null  => '—',
            false => 'FAILED',
            true  => sprintf('ok (%d bytes, %dms)', $this->probeBytes, $this->probeMs),
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $report = [
            'available'    => $this->available,
            'reason'       => $this->reason,
            'capabilities' => $this->capabilityValues(),
        ];

        if ($this->wasProbed()) {
            $report['probe'] = array_filter([
                'ok'    => $this->probeOk,
                'bytes' => $this->probeBytes,
                'ms'    => $this->probeMs,
                'error' => $this->probeError,
            ], static fn (mixed $value): bool => $value !== null);
        }

        return $report;
    }
}

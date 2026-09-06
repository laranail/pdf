<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Drivers;

use Simtabi\Laranail\Pdf\Enums\Capability;
use Simtabi\Laranail\Pdf\Contracts\PdfDriver;
use Simtabi\Laranail\Pdf\Exceptions\DriverUnavailable;

/**
 * The parts every driver implements identically.
 *
 * `capabilities()` and `supports()` are final, and derive from `instanceof`
 * rather than from anything a subclass declares. A driver that could override
 * them could claim a capability it has not implemented — which is the one thing
 * this whole seam exists to make impossible.
 */
abstract class Driver implements PdfDriver
{
    /** @return list<Capability> */
    final public function capabilities(): array
    {
        return Capability::of($this);
    }

    final public function supports(Capability $capability): bool
    {
        return $capability->isImplementedBy($this);
    }

    public function isAvailable(): bool
    {
        return $this->unavailableReason() === null;
    }

    /**
     * @throws DriverUnavailable
     */
    protected function assertAvailable(): void
    {
        $reason = $this->unavailableReason();

        if ($reason !== null) {
            throw DriverUnavailable::because($this->name(), $reason);
        }
    }
}

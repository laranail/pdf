<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Contracts;

use Simtabi\Laranail\Pdf\Enums\Capability;

/**
 * What every PDF driver can answer, regardless of what it can render.
 *
 * The rendering methods are deliberately **not** here. They live on the four
 * capability interfaces in `Contracts\Capabilities`, and a driver declares what
 * it does by implementing them. That is the whole design: `supports()` is
 * derived from `instanceof`, so a driver physically cannot claim a capability
 * it has not implemented, and cannot implement one without claiming it.
 *
 * The alternative — a `supports()` returning a hand-written list — puts the
 * claim and the implementation in two places, and they drift the first time
 * someone adds a method.
 */
interface PdfDriver
{
    /**
     * The name this driver is registered and configured under.
     */
    public function name(): string;

    /**
     * Whether the driver can be used right now.
     *
     * False covers both "the optional package is not installed" and "it is, but
     * nothing has configured it" — a doctor command wants to tell those apart,
     * which is what `unavailableReason()` is for.
     */
    public function isAvailable(): bool;

    /**
     * Why `isAvailable()` said no, or null when it said yes.
     */
    public function unavailableReason(): ?string;

    /**
     * @return list<Capability>
     */
    public function capabilities(): array;

    public function supports(Capability $capability): bool;
}

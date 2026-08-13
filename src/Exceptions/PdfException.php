<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Exceptions;

use RuntimeException;

/**
 * Base for everything this package throws.
 *
 * Its existence is the point of the package's error contract: **no vendor
 * exception escapes.** A caller catches `PdfException` and is done, whether the
 * failure came from Gotenberg's `GotenbergApiErrored`, Dompdf's internals, or a
 * driver that is not installed. Optional dependencies whose exception types leak
 * are not optional in any useful sense — you cannot write a `catch` for a class
 * that may not exist.
 */
abstract class PdfException extends RuntimeException
{
    /** @var array<string, mixed> */
    protected array $context = [];

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }
}

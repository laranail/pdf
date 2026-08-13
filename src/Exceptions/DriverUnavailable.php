<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Pdf\Exceptions;

/**
 * The driver exists but cannot run — usually an optional package that is not
 * installed, or one that is but has not been pointed at anything.
 */
final class DriverUnavailable extends PdfException
{
    public static function because(string $driver, string $reason): self
    {
        $e = new self("The [{$driver}] PDF driver is unavailable: {$reason}", 6003);
        $e->context = ['driver' => $driver, 'reason' => $reason];

        return $e;
    }

    public static function missingPackage(string $driver, string $package): self
    {
        return self::because($driver, "{$package} is not installed. Run `composer require {$package}`.");
    }
}
